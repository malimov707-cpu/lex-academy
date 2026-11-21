# HUQUQ AI Virtual Courtroom — arxitektura va joriy etish qarorlari

## Holat va audit xulosasi

Ushbu hujjat Virtual Courtroom master topshirig‘ining Phase 0 natijasidir. U mavjud Laravel ilovasining real holatiga asoslanadi; mavjud bo‘lmagan imkoniyatlar tayyor deb ko‘rsatilmaydi.

| Yo‘nalish | Mavjud holat | Qaror |
|---|---|---|
| Backend | Laravel 13, PHP 8.4, session auth, rol middleware | Birinchi versiya same-origin session + CSRF bilan ishlaydi |
| AI | OpenAI-compatible, faqat non-streaming text chat | Mavjud driver buzilmaydi; courtroom orchestrator undan matnli javob uchun foydalanadi |
| AI holati | Kalit sozlangan, so‘nggi jonli so‘rov billing/kredit xatosi bilan qaytgan | Soxta javob berilmaydi; 503 va matnli mustaqil davom ettirish holati ko‘rsatiladi |
| Voice | Brauzer STT/TTS va sozlama orqali yoqiladigan OpenAI STT → orchestrator → TTS | WebRTC mikrofon oqimini uzatadi. Laravel transcriptni sud harakati sifatida tekshiradi va saqlangan AI javobini ovozga aylantiradi. |
| 3D | Babylon.js 9.37.0, PBR zal va ikki riglangan Rocketbox GLB modeli | Assetlar lokal yuklanadi. WebGL2 ishlamasa DOM interfeysi, model yuklanmasa procedural avatar ishlaydi. |
| Huquqiy RAG | So‘nggi tekshirilgan manbalardan oddiy kontekst | Scenario versiyasiga biriktirilgan, ID bo‘yicha allow-list qilingan manbalar ishlatiladi |
| Fayllar | Private local storage va avtorizatsiyalangan streaming mavjud | Dalillar private storage’da, rol va session bo‘yicha tekshiriladi |
| Realtime | WebSocket/Reverb/Sanctum yo‘q | V1 server-authoritative HTTP event oqimi; multiplayer keyingi bosqich |

## Asosiy tamoyillar

1. Server ssenariy bosqichi, ruxsat etilgan harakat, dalil ko‘rinishi va bahoni boshqaradi.
2. AI state machine’ni o‘zgartirmaydi. AI faqat server bergan rol konteksti va scenario snapshot doirasida javob beradi.
3. Har bir session aniq scenario versiyasiga bog‘lanadi. Published versiya o‘zgarsa ham boshlangan session faktlari o‘zgarmaydi.
4. Audio recording default holatda o‘chiq. Raw audio saqlanmaydi. Transcript faqat aniq rozilik va retention siyosati bilan saqlanadi.
5. Baholash har bir mezon bo‘yicha action/event identifikatorlariga bog‘langan izoh bilan yaratiladi. O‘qituvchi yakuniy vakolatga ega.
6. AI, voice yoki 3D ishlamasa, modul buzilmaydi: text va accessible DOM interfeysi saqlanadi.

## Domen modeli

### Ssenariy va versiya

- `simulation_scenarios`: katalog metama’lumotlari, egasi va lifecycle holati.
- `scenario_versions`: o‘zgarmas case facts, procedure, learning objectives va approval/publish holati.
- `scenario_roles`: tanlanadigan yoki AI bajaradigan rollar, umumiy va maxfiy rol instruksiyasi.
- `scenario_stages`: tartiblangan state machine bosqichlari va ruxsat etilgan keyingi bosqichlar.
- `scenario_action_rules`: stage + rol + action kombinatsiyasi, evidence/content talablari va transition.
- `scenario_evidence`: o‘quv dalili, provenance, checksum, versiya va rol visibility.
- `scenario_legal_references`: scenario versiyasi uchun tekshirilgan manba snapshoti.
- `evaluation_rubrics` va `evaluation_criteria`: vazn, maksimal ball va pedagogik guidance.

### Session va eventlar

- `simulation_sessions`: talaba, tanlangan rol, current stage, lifecycle va optimistic lock.
- `session_participants`: student/AI rollar; kelajakdagi multiplayerga mos.
- `session_turns`: real talaba yoki real AI matni, input turi va ketma-ketlik.
- `session_actions`: idempotency key, validation natijasi, dalil va AI javob.
- `session_events`: append-only domain timeline. Sequence session ichida unique.
- `voice_sessions` va `voice_transcript_segments`: consent va transcript metadata; audio yo‘q.
- `evaluation_results`, `evaluation_criterion_results`, `teacher_reviews`: audit qilinadigan baho va inson tekshiruvi.

## Session state machine

Bosqichlar har bir scenario versiyasida saqlanadi. Demo ssenariy oqimi:

`PREPARATION → COURT_OPENING → PARTICIPANT_IDENTIFICATION → OPENING_STATEMENTS → EVIDENCE_PRESENTATION → WITNESS_EXAMINATION → CROSS_EXAMINATION → CLOSING_ARGUMENTS → DECISION_OR_VERDICT → EVALUATION`

Har bir action uchun tekshiruv tartibi:

1. session owner va session holati;
2. idempotency key;
3. current stage;
4. actor role;
5. action rule;
6. content/evidence talabi;
7. evidence visibility va scenario version mosligi;
8. action yozuvi va append-only event;
9. konfiguratsiyalangan transition;
10. kerak bo‘lsa AI orchestrator javobi, alohida turn/event sifatida.

Transition `DB::transaction()` va `lockForUpdate()` ichida bajariladi. Klient yuborgan `current_stage` yoki `score` ishonchli manba emas.

## API va web route qarori

Loyihada hozir `api.php`, Sanctum yoki token auth yo‘q. Birinchi versiya mavjud konventsiyaga mos authenticated web JSON endpointlardan foydalanadi:

- `GET /virtual-courtroom`
- `GET /virtual-courtroom/scenarios/{scenario}`
- `POST /virtual-courtroom/sessions`
- `GET /virtual-courtroom/sessions/{session}`
- `POST /virtual-courtroom/sessions/{session}/start`
- `POST /virtual-courtroom/sessions/{session}/actions`
- `POST /virtual-courtroom/sessions/{session}/complete`
- `GET /virtual-courtroom/sessions/{session}/timeline`
- `GET /virtual-courtroom/sessions/{session}/report`
- `POST /virtual-courtroom/sessions/{session}/report/feedback`
- `GET /virtual-courtroom/sessions/{session}/evidence`
- `POST /virtual-courtroom/sessions/{session}/evidence/{evidence}/present`
- `GET`, `POST /virtual-courtroom/sessions/{session}/evidence/{evidence}/annotations`
- `DELETE /virtual-courtroom/sessions/{session}/evidence/{evidence}/annotations/{annotation}`
- `POST /virtual-courtroom/sessions/{session}/voice`
- `POST /virtual-courtroom/sessions/{session}/voice/{voiceSession}/segments`
- `POST /virtual-courtroom/sessions/{session}/voice/{voiceSession}/end`
- `POST /virtual-courtroom/sessions/{session}/voice/{voiceSession}/realtime`
- `POST /virtual-courtroom/sessions/{session}/voice/{voiceSession}/transcribe`
- `POST /virtual-courtroom/sessions/{session}/voice/{voiceSession}/speech/{turn}`
- `/admin/virtual-courtroom/*` — teacher/admin builder, publish, session report va review.

JSON endpointlar `Accept: application/json`, session auth, CSRF, role/ownership policy va named rate limiter bilan himoyalanadi. Keyinchalik mobil yoki tashqi client talab qilinsa, `/api/v1` + Sanctum alohida arxitektura qarori bo‘ladi.

## AI orchestrator va huquqiy manbalar

Courtroom orchestrator quyidagilarni serverda tuzadi:

- immutable scenario version va current stage;
- AI bajaradigan faqat bitta rolning umumiy/maxfiy konteksti;
- talabaning tanlangan roli, lekin uning maxfiy instruksiyasini boshqa AI roliga bermaydi;
- visible evidence va tekshirilgan legal reference ID ro‘yxati;
- oxirgi cheklangan session turnlar;
- ruxsat etilgan actionlar.

Talaba matni va scenario kontenti “data” sifatida delimit qilinadi. AI’ning transition, score, evidence mutation yoki citation yaratish taklifi server buyrug‘i hisoblanmaydi. Javobdagi citation ID faqat yuborilgan allow-list ichida bo‘lsa ko‘rsatiladi. Manba yetarli bo‘lmasa AI buni aytishi kerak.

Matnli yoki ovozli harakat ixtiyoriy `target_role_key` bilan yuboriladi. Server uni sessiyaning o‘zgarmas ssenariy versiyasidagi rol va bosqich qoidalariga muvofiq tekshiradi. O‘qituvchi harakat uchun javob beruvchi rollarni belgilashi mumkin. Tanlov harakat va timeline’da saqlanadi. AI javobiga platformaning o‘zbek lotin, o‘zbek kirill yoki rus tili sozlamasi beriladi.

O‘qituvchi ssenariyning eng so‘nggi qoralamasini oldindan ko‘rish sahifasida har bir rasmiy manbani ochib, tegishli norma va amaldagi tahrirni qayd etishi mumkin. Tasdiq izoh, tekshiruvchi va sana bilan saqlanadi. Nashr alohida amal orqali bajariladi. AI javobida tekshirilgan manba keltirilsa, sessiya transkripti hujjat nomi, havolasi, norma izohi va tekshiruv sanasini ko‘rsatadi.

Umumiy va tasdiqlangan `LegalSource` yozuvlari kutubxonadan qoralamaga biriktiriladi. Ssenariy nusxasida manba parchasi va kutubxona tasdiq sanasi saqlanadi. O‘qituvchi qoralamadagi normani alohida tekshiradi. Kutubxonada manba arxivlansa yoki yangidan tasdiqlansa, eski nusxa yangi AI javoblari va faol manbalar ro‘yxatidan chiqariladi. O‘qituvchi joriy tahrirni qayta biriktirishi mumkin.

## Voice arxitekturasi

### Hozirgi v1

- User gesture’dan keyin microphone permission.
- Browser `SpeechRecognition` capability mavjud bo‘lsa, explicit consent bilan turn-level transcript.
- Yakuniy transcript odatiy action endpointga yuboriladi.
- Real AI text javobi olingandagina browser `speechSynthesis` orqali ovoz chiqarilishi mumkin.
- Browser capability bo‘lmasa mic tugmasi ishlamaydigan dekoratsiya bo‘lmaydi: “qo‘llanmaydi” holati va text input ko‘rsatiladi.
- Hands-free, ovoz balandligi, AI nutqini to‘xtatish va voice sessiyasini tugatish boshqaruvlari mavjud. Tab yashirilganda tinglash to‘xtatiladi.
- Voice sessiyasi qayta ochilganda oldingisi yopiladi; sud yakunlanganda faol voice sessiyalari yopiladi. Transcript yozish session va voice row lock bilan ketma-ketlashtiriladi.
- Brauzer STT/TTS rejimi `MediaRecorder` ishlatmaydi.

Browser STT vendoriga audio uzatilishi mumkinligi consent oynasida tushuntiriladi. Bu rejim cross-browser aniqlik kafolati emas.

### Server STT/TTS

`VIRTUAL_COURTROOM_SERVER_SPEECH_ENABLED` OpenAI server yo‘lini yoqadi. Rozilikdan keyin brauzer ko‘pi bilan 30 soniyalik nutqni ustma-ust yozmasdan serverga yuboradi. Server nutqni matnga aylantirib, mavjud ssenariy action oqimidan o‘tkazadi. Speech endpoint faqat shu sessiyada saqlangan AI javobini o‘qiydi. Audio doimiy saqlanmaydi. Ixtiyoriy transcriptlar `courtroom:prune-transcripts` orqali har kuni tozalanadi. O‘quv jarayoni harakatlari va baholash uchun kerakli matn alohida saqlanadi.

`AI_TTS_PROVIDER=inworld` server ovoz sintezida ixtiyoriy muqobil. Unda `AI_TTS_INWORLD_API_KEY` va `AI_TTS_INWORLD_VOICE` serverda sozlanadi. Xizmatga faqat saqlangan AI javobi yuboriladi. `inworld-tts-2` bir javobda WAV va OpenXR viseme oraliqlarini qaytaradi. Brauzer ularni `Audio.currentTime` bo‘yicha o‘qiydi. Ovoz kelganida vaqt belgilari mavjud bo‘lmasa, amplituda asosidagi muqobil ishlaydi. Joriy OpenAI rejimi ham amplituda va matndan hosil qilingan taxminiy belgilardan foydalanadi. [Inworld lab sinxroni](https://docs.inworld.ai/tts/capabilities/lipsync) · [Til yordami](https://docs.inworld.ai/tts/capabilities/multilingual)

`php artisan courtroom:check-voice --no-interaction` uchta sinov jumlasini ovozga aylantirib, nutqni tanish xizmatiga qaytaradi, vaqt va so‘z xatosini chiqaradi. `--locale=uz-Latn`, `--locale=uz-Cyrl` yoki `--locale=ru` bilan bitta namuna, `--tts-only` bilan ovoz sintezi tekshiriladi. `--input=/path/to/test.wav --locale=ru` bilan mustaqil STT tekshiruvi ishlaydi. Sintetik jumla bilan sinov tabiiy talaffuzni baholash o‘rnini bosa olmaydi. 2026-11-18 da OpenAI TTS va STT tekshiruvlari `429` qaytardi.

### WebRTC orqali mikrofon oqimi

`VIRTUAL_COURTROOM_VOICE_MODE=realtime` va `VIRTUAL_COURTROOM_SERVER_SPEECH_ENABLED=true` WebRTC rejimini yoqadi. Laravel sessiya egasi va roziligini tekshiradi, SDP taklifini OpenAI Realtime API’ga yuboradi va brauzerga SDP javobini qaytaradi. API kaliti serverda qoladi. Brauzer qo‘lda yoki sukunat aniqlanganda nutqni tugatadi va transcriptni mavjud harakat endpointiga yuboradi. OpenAI Realtime suhbat modelining javobi sud oqimida ishlatilmaydi. Eshitiladigan javob Laravel saqlagan AI turn’dan olinadi. WebRTC uzilganda oddiy server STT/TTS yoki matnli rejim ishlaydi.

## 3D arxitekturasi va asset rejasi

- Catalog/detail sahifalari umumiy `app.js`da qoladi.
- Session alohida `virtual-courtroom.js` Vite entry orqali yuklanadi.
- Scene, camera, quality, avatar/speaker, evidence focus, voice va cleanup modullari ajratiladi.
- WebGPU optional enhancement; WebGL2 asosiy capability. Canvas ishlamasa to‘liq DOM fallback mavjud.
- `prefers-reduced-motion` auto camera va cinematic transitionlarni o‘chiradi.
- Mobile va low-end qurilmalarda texture/avatar/particle soni kamayadi; DPR cheklanadi.

Babylon.js 9.37.0 va glTF loader PBR zalni va lokal GLB avatarlarni yuklaydi. Ikki model [Microsoft Rocketbox](https://github.com/microsoft/Microsoft-Rocketbox) asosida tarqatilgan. Manba, MIT litsenziyasi, SHA-256, fayl hajmi va uchburchaklar soni `public/courtroom/manifest.json` hamda `simulation_assets` jadvalida saqlanadi. Administrator har bir modelni boshqaruv panelidan yoqadi yoki o‘chiradi.

Avatarlar sessiya boshida joyiga yurib boradi, gapirish navbatida turadi va navbat tugagach o‘tiradi. Skelet yurish qadamiga mos qo‘l harakatini, gapirayotgan ishtirokchi tomonga bosh burishni, dalil taqdimoti, savol va e’tiroz imo-ishoralarini bajaradi. Harakatni kamaytirish sozlamasi yoqilganda pozalar o‘tish animatsiyasisiz yangilanadi. Kameralar, sifat sozlamalari va model yuklanmasa procedural ko‘rinish saqlanadi.

## Security va privacy

- Barcha mutatsiya endpointlar auth, role, policy, validation, throttle va CSRF bilan himoyalanadi.
- Session ownership har bir requestda serverda tekshiriladi.
- Evidence private diskda; MIME content + extension + size allow-list bilan tekshiriladi.
- Provider key faqat backend configda, hech qachon `VITE_*`, JS, HTML, log yoki session payloadida emas.
- Provider response body, promptdagi maxfiy rol konteksti, transcript va audio umumiy logga yozilmaydi.
- `Permissions-Policy: microphone=(self)`; provider aniqlanmaguncha wildcard CSP `connect-src` yo‘q.
- Transcript retention muddati config orqali; foydalanuvchi rozilik bermasa transcript segmentlari saqlanmaydi.
- Dalil qaydlari sessiya egasigagina ochiq. Matn belgilari dalilning o‘zgarmas nusxasida Unicode belgilar oralig‘i bilan saqlanadi. Qaydlar AI kontekstiga va o‘qituvchi hisobotiga qo‘shilmaydi. Talaba ularni dalillar oynasidan yoki yakuniy hisobotdan o‘chirishi mumkin.
- Audio recording alohida feature, explicit consent va pedagogik maqsadsiz yoqilmaydi.
- Scenario — ta’lim materiali; AI yuridik maslahat yoki haqiqiy sud qarori sifatida taqdim etilmaydi.

## Baholash

V1 deterministik, event-based draft baho yaratadi. U real actionlar, valid/invalid natijalar, evidence presentation va stage completionga tayanadi. Har criterion natijasi action/event ID va izoh bilan saqlanadi. Talaba yakuniy hisobotdan AI tavsiyasini so‘rashi mumkin. AI xizmatiga so‘nggi 20 tagacha mazmunli harakat, rubrika va tasdiqlangan manbalar yuboriladi. Tavsiya rubrikadagi mezon va yuborilgan harakat IDlariga bog‘lanadi, keyingi mashqni taklif qiladi hamda talaba va o‘qituvchi hisobotida ko‘rinadi. O‘qituvchi bahosi va dastlabki tizim bali o‘z qiymatlarida saqlanadi.

## Test va monitoring rejasi

- Unit: state transition, action rule, role isolation, evidence visibility, criterion computation.
- Feature: auth, role, ownership, IDOR, validation, idempotency, publish approval, report/review.
- AI: disabled/no fake reply, timeout/quota, prompt injection boundary, citation allow-list, no stray HTTP.
- Voice: unsupported/denied state, consent, transcript retention va end lifecycle; real provider accuracy alohida manual evaluation.
- Frontend: 390/768/1440 responsive, keyboard, reduced motion, WebGL failure fallback, JS/browser log.
- Unumdorlik: Apple M1 va Chromium Metal lokal sinovida 1440×1000 o‘rta sifat hamda 390×844 past sifat 3 soniyada 60 FPS, p95 kadr oralig‘i 16.7 ms. Kichik viewport shu Mac’da o‘lchandi. Android yoki iPhone qurilmasi o‘lchanmagan.
- Production: AI/voice latency/error rate, session completion, provider cost va retention cleanup; matn/audio kontenti loglanmaydi.

## Bosqichlar va haqiqiy blockerlar

1. Phase 0: ushbu audit va arxitektura.
2. Phase 1: catalog, detail, role selection, server session, dashboard integration.
3. Phase 2: 3D bundle, kamera, grafik sifat, lokal GLB modellar va fallback joriy qilindi. Har bir avatar uchun to‘liq harakat kutubxonasi qolgan.
4. Phase 3: rozilik, brauzer ovozi, server STT/TTS va transcript retention joriy qilindi. WebRTC transporti Chromium’da mahalliy audio jufti bilan tekshirildi. Jonli OpenAI sinovi uchun API krediti kerak.
5. Phase 4–6: role-isolated orchestrator, full state machine, evidence, auditli evaluation va report.
6. Phase 7–8: teacher builder, version/publish, review va admin observability.
7. Phase 9: CSP/provider allow-list, performance, backup, retention, real device/provider QA.
8. Phase 10: multiplayer faqat bir foydalanuvchili oqim productionda barqarorlashgach.

Modul “to‘liq tayyor” deb faqat haqiqiy provider bilan voice/AI E2E, litsenziyalangan 3D assetlar va Definition of Done’ning barcha bandlari real qurilmada o‘tgach aytiladi.

O‘qituvchi oldindan ko‘rish sahifasidan ssenariyning tanlangan versiyasidagi rol, dalil va bosqich o‘tishlarini sinashi mumkin. Sinov talaba sessiyasidagi tekshiruvlardan foydalanadi. U sessiya yozuvi, baho yoki tashqi provayder so‘rovini yaratmaydi. Admin xizmat holatida oxirgi 30 kundagi ovoz sessiyalari va provayder qaytargan AI tokenlari ko‘rinadi.
