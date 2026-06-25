<?php

use App\Modules\CommitteeSittings\Controllers\CommitteeWorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('committee')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/sittings', [CommitteeWorkflowController::class, 'storeSitting'])
        ->middleware('role:committee_secretary|committee_secretariat|admin');
    Route::post('/sittings/{committeeSitting}/capture-slots', [CommitteeWorkflowController::class, 'commitSlot'])
        ->middleware('role:committee_secretary|committee_secretariat|admin');
    Route::post('/sittings/{committeeSitting}/forward', [CommitteeWorkflowController::class, 'forward'])
        ->middleware('role:committee_secretary|committee_secretariat|admin');
    Route::post('/sittings/{committeeSitting}/chief-commit', [CommitteeWorkflowController::class, 'chiefCommit'])
        ->middleware('role:committee_secretary|committee_secretariat|admin');
    Route::post('/documents/{document}/secretariat-review', [CommitteeWorkflowController::class, 'secretariatReview'])
        ->middleware('role:committee_secretary|committee_secretariat|admin');
    Route::post('/documents/{document}/draft-report', [CommitteeWorkflowController::class, 'draftReport'])
        ->middleware('role:committee_secretary|committee_secretariat|admin');
    Route::post('/documents/{document}/chair-sign', [CommitteeWorkflowController::class, 'chairSign'])
        ->middleware('role:committee_chair|admin');
    Route::post('/documents/{document}/lay', [CommitteeWorkflowController::class, 'layReport'])
        ->middleware('role:committee_secretary|committee_secretariat|admin');
});
