<?php

use App\Modules\InCamera\Controllers\InCameraController;
use Illuminate\Support\Facades\Route;

Route::prefix('in-camera')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/blocks/{block}/flag', [InCameraController::class, 'flag'])
        ->middleware('role:committee_secretary|committee_secretariat|committee_chair|admin');
    Route::get('/blocks/{block}', [InCameraController::class, 'show']);
});
