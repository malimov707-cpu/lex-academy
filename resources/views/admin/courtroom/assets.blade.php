@extends('layouts.app')
@section('title', __('3D modellar va xizmat holati'))
@section('content')
<a href="{{ route('admin.courtroom.index') }}">← {{ __('Virtual sud boshqaruvi') }}</a>
<h1 class="edu-page-title mt-3">{{ __('3D modellar va xizmat holati') }}</h1>

<dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="edu-card p-4"><dt>{{ __('Matnli AI sozlamasi') }}</dt><dd>{{ $health['text_configured'] ? __('Kalit sozlangan') : __('Kalit sozlanmagan') }}</dd></div>
    <div class="edu-card p-4"><dt>{{ __('Server ovoz rejimi') }}</dt><dd>{{ $health['server_voice_enabled'] ? ($health['voice_mode'] === 'realtime' ? 'WebRTC' : __('Navbatma-navbat')) : __('O‘chirilgan') }}</dd><dd>{{ $health['stt_model'] }} · {{ $health['tts_model'] }}</dd></div>
    <div class="edu-card p-4"><dt>{{ __('Transcript saqlash muddati') }}</dt><dd>{{ $health['retention_days'] }} {{ __('kun') }}</dd></div>
    <div class="edu-card p-4"><dt>{{ __('AI javoblari · so‘nggi 500 tagacha') }}</dt><dd>{{ $health['sample_count'] }} · {{ $health['average_latency_ms'] === null ? '—' : round($health['average_latency_ms']).' ms' }}</dd><dd>{{ __('Qayd etilgan tokenlar') }} {{ $health['total_tokens'] ?? '—' }} · {{ __(':count ta javobda', ['count' => $health['token_reported_responses']]) }}</dd></div>
    <div class="edu-card p-4"><dt>{{ __('Ochilgan ovoz sessiyalari · 30 kun') }}</dt><dd>{{ __('Brauzer') }} {{ $health['browser_voice_sessions'] }} · {{ __('Server') }} {{ $health['server_voice_sessions'] }}</dd></div>
</dl>

<section class="edu-card mt-6 p-5">
    <h2 class="text-xl font-semibold">{{ __('Avatarlar') }}</h2>
    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        @forelse($assets as $item)
            <article class="rounded-xl border p-4" style="border-color:var(--edu-line)">
                <h3 class="font-semibold">{{ $item->name }}</h3>
                <p>{{ number_format($item->bytes / 1048576, 2) }} MB · {{ number_format($item->triangle_count ?? 0) }} {{ __('uchburchak') }}</p>
                <p class="mt-2 break-all font-mono text-xs">SHA-256 {{ $item->checksum }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ __('Manba') }} ↗</a>
                    <a href="{{ asset($item->license_path) }}">{{ $item->license }}</a>
                    <form method="POST" action="{{ route('admin.courtroom.assets.update', $item) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="enabled" value="{{ $item->enabled ? 0 : 1 }}">
                        <button class="edu-btn edu-btn-secondary">{{ $item->enabled ? __('O‘chirish') : __('Yoqish') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p>{{ __('Ro‘yxatga kiritilgan 3D model yo‘q.') }}</p>
        @endforelse
    </div>
</section>
@endsection
