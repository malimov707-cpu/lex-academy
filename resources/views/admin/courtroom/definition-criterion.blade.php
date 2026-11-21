<fieldset data-repeater-row class="rounded-xl border p-4" style="border-color:var(--edu-line)">
    <legend>{{ __('Baholash mezoni') }}</legend>
    <div class="grid gap-4 md:grid-cols-3">
        <label class="edu-field"><span>{{ __('Mezon kaliti') }}</span><input class="edu-input" name="{{ $prefix }}[key]" value="{{ $criterion['key'] ?? '' }}" required></label>
        <label class="edu-field"><span>{{ __('Nomi') }}</span><input class="edu-input" name="{{ $prefix }}[name]" value="{{ $criterion['name'] ?? '' }}" required></label>
        <label class="edu-field"><span>{{ __('Vazn, %') }}</span><input class="edu-input" type="number" min="1" max="100" name="{{ $prefix }}[weight]" value="{{ $criterion['weight'] ?? 10 }}" required></label>
        <label class="edu-field md:col-span-3"><span>{{ __('Nima baholanadi?') }}</span><textarea class="edu-input" name="{{ $prefix }}[description]" required>{{ $criterion['description'] ?? '' }}</textarea></label>
        <label class="edu-field md:col-span-3"><span>{{ __('O‘qituvchi uchun baholash yo‘riqnomasi') }}</span><textarea class="edu-input" name="{{ $prefix }}[guidance]">{{ $criterion['guidance'] ?? '' }}</textarea></label>
    </div>
    <button type="button" data-remove-row class="edu-btn edu-btn-ghost mt-3">{{ __('Mezonni olib tashlash') }}</button>
</fieldset>
