<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SimulationScenario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourtroomScenarioController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SimulationScenario::class);
        $filters = $request->validate([
            'legal_area' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['nullable', 'in:beginner,intermediate,advanced'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $scenarios = SimulationScenario::query()
            ->published()
            ->where('legal_area', '!=', 'criminal')
            ->when($filters['legal_area'] ?? null, fn ($query, $area) => $query->where('legal_area', $area))
            ->when($filters['difficulty'] ?? null, fn ($query, $difficulty) => $query->where('difficulty', $difficulty))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($nested) use ($search): void {
                $nested->where('title', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%');
            }))
            ->with(['versions' => fn ($query) => $query->where('status', 'published')->orderByDesc('version')->with('roles')])
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('student.courtroom.index', [
            'scenarios' => $scenarios,
            'legalAreas' => SimulationScenario::query()->published()->distinct()->orderBy('legal_area')->pluck('legal_area'),
        ]);
    }

    public function show(Request $request, SimulationScenario $scenario): View
    {
        Gate::authorize('view', $scenario);
        abort_unless($scenario->status === 'published', 404);
        abort_if($scenario->legal_area === 'criminal', 404);
        $version = $scenario->versions()
            ->where('status', 'published')
            ->with(['roles', 'stages.actionRules', 'evidence', 'verifiedLegalReferences', 'rubric.criteria'])
            ->orderByDesc('version')
            ->firstOrFail();

        return view('student.courtroom.show', [
            'scenario' => $scenario,
            'version' => $version,
            'previousSessions' => $request->user()->simulationSessions()
                ->where('scenario_id', $scenario->id)
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
