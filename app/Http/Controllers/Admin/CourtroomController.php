<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtroomScenarioRequest;
use App\Http\Requests\UpdateCourtroomDefinitionRequest;
use App\Models\LegalSource;
use App\Models\SimulationScenario;
use App\Models\SimulationSession;
use App\Services\VirtualCourtroom\ScenarioBuilder;
use App\Services\VirtualCourtroom\ScenarioDefinitionEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourtroomController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SimulationScenario::class);
        $isTeacher = $request->user()->role === UserRole::Teacher;
        $scenarios = SimulationScenario::query()->when($isTeacher,
            fn (Builder $query): Builder => $query->where('created_by', $request->user()->id));
        $sessions = SimulationSession::query()->when($isTeacher,
            fn (Builder $query): Builder => $query->whereHas('scenario',
                fn (Builder $query): Builder => $query->where('created_by', $request->user()->id)));

        return view('admin.courtroom.index', [
            'scenarios' => (clone $scenarios)
                ->withCount(['versions'])
                ->with(['creator:id,name', 'versions' => fn ($query) => $query->orderByDesc('version')->limit(1)])
                ->latest('id')
                ->paginate(15),
            'stats' => [
                'published_scenarios' => (clone $scenarios)->where('status', 'published')->count(),
                'active_sessions' => (clone $sessions)->where('status', 'active')->count(),
                'completed_sessions' => (clone $sessions)->where('status', 'completed')->count(),
                'review_pending' => (clone $sessions)->whereHas('evaluation', fn ($query) => $query->where('status', 'draft'))->count(),
            ],
            'recentSessions' => (clone $sessions)->whereNull('education_group_id')
                ->with(['student:id,name', 'scenario:id,title,slug', 'evaluation'])
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SimulationScenario::class);

        return view('admin.courtroom.form', ['scenario' => null, 'version' => null]);
    }

    public function store(StoreCourtroomScenarioRequest $request, ScenarioBuilder $builder): RedirectResponse
    {
        Gate::authorize('create', SimulationScenario::class);
        $scenario = $builder->create($request->user(), $request->validated());

        return redirect()->route('admin.courtroom.edit', $scenario)
            ->with('status', __('Ssenariy qoralamasi yaratildi. Preview qilib, so‘ng nashr qiling.'));
    }

    public function edit(SimulationScenario $scenario): View
    {
        Gate::authorize('update', $scenario);
        $version = $scenario->versions()
            ->with(['roles', 'stages.actionRules', 'evidence', 'legalReferences', 'rubric.criteria'])
            ->orderByDesc('version')
            ->firstOrFail();

        return view('admin.courtroom.form', ['scenario' => $scenario, 'version' => $version]);
    }

    public function update(
        StoreCourtroomScenarioRequest $request,
        SimulationScenario $scenario,
        ScenarioBuilder $builder,
    ): RedirectResponse {
        Gate::authorize('update', $scenario);
        $builder->update($scenario, $request->user(), $request->validated());

        return back()->with('status', __('Yangi qoralama versiya saqlandi.'));
    }

    public function preview(Request $request, SimulationScenario $scenario): View
    {
        Gate::authorize('update', $scenario);
        $filters = $request->validate(['version' => ['nullable', 'integer', 'min:1']]);
        $version = $scenario->versions()->when($filters['version'] ?? null, fn ($query, $number) => $query->where('version', $number))
            ->with(['roles', 'stages.actionRules', 'evidence', 'legalReferences', 'verifiedLegalReferences', 'rubric.criteria'])
            ->orderByDesc('version')
            ->firstOrFail();
        $isLatest = ($filters['version'] ?? null) === null || $scenario->versions()->latest('version')->value('id') === $version->id;

        return view('student.courtroom.show', [
            'scenario' => $scenario,
            'version' => $version,
            'previousSessions' => collect(),
            'isPreview' => true,
            'canVerifyReferences' => $version->status === 'draft' && $isLatest,
            'canAttachReference' => $isLatest && $scenario->status !== 'archived',
            'currentReferenceIds' => $version->legalReferences()->currentSource()->pluck('id'),
        ]);
    }

    public function library(Request $request, SimulationScenario $scenario): View
    {
        Gate::authorize('update', $scenario);
        abort_if($scenario->status === 'archived', 409);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $search = $filters['q'] ?? '';
        $sources = LegalSource::query()->courtroomLibrary()
            ->when($search !== '', fn (Builder $query): Builder => $query->whereLike('title', '%'.$search.'%'))
            ->latest('verified_at')->orderByDesc('id')->paginate(15)->appends($filters);

        return view('admin.courtroom.reference-library', ['scenario' => $scenario, 'sources' => $sources, 'search' => $search]);
    }

    public function attachLibraryReference(SimulationScenario $scenario, int $source, ScenarioBuilder $builder): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $builder->attachLibraryReference($scenario, $source);

        return redirect()->route('admin.courtroom.preview', $scenario)
            ->with('status', __('Kutubxona manbasi qoralamaga biriktirildi. Modda va amaldagi tahrirni tekshiring.'));
    }

    public function verifyReference(Request $request, SimulationScenario $scenario, int $reference, ScenarioBuilder $builder): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'citation_text' => ['required', 'string', 'max:2000'],
        ]);
        $builder->verifyReference($scenario, $reference, $request->user(), $data['citation_text']);

        return redirect()->route('admin.courtroom.preview', $scenario)
            ->with('status', __('Huquqiy manba tekshirilgan deb belgilandi.'));
    }

    public function definition(SimulationScenario $scenario): View
    {
        Gate::authorize('update', $scenario);
        $version = $scenario->versions()->with(['roles', 'stages.actionRules', 'rubric.criteria'])->latest('version')->firstOrFail();

        return view('admin.courtroom.definition', ['scenario' => $scenario, 'version' => $version]);
    }

    public function updateDefinition(UpdateCourtroomDefinitionRequest $request, SimulationScenario $scenario, ScenarioDefinitionEditor $editor): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $editor->save($scenario, $request->validated());

        return back()->with('status', __('Rollar, bosqichlar va mezonlar qoralamada saqlandi.'));
    }

    public function history(SimulationScenario $scenario): View
    {
        Gate::authorize('update', $scenario);

        return view('admin.courtroom.history', [
            'scenario' => $scenario,
            'versions' => $scenario->versions()->withCount(['roles', 'stages', 'evidence'])->latest('version')->paginate(20),
        ]);
    }

    public function duplicate(SimulationScenario $scenario, ScenarioDefinitionEditor $editor): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $copy = $editor->duplicate($scenario, request()->user());

        return redirect()->route('admin.courtroom.edit', $copy)->with('status', __('Ssenariy nusxasi qoralama sifatida yaratildi.'));
    }

    public function publish(SimulationScenario $scenario, ScenarioBuilder $builder): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $version = $builder->publish($scenario, request()->user());

        return back()->with('status', __('Ssenariyning :version-versiyasi nashr qilindi.', ['version' => $version->version]));
    }

    public function archive(SimulationScenario $scenario): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $scenario->update(['status' => 'archived']);

        return back()->with('status', __('Ssenariy arxivlandi. Boshlangan sessionlar snapshot bilan saqlanadi.'));
    }
}
