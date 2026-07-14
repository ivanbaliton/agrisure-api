<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FarmerProfileController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\InsuranceApplicationController;
use App\Http\Controllers\DamageReportController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\InsuranceSeasonController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\BarangayController;
use App\Http\Controllers\DistributionEventController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/verify-login-otp', [LoginController::class, 'verifyOtp'])
->middleware('throttle:5,1');;
    
Route::post('/otp/resend', [LoginController::class, 'resendOtp'])
    ->middleware('throttle:3,1');





Route::middleware('auth:sanctum')->group(function () {
    Route::post('/save-fcm-token', [NotificationController::class, 'saveFcmToken']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
});
Route::post('/forgot-password', [LoginController::class, 'forgotPassword']);

Route::post('/forgot-password/verify-otp', [LoginController::class, 'verifyForgotPasswordOtp']);

Route::post('/forgot-password/reset', [LoginController::class, 'resetPassword']);
// Farmer protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/farmer/profile/{user_id}', [FarmerProfileController::class, 'show']);
    Route::put('/farmer/profile/{user_id}', [FarmerProfileController::class, 'update']);
    Route::post('/farmer/profile/{user_id}/photo',[FarmerProfileController::class, 'uploadProfilePhoto']);
    Route::put('/farmer/profile/{user_id}/update-rejected', [FarmerProfileController::class, 'updateRejectedProfile']);

    Route::post('/farmer/profile/{user_id}/resubmit', [FarmerProfileController::class, 'resubmitVerification']);

    Route::post('/farmer/profile/{user_id}/change-password', [FarmerProfileController::class, 'changePassword']);
});

// MAO protected routes
Route::middleware(['auth:sanctum', 'role:mao'])->group(function () {
    Route::get('/farmers/pending', [FarmerProfileController::class, 'pending']);
    Route::get('/farmers/verified', [FarmerProfileController::class, 'verified']);
    Route::get('/farmers/rejected', [FarmerProfileController::class, 'rejected']);

    Route::post('/farmers/{user_id}/verify', [FarmerProfileController::class, 'verify']);
    Route::post('/farmers/{user_id}/reject', [FarmerProfileController::class, 'reject']);
    
});

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\DistributionListController;

Route::middleware(['auth:sanctum', 'role:mao'])->group(function () {

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::put('/inventory/{id}', [InventoryController::class, 'update']);
    Route::delete('/inventory/{id}', [InventoryController::class, 'destroy']);

    // Distribution Lists
    Route::get('/distribution-lists', [DistributionListController::class, 'index']);
    Route::post('/distribution-lists', [DistributionListController::class, 'store']);

    // Publish distribution so barangay can view it
    Route::patch(
        '/distribution-lists/{id}/publish',
        [DistributionListController::class, 'publish']
    );

    // Mark specific farmer as received
    Route::patch(
        '/distribution-lists/{listId}/farmers/{farmerId}/received',
        [DistributionListController::class, 'markFarmerReceived']
    );

    // Complete whole distribution
    Route::patch(
        '/distribution-lists/{id}/complete',
        [DistributionListController::class, 'complete']
    );


    

    Route::get('/distribution-events', [DistributionEventController::class, 'index']);
    Route::post('/distribution-events', [DistributionEventController::class, 'store']);

    Route::patch('/distribution-events/{id}/publish', [DistributionEventController::class, 'publish']);
    Route::patch('/distribution-events/{id}/complete', [DistributionEventController::class, 'complete']);
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/farms', [FarmController::class, 'all']);

    Route::get('/farms/{user_id}', [FarmController::class, 'index']);

    Route::post('/farms', [FarmController::class, 'store']);

    Route::get('/farm/{id}', [FarmController::class, 'show']);

    Route::put('/farm/{id}', [FarmController::class, 'update']);

    Route::delete('/farm/{id}', [FarmController::class, 'destroy']);
    Route::get('/farmer/profile/{user_id}', [FarmerProfileController::class, 'show']);


});



Route::middleware('auth:sanctum')->group(function () {

    Route::post(
        '/insurance-applications',
        [InsuranceApplicationController::class, 'store']
    );

    Route::get(
        '/insurance-applications',
        [InsuranceApplicationController::class, 'index']
    );

    Route::get(
        '/insurance-applications/{id}',
        [InsuranceApplicationController::class, 'show']
    );
    Route::put(
        '/insurance-applications/{id}/approve-for-pcic',
        [InsuranceApplicationController::class, 'approveForPcic']
    );

    Route::put(
        '/insurance-applications/{id}/needs-revision',
        [InsuranceApplicationController::class, 'needsRevision']
    );

    Route::put(
        '/insurance-applications/{id}/submit-pcic',
        [InsuranceApplicationController::class, 'submitToPcic']
    );

    Route::put(
        '/insurance-applications/{id}/approve',
        [InsuranceApplicationController::class, 'approve']
    );

    Route::put(
        '/insurance-applications/{id}/reject',
        [InsuranceApplicationController::class, 'reject']
    );
    Route::post('/insurance-applications/{id}/verify-payment', [InsuranceApplicationController::class, 'verifyPayment']);

    Route::post('/insurance-applications/{id}/reject-payment', [InsuranceApplicationController::class, 'rejectPayment']);

    Route::get(
    '/insurance/free-coverage/{user_id}',
    [InsuranceApplicationController::class, 'freeCoverage']);

    Route::get('/insurance-seasons', [InsuranceSeasonController::class, 'index']);
    Route::get('/insurance-seasons/current', [InsuranceSeasonController::class, 'current']);

    Route::put(
        '/insurance-seasons/current',
        [InsuranceSeasonController::class, 'updateCurrent']
    );

    Route::post(
        '/insurance-seasons/current/close',
        [InsuranceSeasonController::class, 'closeCurrent']
    );

     Route::get(
        '/insurance-applications-history',
        [InsuranceApplicationController::class, 'history']
    );

    Route::get(
        '/insurance-seasons/{id}',
        [InsuranceSeasonController::class, 'show']
    );

    Route::get(
    '/insurance-applications/farm/{farm_id}',
    [InsuranceApplicationController::class, 'farmHistory']);

    Route::post('/insurance-seasons/new', [InsuranceSeasonController::class, 'createNewSeason']);

   

    

    Route::get('/barangay-accounts', [BarangayController::class, 'index']);

    Route::get(
    '/barangays/{barangay}/farmers',
    [BarangayController::class, 'farmers']);

    
});

Route::get('/barangays/list', [BarangayController::class, 'list']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/damage-reports', [DamageReportController::class, 'store']);
    Route::get('/damage-reports', [DamageReportController::class, 'index']);
    Route::get('/damage-reports/{id}', [DamageReportController::class, 'show']);
    Route::get('/farms/{farm_id}/damage-reports', [DamageReportController::class, 'farmReports']);
    Route::put('/damage-reports/{id}/status', [DamageReportController::class, 'updateStatus']);
});

use App\Http\Controllers\BarangayFarmerController;

Route::middleware(['auth:sanctum', 'role:barangay'])->group(function () {

    Route::get(
        '/barangay/farmers',
        [BarangayFarmerController::class, 'index']
    );

    Route::get(
        '/barangay/farmers/{id}',
        [BarangayFarmerController::class, 'show']
    );

});

Route::middleware(['auth:sanctum', 'role:barangay'])->group(function () {
    Route::get('/barangay/distribution-lists', [
        DistributionListController::class,
        'barangayIndex'
    ]);

    Route::get('/barangay/distribution-lists/{id}', [
        DistributionListController::class,
        'barangayShow'
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/claims', [ClaimController::class, 'index']);
    Route::get('/claims/{id}', [ClaimController::class, 'show']);
    Route::put('/claims/{id}', [ClaimController::class, 'update']);

    Route::patch('/claims/{id}/submit-to-pcic', [ClaimController::class, 'submitToPcic']);
    Route::patch('/claims/{id}/pcic-result', [ClaimController::class, 'updatePcicResult']);
    Route::patch('/claims/{id}/release', [ClaimController::class, 'setRelease']);
    Route::patch('/claims/{id}/claimed', [ClaimController::class, 'markClaimed']);

    Route::get('/farmers/{user_id}/claims', [ClaimController::class, 'myClaims']);
});


use App\Http\Controllers\ReportController;

Route::prefix('reports')->group(function () {

    // Overview Dashboard
    Route::get('/overview', [ReportController::class, 'overview']);

    // Farmers Reports
    Route::get('/farmers', [ReportController::class, 'farmers']);

    // Farms & Crops Reports
    Route::get('/farms', [ReportController::class, 'farms']);

    // Insurance Reports
    Route::get('/insurance', [ReportController::class, 'insurance']);

    // Damage Reports
    Route::get('/damage-reports', [ReportController::class, 'damageReports']);

    // Claims Reports
    Route::get('/claims', [ReportController::class, 'claims']);

    // Distribution Reports
    Route::get('/distribution', [ReportController::class, 'distribution']);

    // Inventory Reports
    Route::get('/inventory', [ReportController::class, 'inventory']);

    // Executive Insights
    Route::get('/executive', [ReportController::class, 'executive']);

});