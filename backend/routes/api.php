<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EnrolmentController;
use Illuminate\Support\Facades\Route;

// --- Public ---
Route::post('/login', [AuthController::class, 'login']);

// --- Authenticated ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Enrolment & attendance management (school admin + teacher).
    Route::middleware('role:super_admin,school_admin,teacher')->group(function () {
        Route::post('/enrolment/consent', [EnrolmentController::class, 'consent']);
        Route::post('/enrolment/face', [EnrolmentController::class, 'store']);
        Route::post('/attendance/bypass', [AttendanceController::class, 'bypass']);
    });

    // Kiosk check-in (the kiosk authenticates as a device/service account).
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);

    // TODO (v2): /bills, /payments/webhook, /notifications — payment & WA gateways deferred.
});
