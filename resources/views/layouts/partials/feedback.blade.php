{{-- Yagona feedback bloki: sessiya xabari + validatsiya xatolari (a11y live region) --}}
@if ($errors->any())
    <div role="alert" class="edu-feedback edu-feedback-error mb-4">
        <div class="flex-1">
            <strong class="block text-sm font-semibold">{{ __('Ma’lumotlarni tekshiring') }}</strong>
            <ul class="mt-1 list-disc pl-4 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="shrink-0 opacity-60 hover:opacity-100"
                onclick="this.closest('[role=alert]').remove()" aria-label="{{ __('Yopish') }}">×</button>
    </div>
@endif

@if (session('status'))
    <div role="status" aria-live="polite" class="edu-feedback edu-feedback-success mb-4">
        <span class="flex-1">{{ session('status') }}</span>
        <button type="button" class="shrink-0 opacity-60 hover:opacity-100"
                onclick="this.closest('[role=status]').remove()" aria-label="{{ __('Yopish') }}">×</button>
    </div>
@endif
