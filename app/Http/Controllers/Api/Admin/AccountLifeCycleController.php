<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedUser;
use App\Models\User;
use App\Notifications\AccountStatusUpdated;
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
        $user->notify(new AccountStatusUpdated('suspended'));

        return response()->json(['message' => 'Account Suspended.', 'user' => $user->fresh()]);
    }

    public function reactivate(Request $request, User $user)
    {
        // Terminated accounts had their data moved to archived_users — restore it.
        if ($user->account_status === 'terminated') {
            return $this->restoreFromArchive($request, $user);
        }

        $user->update([
            'account_status' => 'active',
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        $user->notify(new AccountStatusUpdated('active'));

        return response()->json(['message' => 'Account Reactivated.', 'user' => $user->fresh()]);
    }

    public function terminate(Request $request, User $user)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You Cannot Terminate Your Own Account.'], 422);
        }

        $archivedUser = DB::transaction(function () use ($request, $user) {
            $validColumns = collect(DB::select('SHOW COLUMNS FROM users'))
                ->pluck('Field')
                ->all();

            // Snapshot only real columns — exclude any accessor/appended
            // attributes (e.g. a computed avatar_url) so this stays a
            // faithful, restorable copy of the actual row.
            $fullSnapshot = collect($user->toArray())
                ->only($validColumns)
                ->toArray();

            $archivedUser = ArchivedUser::create([
                'original_user_id' => $user->id,
                'user_data' => $fullSnapshot,
                'termination_reason' => $request->reason,
                'terminated_by' => $request->user()->id,
                'terminated_at' => now(),
            ]);

            $user->tokens()->delete();
            $user->loginSessions()->delete();

            // Keep the complete row visible to administrators while making
            // the account inactive. The archive remains the restoration copy.
            $user->forceFill(['account_status' => 'terminated'])->save();

            return $archivedUser->load('terminatedBy:id,name,email');
        });

        $user->notify(new AccountStatusUpdated('terminated'));

        return response()->json([
            'success' => true,
            'message' => 'Account Terminated and archived successfully.',
            'user' => $user->fresh(),
            'archived_user' => $archivedUser,
        ]);
    }

    /**
     * Restore the most recent un-restored archive snapshot back onto the
     * live users row, then mark that archive record as restored.
     */
    private function restoreFromArchive(Request $request, User $user)
    {
        $archive = ArchivedUser::where('original_user_id', $user->id)
            ->whereNull('restored_at')
            ->latest('terminated_at')
            ->first();

        if (! $archive) {
            return response()->json([
                'message' => 'No archived data found to restore for this account.',
            ], 422);
        }

        DB::transaction(function () use ($user, $archive) {
            $validColumns = collect(DB::select('SHOW COLUMNS FROM users'))
                ->pluck('Field')
                ->all();

            $restoreData = collect($archive->user_data)
                ->only($validColumns)
                ->except(['id', 'created_at', 'updated_at'])
                ->toArray();

            $restoreData['account_status'] = 'active';

            $user->forceFill($restoreData)->save();

            $archive->update(['restored_at' => now()]);
        });

        $user->notify(new AccountStatusUpdated('active'));

        return response()->json([
            'success' => true,
            'message' => 'Account restored from archive and reactivated.',
            'user' => $user->fresh(),
        ]);
    }

    public function listArchived(Request $request)
    {
        return ArchivedUser::with('terminatedBy:id,name,email')
            ->latest('terminated_at')
            ->paginate(15);
    }
}
