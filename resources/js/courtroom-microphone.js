export class CourtroomMicrophone {
    constructor({ onListening, onProcessing, onTranscript, onError }) {
        this.onListening = onListening;
        this.onProcessing = onProcessing;
        this.onTranscript = onTranscript;
        this.onError = onError;
        this.generation = 0;
    }

    async start(url, csrf, locale, existingStream = null) {
        this.stop(true);
        const generation = ++this.generation;
        let stream;
        try {
            stream = existingStream || await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
            if (generation !== this.generation) { stream.getTracks().forEach(track => track.stop()); return; }
            this.stream = stream;
            const mimeType = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4'].find(type => MediaRecorder.isTypeSupported(type));
            const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});
            this.recorder = recorder;
            const chunks = [];
            let bytes = 0;
            recorder.ondataavailable = event => {
                bytes += event.data.size;
                if (bytes > 8 * 1024 * 1024) {
                    this.stop(true);
                    this.onError('Nutq juda uzun. Qisqaroq gapiring yoki matn yozing.');
                } else if (event.data.size) chunks.push(event.data);
            };
            recorder.onerror = () => { this.stop(true); this.onError('Mikrofon ishlamadi. Matnli rejimdan foydalaning.'); };
            recorder.onstop = async () => {
                clearTimeout(this.timeout);
                stream.getTracks().forEach(track => track.stop());
                if (generation !== this.generation) return;
                this.onListening(false);
                if (!chunks.length) {
                    this.onError('Ovoz yozuvi olinmadi. Qayta urinib ko‘ring.');
                    return;
                }
                this.onProcessing();
                const type = recorder.mimeType;
                const extension = type.includes('mp4') ? 'mp4' : type.includes('ogg') ? 'ogg' : 'webm';
                const body = new FormData();
                body.append('audio', new Blob(chunks, { type }), `speech.${extension}`);
                body.append('locale', locale);
                this.request = new AbortController();
                try {
                    const response = await fetch(url, { method: 'POST', body, signal: this.request.signal, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                    const data = await response.json();
                    if (generation !== this.generation) return;
                    if (!response.ok) throw new Error(data.error || 'Nutqni aniqlab bo‘lmadi. Matnli rejim ishlaydi.');
                    this.onTranscript(data.content);
                } catch (error) {
                    if (error.name !== 'AbortError' && generation === this.generation) this.onError(error.message);
                }
            };
            recorder.start(200);
            this.onListening(true);
            this.timeout = setTimeout(() => this.stop(false), 30000);
        } catch {
            stream?.getTracks().forEach(track => track.stop());
            if (generation !== this.generation) return;
            this.onListening(false);
            this.onError('Mikrofon ruxsati berilmadi yoki qurilma topilmadi. Matnli rejim ishlaydi.');
        }
    }

    stop(discard = false) {
        clearTimeout(this.timeout);
        if (discard) {
            this.generation++;
            this.request?.abort();
            this.onListening(false);
            this.stream?.getTracks().forEach(track => track.stop());
            if (this.recorder && this.recorder.state === 'recording') {
                try { this.recorder.stop(); } catch {}
            }
            return;
        }

        if (this.recorder && this.recorder.state === 'recording') {
            try { this.recorder.requestData(); } catch {}
            try { this.recorder.stop(); } catch {}
        }
    }
}
