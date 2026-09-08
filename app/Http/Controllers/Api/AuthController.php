<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'mobile_number' => $request->mobile_number,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
            'address' => $request->address,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user' => $user,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $key = Str::lower($request->login).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->status(429);
        }

        $user = User::where('email', $request->login)
            ->orWhere('username', $request->login)
            ->first();

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'login' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            RateLimiter::clear($key);

            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'email_verified' => false,
            ], 403);
        }
        if ($user->status === 'pending') {
            RateLimiter::clear($key);

            return response()->json([
                'message' => 'Your account is awaiting admin approval.',
                'account_status' => 'pending',
            ], 403);
        }

        if ($user->status === 'rejected') {
            RateLimiter::clear($key);

            return response()->json([
                'message' => 'Your account registration was not approved. Contact support for details.',
                'account_status' => 'rejected',
            ], 403);
        }

        if ($user->account_status === 'suspended') {
            RateLimiter::clear($key);

            return response()->json([
                'message' => 'Your Account Has Been Suspended. Contact An Administrator For Details.',
                'account_lifecycle' => 'suspended',
            ], 403);
        }

        if ($user->account_status === 'terminated') {
            RateLimiter::clear($key);

            return response()->json([
                'message' => 'Your account has been terminated. Contact an administrator for details.',
                'account_lifecycle' => 'terminated',
            ], 403);
        }

        RateLimiter::clear($key);

        $remember = $request->boolean('remember');
        $expiresAt = $remember ? now()->addDays(30) : now()->addDay();

        $tokenResult = $user->createToken('auth_token', ['*'], $expiresAt);
        $token = $tokenResult->plainTextToken;

        $user->loginSessions()->create([
            'personal_access_token_id' => $tokenResult->accessToken->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'remember' => $remember,
            'login_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);
    }

    public function logout(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();

        LoginSession::where('personal_access_token_id', $currentToken->id)
            ->whereNull('logout_at')
            ->update(['logout_at' => now()]);

        $currentToken->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function requestReview(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->status === 'rejected') {
            $user->update([
                'status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);
        }

        // Same response either way — don't reveal whether the email exists
        return response()->json(['message' => 'If eligible, your account has been resubmitted for review.']);
    }
}
