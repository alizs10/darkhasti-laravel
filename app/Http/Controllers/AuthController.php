<?php

namespace App\Http\Controllers;

use App\ApiResponse;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        if (! auth()->attempt($credentials)) {
            return $this->errorResponse(
                'Invalid credentials',
                401
            );
        }

        $user = auth()->user();

        $token = $this->createAccessToken($user);
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
        $authHeader = $request->header('Authorization');

        if (
            ! $authHeader ||
            ! preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)
        ) {
            return $this->errorResponse('Refresh token not provided', 401);
        }

        $token = $matches[1];

        try {
            // lockForUpdate is enough, DB::transaction can cause deadlocks
            // when Node.js polls and retries quickly.
            $record = RefreshToken::where('token', $token)->lockForUpdate()->first();

            if (! $record) {
                return $this->errorResponse('Invalid refresh token', 401);
            }

            $verified = $this->verifyRefreshToken($token);

            if (! $verified) {
                return $this->errorResponse('Invalid or expired refresh token', 401);
            }

            $user = User::find($verified['user_id']);

            if (! $user) {
                return $this->errorResponse('User not found', 401);
            }

            // If token was reused within the grace period, return the replacement token
            if ($verified['reused']) {
                return $this->successResponse([
                    'access_token' => $this->createAccessToken($user),
                    'refresh_token' => $verified['refresh_token'],
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                    'refresh_expires_in' => config('jwt.refresh_ttl') * 60,
                ]);
            }

            // Generate new tokens
            $newAccessToken = $this->createAccessToken($user);
            $newRefreshToken = $this->createRefreshToken($user);

            // Invalidate the old token and link the new one
            $this->invalidateRefreshToken($token, $newRefreshToken);

            return $this->successResponse([
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'refresh_expires_in' => config('jwt.refresh_ttl') * 60,
            ]);

        } catch (\Throwable $e) {
            logger()->error($e);

            return $this->errorResponse('Could not refresh token', 401);
        }
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::parseToken()->invalidate();

            $user = auth()->user();

            if ($user) {
                $this->invalidateAllUserTokens($user);
            }

            return $this->successResponse(null, 'Successfully logged out');
        } catch (\Exception $e) {
            return $this->errorResponse('Could not log out: '.$e, 401);
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $validated = $request->validated();

        $user = auth()->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('کلمه عبور فعلی اشتباه است', 422);
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

    protected function createRefreshToken(User $user): string
    {
        $ttl = JWTAuth::factory()->getTTL();
        JWTAuth::factory()->setTTL(config('jwt.refresh_ttl'));

        $token = JWTAuth::claims(['type' => 'refresh'])->fromUser($user);

        JWTAuth::factory()->setTTL($ttl); // restore default TTL for subsequent access token creation

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes(config('jwt.refresh_ttl')),
        ]);

        return $token;
    }

    protected function verifyRefreshToken(string $token): ?array
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();

            if ($payload->get('type') !== 'refresh') {
                return null;
            }

            $refreshToken = RefreshToken::where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            if (! $refreshToken) {
                return null;
            }

            if (! $refreshToken->revoked) {
                return [
                    'user_id' => $payload->get('sub'),
                    'reused' => false,
                ];
            }

            $withinGrace =
                $refreshToken->revoked_at &&
                $refreshToken->revoked_at
                    ->copy()
                    ->addSeconds(30) // Increased to 30s for Node Redis polling
                    ->isFuture();

            if (
                $withinGrace &&
                $refreshToken->replacement_refresh_token
            ) {
                $replacement = RefreshToken::where(
                    'token',
                    $refreshToken->replacement_refresh_token
                )
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())
                    ->first();

                if (! $replacement) {
                    return null;
                }

                return [
                    'user_id' => $replacement->user_id,
                    'reused' => true,
                    'refresh_token' => $replacement->token,
                ];
            }

            return null;
        } catch (\Throwable $e) {
            logger()->error($e);

            return null;
        }
    }

    protected function invalidateRefreshToken(
        string $token,
        ?string $replacementRefreshToken = null
    ): void {
        RefreshToken::where('token', $token)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'replacement_refresh_token' => $replacementRefreshToken,
            ]);
    }

    protected function invalidateAllUserTokens($user)
    {
        RefreshToken::where('user_id', $user->id)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'replacement_refresh_token' => null,
            ]);
    }

    protected function createAccessToken(User $user): string
    {
        return JWTAuth::claims([
            'type' => 'access',
        ])->fromUser($user);
    }
}
