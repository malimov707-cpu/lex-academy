<?php

namespace App\Services\VirtualCourtroom;

use App\Models\EvaluationCriterion;
use App\Models\LegalSource;
use App\Models\ScenarioActionRule;
use App\Models\ScenarioEvidence;
use App\Models\ScenarioLegalReference;
use App\Models\ScenarioRole;
use App\Models\ScenarioStage;
use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScenarioBuilder
{
    public function __construct(private ScenarioDefinitionEditor $definitions) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $creator, array $data): SimulationScenario
    {
        return DB::transaction(function () use ($creator, $data): SimulationScenario {
            $baseSlug = Str::slug($data['title']);
            $slug = $baseSlug;
            $suffix = 2;
            while (SimulationScenario::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }
            $scenario = SimulationScenario::query()->create([
                'created_by' => $creator->id,
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'],
                'legal_area' => $data['legal_area'],
                'case_type' => $data['case_type'],
                'difficulty' => $data['difficulty'],
                'estimated_duration_minutes' => $data['estimated_duration_minutes'],
                'status' => 'draft',
            ]);
            $version = $scenario->versions()->create($this->versionData($data, 1));
            $this->syncTemplate($version, $creator, $data);

            return $scenario->fresh(['versions.roles', 'versions.stages']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(SimulationScenario $scenario, User $editor, array $data): SimulationScenario
    {
        return DB::transaction(function () use ($scenario, $editor, $data): SimulationScenario {
            $scenario = SimulationScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            $scenario->update([
                'title' => $data['title'],
                'description' => $data['description'],
                'legal_area' => $data['legal_area'],
                'case_type' => $data['case_type'],
                'difficulty' => $data['difficulty'],
                'estimated_duration_minutes' => $data['estimated_duration_minutes'],
            ]);
            $version = $this->definitions->draft($scenario);
            $version->update($this->versionData($data, $version->version));
            $this->syncTemplate($version, $editor, $data);

            return $scenario->fresh(['versions.roles', 'versions.stages']);
        });
    }

    public function verifyReference(SimulationScenario $scenario, int $referenceId, User $verifier, string $citationText): void
    {
        DB::transaction(function () use ($scenario, $referenceId, $verifier, $citationText): void {
            $scenario = SimulationScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            $version = $scenario->versions()->lockForUpdate()->latest('version')->firstOrFail();
            $reference = $version->legalReferences()->findOrFail($referenceId);
            abort_unless($version->status === 'draft', 409, __('Manbani faqat qoralama versiyada tasdiqlash mumkin.'));

            if (! $version->legalReferences()->currentSource()->whereKey($reference->id)->exists()) {
                throw ValidationException::withMessages(['legal_reference' => __('Kutubxonadan manbaning tasdiqlangan tahririni qayta biriktiring.')]);
            }
            if (Validator::make(['url' => $reference->source_url], ['url' => ['required', 'url:http,https']])->fails()) {
                throw ValidationException::withMessages(['legal_reference' => __('Manbaning rasmiy havolasini tekshiring.')]);
            }

            if ($reference->verified_at === null) {
                $reference->update(['citation_text' => $citationText, 'verified_at' => now(), 'verified_by' => $verifier->id]);
            }
        }, 3);
    }

    public function attachLibraryReference(SimulationScenario $scenario, int $sourceId): void
    {
        DB::transaction(function () use ($scenario, $sourceId): void {
            $scenario = SimulationScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            abort_if($scenario->status === 'archived', 409);
            $source = LegalSource::query()->courtroomLibrary()->lockForUpdate()->findOrFail($sourceId);
            if (Validator::make(['url' => $source->url], ['url' => ['required', 'url:https']])->fails()) {
                throw ValidationException::withMessages(['legal_reference' => __('Kutubxonadagi manbaning rasmiy havolasi yaroqsiz.')]);
            }

            $version = $this->definitions->draft($scenario);
            $reference = $version->legalReferences()->firstWhere('legal_source_id', $source->id)
                ?? $version->legalReferences()->whereNull('legal_source_id')->whereNull('library_verified_at')->firstWhere('source_url', $source->url)
                ?? $version->legalReferences()->make(['citation_text' => __('Modda va amaldagi tahrirni qayd eting.')]);
            $reference->fill([
                'legal_source_id' => $source->id,
                'title' => $source->title,
                'source_url' => $source->url,
                'library_verified_at' => $source->verified_at,
                'library_excerpt' => mb_substr($source->excerpt, 0, 4000),
            ]);
            if ($reference->isDirty()) {
                $reference->fill(['verified_at' => null, 'verified_by' => null])->save();
            }
        }, 3);
    }

    public function publish(SimulationScenario $scenario, User $approver): ScenarioVersion
    {
        return DB::transaction(function () use ($scenario, $approver): ScenarioVersion {
            $scenario = SimulationScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            $version = $scenario->versions()->lockForUpdate()->orderByDesc('version')->firstOrFail();
            if ($version->status === 'published') {
                return $version;
            }
            $version->load(['roles', 'stages.actionRules', 'evidence', 'legalReferences', 'rubric.criteria']);
            $errors = [];
            if ($version->roles->where('is_student_selectable', true)->isEmpty() || $version->roles->count() < 2) {
                $errors['roles'] = 'Kamida bitta talaba tanlaydigan rol va boshqa ishtirokchi roli bo‘lishi kerak.';
            }
            if ($version->stages->count() < 2 || $version->stages->where('is_terminal', true)->isEmpty()) {
                $errors['stages'] = 'Boshlanish va terminal bosqichlari to‘liq sozlanishi kerak.';
            }
            if ($version->evidence->where('status', 'active')->isEmpty()) {
                $errors['evidence'] = 'Kamida bitta faol dalil biriktirilishi kerak.';
            }
            if (! $version->verifiedLegalReferences()->exists()) {
                $errors['legal_reference'] = 'Kamida bitta o‘qituvchi tasdiqlagan huquqiy manba kerak.';
            }
            if (! $version->rubric || $version->rubric->criteria->isEmpty()) {
                $errors['rubric'] = 'Baholash rubrikasi to‘liq bo‘lishi kerak.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $version->update([
                'status' => 'published',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'published_at' => now(),
                'snapshot_hash' => $this->snapshotHash($version),
            ]);
            $scenario->update(['status' => 'published']);

            return $version->fresh();
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function versionData(array $data, int $version): array
    {
        return [
            'version' => $version,
            'procedure_type' => $data['procedure_type'],
            'learning_objectives' => array_values($data['learning_objectives']),
            'case_brief' => $data['case_brief'],
            'case_facts' => $data['case_facts'],
            'teacher_notes' => $data['teacher_notes'] ?? null,
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
            'published_at' => null,
            'snapshot_hash' => null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function syncTemplate(ScenarioVersion $version, User $editor, array $data): void
    {
        if (! $version->stages()->exists()) {
            foreach ($this->roles() as $index => $role) {
                ScenarioRole::query()->updateOrCreate(
                    ['scenario_version_id' => $version->id, 'key' => $role['key']],
                    [...$role, 'sort_order' => $index + 1],
                );
            }
            $stages = $this->stages();
            foreach ($stages as $index => $stageData) {
                $next = $stages[$index + 1]['key'] ?? null;
                $stage = ScenarioStage::query()->updateOrCreate(
                    ['scenario_version_id' => $version->id, 'key' => $stageData['key']],
                    [
                        'name' => $stageData['name'],
                        'description' => $stageData['description'],
                        'sort_order' => $index + 1,
                        'allowed_next_stages' => $next ? [$next] : [],
                        'is_terminal' => $next === null,
                    ],
                );
                ScenarioActionRule::query()->updateOrCreate(
                    ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'make_statement'],
                    ['label' => 'Fikr bildirish', 'requires_content' => true, 'requires_evidence' => false],
                );
                if ($next) {
                    ScenarioActionRule::query()->updateOrCreate(
                        ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'advance_stage'],
                        ['label' => 'Keyingi bosqichga o‘tish', 'requires_content' => false, 'requires_evidence' => false, 'advances_to_stage_key' => $next],
                    );
                }
                if ($stageData['key'] === 'EVIDENCE_PRESENTATION') {
                    ScenarioActionRule::query()->updateOrCreate(
                        ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'present_evidence'],
                        ['label' => 'Dalilni taqdim etish', 'requires_content' => true, 'requires_evidence' => true, 'scoring_metadata' => ['criterion' => 'evidence_analysis']],
                    );
                }
                if (in_array($stageData['key'], ['WITNESS_EXAMINATION', 'CROSS_EXAMINATION'], true)) {
                    ScenarioActionRule::query()->updateOrCreate(
                        ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'ask_question'],
                        ['label' => 'Savol berish', 'requires_content' => true, 'requires_evidence' => false, 'scoring_metadata' => ['criterion' => 'questioning']],
                    );
                }
                if ($stageData['key'] === 'OBJECTIONS') {
                    ScenarioActionRule::query()->updateOrCreate(
                        ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'make_objection'],
                        ['label' => 'E’tiroz bildirish', 'requires_content' => true, 'requires_evidence' => false, 'scoring_metadata' => ['criterion' => 'procedure']],
                    );
                }
            }

        }

        $evidence = ScenarioEvidence::query()->firstOrNew(['scenario_version_id' => $version->id, 'sort_order' => 1]);
        $evidence->fill([
            'title' => $data['evidence_title'],
            'type' => 'document',
            'summary' => $data['evidence_summary'],
            'body' => $data['evidence_body'],
            'source_label' => $data['evidence_source_label'],
            'version' => $evidence->exists ? $evidence->version + 1 : 1,
            'visibility' => 'all',
            'status' => 'active',
            'sort_order' => 1,
            'checksum' => hash('sha256', $data['evidence_body']),
        ])->save();
        if (filled($data['legal_reference_url'] ?? null)) {
            ScenarioLegalReference::query()->whereNull('library_verified_at')->whereNull('legal_source_id')->updateOrCreate(
                ['scenario_version_id' => $version->id, 'source_url' => $data['legal_reference_url']],
                [
                    'title' => $data['legal_reference_title'],
                    'citation_text' => $data['legal_reference_note'],
                    'verified_at' => now(),
                    'verified_by' => $editor->id,
                ],
            );
        }

        if (! $version->rubric()->exists()) {
            $rubric = $version->rubric()->updateOrCreate([], ['title' => 'Virtual sud rubrikasi', 'total_score' => 100, 'status' => 'draft']);
            foreach ($this->criteria() as $index => $criterion) {
                EvaluationCriterion::query()->updateOrCreate(
                    ['evaluation_rubric_id' => $rubric->id, 'key' => $criterion['key']],
                    [...$criterion, 'description' => $criterion['name'].' bo‘yicha session harakatlari.', 'max_score' => $criterion['weight'], 'guidance' => 'Har bir ball action/event bilan izohlansin.', 'sort_order' => $index + 1],
                );
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function roles(): array
    {
        return [
            ['key' => 'judge', 'name' => 'Sudya', 'description' => 'Jarayonni xolis boshqaradi.', 'instructions' => 'Protsessual tartib va xolislikni saqlang.', 'private_context' => 'Tasdiqlanmagan faktni qabul qilmang.', 'is_student_selectable' => true],
            ['key' => 'prosecutor', 'name' => 'Da’vogar vakili', 'description' => 'Talabni dalillar bilan asoslaydi.', 'instructions' => 'Fakt, dalil va manbani ajrating.', 'private_context' => 'Faqat scenario faktlariga tayaning.', 'is_student_selectable' => true],
            ['key' => 'defense_attorney', 'name' => 'Javobgar vakili', 'description' => 'Qarshi pozitsiya va dalillarni tekshiradi.', 'instructions' => 'Qarama-qarshi dalilni professional tekshiring.', 'private_context' => 'Yangi fakt yaratmang.', 'is_student_selectable' => true],
            ['key' => 'witness', 'name' => 'Guvoh — AI rol', 'description' => 'Scenario doirasida savollarga javob beradi.', 'instructions' => 'Faqat berilgan ma’lumotni ayting.', 'private_context' => 'Javob yo‘q bo‘lsa buni tan oling.', 'is_student_selectable' => false],
        ];
    }

    /** @return list<array{key: string, name: string, description: string}> */
    private function stages(): array
    {
        return [
            ['key' => 'PREPARATION', 'name' => 'Tayyorgarlik', 'description' => 'Rol va materiallar bilan tanishish.'],
            ['key' => 'COURT_OPENING', 'name' => 'Sudni ochish', 'description' => 'Majlis qoidalarini e’lon qilish.'],
            ['key' => 'PARTICIPANT_IDENTIFICATION', 'name' => 'Ishtirokchilar', 'description' => 'Rollarni aniqlash.'],
            ['key' => 'OPENING_STATEMENTS', 'name' => 'Kirish nutqlari', 'description' => 'Boshlang‘ich pozitsiyalar.'],
            ['key' => 'EVIDENCE_PRESENTATION', 'name' => 'Dalillar', 'description' => 'Dalillarni taqdim etish.'],
            ['key' => 'WITNESS_EXAMINATION', 'name' => 'Guvoh so‘rovi', 'description' => 'Asosiy savollar.'],
            ['key' => 'CROSS_EXAMINATION', 'name' => 'Qarama-qarshi so‘roq', 'description' => 'Ko‘rsatmalarni tekshirish.'],
            ['key' => 'OBJECTIONS', 'name' => 'E’tirozlar', 'description' => 'Protsessual e’tirozlar.'],
            ['key' => 'CLOSING_ARGUMENTS', 'name' => 'Yakuniy nutqlar', 'description' => 'Yakuniy pozitsiya.'],
            ['key' => 'DECISION_OR_VERDICT', 'name' => 'Qaror loyihasi', 'description' => 'Rolga mos xulosa.'],
            ['key' => 'EVALUATION', 'name' => 'Baholash', 'description' => 'Auditli hisobot.'],
        ];
    }

    /** @return list<array{key: string, name: string, weight: int}> */
    private function criteria(): array
    {
        return [
            ['key' => 'legal_knowledge', 'name' => 'Huquqiy bilim', 'weight' => 15],
            ['key' => 'legal_reasoning', 'name' => 'Huquqiy asoslash', 'weight' => 15],
            ['key' => 'evidence_analysis', 'name' => 'Dalillar tahlili', 'weight' => 12],
            ['key' => 'procedure', 'name' => 'Protsessual bilim', 'weight' => 12],
            ['key' => 'questioning', 'name' => 'Savol berish', 'weight' => 10],
            ['key' => 'speech', 'name' => 'Nutq madaniyati', 'weight' => 10],
            ['key' => 'critical_thinking', 'name' => 'Tanqidiy fikrlash', 'weight' => 10],
            ['key' => 'strategy', 'name' => 'Strategiya', 'weight' => 8],
            ['key' => 'ethics', 'name' => 'Etika', 'weight' => 8],
        ];
    }

    private function snapshotHash(ScenarioVersion $version): string
    {
        return hash('sha256', json_encode([
            'version' => $version->only(['id', 'version', 'procedure_type', 'learning_objectives', 'case_brief', 'case_facts']),
            'roles' => $version->roles->sortBy('sort_order')->map->only(['key', 'name', 'instructions', 'private_context', 'is_student_selectable'])->values(),
            'stages' => $version->stages->sortBy('sort_order')->map->only(['key', 'name', 'allowed_next_stages', 'is_terminal'])->values(),
            'action_rules' => $version->stages->sortBy('sort_order')->mapWithKeys(fn (ScenarioStage $stage): array => [
                $stage->key => $stage->actionRules->sortBy(['role_key', 'action_type'])->map->only([
                    'role_key', 'action_type', 'requires_content', 'requires_evidence', 'advances_to_stage_key', 'response_role_keys', 'scoring_metadata',
                ])->values(),
            ]),
            'evidence' => $version->evidence->sortBy('sort_order')->map->only(['id', 'title', 'checksum', 'version', 'visibility'])->values(),
            'references' => $version->legalReferences->map->only(['id', 'title', 'source_url', 'verified_at', 'citation_text', 'library_verified_at', 'library_excerpt'])->values(),
            'criteria' => $version->rubric?->criteria->sortBy('sort_order')->map->only(['key', 'weight', 'max_score'])->values(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
