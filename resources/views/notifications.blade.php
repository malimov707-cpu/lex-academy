@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('Bildirishnomalar'))
@section('content')
<div class="staff-shell">
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('Bildirishnomalar') }}</span>
            <h1>{{ __('Yangi xabarlar') }}</h1>
            <p>{{ __('Tizimdagi barcha bildirishnomalar — kurs, topshiriq va tizim hodisalari bo‘yicha.') }}</p>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">◈</span>
    </section>

    <div class="staff-stack mt-6 max-w-3xl">
        @forelse ($notifications as $notification)
            <article class="staff-panel !p-5">
                <div class="staff-row !p-0 !border-0 !shadow-none">
                    <span class="staff-kpi-icon" style="width:38px;height:38px;border-radius:11px"><x-nav-icon name="notification" style="width:18px;height:18px" /></span>
                    <div class="staff-row-main">
                        <strong><a href="{{ $notification->data['path'] }}" style="color:#1765e9">{{ __($notification->data['title']) }}</a></strong>
                        <small>{{ $notification->created_at->format('d.m.Y H:i') }}</small>
                    </div>
                    @if (! $notification->read_at)
                        <form method="POST" action="{{ route('notifications.update', $notification->id) }}">@csrf @method('PATCH')
                            <button class="edu-btn edu-btn-ghost edu-btn-sm">{{ __('O‘qilgan deb belgilash') }}</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="staff-empty">{{ __('Yangi bildirishnoma yo‘q.') }}</div>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>
@endsection
