<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Handle the one-click verification link.
     * This is hit directly by the browser (from the email), not by the SPA via fetch.
     */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(config('app.frontend_url').'/email-verified?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url').'/email-verified?status=already');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(config('app.frontend_url').'/email-verified?status=success');
    }

    /**
     * Resend the verification email. Called by the SPA.
     */
    public function resend(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
        ]);

        $user = User::where('email', $request->login)
            ->orWhere('username', $request->login)
            ->first();

        if (! $user) {
            // Don't reveal whether the account exists — respond generically
            return response()->json(['message' => 'If an account exists, a verification email has been sent.']);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }
}
