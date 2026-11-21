<?php

namespace App\Services\VirtualCourtroom;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupAssessmentRubric
{
    public const TEAM = [
        'legal' => ['Huquqiy asoslash', 20],
        'evidence' => ['Dalillarning kuchi va ishonchliligi', 20],
        'questions' => ['Savol berish sifati', 15],
        'rebuttal' => ['Qarshi argumentlarga javob', 15],
        'procedure' => ['Protsessual harakatlar', 15],
        'closing' => ['Yakuniy nutq sifati', 15],
    ];

    public const GRADEBOOK = ['attendance', 'classroom_activity', 'simulation_performance', 'individual_role_score', 'teacher_final_grade'];

    public static function teamCriteria(): array
    {
        return self::TEAM;
    }

    public static function teamCriterionLabel(string $key): ?string
    {
        return self::TEAM[$key][0] ?? null;
    }

    /** @return array<string, mixed> */
    public function snapshot(array $config = []): array
    {
        Validator::make(['config' => $config], [
            'config' => ['array:tie_threshold,team_weights,academic,gradebook'],
            'config.tie_threshold' => ['sometimes', 'numeric', 'between:0,100', 'decimal:0,2'],
            'config.team_weights' => ['sometimes', 'array:'.implode(',', array_keys(self::TEAM)), 'required_array_keys:'.implode(',', array_keys(self::TEAM))],
            'config.team_weights.*' => ['required', 'integer', 'between:1,100'],
            'config.academic' => ['sometimes', 'array:active,observer'],
            'config.academic.*' => ['required', 'array', 'min:1', 'max:10'],
            'config.academic.*.*' => ['required', 'array:label,max'],
            'config.academic.*.*.label' => ['required', 'string', 'max:120'],
            'config.academic.*.*.max' => ['required', 'integer', 'between:1,10'],
            'config.gradebook' => ['sometimes', 'list', 'array', 'max:5'],
            'config.gradebook.*' => ['required', 'string', 'distinct', Rule::in(self::GRADEBOOK)],
        ])->validate();

        $team = [];
        foreach (self::TEAM as $key => [$label, $weight]) {
            $team[$key] = ['label' => $label, 'weight' => (int) ($config['team_weights'][$key] ?? $weight)];
        }
        if (array_sum(array_column($team, 'weight')) !== 100) {
            throw ValidationException::withMessages(['team_weights' => 'Jamoaviy mezonlar ulushi jami 100 bo‘lishi kerak.']);
        }

        $academic = $this->academicDefaults();
        foreach ($config['academic'] ?? [] as $group => $criteria) {
            if (array_sum(array_column($criteria, 'max')) !== 10) {
                throw ValidationException::withMessages(['academic.'.$group => 'Faollik mezonlarining maksimal ballari jami 10 bo‘lishi kerak.']);
            }
            $academic[$group] = [];
            foreach ($criteria as $key => $criterion) {
                if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $key)) {
                    throw ValidationException::withMessages(['academic.'.$group => 'Mezon kaliti lotincha harf bilan boshlanib, kichik harflar, raqamlar va pastki chiziqlardan tuziladi.']);
                }
                $academic[$group][$key] = ['label' => trim($criterion['label']), 'max' => (int) $criterion['max']];
            }
        }
        $settings = [
            'roles' => $this->roles(), 'academic' => $academic, 'team' => $team,
            'tie_threshold' => (float) ($config['tie_threshold'] ?? 2),
            'gradebook' => array_values(array_intersect(self::GRADEBOOK, $config['gradebook'] ?? ['teacher_final_grade'])),
        ];

        return ['version' => 'group-v1-'.substr(hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR)), 0, 16), ...$settings];
    }

    /** @return array<string, array<string, array{label:string,max:int}>> */
    private function academicDefaults(): array
    {
        $active = $this->criteria([
            'attendance' => ['Davomat va o‘z vaqtida qatnashish', 2],
            'preparation' => ['Mashg‘ulotga tayyorgarlik', 2],
            'participation' => ['Faol ishtirok', 2],
            'reasoning' => ['Huquqiy fikrlash va asoslash', 2],
            'teamwork' => ['Jamoaviy ish va intizom', 2],
        ]);
        $observer = $active;
        $observer['participation']['label'] = 'Kuzatuv topshiriqlari va yozma tahlilda ishtirok';

        return ['active' => $active, 'observer' => $observer];
    }

    /** @return array<string, array<string, array{label:string,max:int}>> */
    private function roles(): array
    {
        $witness = $this->criteria([
            'facts' => ['Ssenariydagi faktlar va guvohga berilgan vazifaga muvofiqlik', 20],
            'answers' => ['Savollarga aniq javob', 15],
            'consistency' => ['Ssenariy doirasidagi ko‘rsatmalarning ichki izchilligi', 20],
            'role' => ['Rol chegaralariga rioya qilish', 15],
            'communication' => ['So‘roq jarayonidagi muloqot', 15],
            'evidence' => ['Ko‘rsatmani hujjat yoki dalilga bog‘lash', 15],
        ]);
        $party = $this->criteria([
            'role' => ['O‘z rolini tushunish', 20],
            'facts' => ['Ssenariy faktlariga muvofiq javob berish', 20],
            'answers' => ['Savollarga aniq javob qaytarish', 20],
            'legal' => ['Huquqiy pozitsiyasini tushuntirish', 20],
            'procedure' => ['Protsessual huquq va majburiyatlarni tushunish', 20],
        ]);
        $victim = $party;
        $victim['legal']['label'] = 'Jabrlanuvchining talabi va huquqiy pozitsiyasini tushuntirish';
        $defendant = $party;
        $defendant['legal']['label'] = 'Ayblanuvchining himoya pozitsiyasi va huquqlarini tushuntirish';

        return [
            'judge' => $this->criteria([
                'impartiality' => ['Xolislik', 15],
                'procedure' => ['Protsessual tartibni boshqarish', 20],
                'equality' => ['Tomonlarga teng imkoniyat berish', 15],
                'evidence' => ['Dalillarni ko‘rib chiqish', 15],
                'objections' => ['E’tirozlarni asosli hal qilish', 15],
                'legal' => ['Yakuniy qarorni huquqiy asoslash', 20],
            ]),
            'prosecutor' => $this->criteria([
                'legal' => ['Ayblov pozitsiyasi va huquqiy asoslarni qo‘llash', 20],
                'evidence' => ['Dalillarni taqdim etish va tekshirish', 20],
                'questions' => ['Guvohlarga asosli savollar berish', 15],
                'rebuttal' => ['Himoya argumentlariga huquqiy javob', 15],
                'procedure' => ['Ayblovni protsessual tartibda ilgari surish', 15],
                'closing' => ['Ayblovning yakuniy nutqi', 15],
            ]),
            'victim_advocate' => $this->criteria([
                'legal' => ['Jabrlanuvchining huquqiy talablarini asoslash', 20],
                'evidence' => ['Jabrlanuvchi foydasiga dalillarni tahlil qilish', 20],
                'questions' => ['Manfaatlarni himoya qiluvchi savollar', 15],
                'rebuttal' => ['Qarshi dalillarga javob va e’tiroz', 15],
                'procedure' => ['Jabrlanuvchi manfaatlarini protsessual himoya qilish', 15],
                'closing' => ['Jabrlanuvchi vakilining yakuniy pozitsiyasi', 15],
            ]),
            'victim' => $victim,
            'defense_advocate' => $this->criteria([
                'legal' => ['Huquqiy asoslangan himoya strategiyasi', 20],
                'evidence' => ['Ayblov dalillarining zaif jihatlarini aniqlash', 20],
                'questions' => ['Qarshi savollar sifati', 15],
                'rebuttal' => ['Huquqiy e’tirozlar va qarshi argumentlar', 15],
                'procedure' => ['Ayblanuvchining protsessual huquqlarini himoya qilish', 15],
                'closing' => ['Yakuniy himoya nutqi', 15],
            ]),
            'defendant' => $defendant,
            'witness_1' => $witness,
            'witness_2' => $witness,
            'witness_3' => $witness,
            'observer' => $this->criteria([
                'stages' => ['Sud bosqichlarini kuzatish va ularni izohlash', 15],
                'errors' => ['Huquqiy xatolarni aniqlash va asoslash', 20],
                'evidence' => ['Dalillarni yozma tahlil qilish', 20],
                'comparison' => ['Tomonlarning argumentlarini taqqoslash', 15],
                'quiz' => ['Qisqa testdagi bilimni namoyish qilish', 10],
                'analysis' => ['Yakuniy tahliliy fikr va tavsiya', 20],
            ]),
        ];
    }

    /**
     * @param  array<string, array{string,int}>  $criteria
     * @return array<string, array{label:string,max:int}>
     */
    private function criteria(array $criteria): array
    {
        foreach ($criteria as $key => [$label, $max]) {
            $criteria[$key] = ['label' => $label, 'max' => $max];
        }

        return $criteria;
    }
}
