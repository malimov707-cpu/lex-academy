import { CourtroomMicrophone } from './courtroom-microphone.js';
import { CourtroomRealtime } from './courtroom-realtime.js';
import { initialiseEvidenceDialog } from './courtroom-evidence.js';
import { initialiseEvidenceBoard } from './courtroom-evidence-board.js';
import { timedVisemeAt, visemeAt, visemeTimeline } from './courtroom-visemes.js';

const root = document.querySelector('[data-courtroom-session]');

if (root) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const scene = document.querySelector('[data-courtroom-stage]');
    const form = document.querySelector('[data-courtroom-action-form]');
    const input = document.querySelector('[data-courtroom-input]');
    const actionType = document.querySelector('[data-action-type]');
    const evidenceSelect = document.querySelector('[data-evidence-select]');
    const responseRoleSelect = document.querySelector('[data-response-role-select]');
    const actionStatus = document.querySelector('[data-action-status]');
    const transcriptLog = document.querySelector('[data-transcript-log]');
    const micButton = document.querySelector('[data-voice-start]');
    const voiceState = document.querySelector('[data-voice-state]');
    const consentDialog = document.querySelector('[data-voice-consent]');
    const transcriptConsent = document.querySelector('[data-transcript-consent]');
    let voiceSessionId = root.dataset.voiceSessionId ? Number(root.dataset.voiceSessionId) : null;
    let pendingMicrophoneStream = null;
    let recognition = null;
    let isListening = false;
    let transcriptStorageEnabled = true;
    let handsFree = false;
    let processing = false;
    let voiceConnecting = false;
    let speaking = false;
    let voiceGeneration = 0;
    let pendingAction = null;
    const serverVoice = ['server', 'realtime'].includes(root.dataset.voiceMode);
    const realtimeVoice = root.dataset.voiceMode === 'realtime' && 'RTCPeerConnection' in window;
    let realtimeFailed = false;
    let audioPlayer = null;
    let audioUrl = null;
    let audioContext = null;
    let amplitudeFrame = null;
    let speechRequest = null;
    const stopAudio = () => {
        speechRequest?.abort();
        speechRequest = null;
        cancelAnimationFrame(amplitudeFrame);
        amplitudeFrame = null;
        audioContext?.close().catch(() => {});
        audioContext = null;
        window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role: null, level: 0 } }));
        if (audioPlayer) {
            audioPlayer.onplaying = audioPlayer.onended = audioPlayer.onerror = null;
            audioPlayer.pause();
        }
        audioPlayer = null;
        if (audioUrl) URL.revokeObjectURL(audioUrl);
        audioUrl = null;
    };
    const indicateListening = listening => {
        isListening = listening;
        micButton?.classList.toggle('is-listening', listening);
        if (micButton) micButton.querySelector('span').textContent = listening ? 'Tugatish' : 'Mikrofon';
        if (listening) setVoiceState('LISTENING', 'Gapiring; tugatish uchun mikrofonni bosing. Bir nutq 30 soniyagacha.');
    };
    const submitTranscript = content => {
        if (!content || typeof content !== 'string' || !content.trim()) return;
        input.value = content.trim();
        form.dataset.inputType = 'voice';
        saveVoiceSegment(content.trim());
        setVoiceState('PROCESSING', 'Nutq qabul qilindi. AI javobi kutilmoqda…');
        form.requestSubmit();
    };
    const stopHandsFree = () => {
        handsFree = false;
        document.querySelector('[data-voice-handsfree]')?.setAttribute('aria-pressed', 'false');
    };
    const microphone = new CourtroomMicrophone({
        onListening: indicateListening,
        onProcessing: () => setVoiceState('PROCESSING', 'Nutq aniqlanmoqda…'),
        onTranscript: submitTranscript,
        onError: message => {
            stopHandsFree();
            setVoiceState('ERROR', message);
        },
    });
    let partialTranscript = '';
    const realtime = new CourtroomRealtime({
        onListening: () => indicateListening(true),
        onProcessing: () => {
            partialTranscript = '';
            indicateListening(false);
            setVoiceState('PROCESSING', 'Jonli transcript tayyorlanmoqda…');
        },
        onDelta: delta => {
            partialTranscript = (partialTranscript + delta).slice(0, 4000);
            input.value = partialTranscript;
        },
        onTranscript: submitTranscript,
        onError: (message, { fallback = true } = {}) => {
            indicateListening(false);
            stopHandsFree();
            realtimeFailed = fallback && Boolean(window.MediaRecorder);
            setVoiceState('ERROR', realtimeFailed ? message + ' Qayta bosilganda oddiy ovoz rejimi ishlaydi.' : message);
        },
    });
    const volume = document.querySelector('[data-voice-volume]');
    volume?.addEventListener('input', () => {
        if (audioPlayer) audioPlayer.volume = Number(volume.value);
    });
    const setVoiceState = (state, message) => {
        if (!voiceState) return;
        voiceState.dataset.state = state;
        voiceState.textContent = message;
    };

    const jsonRequest = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers ?? {}),
            },
        });
        let data = {};
        try { data = await response.json(); } catch { data = {}; }
        if (!response.ok && response.status !== 202) {
            const error = new Error(typeof data.error === 'string' ? data.error : 'So‘rov bajarilmadi.');
            error.status = response.status;
            error.validation = data.errors;
            throw error;
        }
        return { response, data };
    };

    const uuid = () => crypto.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
        const random = Math.random() * 16 | 0;
        return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
    });

    const setStatus = (message, isError = false) => {
        if (!actionStatus) return;
        actionStatus.textContent = message;
        actionStatus.classList.toggle('is-error', isError);
    };

    const setSpeaker = (role, duration = 2600) => {
        document.querySelectorAll('[data-participant]').forEach(item => item.classList.toggle('is-speaking', item.dataset.participant === role));
        window.clearTimeout(setSpeaker.timeout);
        if (role) setSpeaker.timeout = window.setTimeout(() => setSpeaker(null), duration);
        window.dispatchEvent(new CustomEvent('courtroom-speaker', { detail: { role } }));
    };

    const references = new Map([...root.querySelectorAll('template[data-courtroom-reference]')]
        .map(template => [template.dataset.courtroomReference, template.content]));
    const appendReferences = (article, content) => {
        for (const id of new Set(content.match(/\bREF-\d+\b/g))) {
            const reference = references.get(id);
            if (reference) article.append(reference.cloneNode(true));
        }
    };
    transcriptLog?.querySelectorAll('article.is-ai').forEach(article => appendReferences(article, article.querySelector('p').textContent));

    const appendTranscript = (role, roleName, content, speakerType) => {
        if (!transcriptLog || typeof content !== 'string' || content.trim() === '') return;
        transcriptLog.querySelector('[data-transcript-empty]')?.remove();
        const article = document.createElement('article');
        article.className = speakerType === 'student' ? 'is-student' : 'is-ai';
        article.dataset.speaker = role;
        const strong = document.createElement('strong');
        strong.textContent = roleName;
        const paragraph = document.createElement('p');
        paragraph.textContent = content;
        article.append(strong, paragraph);
        if (speakerType === 'ai') appendReferences(article, content);
        transcriptLog.append(article);
        transcriptLog.scrollTop = transcriptLog.scrollHeight;
        setSpeaker(role, Math.min(9000, Math.max(2200, content.length * 45)));
    };

    const speak = async (content, role, turnId) => {
        if (serverVoice && voiceSessionId && turnId) {
            stopAudio();
            const generation = ++voiceGeneration;
            speaking = true;
            setVoiceState('PROCESSING', 'AI ovozi tayyorlanmoqda…');
            try {
                const url = root.dataset.speechTemplate.replace('__VOICE__', String(voiceSessionId)).replace('__TURN__', String(turnId));
                speechRequest = new AbortController();
                const response = await fetch(url, { method: 'POST', signal: speechRequest.signal, headers: { 'Accept': 'application/json, audio/mpeg, audio/wav', 'X-CSRF-TOKEN': csrf } });
                if (!response.ok) throw new Error('Ovoz olinmadi. AI javobini matnda o‘qing.');
                const timed = response.headers.get('Content-Type')?.includes('application/json') ? await response.json() : null;
                const audio = timed
                    ? new Blob([Uint8Array.from(atob(timed.audio_base64), value => value.charCodeAt(0))], { type: timed.mime })
                    : await response.blob();
                if (generation !== voiceGeneration || !voiceSessionId || document.hidden) return;
                audioUrl = URL.createObjectURL(audio);
                const player = audioPlayer = new Audio(audioUrl);
                player.volume = Number(volume?.value ?? 1);
                player.onplaying = () => {
                    setVoiceState('SPEAKING', 'AI gapirmoqda');
                    setSpeaker(role, 60000);
                    if (amplitudeFrame !== null) return;
                    if (timed?.visemes?.length) {
                        const measureTimed = () => {
                            const shape = player.paused || player.seeking || player.readyState < 3 ? 'sil' : timedVisemeAt(timed.visemes, player.currentTime);
                            window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role, shape, level: shape === 'sil' ? 0 : .16, alignment: 'timed' } }));
                            amplitudeFrame = requestAnimationFrame(measureTimed);
                        };
                        measureTimed();
                        return;
                    }

                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext || audioContext) return;
                    audioContext = new AudioContext();
                    if (audioContext.state !== 'running') {
                        audioContext.close().catch(() => {});
                        audioContext = null;
                        return;
                    }
                    const analyser = audioContext.createAnalyser();
                    analyser.fftSize = 256;
                    audioContext.createMediaElementSource(player).connect(analyser);
                    analyser.connect(audioContext.destination);
                    const samples = new Uint8Array(analyser.fftSize);
                    const mouthTimeline = visemeTimeline(content);
                    const measure = () => {
                        analyser.getByteTimeDomainData(samples);
                        const level = Math.sqrt(samples.reduce((sum, sample) => sum + ((sample - 128) / 128) ** 2, 0) / samples.length);
                        const shape = visemeAt(mouthTimeline, player.currentTime, player.duration);
                        window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role, level, shape } }));
                        amplitudeFrame = requestAnimationFrame(measure);
                    };
                    measure();
                };
                player.onended = () => {
                    speaking = false;
                    stopAudio();
                    setSpeaker(null);
                    setVoiceState('IDLE', 'Voice tayyor');
                    if (handsFree && !document.hidden) beginRecognition();
                };
                player.onerror = () => { speaking = false; stopAudio(); setSpeaker(null); setVoiceState('ERROR', 'Ovoz ijro etilmadi. Matnli javob mavjud.'); };
                await player.play();
            } catch (error) {
                if (generation !== voiceGeneration) return;
                stopAudio();
                if (!serverVoice && 'speechSynthesis' in window && typeof content === 'string') {
                    playBrowserSpeech(content, role);
                    return;
                }
                speaking = false;
                setVoiceState('ERROR', error.message || 'Azure Speech ovozini olib bo‘lmadi. Javob matnda mavjud.');
            }
            return;
        }
        playBrowserSpeech(content, role);
    };

    const playBrowserSpeech = (content, role) => {
        if (!('speechSynthesis' in window) || typeof content !== 'string') return;
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(content);
        const lang = document.documentElement.lang.startsWith('ru') ? 'ru-RU' : 'uz-UZ';
        utterance.lang = lang;
        utterance.rate = 0.98;
        utterance.volume = Number(volume?.value ?? 1);
        try {
            const voices = window.speechSynthesis.getVoices() || [];
            if (voices.length) {
                const match = voices.find(v => v.lang.startsWith('uz'))
                    || (lang === 'uz-UZ' ? voices.find(v => v.lang.startsWith('tr')) : null)
                    || voices.find(v => v.lang.startsWith('ru'));
                if (match) utterance.voice = match;
            }
        } catch {}
        const generation = voiceGeneration;
        recognition?.abort();
        microphone.stop(true);
        speaking = true;
        utterance.onstart = () => {
            setSpeaker(role, 60000);
            window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role, level: 0 } }));
            setVoiceState('SPEAKING', 'AI gapirmoqda. Mikrofon tugmasi orqali to‘xtatishingiz mumkin.');
        };
        const finishSpeech = () => {
            if (generation !== voiceGeneration) return;
            speaking = false;
            window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role: null, level: 0 } }));
            setSpeaker(null);
            setVoiceState('IDLE', 'Voice tayyor');
            if (handsFree && voiceSessionId && !document.hidden) beginRecognition();
        };
        utterance.onend = finishSpeech;
        utterance.onerror = finishSpeech;
        window.speechSynthesis.speak(utterance);
    };

    const currentRule = () => actionType?.selectedOptions[0];

    if (form) {
        actionType?.addEventListener('change', () => {
            const rule = currentRule();
            const requiresEvidence = rule?.dataset.requiresEvidence === 'true';
            evidenceSelect?.closest('label')?.classList.toggle('is-required', requiresEvidence);
            input?.toggleAttribute('required', rule?.dataset.requiresContent === 'true');
            const allowedRoles = rule?.dataset.responseRoles?.split(',').filter(Boolean) ?? [];
            if (responseRoleSelect) {
                for (const option of responseRoleSelect.options) {
                    option.hidden = option.disabled = option.value !== '' && !allowedRoles.includes(option.value);
                }
                if (!allowedRoles.includes(responseRoleSelect.value)) responseRoleSelect.value = '';
                if (responseRoleSelect.closest('label')) {
                    responseRoleSelect.disabled = responseRoleSelect.closest('label').hidden = allowedRoles.length === 0;
                }
            }
        });
        actionType?.dispatchEvent(new Event('change'));
        input?.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submit = form.querySelector('[type="submit"]');
            const rule = currentRule();
            const content = input.value.trim();
            if (rule?.dataset.requiresContent === 'true' && content === '') {
                setStatus('Bu harakat uchun matn yoki ovozli transcript kerak.', true);
                input.focus();
                return;
            }
            if (rule?.dataset.requiresEvidence === 'true' && !evidenceSelect.value) {
                setStatus('Avval taqdim qilinadigan dalilni tanlang.', true);
                evidenceSelect.focus();
                return;
            }
            if (processing) return;
            processing = true;
            if (realtime.status !== 'idle') realtime.close();
            recognition?.abort();
            microphone.stop(true);
            setVoiceState('PROCESSING', 'Javob kutilmoqda…');
            submit.disabled = true;
            form.setAttribute('aria-busy', 'true');
            setStatus('Harakat serverda tekshirilmoqda…');
            const payload = {
                action_type: actionType.value,
                target_role_key: responseRoleSelect.disabled ? null : responseRoleSelect.value || null,
                content: content || null,
                evidence_id: evidenceSelect.value ? Number(evidenceSelect.value) : null,
                input_type: form.dataset.inputType === 'voice' ? 'voice' : 'text',
            };
            const signature = JSON.stringify(payload);
            if (pendingAction?.signature !== signature) pendingAction = { signature, key: uuid() };
            try {
                const { data } = await jsonRequest(root.dataset.actionUrl, {
                    method: 'POST',
                    body: JSON.stringify({ ...payload, idempotency_key: pendingAction.key }),
                });
                pendingAction = null;
                if (content) appendTranscript(root.dataset.studentRole, root.dataset.studentName, content, 'student');
                if (data.ai?.content) {
                    appendTranscript(data.ai.role_key, data.ai.role_name, data.ai.content, 'ai');
                    speak(data.ai.content, data.ai.role_key, data.ai.turn_id);
                }
                if (input.value.trim() === content) input.value = '';
                form.dataset.inputType = 'text';
                if (data.ai_error) setStatus(`Harakat saqlandi. AI javobi olinmadi: ${data.ai_error}`, true);
                else setStatus('Harakat qabul qilindi.');
                if (data.action?.status === 'accepted') {
                    window.dispatchEvent(new CustomEvent('courtroom-gesture', { detail: { role: root.dataset.studentRole, type: data.action.type } }));
                    if (payload.action_type === 'present_evidence' && payload.evidence_id) {
                        window.dispatchEvent(new CustomEvent('courtroom-select-evidence', { detail: { id: payload.evidence_id } }));
                    }
                }
                if (data.session?.current_stage && data.session.current_stage !== data.action?.stage) {
                    window.setTimeout(() => window.location.reload(), 350);
                }
            } catch (error) {
                if (error.status >= 400 && error.status < 500) pendingAction = null;
                setStatus(error.message || 'Harakat yuborilmadi. Aloqani tekshirib, qayta yuboring.', true);
            } finally {
                processing = false;
                if (!speaking && voiceSessionId) {
                    setVoiceState('IDLE', 'Voice tayyor');
                    if (handsFree && !document.hidden) beginRecognition();
                }
                submit.disabled = false;
                form.removeAttribute('aria-busy');
            }
        });
    }

    document.querySelectorAll('[data-courtroom-tab]').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-courtroom-tab]').forEach(item => item.classList.toggle('is-active', item === button));
            document.querySelectorAll('[data-courtroom-panel]').forEach(panel => { panel.hidden = panel.dataset.courtroomPanel !== button.dataset.courtroomTab; });
        });
    });
    const sidePanel = document.querySelector('.courtroom-left-panel');
    document.querySelector('[data-panel-open]')?.addEventListener('click', () => sidePanel?.classList.add('is-open'));
    document.querySelector('[data-panel-close]')?.addEventListener('click', () => sidePanel?.classList.remove('is-open'));

    initialiseEvidenceBoard(root);
    initialiseEvidenceDialog(root, jsonRequest, uuid);
    document.querySelector('[data-captions-toggle]')?.addEventListener('click', event => {
        const button = event.currentTarget;
        const enabled = button.getAttribute('aria-pressed') !== 'true';
        button.setAttribute('aria-pressed', String(enabled));
        transcriptLog.hidden = !enabled;
    });

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const voiceSupported = root.dataset.voiceEnabled === 'true'
        && (serverVoice ? realtimeVoice || Boolean(window.MediaRecorder) : (Boolean(SpeechRecognition) || Boolean(window.MediaRecorder)))
        && Boolean(navigator.mediaDevices?.getUserMedia);
    if (micButton && !voiceSupported) {
        micButton.disabled = true;
        micButton.querySelector('span').textContent = 'Voice qo‘llanmaydi';
        if (voiceState) voiceState.textContent = 'Bu brauzerda microphone STT mavjud emas — text rejimi ishlaydi.';
    }

    const saveVoiceSegment = async content => {
        if (!voiceSessionId || !transcriptStorageEnabled) return;
        const url = root.dataset.voiceSegmentTemplate.replace('__VOICE__', String(voiceSessionId));
        try {
            await jsonRequest(url, { method: 'POST', body: JSON.stringify({ content, is_final: true }) });
        } catch {
            if (voiceState) voiceState.textContent = 'Transcript saqlanmadi; text harakatini yuborishingiz mumkin.';
        }
    };

    const startBrowserRecognition = () => {
        if (!SpeechRecognition) return false;
        recognition = new SpeechRecognition();
        recognition.lang = document.documentElement.lang.startsWith('ru') ? 'ru-RU' : 'uz-UZ';
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;
        recognition.onstart = () => {
            isListening = true;
            micButton?.classList.add('is-listening');
            if (micButton) micButton.querySelector('span').textContent = 'Tinglanmoqda';
            setVoiceState('LISTENING', 'Tinglanmoqda. Fikringizni bildiring…');
        };
        recognition.onresult = event => {
            if (!voiceSessionId || document.hidden) return;
            let finalTranscript = '';
            let interimTranscript = '';
            for (let index = event.resultIndex; index < event.results.length; index++) {
                const text = event.results[index][0]?.transcript ?? '';
                if (event.results[index].isFinal) finalTranscript += text;
                else interimTranscript += text;
            }
            input.value = (finalTranscript || interimTranscript).trim();
            if (finalTranscript.trim()) {
                form.dataset.inputType = 'voice';
                saveVoiceSegment(finalTranscript.trim());
                const generation = voiceGeneration;
                window.setTimeout(() => {
                    if (generation === voiceGeneration && voiceSessionId && !document.hidden) form.requestSubmit();
                }, 120);
            }
        };
        recognition.onerror = event => {
            if (event.error === 'aborted') return;
            handsFree = false;
            document.querySelector('[data-voice-handsfree]')?.setAttribute('aria-pressed', 'false');

            const messages = {
                'not-allowed': 'Mikrofon ruxsati rad etildi. Matnli rejimdan foydalaning.',
                'no-speech': 'Nutq aniqlanmadi. Qayta urinishingiz yoki yozishingiz mumkin.',
                'audio-capture': 'Mikrofon topilmadi.',
                'network': 'Brauzer speech xizmatiga ulanish bo‘lmadi.',
                'service-not-allowed': 'Brauzer nutq xizmati cheklangan. Matnli rejimdan foydalanishingiz mumkin.',
            };
            setVoiceState('ERROR', messages[event.error] || `Voice xatosi: ${event.error}`);
        };
        recognition.onend = () => {
            isListening = false;
            micButton?.classList.remove('is-listening');
            if (micButton) micButton.querySelector('span').textContent = 'Mikrofon';
            if (!input.value.trim()) voiceState.textContent = 'Text rejimi tayyor';
        };
        try {
            recognition.start();
            return true;
        } catch {
            return false;
        }
    };

    const beginRecognition = () => {
        if (isListening || processing || speaking || !voiceSessionId) return;
        const microphoneStream = pendingMicrophoneStream;
        pendingMicrophoneStream = null;
        if (realtimeVoice && !realtimeFailed) {
            if (realtime.status !== 'idle') {
                microphoneStream?.getTracks().forEach(track => track.stop());
                return;
            }
            setVoiceState('CONNECTING', 'Jonli mikrofon ulanmoqda…');
            const url = root.dataset.realtimeTemplate.replace('__VOICE__', String(voiceSessionId));
            realtime.begin(async (sdp, signal) => {
                const { data } = await jsonRequest(url, {
                    method: 'POST', signal,
                    body: JSON.stringify({ sdp, locale: document.documentElement.lang }),
                });
                return data.sdp;
            }, handsFree, microphoneStream);
            return;
        }
        if (serverVoice && !realtimeFailed) {
            setVoiceState('CONNECTING', 'Mikrofon ulanmoqda…');
            microphone.start(root.dataset.transcribeTemplate.replace('__VOICE__', String(voiceSessionId)), csrf, document.documentElement.lang, microphoneStream);
            return;
        }
        if (root.dataset.transcribeTemplate && Boolean(window.MediaRecorder)) {
            setVoiceState('CONNECTING', 'Mikrofon ulanmoqda…');
            microphone.start(root.dataset.transcribeTemplate.replace('__VOICE__', String(voiceSessionId)), csrf, document.documentElement.lang, microphoneStream);
            return;
        }
        if (startBrowserRecognition()) return;
        setVoiceState('ERROR', 'Mikrofon ishga tushmadi. Matnli rejimdan foydalaning.');
    };

    const createVoiceSession = async () => {
        if (voiceConnecting) return;
        voiceConnecting = true;
        const generation = voiceGeneration;
        try {
            await connectVoiceSession(generation);
        } finally {
            voiceConnecting = false;
        }
    };

    const connectVoiceSession = async generation => {
        setVoiceState('CONNECTING', 'Mikrofon ruxsati tekshirilmoqda…');
        let permissionStream;
        const voiceContextError = message => Object.assign(new Error(message), { name: 'CourtroomVoiceError' });
        try {
            const microphonePolicy = document.permissionsPolicy || document.featurePolicy;
            if (!window.isSecureContext) {
                throw voiceContextError('Mikrofon uchun sahifani HTTPS yoki localhost orqali oching.');
            }
            if (microphonePolicy?.allowsFeature && !microphonePolicy.allowsFeature('microphone')) {
                throw voiceContextError('Brauzer ushbu sahifaga mikrofon ruxsatini bermayapti. Agar sahifa iframe ichida bo‘lsa, uni alohida oynada oching.');
            }
            if (!navigator.mediaDevices?.getUserMedia) {
                throw voiceContextError('Brauzer mikrofon API sini taqdim etmadi. Sahifani HTTPS yoki localhost orqali oching.');
            }
            const permissionRequest = navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            });
            permissionStream = await permissionRequest;
        } catch (error) {
            if (error.name === 'CourtroomVoiceError') {
                throw error;
            }
            if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
                throw new Error('Brauzer mikrofonni blokladi. Sayt ruxsatlaridan Microphone → Allow ni tanlang, sahifani qayta yuklang va yana urinib ko‘ring. Safari’da: Settings → Websites → Microphone.');
            }
            if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                throw new Error('Mikrofon topilmadi. Qurilmani ulang va tizim sozlamalarida mikrofonni yoqing.');
            }
            if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
                throw new Error('Mikrofon boshqa dasturda band yoki tizim tomonidan bloklangan. Boshqa ilovalarni yoping va qayta urinib ko‘ring.');
            }
            throw new Error('Mikrofonni ishga tushirib bo‘lmadi. Brauzer va tizim mikrofon ruxsatlarini tekshiring.');
        }
        if (generation !== voiceGeneration || document.hidden) {
            permissionStream.getTracks().forEach(track => track.stop());
            return;
        }
        pendingMicrophoneStream = permissionStream;
        transcriptStorageEnabled = Boolean(transcriptConsent?.checked);
        const { data } = await jsonRequest(root.dataset.voiceUrl, {
            method: 'POST',
            body: JSON.stringify({
                consent: true,
                transcript_consent: transcriptStorageEnabled,
                capabilities: {
                    speech_recognition: Boolean(SpeechRecognition),
                    speech_synthesis: 'speechSynthesis' in window,
                    locale: document.documentElement.lang,
                },
            }),
        });
        if (generation !== voiceGeneration || document.hidden) {
            pendingMicrophoneStream?.getTracks().forEach(track => track.stop());
            pendingMicrophoneStream = null;
            const endUrl = root.dataset.voiceEndTemplate.replace('__VOICE__', String(data.voice_session.id));
            jsonRequest(endUrl, { method: 'POST', keepalive: true }).catch(() => {});
            return;
        }
        voiceSessionId = data.voice_session.id;
        voiceState.textContent = 'Voice session faol. Raw audio saqlanmaydi.';
        beginRecognition();
    };

    micButton?.addEventListener('click', () => {
        if (!voiceSupported || voiceConnecting) return;
        if (speaking) {
            voiceGeneration++;
            speaking = false;
            window.speechSynthesis?.cancel();
            stopAudio();
            setSpeaker(null);
            setVoiceState('INTERRUPTED', 'AI ovozi to‘xtatildi.');
        }
        if (isListening) {
            if (realtime.status === 'listening') { realtime.finish(); return; }
            if ((microphone.recorder && microphone.recorder.state === 'recording') || serverVoice) {
                setVoiceState('PROCESSING', 'Nutq aniqlanmoqda…');
                microphone.stop(false);
                return;
            }
            recognition?.stop();
            return;
        }
        if (voiceSessionId) {
            beginRecognition();
            return;
        }
        consentDialog?.showModal();
    });
    document.querySelector('[data-voice-consent-confirm]')?.addEventListener('click', async event => {
        event.preventDefault();
        consentDialog.close();
        try { await createVoiceSession(); }
        catch (error) {
            pendingMicrophoneStream?.getTracks().forEach(track => track.stop());
            pendingMicrophoneStream = null;
            setVoiceState('ERROR', error.message || 'Mikrofon yoqilmadi. Matnli rejim ishlaydi.');
        }
    });
    document.querySelector('[data-voice-handsfree]')?.addEventListener('click', event => {
        handsFree = !handsFree;
        event.currentTarget.setAttribute('aria-pressed', String(handsFree));
        if (realtime.status === 'listening') {
            realtime.finish();
            return;
        }
        if (handsFree && voiceSessionId && !processing && !speaking) beginRecognition();
        if (!handsFree) recognition?.stop();
    });
    document.querySelector('[data-voice-end]')?.addEventListener('click', async () => {
        stopHandsFree();
        realtime.close();
        realtimeFailed = false;
        voiceGeneration++;
        speaking = false;
        recognition?.abort();
        microphone.stop(true);
        window.speechSynthesis?.cancel();
        stopAudio();
        setSpeaker(null);
        if (!voiceSessionId) return;
        const id = voiceSessionId;
        voiceSessionId = null;
        setVoiceState('ENDED', 'Ovozli suhbat tugadi. Matnli rejim ishlaydi.');
        try {
            await jsonRequest(root.dataset.voiceEndTemplate.replace('__VOICE__', String(id)), { method: 'POST' });
        } catch {
            setVoiceState('ERROR', 'Mikrofon o‘chirildi, lekin serverga ulanish bo‘lmadi.');
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            realtime.close();
            voiceGeneration++;
            speaking = false;
            recognition?.abort();
            microphone.stop(true);
            window.speechSynthesis?.cancel();
            stopAudio();
            setSpeaker(null);
            setVoiceState('MUTED', 'Ovoz pauzada. Davom etish uchun mikrofonni bosing.');
        }
    });
    window.addEventListener('offline', () => {
        realtime.close();
        voiceGeneration++;
        speaking = false;
        recognition?.abort();
        microphone.stop(true);
        window.speechSynthesis?.cancel();
        stopAudio();
        setVoiceState('ERROR', 'Internet uzildi. Matnni saqlab, aloqa qaytgach qayta yuboring.');
    });
    window.addEventListener('online', () => {
        setVoiceState('IDLE', 'Internet tiklandi. Davom etishingiz mumkin.');
    });
    window.addEventListener('pagehide', () => {
        realtime.close();
        voiceGeneration++;
        handsFree = false;
        recognition?.stop();
        microphone.stop(true);
        window.speechSynthesis?.cancel();
        stopAudio();
        if (!voiceSessionId) return;
        const url = root.dataset.voiceEndTemplate.replace('__VOICE__', String(voiceSessionId));
        fetch(url, { method: 'POST', keepalive: true, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } }).catch(() => {});
    });

    // Group Courtroom Real-time Synchronization and Voice simulation
    if (root.dataset.feedUrl) {
        let lastTurnSequence = 0;
        const existingArticles = [...(transcriptLog?.querySelectorAll('[data-sequence]') ?? [])];
        if (existingArticles.length > 0) {
            lastTurnSequence = Math.max(...existingArticles.map(a => Number(a.dataset.sequence || 0)));
        }
        let lastEventSequence = 0;
        let isPolling = false;

        const simulateSpeakingLipMotion = (role, durationMs = 3000) => {
            const started = performance.now();
            const shapes = ['aa', 'ee', 'oh', 'sil'];
            let frameId = null;
            const animate = () => {
                const elapsed = performance.now() - started;
                if (elapsed >= durationMs) {
                    window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role: null, level: 0, shape: 'sil' } }));
                    return;
                }
                const level = 0.2 + 0.3 * Math.abs(Math.sin(elapsed / 120));
                const shape = shapes[Math.floor((elapsed / 180) % shapes.length)];
                window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role, level, shape } }));
                frameId = requestAnimationFrame(animate);
            };
            frameId = requestAnimationFrame(animate);
        };

        const pollGroupFeed = async () => {
            if (isPolling || document.hidden) return;
            isPolling = true;
            try {
                const res = await fetch(`${root.dataset.feedUrl}?after=${lastTurnSequence}&after_event=${lastEventSequence}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();

                // Status sync (paused / active / completed)
                const pauseOverlay = document.querySelector('[data-pause-overlay]');
                if (pauseOverlay) {
                    pauseOverlay.hidden = (data.status !== 'paused');
                }
                const connStatus = document.querySelector('[data-connection-status]');
                if (connStatus && data.status) {
                    connStatus.textContent = data.status.toUpperCase();
                }

                // Stage change sweep
                const currentStageEl = document.querySelector('[data-current-stage]');
                if (currentStageEl && data.stage_name && currentStageEl.textContent !== data.stage_name) {
                    currentStageEl.textContent = data.stage_name;
                    window.dispatchEvent(new CustomEvent('courtroom-stage-change', { detail: { stage: data.stage } }));
                }

                // Evidence presentation
                if (data.board_evidence_id && String(data.board_evidence_id) !== String(root.dataset.boardEvidenceId)) {
                    root.dataset.boardEvidenceId = data.board_evidence_id;
                    window.dispatchEvent(new CustomEvent('courtroom-evidence', { detail: { id: data.board_evidence_id, focus: true } }));
                }

                // Turns ingestion
                if (Array.isArray(data.turns) && data.turns.length > 0) {
                    const emptyPlaceholder = transcriptLog?.querySelector('.courtroom-transcript-empty');
                    if (emptyPlaceholder) emptyPlaceholder.remove();

                    for (const turn of data.turns) {
                        if (turn.sequence > lastTurnSequence) {
                            lastTurnSequence = turn.sequence;
                            const article = document.createElement('article');
                            article.className = turn.speaker_type === 'student' ? 'is-student' : 'is-ai';
                            article.dataset.speaker = turn.speaker_role_key;
                            article.dataset.sequence = String(turn.sequence);
                            article.innerHTML = `<strong>${turn.speaker_role_key}</strong><p>${turn.content}</p>`;
                            transcriptLog?.appendChild(article);

                            // Trigger camera focus & speaker event
                            window.dispatchEvent(new CustomEvent('courtroom-speaker', { detail: { role: turn.speaker_role_key } }));

                            // Trigger gesture
                            let gestureType = 'make_statement';
                            const lower = (turn.content || '').toLowerCase();
                            if (lower.includes('e’tiroz') || lower.includes('e\'tiroz') || lower.includes('objection')) gestureType = 'make_objection';
                            else if (lower.includes('dalil') || lower.includes('hujjat') || lower.includes('evidence')) gestureType = 'present_evidence';
                            else if (lower.includes('?') || lower.includes('savol')) gestureType = 'ask_question';
                            window.dispatchEvent(new CustomEvent('courtroom-gesture', { detail: { role: turn.speaker_role_key, type: gestureType } }));

                            // Trigger simulated lip motion
                            simulateSpeakingLipMotion(turn.speaker_role_key, Math.min(5000, Math.max(1800, turn.content.length * 60)));
                        }
                    }
                    transcriptLog?.scrollTo({ top: transcriptLog.scrollHeight, behavior: 'smooth' });
                }

                if (Array.isArray(data.events)) {
                    for (const ev of data.events) {
                        if (ev.sequence > lastEventSequence) lastEventSequence = ev.sequence;
                    }
                }
            } catch {
                // Ignore transient network errors
            } finally {
                isPolling = false;
            }
        };

        const groupPollInterval = window.setInterval(pollGroupFeed, 2000);
        window.addEventListener('pagehide', () => clearInterval(groupPollInterval));

        // Group Student Microphone integration
        const groupMicBtn = document.querySelector('[data-group-mic-btn]');
        const groupMicLabel = document.querySelector('[data-group-mic-label]');
        const groupTextArea = document.querySelector('#courtroom-group-message');
        if (groupMicBtn && groupTextArea) {
            let isRecording = false;
            let recognitionInstance = null;
            const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;

            groupMicBtn.addEventListener('click', () => {
                if (isRecording) {
                    recognitionInstance?.stop();
                    return;
                }
                if (!SpeechRec) {
                    alert('Brauzeringiz ovoz tanishni qo‘llab-quvvatlamaydi. Matn yozing.');
                    return;
                }
                try {
                    recognitionInstance = new SpeechRec();
                    recognitionInstance.lang = 'uz-UZ';
                    recognitionInstance.continuous = true;
                    recognitionInstance.interimResults = true;

                    recognitionInstance.onstart = () => {
                        isRecording = true;
                        groupMicBtn.classList.add('is-recording');
                        if (groupMicLabel) groupMicLabel.textContent = 'Tugatish';
                        window.dispatchEvent(new CustomEvent('courtroom-speaker', { detail: { role: root.dataset.studentRole } }));
                        window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role: root.dataset.studentRole, level: 0.4, shape: 'aa' } }));
                    };

                    recognitionInstance.onresult = event => {
                        let finalTranscript = '';
                        for (let i = event.resultIndex; i < event.results.length; ++i) {
                            if (event.results[i].isFinal) {
                                finalTranscript += event.results[i][0].transcript;
                            }
                        }
                        if (finalTranscript) {
                            groupTextArea.value = (groupTextArea.value + ' ' + finalTranscript).trim();
                        }
                    };

                    recognitionInstance.onerror = () => {
                        isRecording = false;
                        groupMicBtn.classList.remove('is-recording');
                        if (groupMicLabel) groupMicLabel.textContent = 'Gapirish';
                        window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role: null, level: 0 } }));
                    };

                    recognitionInstance.onend = () => {
                        isRecording = false;
                        groupMicBtn.classList.remove('is-recording');
                        if (groupMicLabel) groupMicLabel.textContent = 'Gapirish';
                        window.dispatchEvent(new CustomEvent('courtroom-audio', { detail: { role: null, level: 0 } }));
                    };

                    recognitionInstance.start();
                } catch {
                    isRecording = false;
                    groupMicBtn.classList.remove('is-recording');
                }
            });
        }

        // Group Student Form AJAX submission (No page reload to preserve 3D scene)
        const groupForm = document.querySelector('[data-group-courtroom-form]');
        if (groupForm) {
            groupForm.addEventListener('submit', async event => {
                event.preventDefault();
                const submitBtn = groupForm.querySelector('[type="submit"]');
                const contentInput = groupForm.querySelector('#courtroom-group-message');
                const content = (contentInput?.value || '').trim();

                if (!content) {
                    contentInput?.focus();
                    return;
                }

                if (submitBtn) submitBtn.disabled = true;

                // Animate speaking student avatar locally immediately
                const myRole = root.dataset.studentRole;
                if (myRole) {
                    window.dispatchEvent(new CustomEvent('courtroom-speaker', { detail: { role: myRole } }));
                    let gestureType = 'make_statement';
                    const lower = content.toLowerCase();
                    if (lower.includes('e’tiroz') || lower.includes('e\'tiroz') || lower.includes('objection')) gestureType = 'make_objection';
                    else if (lower.includes('dalil') || lower.includes('hujjat') || lower.includes('evidence')) gestureType = 'present_evidence';
                    else if (lower.includes('?') || lower.includes('savol')) gestureType = 'ask_question';
                    window.dispatchEvent(new CustomEvent('courtroom-gesture', { detail: { role: myRole, type: gestureType } }));
                    simulateSpeakingLipMotion(myRole, Math.min(5000, Math.max(1800, content.length * 60)));
                }

                try {
                    const formData = new FormData(groupForm);
                    const res = await fetch(groupForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: formData,
                    });

                    if (res.ok) {
                        if (contentInput) contentInput.value = '';
                        const idempotencyInput = groupForm.querySelector('input[name="idempotency_key"]');
                        if (idempotencyInput) {
                            idempotencyInput.value = window.crypto?.randomUUID ? window.crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).substring(2, 9));
                        }
                        await pollGroupFeed();
                    } else {
                        const err = await res.json().catch(() => ({}));
                        alert(err.message || 'Xatolik yuz berdi. Iltimos qaytadan urining.');
                    }
                } catch {
                    alert('Tarmoq xatosi. Iltimos aloqani tekshiring.');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // Group Teacher Controls AJAX submission
        document.querySelectorAll('form[data-group-teacher-action]').forEach(teacherForm => {
            teacherForm.addEventListener('submit', async event => {
                event.preventDefault();
                const submitBtn = teacherForm.querySelector('[type="submit"], button');
                if (submitBtn) submitBtn.disabled = true;

                try {
                    const res = await fetch(teacherForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: new FormData(teacherForm),
                    });

                    if (res.ok) {
                        const json = await res.json().catch(() => ({}));
                        if (json.board_evidence_id) {
                            window.dispatchEvent(new CustomEvent('courtroom-evidence', { detail: { id: json.board_evidence_id, focus: true } }));
                        }
                        if (json.stage) {
                            window.dispatchEvent(new CustomEvent('courtroom-stage-change', { detail: { stage: json.stage } }));
                        }
                        await pollGroupFeed();
                    }
                } catch {
                    // Ignore or fallback
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        });
    }

    // Lobby Pre-flight check (Microphone test)
    const lobbyMicBtn = document.querySelector('#lobby-mic-test-btn');
    const lobbyMicStatus = document.querySelector('#lobby-mic-status');
    if (lobbyMicBtn && lobbyMicStatus) {
        lobbyMicBtn.addEventListener('click', async () => {
            lobbyMicBtn.disabled = true;
            lobbyMicStatus.textContent = 'Tekshirilmoqda…';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                lobbyMicStatus.textContent = '✓ Mikrofon ishlayapti';
                lobbyMicStatus.className = 'text-emerald-600 dark:text-emerald-400 text-[11px] font-semibold';
                lobbyMicBtn.textContent = 'Muvaffaqiyatli';
                stream.getTracks().forEach(track => track.stop());
            } catch {
                lobbyMicStatus.textContent = '✗ Ruxsat berilmadi yoki topilmadi';
                lobbyMicStatus.className = 'text-rose-600 dark:text-rose-400 text-[11px]';
                lobbyMicBtn.disabled = false;
            }
        });
    }

    const timer = document.querySelector('[data-courtroom-timer]');
    if (timer?.dataset.startedAt) {
        const started = new Date(timer.dataset.startedAt).getTime();
        const updateTimer = () => {
            const seconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
            timer.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        };
        updateTimer();
        window.setInterval(updateTimer, 1000);
    }

    if (scene) {
        import('./courtroom-scene.js')
            .then(module => module.initialiseCourtroomScene(scene))
            .catch(() => {
                scene.querySelector('#courtroom-canvas').hidden = true;
                scene.querySelector('[data-scene-loading]').hidden = true;
                scene.querySelector('[data-scene-fallback]').hidden = false;
            });
    }
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });
}
