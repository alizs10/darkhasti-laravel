<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
        ]);

        // $token = JWTAuth::fromUser($user);
        // $refreshToken = $this->createRefreshToken($user);

        return $this->successResponse([
            'user' => $user->only(['id', 'username']),
            // 'access_token' => $token,
            // 'refresh_token' => $refreshToken,
            // 'token_type' => 'bearer',
            // 'expires_in' => config('jwt.ttl') * 60,
            // 'refresh_expires_in' => config('jwt.refresh_ttl', 20160) * 60, // 14 days default
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
            // Get refresh token from Authorization header
            $refreshToken = null;
            $authHeader = $request->header('Authorization');

            if ($authHeader && preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $refreshToken = $matches[1];
            }

            if (! $refreshToken) {
                return $this->errorResponse('Refresh token not provided in Authorization header', 401);
            }

            // Verify refresh token
            $refreshTokenData = $this->verifyRefreshToken($refreshToken);

            // dd($refreshTokenData);

            if (! $refreshTokenData || $refreshTokenData['expires_at'] < now()) {
                return $this->errorResponse('Invalid or expired refresh token', 401);
            }

            // Get user and generate new tokens
            $user = User::find($refreshTokenData['user_id']);
            if (! $user) {
                return $this->errorResponse('User not found', 401);
            }

            // Invalidate the old refresh token (one-time use)
            $this->invalidateRefreshToken($refreshToken);

            // Generate new tokens
            $newToken = JWTAuth::fromUser($user);
            $newRefreshToken = $this->createRefreshToken($user);

            return $this->successResponse([
                'access_token' => $newToken,
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
            // Invalidate access token
            JWTAuth::parseToken()->invalidate();

            // Invalidate refresh token if provided
            if ($request->has('refresh_token')) {
                $this->invalidateRefreshToken($request->refresh_token);
            }

            return $this->successResponse(
                null,
                'Successfully logged out'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Could not log out', 401);
        }
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = auth()->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Current password is incorrect', 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        // Invalidate all existing tokens after password change for security
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

    /**
     * Create a refresh token (store in database or encode with claims)
     */
    protected function createRefreshToken($user)
    {

        $exp = now()->addMinutes(config('jwt.refresh_ttl', 20160));
        // Return a JWT with longer expiry instead of random string
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

    /**
     * Verify a refresh token
     */
    protected function verifyRefreshToken($token)
    {
        try {
            // Verify the JWT signature and get payload
            $payload = JWTAuth::setToken($token)->getPayload();

            // Check token type
            if ($payload->get('type') !== 'refresh') {
                return null;
            }

            // Check if token exists in DB and is not revoked
            $refreshToken = RefreshToken::where('token', $token)
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->first();

            if (! $refreshToken) {
                return null;
            }

            return [
                'user_id' => $payload->get('sub'),
                'expires_at' => $refreshToken->expires_at,
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Invalidate a refresh token
     */
    protected function invalidateRefreshToken($token)
    {
        RefreshToken::where('token', $token)
            ->update(['revoked' => true]);
    }

    /**
     * Invalidate all user tokens (for security)
     */
    protected function invalidateAllUserTokens($user)
    {
        RefreshToken::where('user_id', $user->id)
            ->update(['revoked' => true]);
    }
}
