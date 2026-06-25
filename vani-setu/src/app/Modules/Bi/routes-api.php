<?php

use App\Modules\Bi\Controllers\SupersetController;
use Illuminate\Support\Facades\Route;

Route::prefix('bi')->middleware(['auth:sanctum', 'role:sg'])->group(function () {
    Route::get('/superset/dashboards/sg-mis', [SupersetController::class, 'sgMis']);
});
