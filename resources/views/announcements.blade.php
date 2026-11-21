@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('E’lonlar'))
@section('content')
<div class="staff-shell">
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('E’lonlar') }}</span>
            <h1>{{ __('Akademiya e’lonlari') }}</h1>
            <p>{{ __('Kurs va guruh bo‘yicha rasmiy xabarlar.') }}</p>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">§</span>
    </section>

    @if ($errors->any())<p class="mt-3 text-red-600" role="alert">{{ $errors->first() }}</p>@endif

    <div class="staff-grid mt-5">
        <div class="staff-stack">
            @forelse ($announcements as $announcement)
                <article class="staff-panel">
                    <div class="staff-panel-head !mb-2">
                        <h2>{{ $announcement->title }}</h2>
                        <small style="color:var(--edu-text-muted)">{{ $announcement->created_at ? Illuminate\Support\Facades\Date::parse($announcement->created_at)->format('d.m.Y') : '' }}</small>
                    </div>
                    <p class="whitespace-pre-line text-sm leading-relaxed" style="color:var(--edu-text-muted)">{{ $announcement->body }}</p>
                </article>
            @empty
                <div class="staff-empty">{{ __('Hozircha e’lon yo‘q.') }}</div>
            @endforelse
            {{ $announcements->links() }}
        </div>

        @if (auth()->user()->isAdmin() || auth()->user()->role->value === 'teacher')
            <form method="POST" action="{{ route('announcements.store') }}" class="staff-panel staff-form-grid">@csrf
                <div class="staff-panel-head !mb-0"><h2>{{ __('Yangi e’lon') }}</h2></div>
                <label>{{ __('Sarlavha') }}<input name="title" required class="edu-input"></label>
                <label>{{ __('Matn') }}<textarea name="body" rows="5" required class="edu-input"></textarea></label>
                <label>{{ __('Kurs') }}<select name="course_id" class="edu-input">@if (auth()->user()->isAdmin())<option value="">{{ __('Barchaga') }}</option>@endif @foreach ($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
                <label>{{ __('Guruh ID (ixtiyoriy)') }}<input name="group_id" type="number" class="edu-input"></label>
                <button class="edu-btn edu-btn-primary">{{ __('Nashr qilish') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
