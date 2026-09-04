<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountStatusUpdated;
use Illuminate\Http\Request;

class UserApprovalController extends Controller
{
    public function pending(Request $request)
    {
        return User::where('status', 'pending')
            ->latest()
            ->paginate(15);
    }

    public function index(Request $request)
    {
        return User::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15);
    }

    public function approve(Request $request, User $user)
    {
        $user->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        $user->notify(new AccountStatusUpdated('approved'));

        return response()->json(['message' => 'User approved.', 'user' => $user]);
    }

    public function reject(Request $request, User $user)
    {
        $user->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        $user->notify(new AccountStatusUpdated('rejected'));

        return response()->json(['message' => 'User rejected.', 'user' => $user]);
    }

    public function resendVerification(Request $request, User $user)
    {
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'This user\'s email is already verified.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email resent to ' . $user->email . '.']);
    }
}
