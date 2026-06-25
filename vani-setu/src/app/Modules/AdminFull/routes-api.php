<?php

use App\Modules\AdminFull\Controllers\AdminFullController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin-full')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->controller(AdminFullController::class)
    ->group(function () {
        Route::get('/summary', 'summary');

        Route::get('/users', 'users');
        Route::post('/users', 'storeUser');
        Route::patch('/users/{user}', 'updateUser');

        Route::get('/roles', 'roles');
        Route::post('/roles', 'storeRole');
        Route::patch('/roles/{role}', 'updateRole');

        Route::get('/sitting-templates', 'sittingTemplates');
        Route::post('/sitting-templates', 'storeSittingTemplate');
        Route::patch('/sitting-templates/{template}', 'updateSittingTemplate');

        Route::get('/slot-templates', 'slotTemplates');
        Route::post('/slot-templates', 'storeSlotTemplate');
        Route::patch('/slot-templates/{template}', 'updateSlotTemplate');

        Route::get('/sittings', 'sittings');
        Route::post('/sittings', 'storeSitting');
        Route::post('/sittings/from-template', 'storeSittingFromTemplate');
        Route::patch('/sittings/{sitting}', 'updateSitting');

        Route::get('/members', 'members');
        Route::post('/members', 'storeMember');
        Route::patch('/members/{member}', 'updateMember');

        Route::get('/custom-members', 'customMembers');
        Route::post('/custom-members', 'storeCustomMember');
        Route::patch('/custom-members/{member}', 'updateCustomMember');

        Route::get('/audit', 'audit');
        Route::get('/audit/verify', 'verifyAudit');

        Route::get('/config', 'config');
        Route::patch('/config', 'updateConfig');
    });
