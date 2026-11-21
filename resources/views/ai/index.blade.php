@extends('layouts.ai')
@section('title', __('AI Ustoz'))
@section('content')
<div class="ai-hub-container">
    <x-ai-welcome :description="__('AI Ustoz — O‘zbekiston Respublikasi qonunchiligi bo‘yicha interaktiv yuridik o‘qituvchi. Savol bering, test yeching yoki shaxsiy o‘quv rejasini shakllantiring.')" />

    <div class="ai-suggestions mb-6">
        <button type="button" class="ai-suggestion" data-quick-prompt="Jinoyat kodeksi 168-moddasini amaliy misollar bilan tushuntirib bering." data-quick-mode="explain">
            <span class="ai-suggestion-icon"><x-nav-icon name="lesson" /></span>
            <strong>{{ __('JK 168-modda tahlili') }}</strong>
            <span class="ai-suggestion-arrow" aria-hidden="true">↗</span>
        </button>
        <button type="button" class="ai-suggestion" data-quick-prompt="Menga fuqarolik va jinoyat huquqi bo‘yicha 3 ta mini-test tuzib bering." data-quick-mode="quiz_mode">
            <span class="ai-suggestion-icon"><x-nav-icon name="quiz" /></span>
            <strong>{{ __('Mini-test yechish') }}</strong>
            <span class="ai-suggestion-arrow" aria-hidden="true">↗</span>
        </button>
        <button type="button" class="ai-suggestion" data-quick-prompt="Asosiy yuridik tushunchalar bo‘yicha flesh-kartalar tayyorlab bering." data-quick-mode="flashcards">
            <span class="ai-suggestion-icon"><x-nav-icon name="sources" /></span>
            <strong>{{ __('Flesh-kartalar') }}</strong>
            <span class="ai-suggestion-arrow" aria-hidden="true">↗</span>
        </button>
        <button type="button" class="ai-suggestion" data-quick-prompt="Yuridik imtihonga tayyorlanish uchun 7 kunlik shaxsiy o‘quv rejasi tuzing." data-quick-mode="study_plan">
            <span class="ai-suggestion-icon"><x-nav-icon name="assignments" /></span>
            <strong>{{ __('Shaxsiy o‘quv rejasi') }}</strong>
            <span class="ai-suggestion-arrow" aria-hidden="true">↗</span>
        </button>
    </div>

    <!-- Standalone AI Ustoz Chat Workspace -->
    <div class="ai-workspace-layout bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden mb-12" data-standalone-workspace>
        <div class="flex flex-col md:flex-row min-h-[620px]">
            <!-- Sidebar: Sessions History -->
            <aside class="w-full md:w-72 bg-slate-50/70 dark:bg-slate-950/50 border-b md:border-b-0 md:border-r border-slate-200/70 dark:border-slate-800 p-4 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Suhbatlar tarixi') }}</h3>
                        <button type="button" id="btn-new-chat" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-400 dark:hover:bg-blue-900/50 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Yangi') }}
                        </button>
                    </div>

                    <div class="space-y-1.5 max-h-[380px] overflow-y-auto pr-1" id="sessions-list">
                        @forelse ($sessions as $s)
                            <div class="session-item group flex items-center justify-between p-2 rounded-xl text-xs transition-all {{ ($activeSession && $activeSession->id === $s->id) ? 'bg-blue-100/70 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-medium' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60' }}" data-session-id="{{ $s->id }}">
                                <a href="{{ route('ai.index', ['session' => $s->id]) }}" class="flex-1 truncate pr-2 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ ($activeSession && $activeSession->id === $s->id) ? 'bg-blue-500' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                                    <span class="truncate">{{ $s->title ?? ($s->lesson ? $s->lesson->title : 'Suhbat #'.$s->id) }}</span>
                                </a>
                                <button type="button" class="btn-delete-session opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-500 transition-opacity p-1" data-session-delete="{{ $s->id }}" title="{{ __('O‘chirish') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 dark:text-slate-500 p-2 text-center" id="empty-sessions-notice">
                                {{ __('Hozircha saqlangan suhbatlar yo‘q.') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200/60 dark:border-slate-800 text-[11px] text-slate-500 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ __('AI Ustoz Faol') }}
                    </span>
                    <a href="{{ route('ai.health') }}" target="_blank" class="hover:text-blue-500 underline text-[10px]">{{ __('Monitoring') }}</a>
                </div>
            </aside>

            <!-- Main Chat Area -->
            <section class="flex-1 flex flex-col justify-between p-4 md:p-6" data-ai-chat
                     data-endpoint="{{ route('ai.chat.send') }}"
                     data-action-endpoint="{{ route('ai.action.execute') }}"
                     data-pending="{{ __('AI Ustoz tahlil qilmoqda…') }}"
                     data-error="{{ __('Ulanish uzildi. Qayta urinib ko‘ring.') }}"
                     data-confidence="{{ __('ishonch:') }}"
                     data-user-name="{{ auth()->user()->name }}"
                     data-assistant-name="{{ __('AI Ustoz') }}"
                     data-active-session-id="{{ $activeSession?->id }}">

                <!-- Mode Selector Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-3 mb-2 border-b border-slate-100 dark:border-slate-800/80 text-xs">
                    <span class="text-slate-400 font-medium shrink-0">{{ __('Rejim:') }}</span>
                    @foreach ($modes as $key => $label)
                        <button type="button"
                                class="mode-pill shrink-0 px-3 py-1 rounded-full text-xs transition-all {{ ($activeSession?->mode === $key || (! $activeSession && $key === 'explain')) ? 'bg-blue-600 text-white font-medium shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                                data-mode-val="{{ $key }}">
                            {{ __($label) }}
                        </button>
                    @endforeach
                    <input type="hidden" id="mode" name="mode" value="{{ $activeSession?->mode ?? 'explain' }}">
                </div>

                <!-- Messages Feed -->
                <div id="messages" class="ai-messages flex-1 overflow-y-auto space-y-4 my-2 pr-2 min-h-[320px] max-h-[500px]" role="log" aria-label="{{ __('AI suhbat tarixi') }}">
                    @if (! $activeSession || $activeSession->messages->isEmpty())
                        <div class="welcome-box text-center py-10 px-4" data-ai-welcome>
                            <div class="w-14 h-14 mx-auto rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-3">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <h4 class="text-base font-semibold text-slate-800 dark:text-slate-200">{{ __('Yuridik savolingizni bering') }}</h4>
                            <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">{{ __('O‘zbekiston Respublikasi Konstitutsiyasi, Fuqarolik, Jinoyat va boshqa kodekslar bo‘yicha tushuntirish oling yoki yuqoridagi tezkor tugmalardan foydalaning.') }}</p>
                        </div>
                    @else
                        @foreach ($activeSession->messages as $message)
                            <article class="ai-message {{ $message->role === 'user' ? 'ai-message-user' : 'ai-message-assistant' }} p-3.5 rounded-2xl text-sm leading-relaxed {{ $message->role === 'user' ? 'bg-blue-600 text-white ml-auto max-w-[85%]' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 mr-auto max-w-[90%] border border-slate-200/50 dark:border-slate-700/50' }}">
                                <div class="flex items-center justify-between text-xs opacity-75 mb-1.5 font-medium">
                                    <span>{{ $message->role === 'user' ? auth()->user()->name : __('AI Ustoz') }}</span>
                                    <span>{{ $message->created_at->format('H:i') }}</span>
                                </div>
                                <div class="message-body whitespace-pre-wrap">{!! nl2br(e($message->content)) !!}</div>

                                @if (!empty($message->metadata['quiz']['questions']))
                                    <div class="interactive-quiz-container mt-3 pt-3 border-t border-slate-200/60 dark:border-slate-700/60 space-y-3">
                                        @foreach ($message->metadata['quiz']['questions'] as $q)
                                            <div class="quiz-question-card bg-white/70 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200/50 dark:border-slate-700/50 text-xs" data-question-id="{{ $q['id'] }}">
                                                <p class="font-semibold text-slate-800 dark:text-slate-200 mb-2">{{ $q['id'] }}. {{ $q['question'] }}</p>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                                    @foreach ($q['options'] as $optKey => $optVal)
                                                        <button type="button" class="btn-quiz-option text-left px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-slate-700 dark:text-slate-300 transition-colors" data-option="{{ $optKey }}">
                                                            <strong class="text-blue-600 dark:text-blue-400 mr-1">[{{ $optKey }}]</strong> {{ $optVal }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if (!empty($message->metadata['sources']))
                                    <div class="sources-container mt-2 pt-2 border-t border-slate-200/40 dark:border-slate-700/40 flex flex-wrap gap-1.5 text-[11px]">
                                        <span class="text-slate-500 font-medium">⚖️ {{ __('Manbalar:') }}</span>
                                        @foreach ($message->metadata['sources'] as $src)
                                            <a href="{{ $src['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 hover:underline">
                                                <span>{{ $src['title'] }}</span>
                                                <span class="text-[9px] opacity-75">↗</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    @endif
                </div>

                <!-- Input Form & Composer -->
                <form id="tutor-form" class="ai-composer mt-3 relative" data-ai-form>
                    @csrf
                    <label for="message" class="sr-only">{{ __('Savolingiz') }}</label>
                    <textarea id="message" name="message" required maxlength="4000" rows="2"
                              placeholder="{{ __('Huquqiy savolingizni yozing yoki topshiriq bering (masalan: JK 168-moddani tushuntir, 5 ta test tuz)…') }}"
                              class="w-full text-sm"></textarea>

                    <div class="ai-composer-footer flex items-center justify-between mt-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <button type="button" id="btn-voice-input" class="p-2 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="{{ __('Ovozli so‘rov') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                            </button>
                            <span class="text-[11px] text-slate-400 hidden sm:inline-flex items-center gap-1">
                                <span class="ai-online-dot" aria-hidden="true"></span>
                                {{ __('Lex.uz qonunchilik bazasi bilan integratsiya') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="ai-chat-status text-xs text-amber-600 dark:text-amber-400" role="status" data-ai-status></span>
                            <button type="submit" class="ai-send" aria-label="{{ __('Yuborish') }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 19V5m-6 6 6-6 6 6"/></svg>
                            </button>
                        </div>
                    </div>
                </form>
                <p class="ai-disclaimer text-center text-[11px] text-slate-400 mt-2">{{ __('AI xato qilishi mumkin. Muhim huquqiy faktlar uchun Lex.uz rasmiy manbalarini tekshiring.') }}</p>
            </section>
        </div>
    </div>

    <!-- Context selection section required by system -->
    <section id="ai-contexts" class="ai-context-section mt-12">
        <div class="ai-section-heading">
            <h2>{{ $workspaceRole === 'mentor' ? __('Tekshiriladigan ishlar') : ($workspaceRole === 'student' ? __('Darsni tanlang') : __('Kursni tanlang')) }}</h2>
            <span>{{ __('Faqat sizga ruxsat berilgan materiallar') }}</span>
        </div>
        <div class="ai-context-grid">
            @forelse ($entries as $entry)
                @php
                    $target = match ($workspaceRole) {
                        'student' => route('student.tutor', [$entry->module->course, $entry]),
                        'mentor' => route('mentor.reviews.show', $entry).'#ai-review',
                        default => route('admin.teacher-ai.index', $entry),
                    };
                    $title = $workspaceRole === 'mentor' ? $entry->assignment->title : $entry->title;
                    $subtitle = match ($workspaceRole) {'student' => $entry->module->course->title, 'mentor' => $entry->student->name, default => __('O‘qituvchi AI vositalari')};
                @endphp
                <a class="ai-context-card" href="{{ $target }}">
                    <span class="ai-context-icon"><x-nav-icon name="{{ $workspaceRole === 'mentor' ? 'review' : 'lesson' }}" /></span>
                    <span><small>{{ $subtitle }}</small><strong>{{ $title }}</strong></span>
                    <span aria-hidden="true">→</span>
                </a>
            @empty
                <div class="ai-empty">{{ __('Hozircha AI bilan ishlash uchun biriktirilgan material yo‘q.') }} <a href="{{ route('dashboard') }}">{{ __('Boshqaruv') }} →</a></div>
            @endforelse
        </div>
        <div class="mt-6">{{ $entries->links() }}</div>
    </section>
</div>
@endsection
