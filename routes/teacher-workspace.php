<?php

use App\Http\Controllers\Teacher\TeacherWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function (): void {
    Route::get('/assessments', [TeacherWorkspaceController::class, 'assessments'])->name('assessments.index');
    Route::get('/lessons', [TeacherWorkspaceController::class, 'lessons'])->name('lessons.index');
});
