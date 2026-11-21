import { LoadAssetContainerAsync } from '@babylonjs/core/Loading/sceneLoader.js';
import { Quaternion } from '@babylonjs/core/Maths/math.vector.js';
import { TransformNode } from '@babylonjs/core/Meshes/transformNode.js';
import { MeshoptCompression } from '@babylonjs/core/Meshes/Compression/meshoptCompression.js';
import { registerBuiltInLoaders } from '@babylonjs/loaders/dynamic.js';
import { createAvatarMotion, createNaturalMotion, gazeYaw } from './courtroom-avatar-motion.js';

registerBuiltInLoaders();

const participantModels = {
    judge: 'business-male-01',
    prosecutor: 'business-male-02',
    victim_advocate: 'business-female-01',
    victim_attorney: 'business-female-01',
    defense_advocate: 'business-male-01',
    defense_attorney: 'business-male-01',
    victim: 'business-female-04',
    defendant: 'male-adult-08',
    witness_1: 'male-adult-03',
    witness_2: 'business-female-02',
    witness_3: 'business-female-03',
    witness: 'male-adult-03',
    claimant: 'rocketbox-male',
    court_clerk: 'business-female-02',
    expert: 'business-female-03',
    expert_witness: 'business-female-03',
    interpreter: 'business-female-04',
};

const observerModels = [
    'rocketbox-male',
    'business-female-01',
    'business-male-01',
    'business-female-02',
    'male-adult-03',
    'business-female-03',
    'business-male-02',
    'business-female-04',
];

const femaleRoles = new Set([
    'defense_attorney',
    'victim_advocate',
    'victim_attorney',
    'victim',
    'witness_2',
    'witness_3',
    'court_clerk',
    'expert',
    'expert_witness',
    'interpreter',
]);

function getModelForRole(role) {
    if (participantModels[role]) return participantModels[role];
    if (role.startsWith('observer_')) {
        const id = parseInt(role.replace(/\D/g, '') || '0', 10);
        return observerModels[id % observerModels.length];
    }
    return null;
}

function getVariantForRole(role) {
    if (femaleRoles.has(role)) return 'female';
    if (role.startsWith('observer_')) {
        const id = parseInt(role.replace(/\D/g, '') || '0', 10);
        return (id % 2 === 1) ? 'female' : 'male';
    }
    return 'male';
}

export async function loadCourtroomAvatars({ scene, shadows, avatarRigs }, assetUrls, signal, { animateEntrance, onLoad }) {
    const models = new Map();
    const containers = new Map();
    const baseUrl = new URL(assetUrls.male || assetUrls.female, window.location.href);
    MeshoptCompression.Configuration.decoder.url = new URL('meshopt_decoder.js', baseUrl).href;

    await Promise.all([...avatarRigs.keys()].map(async role => {
        const variant = getVariantForRole(role);
        if (!assetUrls[variant]) return;
        const modelName = getModelForRole(role);
        const dedicated = modelName && new URL(`${modelName}.glb`, baseUrl).href;
        for (const source of [dedicated, assetUrls[variant]].filter(Boolean)) {
            try {
                const url = new URL(source, window.location.href);
                if (url.origin !== window.location.origin) continue;
                const container = await LoadAssetContainerAsync(url.href, scene, { pluginExtension: '.glb' });
                const managers = new Set(container.meshes.map(mesh => mesh.morphTargetManager).filter(Boolean));
                for (const manager of managers) {
                    manager.areUpdatesFrozen = true;
                    for (let index = manager.numTargets - 1; index >= 0; index--) {
                        const target = manager.getTarget(index);
                        if (!/^(viseme_|AA_VI_)|(?:eyeBlink(?:Left|Right)|browInnerUp|eyeSquint(?:Left|Right))$/i.test(target.name)) manager.removeTarget(target);
                    }
                    manager.numMaxInfluencers = 6;
                    manager.areUpdatesFrozen = false;
                }
                if (signal.aborted) container.dispose();
                else containers.set(role, container);
                return;
            } catch {
                // Retry the configured model before keeping the procedural participant.
            }
        }
    }));

    if (signal.aborted || containers.size === 0) return models;
    scene.onDisposeObservable.addOnce(() => containers.forEach(container => container.dispose()));
    for (const [role, rig] of avatarRigs) {
        const container = containers.get(role);
        if (!container) continue;
        const anchor = new TransformNode(`avatar-${role}`, scene);
        const instance = container.instantiateModelsToScene(name => `${role}:${name}`, false, { doNotInstantiate: true });
        instance.rootNodes.forEach(node => { node.parent = anchor; });
        const { min, max } = anchor.getHierarchyBoundingVectors();
        const height = max.y - min.y;
        if (!Number.isFinite(height) || height < .05) {
            instance.dispose();
            anchor.dispose();
            continue;
        }
        const scale = 1.9 / height;
        const character = new TransformNode(`character-${role}`, scene);
        character.parent = rig.node;
        character.rotation.y = ['judge', 'witness', 'witness_1', 'witness_2', 'witness_3', 'court_clerk', 'expert', 'expert_witness'].includes(role) ? 0 : Math.PI;
        if (rig.label) rig.label.parent = character;
        anchor.parent = character;
        anchor.scaling.setAll(scale);
        anchor.position.set(-(min.x + max.x) * scale / 2, -min.y * scale, -(min.z + max.z) * scale / 2);
        const meshes = anchor.getChildMeshes();
        for (const mesh of meshes) {
            for (const texture of mesh.material?.getActiveTextures() ?? []) texture.anisotropicFilteringLevel = 16;
        }
        meshes.filter(mesh => mesh.getTotalVertices() > 0).forEach(mesh => shadows.addShadowCaster(mesh));
        const managers = meshes.map(mesh => mesh.morphTargetManager).filter(Boolean);
        const targets = new Map();
        for (const manager of managers) {
            manager.numMaxInfluencers = 6;
            for (let index = 0; index < manager.numTargets; index++) {
                const target = manager.getTarget(index);
                const shape = target.name.startsWith('viseme_') ? target.name.slice(7) : target.name.match(/^AA_VI_\d+_(.+)$/)?.[1];
                if (!shape || shape.toLowerCase() === 'sil') continue;
                const normalized = shape === 'KK' ? 'kk' : shape;
                if (!targets.has(normalized)) targets.set(normalized, new Set());
                targets.get(normalized).add(target);
            }
        }
        const blinks = managers.flatMap(manager => [
            manager.getTargetByName('eyeBlinkLeft') ?? manager.getTargetByName('AK_09_EyeBlinkLeft'),
            manager.getTargetByName('eyeBlinkRight') ?? manager.getTargetByName('AK_10_EyeBlinkRight'),
        ]).filter(Boolean);
        const brows = managers.map(manager => manager.getTargetByName('browInnerUp') ?? manager.getTargetByName('AK_03_BrowInnerUp')).filter(Boolean);
        const squints = managers.flatMap(manager => [
            manager.getTargetByName('eyeSquintLeft') ?? manager.getTargetByName('AK_19_EyeSquintLeft'),
            manager.getTargetByName('eyeSquintRight') ?? manager.getTargetByName('AK_20_EyeSquintRight'),
        ]).filter(Boolean);
        const joints = anchor.getDescendants();
        const bone = name => {
            const bipName = name.replace(/^left/, 'l ').replace(/^right/, 'r ')
                .replace('upleg', 'thigh').replace(/(?<!fore)arm$/, 'upperarm')
                .replace(/(?<!up)leg$/, 'calf').replace('toebase', 'toe0');
            return joints.find(node => node.name.toLowerCase().endsWith(':' + name)
                || node.name.toLowerCase().endsWith(':bip01 ' + bipName));
        };
        let motion = null;
        let motionTime = 0;
        let previousMotionTime = null;
        const head = bone('head');
        const spine = bone('spine2');
        const headRest = head?.rotationQuaternion?.clone();
        const spineRest = spine?.rotationQuaternion?.clone();
        const eyes = ['leye', 'reye'].map(name => bone(name)).filter(node => node?.rotationQuaternion)
            .map(node => ({ node, rest: node.rotationQuaternion.clone() }));
        const naturalMotion = createNaturalMotion(role);
        const nod = Quaternion.Identity();
        let previousShape = null;
        let previousTime = null;
        let yaw = 0;
        rig.node.getChildMeshes().filter(mesh => !meshes.includes(mesh) && !mesh.metadata?.courtroomLabel).forEach(mesh => mesh.setEnabled(false));
        models.set(role, {
            animate({ level, shape, active, gesture, speaking, lookingAt, reducedMotion, time }) {
                motion ??= createAvatarMotion(character, bone, role, animateEntrance ? 0 : null);
                if (previousMotionTime !== null) motionTime += Math.min(100, Math.max(0, time - previousMotionTime));
                previousMotionTime = time;
                const life = naturalMotion(time, { speaking: speaking || active, level, listening: Boolean(lookingAt), reducedMotion });
                const posture = motion({ time, entranceTime: motionTime, standing: speaking || active || Boolean(gesture), reducedMotion, gesture, natural: life });
                const mouthShape = active && level > .012 ? (shape ?? 'aa') : 'sil';
                if (previousShape !== mouthShape) {
                    for (const target of targets.get(previousShape) ?? []) target.influence = 0;
                    previousShape = mouthShape;
                }
                for (const target of targets.get(mouthShape) ?? []) target.influence = Math.min(.8, level * 5);
                for (const target of blinks) target.influence = life.blink;
                for (const target of brows) target.influence = life.brow;
                for (const target of squints) target.influence = life.squint;
                if (headRest) {
                    const targetYaw = reducedMotion || posture === 'walking' ? 0 : gazeYaw(character.rotation.y, rig.node.position, lookingAt) + life.headYaw;
                    const blend = previousTime === null || reducedMotion ? 1 : 1 - Math.exp(-(time - previousTime) / 130);
                    yaw += (targetYaw - yaw) * blend;
                    previousTime = time;
                    const tilt = posture === 'walking' ? 0 : life.headPitch;
                    Quaternion.RotationYawPitchRollToRef(yaw, tilt, life.headRoll, nod);
                    headRest.multiplyToRef(nod, head.rotationQuaternion);
                }
                for (const eye of eyes) {
                    Quaternion.RotationYawPitchRollToRef(life.eyeYaw, 0, 0, nod);
                    eye.rest.multiplyToRef(nod, eye.node.rotationQuaternion);
                }
                if (spineRest) {
                    Quaternion.RotationYawPitchRollToRef(0, life.spinePitch, life.spineRoll, nod);
                    spineRest.multiplyToRef(nod, spine.rotationQuaternion);
                }
                return posture;
            },
        });
        onLoad(role);
    }

    return models;
}
