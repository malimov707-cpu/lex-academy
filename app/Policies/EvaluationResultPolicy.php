<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\EvaluationResult;
use App\Models\User;

class EvaluationResultPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EvaluationResult $evaluationResult): bool
    {
        return $evaluationResult->session->student_id === $user->id
            || $user->isAdmin()
            || ($user->role === UserRole::Teacher && $evaluationResult->session->scenario->created_by === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EvaluationResult $evaluationResult): bool
    {
        return $user->isAdmin()
            || ($user->role === UserRole::Teacher && $evaluationResult->session->scenario->created_by === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EvaluationResult $evaluationResult): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EvaluationResult $evaluationResult): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EvaluationResult $evaluationResult): bool
    {
        return false;
    }
}
