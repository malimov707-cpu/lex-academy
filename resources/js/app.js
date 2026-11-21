import './teacher-overview.js';
import './courtroom-builder.js';
import './legal-information-check.js';
const globalSearch = document.querySelector('#global-search');
if (globalSearch) {
    document.addEventListener('keydown', event => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k' && globalSearch.getClientRects().length) {
            event.preventDefault();
            globalSearch.focus();
            globalSearch.select();
        }
    });
}
document.querySelectorAll('[data-block-editor]').forEach(editor => {
    const list = editor.querySelector('[data-block-list]');
    const template = editor.parentElement.querySelector('[data-block-template]');
    let dragged = null;
    const reindex = () => {
        list.querySelectorAll('[data-block-row]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/blocks\[[^\]]+\]/, `blocks[${index}]`); });
        });
    };
    editor.querySelector('[data-block-add]').addEventListener('click', () => {
        list.append(template.content.cloneNode(true));
        reindex();
        list.lastElementChild.querySelector('select').focus();
    });
    list.addEventListener('click', event => {
        const row = event.target.closest('[data-block-row]');
        if (!row) return;
        if (event.target.closest('[data-block-remove]')) row.remove();
        if (event.target.closest('[data-block-up]') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
        if (event.target.closest('[data-block-down]') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        reindex();
    });
    list.addEventListener('dragstart', event => {
        if (event.target.matches('input, textarea, select')) { event.preventDefault(); return; }
        dragged = event.target.closest('[data-block-row]');
        if (dragged) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', 'lesson-block'); }
    });
    list.addEventListener('dragover', event => { if (dragged) event.preventDefault(); });
    list.addEventListener('drop', event => {
        if (!dragged) return;
        event.preventDefault();
        const target = event.target.closest('[data-block-row]');
        if (target && target !== dragged) {
            const after = event.clientY > target.getBoundingClientRect().top + target.getBoundingClientRect().height / 2;
            list.insertBefore(dragged, after ? target.nextSibling : target);
        }
        dragged = null;
        reindex();
    });
    list.addEventListener('dragend', () => { dragged = null; });
    editor.addEventListener('submit', () => {
        reindex();

    });
});

document.querySelectorAll('[data-resource-sortable]').forEach(list => {
    let dragged = null;
    let original = [];
    const status = list.closest('[data-sort-container]').querySelector('[data-sort-status]');
    list.addEventListener('dragstart', event => {
        if (event.target.closest('button, input, select, textarea, a')) { event.preventDefault(); return; }
        dragged = event.target.closest('[data-resource-id]');
        original = [...list.children];
        if (dragged) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', dragged.dataset.resourceId); }
    });
    list.addEventListener('dragover', event => { if (dragged) event.preventDefault(); });
    list.addEventListener('drop', async event => {
        if (!dragged) return;
        event.preventDefault();
        const target = event.target.closest('[data-resource-id]');
        if (target && target !== dragged) list.insertBefore(dragged, target);
        dragged = null;
        try {
            const response = await fetch(list.dataset.reorderUrl, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ ids: [...list.querySelectorAll('[data-resource-id]')].map(row => Number(row.dataset.resourceId)) }),
            });
            if (!response.ok) throw new Error('save failed');
            status.textContent = status.dataset.saved;
        } catch {
            original.forEach(row => list.append(row));
            status.textContent = status.dataset.error;
        }
    });
    list.addEventListener('dragend', () => { dragged = null; });
});

// AI Interactive Teaching Agent & Chat Controller
document.querySelectorAll('[data-ai-chat]').forEach(chat => {
    const form = chat.querySelector('[data-ai-form]');
    const input = chat.querySelector('[name="message"]');
    const messages = chat.querySelector('#messages');
    const status = chat.querySelector('[data-ai-status]');
    const button = form?.querySelector('[type="submit"]');
    const mode = document.getElementById('mode');
    const actionEndpoint = chat.dataset.actionEndpoint || '/ai/action';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Mode pills toggle
    document.querySelectorAll('.mode-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            const val = pill.dataset.modeVal;
            if (mode) mode.value = val;
            document.querySelectorAll('.mode-pill').forEach(p => {
                p.classList.remove('bg-blue-600', 'text-white', 'font-medium', 'shadow-sm');
                p.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
            });
            pill.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
            pill.classList.add('bg-blue-600', 'text-white', 'font-medium', 'shadow-sm');
        });
    });

    // Quick prompts
    document.querySelectorAll('[data-quick-prompt]').forEach(suggestion => {
        suggestion.addEventListener('click', () => {
            if (input) input.value = suggestion.dataset.quickPrompt;
            if (mode && suggestion.dataset.quickMode) {
                mode.value = suggestion.dataset.quickMode;
                const matchingPill = document.querySelector(`.mode-pill[data-mode-val="${suggestion.dataset.quickMode}"]`);
                if (matchingPill) matchingPill.click();
            }
            if (input) input.focus();
        });
    });

    chat.querySelectorAll('[data-ai-prompt]').forEach(suggestion => {
        suggestion.addEventListener('click', () => {
            if (input) input.value = suggestion.dataset.aiPrompt;
            if (mode) mode.value = suggestion.dataset.aiMode;
            if (input) input.focus();
        });
    });

    // Quiz options direct click handler
    const attachQuizListeners = (container = chat) => {
        container.querySelectorAll('.btn-quiz-option:not([data-bound])').forEach(btn => {
            btn.setAttribute('data-bound', 'true');
            btn.addEventListener('click', async () => {
                const card = btn.closest('.quiz-question-card');
                if (!card) return;
                const questionId = card.dataset.questionId;
                const selectedOption = btn.dataset.option;

                // Disable sister buttons during evaluation
                const allOpts = card.querySelectorAll('.btn-quiz-option');
                allOpts.forEach(b => b.disabled = true);

                try {
                    const res = await fetch(actionEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            action: 'GRADE_QUIZ',
                            params: { question_id: questionId, selected_option: selectedOption },
                            session_id: chat.dataset.activeSessionId || null,
                        }),
                    });
                    const data = await res.json();
                    if (data.metadata) {
                        const isCorrect = data.metadata.is_correct;
                        if (isCorrect) {
                            btn.classList.add('!bg-emerald-600', '!text-white', '!border-emerald-600');
                        } else {
                            btn.classList.add('!bg-red-600', '!text-white', '!border-red-600');
                            const correctBtn = card.querySelector(`[data-option="${data.metadata.correct_option}"]`);
                            if (correctBtn) correctBtn.classList.add('!bg-emerald-500', '!text-white');
                        }
                        const expl = document.createElement('div');
                        expl.className = `mt-2.5 p-2.5 rounded-lg text-xs leading-relaxed ${isCorrect ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800'}`;
                        const resultLabel = document.createElement('strong');
                        resultLabel.textContent = isCorrect ? '✅ To‘g‘ri!' : '❌ Noto‘g‘ri.';
                        const explanation = document.createElement('span');
                        explanation.textContent = ` ${data.metadata.explanation} `;
                        const basis = document.createElement('span');
                        basis.className = 'block mt-1 font-semibold text-[11px] opacity-90';
                        basis.textContent = `Asos: ${data.metadata.legal_basis}`;
                        expl.append(resultLabel, explanation, basis);
                        card.appendChild(expl);
                    }
                } catch {
                    allOpts.forEach(b => b.disabled = false);
                }
            });
        });
    };

    attachQuizListeners();

    // Voice recognition (Speech to text)
    const voiceBtn = chat.querySelector('#btn-voice-input');
    if (voiceBtn) {
        const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SpeechRec) {
            const recognition = new SpeechRec();
            recognition.lang = 'uz-UZ';
            recognition.continuous = false;
            recognition.interimResults = false;

            let isListening = false;
            voiceBtn.addEventListener('click', () => {
                if (isListening) {
                    recognition.stop();
                    return;
                }
                try {
                    recognition.start();
                    isListening = true;
                    voiceBtn.classList.add('text-red-600', 'animate-pulse');
                    if (status) status.textContent = '🎙️ Eshitilmoqda… Gapiring';
                } catch {
                    isListening = false;
                }
            });

            recognition.onresult = event => {
                const transcript = event.results[0][0].transcript;
                if (input) {
                    input.value = (input.value ? input.value + ' ' : '') + transcript;
                    input.focus();
                }
            };

            recognition.onerror = () => {
                isListening = false;
                voiceBtn.classList.remove('text-red-600', 'animate-pulse');
                if (status) status.textContent = '';
            };

            recognition.onend = () => {
                isListening = false;
                voiceBtn.classList.remove('text-red-600', 'animate-pulse');
                if (status) status.textContent = '';
            };
        } else {
            voiceBtn.setAttribute('title', 'Brauzeringiz ovozli yozishni qo‘llab-quvvatlamaydi');
        }
    }

    // New conversation creation
    const newChatBtn = document.getElementById('btn-new-chat');
    if (newChatBtn) {
        newChatBtn.addEventListener('click', async () => {
            newChatBtn.disabled = true;
            try {
                const res = await fetch('/ai/conversations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ mode: mode ? mode.value : 'explain' }),
                });
                const data = await res.json();
                if (data.session) {
                    chat.dataset.activeSessionId = data.session.id;
                    messages.innerHTML = `<div class="welcome-box text-center py-10 px-4" data-ai-welcome>
                        <h4 class="text-base font-semibold text-slate-800 dark:text-slate-200">Yangi Suhbat Boshlandi</h4>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">Savolingizni yozing yoki rejimni tanlang.</p>
                    </div>`;
                    const list = document.getElementById('sessions-list');
                    if (list) {
                        const notice = document.getElementById('empty-sessions-notice');
                        if (notice) notice.remove();
                        const item = document.createElement('div');
                        item.className = 'session-item group flex items-center justify-between p-2 rounded-xl text-xs bg-blue-100/70 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-medium';
                        item.dataset.sessionId = data.session.id;
                        item.innerHTML = `<a href="/ai?session=${data.session.id}" class="flex-1 truncate pr-2 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span class="truncate">${data.session.title}</span>
                        </a>`;
                        list.prepend(item);
                    }
                }
            } finally {
                newChatBtn.disabled = false;
            }
        });
    }

    // Delete session
    document.querySelectorAll('[data-session-delete]').forEach(delBtn => {
        delBtn.addEventListener('click', async e => {
            e.preventDefault();
            e.stopPropagation();
            const sid = delBtn.dataset.sessionDelete;
            if (!confirm('Ushbu suhbatni o‘chirmoqchimisiz?')) return;
            try {
                const res = await fetch(`/ai/conversations/${sid}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                if (res.ok) {
                    const item = document.querySelector(`.session-item[data-session-id="${sid}"]`);
                    if (item) item.remove();
                    if (chat.dataset.activeSessionId === sid) {
                        window.location.href = '/ai';
                    }
                }
            } catch {}
        });
    });

    const append = (role, content, confidence = null, metadata = null) => {
        const item = document.createElement('article');
        item.className = `ai-message ai-message-${role} p-3.5 rounded-2xl text-sm leading-relaxed ${role === 'user' ? 'bg-blue-600 text-white ml-auto max-w-[85%]' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 mr-auto max-w-[90%] border border-slate-200/50 dark:border-slate-700/50'}`;

        const header = document.createElement('div');
        header.className = 'flex items-center justify-between text-xs opacity-75 mb-1.5 font-medium';
        const author = document.createElement('span');
        author.textContent = role === 'user' ? chat.dataset.userName : chat.dataset.assistantName;
        const time = document.createElement('span');
        const now = new Date();
        time.textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        header.append(author, time);

        const text = document.createElement('div');
        text.className = 'message-body whitespace-pre-wrap';
        text.textContent = content;

        item.append(header, text);

        if (metadata && metadata.quiz && metadata.quiz.questions) {
            const quizContainer = document.createElement('div');
            quizContainer.className = 'interactive-quiz-container mt-3 pt-3 border-t border-slate-200/60 dark:border-slate-700/60 space-y-3';
            metadata.quiz.questions.forEach(q => {
                const qCard = document.createElement('div');
                qCard.className = 'quiz-question-card bg-white/70 dark:bg-slate-900/60 p-3 rounded-xl border border-slate-200/50 dark:border-slate-700/50 text-xs';
                qCard.dataset.questionId = q.id;
                const qTitle = document.createElement('p');
                qTitle.className = 'font-semibold text-slate-800 dark:text-slate-200 mb-2';
                qTitle.textContent = `${q.id}. ${q.question}`;
                qCard.appendChild(qTitle);

                const optsGrid = document.createElement('div');
                optsGrid.className = 'grid grid-cols-1 sm:grid-cols-2 gap-1.5';
                Object.entries(q.options).forEach(([optKey, optVal]) => {
                    const optBtn = document.createElement('button');
                    optBtn.type = 'button';
                    optBtn.className = 'btn-quiz-option text-left px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-slate-700 dark:text-slate-300 transition-colors';
                    optBtn.dataset.option = optKey;
                    const optionLabel = document.createElement('strong');
                    optionLabel.className = 'text-blue-600 dark:text-blue-400 mr-1';
                    optionLabel.textContent = `[${optKey}]`;
                    const optionText = document.createElement('span');
                    optionText.textContent = String(optVal);
                    optBtn.append(optionLabel, optionText);
                    optsGrid.appendChild(optBtn);
                });
                qCard.appendChild(optsGrid);
                quizContainer.appendChild(qCard);
            });
            item.appendChild(quizContainer);
        }

        if (metadata && metadata.sources && Array.isArray(metadata.sources)) {
            const srcDiv = document.createElement('div');
            srcDiv.className = 'sources-container mt-2 pt-2 border-t border-slate-200/40 dark:border-slate-700/40 flex flex-wrap gap-1.5 text-[11px]';
            const sourceHeading = document.createElement('span');
            sourceHeading.className = 'text-slate-500 font-medium';
            sourceHeading.textContent = '⚖️ Manbalar:';
            srcDiv.appendChild(sourceHeading);
            metadata.sources.forEach(src => {
                if (!src || typeof src.url !== 'string' || !src.url.startsWith('https://')) return;
                const link = document.createElement('a');
                link.href = src.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 hover:underline';
                const sourceTitle = document.createElement('span');
                sourceTitle.textContent = String(src.title || src.reference || 'Rasmiy manba');
                const externalLinkIcon = document.createElement('span');
                externalLinkIcon.className = 'text-[9px] opacity-75';
                externalLinkIcon.textContent = '↗';
                link.append(sourceTitle, externalLinkIcon);
                srcDiv.appendChild(link);
            });
            item.appendChild(srcDiv);
        }

        if (Number.isFinite(confidence)) {
            const detail = document.createElement('small');
            detail.className = 'block text-[10px] text-slate-400 mt-1';
            detail.textContent = `${chat.dataset.confidence} ${Math.round(confidence * 100)}%`;
            item.append(detail);
        }

        messages.hidden = false;
        messages.append(item);
        attachQuizListeners(item);
        messages.scrollTop = messages.scrollHeight;
    };

    if (input && form) {
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const message = input.value.trim();
            if (!message || (button && button.disabled)) return;
            if (button) button.disabled = true;
            form.setAttribute('aria-busy', 'true');
            if (status) status.textContent = chat.dataset.pending;
            const welcome = chat.querySelector('[data-ai-welcome]');
            if (welcome) welcome.hidden = true;
            append('user', message);
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 90000);
            try {
                const response = await fetch(chat.dataset.endpoint, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        message,
                        mode: mode ? mode.value : 'explain',
                        session_id: chat.dataset.activeSessionId || null,
                    }),
                });
                const data = await response.json();
                if (!response.ok || typeof data.reply !== 'string') {
                    append('error', typeof data.error === 'string' ? data.error : chat.dataset.error);
                    return;
                }
                append('assistant', data.reply, data.confidence, data.metadata);
                if (input.value.trim() === message) input.value = '';
            } catch {
                append('error', chat.dataset.error);
            } finally {
                window.clearTimeout(timeout);
                if (button) button.disabled = false;
                form.removeAttribute('aria-busy');
                if (status) status.textContent = '';
            }
        });
    }
});

document.querySelectorAll('[data-ai-kind]').forEach(button => {
    button.addEventListener('click', () => {
        const form = document.querySelector('[data-ai-teacher-form]');
        form.querySelector('[name="kind"]').value = button.dataset.aiKind;
        form.querySelector('[name="topic"]').focus();
        form.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
});

const studentNavigation = document.querySelector('[data-student-navigation]');
if (studentNavigation) {
    const opener = document.querySelector('[data-student-menu-open]');
    const closer = document.querySelector('[data-student-menu-close]');
    let previousFocus = null;
    const setOpen = open => {
        studentNavigation.classList.toggle('is-open', open);
        closer.hidden = !open;
        opener.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            previousFocus = document.activeElement;
            studentNavigation.querySelector('a')?.focus();
        } else {
            previousFocus?.focus();
        }
    };
    opener.addEventListener('click', () => setOpen(true));
    closer.addEventListener('click', () => setOpen(false));
    studentNavigation.addEventListener('click', event => {
        if (event.target.closest('a') && window.innerWidth < 1024) setOpen(false);
    });
    document.addEventListener('keydown', event => {
        if (!studentNavigation.classList.contains('is-open')) return;
        if (event.key === 'Escape') setOpen(false);
        if (event.key === 'Tab') {
            const items = [...studentNavigation.querySelectorAll('a[href], button, input, select, summary')].filter(item => item.getClientRects().length && !item.disabled);
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024 && studentNavigation.classList.contains('is-open')) setOpen(false);
    });
}

const studentFilter = document.querySelector('[data-student-filter]');
if (studentFilter) {
    studentFilter.addEventListener('click', () => {
        const enabled = studentFilter.getAttribute('aria-pressed') !== 'true';
        studentFilter.setAttribute('aria-pressed', String(enabled));
        const cards = [...document.querySelectorAll('[data-student-course]')];
        cards.forEach(card => { card.hidden = enabled && card.dataset.completed === 'true'; });
        document.querySelector('[data-student-filter-empty]').hidden = !enabled || cards.some(card => !card.hidden);
    });
}
