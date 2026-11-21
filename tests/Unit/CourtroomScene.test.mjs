import test from 'node:test';
import assert from 'node:assert/strict';
import { NullEngine } from '@babylonjs/core/Engines/nullEngine.js';
import { Quaternion, Vector3 } from '@babylonjs/core/Maths/math.vector.js';
import { TransformNode } from '@babylonjs/core/Meshes/transformNode.js';
import { createAvatarMotion, createNaturalMotion, gazeYaw } from '../../resources/js/courtroom-avatar-motion.js';
import { createCourtroom } from '../../resources/js/courtroom-scene.js';

test('courtroom contains the scenario participants and disposes its meshes', () => {
    const engine = new NullEngine();
    try {
        const roles = ['judge', 'prosecutor', 'defense_attorney', 'witness'];
        const { scene, camera, avatars } = createCourtroom(engine, roles);
        assert.deepEqual([...avatars.keys()], roles);
        assert.equal(scene.activeCamera, camera);
        for (const name of ['judge-bench', 'evidence-screen', 'court-door', 'floor']) {
            assert.ok(scene.getMeshByName(name), `Missing courtroom object: ${name}`);
        }
        assert.ok(scene.materials.some(material => material.getClassName() === 'PBRMaterial'));
        scene.dispose();
        assert.equal(scene.meshes.length, 0);
        assert.equal(engine.scenes.length, 0);
    } finally {
        engine.dispose();
    }
});

test('courtroom does not create participants absent from the scenario', () => {
    const engine = new NullEngine();
    try {
        const { scene, avatars } = createCourtroom(engine, ['judge']);
        assert.equal(avatars.size, 1);
        assert.equal(scene.getTransformNodeByName('participant-witness'), null);
        scene.dispose();
    } finally {
        engine.dispose();
    }
});

test('parties have their own seats beside their representatives and the evidence screen faces the room', () => {
    const engine = new NullEngine();
    try {
        const { scene, avatars, evidenceScreen } = createCourtroom(engine, ['prosecutor', 'defense_attorney', 'defendant', 'claimant']);
        assert.ok(avatars.get('defendant').position.x > avatars.get('defense_attorney').position.x);
        assert.ok(avatars.get('claimant').position.x < avatars.get('prosecutor').position.x);
        for (const role of ['defendant', 'claimant']) {
            const chair = scene.getMeshByName(`${role}-chair-seat`);
            assert.ok(chair, `${role} needs a separate seat`);
            assert.equal(chair.position.x, avatars.get(role).position.x);
            assert.equal(chair.position.z, avatars.get(role).position.z);
        }
        evidenceScreen.computeWorldMatrix(true);
        assert.ok(evidenceScreen.getDirection(new Vector3(0, 0, -1)).x < -.9, 'the document must face the centre of the courtroom');
        scene.dispose();
    } finally {
        engine.dispose();
    }
});

test('seated avatar bends its knees forward and keeps its feet on the floor', () => {
    const engine = new NullEngine();
    try {
        const { scene } = createCourtroom(engine, []);
        const character = new TransformNode('test-character', scene);
        const joints = new Map();
        for (const [side, x] of [['left', -.16], ['right', .16]]) {
            let parent = character;
            for (const [name, position] of [
                ['upleg', [x, .95, 0]], ['leg', [0, -.43, 0]],
                ['foot', [0, -.43, 0]], ['toebase', [0, -.04, .16]],
            ]) {
                const joint = new TransformNode(`${side}${name}`, scene);
                joint.parent = parent;
                joint.position.set(...position);
                joint.rotationQuaternion = Quaternion.Identity();
                joints.set(joint.name, joint);
                parent = joint;
            }
        }
        const motion = createAvatarMotion(character, name => joints.get(name), 'judge', null);
        const position = name => joints.get(name).computeWorldMatrix(true).getTranslation();
        assert.equal(motion({ time: 0, standing: false, reducedMotion: false }), 'seated');
        const seatedFoot = position('leftfoot');
        assert.ok(position('leftleg').z > .35, 'seated knee should point toward the desk');
        assert.ok(Math.abs(seatedFoot.y - .09) < .01, 'sitting should keep the foot at its standing height');
        assert.equal(motion({ time: 16, standing: true, reducedMotion: true }), 'standing');
        assert.ok(Math.abs(position('leftfoot').y - seatedFoot.y) < .01);
        assert.ok(position('leftfoot').z > seatedFoot.z && position('leftfoot').z - seatedFoot.z < .2, 'standing should move a short step clear of the chair');
    } finally {
        engine.dispose();
    }
});

test('different body proportions sit above the cushion with their feet planted', () => {
    const engine = new NullEngine();
    try {
        for (const role of ['defendant', 'judge']) {
            for (const hipHeight of [.88, 1.04]) {
                const { scene, avatarRigs } = createCourtroom(engine, [role]);
                const character = new TransformNode('seated-character', scene);
                character.parent = avatarRigs.get(role).node;
                character.rotation.y = role === 'defendant' ? Math.PI : 0;
                const joints = new Map();
                for (const [side, x] of [['left', -.16], ['right', .16]]) {
                    let parent = character;
                    for (const [name, position] of [
                        ['upleg', [x, hipHeight, 0]], ['leg', [0, .52 - hipHeight, 0]],
                        ['foot', [0, -.43, 0]], ['toebase', [0, -.04, .16]],
                    ]) {
                        const joint = new TransformNode(`${side}${name}`, scene);
                        joint.parent = parent;
                        joint.position.set(...position);
                        joint.rotationQuaternion = Quaternion.Identity();
                        joints.set(joint.name, joint);
                        parent = joint;
                    }
                }
                const position = name => joints.get(name).computeWorldMatrix(true).getTranslation();
                const motion = createAvatarMotion(character, name => joints.get(name), role, null);
                motion({ time: 0, standing: true, reducedMotion: true });
                const standingFoot = position('leftfoot').clone();
                assert.equal(motion({ time: 1000, standing: false, reducedMotion: true }), 'seated');
                const seat = scene.getMeshByName(`${role}-chair-seat`);
                seat.computeWorldMatrix(true);
                const seatTop = seat.getBoundingInfo().boundingBox.maximumWorld.y;
                const clearance = position('leftupleg').y - seatTop;
                assert.ok(clearance > .07 && clearance < .14, 'the pelvis must stay above the cushion rather than inside it or floating above it');
                assert.ok(Math.abs(position('leftfoot').y - standingFoot.y) < .01, 'seating must preserve foot contact with the floor');
                assert.ok(position('leftleg').y > seatTop + .04, 'the knee must clear the front of the cushion');
                motion({ time: 2000, standing: true, reducedMotion: true });
                assert.ok(Vector3.Distance(position('leftfoot'), standingFoot) < .01, 'standing must restore the original foot position');
                const shin = position('leftfoot').add(new Vector3(0, .30, 0));
                assert.ok(!seat.getBoundingInfo().boundingBox.intersectsPoint(shin), 'standing legs must clear the seat cushion');
                scene.dispose();
            }
        }
    } finally {
        engine.dispose();
    }
});

test('the witness and clerk have clear body space behind their furniture', () => {
    const engine = new NullEngine();
    try {
        const { scene, avatars } = createCourtroom(engine, ['witness', 'court_clerk']);
        const stand = scene.meshes.filter(mesh => mesh.name.startsWith('witness-stand'));
        assert.ok(stand.length >= 3, 'the stand needs walls around an open interior');
        const furniture = [...stand, scene.getMeshByName('clerk-desk')];
        for (const role of ['witness', 'court_clerk']) {
            const origin = avatars.get(role).position;
            for (const x of [-.22, 0, .22]) {
                for (const z of [-.18, 0, .18]) {
                    for (const mesh of furniture) {
                        mesh.computeWorldMatrix(true);
                        const bounds = mesh.getBoundingInfo().boundingBox;
                        const point = origin.add(new Vector3(x, .95, z));
                        assert.ok(!bounds.intersectsPoint(point), `${role}'s body must not be inside ${mesh.name}`);
                    }
                }
            }
        }
        scene.dispose();
    } finally {
        engine.dispose();
    }
});

test('the witness enters through the open back of the stand rather than its side wall', () => {
    const engine = new NullEngine();
    try {
        const { scene, avatarRigs } = createCourtroom(engine, ['witness']);
        const character = new TransformNode('walking-witness', scene);
        character.parent = avatarRigs.get('witness').node;
        const joints = new Map();
        for (const [side, x] of [['left', -.16], ['right', .16]]) {
            let parent = character;
            for (const [name, position] of [
                ['upleg', [x, .95, 0]], ['leg', [0, -.43, 0]],
                ['foot', [0, -.43, 0]], ['toebase', [0, -.04, .16]],
            ]) {
                const joint = new TransformNode(`${side}${name}`, scene);
                joint.parent = parent;
                joint.position.set(...position);
                joint.rotationQuaternion = Quaternion.Identity();
                joints.set(joint.name, joint);
                parent = joint;
            }
        }
        const motion = createAvatarMotion(character, name => joints.get(name), 'witness', 0);
        for (let time = 0; time <= 2000; time += 100) {
            motion({ time, standing: true, reducedMotion: false });
            assert.equal(character.position.x, 0, 'the entrance must stay between the side walls');
            assert.ok(character.position.z <= 0, 'the witness must approach from the open back');
            for (const mesh of scene.meshes.filter(mesh => mesh.name.startsWith('witness-stand'))) {
                mesh.computeWorldMatrix(true);
                const point = character.computeWorldMatrix(true).getTranslation().add(new Vector3(0, .95, 0));
                assert.ok(!mesh.getBoundingInfo().boundingBox.intersectsPoint(point), 'the entrance path must clear the stand');
            }
        }
        assert.ok(Math.abs(character.position.z) < .001, 'the witness must finish at the assigned position');
        scene.dispose();
    } finally {
        engine.dispose();
    }
});
test('looks toward the talker from either side of the courtroom within a natural range', () => {
    const seat = { x: 0, z: 0 };
    assert.equal(gazeYaw(0, seat, { x: 12, z: 1 }), .68);
    assert.equal(gazeYaw(0, seat, { x: -12, z: 1 }), -.68);
    assert.equal(gazeYaw(Math.PI, seat, { x: 0, z: -1 }), 0);
    assert.ok(gazeYaw(Math.PI, seat, { x: 1, z: -2 }) < 0);
    assert.equal(gazeYaw(0, seat, seat), 0);
    assert.equal(gazeYaw(0, seat, null), 0);
});

test('idle participants breathe and blink at different rhythms without waving constantly', () => {
    const judge = createNaturalMotion('judge');
    const witness = createNaturalMotion('witness');
    const judgeBlinks = [];
    const witnessBlinks = [];
    const breaths = [];
    for (let time = 0; time <= 16000; time += 40) {
        const left = judge(time);
        const right = witness(time);
        if (left.blink > .5) judgeBlinks.push(time);
        if (right.blink > .5) witnessBlinks.push(time);
        breaths.push(left.spinePitch);
        assert.ok(left.rightLift < .025 && left.leftLift < .025, 'quiet hands should stay close to their resting pose');
        assert.ok(Object.values(left).every(Number.isFinite));
    }
    assert.ok(judgeBlinks.length && witnessBlinks.length, 'both participants should blink');
    assert.notDeepEqual(judgeBlinks, witnessBlinks, 'participants must not blink together on a shared loop');
    assert.ok(Math.max(...breaths) - Math.min(...breaths) > .01, 'breathing must change the chest pose');
});

test('speech adds gentle hand emphasis and fades back to rest after the speaker stops', () => {
    const sample = createNaturalMotion('prosecutor');
    let emphasis = 0;
    for (let time = 0; time < 12000; time += 40) {
        const pose = sample(time, { speaking: true, level: .16 });
        emphasis = Math.max(emphasis, pose.rightLift, pose.leftLift);
        assert.ok(Math.abs(pose.headPitch) < .15 && Math.abs(pose.spineRoll) < .1);
    }
    assert.ok(emphasis > .05, 'speaking should produce visible hand emphasis');
    let resting;
    for (let time = 12000; time < 16000; time += 40) resting = sample(time);
    assert.ok(resting.rightLift < .025 && resting.leftLift < .025, 'hand emphasis must fade when speech ends');
});

test('reduced motion immediately stops idle gestures facial expressions and eye movement', () => {
    const sample = createNaturalMotion('defense_attorney');
    for (let time = 0; time <= 5000; time += 40) sample(time, { speaking: true, level: .2, listening: true });
    const pose = sample(5040, { speaking: true, level: .2, listening: true, reducedMotion: true });
    assert.ok(Object.values(pose).every(value => value === 0));
});
