<?php

use App\Http\Controllers\Api\Admin\AccountLifecycleController;
use App\Http\Controllers\Api\Admin\UserApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Public routes ──────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:6,1');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
    ->middleware('throttle:6,1');

Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
    ->middleware('throttle:6,1');

Route::post('/request-review', [AuthController::class, 'requestReview'])
    ->middleware('throttle:6,1');

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// ── Protected routes ───────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/complete-profile', [GoogleAuthController::class, 'completeProfile']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);

    Route::get('/sessions', function (Request $request) {
        return $request->user()->loginSessions()->latest('login_at')->paginate(10);
    });

    Route::middleware('superadmin')->prefix('admin')->group(function () {
        Route::get('/users/pending', [UserApprovalController::class, 'pending']);
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
        Route::post('/users/{user}/approve', [UserApprovalController::class, 'approve']);
        Route::post('/users/{user}/reject', [UserApprovalController::class, 'reject']);
        Route::post('/users/{user}/resend-verification', [UserApprovalController::class, 'resendVerification']);

        Route::post('/users/{user}/suspend', [AccountLifecycleController::class, 'suspend']);
        Route::post('/users/{user}/reactivate', [AccountLifecycleController::class, 'reactivate']);
        Route::post('/users/{user}/terminate', [AccountLifecycleController::class, 'terminate']);
        Route::get('/archived-users', [AccountLifecycleController::class, 'listArchived']);
    });

    Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::get('/users/{user}', [UserManagementController::class, 'show']);
    Route::post('/users', [UserManagementController::class, 'store']);
    Route::put('/users/{user}', [UserManagementController::class, 'update']);
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
});
});
