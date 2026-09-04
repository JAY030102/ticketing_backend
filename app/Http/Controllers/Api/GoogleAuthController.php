<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\GoogleAccountVerifiedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url').'/login?google_error=1');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'password' => null,
                'role' => null,
                'status' => 'pending',
                'email_verified_at' => now(),
            ]);

            if (class_exists(GoogleAccountVerifiedNotification::class)) {
                $user->notify(new GoogleAccountVerifiedNotification);
            }
        } elseif (! $user->google_id) {
            $wasUnverified = is_null($user->email_verified_at);

            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            if ($wasUnverified && class_exists(GoogleAccountVerifiedNotification::class)) {
                $user->notify(new GoogleAccountVerifiedNotification);
            }
        }

        $wasRejected = $user->status === 'rejected';

        if ($wasRejected) {
            $user->update([
                'status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
            ]);
        }

        if (is_null($user->role)) {
            $tempToken = $user->createToken('profile_completion', ['profile:complete'], now()->addMinutes(15))
                ->plainTextToken;

            return redirect(
                config('app.frontend_url').'/complete-profile?token='.$tempToken
            );
        }

        if ($user->status === 'pending') {
            $statusParam = $wasRejected ? 'resubmitted' : 'pending';

            return redirect(config('app.frontend_url').'/login?google_status='.$statusParam);
        }

        $expiresAt = now()->addDay();
        $tokenResult = $user->createToken('auth_token', ['*'], $expiresAt);

        $user->loginSessions()->create([
            'personal_access_token_id' => $tokenResult->accessToken->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'remember' => false,
            'login_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return redirect(
            config('app.frontend_url')
            .'/oauth-callback?token='.$tokenResult->plainTextToken
            .'&expires_at='.$expiresAt->toIso8601String()
        );
    }

    public function completeProfile(Request $request)
    {
        $user = $request->user();

        if (! $request->user()->currentAccessToken()->can('profile:complete')) {
            return response()->json(['message' => 'Invalid or expired request.'], 403);
        }

        $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username,'.$user->id],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'birthdate' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'address' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,staff'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update([
            'username' => $request->username,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'mobile_number' => $request->mobile_number,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
            'address' => $request->address,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Profile completed. Your account is now awaiting admin approval.']);
    }
}
