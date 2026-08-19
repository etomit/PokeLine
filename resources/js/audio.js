import {Midi} from '@tonejs/midi';
import menuMidiUrl from '../audio/music/Jubilife City.mid?url';
import soloMidiUrl from '../audio/music/Battle (Trainer).mid?url';
import localMidiUrl from '../audio/music/Battle (Barry).mid?url';
import onlineMidiUrl from '../audio/music/Battle (Frontier Brain).mid?url';
import victoryMidiUrl from '../audio/music/Victory (Trainer).mid?url';
import defeatMidiUrl from '../audio/music/Natural Disaster.mid?url';

export const midiMusicThemes = {
    menu: menuMidiUrl,
    'battle-solo': {url: soloMidiUrl, loopStart: 3.913},
    'battle-local': {url: localMidiUrl, loopStart: 3.934},
    'battle-online': {url: onlineMidiUrl, loopStart: 3.789},
    victory: victoryMidiUrl,
    defeat: defeatMidiUrl,
};

const AudioContextClass = () => window.AudioContext || window.webkitAudioContext;
const midiFrequency = midi => 440 * (2 ** ((midi - 69) / 12));
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

const makeNoiseBuffer = context => {
    if (context.__pokelineNoise) return context.__pokelineNoise;
    const buffer = context.createBuffer(1, context.sampleRate, context.sampleRate);
    const data = buffer.getChannelData(0);
    for (let index = 0; index < data.length; index++) data[index] = Math.random() * 2 - 1;
    context.__pokelineNoise = buffer;
    return buffer;
};

const scheduleNoise = (context, output, start, duration, volume, frequency, type = 'highpass') => {
    const source = context.createBufferSource();
    const filter = context.createBiquadFilter();
    const gain = context.createGain();
    source.buffer = makeNoiseBuffer(context);
    filter.type = type;
    filter.frequency.setValueAtTime(frequency, start);
    gain.gain.setValueAtTime(Math.max(.0001, volume), start);
    gain.gain.exponentialRampToValueAtTime(.0001, start + duration);
    source.connect(filter).connect(gain).connect(output);
    source.start(start);
    source.stop(start + duration + .02);
};

const scheduleKick = (context, output, start, volume = .12, pitch = 145) => {
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(pitch, start);
    oscillator.frequency.exponentialRampToValueAtTime(42, start + .12);
    gain.gain.setValueAtTime(volume, start);
    gain.gain.exponentialRampToValueAtTime(.0001, start + .15);
    oscillator.connect(gain).connect(output);
    oscillator.start(start);
    oscillator.stop(start + .17);
};

const scheduleDrum = (context, output, note, start) => {
    const velocity = clamp(note.velocity || .7, .15, 1);
    if (note.midi === 35 || note.midi === 36) {
        scheduleKick(context, output, start, .105 * velocity, note.midi === 35 ? 125 : 155);
        return;
    }
    if ([38, 39, 40].includes(note.midi)) {
        scheduleNoise(context, output, start, .14, .055 * velocity, 1050);
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'triangle';
        oscillator.frequency.setValueAtTime(190, start);
        gain.gain.setValueAtTime(.04 * velocity, start);
        gain.gain.exponentialRampToValueAtTime(.0001, start + .1);
        oscillator.connect(gain).connect(output);
        oscillator.start(start);
        oscillator.stop(start + .12);
        return;
    }
    if ([41, 43, 45, 47, 48, 50].includes(note.midi)) {
        scheduleKick(context, output, start, .045 * velocity, 85 + (note.midi - 41) * 12);
        return;
    }
    const openHat = [46, 49, 51, 52, 55, 57, 59].includes(note.midi);
    scheduleNoise(context, output, start, openHat ? .18 : .045, (openHat ? .035 : .025) * velocity, openHat ? 4300 : 5900);
};

const synthProfile = family => ({
    bass: ['triangle', .046, .008, .12],
    brass: ['sawtooth', .026, .012, .09],
    ensemble: ['sawtooth', .018, .045, .2],
    strings: ['sawtooth', .019, .035, .18],
    organ: ['square', .017, .01, .08],
    pipe: ['sine', .04, .025, .12],
    reed: ['square', .023, .025, .12],
    guitar: ['sawtooth', .024, .008, .16],
    piano: ['triangle', .035, .006, .22],
    'chromatic percussion': ['sine', .045, .004, .28],
}[family] || ['triangle', .025, .012, .16]);

const scheduleMidiNote = (context, output, event, start) => {
    if (event.percussion) {
        scheduleDrum(context, output, event, start);
        return;
    }
    const [waveform, baseGain, attack, release] = synthProfile(event.family);
    const duration = clamp(event.duration || .12, .045, 8);
    const stop = start + duration + release;
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.type = waveform;
    oscillator.frequency.setValueAtTime(midiFrequency(event.midi), start);
    gain.gain.setValueAtTime(.0001, start);
    gain.gain.exponentialRampToValueAtTime(Math.max(.002, baseGain * clamp(event.velocity || .7, .12, 1)), start + attack);
    gain.gain.setValueAtTime(Math.max(.001, baseGain * .62 * clamp(event.velocity || .7, .12, 1)), start + Math.max(attack, duration * .72));
    gain.gain.exponentialRampToValueAtTime(.0001, stop);
    oscillator.connect(gain).connect(output);
    oscillator.start(start);
    oscillator.stop(stop + .02);
};

let musicContext = null;
let musicMaster = null;
let musicTimer = null;
let activeMusicTheme = null;
let musicGeneration = 0;
let requestedMusicVolume = .65;

export const stopMidiMusic = () => {
    musicGeneration += 1;
    if (musicTimer) window.clearInterval(musicTimer);
    musicTimer = null;
    activeMusicTheme = null;
    const context = musicContext;
    const master = musicMaster;
    musicContext = null;
    musicMaster = null;
    if (!context || context.state === 'closed') return;
    const now = context.currentTime;
    master?.gain.cancelScheduledValues(now);
    master?.gain.setValueAtTime(Math.max(.0001, master.gain.value), now);
    master?.gain.exponentialRampToValueAtTime(.0001, now + .14);
    window.setTimeout(() => context.close().catch(() => {}), 180);
};

export const setMidiMusicVolume = volume => {
    requestedMusicVolume = clamp(Number(volume), 0, 1);
    if (!musicMaster || !musicContext) return;
    const now = musicContext.currentTime;
    musicMaster.gain.cancelScheduledValues(now);
    musicMaster.gain.setTargetAtTime(Math.max(.0001, requestedMusicVolume), now, .025);
};

export const startMidiMusic = async themeName => {
    const theme = midiMusicThemes[themeName];
    const source = typeof theme === 'string' ? theme : theme?.url;
    const Context = AudioContextClass();
    if (!source || !Context) return;
    if (activeMusicTheme === themeName && musicContext) {
        setMidiMusicVolume(requestedMusicVolume);
        if (musicContext.state === 'suspended') await musicContext.resume().catch(() => {});
        return;
    }

    stopMidiMusic();
    const generation = musicGeneration;
    const context = new Context();
    const master = context.createGain();
    const compressor = context.createDynamicsCompressor();
    compressor.threshold.value = -20;
    compressor.knee.value = 12;
    compressor.ratio.value = 5;
    master.gain.value = Math.max(.0001, requestedMusicVolume);
    master.connect(compressor).connect(context.destination);
    musicContext = context;
    musicMaster = master;
    activeMusicTheme = themeName;
    await context.resume().catch(() => {});

    try {
        const response = await fetch(source);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const midi = new Midi(await response.arrayBuffer());
        if (generation !== musicGeneration || context !== musicContext) return;
        const events = midi.tracks.flatMap(track => track.notes.map(note => ({
            time: note.time,
            duration: note.duration,
            midi: note.midi,
            velocity: note.velocity,
            family: track.instrument.family,
            percussion: track.instrument.percussion || track.channel === 9,
        }))).sort((first, second) => first.time - second.time);
        if (!events.length) return;

        const loopDuration = Math.max(midi.duration, events.at(-1).time + events.at(-1).duration, 1);
        const loopStart = clamp(Number(typeof theme === 'object' ? theme.loopStart : 0), 0, Math.max(0, loopDuration - .5));
        const loopEvents = events.filter(event => event.time >= loopStart);
        const repeatingEvents = loopEvents.length ? loopEvents : events;
        const repeatingDuration = loopEvents.length ? loopDuration - loopStart : loopDuration;
        const startedAt = context.currentTime + .08;
        let eventIndex = 0;
        let cycle = 0;
        const schedule = () => {
            if (context !== musicContext || generation !== musicGeneration) return;
            const horizon = context.currentTime + .35;
            while (true) {
                const cycleEvents = cycle === 0 ? events : repeatingEvents;
                const event = cycleEvents[eventIndex];
                const cycleStart = cycle === 0
                    ? startedAt
                    : startedAt + loopDuration + (cycle - 1) * repeatingDuration;
                const eventTime = cycleStart + event.time - (cycle === 0 ? 0 : loopStart);
                if (eventTime > horizon) break;
                if (eventTime >= context.currentTime - .02) scheduleMidiNote(context, master, event, eventTime);
                eventIndex += 1;
                if (eventIndex >= cycleEvents.length) {
                    eventIndex = 0;
                    cycle += 1;
                }
            }
        };
        schedule();
        musicTimer = window.setInterval(schedule, 80);
    } catch (error) {
        console.error(`Unable to play MIDI theme "${themeName}".`, error);
        if (context === musicContext) stopMidiMusic();
    }
};

let effectsContext = null;
let effectsMaster = null;
let lastEffectAt = new Map();
const effectsVolumeBoost = 1.4;

const effectsOutput = volume => {
    const Context = AudioContextClass();
    if (!Context) return null;
    if (!effectsContext || effectsContext.state === 'closed') {
        effectsContext = new Context();
        effectsMaster = effectsContext.createGain();
        const compressor = effectsContext.createDynamicsCompressor();
        compressor.threshold.value = -16;
        compressor.ratio.value = 7;
        effectsMaster.connect(compressor).connect(effectsContext.destination);
    }
    effectsMaster.gain.setValueAtTime(clamp(volume * effectsVolumeBoost, .0001, 1), effectsContext.currentTime);
    if (effectsContext.state === 'suspended') effectsContext.resume().catch(() => {});
    return [effectsContext, effectsMaster];
};

export const playGameSound = (name, volume = .75) => {
    if (volume <= 0) return;
    const requestedProfile = String(name || 'normal').toLowerCase();
    const attackMatch = requestedProfile.match(/^attack-([a-z]+)-(physical|special|status)$/);
    const profile = attackMatch?.[1] || requestedProfile;
    const attackClass = attackMatch?.[2] || null;
    const nowMs = performance.now();
    const cooldown = profile === 'collision' ? 170 : profile === 'hover' ? 90 : 0;
    if (cooldown && nowMs - (lastEffectAt.get(profile) || 0) < cooldown) return;
    lastEffectAt.set(profile, nowMs);
    const output = effectsOutput(volume);
    if (!output) return;
    const [context, master] = output;
    const now = context.currentTime + .006;

    const tone = (waveform, from, to, duration, gainValue, delay = 0, detune = 0) => {
        const start = now + delay;
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = waveform;
        oscillator.frequency.setValueAtTime(Math.max(1, from), start);
        oscillator.frequency.exponentialRampToValueAtTime(Math.max(1, to), start + duration);
        oscillator.detune.setValueAtTime(detune, start);
        gain.gain.setValueAtTime(.0001, start);
        gain.gain.exponentialRampToValueAtTime(gainValue, start + Math.min(.012, duration / 3));
        gain.gain.exponentialRampToValueAtTime(.0001, start + duration);
        oscillator.connect(gain).connect(master);
        oscillator.start(start);
        oscillator.stop(start + duration + .02);
    };
    const burst = (duration, gainValue, frequency, delay = 0) => scheduleNoise(context, master, now + delay, duration, gainValue, frequency);

    if (attackClass === 'physical') {
        tone('triangle', 175, 68, .16, .075);
        burst(.09, .04, 780, .025);
    } else if (attackClass === 'special') {
        tone('sine', 360, 920, .24, .055);
        tone('triangle', 710, 280, .2, .035, .04);
    } else if (attackClass === 'status') {
        tone('sine', 260, 680, .34, .05);
        tone('sine', 690, 310, .3, .032, .06, 9);
    }

    switch (profile) {
        case 'collision':
            tone('square', 145, 82, .1, .075);
            burst(.055, .035, 720);
            break;
        case 'confirm':
        case 'open':
            tone('square', 523, 784, .09, .045);
            tone('square', 784, 1047, .11, .035, .075);
            break;
        case 'cancel':
        case 'close':
            tone('square', 520, 260, .16, .05);
            break;
        case 'notice':
            tone('sine', 660, 880, .13, .045);
            break;
        case 'select':
            tone('square', 420, 510, .065, .032);
            break;
        case 'navigate':
            tone('triangle', 330, 560, .11, .04);
            tone('square', 560, 690, .08, .024, .07);
            break;
        case 'settings':
            tone('square', 310, 390, .07, .035);
            tone('square', 440, 540, .07, .032, .075);
            tone('square', 590, 720, .09, .028, .15);
            break;
        case 'search':
            tone('sine', 240, 920, .24, .055);
            burst(.12, .025, 4300, .08);
            break;
        case 'page':
            tone('square', 620, 780, .06, .035);
            tone('square', 780, 620, .06, .028, .065);
            break;
        case 'pokedex-entry':
            tone('square', 440, 660, .08, .038);
            tone('square', 660, 990, .12, .034, .075);
            break;
        case 'party-add':
            [0, .065, .13].forEach((delay, index) => tone('triangle', 390 + index * 120, 520 + index * 150, .11, .034, delay));
            break;
        case 'remove':
            tone('square', 460, 180, .15, .05);
            burst(.07, .025, 950, .04);
            break;
        case 'toggle':
            tone('square', 360, 540, .075, .038);
            tone('square', 540, 420, .07, .025, .07);
            break;
        case 'battle-command':
            tone('square', 190, 320, .09, .055);
            burst(.055, .025, 1500, .055);
            break;
        case 'faint':
            [0, .12, .24, .36].forEach((delay, index) => tone('square', 520 - index * 85, 230 - index * 35, .2, .095, delay));
            tone('triangle', 180, 38, .72, .14, .08);
            burst(.34, .05, 650, .32);
            break;
        case 'critical-impact':
            tone('square', 170, 42, .25, .16);
            tone('sawtooth', 380, 65, .18, .08, .015);
            burst(.16, .1, 720);
            break;
        case 'super-effective-impact':
            tone('square', 210, 44, .27, .15);
            tone('sawtooth', 520, 92, .2, .075, .012);
            burst(.18, .09, 820);
            break;
        case 'resisted-impact':
            tone('triangle', 115, 72, .14, .065);
            burst(.07, .025, 1250);
            break;
        case 'impact':
            tone('square', 135, 48, .18, .13);
            burst(.11, .07, 900);
            break;
        case 'immune':
            tone('square', 240, 430, .09, .055);
            tone('square', 430, 215, .13, .045, .08);
            break;
        case 'miss':
            burst(.2, .04, 3600);
            tone('sine', 520, 180, .2, .035);
            break;
        case 'protect':
            tone('triangle', 330, 760, .18, .065);
            tone('sine', 920, 610, .2, .04, .08);
            break;
        case 'heal':
            [0, .075, .15, .225, .3].forEach((delay, index) => tone('sine', 440 + index * 95, 610 + index * 120, .16, .05, delay));
            break;
        case 'switch':
            tone('triangle', 150, 720, .3, .1);
            tone('square', 300, 1040, .22, .032, .08);
            break;
        case 'status':
            tone('sine', 250, 820, .36, .075);
            tone('square', 530, 370, .28, .032, .05, 7);
            break;
        case 'fire':
            tone('sawtooth', 340, 82, .34, .11);
            burst(.28, .07, 1100);
            break;
        case 'water':
            tone('sine', 170, 790, .34, .12);
            tone('triangle', 650, 205, .27, .065, .08);
            break;
        case 'electric':
            [0, .05, .1, .15].forEach((delay, index) => tone('square', index % 2 ? 1320 : 700, index % 2 ? 590 : 1550, .065, .068, delay));
            burst(.2, .03, 4400);
            break;
        case 'grass':
        case 'bug':
            tone('triangle', 350, 960, .3, .085);
            tone('square', 760, 420, .2, .038, .07);
            break;
        case 'ice':
            [0, .07, .14].forEach((delay, index) => tone('sine', 760 + index * 180, 1150 + index * 230, .18, .052, delay));
            burst(.18, .022, 6200);
            break;
        case 'psychic':
        case 'fairy':
            tone('sine', 280, 1160, .38, .09);
            tone('sine', 900, 320, .38, .06, 0, 11);
            break;
        case 'ghost':
        case 'dark':
            tone('sawtooth', 215, 52, .42, .085);
            tone('sine', 490, 120, .36, .05, .04, -16);
            break;
        case 'poison':
            tone('sawtooth', 185, 350, .36, .08);
            tone('square', 115, 76, .32, .05, .06);
            break;
        case 'flying':
            tone('triangle', 240, 1080, .27, .09);
            burst(.18, .03, 3700, .05);
            break;
        case 'dragon':
            tone('sawtooth', 140, 760, .38, .12);
            tone('square', 320, 90, .3, .06, .08);
            break;
        case 'rock':
        case 'ground':
        case 'steel':
        case 'fighting':
            tone('square', profile === 'steel' ? 410 : 145, 46, .28, .13);
            burst(.18, .08, profile === 'steel' ? 2800 : 650);
            break;
        default:
            tone('square', 270, 100, .26, .1);
            tone('triangle', 400, 165, .2, .045, .04);
    }
};
