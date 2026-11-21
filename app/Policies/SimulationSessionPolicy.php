<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SimulationSession;
use App\Models\User;

class SimulationSessionPolicy
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
    public function view(User $user, SimulationSession $simulationSession): bool
    {
        if ($simulationSession->education_group_id !== null) {
            return false;
        }

        return $simulationSession->student_id === $user->id
            || $user->isAdmin()
            || ($user->role === UserRole::Teacher && $simulationSession->scenario->created_by === $user->id);
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
    public function update(User $user, SimulationSession $simulationSession): bool
    {
        return $simulationSession->education_group_id === null && $user->role === UserRole::Student && $simulationSession->student_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SimulationSession $simulationSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SimulationSession $simulationSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SimulationSession $simulationSession): bool
    {
        return false;
    }
}
