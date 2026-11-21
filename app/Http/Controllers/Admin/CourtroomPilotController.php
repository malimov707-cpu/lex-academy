<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimulationScenario;
use App\Services\VirtualCourtroom\CourtroomPilotReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourtroomPilotController extends Controller
{
    public function __invoke(Request $request, CourtroomPilotReport $pilot): View
    {
        Gate::authorize('create', SimulationScenario::class);
        $filters = $request->validate([
            'scenario' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
            'role' => ['nullable', 'string', 'max:60'],
        ]);

        $scenarios = SimulationScenario::query()->published()
            ->when(! $request->user()->isAdmin(), fn (Builder $query) => $query->where('created_by', $request->user()->id))
            ->orderBy('title')->orderBy('id')->get(['id', 'title', 'slug', 'created_by']);
        $selectedScenario = isset($filters['scenario']) ? $scenarios->firstWhere('slug', $filters['scenario']) : $scenarios->first();
        abort_if(isset($filters['scenario']) && ! $selectedScenario, 404);

        $versions = $selectedScenario?->versions()->where('status', 'published')->orderByDesc('version')->get(['id', 'scenario_id', 'version']) ?? collect();
        $selectedVersion = isset($filters['version']) ? $versions->firstWhere('version', (int) $filters['version']) : $versions->first();
        abort_if(isset($filters['version']) && ! $selectedVersion, 404);

        $roles = $selectedVersion?->roles()->where('is_student_selectable', true)->get(['id', 'key', 'name']) ?? collect();
        $selectedRole = isset($filters['role']) ? $roles->firstWhere('key', $filters['role']) : $roles->first();
        abort_if(isset($filters['role']) && ! $selectedRole, 404);
        $report = $selectedRole ? $pilot->forRole($selectedVersion, $selectedRole->key) : null;

        return view('admin.courtroom.pilot', compact('scenarios', 'selectedScenario', 'versions', 'selectedVersion', 'roles', 'selectedRole', 'report'));
    }
}
