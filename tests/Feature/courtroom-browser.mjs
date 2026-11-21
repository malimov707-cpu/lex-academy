import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(pathToFileURL(process.env.COURTROOM_PLAYWRIGHT_MODULE).href);
const benchmarkMode = process.env.COURTROOM_BROWSER_PROFILE === '1';
const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.COURTROOM_BROWSER_EXECUTABLE || undefined,
    args: benchmarkMode ? ['--use-angle=metal'] : ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'],
});
const base = process.env.COURTROOM_BROWSER_URL;
const scenario = process.env.COURTROOM_SCENARIO;
const artifacts = process.env.COURTROOM_BROWSER_ARTIFACTS;
const errors = [];
const measureScene = (page) => page.evaluate(async () => {
    const canvas = document.querySelector('#courtroom-canvas');
    const gl = canvas.getContext('webgl2');
    const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
    const renderer = gl.getParameter(debugInfo?.UNMASKED_RENDERER_WEBGL ?? gl.RENDERER);
    const deltas = await new Promise(resolve => {
        const frames = [];
        let first, previous;
        const measure = time => {
            first ??= time;
            if (previous !== undefined) frames.push(time - previous);
            previous = time;
            if (time - first >= 3000) resolve(frames);
            else requestAnimationFrame(measure);
        };
        requestAnimationFrame(measure);
    });
    const average = deltas.reduce((total, delta) => total + delta, 0) / deltas.length;
    const p95 = deltas.sort((a, b) => a - b)[Math.ceil(deltas.length * .95) - 1];
    return {
        viewport: `${innerWidth}×${innerHeight}`, renderer,
        quality: document.querySelector('[data-scene-status]').dataset.appliedQuality,
        frames: deltas.length, fps: Number((1000 / average).toFixed(1)), p95_ms: Number(p95.toFixed(1)),
    };
});
const login = async (page, email) => {
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/login`);
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill('browser-test-password');
    await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('form:has([name="password"]) button[type="submit"]').click()]);
};
try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'no-preference' });
    const student = await context.newPage();
    await login(student, 'student.browser@example.test');
    for (const path of ['/', '/courses', '/learn', '/learn/assignments', '/library', '/search?q=Kodeks']) {
        const response = await student.goto(`${base}${path}`, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, `Student screen failed: ${path}`);
    }
    await student.goto(`${base}/virtual-courtroom/scenarios/${scenario}`);
    await student.locator('[name="role_key"][value="judge"]').check();
    await student.locator('.courtroom-role-form button').click();
    await student.locator('.courtroom-primary-button').first().click();
    await student.locator('[data-scene-loading]').waitFor({ state: 'hidden', timeout: 45000 });
    assert.equal(await student.locator('[data-scene-fallback]').isVisible(), false, 'WebGL scene fell back unexpectedly');
    const canvas = student.locator('#courtroom-canvas');
    assert.ok(await canvas.isVisible());
    await student.locator('[data-participant="judge"][data-avatar-loaded="true"]').waitFor({ timeout: 45000 });
    await student.locator('[data-participant="defense_attorney"][data-avatar-loaded="true"]').waitFor({ timeout: 45000 });
    const judge = student.locator('[data-participant="judge"]');
    await judge.and(student.locator('[data-avatar-pose="walking"]')).waitFor();
    await judge.and(student.locator('[data-avatar-pose="seated"]')).waitFor();
    await student.locator('[data-action-type]').selectOption('make_statement');
    await student.locator('[data-courtroom-input]').fill('Sud tartibini tushuntiraman.');
    await student.locator('[data-courtroom-action-form] button[type="submit"]').click();
    await judge.and(student.locator('[data-avatar-pose="standing"]')).waitFor();
    await judge.and(student.locator('[data-avatar-pose="seated"]')).waitFor();
    await student.locator('[data-action-type]').selectOption('ask_question');
    await student.locator('[data-response-role-select]').selectOption('witness');
    await student.locator('[data-courtroom-input]').fill('Dalil bo‘yicha savol beraman.');
    await student.locator('[data-courtroom-action-form] button[type="submit"]').click();
    await judge.and(student.locator('[data-avatar-gesture="ask_question"]')).waitFor();
    await judge.and(student.locator('[data-avatar-gesture=""]')).waitFor();
    if (benchmarkMode) {
        await student.locator('[data-scene-quality]').selectOption('medium');
        const desktop = await measureScene(student);
        await student.locator('[data-scene-quality]').selectOption('low');
        await student.setViewportSize({ width: 390, height: 844 });
        const mobileViewport = await measureScene(student);
        await writeFile(`${artifacts}/courtroom-performance.json`, JSON.stringify({ desktop, mobileViewport }, null, 2));
        await student.setViewportSize({ width: 1440, height: 1000 });
    }
    await student.emulateMedia({ reducedMotion: 'reduce' });
    assert.equal(await student.locator('[data-voice-end]').count(), 1);
    await student.locator('[data-camera="judge"]').click();
    await student.locator('[data-scene-quality]').selectOption('low');
    const defendant = student.locator('[data-scene-participant="defendant"]');
    await defendant.and(student.locator('[data-avatar-loaded="true"][data-avatar-pose="seated"]')).waitFor();
    await defendant.press('Enter');
    await student.screenshot({ path: `${artifacts}/courtroom-defendant.png` });
    const boardStatus = student.locator('[data-evidence-board-status]');
    assert.match(await boardStatus.textContent(), /Test shartnomasi/);
    assert.equal(await student.locator('[data-evidence-open]').count(), 2, 'Private witness evidence must stay off the board');
    await student.locator('[data-evidence-step="1"]').click();
    assert.match(await boardStatus.textContent(), /Ikkinchi doska dalili/);
    await student.locator('[data-evidence-step="1"]').click();
    assert.match(await boardStatus.textContent(), /Test shartnomasi/);
    await student.locator('[data-evidence-step="-1"]').click();
    assert.match(await boardStatus.textContent(), /Ikkinchi doska dalili/);
    assert.equal(await student.locator('[data-evidence-open][aria-current="true"]').getAttribute('data-evidence-title'), 'Ikkinchi doska dalili');
    await student.screenshot({ path: `${artifacts}/courtroom-evidence-board.png` });
    await student.locator('[data-board-open-document]').click();
    await student.locator('[data-evidence-dialog] [data-dialog-body]').getByText('Ikkinchi hujjatning to‘liq matni.').waitFor();
    await student.locator('[data-evidence-dialog] form[method="dialog"] button').click();
    const canvasBounds = await canvas.boundingBox();
    await canvas.click({ position: { x: canvasBounds.width / 2, y: canvasBounds.height / 2 } });
    await student.locator('[data-evidence-dialog] [data-dialog-title]').getByText('Ikkinchi doska dalili').waitFor();
    await student.locator('[data-evidence-dialog] form[method="dialog"] button').click();
    await student.locator('[data-camera="wide"]').click();
    await student.screenshot({ path: `${artifacts}/courtroom-desktop.png` });
    await student.setViewportSize({ width: 768, height: 1024 });
    assert.ok(await student.locator('[data-camera="wide"]').isVisible());
    await student.setViewportSize({ width: 390, height: 844 });
    await student.locator('[data-panel-open]').click();
    assert.ok(await student.locator('.courtroom-left-panel.is-open').isVisible());
    await student.locator('[data-panel-close]').click();
    await student.screenshot({ path: `${artifacts}/courtroom-mobile.png` });
    const textOnly = await context.newPage();
    await textOnly.bringToFront();
    await textOnly.addInitScript(() => {
        navigator.mediaDevices.getUserMedia = async () => {
            const context = new AudioContext();
            const output = context.createMediaStreamDestination();
            const oscillator = context.createOscillator();
            oscillator.connect(output);
            oscillator.start();
            const track = output.stream.getAudioTracks()[0];
            const stop = track.stop.bind(track);
            track.stop = () => {
                stop();
                if (context.state !== 'closed') context.close().catch(() => {});
            };
            return output.stream;
        };
    });
    await textOnly.route(/courtroom-scene-.*\.js$/, route => route.abort());
    await textOnly.goto(student.url());
    await textOnly.locator('[data-scene-fallback]').waitFor({ state: 'visible' });
    assert.equal(await textOnly.locator('[data-courtroom-session]').getAttribute('data-avatar-entrance'), 'false');
    assert.ok(await textOnly.locator('[data-courtroom-input]').isVisible());
    let uploadedAudio = false;
    let realtimeAttempts = 0;
    await textOnly.route(/\/voice\/\d+\/realtime$/, async route => {
        if (++realtimeAttempts > 1) {
            await route.fulfill({ status: 503, json: { error: 'Jonli ovozga ulanib bo‘lmadi.' } });
            return;
        }
        const { sdp } = route.request().postDataJSON();
        const answer = await textOnly.evaluate(async offer => {
            const peer = window.courtroomTestPeer = new RTCPeerConnection();
            peer.addEventListener('datachannel', ({ channel }) => {
                channel.addEventListener('message', ({ data }) => {
                    if (JSON.parse(data).type !== 'input_audio_buffer.commit') return;
                    const item_id = 'test-turn';
                    channel.send(JSON.stringify({ type: 'input_audio_buffer.committed', item_id }));
                    channel.send(JSON.stringify({ type: 'conversation.item.input_audio_transcription.completed', item_id, transcript: 'Jonli nutq sinovi.' }));
                });
            });
            await peer.setRemoteDescription({ type: 'offer', sdp: offer });
            await peer.setLocalDescription(await peer.createAnswer());
            if (peer.iceGatheringState !== 'complete') {
                await new Promise(resolve => {
                    const done = () => {
                        if (peer.iceGatheringState !== 'complete') return;
                        peer.removeEventListener('icegatheringstatechange', done);
                        resolve();
                    };
                    peer.addEventListener('icegatheringstatechange', done);
                    done();
                });
            }
            return peer.localDescription.sdp;
        }, sdp);
        await route.fulfill({ json: { sdp: answer } });
    });
    await textOnly.route(/\/voice\/\d+\/transcribe$/, async route => {
        uploadedAudio = route.request().headers()['content-type']?.includes('multipart/form-data')
            && route.request().postDataBuffer()?.includes(Buffer.from('name="audio"'));
        await route.fulfill({ status: 503, json: { error: 'Ovoz xizmati vaqtincha ishlamayapti.' } });
    });
    const microphone = textOnly.locator('[data-voice-start]');
    const consent = textOnly.locator('[data-voice-consent]');
    const recipient = textOnly.locator('[data-response-role-select]');
    assert.equal(await recipient.isVisible(), false);
    await textOnly.locator('[data-action-type]').selectOption('ask_question');
    assert.equal(await recipient.locator('option[value="prosecutor"]').isDisabled(), true);
    await recipient.selectOption('witness');
    await microphone.click();
    await consent.waitFor({ state: 'visible' });
    assert.equal(await consent.locator('[data-transcript-consent]').isChecked(), false);
    await consent.locator('[data-voice-consent-confirm]').click();
    await textOnly.locator('[data-voice-state][data-state="LISTENING"]').waitFor();
    const recording = await textOnly.evaluate(async () => {
        const started = performance.now();
        const stats = await window.courtroomTestPeer.getStats();
        const packets = [...stats.values()].find(stat => stat.type === 'inbound-rtp' && stat.kind === 'audio')?.packetsReceived ?? 0;
        return { started, packets };
    });
    const audioArrived = await textOnly.evaluate(async ({ started, packets }) => {
        const deadline = performance.now() + 8000;
        while (performance.now() < deadline) {
            const stats = await window.courtroomTestPeer.getStats();
            if (performance.now() - started >= 300 && [...stats.values()].some(stat =>
                stat.type === 'inbound-rtp' && stat.kind === 'audio' && stat.packetsReceived >= packets + 20)) return true;
            await new Promise(resolve => setTimeout(resolve, 75));
        }
        return false;
    }, recording);
    assert.ok(audioArrived, 'The receiving peer should receive microphone RTP packets before the student ends the turn');
    const pcm = Buffer.alloc(16000 * 2 * 1.25);
    const wavHeader = Buffer.from('524946460000000057415645666d74201000000001000100803e0000007d0000020010006461746100000000', 'hex');
    wavHeader.writeUInt32LE(36 + pcm.length, 4);
    wavHeader.writeUInt32LE(pcm.length, 40);
    await textOnly.route(/\/speech\/73$/, route => route.fulfill({ json: {
        mime: 'audio/wav', audio_base64: Buffer.concat([wavHeader, pcm]).toString('base64'),
        visemes: [{ start: 0, end: .15, shape: 'sil' }, { start: .15, end: .55, shape: 'PP' },
            { start: .55, end: .9, shape: 'O' }, { start: .9, end: 1.25, shape: 'sil' }],
    } }));
    await textOnly.route(/\/sessions\/\d+\/actions$/, async route => {
        const response = await route.fetch();
        const data = await response.json();
        data.ai = { role_key: 'witness', role_name: 'Guvoh', content: 'Ovoz va lab sinovi.', turn_id: 73 };
        await route.fulfill({ response, json: data });
    }, { times: 1 });
    await textOnly.evaluate(() => {
        window.courtroomPlaybackShapes = new Set();
        window.addEventListener('courtroom-audio', event => {
            if (event.detail.alignment === 'timed') window.courtroomPlaybackShapes.add(event.detail.shape);
        });
    });
    const [questionRequest] = await Promise.all([
        textOnly.waitForRequest(request => request.url().endsWith('/actions') && request.postDataJSON()?.input_type === 'voice'),
        textOnly.locator('[data-transcript-log]').getByText('Jonli nutq sinovi.').waitFor(),
        microphone.click(),
    ]).catch(async error => {
        const voice = await textOnly.locator('[data-voice-state]').textContent();
        const action = await textOnly.locator('[data-action-status]').textContent();
        throw new Error(`Transcript kelmadi. Ovoz holati: ${voice}. Harakat: ${action}. Brauzer xatolari: ${errors.join(', ')}`, { cause: error });
    });
    assert.equal(questionRequest.postDataJSON().target_role_key, 'witness');
    await textOnly.waitForFunction(() => ['sil', 'PP', 'O'].every(shape => window.courtroomPlaybackShapes.has(shape)));
    await textOnly.locator('[data-voice-state][data-state="IDLE"]').waitFor();
    await textOnly.locator('[data-voice-end]').click();
    await microphone.click();
    await consent.locator('[data-voice-consent-confirm]').click();
    await textOnly.locator('[data-voice-state]').getByText(/Jonli ovozga ulanib bo‘lmadi/).waitFor();
    await microphone.click();
    await textOnly.locator('[data-voice-state][data-state="LISTENING"]').waitFor();
    await microphone.click();
    await textOnly.locator('[data-voice-state]').getByText('Ovoz xizmati vaqtincha ishlamayapti.').waitFor();
    assert.ok(uploadedAudio, 'Browser sent a microphone recording to the transcription endpoint');
    await textOnly.locator('[data-voice-end]').click();
    await textOnly.locator('[data-action-type]').selectOption('make_statement');
    assert.equal(await recipient.isVisible(), false);
    await textOnly.route(/\/sessions\/\d+\/actions$/, async route => {
        const response = await route.fetch();
        const data = await response.json();
        data.ai = {
            role_key: 'prosecutor', role_name: 'Da’vogar vakili',
            content: `Sinovdagi javob REF-${process.env.COURTROOM_REFERENCE_ID} manbasiga tayanadi.`,
        };
        await route.fulfill({ response, json: data });
    }, { times: 1 });
    await textOnly.locator('[data-courtroom-input]').fill('Sud jarayonini boshlashga tayyorman.');
    await textOnly.locator('[data-courtroom-action-form] button[type="submit"]').click();
    await textOnly.locator('[data-transcript-log]').getByText('Sud jarayonini boshlashga tayyorman.').waitFor();
    const citedReference = textOnly.locator('[data-transcript-log] .courtroom-transcript-reference');
    const citedLink = citedReference.getByRole('link', { name: /Brauzer sinovidagi huquqiy manba/ });
    assert.equal(await citedLink.getAttribute('href'), 'https://example.test/courtroom-reference');
    await citedReference.getByText('Sinovdagi norma va tahrir izohi.').waitFor();
    await citedReference.getByText(/Tekshirilgan sana/).waitFor();
    await textOnly.locator('[data-action-type]').selectOption('advance_stage');
    await textOnly.locator('[data-courtroom-action-form] button[type="submit"]').click();
    await textOnly.locator('[data-current-stage]').getByText('Dalillar', { exact: true }).waitFor();
    await textOnly.locator('[data-evidence-open]').first().click();
    const evidenceDialog = textOnly.locator('[data-evidence-dialog]');
    const evidenceBody = evidenceDialog.locator('[data-dialog-body]');
    await evidenceBody.getByText('O‘quv hujjati matni.').waitFor();
    const selectExcerpt = () => evidenceBody.evaluate(element => {
        const range = document.createRange();
        range.setStart(element.firstChild, 0);
        range.setEnd(element.firstChild, 5);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
    });
    await selectExcerpt();
    await evidenceDialog.locator('[data-annotation-selection]').getByText('O‘quv').waitFor();
    await evidenceDialog.getByLabel('Shaxsiy izoh').fill('Qabul hujjatini tekshiring.');
    await evidenceDialog.getByRole('button', { name: 'Qaydni saqlash' }).click();
    await evidenceDialog.getByText('Qayd saqlandi.').waitFor();
    assert.equal(await evidenceBody.locator('mark').textContent(), 'O‘quv');
    await textOnly.keyboard.press('Escape');
    await textOnly.reload();
    await textOnly.locator('[data-evidence-open]').first().click();
    await evidenceDialog.locator('[data-evidence-annotations]').getByText('Qabul hujjatini tekshiring.').waitFor();
    assert.equal(await evidenceBody.locator('mark').textContent(), 'O‘quv');
    await evidenceDialog.getByRole('button', { name: 'Qaydni o‘chirish' }).click();
    await evidenceDialog.getByText('Qayd o‘chirildi.').waitFor();
    assert.equal(await evidenceBody.locator('mark').count(), 0);
    await textOnly.keyboard.press('Escape');
    const argument = textOnly.locator('[data-courtroom-input]');
    const exhibit = textOnly.locator('[data-evidence-open]').first();
    await argument.fill('Qabul qilish sanasini tekshirish kerak.');
    await exhibit.click();
    await selectExcerpt();
    await evidenceDialog.getByRole('button', { name: 'Javobimga iqtibos qo‘shish' }).click();
    assert.equal(await argument.inputValue(), `Qabul qilish sanasini tekshirish kerak.\n“O‘quv” (${await exhibit.getAttribute('data-evidence-title')})`);
    assert.equal(await textOnly.locator('[data-evidence-select]').inputValue(), await exhibit.getAttribute('data-evidence-id'));
    await argument.fill('');
    await textOnly.locator('[data-action-type]').selectOption('advance_stage');
    await textOnly.locator('[data-courtroom-action-form] button[type="submit"]').click();
    await textOnly.locator('.courtroom-finish button').click();
    await textOnly.locator('.courtroom-score-ring').waitFor();
    const teacherContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const teacher = await teacherContext.newPage();
    await login(teacher, 'teacher.browser@example.test');
    for (const path of ['/teacher', '/admin/courses']) {
        const response = await teacher.goto(`${base}${path}`, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, `Teacher screen failed: ${path}`);
    }
    await teacher.goto(`${base}/admin/virtual-courtroom`);
    await teacher.setViewportSize({ width: 390, height: 844 });
    const mobileLayout = await teacher.locator('.edu-workspace').evaluate(workspace => ({
        viewport: document.documentElement.clientWidth,
        document: document.documentElement.scrollWidth,
        workspace: workspace.clientWidth,
    }));
    assert.equal(mobileLayout.document, mobileLayout.viewport, 'Courtroom administration must fit a phone viewport');
    assert.equal(mobileLayout.workspace, mobileLayout.viewport);
    await teacher.setViewportSize({ width: 1440, height: 1000 });
    await teacher.getByRole('link', { name: /Tekshirish/ }).first().click();
    const markFields = teacher.locator('input[name^="criterion_overrides"]');
    assert.ok(await markFields.count() > 0);
    assert.equal(await markFields.first().inputValue(), '');
    await teacher.locator('#courtroom-review-form [name="comment"]').fill('Brauzer sinovida talaba natijasi ko‘rib chiqildi.');
    await teacher.locator('#courtroom-review-form button').click();
    await teacher.getByText('Brauzer sinovida talaba natijasi ko‘rib chiqildi.').waitFor();
    await teacher.getByRole('link', { name: 'Sinov darsi natijalari' }).click();
    await teacher.locator('#pilot-role').selectOption('defense_attorney');
    await teacher.getByRole('button', { name: 'Rolni tanlash' }).click();
    await teacher.waitForURL(url => url.searchParams.get('role') === 'defense_attorney');
    assert.equal(await teacher.locator('#pilot-role').inputValue(), 'defense_attorney');
    await teacher.goto(`${base}/admin/virtual-courtroom/scenarios/${scenario}/definition`);
    const count = await teacher.locator('[data-role-key]').count();
    await teacher.locator('[data-add-row="role-template"]').click();
    assert.equal(await teacher.locator('[data-role-key]').count(), count + 1);
    await teacher.locator('[data-repeater="roles"] [data-remove-row]').last().click();
    assert.equal(await teacher.locator('[data-role-key]').count(), count);
    await teacher.locator('[name="roles[0][instructions]"]').fill('Brauzerda tahrirlangan rol yo‘riqnomasi.');
    await teacher.locator('[data-courtroom-builder] > button[type="submit"], [data-courtroom-builder] > button:not([type])').click();
    await teacher.waitForLoadState('networkidle');
    assert.equal(await teacher.locator('[name="roles[0][instructions]"]').inputValue(), 'Brauzerda tahrirlangan rol yo‘riqnomasi.');
    await teacher.screenshot({ path: `${artifacts}/courtroom-builder.png` });
    await teacher.goto(`${base}/admin/virtual-courtroom/scenarios/shartnoma-majburiyati-boyicha-oquv-sudi/preview`);
    const source = teacher.locator('.courtroom-reference-list li').filter({ hasText: 'Fuqarolik kodeksi (birinchi qism)' });
    const citation = 'Brauzer sinovi uchun norma va tahrir izohi.';
    await source.getByLabel('Modda va amaldagi tahrir izohi').fill(citation);
    await source.getByRole('checkbox').check();
    await source.getByRole('button', { name: 'Manbani tasdiqlash' }).click();
    await source.getByText(citation).waitFor();
    await source.getByText(/Tekshirilgan sana/).waitFor();
    assert.equal(await source.getByRole('button', { name: 'Manbani tasdiqlash' }).count(), 0);
    await teacher.getByRole('link', { name: 'Kutubxonadan manba biriktirish' }).click();
    await teacher.getByLabel('Manba nomi').fill('Brauzer kutubxonasidagi norma');
    await teacher.getByRole('button', { name: 'Qidirish' }).click();
    await teacher.getByRole('button', { name: 'Qoralamaga biriktirish' }).click();
    const attachedSource = teacher.locator('.courtroom-reference-list li').filter({ hasText: 'Brauzer kutubxonasidagi norma' });
    await attachedSource.getByRole('button', { name: 'Manbani tasdiqlash' }).waitFor();
    await teacher.getByRole('link', { name: 'Ssenariy qoidalarini sinash' }).click();
    await teacher.locator('[name="action_type"]').selectOption('advance_stage');
    await teacher.getByRole('button', { name: 'Harakatni tekshirish' }).click();
    const rehearsalResult = teacher.getByRole('region', { name: 'Sinov natijasi' });
    await rehearsalResult.getByText('Harakat qabul qilindi.').waitFor();
    await rehearsalResult.getByRole('link', { name: /Sud majlisini ochish/ }).click();
    assert.match(await rehearsalResult.textContent(), /Sud majlisini ochish/);
    assert.deepEqual(errors, [], 'Browser JavaScript errors');
    console.log(`Browser QA passed. Screenshots: ${artifacts}`);
} finally {
    await browser.close();
}
