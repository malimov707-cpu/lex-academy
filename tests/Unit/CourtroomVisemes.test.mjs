import assert from 'node:assert/strict';
import test from 'node:test';
import { timedVisemeAt, visemeAt, visemeTimeline } from '../../resources/js/courtroom-visemes.js';

test('maps Uzbek digraphs and Cyrillic vowels to the rig shapes used during playback', () => {
    assert.deepEqual(visemeTimeline('Shoʻng‘ish').cues.map(cue => cue.shape), ['CH', 'O', 'nn', 'kk', 'I', 'CH']);
    assert.deepEqual(visemeTimeline('Мўъжиза').cues.map(cue => cue.shape), ['PP', 'O', 'CH', 'I', 'SS', 'aa']);
    assert.deepEqual(visemeTimeline('Юрист').cues.map(cue => cue.shape), ['U', 'RR', 'I', 'SS', 'DD']);
});

test('chooses mouth shapes from audio progress and closes the mouth at playback end', () => {
    const timeline = visemeTimeline('Она.');
    assert.equal(visemeAt(timeline, 0, 3), 'O');
    assert.equal(visemeAt(timeline, 1, 3), 'nn');
    assert.equal(visemeAt(timeline, 3, 3), 'sil');
    assert.equal(visemeAt(timeline, 0, NaN), 'sil');
});

test('uses provider audio time directly, including gaps and seek positions', () => {
    const spans = [{ start: .2, end: .41, shape: 'PP' }, { start: .41, end: .8, shape: 'O' }, { start: 1.05, end: 1.2, shape: 'I' }];
    assert.equal(timedVisemeAt(spans, 1.17), 'I');
    assert.equal(timedVisemeAt(spans, .2), 'PP');
    assert.equal(timedVisemeAt(spans, .41), 'O');
    for (const time of [0, .8, 1, 1.2, NaN]) assert.equal(timedVisemeAt(spans, time), 'sil');
});
