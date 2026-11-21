import { courtroomGestures, courtroomSeating, createNaturalMotion, gazeYaw } from './courtroom-avatar-motion.js';
import { courtroomEvidence, drawEvidenceBoard } from './courtroom-evidence-board.js';
import { Engine } from '@babylonjs/core/Engines/engine.js';
import '@babylonjs/core/Engines/AbstractEngine/abstractEngine.cubeTexture.js';
import '@babylonjs/core/Engines/Extensions/engine.prefilteredCubeTexture.js';
import '@babylonjs/core/Materials/Textures/Loaders/envTextureLoader.js';
import { Scene } from '@babylonjs/core/scene.js';
import { ArcRotateCamera } from '@babylonjs/core/Cameras/arcRotateCamera.js';
import { Vector3 } from '@babylonjs/core/Maths/math.vector.js';
import { Color3, Color4 } from '@babylonjs/core/Maths/math.color.js';
import { MeshBuilder } from '@babylonjs/core/Meshes/meshBuilder.js';
import { TransformNode } from '@babylonjs/core/Meshes/transformNode.js';
import { Texture } from '@babylonjs/core/Materials/Textures/texture.js';
import { DynamicTexture } from '@babylonjs/core/Materials/Textures/dynamicTexture.js';
import { PointerEventTypes } from '@babylonjs/core/Events/pointerEvents.js';
import '@babylonjs/core/Culling/ray.js';
import { CubeTexture } from '@babylonjs/core/Materials/Textures/cubeTexture.js';
import { PBRMaterial } from '@babylonjs/core/Materials/PBR/pbrMaterial.js';
import { HemisphericLight } from '@babylonjs/core/Lights/hemisphericLight.js';
import { DirectionalLight } from '@babylonjs/core/Lights/directionalLight.js';
import { ShadowGenerator } from '@babylonjs/core/Lights/Shadows/shadowGenerator.js';
import { SSAO2RenderingPipeline } from '@babylonjs/core/PostProcesses/RenderPipeline/Pipelines/ssao2RenderingPipeline.js';
import '@babylonjs/core/Lights/Shadows/shadowGeneratorSceneComponent.js';

export function createCourtroom(engine, roles = [], participantNames = {}) {
    const scene = new Scene(engine);
    scene.clearColor = new Color4(.025, .035, .055, 1);
    scene.imageProcessingConfiguration.toneMappingEnabled = true;
    scene.imageProcessingConfiguration.exposure = 1.15;
    scene.imageProcessingConfiguration.contrast = 1.05;
    if (typeof document !== 'undefined') {
        scene.environmentTexture = CubeTexture.CreateFromPrefilteredData('/courtroom/studio.env', scene);
        scene.environmentIntensity = .55;
    }
    const camera = new ArcRotateCamera('court-camera', Math.PI / 2, 1.1, 23, new Vector3(0, 2, -1), scene);
    camera.lowerRadiusLimit = 2;
    camera.upperRadiusLimit = 32;
    camera.lowerBetaLimit = .2;
    camera.upperBetaLimit = 1.5;
    camera.wheelPrecision = 35;
    camera.minZ = .1;
    let ambientOcclusion = null;
    if (typeof document !== 'undefined') {
        ambientOcclusion = new SSAO2RenderingPipeline('courtroom-contact-shadows', scene, { ssaoRatio: .5, blurRatio: .5 }, [camera]);
        ambientOcclusion.radius = .25;
        ambientOcclusion.totalStrength = .45;
        ambientOcclusion.samples = 16;
    }
    const fill = new HemisphericLight('soft-fill', new Vector3(0, 1, 0), scene);
    fill.intensity = .95;
    fill.groundColor = new Color3(.32, .30, .28);
    const portraitFill = new DirectionalLight('portrait-fill', new Vector3(.4, -.35, .8), scene);
    portraitFill.intensity = .65;
    portraitFill.diffuse = new Color3(.86, .92, 1);
    const sunlight = new DirectionalLight('window-light', new Vector3(-.6, -1, -.4), scene);
    sunlight.position = new Vector3(6, 12, 5);
    sunlight.intensity = 1.65;
    sunlight.diffuse = new Color3(1, .91, .78);
    const shadows = new ShadowGenerator(1024, sunlight);
    shadows.usePercentageCloserFiltering = true;
    shadows.bias = .001;

    const material = (name, color, roughness = .6, metallic = 0) => {
        const result = new PBRMaterial(name, scene);
        result.albedoColor = Color3.FromHexString(color);
        result.roughness = roughness;
        result.metallic = metallic;
        return result;
    };
    const walnut = material('walnut', '#543729', .36);
    const darkWood = material('dark-walnut', '#291f1c', .4);
    const stone = material('warm-limestone', '#b6ac98', .72);
    const marble = material('marble-floor', '#77746b', .24);
    const applyScannedSurface = (surface, name, horizontal, vertical) => {
        if (typeof document === 'undefined') return;
        const map = (suffix, color = false) => {
            const texture = new Texture(`/courtroom/${name}-${suffix}.jpg`, scene);
            texture.uScale = horizontal;
            texture.vScale = vertical;
            texture.anisotropicFilteringLevel = 16;
            texture.gammaSpace = color;
            return texture;
        };
        surface.albedoTexture = map('diff', true);
        surface.bumpTexture = map('nor_gl');
        surface.bumpTexture.level = .18;
        surface.invertNormalMapX = true;
        surface.invertNormalMapY = true;
        surface.metallicTexture = map('arm');
        surface.useRoughnessFromMetallicTextureAlpha = false;
        surface.useRoughnessFromMetallicTextureGreen = false;
        surface.useMetallnessFromMetallicTextureBlue = true;
        surface.useAmbientOcclusionFromMetallicTextureRed = true;
        surface.roughness = name === 'wood' ? .62 : .42;
    };
    walnut.albedoColor = new Color3(.65, .47, .32);
    marble.albedoColor = Color3.White();
    applyScannedSurface(walnut, 'wood', 1, 1);
    applyScannedSurface(darkWood, 'wood', 1, 1);
    applyScannedSurface(marble, 'marble', 8, 10);
    const brass = material('brass', '#b59b60', .3, .7);
    const fabric = material('navy-upholstery', '#182b42', .95);
    const paper = material('paper', '#e2ddcc', .9);
    const microphoneSurface = material('microphone-black', '#171a20', .65);
    const lightPanel = material('ceiling-diffuser', '#fff4dd', .9);
    lightPanel.emissiveColor = new Color3(.8, .73, .6);
    const skin = material('avatar-skin', '#bd9275', .82);
    const screenMaterial = material('evidence-display', '#182b42', .3);
    screenMaterial.unlit = true;
    screenMaterial.albedoColor = Color3.White();
    let boardTexture = null;
    if (typeof document !== 'undefined') {
        boardTexture = new DynamicTexture('evidence-document', { width: 1536, height: 1024 }, scene, true);
        boardTexture.anisotropicFilteringLevel = 16;
        screenMaterial.albedoTexture = boardTexture;
    }
    const showEvidence = (item, index = 0, count = 0) => {
        if (!boardTexture) return;
        drawEvidenceBoard(boardTexture.getContext(), item, index, count);
        boardTexture.update();
    };
    showEvidence(null);
    const box = (name, position, dimensions, surface, castsShadow = true) => {
        const mesh = MeshBuilder.CreateBox(name, { width: dimensions[0], height: dimensions[1], depth: dimensions[2] }, scene);
        mesh.position.set(...position);
        mesh.material = surface;
        mesh.receiveShadows = true;
        if (castsShadow) shadows.addShadowCaster(mesh);
        return mesh;
    };
    box('floor', [0, -.15, 0], [20, .3, 26], marble, false);
    box('front-wall', [0, 4.5, 13], [20, 9, .3], stone, false);
    box('ceiling', [0, 9, 0], [20, .25, 26], stone, false);
    for (const x of [-5, 0, 5]) {
        for (const z of [-7, 0, 7]) {
            box(`ceiling-light-frame-${x}-${z}`, [x, 8.8, z], [2.5, .12, 1], brass, false);
            box(`ceiling-light-${x}-${z}`, [x, 8.72, z], [2.35, .04, .85], lightPanel, false);
        }
    }
    box('front-door', [0, 2, 12.8], [3.2, 4, .2], walnut);
    box('front-door-trim', [0, 4.1, 12.65], [3.5, .15, .1], brass);
    box('back-wall', [0, 4.5, -12], [20, 9, .3], stone, false);
    box('left-wall', [-10, 4.5, 0], [.3, 9, 26], stone, false);
    box('right-wall', [10, 4.5, 0], [.3, 9, 26], stone, false);
    for (let x = -9; x <= 9; x += 1.5) {
        box(`back-panel-${x}`, [x, 1.45, -11.8], [1.4, 2.9, .15], walnut);
        box(`back-trim-${x}`, [x, 3, -11.65], [1.45, .08, .1], brass);
    }
    for (const side of [-1, 1]) {
        for (let z = -9; z <= 9; z += 4.5) {
            box(`column-${side}-${z}`, [side * 9.7, 4, z], [.5, 8, .55], stone);
            box(`column-base-${side}-${z}`, [side * 9.7, .3, z], [.75, .6, .8], darkWood);
            box(`wall-panel-${side}-${z}`, [side * 9.8, 1.4, z + 2], [.12, 2.8, 3.8], walnut);
        }
    }
    for (const side of [-1, 1]) {
        for (const z of [-6, 0, 6]) {
            const windowMaterial = material(`frosted-window-${side}-${z}`, '#b5c7d1', .6);
            windowMaterial.emissiveColor = new Color3(.12, .15, .18);
            box(`window-${side}-${z}`, [side * 9.78, 5.5, z], [.08, 3.4, 2.8], windowMaterial, false);
            box(`window-divider-${side}-${z}`, [side * 9.68, 5.5, z], [.12, 3.5, .09], darkWood);
        }
    }
    box('judge-platform', [0, .3, -8.5], [12, .6, 5], darkWood);
    box('platform-step', [0, .12, -5.8], [10, .24, .6], walnut);
    box('judge-bench', [0, 1.05, -7.6], [8, .9, 1.2], walnut);
    box('judge-desktop', [0, 1.55, -7.6], [8.3, .1, 1.5], darkWood);
    box('judge-brass-inlay', [0, 1.29, -6.98], [6.8, .06, .03], brass);
    for (const x of [-2.65, 0, 2.65]) {
        box(`bench-recess-${x}`, [x, 1.02, -6.985], [2.35, .6, .04], darkWood);
        box(`bench-panel-${x}`, [x, 1.02, -6.95], [2.15, .44, .035], walnut);
    }
    box('symbol-reserved-panel', [0, 5, -11.7], [3, 3, .15], darkWood);
    box('court-door', [-6.7, 2, -11.7], [2, 4, .18], walnut);
    box('door-handle', [-6.05, 1.9, -11.55], [.05, .4, .06], brass);
    const chair = (name, x, y, z, facing = 1) => {
        box(`${name}-seat`, [x, y + courtroomSeating.seatHeight - courtroomSeating.cushionThickness / 2, z], [.9, courtroomSeating.cushionThickness, .85], fabric);
        box(`${name}-back`, [x, y + .88, z - facing * .4], [.95, .9, .18], walnut);
        box(`${name}-back-cushion`, [x, y + .92, z - facing * .29], [.79, .72, .09], fabric);
        for (const side of [-1, 1]) {
            box(`${name}-armrest-${side}`, [x + side * .48, y + .67, z], [.12, .09, .7], darkWood);
        }
        for (const dx of [-.35, .35]) for (const dz of [-.32, .32]) {
            box(`${name}-leg-${dx}-${dz}`, [x + dx, y + .17, z + dz], [.09, .34, .09], darkWood);
        }
    };
    chair('judge-chair', 0, .6, -9.3);
    chair('defendant-chair', 5.9, 0, 1.5, -1);
    box('defendant-side-desk', [5.9, .46, .3], [1.8, .92, .8], walnut);
    chair('victim-chair', -5.9, 0, 1.5, -1);
    box('victim-side-desk', [-5.9, .46, .3], [1.8, .92, .8], walnut);
    chair('clerk-chair', -6.5, 0, -5.1, 1);

    // Left counsel desk (Prosecutor and Victim Advocate)
    box('counsel-desk-left', [-2.9, .46, -1], [3.8, .92, 1.6], walnut);
    box('counsel-top-left', [-2.9, .96, -1], [4, .08, 1.8], darkWood);
    box('case-papers-left', [-2.2, 1.02, -1], [.55, .035, .7], paper);
    chair('counsel-chair-left-1', -3.8, 0, .7, -1);
    chair('counsel-chair-left-2', -2.0, 0, .7, -1);

    // Right counsel desk (Defense Advocate and Assistant)
    box('counsel-desk-right', [2.9, .46, -1], [3.8, .92, 1.6], walnut);
    box('counsel-top-right', [2.9, .96, -1], [4, .08, 1.8], darkWood);
    box('case-papers-right', [2.2, 1.02, -1], [.55, .035, .7], paper);
    chair('counsel-chair-right-1', 2.0, 0, .7, -1);
    chair('counsel-chair-right-2', 3.8, 0, .7, -1);

    // Witness bench for waiting witnesses
    box('witness-waiting-bench', [6.5, .3, -2.75], [1.2, .6, 2.8], walnut);
    box('witness-waiting-cushion', [6.5, .62, -2.75], [1.1, .06, 2.7], fabric);

    box('witness-stand', [6.5, .52, -5.35], [2.2, 1.04, .14], walnut);
    box('witness-stand-front-rail', [6.5, 1.08, -5.35], [2.3, .10, .24], darkWood);
    for (const side of [-1, 1]) {
        box(`witness-stand-side-${side}`, [6.5 + side * 1.03, .52, -6.3], [.14, 1.04, 1.9], walnut);
        box(`witness-stand-side-rail-${side}`, [6.5 + side * 1.03, 1.08, -6.3], [.24, .10, 2], darkWood);
    }
    box('clerk-desk', [-6.5, .65, -5.1], [2.8, 1.3, 1.4], walnut);
    const microphone = (name, x, y, z) => {
        box(`${name}-base`, [x, y, z], [.23, .035, .17], microphoneSurface);
        const stem = MeshBuilder.CreateTube(`${name}-stem`, {
            path: [new Vector3(x, y, z), new Vector3(x, y + .23, z), new Vector3(x, y + .34, z + .16)],
            radius: .012, tessellation: 12,
        }, scene);
        stem.material = microphoneSurface;
        const capsule = MeshBuilder.CreateCapsule(`${name}-capsule`, { height: .09, radius: .025, tessellation: 12 }, scene);
        capsule.position.set(x, y + .34, z + .17);
        capsule.rotation.x = Math.PI / 3;
        capsule.material = microphoneSurface;
    };
    microphone('judge-microphone', -.7, 1.62, -8);
    microphone('prosecutor-microphone', -3.8, 1.03, -.7);
    microphone('victim-advocate-microphone', -2.0, 1.03, -.7);
    microphone('defense-microphone', 2.0, 1.03, -.7);
    microphone('defense-microphone-2', 3.8, 1.03, -.7);
    microphone('witness-microphone', 6.5, 1.15, -5.8);
    const gavelHead = MeshBuilder.CreateCylinder('gavel-head', { height: .23, diameter: .12, tessellation: 24 }, scene);
    gavelHead.rotation.z = Math.PI / 2;
    gavelHead.position.set(.8, 1.68, -7.6);
    gavelHead.material = walnut;
    const gavelHandle = MeshBuilder.CreateCylinder('gavel-handle', { height: .34, diameter: .035, tessellation: 16 }, scene);
    gavelHandle.rotation.x = Math.PI / 2;
    gavelHandle.position.set(.8, 1.65, -7.42);
    gavelHandle.material = darkWood;
    box('evidence-frame', [8.5, 4, -7], [.18, 3.1, 4.3], darkWood);
    const evidenceScreen = MeshBuilder.CreatePlane('evidence-screen', { width: 4, height: 2.8 }, scene);
    evidenceScreen.position.set(8.38, 4, -7);
    evidenceScreen.rotation.y = Math.PI / 2;
    evidenceScreen.material = screenMaterial;
    for (let row = 0; row < 6; row++) for (const side of [-1, 1]) {
        box(`gallery-seat-${row}-${side}`, [side * 5, .6, 5 + row * 1.7], [6, .2, 1], walnut);
        box(`gallery-back-${row}-${side}`, [side * 5, 1.1, 5.45 + row * 1.7], [6, 1.1, .16], walnut);
    }
    box('gallery-rail', [0, 1, 3], [18, .12, .15], brass);
    for (let x = -9; x <= 9; x += 1.5) box(`rail-${x}`, [x, .5, 3], [.08, 1, .08], walnut);

    const positions = {
        judge: [0, .6, -9.3],
        prosecutor: [-3.8, 0, .7],
        victim_advocate: [-2.0, 0, .7],
        victim_attorney: [-2.0, 0, .7],
        victim: [-5.9, 0, 1.5],
        defense_advocate: [2.0, 0, .7],
        defense_attorney: [2.0, 0, .7],
        defendant: [5.9, 0, 1.5],
        witness_1: [6.5, 0, -6.5],
        witness_2: [6.5, 0, -3.5],
        witness_3: [6.5, 0, -2.0],
        witness: [6.5, 0, -6.5],
        court_clerk: [-6.5, 0, -5.1],
        claimant: [-5.9, 0, 1.5],
        expert: [6.5, 0, -6.5],
        expert_witness: [6.5, 0, -6.5],
        interpreter: [-5, 0, .7],
    };
    const getPositionForRole = role => {
        if (positions[role]) return positions[role];
        if (role.startsWith('observer_')) {
            const id = parseInt(role.replace(/\D/g, '') || '0', 10);
            const side = (id % 2 === 0) ? -1 : 1;
            const pairIndex = Math.floor(id / 2);
            const row = pairIndex % 6;
            const col = Math.floor(pairIndex / 6);
            const pos = [side * (3.5 + col * 1.3), 0, 5.0 + row * 1.7];
            positions[role] = pos;
            return pos;
        }
        return [0, 0, 5];
    };
    const avatars = new Map();
    const avatarRigs = new Map();
    const eyes = material('avatar-eyes', '#192430', .6);
    const hair = material('avatar-hair', '#29231f', .95);
    roles.forEach((role, index) => {
        const node = new TransformNode(`participant-${role}`, scene);
        node.position.set(...getPositionForRole(role));
        const suit = material(`suit-${role}`, role === 'judge' ? '#171b22' : '#26374c', .9);
        const torso = MeshBuilder.CreateCapsule(`torso-${role}`, { height: .9, radius: .26, tessellation: 12 }, scene);
        torso.parent = node;
        torso.position.y = 1.25;
        torso.material = suit;
        const head = MeshBuilder.CreateSphere(`head-${role}`, { diameter: .4, segments: 12 }, scene);
        head.parent = node;
        head.position.y = 1.95;
        head.scaling.set(.82, 1.12, .9);
        head.material = skin;
        const facing = ['judge', 'witness', 'witness_1', 'witness_2', 'witness_3', 'court_clerk', 'expert', 'expert_witness'].includes(role) ? 1 : -1;
        const facePart = (name, diameter, position, surface) => {
            const mesh = MeshBuilder.CreateSphere(`${name}-${role}`, { diameter, segments: 12 }, scene);
            mesh.parent = node;
            mesh.position.set(...position);
            mesh.material = surface;
            return mesh;
        };
        for (const x of [-.07, .07]) facePart(`eye-${x}`, .035, [x, 1.99, facing * .16], eyes);
        facePart('nose', .055, [0, 1.93, facing * .19], skin);
        const hairMesh = facePart('hair', .37, [0, 2.08, 0], hair);
        hairMesh.scaling.y = .45;
        const mouth = facePart('mouth', .055, [0, 1.85, facing * .165], eyes);
        mouth.scaling.set(1.1, .14, .3);
        const rightArm = new TransformNode(`arm-pivot-${role}`, scene);
        rightArm.parent = node;
        rightArm.position.set(.26, 1.6, 0);
        const arm = MeshBuilder.CreateCapsule(`arm-${role}`, { height: .62, radius: .09, tessellation: 10 }, scene);
        arm.parent = rightArm;
        arm.position.y = -.27;
        arm.material = suit;
        facePart('hand', .14, [.3, .98, 0], skin);
        for (const side of [-1, 1]) {
            const leg = MeshBuilder.CreateCapsule(`leg-${side}-${role}`, { height: .65, radius: .105, tessellation: 10 }, scene);
            leg.parent = node;
            leg.position.set(side * .15, .5, 0);
            leg.material = suit;
            shadows.addShadowCaster(leg);
        }
        shadows.addShadowCaster(torso);
        shadows.addShadowCaster(head);
        shadows.addShadowCaster(arm);
        avatarRigs.set(role, { node, head, mouth, rightArm, torso });
        avatars.set(role, node);
        if (typeof document !== 'undefined' && participantNames[role]) {
            const label = MeshBuilder.CreatePlane(`nameplate-${role}`, { width: 1.85, height: .42 }, scene);
            label.parent = node;
            label.position.y = 2.18;
            label.billboardMode = 7;
            label.isPickable = false;
            label.metadata = { courtroomLabel: true };
            const texture = new DynamicTexture(`nameplate-text-${role}`, { width: 768, height: 170 }, scene, true);
            const surface = material(`nameplate-surface-${role}`, '#ffffff');
            surface.albedoTexture = texture;
            surface.unlit = true;
            label.material = surface;
            const rig = avatarRigs.get(role);
            rig.label = label;
            rig.updateNameplate = (isSpeaking = false, isMicOn = false) => {
                const ctx = texture.getContext();
                ctx.clearRect(0, 0, 768, 170);
                ctx.fillStyle = isSpeaking ? 'rgba(16, 185, 129, 0.95)' : 'rgba(15, 23, 42, 0.88)';
                if (typeof ctx.roundRect === 'function') {
                    ctx.beginPath();
                    ctx.roundRect(4, 4, 760, 162, 20);
                    ctx.fill();
                } else {
                    ctx.fillRect(4, 4, 760, 162);
                }
                ctx.lineWidth = 4;
                ctx.strokeStyle = isSpeaking ? '#34d399' : '#334155';
                ctx.strokeRect(4, 4, 760, 162);

                ctx.font = 'bold 44px sans-serif';
                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                const nameText = participantNames[role] || role;
                ctx.fillText(nameText.length > 22 ? nameText.slice(0, 20) + '…' : nameText, 384, 55);

                ctx.font = 'bold 30px sans-serif';
                ctx.fillStyle = isSpeaking ? '#ecfdf5' : '#94a3b8';
                const micStatus = isSpeaking ? '● GAPIRMOQDA' : (isMicOn ? '● MIKROFON YONIQ' : '');
                const roleLabel = role.startsWith('observer_') ? 'Kuzatuvchi' : role;
                const subText = micStatus ? `${roleLabel} · ${micStatus}` : roleLabel;
                ctx.fillText(subText, 384, 115);
                texture.update();
            };
            rig.updateNameplate(false, false);
        }
    });
    return { scene, camera, shadows, ambientOcclusion, avatars, avatarRigs, positions, showEvidence, evidenceScreen };
}

export async function initialiseCourtroomScene(stage) {
    const canvas = stage.querySelector('#courtroom-canvas');
    const loading = stage.querySelector('[data-scene-loading]');
    const fallback = stage.querySelector('[data-scene-fallback]');
    const status = stage.querySelector('[data-scene-status]');
    const session = stage.closest('[data-courtroom-session]');
    const controls = new AbortController();
    const { signal } = controls;
    let engine;
    let world;
    let disposed = false;
    const dispose = () => {
        if (disposed) return;
        disposed = true;
        controls.abort();
        world?.scene.dispose();
        engine?.dispose();
    };
    const fail = () => {
        dispose();
        canvas.hidden = true;
        loading.hidden = true;
        fallback.hidden = false;
        if (status) status.textContent = 'Matnli rejim';
    };
    try {
        engine = new Engine(canvas, true, { stencil: true, preserveDrawingBuffer: false, disableWebGL2Support: false });
        if (engine.webGLVersion < 2) { fail(); return; }
        const participants = new Map([...session.querySelectorAll('[data-participant], [data-scene-participant]')].map(item => [item.dataset.participant ?? item.dataset.sceneParticipant, item]));
        const roles = [...participants.keys()];
        const participantNames = Object.fromEntries([...participants].map(([role, item]) => [role, item.querySelector('strong')?.textContent ?? role]));
        world = createCourtroom(engine, roles, participantNames);
        const { scene, camera, shadows, positions, avatarRigs } = world;
        let loadedAvatars = new Map();
        let audioRole = null;
        let speakerRole = null;
        let audioLevel = 0;
        let audioShape = null;
        let gestureState = null;
        window.addEventListener('courtroom-audio', event => {
            audioRole = event.detail.role;
            audioLevel = event.detail.level ?? 0;
            audioShape = event.detail.shape ?? null;
        }, { signal });
        window.addEventListener('courtroom-gesture', event => {
            const { role, type } = event.detail;
            if (Object.hasOwn(courtroomGestures, type)) gestureState = { role, type, started: performance.now() };
        }, { signal });
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let autoCamera = session.dataset.autoCamera === 'true';
        let freeCamera = false;
        let targetPosition = new Vector3(0, 4.7, 7);
        let targetLook = new Vector3(0, 1.7, -3);
        const rolePosition = positions[session.dataset.studentRole] ?? [0, 0, 1];
        const isForwardRole = ['judge', 'witness', 'witness_1', 'witness_2', 'witness_3', 'court_clerk'].includes(session.dataset.studentRole);
        const presets = {
            wide: [[0, 5.2, 8.5], [0, 1.8, -2.5]],
            judge: [[0, 2.65, -8.6], [0, 1.6, 2]],
            counsel: [[0, 4, 7.5], [0, 1.4, -1.5]],
            evidence: [[1.2, 4, -7], [8.3, 4, -7]],
            defendant: [[4.8, 1.9, -1.2], [5.9, 1.3, 1.5]],
            witness: [[5.0, 2.1, -5.2], [6.5, 1.6, -6.5]],
            observer: [[0, 2.4, 4.5], [0, 2.0, -7.5]],
            first: [[rolePosition[0], rolePosition[1] + 1.85, rolePosition[2] + (isForwardRole ? .3 : -.3)], isForwardRole ? [0, 2, 2] : [0, 2, -7]],
            third: [[rolePosition[0] + (isForwardRole ? -1.8 : 1.8), rolePosition[1] + 3.2, rolePosition[2] + (isForwardRole ? -3.5 : 3.5)], rolePosition],
        };
        const focus = (eye, target) => {
            targetPosition = Vector3.FromArray(eye);
            targetLook = Vector3.FromArray(target);
            if (reducedMotion.matches) { camera.setTarget(targetLook); camera.setPosition(targetPosition); }
        };
        const evidenceItems = courtroomEvidence(session);
        const updateBoard = (id, shouldFocus = false) => {
            const index = evidenceItems.findIndex(item => item.id === String(id));
            if (evidenceItems.length && index < 0) return;
            world.showEvidence(evidenceItems[index] ?? null, index, evidenceItems.length);
            if (shouldFocus) {
                freeCamera = false;
                camera.detachControl();
                focus(...presets.evidence);
                stage.querySelectorAll('[data-camera]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.camera === 'evidence')));
            }
        };
        updateBoard(session.dataset.boardEvidenceId ?? evidenceItems[0]?.id);
        window.addEventListener('courtroom-evidence', event => updateBoard(event.detail.id, event.detail.focus), { signal });
        scene.onPointerObservable.add(info => {
            if (info.type === PointerEventTypes.POINTERPICK && info.pickInfo?.pickedMesh === world.evidenceScreen) {
                [...session.querySelectorAll('[data-evidence-open]')].find(button => button.dataset.evidenceId === session.dataset.boardEvidenceId)?.click();
            }
        });
        session.querySelectorAll('[data-focus-participant]').forEach(item => {
            const focusParticipant = () => {
                const position = positions[item.dataset.focusParticipant];
                if (!position) return;
                freeCamera = false;
                camera.detachControl();
                const isForward = ['judge', 'witness', 'witness_1', 'witness_2', 'witness_3', 'court_clerk', 'expert', 'expert_witness'].includes(item.dataset.focusParticipant);
                const direction = isForward ? 1 : -1;
                focus([position[0] + .65, position[1] + 1.9, position[2] + 2.8 * direction], [position[0], position[1] + 1.35, position[2]]);
                stage.querySelectorAll('[data-camera]').forEach(button => button.setAttribute('aria-pressed', 'false'));
            };
            item.addEventListener('click', focusParticipant, { signal });
            item.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                focusParticipant();
            }, { signal });
        });
        stage.querySelectorAll('[data-camera]').forEach(button => button.addEventListener('click', () => {
            stage.querySelectorAll('[data-camera]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
            freeCamera = button.dataset.camera === 'free';
            if (freeCamera) camera.attachControl(canvas, true);
            else { camera.detachControl(); focus(...(presets[button.dataset.camera] ?? presets.wide)); }
        }, { signal }));
        stage.querySelector('[data-auto-camera]')?.addEventListener('click', event => {
            autoCamera = !autoCamera;
            event.currentTarget.setAttribute('aria-pressed', String(autoCamera));
        }, { signal });
        window.addEventListener('courtroom-camera', event => {
            if (!freeCamera) focus(...(presets[event.detail.camera] ?? presets.wide));
        }, { signal });
        window.addEventListener('courtroom-speaker', event => {
            speakerRole = event.detail.role;
            if (!autoCamera || freeCamera || reducedMotion.matches) return;
            if (session.querySelector('[data-evidence-dialog]')?.open || stage.querySelector('[data-camera="evidence"][aria-pressed="true"]')) return;
            const position = positions[event.detail.role];
            if (position) {
                const isForward = ['judge', 'witness', 'witness_1', 'witness_2', 'witness_3', 'court_clerk', 'expert', 'expert_witness'].includes(event.detail.role);
                const direction = isForward ? 1 : -1;
                focus([position[0] + .65, position[1] + 1.9, position[2] + 2.8 * direction], [position[0], position[1] + 1.5, position[2]]);
            }
        }, { signal });
        window.addEventListener('courtroom-stage-change', event => {
            if (!autoCamera || freeCamera || reducedMotion.matches) return;
            const stageKey = (event.detail?.stage || '').toLowerCase();
            if (stageKey.includes('prosecut') || stageKey.includes('indictment')) {
                focus(...presets.counsel);
            } else if (stageKey.includes('defen')) {
                focus(...presets.defendant);
            } else if (stageKey.includes('witness') || stageKey.includes('testimony')) {
                focus(...presets.witness);
            } else if (stageKey.includes('evidence')) {
                focus(...presets.evidence);
            } else if (stageKey.includes('verdict') || stageKey.includes('deliberat') || stageKey.includes('ruling')) {
                focus(...presets.judge);
            } else {
                focus(...presets.wide);
            }
        }, { signal });
        const qualitySelect = stage.querySelector('[data-scene-quality]');
        const lowPower = window.innerWidth < 768 || navigator.hardwareConcurrency <= 4;
        let quality = qualitySelect?.value ?? 'auto';
        let slowFrames = 0;
        let appliedQuality;
        let appliedAmbientQuality;
        let lastFpsUpdate = 0;
        const applyQuality = level => {
            appliedQuality = level;
            if (status) status.dataset.appliedQuality = level;
            const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
            const fullHdScaling = Math.max(.25, Math.min(1 / pixelRatio, canvas.clientWidth / 1920, canvas.clientHeight / 1080));
            const settings = { low: [1, false, 512], medium: [1 / Math.min(pixelRatio, 1.5), true, 1024], high: [fullHdScaling, true, 2048], ultra: [Math.max(.25, fullHdScaling * .75), true, 4096] };
            const [scaling, enabled, size] = settings[level];
            engine.setHardwareScalingLevel(scaling);
            scene.shadowsEnabled = enabled;
            if (world.ambientOcclusion && level !== appliedAmbientQuality) {
                const manager = scene.postProcessRenderPipelineManager;
                if (level === 'low' || level === 'medium') manager.detachCamerasFromRenderPipeline(world.ambientOcclusion.name, camera);
                else manager.attachCamerasToRenderPipeline(world.ambientOcclusion.name, camera, true);
                appliedAmbientQuality = level;
            }
            shadows.mapSize = size;
            engine.resize();
        };
        applyQuality(quality === 'auto' ? (lowPower ? 'medium' : 'high') : quality);
        qualitySelect?.addEventListener('change', () => {
            quality = qualitySelect.value;
            slowFrames = 0;
            applyQuality(quality === 'auto' ? (lowPower ? 'medium' : 'high') : quality);
        }, { signal });
        let previousTalkingRole = null;
        const render = () => {
            if (disposed || document.hidden) return;
            const delta = Math.min(engine.getDeltaTime() / 1000, .1);
            if (!freeCamera) {
                const smoothing = reducedMotion.matches ? 1 : 1 - Math.exp(-delta * 4);
                const nextPosition = Vector3.Lerp(camera.position, targetPosition, smoothing);
                camera.setTarget(Vector3.Lerp(camera.target, targetLook, smoothing));
                camera.setPosition(nextPosition);
            }
            const time = performance.now();
            const talkingRole = audioRole ?? speakerRole;
            if (talkingRole !== previousTalkingRole) {
                if (previousTalkingRole && avatarRigs.has(previousTalkingRole)) {
                    avatarRigs.get(previousTalkingRole).updateNameplate?.(false, false);
                }
                if (talkingRole && avatarRigs.has(talkingRole)) {
                    avatarRigs.get(talkingRole).updateNameplate?.(true, true);
                }
                previousTalkingRole = talkingRole;
            }
            const talker = avatarRigs.get(talkingRole)?.node.position;
            if (gestureState && time >= gestureState.started + courtroomGestures[gestureState.type].duration) gestureState = null;
            for (const [role, rig] of avatarRigs) {
                if (rig.label) {
                    const distance = Vector3.Distance(camera.position, rig.label.getAbsolutePosition());
                    const labelScale = 2 * distance * Math.tan(camera.fov / 2) * 28 / Math.max(1, canvas.clientHeight) / .3;
                    rig.label.scaling.setAll(Math.min(1.5, Math.max(.12, labelScale)));
                }
                const active = role === audioRole;
                const gesture = !reducedMotion.matches && role === gestureState?.role
                    ? { type: gestureState.type, elapsed: time - gestureState.started }
                    : null;
                const lookingAt = role === talkingRole ? null : talker;
                const participant = participants.get(role);
                const gestureType = gesture?.type ?? '';
                if (participant && participant.dataset.avatarGesture !== gestureType) participant.dataset.avatarGesture = gestureType;
                const model = loadedAvatars.get(role);
                if (model) {
                    const pose = model.animate({
                        level: audioLevel,
                        shape: audioShape,
                        active,
                        gesture,
                        speaking: speakerRole === role,
                        lookingAt,
                        reducedMotion: reducedMotion.matches,
                        time,
                    });
                    if (participant && participant.dataset.avatarPose !== pose) participant.dataset.avatarPose = pose;
                    continue;
                }
                rig.naturalMotion ??= createNaturalMotion(role);
                const life = rig.naturalMotion(time, { speaking: active || speakerRole === role, level: audioLevel, listening: Boolean(lookingAt), reducedMotion: reducedMotion.matches });
                rig.mouth.scaling.y = active ? .14 + Math.min(1, audioLevel * 5) : .14;
                rig.head.rotation.x = life.headPitch;
                rig.head.rotation.z = life.headRoll;
                rig.torso.rotation.x = life.spinePitch;
                rig.torso.rotation.z = life.spineRoll;
                if (!reducedMotion.matches) {
                    rig.rightArm.rotation.z = gesture ? -.25 - Math.sin(Math.PI * gesture.elapsed / courtroomGestures[gesture.type].duration) * .48 : -.05 - life.rightLift;
                    const heading = role === 'judge' ? 0 : Math.PI;
                    rig.head.rotation.y += (gazeYaw(heading, rig.node.position, lookingAt) + life.headYaw - rig.head.rotation.y) * (1 - Math.exp(-delta * 8));
                } else {
                    rig.rightArm.rotation.z = -.05;
                    rig.head.rotation.y = 0;
                }
            }
            scene.render();
            const fps = engine.getFps();
            if (quality === 'auto' && appliedQuality !== 'low' && fps > 0 && fps < 28) slowFrames++; else slowFrames = 0;
            if (slowFrames === 150) {
                applyQuality(appliedQuality === 'high' ? 'medium' : 'low');
                slowFrames = 0;
            }
            if (status && time - lastFpsUpdate >= 500) {
                status.textContent = `${Math.round(fps)} FPS`;
                lastFpsUpdate = time;
            }
        };
        window.addEventListener('resize', () => engine.resize(), { signal });
        window.addEventListener('pagehide', dispose, { signal, once: true });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) engine.stopRenderLoop(render);
            else engine.runRenderLoop(render);
        }, { signal });
        canvas.addEventListener('webglcontextlost', fail, { signal });
        await scene.whenReadyAsync();
        if (disposed) return;
        loading.hidden = true;
        fallback.hidden = true;
        engine.resize();
        engine.runRenderLoop(render);
        if (session.dataset.avatarMale || session.dataset.avatarFemale) {
            import('./courtroom-avatars.js')
                .then(module => module.loadCourtroomAvatars(world, {
                    male: session.dataset.avatarMale,
                    female: session.dataset.avatarFemale,
                }, signal, {
                    animateEntrance: session.dataset.avatarEntrance === 'true',
                    onLoad: role => {
                        const participant = participants.get(role);
                        if (participant) participant.dataset.avatarLoaded = 'true';
                    },
                }))
                .then(models => { loadedAvatars = models; })
                .catch(() => {});
        }
    } catch {
        fail();
    }
}
