@extends(($isPreview ?? false) ? 'layouts.app' : 'layouts.student')
@php
    $isPreview = $isPreview ?? false;
    $selectableRoles = $version->roles->where('is_student_selectable', true);
    $preferredRole = $selectableRoles->firstWhere('key', request()->query('role')) ?? $selectableRoles->first();
@endphp
@section('title', $scenario->title)
@section('content')
<nav class="courtroom-breadcrumb" aria-label="{{ __('Yo‘l') }}"><a href="{{ $isPreview ? route('admin.courtroom.index') : route('student.courtroom.index') }}">{{ __('Virtual sud') }}</a><span>/</span><span aria-current="page">{{ $scenario->title }}</span></nav>

<section class="courtroom-detail-hero">
    <div class="courtroom-detail-copy">
        <span class="courtroom-eyebrow">{{ __(Str::headline($scenario->legal_area)) }} · {{ __(':minutes daqiqa', ['minutes' => $scenario->estimated_duration_minutes]) }}</span>
        <h1>{{ $scenario->title }}</h1>
        <p>{{ $scenario->description }}</p>
        <div class="courtroom-snapshot"><span aria-hidden="true">✓</span><div><strong>{{ $isPreview ? __('O‘qituvchi uchun oldindan ko‘rish') : __('Nashr qilingan o‘quv snapshoti') }}</strong><small>{{ __('Versiya :version · :date', ['version' => $version->version, 'date' => $version->published_at?->format('d.m.Y') ?? __('Qoralama')]) }}</small></div></div>
    </div>
    <div class="courtroom-detail-scene" aria-hidden="true"><div class="courtroom-mini-bench"><i></i><span>§</span></div><div class="courtroom-mini-floor"></div></div>
</section>

<div class="courtroom-detail-grid">
    <div class="courtroom-detail-main">
        <section class="courtroom-info-panel"><span class="courtroom-eyebrow">{{ __('ISH BRIFINGI') }}</span><h2>{{ __('Vaziyat') }}</h2><p>{{ $version->case_brief }}</p><h3>{{ __('Tasdiqlangan ssenariy faktlari') }}</h3><p>{{ $version->case_facts }}</p><div class="courtroom-education-notice"><strong>{{ __('Ta’lim uchun to‘qima ish') }}</strong><p>{{ __('Bu simulyatsiya yuridik maslahat, haqiqiy sud ishi yoki avtomatik huquqiy xulosa emas.') }}</p></div></section>
        <section class="courtroom-info-panel"><h2>{{ __('O‘quv maqsadlari') }}</h2><ul class="courtroom-objectives">@foreach($version->learning_objectives as $objective)<li><span>✓</span>{{ $objective }}</li>@endforeach</ul></section>
        <section class="courtroom-info-panel"><div class="student-section-title"><h2>{{ __('Jarayon bosqichlari') }}</h2><span>{{ $version->stages->count() }} {{ __('bosqich') }}</span></div><ol class="courtroom-stage-preview">@foreach($version->stages as $stage)<li><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><strong>{{ $stage->name }}</strong><small>{{ $stage->description }}</small></div></li>@endforeach</ol></section>
        <section class="courtroom-info-panel">
            <h2>{{ __('Dalillar va huquqiy manbalar') }}</h2>
            @if($canAttachReference ?? false)
                <a class="edu-btn edu-btn-secondary mb-4 inline-flex" href="{{ route('admin.courtroom.references.library', $scenario) }}">{{ __('Kutubxonadan manba biriktirish') }}</a>
            @endif
            <div class="courtroom-resource-summary">
                <div><strong>{{ $version->evidence->where('status', 'active')->filter(fn ($item) => $isPreview || $item->visibility === 'all')->count() }}</strong><span>{{ __('o‘quv dalili') }}</span></div>
                <div><strong>{{ $version->verifiedLegalReferences->count() }}</strong><span>{{ __('tekshirilgan manba') }}</span></div>
            </div>
            <ul class="courtroom-reference-list">
                @foreach(($isPreview ? $version->legalReferences : $version->verifiedLegalReferences) as $reference)
                    @php($hasSourceLink = Str::startsWith(Str::lower($reference->source_url ?? ''), ['https://', 'http://']))
                    @php($isCurrent = !$isPreview || $currentReferenceIds->contains($reference->id))
                    <li>
                        <div>
                            <strong>{{ $reference->title }}</strong>
                            <small>{{ $reference->citation_text }}</small>
                            @if(!$isCurrent)
                                <small>{{ __('Kutubxona manbasini qayta biriktirish kerak') }}</small>
                            @elseif($reference->verified_at)
                                <small>{{ __('Tekshirilgan sana :date', ['date' => $reference->verified_at->format('d.m.Y')]) }}</small>
                            @elseif($isPreview)
                                <small>{{ __('O‘qituvchi tekshiruvi kutilmoqda') }}</small>
                            @endif
                            @if($hasSourceLink)
                                <a href="{{ $reference->source_url }}" target="_blank" rel="noopener noreferrer">{{ __('Rasmiy manba') }} ↗</a>
                            @endif
                            @if($isPreview && $reference->library_excerpt)
                                <details>
                                    <summary>{{ __('Kutubxonadan olingan parcha') }}</summary>
                                    <small>{{ $reference->library_excerpt }}</small>
                                </details>
                            @endif
                        </div>
                        @if(($canVerifyReferences ?? false) && $isCurrent && !$reference->verified_at && $hasSourceLink)
                            <form method="POST" action="{{ route('admin.courtroom.references.verify', [$scenario, $reference]) }}" class="courtroom-reference-review" aria-label="{{ $reference->title }}">
                                @csrf
                                @method('PATCH')
                                <label class="edu-field">
                                    <span>{{ __('Modda va amaldagi tahrir izohi') }}</span>
                                    <textarea class="edu-input" name="citation_text" maxlength="2000" rows="3" required></textarea>
                                </label>
                                <label class="courtroom-setting">
                                    <input type="checkbox" name="confirmed" value="1" required>
                                    <span>{{ __('Amaldagi tahrir va ko‘rsatilgan normani tekshirdim') }}</span>
                                </label>
                                <button type="submit" class="courtroom-secondary-button">{{ __('Manbani tasdiqlash') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
        @if($isPreview)
            <section class="courtroom-info-panel"><h2>{{ __('Dalillarni tekshirish') }}</h2>@foreach($version->evidence as $item)<article><h3>{{ $item->title }}</h3><p>{{ $item->summary }}</p><p>{{ $item->body }}</p><small>{{ $item->source_label }}</small></article>@endforeach</section>
        @endif
    </div>

    <aside id="rol-tanlash" class="courtroom-role-panel">
        <span class="courtroom-eyebrow">{{ __('ROL TANLASH') }}</span>
        <h2>{{ __('Sudda kim bo‘lasiz?') }}</h2>
        <p>{{ __('Boshqa rollar AI tomonidan faqat ssenariy ma’lumotlari doirasida bajariladi.') }}</p>
        @if($isPreview)
            <div class="courtroom-education-notice"><strong>{{ __('Bu oldindan ko‘rish rejimi') }}</strong><p>{{ __('Ssenariy, dalillar va manbalarni tekshirib, tahrirlash sahifasidan nashr qiling.') }}</p><a href="{{ route('admin.courtroom.edit', $scenario) }}">{{ __('Tahrirlashga qaytish') }} →</a></div>
            <a class="edu-btn edu-btn-secondary my-3" href="{{ route('admin.courtroom.rehearsal', [$scenario, $version->version]) }}">{{ __('Ssenariy qoidalarini sinash') }}</a>
            @foreach($version->roles as $role)
                <article class="courtroom-info-panel"><h3>{{ $role->name }}</h3><p>{{ $role->instructions }}</p><h4>{{ __('Maxfiy rol konteksti') }}</h4><p>{{ $role->private_context }}</p></article>
            @endforeach
        @else
        <form method="POST" action="{{ route('student.courtroom.sessions.store') }}" class="courtroom-role-form">@csrf
            <input type="hidden" name="scenario_id" value="{{ $scenario->id }}">
            @foreach($selectableRoles as $role)
                <label class="courtroom-role-option"><input type="radio" name="role_key" value="{{ $role->key }}" @checked(old('role_key', $preferredRole?->key) === $role->key) required><span class="courtroom-role-avatar">{{ mb_strtoupper(mb_substr($role->name, 0, 1)) }}</span><span><strong>{{ $role->name }}</strong><small>{{ $role->description }}</small></span><i aria-hidden="true">✓</i></label>
            @endforeach
            <label class="courtroom-setting"><input type="checkbox" name="captions" value="1" checked><span>{{ __('Subtitrlarni yoqish') }}</span></label>
            <label class="courtroom-setting"><input type="checkbox" name="auto_camera" value="1" checked><span>{{ __('Gapiruvchiga avtomatik kamera') }}</span></label>
            <button class="courtroom-primary-button">{{ __('Rolni tanlash va briefingga o‘tish') }} <span aria-hidden="true">→</span></button>
        </form>
        @endif
        <section class="courtroom-info-panel"><h3>{{ __('Baholash mezonlari') }}</h3><ul>@foreach($version->rubric?->criteria ?? [] as $criterion)<li><strong>{{ $criterion->name }}</strong> · {{ $criterion->weight }}%<p>{{ $criterion->description }}</p></li>@endforeach</ul></section>
        <div class="courtroom-privacy-note"><strong>{{ __('Maxfiylik') }}</strong><p>{{ __('Mikrofon keyingi bosqichda faqat siz bosgandan va rozilik bergandan so‘ng so‘raladi. Raw audio saqlanmaydi.') }}</p></div>
        @if($previousSessions->isNotEmpty())<div class="courtroom-previous"><h3>{{ __('Oldingi urinishlar') }}</h3>@foreach($previousSessions as $attempt)<a href="{{ $attempt->status === 'completed' ? route('student.courtroom.sessions.report', $attempt) : route('student.courtroom.sessions.show', $attempt) }}"><span>#{{ $attempt->id }} · {{ __($attempt->status) }}</span><small>{{ $attempt->created_at->format('d.m.Y H:i') }} ↗</small></a>@endforeach</div>@endif
    </aside>
</div>
@endsection
