// Interview recorder: captures audio with MediaRecorder and, where the browser supports it,
// transcribes live with the Web Speech API so elders can simply talk.
const SPEECH_LANG = { en: 'en-IN', hi: 'hi-IN', ur: 'ur-PK', ar: 'ar-SA', bn: 'bn-IN', pa: 'pa-IN', gu: 'gu-IN', ta: 'ta-IN', te: 'te-IN', mr: 'mr-IN', fa: 'fa-IR', fr: 'fr-FR', es: 'es-ES', de: 'de-DE', zh: 'zh-CN' };

export function recorder({ saveUrl, transcript = '', lang = 'en' }) {
    return {
        transcript,
        interim: '',
        recording: false,
        seconds: 0,
        blob: null,
        audioUrl: null,
        status: '',
        saving: false,
        speechSupported: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
        _rec: null,
        _speech: null,
        _timer: null,
        _chunks: [],

        async start() {
            this.status = '';
            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.status = 'Microphone access was blocked. Allow it in the browser, or type the transcript below.';
                return;
            }
            this._chunks = [];
            this._rec = new MediaRecorder(stream);
            this._rec.ondataavailable = (e) => e.data.size && this._chunks.push(e.data);
            this._rec.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                this.blob = new Blob(this._chunks, { type: this._rec.mimeType || 'audio/webm' });
                this.audioUrl = URL.createObjectURL(this.blob);
            };
            this._rec.start(1000);
            this.recording = true;
            this.seconds = 0;
            this._timer = setInterval(() => this.seconds++, 1000);
            this.startSpeech();
        },

        startSpeech() {
            if (!this.speechSupported) return;
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            this._speech = new SR();
            this._speech.lang = SPEECH_LANG[lang] || 'en-IN';
            this._speech.continuous = true;
            this._speech.interimResults = true;
            this._speech.onresult = (e) => {
                let interim = '';
                for (let i = e.resultIndex; i < e.results.length; i++) {
                    const text = e.results[i][0].transcript;
                    if (e.results[i].isFinal) {
                        this.transcript += (this.transcript && !this.transcript.endsWith('\n') ? ' ' : '') + text.trim();
                    } else {
                        interim += text;
                    }
                }
                this.interim = interim;
            };
            // Recognition stops on silence; keep it going while recording.
            this._speech.onend = () => this.recording && this._speech.start();
            this._speech.start();
        },

        stop() {
            this.recording = false;
            clearInterval(this._timer);
            this._speech?.stop();
            this._rec?.state !== 'inactive' && this._rec?.stop();
            this.interim = '';
        },

        markQuestion(q) {
            this.transcript += (this.transcript ? '\n\n' : '') + 'Q: ' + q + '\nA: ';
        },

        get clock() {
            const m = String(Math.floor(this.seconds / 60)).padStart(2, '0');
            return m + ':' + String(this.seconds % 60).padStart(2, '0');
        },

        async save() {
            this.saving = true;
            const body = new FormData();
            body.append('_method', 'PUT');
            body.append('transcript', this.transcript);
            if (this.blob) body.append('audio', this.blob, 'interview.webm');
            try {
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    body,
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Could not save. Please try again.');
                this.status = data.message || 'Saved.';
                this.blob = null;
                setTimeout(() => window.location.reload(), 600);
            } catch (e) {
                this.status = e.message;
            } finally {
                this.saving = false;
            }
        },
    };
}
