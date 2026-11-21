import { Matrix, Quaternion, Vector3 } from '@babylonjs/core/Maths/math.vector.js';

const sittingTime = 520;
export const courtroomSeating = { seatHeight: .44, cushionThickness: .12, pelvisClearance: .10 };
export const isRoleSeated = role => {
    if (!role) return false;
    if (role.startsWith('observer_')) return true;
    return ['judge', 'prosecutor', 'defense_attorney', 'defense_advocate', 'victim_advocate', 'victim_attorney', 'victim', 'defendant', 'claimant', 'court_clerk', 'witness_2', 'witness_3'].includes(role);
};
const entrances = {
    judge: -2.1,
    prosecutor: 1.8,
    victim_advocate: 1.8,
    defense_advocate: -1.8,
    defense_attorney: -1.8,
    victim: 2.0,
    defendant: -2.0,
    witness: -1.7,
    witness_1: -1.7,
    witness_2: -1.7,
    witness_3: -1.7,
};
const clamp = value => Math.max(0, Math.min(1, value));
const smooth = value => value * value * (3 - 2 * value);
export const courtroomGestures = {
    present_evidence: { duration: 1450, lift: .16, reach: .33 },
    make_objection: { duration: 1200, lift: .47, reach: .12 },
    ask_question: { duration: 1000, lift: .19, reach: .18 },
    request_clarification: { duration: 1000, lift: .19, reach: .18 },
    thinking: { duration: 1600, lift: .32, reach: .08 },
    reacting: { duration: 1100, lift: .22, reach: .15 },
    hand_gestures: { duration: 1300, lift: .25, reach: .22 },
    make_statement: { duration: 1300, lift: .22, reach: .20 },
    answer_question: { duration: 1200, lift: .18, reach: .16 },
    closing_statement: { duration: 1500, lift: .30, reach: .25 },
    rebuttal: { duration: 1300, lift: .35, reach: .18 },
    procedural_ruling: { duration: 1400, lift: .40, reach: .20 },
    final_decision: { duration: 1600, lift: .45, reach: .25 },
};

export function gazeYaw(heading, from, to) {
    if (!to) return 0;
    const x = to.x - from.x;
    const z = to.z - from.z;
    if (Math.hypot(x, z) < .2) return 0;
    const yaw = Math.atan2(x, z) - heading;
    return Math.max(-.68, Math.min(.68, Math.atan2(Math.sin(yaw), Math.cos(yaw))));
}

export function createNaturalMotion(role) {
    const seed = [...role].reduce((value, letter) => (value * 31 + letter.charCodeAt(0)) >>> 0, 7);
    const noise = value => {
        const wave = Math.sin(seed * .001 + value * 12.9898) * 43758.5453;
        return wave - Math.floor(wave);
    };
    const phase = noise(1) * 27;
    let previousTime = null;
    let nextBlink = null;
    let blinkCount = 0;
    let activity = 0;
    let eyeYaw = 0;

    return (time, { speaking = false, level = 0, listening = false, reducedMotion = false } = {}) => {
        const delta = previousTime === null ? 0 : Math.max(0, Math.min(100, time - previousTime));
        previousTime = time;
        const t = time / 1000 + phase;
        const goal = speaking ? Math.min(1, .35 + Math.max(0, level) * 4) : 0;
        activity = reducedMotion ? 0 : activity + (goal - activity) * (1 - Math.exp(-delta / 220));
        const beat = Math.pow(Math.max(0, Math.sin(t * 2.1) * .75 + Math.sin(t * 1.17) * .25), 3);
        const answerBeat = Math.pow(Math.max(0, Math.sin(t * 1.7 + 1.4)), 5);
        const fidget = Math.pow(Math.max(0, Math.sin(t * .56)), 14);
        const listenerNod = listening ? Math.pow(Math.max(0, Math.sin(t * .78)), 12) : 0;
        const motion = reducedMotion ? 0 : 1;
        const eyeTarget = (noise(Math.floor(t / 2.4) + 5) - .5) * .11;
        eyeYaw = reducedMotion ? 0 : eyeYaw + (eyeTarget - eyeYaw) * (1 - Math.exp(-delta / 90));
        nextBlink ??= time + 1400 + noise(2) * 3000;
        const blinkProgress = (time - nextBlink) / 210;
        const blink = !reducedMotion && blinkProgress >= 0 && blinkProgress <= 1 ? Math.sin(Math.PI * blinkProgress) ** .65 : 0;
        if (blinkProgress > 1) nextBlink = time + 2300 + noise(++blinkCount + 10) * 3800;

        return {
            blink,
            eyeYaw,
            headYaw: motion * (.025 * Math.sin(t * .31) + .035 * fidget),
            headPitch: motion * (.009 * Math.sin(t * .83) + .035 * listenerNod + activity * beat * .045),
            headRoll: motion * (.012 * Math.sin(t * .43) + activity * answerBeat * .012),
            spinePitch: motion * (.008 * Math.sin(t * 1.45) + activity * beat * .015),
            spineRoll: motion * (.009 * Math.sin(t * .37) + .008 * fidget),
            leftLift: motion * (.009 * fidget + activity * answerBeat * .105),
            leftReach: motion * activity * answerBeat * .075,
            rightLift: motion * (.013 * fidget + activity * beat * .18),
            rightReach: motion * activity * beat * .13,
            wrist: motion * activity * beat * .09,
            brow: motion * activity * beat * .13,
            squint: motion * (.018 + activity * answerBeat * .045),
        };
    };
}

function aimJoint(joint, child) {
    const rest = joint.rotationQuaternion?.clone();
    if (!rest || !joint.parent || !child) return null;
    const neutral = Vector3.Zero();
    child.position.rotateByQuaternionToRef(rest, neutral);
    neutral.normalize();
    const inverse = Matrix.Identity();
    const target = Vector3.Zero();
    const origin = Vector3.Zero();
    const delta = Quaternion.Identity();

    return point => {
        joint.parent.computeWorldMatrix(true).invertToRef(inverse);
        joint.computeWorldMatrix(true).getTranslationToRef(origin);
        point.subtractToRef(origin, target);
        Vector3.TransformNormalToRef(target, inverse, target);
        if (target.lengthSquared() < 1e-6) return;
        Quaternion.FromUnitVectorsToRef(neutral, target.normalize(), delta);
        delta.multiplyToRef(rest, joint.rotationQuaternion);
    };
}

function createLeg(character, bone, side) {
    const hip = bone(side + 'upleg');
    const knee = bone(side + 'leg');
    const ankle = bone(side + 'foot');
    const toe = bone(side + 'toebase');
    if (!hip || !knee || !ankle) return null;
    const aimHip = aimJoint(hip, knee);
    const aimKnee = aimJoint(knee, ankle);
    if (!aimHip || !aimKnee) return null;
    const aimFoot = aimJoint(ankle, toe);
    const basis = Matrix.Identity();
    character.computeWorldMatrix(true).invertToRef(basis);
    const worldPosition = joint => joint.computeWorldMatrix(true).getTranslation();
    const hipPosition = worldPosition(hip);
    const kneePosition = worldPosition(knee);
    const anklePosition = worldPosition(ankle);
    const upperLength = Vector3.Distance(hipPosition, kneePosition);
    const lowerLength = Vector3.Distance(kneePosition, anklePosition);
    const neutralAnkle = Vector3.TransformCoordinates(anklePosition, basis);
    const neutralHip = Vector3.TransformCoordinates(hipPosition, basis);
    const seatedFootAdvance = upperLength + neutralHip.z - neutralAnkle.z;
    const toeDirection = toe ? Vector3.TransformNormal(worldPosition(toe).subtract(anklePosition), basis) : null;

    return (seat, wave, lift, displacement) => {
        const matrix = character.computeWorldMatrix(true);
        const localTarget = new Vector3(neutralAnkle.x, neutralAnkle.y + displacement + lift, neutralAnkle.z + seat * seatedFootAdvance + wave);
        const footTarget = Vector3.TransformCoordinates(localTarget, matrix);
        const origin = worldPosition(hip);
        const direction = footTarget.subtract(origin);
        const distance = Math.max(.01, Math.min(direction.length(), upperLength + lowerLength - .002));
        direction.normalize();
        const forward = Vector3.TransformNormal(Vector3.LeftHandedForwardReadOnly, matrix).normalize();
        const bend = forward.subtract(direction.scale(Vector3.Dot(forward, direction))).normalize();
        const along = (upperLength ** 2 - lowerLength ** 2 + distance ** 2) / (2 * distance);
        const kneeTarget = origin.add(direction.scale(along))
            .add(bend.scale(Math.sqrt(Math.max(0, upperLength ** 2 - along ** 2))));

        aimHip(kneeTarget);
        aimKnee(footTarget);
        if (aimFoot && toeDirection) {
            aimFoot(worldPosition(ankle).add(Vector3.TransformNormal(toeDirection, matrix)));
        }
    };
}

function createArm(character, bone, side) {
    const upper = bone(side + 'arm');
    const forearm = bone(side + 'forearm');
    const hand = bone(side + 'hand');
    if (!upper || !forearm || !hand) return null;
    const aimUpper = aimJoint(upper, forearm);
    const aimForearm = aimJoint(forearm, hand);
    if (!aimUpper || !aimForearm) return null;
    const inverse = character.computeWorldMatrix(true).clone().invert();
    const shoulder = Vector3.TransformCoordinates(upper.computeWorldMatrix(true).getTranslation(), inverse);
    const elbowRest = Vector3.TransformCoordinates(forearm.computeWorldMatrix(true).getTranslation(), inverse);
    const wristRest = Vector3.TransformCoordinates(hand.computeWorldMatrix(true).getTranslation(), inverse);
    const upperLength = Vector3.Distance(shoulder, elbowRest);
    const lowerLength = Vector3.Distance(elbowRest, wristRest);
    const matrix = character.computeWorldMatrix(true);
    aimUpper(Vector3.TransformCoordinates(new Vector3(shoulder.x * 1.22, shoulder.y - upperLength * .94, shoulder.z + .025), matrix));
    aimForearm(Vector3.TransformCoordinates(new Vector3(shoulder.x * 1.15, shoulder.y - (upperLength + lowerLength) * .94, shoulder.z + .08), matrix));
    const neutralElbow = Vector3.TransformCoordinates(forearm.computeWorldMatrix(true).getTranslation(), inverse);
    const neutralHand = Vector3.TransformCoordinates(hand.computeWorldMatrix(true).getTranslation(), inverse);
    const upperRest = upper.rotationQuaternion.clone();
    const forearmRest = forearm.rotationQuaternion.clone();
    const handRest = hand.rotationQuaternion?.clone();
    const handTurn = Quaternion.Identity();
    const elbow = Vector3.Zero();
    const wrist = Vector3.Zero();
    let moved = false;

    return (swing, lift, reach, wristTurn = 0) => {
        if (handRest) {
            Quaternion.RotationYawPitchRollToRef(0, wristTurn, 0, handTurn);
            handRest.multiplyToRef(handTurn, hand.rotationQuaternion);
        }
        if (!swing && !lift && !reach) {
            if (moved) {
                upper.rotationQuaternion.copyFrom(upperRest);
                forearm.rotationQuaternion.copyFrom(forearmRest);
                moved = false;
            }
            return;
        }
        moved = true;
        const matrix = character.computeWorldMatrix(true);
        elbow.set(neutralElbow.x, neutralElbow.y + lift * .26, neutralElbow.z + swing * .60 + reach * .35);
        wrist.set(neutralHand.x, neutralHand.y + lift, neutralHand.z + swing + reach);
        Vector3.TransformCoordinatesToRef(elbow, matrix, elbow);
        Vector3.TransformCoordinatesToRef(wrist, matrix, wrist);
        aimUpper(elbow);
        aimForearm(wrist);
    };
}

export function createAvatarMotion(character, bone, role, enteredAt, seatHeight = courtroomSeating.seatHeight) {
    const legs = ['left', 'right'].map(side => createLeg(character, bone, side));
    const leftArm = createArm(character, bone, 'left');
    const rightArm = createArm(character, bone, 'right');
    const canSit = isRoleSeated(role) && legs.every(Boolean);
    const inverse = character.computeWorldMatrix(true).clone().invert();
    const hips = ['leftupleg', 'rightupleg'].map(bone).filter(Boolean)
        .map(joint => Vector3.TransformCoordinates(joint.computeWorldMatrix(true).getTranslation(), inverse));
    const hipHeight = hips.length ? hips.reduce((sum, hip) => sum + hip.y, 0) / hips.length : 0;
    const seatedDisplacement = Math.max(0, hipHeight - seatHeight - courtroomSeating.pelvisClearance);
    const angle = character.rotation.y;
    const offset = entrances[role] ?? 0;
    const entranceAxis = role === 'witness' ? 'z' : 'x';
    const duration = offset ? Math.abs(offset) / 1.15 * 1000 : 0;
    const naturalMotion = createNaturalMotion(role);
    let seat = canSit && enteredAt === null ? 1 : 0;
    let updatedAt = enteredAt;
    let previousSeat = null;
    let wasWalking = false;

    return ({ time, entranceTime = time, standing, reducedMotion, gesture, natural }) => {
        const entrance = enteredAt === null || reducedMotion || !duration ? 1 : clamp((entranceTime - enteredAt) / duration);
        const walking = entrance < 1 && legs.every(Boolean);
        const goal = canSit && !standing && !walking ? 1 : 0;
        const step = updatedAt === null ? 1 : Math.min(1, Math.max(0, time - updatedAt) / sittingTime);
        seat = reducedMotion ? goal : seat + Math.sign(goal - seat) * Math.min(Math.abs(goal - seat), step);
        updatedAt = time;
        const walkProgress = walking ? entrance : 1;
        const stride = walkProgress * Math.abs(offset) / .65 * Math.PI * 2;
        const swing = walking ? Math.sin(stride) * .20 : 0;

        if (previousSeat !== seat || walking || wasWalking) {
            const seated = smooth(seat);
            const facing = entranceAxis === 'z' ? (offset > 0 ? Math.PI : 0) : (offset > 0 ? -Math.PI / 2 : Math.PI / 2);
            const angleDelta = Math.atan2(Math.sin(facing - angle), Math.cos(facing - angle));
            const turn = walking ? smooth(clamp((1 - walkProgress) / .2)) : 0;
            const bob = walking ? .012 * (1 - Math.cos(stride * 2)) : 0;
            const displacement = seatedDisplacement * seated - bob;
            const chairClearance = canSit ? .58 * (1 - seated) : 0;

            character.position.x = (entranceAxis === 'x' ? offset * (1 - walkProgress) : 0) + Math.sin(angle) * chairClearance;
            character.position.z = (entranceAxis === 'z' ? offset * (1 - walkProgress) : 0) + Math.cos(angle) * chairClearance;
            character.position.y = -displacement;
            character.rotation.y = angle + angleDelta * turn;

            legs.forEach((apply, index) => {
                const cycle = stride + index * Math.PI;
                const swing = walking ? Math.sin(cycle) : 0;
                const lift = walking ? .09 * Math.max(0, Math.cos(cycle)) : 0;
                apply?.(seated, .17 * swing, lift, displacement);
            });
            previousSeat = seat;
            wasWalking = walking;
        }

        const pose = gesture && !walking && !reducedMotion ? courtroomGestures[gesture.type] : null;
        const progress = pose ? clamp(gesture.elapsed / pose.duration) : 0;
        const weight = smooth(Math.min(1, progress * 4, (1 - progress) * 4));
        const restingLift = smooth(seat) * .10;
        const restingReach = smooth(seat) * .055;
        const life = natural ?? naturalMotion(time, { speaking: standing, reducedMotion });
        const idleWeight = walking || reducedMotion ? 0 : 1 - weight;
        leftArm?.(swing, restingLift + life.leftLift * idleWeight, restingReach + life.leftReach * idleWeight, -life.wrist * idleWeight);
        rightArm?.(-swing, restingLift + (pose?.lift ?? 0) * weight + life.rightLift * idleWeight,
            restingReach + (pose?.reach ?? 0) * weight + life.rightReach * idleWeight, life.wrist * idleWeight);

        if (walking) return 'walking';
        if (!canSit || seat === 0) return 'standing';
        if (seat === 1) return 'seated';
        return goal ? 'sitting' : 'rising';
    };
}
