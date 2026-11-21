<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidSimulationAction;
use App\Http\Controllers\Controller;
use App\Models\ScenarioEvidence;
use App\Models\SimulationScenario;
use App\Services\VirtualCourtroom\SimulationStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourtroomRehearsalController extends Controller
{
    public function __invoke(Request $request, SimulationScenario $scenario, int $version, SimulationStateMachine $stateMachine): View
    {
        Gate::authorize('update', $scenario);
        $version = $scenario->versions()->where('version', $version)->with(['roles', 'stages.actionRules', 'evidence'])->firstOrFail();
        $isRun = $request->isMethod('POST');
        $data = $request->validate([
            'stage_key' => [$isRun ? 'required' : 'nullable', 'string', 'max:80'],
            'role_key' => [$isRun ? 'required' : 'nullable', 'string', 'max:60'],
            ...($isRun ? [
                'action_type' => ['required', 'string', 'max:80'],
                'content' => ['nullable', 'string', 'max:4000'],
                'evidence_id' => ['nullable', 'integer'],
                'target_role_key' => ['nullable', 'string', 'max:60'],
            ] : []),
        ]);
        $roles = $version->roles->where('is_student_selectable', true);
        $role = isset($data['role_key']) ? $roles->firstWhere('key', $data['role_key']) : $roles->first();
        $stage = isset($data['stage_key']) ? $version->stages->firstWhere('key', $data['stage_key']) : $version->stages->first();
        abort_if((isset($data['role_key']) && ! $role) || (isset($data['stage_key']) && ! $stage), 404);
        $result = null;
        $failure = null;
        if ($isRun && $stage && $role) {
            $stage->setRelation('version', $version);
            try {
                $result = $stateMachine->rehearseAction($stage, $role->key, $data);
            } catch (InvalidSimulationAction $exception) {
                $failure = $exception->getMessage();
            }
        }

        return view('admin.courtroom.rehearsal', [
            'scenario' => $scenario, 'version' => $version, 'roles' => $roles, 'role' => $role, 'stage' => $stage,
            'evidence' => $version->evidence->filter(fn (ScenarioEvidence $item): bool => $item->status === 'active' && $role && $item->isVisibleTo($role->key)),
            'result' => $result, 'failure' => $failure, 'input' => $data,
        ]);
    }
}
