<fieldset data-repeater-row class="rounded-xl border p-4" style="border-color:var(--edu-line)">
    <legend>{{ __('Ishtirokchi roli') }}</legend>
    <div class="grid gap-4 md:grid-cols-2">
        <label class="edu-field"><span>{{ __('Rol kaliti') }}</span><input class="edu-input" data-role-key name="{{ $prefix }}[key]" value="{{ $role['key'] ?? '' }}" required pattern="[a-z][a-z0-9_]*"></label>
        <label class="edu-field"><span>{{ __('Nomi') }}</span><input class="edu-input" name="{{ $prefix }}[name]" value="{{ $role['name'] ?? '' }}" required></label>
        <label class="edu-field md:col-span-2"><span>{{ __('Tavsif') }}</span><textarea class="edu-input" name="{{ $prefix }}[description]" required>{{ $role['description'] ?? '' }}</textarea></label>
        <label class="edu-field"><span>{{ __('Umumiy yo‘riqnoma') }}</span><textarea class="edu-input" name="{{ $prefix }}[instructions]" rows="4" required>{{ $role['instructions'] ?? '' }}</textarea></label>
        <label class="edu-field"><span>{{ __('Faqat shu rol uchun maxfiy kontekst') }}</span><textarea class="edu-input" name="{{ $prefix }}[private_context]" rows="4">{{ $role['private_context'] ?? '' }}</textarea></label>
        <label class="edu-field"><span>{{ __('Talaba tanlay oladimi?') }}</span><select class="edu-input" name="{{ $prefix }}[is_student_selectable]"><option value="1" @selected($role['is_student_selectable'] ?? false)>{{ __('Ha') }}</option><option value="0" @selected(!($role['is_student_selectable'] ?? false))>{{ __('Faqat AI') }}</option></select></label>
    </div>
    <button type="button" data-remove-row class="edu-btn edu-btn-ghost mt-3">{{ __('Rolni olib tashlash') }}</button>
</fieldset>
