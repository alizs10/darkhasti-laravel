<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
        ]);

        return $this->successResponse([
            'user' => $user->only(['id', 'username']),
        ], 'Registration successful', 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'username' => $validated['username'],
            'password' => $validated['password'],
        ];

        if (! $token = JWTAuth::attempt($credentials)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        $user = auth()->user();
        $refreshToken = $this->createRefreshToken($user);

        return $this->successResponse([
            'user' => $user?->only(['id', 'username']),
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'refresh_expires_in' => config('jwt.refresh_ttl', 20160) * 60,
        ], 'Login successful');
    }

    public function refresh(Request $request)
    {
        try {
            $refreshToken = null;
            $authHeader = $request->header('Authorization');

            if ($authHeader && preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $refreshToken = $matches[1];
            }

            if (! $refreshToken) {
                return $this->errorResponse('Refresh token not provided in Authorization header', 401);
            }

            // Verify refresh token — also checks the reuse grace window
            $refreshTokenData = $this->verifyRefreshToken($refreshToken);

            if (! $refreshTokenData) {
                return $this->errorResponse('Invalid or expired refresh token', 401);
            }

            // If this token was already revoked but within the grace window,
            // return the previously issued tokens instead of creating new ones
            if ($refreshTokenData['reused']) {
                return $this->successResponse([
                    'access_token' => $refreshTokenData['access_token'],
                    'refresh_token' => $refreshTokenData['refresh_token'],
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                    'refresh_expires_in' => config('jwt.refresh_ttl', 20160) * 60,
                ], 'Token refreshed successfully (reused)');
            }

            $user = User::find($refreshTokenData['user_id']);
            if (! $user) {
                return $this->errorResponse('User not found', 401);
            }

            // Generate new tokens
            // $newAccessToken = JWTAuth::fromUser($user);
            // $newRefreshToken = $this->createRefreshToken($user);
            // Generate new tokens — clear any lingering custom claims first
            $newAccessToken = JWTAuth::customClaims([])->fromUser($user);
            $newRefreshToken = $this->createRefreshToken($user);

            $this->invalidateRefreshToken($refreshToken, $newAccessToken, $newRefreshToken);

            return $this->successResponse([
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'refresh_expires_in' => config('jwt.refresh_ttl', 20160) * 60,
            ], 'Token refreshed successfully');

        } catch (TokenBlacklistedException $e) {
            return $this->errorResponse('Token has been blacklisted', 401);
        } catch (TokenExpiredException $e) {
            return $this->errorResponse('Access token has expired, use refresh token', 401);
        } catch (TokenInvalidException $e) {
            return $this->errorResponse('Token is invalid', 401);
        } catch (\Exception $e) {
            return $this->errorResponse('Could not refresh token: '.$e->getMessage(), 401);
        }
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::parseToken()->invalidate();

            if ($request->has('refresh_token')) {
                $this->invalidateRefreshToken($request->refresh_token);
            }

            return $this->successResponse(null, 'Successfully logged out');
        } catch (\Exception $e) {
            return $this->errorResponse('Could not log out', 401);
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $validated = $request->validated();

        $user = auth()->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Current password is incorrect', 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        $this->invalidateAllUserTokens($user);

        return $this->successResponse(
            null,
            'Password changed successfully. Please login again.'
        );
    }

    public function checkUsername(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:99'],
        ]);

        $username = $validated['username'];
        $exists = User::where('username', $username)->exists();

        return $this->successResponse(
            ['is_available' => ! $exists],
            $exists ? 'Username is already taken.' : 'Username is available.'
        );
    }

    protected function createRefreshToken($user)
    {
        $exp = now()->addMinutes(config('jwt.refresh_ttl', 20160));

        $generatedToken = JWTAuth::customClaims([
            'type' => 'refresh',
            'user_id' => $user->id,
            'exp' => $exp->timestamp,
        ])->fromUser($user);

        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => $generatedToken,
            'expires_at' => $exp,
        ]);

        return $refreshToken->token;
    }

    protected function verifyRefreshToken($token)
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();

            if ($payload->get('type') !== 'refresh') {
                return null;
            }

            // First: check if token is still valid (not revoked)
            $refreshToken = RefreshToken::where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            if (! $refreshToken) {
                return null;
            }

            // Token is still active — valid, not yet revoked
            if (! $refreshToken->revoked) {
                return [
                    'user_id' => $payload->get('sub'),
                    'expires_at' => $refreshToken->expires_at,
                    'reused' => false,
                ];
            }

            // Token is revoked — check if it's within the grace window (10 seconds)
            // and has replacement tokens stored (meaning WE revoked it, not a logout)
            $withinGrace = $refreshToken->revoked_at &&
                now()->diffInSeconds($refreshToken->revoked_at) <= 10;

            if ($withinGrace && $refreshToken->replacement_access_token) {
                return [
                    'user_id' => $payload->get('sub'),
                    'expires_at' => $refreshToken->expires_at,
                    'reused' => true,
                    'access_token' => $refreshToken->replacement_access_token,
                    'refresh_token' => $refreshToken->replacement_refresh_token,
                ];
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Invalidate a refresh token, optionally storing replacement tokens
     * so concurrent requests within the grace window can reuse them.
     */
    protected function invalidateRefreshToken($token, $newAccessToken = null, $newRefreshToken = null)
    {
        RefreshToken::where('token', $token)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'replacement_access_token' => $newAccessToken,
                'replacement_refresh_token' => $newRefreshToken,
            ]);
    }

    protected function invalidateAllUserTokens($user)
    {
        RefreshToken::where('user_id', $user->id)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'replacement_access_token' => null,
                'replacement_refresh_token' => null,
            ]);
    }
}
