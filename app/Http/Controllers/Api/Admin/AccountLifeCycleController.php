<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountLifecycleController extends Controller
{
    public function suspend(Request $request, User $user)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You Cannot Suspend Your Own Account.'], 422);
        }

        $user->update([
            'account_status' => 'suspended',
            'suspended_at' => now(),
            'suspended_by' => $request->user()->id,
            'suspension_reason' => $request->reason,
        ]);

        // Revoke all active tokens so a suspended user is logged out immediately
        $user->tokens()->delete();

        return response()->json(['message' => 'Account Suspended.', 'user' => $user]);
    }

    public function reactivate(Request $request, User $user)
    {
        $user->update([
            'account_status' => 'active',
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        return response()->json(['message' => 'Account Reactivated.', 'user' => $user]);
    }

    public function terminate(Request $request, User $user)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You Cannot Terminate Your Own Account.'], 422);
        }

        DB::transaction(function () use ($request, $user) {
            ArchivedUser::create([
                'original_user_id' => $user->id,
                'user_data' => $user->toArray(),
                'termination_reason' => $request->reason,
                'terminated_by' => $request->user()->id,
                'terminated_at' => now(),
            ]);

            $user->tokens()->delete();
            $user->loginSessions()->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Account Terminated and Archived.']);
    }

    public function listArchived(Request $request)
    {
        return ArchivedUser::with('terminatedBy:id,name,email')
            ->latest('terminated_at')
            ->paginate(15);
    }
}
