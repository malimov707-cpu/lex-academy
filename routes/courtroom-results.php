<?php

use App\Http\Controllers\CourtroomResultController;
use App\Http\Controllers\CourtroomResultHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student,teacher,academy_admin,super_admin'])->prefix('virtual-courtroom')->name('courtroom.results.')->group(function (): void {
    Route::get('/sessions/{session}/results', [CourtroomResultController::class, 'show'])->name('show');
    Route::get('/sessions/{session}/individual-scores', [CourtroomResultController::class, 'individual'])->name('individual');
    Route::get('/sessions/{session}/team-scores', [CourtroomResultController::class, 'teams'])->name('teams');
    Route::middleware('role:teacher,academy_admin,super_admin')->group(function (): void {
        Route::post('/sessions/{session}/teacher-grades', [CourtroomResultController::class, 'grade'])->name('grade');
        Route::post('/sessions/{session}/approve-results', [CourtroomResultController::class, 'approve'])->name('approve');
        Route::post('/sessions/{session}/return-results', [CourtroomResultController::class, 'revise'])->name('revise');
        Route::get('/groups/{group}/simulation-history', [CourtroomResultHistoryController::class, 'group'])->name('group-history');
    });
    Route::post('/sessions/{session}/reassessment-requests', [CourtroomResultController::class, 'reassess'])
        ->middleware(['role:student', 'throttle:10,1'])->name('reassess');
    Route::get('/students/{student}/learning-results', [CourtroomResultHistoryController::class, 'student'])->name('student-history');
});
