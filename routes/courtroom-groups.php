<?php

use App\Http\Controllers\GroupCourtroomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin,academy_admin,teacher,student'])->prefix('virtual-courtroom')->name('courtroom.groups.')->group(function (): void {
    Route::get('/groups', [GroupCourtroomController::class, 'index'])->name('index');
    Route::middleware('role:super_admin,academy_admin,teacher')->group(function (): void {
        Route::get('/groups/create', [GroupCourtroomController::class, 'create'])->name('create');
        Route::post('/group-sessions', [GroupCourtroomController::class, 'store'])->name('store');
        Route::post('/group-sessions/{session}/start', [GroupCourtroomController::class, 'start'])->name('start');
        Route::post('/group-sessions/{session}/pause', [GroupCourtroomController::class, 'pause'])->name('pause');
        Route::post('/group-sessions/{session}/resume', [GroupCourtroomController::class, 'resume'])->name('resume');
        Route::post('/group-sessions/{session}/stage', [GroupCourtroomController::class, 'stage'])->name('stage');
        Route::post('/group-sessions/{session}/evidence', [GroupCourtroomController::class, 'evidence'])->name('evidence');
        Route::post('/group-sessions/{session}/complete', [GroupCourtroomController::class, 'complete'])->name('complete');
    });
    Route::get('/group-sessions/{session}', [GroupCourtroomController::class, 'show'])->name('show');
    Route::get('/group-sessions/{session}/feed', [GroupCourtroomController::class, 'feed'])->name('feed');
    Route::post('/group-sessions/{session}/attendance', [GroupCourtroomController::class, 'attend'])->middleware('role:student')->name('attend');
    Route::post('/group-sessions/{session}/actions', [GroupCourtroomController::class, 'act'])->middleware(['role:student', 'throttle:courtroom-actions'])->name('act');
});
