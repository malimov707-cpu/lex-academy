<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCourtroomDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('scenario')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'list', 'min:2', 'max:12'],
            'roles.*' => ['required', 'array:key,name,description,instructions,private_context,is_student_selectable'],
            'roles.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,59}$/', 'distinct'],
            'roles.*.name' => ['required', 'string', 'max:255'],
            'roles.*.description' => ['required', 'string', 'max:2000'],
            'roles.*.instructions' => ['required', 'string', 'max:8000'],
            'roles.*.private_context' => ['nullable', 'string', 'max:8000'],
            'roles.*.is_student_selectable' => ['required', 'boolean'],
            'stages' => ['required', 'array', 'list', 'min:2', 'max:24'],
            'stages.*' => ['required', 'array:key,name,description,is_terminal,allowed_next_stages,actions'],
            'stages.*.key' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9_]{0,79}$/', 'distinct'],
            'stages.*.name' => ['required', 'string', 'max:255'],
            'stages.*.description' => ['nullable', 'string', 'max:2000'],
            'stages.*.is_terminal' => ['required', 'boolean'],
            'stages.*.allowed_next_stages' => ['sometimes', 'array', 'max:24'],
            'stages.*.allowed_next_stages.*' => ['required', 'string', 'max:80'],
            'stages.*.actions' => ['sometimes', 'array', 'max:24'],
            'stages.*.actions.*' => ['required', 'array:role_key,action_type,label,requires_content,requires_evidence,advances_to_stage_key,response_role_keys'],
            'stages.*.actions.*.role_key' => ['required', 'string', 'max:60'],
            'stages.*.actions.*.action_type' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,79}$/'],
            'stages.*.actions.*.label' => ['required', 'string', 'max:255'],
            'stages.*.actions.*.requires_content' => ['required', 'boolean'],
            'stages.*.actions.*.requires_evidence' => ['required', 'boolean'],
            'stages.*.actions.*.advances_to_stage_key' => ['nullable', 'string', 'max:80'],
            'stages.*.actions.*.response_role_keys' => ['nullable', 'array', 'list', 'max:12'],
            'stages.*.actions.*.response_role_keys.*' => ['required', 'string', 'max:60'],
            'criteria' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'criteria.*' => ['required', 'array:key,name,description,weight,guidance'],
            'criteria.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,79}$/', 'distinct'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['required', 'string', 'max:2000'],
            'criteria.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'criteria.*.guidance' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $roles = collect($this->input('roles'))->pluck('key')->all();
            $stages = collect($this->input('stages'))->keyBy('key');
            if (! collect($this->input('roles'))->contains(fn (array $role): bool => (bool) $role['is_student_selectable'])) {
                $validator->errors()->add('roles', 'Kamida bitta talaba tanlaydigan rol kerak.');
            }
            if ((int) collect($this->input('criteria'))->sum('weight') !== 100) {
                $validator->errors()->add('criteria', 'Mezonlar vazni jami 100 bo‘lishi kerak.');
            }
            foreach ($this->input('stages') as $index => $stage) {
                $next = $stage['allowed_next_stages'] ?? [];
                if (array_diff($next, $stages->keys()->all()) || ((bool) $stage['is_terminal'] && $next)) {
                    $validator->errors()->add("stages.$index.allowed_next_stages", 'Bosqich o‘tishlari noto‘g‘ri.');
                }
                $pairs = [];
                $transitions = [];
                foreach ($stage['actions'] ?? [] as $actionIndex => $action) {
                    $pair = $action['role_key'].'|'.$action['action_type'];
                    if ((! in_array($action['role_key'], [...$roles, '*'], true)) || in_array($pair, $pairs, true)) {
                        $validator->errors()->add("stages.$index.actions", 'Harakat roli noma’lum yoki bir xil rol uchun takrorlangan.');
                    }
                    $pairs[] = $pair;
                    $responseRoleKeys = $action['response_role_keys'] ?? [];
                    if ($responseRoleKeys && (array_diff($responseRoleKeys, $roles)
                        || count(array_unique($responseRoleKeys)) !== count($responseRoleKeys)
                        || $responseRoleKeys === [$action['role_key']]
                        || ! $action['requires_content'] || $action['action_type'] === 'advance_stage' || ($action['advances_to_stage_key'] ?? null))) {
                        $validator->errors()->add("stages.$index.actions.$actionIndex.response_role_keys", 'Javob beruvchilar alohida mavjud rollar bo‘lishi kerak. Ularni matnli va bosqichni o‘zgartirmaydigan harakatga biriktiring.');
                    }
                    if ($action['advances_to_stage_key'] ?? null) {
                        $transitions[] = $action['advances_to_stage_key'];
                        if (! in_array($action['advances_to_stage_key'], $next, true)) {
                            $validator->errors()->add("stages.$index.actions", 'Harakat faqat ruxsat etilgan keyingi bosqichga o‘tishi mumkin.');
                        }
                    }
                }
                if (! $stage['is_terminal'] && (! $next || array_diff($next, $transitions))) {
                    $validator->errors()->add("stages.$index.actions", 'Har bir keyingi bosqich uchun o‘tish harakati kerak.');
                }
            }
            $visited = [];
            $queue = [$stages->keys()->first()];
            while ($queue) {
                $key = array_shift($queue);
                if (isset($visited[$key]) || ! $stages->has($key)) {
                    continue;
                }
                $visited[$key] = true;
                array_push($queue, ...($stages[$key]['allowed_next_stages'] ?? []));
            }
            $canFinish = $stages->filter(fn (array $stage): bool => (bool) $stage['is_terminal'])->keys()->all();
            do {
                $before = count($canFinish);
                foreach ($stages as $key => $stage) {
                    if (array_intersect($stage['allowed_next_stages'] ?? [], $canFinish) && ! in_array($key, $canFinish, true)) {
                        $canFinish[] = $key;
                    }
                }
            } while (count($canFinish) !== $before);
            if (count($visited) !== $stages->count() || count($canFinish) !== $stages->count()) {
                $validator->errors()->add('stages', 'Barcha bosqichlarga yetib borish va ulardan yakuniy bosqichga o‘tish mumkin bo‘lishi kerak.');
            }
        }];
    }
}
