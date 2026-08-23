<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\Auth\UserResource;
use App\Models\User;
use App\Models\LoginHistory;
use App\Models\FailedLoginAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // SECURITY: Check if registration is allowed (optional)
        if (config('app.registration_enabled') === false) {
            return response()->json(['message' => 'Registration is currently disabled'], 403);
        }

        // SECURITY: Check if email is from disposable domain (optional)
        if ($this->isDisposableEmail($request->email)) {
            return response()->json(['message' => 'Disposable email addresses are not allowed'], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(), // TODO: require real verification once mail is configured
            'is_active' => true,
            'is_banned' => false,
            'last_login_at' => null,
            'last_login_ip' => null,
        ]);

        // SECURITY: Log registration
        Log::info('New user registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login a user with enhanced security.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // SECURITY 1: Rate limiting
        $key = 'login_attempts_' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) { // 5 attempts per minute
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, 60); // Hit counter, expires in 60 seconds

        // SECURITY 2: Find user
        $user = User::where('email', $request->email)->first();

        // SECURITY 3: Account checks before password verification
        if ($user) {
            // Check if banned
            if ($user->is_banned) {
                return response()->json([
                    'message' => 'Your account has been banned. Please contact support.',
                ], 403);
            }

            // Check if inactive
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Your account is inactive. Please contact support.',
                ], 403);
            }
        }

        // SECURITY 4: Verify password
        if (!$user || !Hash::check($request->password, $user->password)) {
            // Log failed attempt
            $this->logFailedAttempt($request->email, $request->ip());

            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // SECURITY 5: Check if account was locked due to too many failed attempts
        if ($this->isAccountLocked($user)) {
            return response()->json([
                'message' => 'Your account has been locked due to multiple failed attempts. Please try again after 15 minutes.',
                'locked_until' => $user->locked_until,
            ], 423); // Locked
        }

        // SECURITY 6: Revoke old tokens if needed (optional)
        if (config('auth.single_session')) {
            $user->tokens()->delete(); // Force single session
        }

        // SECURITY 7: Generate token with expiry
        $token = $user->createToken('auth_token_' . Str::random(10), ['*'], now()->addDays(7))->plainTextToken;

        // SECURITY 8: Update user login details
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_login_agent' => $request->userAgent(),
            'email_verified_at' => $user->email_verified_at ?? now(), // Auto verify on login
        ]);

        // SECURITY 9: Log successful login
        $this->logSuccessfulLogin($user, $request);

        // SECURITY 10: Clear failed attempts after successful login
        $this->clearFailedAttempts($user);

        // SECURITY 11: Reset rate limiter on success
        RateLimiter::clear($key);

        return response()->json([
            'message' => 'User logged in successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 604800, // 7 days in seconds
            'user' => new UserResource($user),
            'requires_2fa' => $user->two_factor_enabled ?? false,
        ]);
    }

    /**
     * Logout the authenticated user with enhanced security.
     */
    public function logout(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // SECURITY: Delete current token only, not all
        $user->currentAccessToken()->delete();

        // SECURITY: Log logout
        Log::info('User logged out', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'message' => 'User logged out successfully',
            'logout_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Logout from all devices (optional feature).
     */
    public function logoutAllDevices(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Delete ALL tokens - force logout from all devices
        $user->tokens()->delete();

        Log::warning('User logged out from all devices', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip(),
        ]);

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ]);
    }

    /**
     * Refresh token (optional).
     */
    public function refreshToken(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Revoke old token
        $user->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth_token_' . Str::random(10), ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 604800,
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // SECURITY: Revoke every other session, keep the one used for this request
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();

        Log::info('User changed password', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    // ============ HELPER METHODS ============

    /**
     * Check if account is locked due to multiple failed attempts.
     */
    private function isAccountLocked(User $user): bool
    {
        $failedAttempts = FailedLoginAttempt::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        return $failedAttempts >= 5; // Lock after 5 failed attempts
    }

    /**
     * Log failed login attempt.
     */
    private function logFailedAttempt(?string $email, ?string $ip): void
    {
        try {
            $user = User::where('email', $email)->first();

            FailedLoginAttempt::create([
                'user_id' => $user->id ?? null,
                'email' => $email,
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'attempted_at' => now(),
            ]);

            // Security: Alert if too many failed attempts from same IP
            $attempts = FailedLoginAttempt::where('ip_address', $ip)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->count();

            if ($attempts >= 10) {
                // Alert admin or trigger security measure
                Log::warning('Multiple failed login attempts', [
                    'ip' => $ip,
                    'attempts' => $attempts,
                    'email' => $email,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to log login attempt: ' . $e->getMessage());
        }
    }

    /**
     * Log successful login.
     */
    private function logSuccessfulLogin(User $user, $request): void
    {
        try {
            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
                'is_suspicious' => $this->isSuspiciousLogin($user, $request),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log login: ' . $e->getMessage());
        }
    }

    /**
     * Check if login is suspicious.
     */
    private function isSuspiciousLogin(User $user, $request): bool
    {
        // Check if IP is from different country
        $lastLogin = LoginHistory::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$lastLogin) {
            return false;
        }

        // Check if IP changed significantly
        return $lastLogin->ip_address !== $request->ip();
    }

    /**
     * Clear failed attempts after successful login.
     */
    private function clearFailedAttempts(User $user): void
    {
        FailedLoginAttempt::where('user_id', $user->id)->delete();
    }

    /**
     * Check if email is from disposable domain.
     */
    private function isDisposableEmail(string $email): bool
    {
        $disposableDomains = [
            'tempmail.com', 'temp-mail.org', 'guerrillamail.com',
            'mailinator.com', '10minutemail.com', 'throwaway.com',
            // Add more domains
        ];

        $domain = substr(strrchr($email, "@"), 1);
        return in_array($domain, $disposableDomains);
    }
}
