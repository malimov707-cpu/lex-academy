const maxUtteranceMs = 30_000;
const transcriptTimeoutMs = 20_000;

export class CourtroomRealtime {
    constructor({ onListening, onProcessing, onDelta, onTranscript, onError }) {
        this.handlers = { onListening, onProcessing, onDelta, onTranscript, onError };
        this.status = 'idle';
        this.generation = 0;
    }

    async begin(exchange, handsFree, existingStream = null) {
        if (this.status !== 'idle') return;
        clearTimeout(this.idleTimeout);
        const generation = ++this.generation;
        this.status = 'connecting';

        try {
            if (!this.channel || this.channel.readyState !== 'open') await this.connect(exchange, existingStream);
            else existingStream?.getTracks().forEach(track => track.stop());
            if (generation !== this.generation || this.status !== 'connecting') return;

            this.status = 'listening';
            this.startedAt = performance.now();
            this.lastSpeechAt = null;
            this.track.enabled = true;
            this.handlers.onListening();
            this.turnTimeout = setTimeout(() => this.finish(), maxUtteranceMs);
            if (handsFree) this.detectSilence();
        } catch (error) {
            if (generation === this.generation && this.status !== 'idle') this.fail(error.message || 'Jonli mikrofon ulanmagan.');
        }
    }

    async connect(exchange, existingStream = null) {
        const connection = this.connection = new AbortController();
        const disconnected = new Promise((_, reject) => {
            const timeout = setTimeout(() => reject(new Error('Jonli ovoz aloqasi vaqtida tiklanmadi.')), 12_000);
            this.connectionTimeout = timeout;
            connection.signal.addEventListener('abort', () => {
                clearTimeout(timeout);
                reject(new DOMException('Ulanish bekor qilindi.', 'AbortError'));
            }, { once: true });
        });
        const stream = existingStream || await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
        });

        if (connection.signal.aborted) {
            stream.getTracks().forEach(track => track.stop());
            return;
        }

        this.stream = stream;
        this.track = stream.getAudioTracks()[0];
        this.track.enabled = false;
        const peer = this.peer = new RTCPeerConnection();
        peer.addTransceiver(this.track, { direction: 'sendonly', streams: [stream] });
        const channel = this.channel = peer.createDataChannel('oai-events');
        channel.addEventListener('message', event => this.receive(event.data));
        channel.addEventListener('close', () => {
            if (!connection.signal.aborted) this.fail('Jonli ovoz aloqasi uzildi.');
        });
        peer.addEventListener('connectionstatechange', () => {
            if (connection.signal.aborted) return;
            if (peer.connectionState === 'connected') {
                clearTimeout(this.connectionTimeout);
                this.connectionTimeout = null;
            } else if (peer.connectionState === 'failed') {
                this.fail('Jonli ovoz aloqasi uzildi.');
            }
        });
        await peer.setLocalDescription(await peer.createOffer());
        const answer = await exchange(peer.localDescription.sdp, connection.signal);
        if (connection.signal.aborted) return;
        await peer.setRemoteDescription({ type: 'answer', sdp: answer });
        if (peer.connectionState !== 'connected') {
            await Promise.race([
                new Promise((resolve, reject) => {
                    const connected = () => {
                        if (peer.connectionState === 'failed') {
                            peer.removeEventListener('connectionstatechange', connected);
                            reject(new Error('Jonli ovoz aloqasi uzildi.'));
                        } else if (peer.connectionState === 'connected') {
                            peer.removeEventListener('connectionstatechange', connected);
                            resolve();
                        }
                    };
                    peer.addEventListener('connectionstatechange', connected);
                    connected();
                }),
                disconnected,
            ]);
        }
        if (channel.readyState !== 'open') {
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => reject(new Error('Jonli ovoz aloqasi vaqtida ochilmadi.')), 10_000);
                const finish = callback => {
                    clearTimeout(timeout);
                    callback();
                };
                channel.addEventListener('open', () => finish(resolve), { once: true, signal: connection.signal });
                connection.signal.addEventListener('abort', () => finish(resolve), { once: true });
            });
        }
    }

    detectSilence() {
        if (!window.AudioContext) return;
        this.audioContext ??= new AudioContext();
        const analyser = this.analyser ??= this.audioContext.createAnalyser();
        if (!this.source) {
            this.source = this.audioContext.createMediaStreamSource(this.stream);
            this.source.connect(analyser);
        }
        this.audioContext.resume().catch(() => {});
        const samples = new Float32Array(analyser.fftSize);
        const sample = now => {
            if (this.status !== 'listening') return;
            analyser.getFloatTimeDomainData(samples);
            const rms = Math.sqrt(samples.reduce((sum, value) => sum + value * value, 0) / samples.length);
            if (rms > .022) this.lastSpeechAt = now;
            if (this.lastSpeechAt && now - this.lastSpeechAt > 850) {
                this.finish();
            } else if (!this.lastSpeechAt && now - this.startedAt > 9000) {
                this.close();
                this.handlers.onError('Nutq aniqlanmadi. Qayta urinib ko‘ring.', { fallback: false });
            } else {
                this.frame = requestAnimationFrame(sample);
            }
        };
        this.frame = requestAnimationFrame(sample);
    }

    finish() {
        if (this.status !== 'listening') return;
        if (performance.now() - this.startedAt < 200) {
            this.close();
            this.handlers.onError('Nutq juda qisqa. Qayta urinib ko‘ring.', { fallback: false });
            return;
        }
        this.track.enabled = false;
        clearTimeout(this.turnTimeout);
        cancelAnimationFrame(this.frame);
        this.status = 'processing';
        this.handlers.onProcessing();
        this.itemId = null;
        // Audio and data travel separately. Give the final RTP packets time to arrive before committing the buffer.
        this.commitTimeout = setTimeout(() => {
            if (this.status !== 'processing' || this.channel?.readyState !== 'open') return;
            this.channel.send(JSON.stringify({ type: 'input_audio_buffer.commit' }));
            this.resultTimeout = setTimeout(() => this.fail('Jonli transcript vaqtida qaytmadi.'), transcriptTimeoutMs);
        }, 200);
    }

    receive(message) {
        if (typeof message !== 'string') return;
        let event;
        try { event = JSON.parse(message); } catch { return; }
        if (!event || typeof event.type !== 'string') return;
        if (event.type === 'error') {
            if (this.status === 'processing') this.fail('Jonli ovoz xizmatida xato yuz berdi.');
            return;
        }
        if (this.status !== 'processing') return;
        if (event.type === 'input_audio_buffer.committed') {
            this.itemId = event.item_id;
            return;
        }
        if (!this.itemId || this.itemId !== event.item_id) return;
        if (event.type === 'conversation.item.input_audio_transcription.delta' && typeof event.delta === 'string') {
            this.handlers.onDelta(event.delta);
        }
        if (event.type === 'conversation.item.input_audio_transcription.failed') {
            this.fail('Jonli nutqni aniqlab bo‘lmadi.');
        }
        if (event.type === 'conversation.item.input_audio_transcription.completed') {
            clearTimeout(this.resultTimeout);
            this.status = 'idle';
            this.idleTimeout = setTimeout(() => this.close(), 60_000);
            const content = typeof event.transcript === 'string' ? event.transcript.trim().slice(0, 4000) : '';
            if (content) this.handlers.onTranscript(content);
            else this.handlers.onError('Nutq aniqlanmadi. Qayta urinib ko‘ring.', { fallback: false });
        }
    }

    fail(message) {
        this.close();
        this.handlers.onError(message);
    }

    close() {
        this.generation++;
        this.status = 'idle';
        this.connection?.abort();
        clearTimeout(this.connectionTimeout);
        this.channel?.close();
        this.peer?.close();
        this.stream?.getTracks().forEach(track => track.stop());
        this.audioContext?.close().catch(() => {});
        this.channel = this.peer = this.stream = this.track = this.audioContext = this.analyser = this.source = null;

        clearTimeout(this.turnTimeout);
        clearTimeout(this.commitTimeout);
        clearTimeout(this.resultTimeout);
        clearTimeout(this.idleTimeout);
        cancelAnimationFrame(this.frame);
    }
}
