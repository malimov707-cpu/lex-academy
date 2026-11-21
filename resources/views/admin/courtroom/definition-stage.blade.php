<fieldset data-repeater-row class="rounded-xl border p-4" style="border-color:var(--edu-line)">
    <legend>{{ __('Jarayon bosqichi') }}</legend>
    <div class="grid gap-4 md:grid-cols-2">
        <label class="edu-field"><span>{{ __('Bosqich kaliti') }}</span><input data-stage-key class="edu-input" name="{{ $prefix }}[key]" value="{{ $stage['key'] ?? '' }}" required pattern="[A-Z][A-Z0-9_]*"></label>
        <label class="edu-field"><span>{{ __('Nomi') }}</span><input class="edu-input" name="{{ $prefix }}[name]" value="{{ $stage['name'] ?? '' }}" required></label>
        <label class="edu-field md:col-span-2"><span>{{ __('Tavsif') }}</span><textarea class="edu-input" name="{{ $prefix }}[description]">{{ $stage['description'] ?? '' }}</textarea></label>
        <label class="edu-field"><span>{{ __('Yakuniy bosqichmi?') }}</span><select class="edu-input" name="{{ $prefix }}[is_terminal]"><option value="0">{{ __('Yo‘q') }}</option><option value="1" @selected($stage['is_terminal'] ?? false)>{{ __('Ha') }}</option></select></label>
        <label class="edu-field"><span>{{ __('Ruxsat etilgan keyingi bosqichlar') }}</span><select multiple data-stage-options class="edu-input" name="{{ $prefix }}[allowed_next_stages][]">@foreach($stageKeys as $key)<option value="{{ $key }}" @selected(in_array($key, $stage['allowed_next_stages'] ?? [], true))>{{ $key }}</option>@endforeach</select></label>
    </div>
    <div data-repeater="actions" class="mt-4 space-y-3">
        <div data-rows class="space-y-3">@foreach($stage['actions'] ?? [] as $index => $action)@include('admin.courtroom.definition-action', ['prefix' => $prefix.'[actions]['.$index.']', 'action' => $action])@endforeach</div>
        <button type="button" data-add-row="action-template" class="edu-btn edu-btn-secondary">{{ __('Harakat qo‘shish') }}</button>
    </div>
    <div class="mt-4 flex flex-wrap gap-2"><button type="button" data-move-row="up" class="edu-btn edu-btn-ghost">↑ {{ __('Yuqoriga') }}</button><button type="button" data-move-row="down" class="edu-btn edu-btn-ghost">↓ {{ __('Pastga') }}</button><button type="button" data-remove-row class="edu-btn edu-btn-ghost">{{ __('Bosqichni olib tashlash') }}</button></div>
</fieldset>
