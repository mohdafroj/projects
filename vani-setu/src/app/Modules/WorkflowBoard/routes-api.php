<?php

use App\Modules\WorkflowBoard\Controllers\WorkflowBoardController;
use Illuminate\Support\Facades\Route;

Route::prefix('workflow-board')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/assignments', [WorkflowBoardController::class, 'index']);
        Route::get('/assignments/{assignment}', [WorkflowBoardController::class, 'show']);
        Route::post('/assignments/{assignment}/transition', [WorkflowBoardController::class, 'transition']);
    });
