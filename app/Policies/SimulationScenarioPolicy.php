<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SimulationScenario;
use App\Models\User;

class SimulationScenarioPolicy
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
    public function view(User $user, SimulationScenario $simulationScenario): bool
    {
        return $simulationScenario->status === 'published'
            || $this->canManage($user, $simulationScenario);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Teacher, UserRole::AcademyAdmin, UserRole::SuperAdmin);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SimulationScenario $simulationScenario): bool
    {
        return $this->canManage($user, $simulationScenario);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SimulationScenario $simulationScenario): bool
    {
        return $user->isAdmin() || $simulationScenario->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SimulationScenario $simulationScenario): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SimulationScenario $simulationScenario): bool
    {
        return false;
    }

    private function canManage(User $user, SimulationScenario $scenario): bool
    {
        return $user->isAdmin()
            || ($user->role === UserRole::Teacher && $scenario->created_by === $user->id);
    }
}
