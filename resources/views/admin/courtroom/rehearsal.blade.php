@extends('layouts.app')
@section('title', __('Ssenariy qoidalarini sinash'))
@section('content')
    <a class="text-sm text-[var(--edu-royal)]" href="{{ route('admin.courtroom.preview', ['scenario' => $scenario, 'version' => $version->version]) }}">← {{ $scenario->title }} · v{{ $version->version }}</a>
    <h1 class="edu-page-title mt-2">{{ __('Ssenariy qoidalarini sinash') }}</h1>
    <p class="edu-page-lead">{{ __('Rol, dalil va bosqich qoidalari talaba sessiyasidagi kabi serverda tekshiriladi. Sinovda AI va mikrofon ishlatilmaydi.') }}</p>

    @if($role && $stage)
        <form method="GET" class="edu-card mt-5 grid gap-4 p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <label class="edu-field">
                <span>{{ __('Talaba roli') }}</span>
                <select class="edu-input" name="role_key">
                    @foreach($roles as $item)<option value="{{ $item->key }}" @selected($item->key === $role->key)>{{ $item->name }}</option>@endforeach
                </select>
            </label>
            <label class="edu-field">
                <span>{{ __('Sinov bosqichi') }}</span>
                <select class="edu-input" name="stage_key">
                    @foreach($version->stages as $item)<option value="{{ $item->key }}" @selected($item->key === $stage->key)>{{ $item->name }}</option>@endforeach
                </select>
            </label>
            <button type="submit" class="edu-btn edu-btn-secondary">{{ __('Ochish') }}</button>
        </form>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <form method="POST" class="edu-card grid gap-4 p-5">
                @csrf
                <input type="hidden" name="role_key" value="{{ $role->key }}">
                <input type="hidden" name="stage_key" value="{{ $stage->key }}">
                <label class="edu-field">
                    <span>{{ __('Harakat') }}</span>
                    <select class="edu-input" name="action_type" required>
                        <option value="">{{ __('Tanlang') }}</option>
                        @foreach($stage->actionRules->whereIn('role_key', ['*', $role->key])->sortByDesc(fn ($rule) => $rule->role_key !== '*')->unique('action_type') as $rule)
                            <option value="{{ $rule->action_type }}" @selected(($input['action_type'] ?? null) === $rule->action_type)>{{ $rule->label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="edu-field">
                    <span>{{ __('Kimga') }}</span>
                    <select class="edu-input" name="target_role_key">
                        <option value="">{{ __('Avtomatik') }}</option>
                        @foreach($version->roles->where('key', '!=', $role->key) as $participant)<option value="{{ $participant->key }}" @selected(($input['target_role_key'] ?? null) === $participant->key)>{{ $participant->name }}</option>@endforeach
                    </select>
                </label>
                <label class="edu-field">
                    <span>{{ __('Dalil') }}</span>
                    <select class="edu-input" name="evidence_id">
                        <option value="">{{ __('Tanlanmagan') }}</option>
                        @foreach($evidence as $item)<option value="{{ $item->id }}" @selected((int) ($input['evidence_id'] ?? 0) === $item->id)>{{ $item->title }}</option>@endforeach
                    </select>
                </label>
                <label class="edu-field">
                    <span>{{ __('Sinov matni') }}</span>
                    <textarea class="edu-input" name="content" rows="3" maxlength="4000">{{ $input['content'] ?? '' }}</textarea>
                </label>
                <button type="submit" class="edu-btn edu-btn-primary justify-self-start">{{ __('Harakatni tekshirish') }}</button>
            </form>

            <section class="edu-card p-5" aria-label="{{ __('Sinov natijasi') }}">
                <h2 class="font-semibold">{{ $stage->name }} · {{ $role->name }}</h2>
                @if($failure)
                    <p class="mt-3" role="alert">{{ $failure }}</p>
                @elseif($result)
                    <p class="mt-3" role="status">{{ __('Harakat qabul qilindi.') }}</p>
                    @if($result['target_role_key'])
                        <p class="mt-2">{{ __('Javob beruvchi') }} {{ $version->roles->firstWhere('key', $result['target_role_key'])?->name }}</p>
                    @endif
                    @if($result['next_stage'])
                        <a class="edu-btn edu-btn-secondary mt-3" href="{{ route('admin.courtroom.rehearsal', ['scenario' => $scenario, 'version' => $version->version, 'role_key' => $role->key, 'stage_key' => $result['next_stage']->key]) }}">{{ $result['next_stage']->name }} →</a>
                    @endif
                @elseif($stage->is_terminal)
                    <p class="mt-3">{{ __('Yakuniy bosqichga yetdingiz.') }}</p>
                @endif
            </section>
        </div>
    @else
        <p class="edu-card mt-5 p-5">{{ __('Sinovni boshlash uchun kamida bitta talaba roli va bosqich kerak.') }}</p>
    @endif
@endsection
