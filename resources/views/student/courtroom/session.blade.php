@extends('layouts.courtroom')
@section('title', $session->scenario->title)
@section('content')
<div class="courtroom-session-shell" data-courtroom-session
     data-avatar-male="{{ $avatarAssets->get('male') }}"
     data-avatar-female="{{ $avatarAssets->get('female') }}"
     data-avatar-entrance="{{ session('courtroom_entrance') === $session->id ? 'true' : 'false' }}"
     data-session-id="{{ $session->id }}"
     data-action-url="{{ route('student.courtroom.sessions.actions', $session) }}"
     data-voice-mode="{{ $voiceMode }}"
     data-voice-session-id="{{ $activeVoiceSession?->id }}"
     data-realtime-template="{{ route('student.courtroom.voice.realtime', [$session, '__VOICE__']) }}"
     data-transcribe-template="{{ route('student.courtroom.voice.transcribe', [$session, '__VOICE__']) }}"
     data-speech-template="{{ route('student.courtroom.voice.speak', [$session, '__VOICE__', '__TURN__']) }}"
     data-voice-url="{{ route('student.courtroom.voice.store', $session) }}"
     data-voice-segment-template="{{ route('student.courtroom.voice.segments.store', [$session, '__VOICE__']) }}"
     data-voice-end-template="{{ route('student.courtroom.voice.destroy', [$session, '__VOICE__']) }}"
     data-complete-url="{{ route('student.courtroom.sessions.complete', $session) }}"
     data-voice-enabled="{{ $voiceEnabled ? 'true' : 'false' }}"
     data-auto-camera="{{ data_get($session->settings, 'auto_camera', true) ? 'true' : 'false' }}"
     data-student-name="{{ auth()->user()->name }}"
     data-student-role="{{ $session->selected_role_key }}">
    <header class="courtroom-topbar">
        <a href="{{ route('student.courtroom.scenarios.show', $session->scenario) }}" class="courtroom-exit" aria-label="{{ __('Ssenariyga qaytish') }}">←</a>
        <div class="courtroom-topbar-title"><span>{{ __('VIRTUAL SUD') }}</span><strong>{{ $session->scenario->title }}</strong></div>
        <div class="courtroom-stage-chip"><span>{{ __('Bosqich') }}</span><strong data-current-stage>{{ $currentStage?->name ?? __('Briefing') }}</strong></div>
        <div class="courtroom-role-chip"><span>{{ __('Sizning rolingiz') }}</span><strong>{{ $selectedRole?->name }}</strong></div>
        <div class="courtroom-session-status"><i class="{{ $session->status === 'active' ? 'is-online' : '' }}"></i><span data-connection-status>{{ $session->status === 'active' ? __('Session faol') : __(Str::headline($session->status)) }}</span></div>
        <time class="courtroom-timer" data-courtroom-timer data-started-at="{{ $session->started_at?->toIso8601String() }}">00:00</time>
    </header>

    @if($session->status === 'created')
        <section class="courtroom-briefing-screen">
            <div class="courtroom-briefing-card">
                <span class="courtroom-eyebrow">{{ __('MAXFIY ROL BRIFINGI') }}</span>
                <div class="courtroom-briefing-role"><span>{{ mb_strtoupper(mb_substr($selectedRole?->name ?? 'R', 0, 1)) }}</span><div><h1>{{ $selectedRole?->name }}</h1><p>{{ $selectedRole?->description }}</p></div></div>
                <div class="courtroom-briefing-grid"><article><h2>{{ __('Vazifangiz') }}</h2><p>{{ $selectedRole?->instructions }}</p></article><article><h2>{{ __('Faqat siz uchun kontekst') }}</h2><p>{{ $selectedRole?->private_context }}</p></article></div>
                <div class="courtroom-briefing-checks"><p><span>✓</span>{{ __('Dalillarni o‘zgartirib bo‘lmaydi; faqat taqdim etish mumkin.') }}</p><p><span>✓</span>{{ __('AI javobi ishlamasa, harakat saqlanadi va text rejimi ochiq qoladi.') }}</p><p><span>✓</span>{{ __('Mikrofon avtomatik yoqilmaydi va raw audio saqlanmaydi.') }}</p></div>
                <form method="POST" action="{{ route('student.courtroom.sessions.start', $session) }}">@csrf<button class="courtroom-primary-button">{{ __('Sud zaliga kirish') }} <span aria-hidden="true">→</span></button></form>
                <a href="{{ route('student.courtroom.scenarios.show', $session->scenario) }}" class="courtroom-text-link">{{ __('Rolni o‘zgartirish uchun yangi session yarating') }}</a>
            </div>
        </section>
    @elseif($session->status === 'completed')
        <section class="courtroom-briefing-screen"><div class="courtroom-briefing-card courtroom-complete-card"><span aria-hidden="true">✓</span><h1>{{ __('Simulyatsiya yakunlangan') }}</h1><p>{{ __('Harakatlaringiz asosida audit qilinadigan dastlabki hisobot yaratildi.') }}</p><a class="courtroom-primary-button" href="{{ route('student.courtroom.sessions.report', $session) }}">{{ __('Hisobotni ko‘rish') }} →</a></div></section>
    @else
        <div class="courtroom-stage" data-courtroom-stage>
            <canvas id="courtroom-canvas" class="courtroom-canvas" aria-label="{{ __('Interaktiv uch o‘lchamli sud zali') }}" tabindex="0"></canvas>
            <div class="courtroom-scene-fallback" data-scene-fallback hidden role="status"><x-nav-icon name="courtroom" /><strong>{{ __('Yengil sud zali rejimi') }}</strong><p>{{ __('Qurilmada WebGL2 ishlamadi. Barcha dalil, transcript va harakatlar matnli interfeysda ishlaydi.') }}</p></div>
            <div class="courtroom-scene-loading" data-scene-loading><span></span><strong>{{ __('Sud zali yuklanmoqda…') }}</strong><small>{{ __('WebGL capability tekshirilmoqda') }}</small></div>

            <div class="courtroom-camera-controls" aria-label="{{ __('Kamera rejimlari') }}"><button type="button" data-camera="wide" aria-pressed="true">{{ __('Umumiy') }}</button><button type="button" data-camera="judge" aria-pressed="false">{{ __('Sudya') }}</button><button type="button" data-camera="counsel" aria-pressed="false">{{ __('Taraflar') }}</button><button type="button" data-camera="evidence" aria-pressed="false">{{ __('Dalillar doskasi') }}</button><button type="button" data-evidence-step="-1" aria-label="{{ __('Oldingi dalil') }}">←</button><button type="button" data-evidence-step="1" aria-label="{{ __('Keyingi dalil') }}">→</button><button type="button" data-board-open-document @disabled($evidence->isEmpty())>{{ __('Hujjatni ochish') }}</button>@if(isset($sceneParties['defendant']) || $session->version->roles->contains('key', 'defendant'))<button type="button" data-camera="defendant" aria-pressed="false">{{ $sceneParties['defendant'] ?? $session->version->roles->firstWhere('key', 'defendant')?->name ?? __('Javobgar') }}</button>@endif<button type="button" data-camera="first" aria-pressed="false">{{ __('Birinchi shaxs') }}</button><button type="button" data-camera="third" aria-pressed="false">{{ __('Uchinchi shaxs') }}</button><button type="button" data-camera="free" aria-pressed="false">{{ __('Erkin kamera') }}</button><button type="button" data-auto-camera aria-pressed="{{ data_get($session->settings, 'auto_camera', true) ? 'true' : 'false' }}">{{ __('Avto fokus') }}</button></div>
            <div class="courtroom-quality"><label for="scene-quality">{{ __('Grafika') }}</label><select id="scene-quality" data-scene-quality><option value="auto">Auto</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="ultra">Ultra</option></select><span data-scene-status>{{ __('Tekshirilmoqda') }}</span></div>

            <aside class="courtroom-left-panel" aria-label="{{ __('Ishtirokchilar') }}">
                <div class="courtroom-panel-heading"><span>{{ __('ISHTIROKCHILAR') }}</span><button type="button" data-panel-close aria-label="{{ __('Panelni yopish') }}">×</button></div>
                <div class="courtroom-participants">@foreach($session->version->roles as $role)<div class="courtroom-participant {{ $role->key === $session->selected_role_key ? 'is-student' : 'is-ai' }}" data-participant="{{ $role->key }}" data-focus-participant="{{ $role->key }}" role="button" tabindex="0" aria-label="{{ $role->name }} — {{ __('Kamerada ko‘rish') }}"><span>{{ mb_strtoupper(mb_substr($role->name, 0, 1)) }}</span><div><strong>{{ $role->name }}</strong><small>{{ $role->key === $session->selected_role_key ? __('Siz') : __('AI ishtirokchi') }}</small></div><i aria-hidden="true"></i></div>@endforeach
                    @foreach($sceneParties as $key => $name)
                        <div class="courtroom-participant" data-scene-participant="{{ $key }}" data-focus-participant="{{ $key }}" role="button" tabindex="0" aria-label="{{ $name }} — {{ __('Kamerada ko‘rish') }}"><span>{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span><div><strong>{{ $name }}</strong><small>{{ __('Ish tarafi') }}</small></div></div>
                    @endforeach
                </div>
                <p class="sr-only" data-evidence-board-status role="status"></p>
                <nav class="courtroom-side-tabs"><button type="button" class="is-active" data-courtroom-tab="evidence">{{ __('Dalillar') }} <span>{{ $evidence->count() }}</span></button><button type="button" data-courtroom-tab="timeline">{{ __('Jarayon') }} <span>{{ $session->events->count() }}</span></button></nav>
                <div data-courtroom-panel="evidence" class="courtroom-evidence-list">@forelse($evidence as $item)<button type="button" data-evidence-open
                    @if($item->storage_path) data-evidence-url="{{ route('student.courtroom.evidence.show', [$session, $item]) }}" @endif
                    data-evidence-annotations-url="{{ route('student.courtroom.evidence.annotations', [$session, $item]) }}"
                    data-evidence-id="{{ $item->id }}" data-evidence-title="{{ $item->title }}" data-evidence-type="{{ $item->type }}" data-evidence-summary="{{ $item->summary }}" data-evidence-body="{{ $item->annotatableText() }}" data-evidence-source="{{ $item->source_label }}" data-evidence-version="{{ $item->version }}"><span><x-nav-icon name="law" /></span><span><strong>{{ $item->title }}</strong><small>{{ $item->summary }}</small></span><i>↗</i></button>@empty<p class="courtroom-transcript-empty">{{ __('Sizga ochiq dalil mavjud emas.') }}</p>@endforelse</div>
                <ol data-courtroom-panel="timeline" class="courtroom-timeline-list" hidden>@foreach($session->events as $event)<li><span>{{ str_pad((string) $event->sequence, 2, '0', STR_PAD_LEFT) }}</span><div><strong>{{ __(Str::headline($event->type)) }}</strong><small>{{ $event->stage_key }} · {{ $event->occurred_at->format('H:i:s') }}</small></div></li>@endforeach</ol>
            </aside>

            <button type="button" class="courtroom-mobile-panel" data-panel-open><x-nav-icon name="cases" />{{ __('Materiallar') }}</button>

            <section class="courtroom-transcript" aria-label="{{ __('Sud transkripti') }}">
                <div class="courtroom-transcript-head"><span>{{ __('JONLI TRANSKRIPT') }}</span><button type="button" data-captions-toggle aria-pressed="{{ data_get($session->settings, 'captions', true) ? 'true' : 'false' }}">CC</button></div>
                <div class="courtroom-transcript-log" data-transcript-log aria-live="polite">@forelse($session->turns as $turn)<article class="{{ $turn->speaker_type === 'student' ? 'is-student' : 'is-ai' }}" data-speaker="{{ $turn->speaker_role_key }}"><strong>{{ $session->version->roles->firstWhere('key', $turn->speaker_role_key)?->name ?? $turn->speaker_role_key }}</strong><p>{{ $turn->content }}</p></article>@empty<p class="courtroom-transcript-empty" data-transcript-empty>{{ __('Birinchi fikringizni yoki savolingizni kiriting.') }}</p>@endforelse</div>
                @foreach($session->version->verifiedLegalReferences as $reference)
                    <template data-courtroom-reference="REF-{{ $reference->id }}">
                        <div class="courtroom-transcript-reference">
                            @if(Str::startsWith(Str::lower($reference->source_url ?? ''), ['https://', 'http://']))
                                <a href="{{ $reference->source_url }}" target="_blank" rel="noopener noreferrer">{{ $reference->title }} ↗</a>
                            @else
                                <span>{{ $reference->title }}</span>
                            @endif
                            <p>{{ $reference->citation_text }}</p>
                            <time datetime="{{ $reference->verified_at->toDateString() }}">{{ __('Tekshirilgan sana :date', ['date' => $reference->verified_at->format('d.m.Y')]) }}</time>
                        </div>
                    </template>
                @endforeach
            </section>

            <section class="courtroom-composer">
                <div class="courtroom-action-row">
                    <label>
                        <span>{{ __('Harakat') }}</span>
                        <select data-action-type>
                            @foreach($allowedActions as $rule)
                                <option value="{{ $rule->action_type }}"
                                    data-requires-content="{{ $rule->requires_content ? 'true' : 'false' }}"
                                    data-requires-evidence="{{ $rule->requires_evidence ? 'true' : 'false' }}"
                                    data-response-roles="{{ $responseOptions[$rule->id]->implode('key', ',') }}"
                                    @disabled($rule->response_role_keys !== null && $responseOptions[$rule->id]->isEmpty())>{{ $rule->label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label><span>{{ __('Dalil') }}</span><select data-evidence-select><option value="">{{ __('Tanlanmagan') }}</option>@foreach($evidence as $item)<option value="{{ $item->id }}">{{ $item->title }}</option>@endforeach</select></label>
                    <label hidden>
                        <span>{{ __('Kimga') }}</span>
                        <select data-response-role-select disabled>
                            <option value="">{{ __('Avtomatik') }}</option>
                            @foreach($session->version->roles->where('key', '!=', $session->selected_role_key) as $role)
                                <option value="{{ $role->key }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <form data-courtroom-action-form>
                    <label for="courtroom-message" class="sr-only">{{ __('Suddagi fikringiz') }}</label>
                    <textarea id="courtroom-message" data-courtroom-input maxlength="4000" placeholder="{{ __('Fikringiz, savolingiz yoki e’tirozingizni yozing…') }}"></textarea>
                    <div class="courtroom-composer-controls">
                        <button type="button" class="courtroom-mic" data-voice-start @disabled(!$voiceEnabled) aria-label="{{ __('Mikrofon orqali gapirish') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/></svg><span>{{ $voiceEnabled ? __('Mikrofon') : __('Voice o‘chiq') }}</span></button>
                        <button type="button" class="courtroom-mic" data-voice-handsfree aria-pressed="false" @disabled(!$voiceEnabled)>{{ __('Hands-free') }}</button>
                        <label class="courtroom-mic">{{ __('Ovoz') }} <input type="range" min="0" max="1" step="0.1" value="1" data-voice-volume aria-label="{{ __('AI ovoz balandligi') }}"></label>
                        <button type="button" class="courtroom-mic" data-voice-end>{{ __('Ovozni tugatish') }}</button>
                        <span class="courtroom-voice-state" data-voice-state role="status">{{ __('Text rejimi tayyor') }}</span>
                        <button type="submit" class="courtroom-send"><span>{{ __('Yuborish') }}</span> ↑</button>
                    </div>
                </form>
                <div class="courtroom-action-status" data-action-status role="status"></div>
                @if($currentStage?->is_terminal)<form method="POST" action="{{ route('student.courtroom.sessions.complete', $session) }}" class="courtroom-finish">@csrf<button>{{ __('Simulyatsiyani yakunlash va hisobot yaratish') }} →</button></form>@endif
            </section>
        </div>

        <dialog class="courtroom-dialog" data-evidence-dialog aria-labelledby="courtroom-evidence-title">
            <form method="dialog"><button aria-label="{{ __('Yopish') }}">×</button></form>
            <span data-dialog-type></span>
            <h2 id="courtroom-evidence-title" data-dialog-title></h2>
            <p data-dialog-summary></p>
            <div data-dialog-body></div>
            <p><a data-dialog-file hidden target="_blank" rel="noopener noreferrer">{{ __('Faylni ochish') }} ↗</a></p>
            <footer><span data-dialog-source></span><strong data-dialog-version></strong></footer>
            <section class="courtroom-annotation-panel" aria-label="{{ __('Shaxsiy qaydlar') }}">
                <form data-evidence-annotation-form>
                    <p data-annotation-hint>{{ __('Matndan parcha tanlab belgilang yoki izoh yozing.') }}</p>
                    <blockquote data-annotation-selection></blockquote>
                    <button type="button" class="courtroom-secondary-button" data-cite-selection data-length-message="{{ __('Bu parcha javobga sig‘maydi. Qisqaroq parcha tanlang.') }}" hidden>{{ __('Javobimga iqtibos qo‘shish') }}</button>
                    <button type="button" data-clear-annotation-selection hidden>{{ __('Matn tanlovini bekor qilish') }}</button>
                    <label>
                        <span>{{ __('Shaxsiy izoh') }}</span>
                        <textarea rows="2" maxlength="1000" name="note"></textarea>
                    </label>
                    <button type="submit" class="courtroom-secondary-button" disabled>{{ __('Qaydni saqlash') }}</button>
                </form>
                <p data-annotations-status role="status"></p>
                <ul data-evidence-annotations></ul>
            </section>
        </dialog>
        <dialog class="courtroom-dialog courtroom-consent-dialog" data-voice-consent>
            <form method="dialog" data-voice-consent-form>
                <button value="cancel" aria-label="{{ __('Yopish') }}">×</button>
                <span>{{ __('MIKROFON MAXFIYLIGI') }}</span>
                <h2>{{ __('Ovozli rejimni yoqasizmi?') }}</h2>
                <p>{{ __('Server ovoz rejimida mikrofondagi nutq ovoz aniqlash xizmatiga yuboriladi; brauzer rejimida brauzerning nutq xizmati ishlatiladi. HUQUQ AI raw audio fayllarini doimiy saqlamaydi. Suddagi harakat matni o‘quv tarixida saqlanadi; quyidagi rozilik alohida ovoz transcriptiga tegishli. Text rejimi doim mavjud.') }}</p>
                @if(in_array($voiceMode, ['server', 'realtime'], true))
                    @if(config('virtual_courtroom.voice.azure.enabled') && filled(config('virtual_courtroom.voice.azure.key')))
                        <p>{{ __('Mikrofondagi nutq Azure Speech orqali aniqlanadi.') }}</p>
                    @else
                        <p>{{ __('Mikrofondagi nutq OpenAI orqali aniqlanadi.') }}</p>
                    @endif
                    @if(config('virtual_courtroom.voice.tts_provider') === 'azure' && config('virtual_courtroom.voice.azure.enabled') && filled(config('virtual_courtroom.voice.azure.key')))
                        <p>{{ __('AI yaratgan yozma javob Azure Speech ovoziga aylantiriladi.') }}</p>
                    @endif
                @endif
                @if(in_array($voiceMode, ['server', 'realtime'], true) && config('virtual_courtroom.voice.tts_provider') === 'inworld')
                    <p>{{ __('AI ishtirokchisining yozma javobi ovoz va lab harakatlarini yaratish uchun Inworld xizmatiga yuboriladi.') }}</p>
                @endif
                <label><input type="checkbox" data-transcript-consent><span>{{ __('Yakuniy transcriptni :days kun saqlashga roziman', ['days' => config('virtual_courtroom.voice.transcript_retention_days')]) }}</span></label>
                <div>
                    <button value="cancel" class="courtroom-secondary-button">{{ __('Bekor qilish') }}</button>
                    <button value="default" data-voice-consent-confirm class="courtroom-primary-button">{{ __('Roziman va mikrofonni yoqaman') }}</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
@endsection
