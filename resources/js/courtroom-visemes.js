const pairs = {
    sh: 'CH', ch: 'CH', ng: 'nn',
    yo: 'O', yu: 'U', ya: 'aa', ye: 'E',
};
const letters = {
    a: 'aa', а: 'aa', я: 'aa',
    e: 'E', е: 'E', э: 'E', ɛ: 'E',
    i: 'I', и: 'I', ы: 'I', й: 'I', y: 'I',
    o: 'O', о: 'O', ё: 'O', ў: 'O',
    u: 'U', у: 'U', ю: 'U', w: 'U',
    p: 'PP', b: 'PP', m: 'PP', п: 'PP', б: 'PP', м: 'PP',
    f: 'FF', v: 'FF', ф: 'FF', в: 'FF',
    k: 'kk', g: 'kk', q: 'kk', x: 'kk', h: 'kk',
    к: 'kk', г: 'kk', қ: 'kk', ғ: 'kk', х: 'kk', ҳ: 'kk',
    t: 'DD', d: 'DD', l: 'DD', т: 'DD', д: 'DD', л: 'DD',
    j: 'CH', ж: 'CH', ш: 'CH', щ: 'CH', ч: 'CH',
    c: 'SS', s: 'SS', z: 'SS', ц: 'SS', с: 'SS', з: 'SS',
    n: 'nn', н: 'nn', r: 'RR', р: 'RR',
};
const vowels = new Set(['aa', 'E', 'I', 'O', 'U']);

export function visemeTimeline(text) {
    const characters = Array.from(text.toLowerCase().normalize('NFC'));
    const cues = [];
    let weight = 0;

    for (let index = 0; index < characters.length; index++) {
        const letter = characters[index];
        if (/[ʻʼ‘’'`ъь]/u.test(letter)) continue;

        const combination = letter + (characters[index + 1] ?? '');
        const pair = combination === 'ng' && /[ʻʼ‘’'`]/u.test(characters[index + 2] ?? '') ? null : pairs[combination];
        if (pair) index++;
        const shape = pair ?? letters[letter] ?? 'sil';
        if (shape === 'sil' && ! /[\s.,;:!?—]/u.test(letter)) continue;

        // Actual audio energy gates these text-derived cues during playback.
        weight += vowels.has(shape) ? 2 : /[.!?]/u.test(letter) ? 2.5 : 1;
        cues.push({ shape, end: weight });
    }

    return { cues, weight };
}

export function visemeAt({ cues, weight }, elapsedSeconds, durationSeconds) {
    if (!Number.isFinite(durationSeconds) || durationSeconds <= 0 || elapsedSeconds >= durationSeconds) return 'sil';
    const position = Math.max(0, elapsedSeconds / durationSeconds) * weight;
    let first = 0;
    let last = cues.length;

    while (first < last) {
        const middle = (first + last) >>> 1;
        if (cues[middle].end <= position) first = middle + 1;
        else last = middle;
    }

    return cues[first]?.shape ?? 'sil';
}

export function timedVisemeAt(spans, elapsedSeconds) {
    if (!Number.isFinite(elapsedSeconds) || elapsedSeconds < 0) return 'sil';
    let first = 0;
    let last = spans.length;
    while (first < last) {
        const middle = (first + last) >>> 1;
        if (spans[middle].end <= elapsedSeconds) first = middle + 1;
        else last = middle;
    }

    const span = spans[first];
    return span && elapsedSeconds >= span.start ? span.shape : 'sil';
}
