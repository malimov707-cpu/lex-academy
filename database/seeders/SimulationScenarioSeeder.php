<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\EvaluationCriterion;
use App\Models\ScenarioActionRule;
use App\Models\ScenarioEvidence;
use App\Models\ScenarioLegalReference;
use App\Models\ScenarioRole;
use App\Models\ScenarioStage;
use App\Models\ScenarioVersion;
use App\Models\SimulationScenario;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SimulationScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->where('role', UserRole::Teacher)->first()
            ?? User::query()->whereIn('role', [
                UserRole::Teacher->value,
                UserRole::AcademyAdmin->value,
                UserRole::SuperAdmin->value,
            ])->firstOrFail();

        DB::transaction(function () use ($creator): void {
            User::query()->lockForUpdate()->findOrFail($creator->id);
            foreach (File::json(database_path('seeders/courtroom-cases.json')) as $case) {
                $scenario = SimulationScenario::query()->firstOrCreate(['slug' => $case['slug']], [
                    'created_by' => $creator->id,
                    'title' => $case['title'],
                    'description' => $case['description'],
                    'legal_area' => $case['legal_area'] ?? 'civil',
                    'case_type' => $case['case_type'],
                    'difficulty' => $case['difficulty'],
                    'estimated_duration_minutes' => $case['estimated_duration_minutes'],
                    'status' => 'draft',
                ]);
                if ($scenario->wasRecentlyCreated) {
                    $this->installDraft($scenario, $case);
                } else {
                    $version = $scenario->versions()->orderByDesc('version')->first();
                    if ($version) {
                        $creator = $scenario->creator()->first();
                        $scenario->update(['status' => 'published']);
                        $version->update([
                            'status' => 'published',
                            'approved_by' => $creator?->id,
                            'approved_at' => $creator ? now() : null,
                            'published_at' => $creator ? now() : null,
                        ]);
                        if ($creator) {
                            $version->legalReferences()->update([
                                'verified_at' => now(),
                                'verified_by' => $creator->id,
                            ]);
                        }
                        ScenarioRole::query()->where('scenario_version_id', $version->id)
                            ->whereIn('key', [
                                'victim_advocate', 'victim', 'defense_advocate', 'defendant',
                                'witness_1', 'witness_2', 'witness_3',
                            ])
                            ->delete();
                    }
                }
            }
        });
    }

    /** @param array{case_brief: string, case_facts: string, learning_objectives: list<string>, teacher_notes: string, claimant_context: string, respondent_context: string, witness: array{name: string, description: string, instructions: string, private_context: string}, evidence: list<array{title: string, type: string, summary: string, body: string, source_label: string}>, substantive_reference: array{title: string, source_url: string, citation_text: string}} $case */
    private function installDraft(SimulationScenario $scenario, array $case): void
    {
        $version = ScenarioVersion::query()->updateOrCreate(
            ['scenario_id' => $scenario->id, 'version' => 1],
            [
                'procedure_type' => 'civil_claim_training',
                'learning_objectives' => $case['learning_objectives'],
                'case_brief' => $case['case_brief'],
                'case_facts' => $case['case_facts'],
                'teacher_notes' => $case['teacher_notes'],
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
                'published_at' => null,
            ],
        );

        $roles = [
            ['key' => 'judge', 'name' => 'Sudya', 'description' => 'Jarayon tartibini boshqaradi va taraflarning dalillarini xolis tinglaydi.', 'instructions' => 'Bosqich tartibini saqlang, savollarni xolis bering va xulosani faqat ko‘rsatilgan faktlarga tayang.', 'private_context' => 'Taraflardan birini qo‘llab-quvvatlamang. Yetishmagan dalilni fakt sifatida qabul qilmang.', 'is_student_selectable' => true],
            ['key' => 'prosecutor', 'name' => 'Da’vogar vakili', 'description' => 'Da’vogar pozitsiyasini bayon qiladi va talabni dalillar bilan asoslaydi.', 'instructions' => 'Talab, fakt, dalil va huquqiy manbani aniq ajrating.', 'private_context' => $case['claimant_context'], 'is_student_selectable' => true],
            ['key' => 'defense_attorney', 'name' => 'Javobgar vakili', 'description' => 'Javobgar pozitsiyasini himoya qiladi va qarshi dalillarni tekshiradi.', 'instructions' => 'Qarama-qarshi dalilni tekshiring, taxminni fakt sifatida ko‘rsatmang.', 'private_context' => $case['respondent_context'], 'is_student_selectable' => true],
            ['key' => 'witness', ...$case['witness'], 'is_student_selectable' => false],
        ];
        foreach ($roles as $index => $role) {
            ScenarioRole::query()->updateOrCreate(
                ['scenario_version_id' => $version->id, 'key' => $role['key']],
                [...$role, 'sort_order' => $index + 1],
            );
        }

        $stages = [
            ['key' => 'PREPARATION', 'name' => 'Tayyorgarlik', 'description' => 'Ish materiallari, rol vazifasi va dalillar bilan tanishing.'],
            ['key' => 'COURT_OPENING', 'name' => 'Sud majlisini ochish', 'description' => 'Majlis qoidalari va vazifalari e’lon qilinadi.'],
            ['key' => 'PARTICIPANT_IDENTIFICATION', 'name' => 'Ishtirokchilarni aniqlash', 'description' => 'Rollar va ishtirok etish vakolati qayd etiladi.'],
            ['key' => 'OPENING_STATEMENTS', 'name' => 'Kirish nutqlari', 'description' => 'Taraflar pozitsiyasini qisqa va aniq bayon qiladi.'],
            ['key' => 'EVIDENCE_PRESENTATION', 'name' => 'Dalillarni taqdim etish', 'description' => 'Dalilning manbasi, mazmuni va ahamiyati tushuntiriladi.'],
            ['key' => 'WITNESS_EXAMINATION', 'name' => 'Guvohni so‘roq qilish', 'description' => 'Aniq va maqsadli savollar beriladi.'],
            ['key' => 'CROSS_EXAMINATION', 'name' => 'Qarama-qarshi so‘roq', 'description' => 'Oldingi ko‘rsatmalar va hujjatlar tekshiriladi.'],
            ['key' => 'OBJECTIONS', 'name' => 'E’tirozlar', 'description' => 'Protsessual e’tiroz va javoblar ko‘rib chiqiladi.'],
            ['key' => 'CLOSING_ARGUMENTS', 'name' => 'Yakuniy nutqlar', 'description' => 'Fakt, dalil va manba asosidagi yakuniy pozitsiya bayon qilinadi.'],
            ['key' => 'DECISION_OR_VERDICT', 'name' => 'Qaror loyihasi', 'description' => 'Sudya yoki taraf o‘z roliga mos yakuniy xulosani taqdim etadi.'],
            ['key' => 'EVALUATION', 'name' => 'Baholash', 'description' => 'Harakatlar va dalillardan foydalanish bo‘yicha auditli hisobot yaratiladi.', 'is_terminal' => true],
        ];
        $stageModels = [];
        foreach ($stages as $index => $stage) {
            $next = $stages[$index + 1]['key'] ?? null;
            $stageModels[$stage['key']] = ScenarioStage::query()->updateOrCreate(
                ['scenario_version_id' => $version->id, 'key' => $stage['key']],
                [
                    'name' => $stage['name'],
                    'description' => $stage['description'],
                    'sort_order' => $index + 1,
                    'allowed_next_stages' => $next ? [$next] : [],
                    'is_terminal' => $stage['is_terminal'] ?? false,
                ],
            );
        }

        foreach ($stageModels as $key => $stage) {
            ScenarioActionRule::query()->updateOrCreate(
                ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'make_statement'],
                ['label' => 'Fikr bildirish', 'requires_content' => true, 'requires_evidence' => false],
            );
            $next = $stage->allowed_next_stages[0] ?? null;
            if ($next) {
                ScenarioActionRule::query()->updateOrCreate(
                    ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'advance_stage'],
                    ['label' => 'Keyingi bosqichga o‘tish', 'requires_content' => false, 'requires_evidence' => false, 'advances_to_stage_key' => $next],
                );
            }
            if ($key === 'EVIDENCE_PRESENTATION') {
                ScenarioActionRule::query()->updateOrCreate(
                    ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'present_evidence'],
                    ['label' => 'Dalilni taqdim etish', 'requires_content' => true, 'requires_evidence' => true, 'scoring_metadata' => ['criterion' => 'evidence_analysis']],
                );
            }
            if (in_array($key, ['WITNESS_EXAMINATION', 'CROSS_EXAMINATION'], true)) {
                ScenarioActionRule::query()->updateOrCreate(
                    ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'ask_question'],
                    ['label' => 'Savol berish', 'requires_content' => true, 'requires_evidence' => false, 'scoring_metadata' => ['criterion' => 'questioning']],
                );
            }
            if ($key === 'OBJECTIONS') {
                ScenarioActionRule::query()->updateOrCreate(
                    ['scenario_stage_id' => $stage->id, 'role_key' => '*', 'action_type' => 'make_objection'],
                    ['label' => 'E’tiroz bildirish', 'requires_content' => true, 'requires_evidence' => false, 'scoring_metadata' => ['criterion' => 'procedure']],
                );
            }
        }

        $evidence = $case['evidence'];
        foreach ($evidence as $index => $item) {
            ScenarioEvidence::query()->updateOrCreate(
                ['scenario_version_id' => $version->id, 'title' => $item['title']],
                [...$item, 'version' => 1, 'visibility' => 'all', 'status' => 'active', 'sort_order' => $index + 1, 'checksum' => hash('sha256', $item['body'])],
            );
        }

        $references = [
            $case['substantive_reference'],
            ['title' => 'O‘zbekiston Respublikasining Fuqarolik protsessual kodeksi', 'source_url' => 'https://lex.uz/docs/-3517337', 'citation_text' => 'Rasmiy LexUZ hujjati. O‘quv sudining protsessual bosqichlarini muhokama qilish uchun birlamchi manba. Amaldagi tahrir mashg‘ulot vaqtida qayta tekshiriladi.'],
        ];
        foreach ($references as $reference) {
            ScenarioLegalReference::query()->updateOrCreate(
                ['scenario_version_id' => $version->id, 'source_url' => $reference['source_url']],
                [...$reference, 'verified_at' => null, 'verified_by' => null],
            );
        }

        $rubric = $version->rubric()->updateOrCreate([], ['title' => 'Virtual sud — asosiy rubrika', 'total_score' => 100, 'status' => 'published']);
        $criteria = [
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
        foreach ($criteria as $index => $criterion) {
            EvaluationCriterion::query()->updateOrCreate(
                ['evaluation_rubric_id' => $rubric->id, 'key' => $criterion['key']],
                [...$criterion, 'description' => $criterion['name'].' bo‘yicha real session harakatlari tahlili.', 'max_score' => $criterion['weight'], 'guidance' => 'Ball faqat action va event dalillari bilan izohlanadi.', 'sort_order' => $index + 1],
            );
        }

        $version->update(['snapshot_hash' => hash('sha256', json_encode([
            'objectives' => $case['learning_objectives'],
            'brief' => $case['case_brief'],
            'facts' => $case['case_facts'],
            'roles' => $roles,
            'stages' => $stages,
            'evidence' => $evidence,
            'references' => $references,
            'criteria' => $criteria,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))]);

        if (($case['slug'] ?? null) === 'uyga-kiralish-bilan-oqrilik') {
            $creator = $scenario->creator()->first();
            if ($creator) {
                $scenario->update(['status' => 'published']);
                $version->update([
                    'status' => 'published',
                    'approved_by' => $creator->id,
                    'approved_at' => now(),
                    'published_at' => now(),
                ]);
                $version->legalReferences()->update([
                    'verified_at' => now(),
                    'verified_by' => $creator->id,
                ]);
            }
        }
    }
}
