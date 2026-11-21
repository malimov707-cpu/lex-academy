<fieldset data-repeater-row class="rounded-xl border p-3" style="border-color:var(--edu-line)">
    <legend>{{ __('Ruxsat etilgan harakat') }}</legend>
    <div class="grid gap-3 md:grid-cols-3">
        <label class="edu-field"><span>{{ __('Harakat kaliti') }}</span><input class="edu-input" name="{{ $prefix }}[action_type]" value="{{ $action['action_type'] ?? 'make_statement' }}" required></label>
        <label class="edu-field"><span>{{ __('Tugma nomi') }}</span><input class="edu-input" name="{{ $prefix }}[label]" value="{{ $action['label'] ?? '' }}" required></label>
        <label class="edu-field"><span>{{ __('Rol') }}</span><select data-role-options class="edu-input" name="{{ $prefix }}[role_key]"><option value="*">{{ __('Barcha rollar') }}</option>@foreach($roleKeys as $key)<option value="{{ $key }}" @selected(($action['role_key'] ?? '*') === $key)>{{ $key }}</option>@endforeach</select></label>
        <label class="edu-field"><span>{{ __('Matn talab qilinadi') }}</span><select class="edu-input" name="{{ $prefix }}[requires_content]"><option value="1" @selected($action['requires_content'] ?? true)>{{ __('Ha') }}</option><option value="0" @selected(!($action['requires_content'] ?? true))>{{ __('Yo‘q') }}</option></select></label>
        <label class="edu-field"><span>{{ __('Dalil talab qilinadi') }}</span><select class="edu-input" name="{{ $prefix }}[requires_evidence]"><option value="0">{{ __('Yo‘q') }}</option><option value="1" @selected($action['requires_evidence'] ?? false)>{{ __('Ha') }}</option></select></label>
        <label class="edu-field"><span>{{ __('Harakatdan keyingi bosqich') }}</span><select data-stage-options class="edu-input" name="{{ $prefix }}[advances_to_stage_key]"><option value="">{{ __('Bosqich o‘zgarmaydi') }}</option>@foreach($stageKeys as $key)<option value="{{ $key }}" @selected(($action['advances_to_stage_key'] ?? null) === $key)>{{ $key }}</option>@endforeach</select></label>
        <label class="edu-field">
            <span>{{ __('Javob berishi mumkin bo‘lgan rollar') }}</span>
            <select multiple data-role-options class="edu-input" name="{{ $prefix }}[response_role_keys][]">
                @foreach($roleKeys as $key)
                    <option value="{{ $key }}" @selected(in_array($key, $action['response_role_keys'] ?? [], true))>{{ $key }}</option>
                @endforeach
            </select>
            <small>{{ __('Bo‘sh qoldirilsa, ssenariyning odatiy javob navbati ishlaydi.') }}</small>
        </label>
    </div>
    <button type="button" data-remove-row class="edu-btn edu-btn-ghost mt-2">{{ __('Harakatni olib tashlash') }}</button>
</fieldset>
