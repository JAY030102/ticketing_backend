<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedUser;
use App\Models\User;
use App\Notifications\AccountStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        return User::query()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->when($request->account_status, fn ($q, $status) => $q->where('account_status', $status))
            ->latest()
            ->paginate(15);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'address' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['superadmin', 'admin', 'staff'])],
            'password' => ['required', 'confirmed', Password::min(8)],
            'account_status' => ['sometimes', Rule::in(['active', 'suspended', 'terminated'])],
        ]);

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
            'role' => $request->role,
            'password' => Hash::make($request->password),
            'status' => 'pending',
            'account_status' => $request->input('account_status', 'active'),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'user' => $user,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $previousAccountStatus = $user->account_status;
        $requestedAccountStatus = $request->input('account_status', $previousAccountStatus);

        // A terminated account must be reactivated before its details can be edited.
        if ($previousAccountStatus === 'terminated' && $requestedAccountStatus === 'terminated') {
            return response()->json([
                'message' => 'This account is terminated. Reactivate it before editing other details.',
            ], 422);
        }

        if ($previousAccountStatus === 'terminated' && $requestedAccountStatus === 'active') {
            return $this->reactivate($request, $user);
        }

        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'address' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['superadmin', 'admin', 'staff'])],
            'account_status' => ['sometimes', Rule::in(['active', 'suspended', 'terminated'])],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $data = $request->only([
            'first_name', 'middle_name', 'last_name', 'username', 'email',
            'mobile_number', 'birthdate', 'gender', 'address', 'role',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($requestedAccountStatus === 'terminated') {
            return $this->terminate($request, $user, $data);
        }

        $data['account_status'] = $requestedAccountStatus;
        $user->update($data);

        if ($requestedAccountStatus !== $previousAccountStatus) {
            if ($requestedAccountStatus === 'suspended') {
                $user->tokens()->delete();
            }
            $user->notify(new AccountStatusUpdated($requestedAccountStatus));
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Snapshot the full user into archived_users while keeping the live row
     * visible and marked as terminated.
     */
    private function terminate(Request $request, User $user, array $pendingEdits = [])
    {
        // Apply any in-flight edits first so the archive captures the latest state
        if (! empty($pendingEdits)) {
            $user->fill($pendingEdits);
        }

        $fullSnapshot = $user->toArray();

        DB::transaction(function () use ($request, $user, $fullSnapshot) {
            ArchivedUser::create([
                'original_user_id' => $user->id,
                'user_data' => $fullSnapshot,
                'termination_reason' => $request->input('reason'),
                'terminated_by' => $request->user()->id,
                'terminated_at' => now(),
            ]);

            $user->tokens()->delete();
            $user->loginSessions()->delete();

            // Keep the complete row visible to administrators while making
            // the account inactive. The archive remains the restoration copy.
            $user->forceFill(['account_status' => 'terminated'])->save();
        });

        $user->notify(new AccountStatusUpdated('terminated'));

        return response()->json([
            'success' => true,
            'message' => 'User terminated and archived. The account remains visible for reactivation.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Restore the full profile from the most recent un-restored archive
     * record back onto the live users row.
     */
    private function reactivate(Request $request, User $user)
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
            $restoreData = Arr::except($archive->user_data, [
                'id', 'created_at', 'updated_at',
            ]);

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

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // Permanent hard delete — no archive. Only for cleaning up test/mistaken accounts.
        $user->tokens()->delete();
        $user->loginSessions()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User permanently deleted.',
        ]);
    }
}
