@extends('layouts.courtroom')

@section('title', $session->scenario->title . ' · ' . __('Guruhli 3D Sud'))

@section('content')
<div class="courtroom-session-shell"
     data-courtroom-session
     data-session-id="{{ $session->id }}"
     data-student-name="{{ auth()->user()->name }}"
     data-student-role="{{ $member?->role_key ?? 'facilitator' }}"
     data-avatar-male="{{ $avatarAssets->get('male') }}"
     data-avatar-female="{{ $avatarAssets->get('female') }}"
     data-feed-url="{{ route('courtroom.groups.feed', $session) }}"
     data-board-evidence-id="{{ data_get($session->settings, 'board_evidence_id', $evidence->first()?->id) }}"
     data-auto-camera="true"
     data-voice-mode="{{ $voiceMode }}"
     data-voice-enabled="{{ $voiceEnabled ? 'true' : 'false' }}">
    <header class="courtroom-topbar">
        <a href="{{ route('courtroom.groups.index') }}" class="courtroom-exit" aria-label="{{ __('Guruhlar ro‘yxatiga qaytish') }}">←</a>
        <div class="courtroom-topbar-title">
            <span>{{ __('GURUHLI VIRTUAL SUD') }}</span>
            <strong>{{ $session->scenario->title }}</strong>
        </div>
        <div class="courtroom-stage-chip">
            <span>{{ __('Bosqich') }}</span>
            <strong data-current-stage>{{ $stage?->name ?? __('Briefing') }}</strong>
        </div>
        <div class="courtroom-role-chip">
            <span>{{ $member ? __('Sizning rolingiz') : __('Rol') }}</span>
            <strong>{{ $member ? ($roles[$member->role_key] ?? ($member->actor_type === 'observer' ? __('Kuzatuvchi') : $member->role_key)) : __('O‘qituvchi / Moderator') }}</strong>
        </div>
        <div class="courtroom-session-status">
            <i class="{{ $session->status === 'active' ? 'is-online' : '' }}"></i>
            <span data-connection-status>{{ __(Str::headline($session->status)) }}</span>
        </div>
        <time class="courtroom-timer" data-courtroom-timer data-started-at="{{ $session->started_at?->toIso8601String() }}">00:00</time>
    </header>

    @if($session->status === 'created')
        <!-- 3D SUD ZALI KUTISH XONASI VA PRE-FLIGHT (LOBBY) -->
        <section class="courtroom-briefing-screen">
            <div class="courtroom-briefing-card max-w-4xl p-6 sm:p-8 space-y-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                    <div>
                        <span class="courtroom-eyebrow">{{ __('GURUHLI SUD KUTISH XONASI (LOBBY)') }}</span>
                        <h1 class="text-2xl font-bold text-[#0f1e3d] dark:text-white">{{ $session->scenario->title }}</h1>
                        <p class="text-xs text-slate-500">{{ $session->educationGroup->name }} · {{ __('O‘qituvchi:') }} {{ $session->facilitator->name }} · ⏱ 25 {{ __('daqiqa') }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                        ⏳ {{ __('Sud boshlanishi kutilmoqda') }}
                    </span>
                </div>

                @if($member && $scenarioRole)
                    <!-- Ishtirokchining o'z roli va maxfiy yo'riqnomasi -->
                    <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/40 dark:bg-blue-950/20">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 font-bold text-white">
                                {{ mb_strtoupper(mb_substr($scenarioRole->name, 0, 1)) }}
                            </span>
                            <div>
                                <h2 class="text-base font-bold text-blue-950 dark:text-blue-200">{{ $scenarioRole->name }}</h2>
                                <p class="text-xs text-blue-800/80 dark:text-blue-300/80">{{ $scenarioRole->description }}</p>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 text-xs">
                            <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-900/50">
                                <strong class="text-slate-900 dark:text-white">{{ __('Sizning vazifangiz:') }}</strong>
                                <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $scenarioRole->instructions }}</p>
                            </div>
                            <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-900/50">
                                <strong class="text-slate-900 dark:text-white">{{ __('Maxfiy faktlar va kontekst:') }}</strong>
                                <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $scenarioRole->private_context }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 9 ta faol rol va ishtirokchilar jadvali -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                        {{ __('9 ta faol sud roli va talabalar taqsimoti') }}
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                        @php
                            $nineKeys = ['judge', 'prosecutor', 'victim_advocate', 'victim', 'defense_advocate', 'defendant', 'witness_1', 'witness_2', 'witness_3'];
                        @endphp
                        @foreach($nineKeys as $roleKey)
                            @php
                                $p = $session->participants->firstWhere('role_key', $roleKey);
                                $isMe = $p && $p->user_id === auth()->id();
                            @endphp
                            <div class="flex items-center justify-between rounded-lg border p-2.5 {{ $isMe ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-950/30' : 'border-slate-200 dark:border-slate-800' }}">
                                <div>
                                    <span class="block font-semibold text-slate-900 dark:text-white">{{ $roles[$roleKey] ?? Str::headline($roleKey) }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ $p?->display_name ?? __('Tayinlanmagan') }}</span>
                                </div>
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold {{ $p?->status === 'present' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $p?->status === 'present' ? '✓ Zalda' : '⏳ Kutilmoqda' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Kuzatuvchilar soni -->
                <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3 text-xs flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-300">
                        👥 <strong>{{ __('Auditoriya kuzatuvchilari:') }}</strong> {{ $session->participants->where('actor_type', 'observer')->count() }} {{ __('nafar talaba') }}
                    </span>
                    <span class="text-slate-400 font-medium">{{ __('Kuzatuvchilar auditoriya o‘rindiqlarida 3D ko‘rinishda qatnashadi') }}</span>
                </div>

                <!-- Qurilma va Mikrofon tekshiruvi (Pre-flight check) -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-4 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        {{ __('Qurilma va Mikrofon tekshiruvi (Pre-flight)') }}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 dark:bg-slate-800/40 p-3">
                            <div>
                                <span class="font-semibold block">🎙️ {{ __('Mikrofon holati') }}</span>
                                <span class="text-slate-500 text-[11px]" id="lobby-mic-status">{{ __('Sinab ko‘rilmagan') }}</span>
                            </div>
                            <button type="button" class="edu-btn edu-btn-secondary edu-btn-sm" id="lobby-mic-test-btn">
                                {{ __('Sinash') }}
                            </button>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 dark:bg-slate-800/40 p-3">
                            <div>
                                <span class="font-semibold block">🎮 {{ __('3D Dvigatel (WebGL2)') }}</span>
                                <span class="text-emerald-600 dark:text-emerald-400 text-[11px]">✓ {{ __('Babylon.js PBR tayyor') }}</span>
                            </div>
                            <span class="rounded bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">60 FPS</span>
                        </div>
                    </div>
                </div>

                <!-- Amal tugmalari -->
                <div class="pt-2 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 dark:border-slate-800">
                    <a href="{{ route('courtroom.groups.index') }}" class="text-xs text-slate-500 hover:underline">
                        &larr; {{ __('Mashg‘ulotlar ro‘yxatiga qaytish') }}
                    </a>

                    @if($member)
                        @if($member->status !== 'present')
                            <form method="POST" action="{{ route('courtroom.groups.attend', $session) }}">
                                @csrf
                                <button class="courtroom-primary-button">
                                    ✓ {{ __('Mashg‘ulotga hozirman (Tasdiqlash)') }} →
                                </button>
                            </form>
                        @else
                            <div class="flex items-center gap-2 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                {{ __('Siz zaldasiz. O‘qituvchi sudni boshlashi bilan 3D zal avtomatik ochiladi…') }}
                            </div>
                        @endif
                    @endif

                    @if(!$member)
                        <!-- O'qituvchi / Moderator uchun boshlash tugmasi -->
                        <form method="POST" action="{{ route('courtroom.groups.start', $session) }}">
                            @csrf
                            <button class="courtroom-primary-button">
                                🚀 {{ __('Barcha ishtirokchilar bilan 3D Sud Zalini Boshlash') }} →
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    @elseif($session->status === 'completed')
        <!-- Yakunlangan holat -->
        <section class="courtroom-briefing-screen">
            <div class="courtroom-briefing-card courtroom-complete-card p-8 text-center space-y-4">
                <span class="text-5xl" aria-hidden="true">⚖️</span>
                <h1 class="text-2xl font-bold">{{ __('Guruhli sud simulyatsiyasi yakunlangan') }}</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Barcha 9 ta rol va kuzatuvchilarning ishtirok ballari, xulosalari va mentor baholari shakllantirildi.') }}
                </p>
                <div class="pt-2">
                    <a class="courtroom-primary-button" href="{{ route('courtroom.results.show', $session) }}">
                        📊 {{ __('Guruh Natijalari va Baholarini ko‘rish') }} →
                    </a>
                </div>
            </div>
        </section>
    @else
        <!-- FAOL 3D SUD ZALI (ACTIVE & PAUSED) -->
        <div class="courtroom-stage" data-courtroom-stage>
            <canvas id="courtroom-canvas" class="courtroom-canvas" aria-label="{{ __('Interaktiv uch o‘lchamli guruh sud zali') }}" tabindex="0"></canvas>
            
            <div class="courtroom-scene-fallback" data-scene-fallback hidden role="status">
                <x-nav-icon name="courtroom" />
                <strong>{{ __('Yengil sud zali rejimi') }}</strong>
                <p>{{ __('Qurilmada 3D sahna ishlamadi. Guruh harakati va transcript matnli rejimda ishlaydi.') }}</p>
            </div>
            
            <div class="courtroom-scene-loading" data-scene-loading>
                <span></span>
                <strong>{{ __('Sud zali yuklanmoqda…') }}</strong>
                <small>{{ __('Guruh rollari va 3D avatarlar joylashtirilmoqda') }}</small>
            </div>

            <!-- Kamera boshqaruv paneli -->
            <div class="courtroom-camera-controls" aria-label="{{ __('Kamera rejimlari') }}">
                <button type="button" data-camera="wide" aria-pressed="true">{{ __('Umumiy') }}</button>
                <button type="button" data-camera="judge" aria-pressed="false">{{ __('Sudya') }}</button>
                <button type="button" data-camera="counsel" aria-pressed="false">{{ __('Taraflar') }}</button>
                <button type="button" data-camera="evidence" aria-pressed="false">{{ __('Dalillar doskasi') }}</button>
                <button type="button" data-camera="witness" aria-pressed="false">{{ __('Guvohlar') }}</button>
                <button type="button" data-camera="defendant" aria-pressed="false">{{ __('Ayblanuvchi') }}</button>
                <button type="button" data-camera="observer" aria-pressed="false">{{ __('Auditoriya') }}</button>
                <button type="button" data-camera="first" aria-pressed="false">{{ __('1-shaxs') }}</button>
                <button type="button" data-camera="third" aria-pressed="false">{{ __('3-shaxs') }}</button>
                <button type="button" data-camera="free" aria-pressed="false">{{ __('Erkin') }}</button>
                <button type="button" data-auto-camera aria-pressed="true">{{ __('Avto fokus') }}</button>
            </div>

            <!-- Grafika sifati selektori -->
            <div class="courtroom-quality">
                <label for="scene-quality">{{ __('Grafika') }}</label>
                <select id="scene-quality" data-scene-quality>
                    <option value="auto">Auto</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="ultra">Ultra</option>
                </select>
                <span data-scene-status>{{ __('Tekshirilmoqda') }}</span>
            </div>

            <!-- Pauza bildirishnomasi -->
            <div class="courtroom-pause-overlay" data-pause-overlay @if($session->status !== 'paused') hidden @endif role="alert">
                <div class="courtroom-pause-card">
                    <strong>⏸ {{ __('Sud majlisi to‘xtatildi (Pauza)') }}</strong>
                    <p>{{ __('O‘qituvchi mashg‘ulotni vaqtincha to‘xtatdi. Savol yoki ko‘rsatmalarni kuting.') }}</p>
                </div>
            </div>

            <!-- Chap panel: 9 ta faol ishtirokchi va kuzatuvchilar -->
            <aside class="courtroom-left-panel" aria-label="{{ __('Guruh ishtirokchilari') }}">
                <div class="courtroom-panel-heading">
                    <span>{{ __('ISHTIROKCHILAR (3D)') }}</span>
                    <button type="button" data-panel-close aria-label="{{ __('Panelni yopish') }}">×</button>
                </div>
                <div class="courtroom-participants">
                    @foreach($session->participants as $participant)
                        @php($participantRole = $participant->actor_type === 'observer' ? __('Kuzatuvchi') : ($roles[$participant->role_key] ?? Str::headline($participant->role_key)))
                        <div class="courtroom-participant {{ $participant->user_id === auth()->id() ? 'is-student' : 'is-ai' }}"
                             data-participant="{{ $participant->role_key }}"
                             data-focus-participant="{{ $participant->role_key }}"
                             role="button"
                             tabindex="0"
                             title="{{ __('Kamerani yo‘naltirish uchun bosing') }}"
                             aria-label="{{ $participantRole }} — {{ $participant->display_name }}">
                            <span>{{ mb_strtoupper(mb_substr($participant->display_name, 0, 1)) }}</span>
                            <div>
                                <strong>{{ $participant->display_name }}</strong>
                                <small>{{ $participantRole }}</small>
                            </div>
                            <i aria-hidden="true"></i>
                        </div>
                    @endforeach
                </div>

                <nav class="courtroom-side-tabs">
                    <button type="button" class="is-active" data-courtroom-tab="evidence">{{ __('Dalillar') }} <span>{{ $evidence->count() }}</span></button>
                    <button type="button" data-courtroom-tab="timeline">{{ __('Jarayon') }} <span>{{ $session->events->count() }}</span></button>
                </nav>
                <div data-courtroom-panel="evidence" class="courtroom-evidence-list">
                    @forelse($evidence as $item)
                        <article>
                            <span><x-nav-icon name="law" /></span>
                            <span><strong>{{ $item->title }}</strong><small>{{ $item->summary }}</small></span>
                        </article>
                    @empty
                        <p class="courtroom-transcript-empty">{{ __('Ushbu bosqichda ochiq dalil mavjud emas.') }}</p>
                    @endforelse
                </div>
                <ol data-courtroom-panel="timeline" class="courtroom-timeline-list" hidden>
                    @foreach($session->events as $event)
                        <li>
                            <span>{{ str_pad((string) $event->sequence, 2, '0', STR_PAD_LEFT) }}</span>
                            <div><strong>{{ __(Str::headline($event->type)) }}</strong><small>{{ $event->stage_key }} · {{ $event->occurred_at->format('H:i:s') }}</small></div>
                        </li>
                    @endforeach
                </ol>
            </aside>

            <button type="button" class="courtroom-mobile-panel" data-panel-open><x-nav-icon name="cases" />{{ __('Materiallar') }}</button>

            <!-- Jonli Sud Transkripti -->
            <section class="courtroom-transcript" aria-label="{{ __('Guruh sud transkripti') }}">
                <div class="courtroom-transcript-head">
                    <span>{{ __('JONLI TRANSKRIPT (MULTIPLAYER)') }}</span>
                </div>
                <div class="courtroom-transcript-log" data-transcript-log aria-live="polite">
                    @forelse($turns as $turn)
                        <article class="{{ $turn->speaker_type === 'student' ? 'is-student' : 'is-ai' }}" data-speaker="{{ $turn->speaker_role_key }}" data-sequence="{{ $turn->sequence }}">
                            <strong>{{ $roles[$turn->speaker_role_key] ?? Str::headline($turn->speaker_role_key) }}</strong>
                            <p>{{ $turn->content }}</p>
                        </article>
                    @empty
                        <p class="courtroom-transcript-empty">{{ __('Guruhdagi birinchi fikr hali yozilmadi.') }}</p>
                    @endforelse
                </div>
            </section>

            <!-- Pastki Boshqaruv / Yozish paneli -->
            @if(in_array($session->status, ['active', 'paused'], true) && $member)
                <section class="courtroom-composer">
                    @if($member->actor_type === 'observer')
                        <div class="courtroom-pause-notice">
                            <span>👀</span>
                            <p>{{ __('Siz auditoriya kuzatuvchisi maqomidasiz. 3D sud zalini kuzating, dalillar doskasi va taraflarning bahslarini tahlil qiling.') }}</p>
                        </div>
                    @elseif($session->status === 'paused')
                        <div class="courtroom-pause-notice">
                            <span>⏸</span>
                            <p>{{ __('Mashg‘ulot o‘qituvchi tomonidan to‘xtatib turilibdi (pauza). Davom ettirilgach fikringizni yuborishingiz mumkin.') }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('courtroom.groups.act', $session) }}" data-group-courtroom-form>
                            @csrf
                            <input type="hidden" name="stage_key" value="{{ $session->current_stage_key }}">
                            <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
                            <div class="courtroom-action-row">
                                <label>
                                    <span>{{ __('Harakat') }}</span>
                                    <select name="action_type" required>
                                        @foreach($actions as $actionType => $label)
                                            <option value="{{ $actionType }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                @if($evidence->isNotEmpty())
                                    <label>
                                        <span>{{ __('Dalil') }}</span>
                                        <select name="evidence_id">
                                            <option value="">{{ __('Tanlanmagan') }}</option>
                                            @foreach($evidence as $item)
                                                <option value="{{ $item->id }}">{{ $item->title }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            </div>
                            <label for="courtroom-group-message" class="sr-only">{{ __('Suddagi fikr yoki javob') }}</label>
                            <textarea id="courtroom-group-message" name="content" maxlength="8000" placeholder="{{ __('Fikringiz, savolingiz yoki dalil tahlilingizni yozing…') }}" required></textarea>
                            <div class="courtroom-composer-controls">
                                <button type="button" class="courtroom-mic-btn" data-group-mic-btn aria-label="{{ __('Mikrofon orqali gapirish') }}" title="{{ __('Mikrofon orqali gapirish') }}">
                                    <span>🎤</span>
                                    <small data-group-mic-label>{{ __('Gapirish') }}</small>
                                </button>
                                <button type="submit" class="courtroom-send"><span>{{ __('Yuborish') }}</span> ↑</button>
                            </div>
                        </form>
                    @endif
                </section>
            @endif

            <!-- O'qituvchi / Moderator Boshqaruv paneli -->
            @if(in_array($session->status, ['active', 'paused'], true) && !$member)
                <section class="courtroom-composer">
                    <div class="courtroom-moderator-panel">
                        <div class="courtroom-moderator-actions">
                            @if($session->status === 'active')
                                <form method="POST" action="{{ route('courtroom.groups.pause', $session) }}" data-group-teacher-action>
                                    @csrf
                                    <button type="submit" class="courtroom-btn-action">⏸ {{ __('Pauza') }}</button>
                                </form>
                            @elseif($session->status === 'paused')
                                <form method="POST" action="{{ route('courtroom.groups.resume', $session) }}" data-group-teacher-action>
                                    @csrf
                                    <button type="submit" class="courtroom-btn-action">▶ {{ __('Davom ettirish') }}</button>
                                </form>
                            @endif

                            @if($evidence->isNotEmpty())
                                <form method="POST" action="{{ route('courtroom.groups.evidence', $session) }}" class="courtroom-inline-form" data-group-teacher-action>
                                    @csrf
                                    <select name="evidence_id" required>
                                        @foreach($evidence as $item)
                                            <option value="{{ $item->id }}" @selected(data_get($session->settings, 'board_evidence_id') == $item->id)>{{ $item->title }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="courtroom-btn-action">📺 {{ __('3D Doskaga chiqarish') }}</button>
                                </form>
                            @endif
                        </div>

                        @php($currentIndex = $session->version->stages->search(fn ($item) => $item->key === $session->current_stage_key))
                        @php($nextStage = $session->version->stages->get($currentIndex + 1))
                        @if($nextStage)
                            <form method="POST" action="{{ route('courtroom.groups.stage', $session) }}" class="courtroom-finish" data-group-teacher-action>
                                @csrf
                                <input type="hidden" name="from" value="{{ $session->current_stage_key }}">
                                <input type="hidden" name="to" value="{{ $nextStage->key }}">
                                <button class="courtroom-primary-button">{{ __('Keyingi bosqich: :stage', ['stage' => $nextStage->name]) }} →</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('courtroom.groups.complete', $session) }}" class="courtroom-finish">
                                @csrf
                                <button class="courtroom-primary-button">🏁 {{ __('Mashg‘ulotni yakunlash & Baholash') }} →</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    @endif
</div>
@endsection
