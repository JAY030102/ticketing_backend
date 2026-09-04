<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\Admin\UserApprovalController;
use App\Http\Controllers\Api\Admin\AccountLifecycleController;
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

    Route::get('/sessions', function (Request $request) {
        return $request->user()->loginSessions()->latest('login_at')->paginate(10);
    });


    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users/pending', [UserApprovalController::class, 'pending']);
        Route::get('/users', [UserApprovalController::class, 'index']);
        Route::post('/users/{user}/approve', [UserApprovalController::class, 'approve']);
        Route::post('/users/{user}/reject', [UserApprovalController::class, 'reject']);
        Route::post('/users/{user}/resend-verification', [UserApprovalController::class, 'resendVerification']);

        Route::post('/users/{user}/suspend', [AccountLifecycleController::class, 'suspend']);
        Route::post('/users/{user}/reactivate', [AccountLifecycleController::class, 'reactivate']);
        Route::post('/users/{user}/terminate', [AccountLifecycleController::class, 'terminate']);
        Route::get('/archived-users', [AccountLifecycleController::class, 'listArchived']);
    });
});
