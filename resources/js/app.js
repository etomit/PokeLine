import './bootstrap';

const settingsDialog = document.querySelector('#settings-dialog');
const settingsButton = document.querySelector('#settings-open');
const soundSetting = document.querySelector('#global-sound');
const musicSetting = document.querySelector('#global-music');
const soundVolumeSetting = document.querySelector('#global-sound-volume');
const musicVolumeSetting = document.querySelector('#global-music-volume');
const storedVolume = (key, fallback) => {
    const value = Number(localStorage.getItem(key));
    return Number.isFinite(value) && value >= 0 && value <= 1 ? value : fallback;
};
let soundEnabled = localStorage.getItem('pokeline_sound') !== 'off';
let musicEnabled = localStorage.getItem('pokeline_music') !== 'off';
let soundVolume = storedVolume('pokeline_sound_volume', .75);
let musicVolume = storedVolume('pokeline_music_volume', .65);

const pageMusicTheme = document.querySelector('#battle-app')
    ? 'battle'
    : document.querySelector('#world-hub, .arcade-setup, .online-center-room, .online-team-page, .pokedex-page') ? 'menu' : null;
const musicThemes = {
    menu: {
        tempo: 116,
        drumStyle: 'light',
        lead: [72, 76, 79, 76, 74, 77, 81, 77, 72, 76, 79, 83, 81, 79, 76, null, 69, 72, 76, 72, 71, 74, 77, 74, 69, 72, 76, 79, 77, 74, 72, null],
        harmony: [64, null, 67, null, 65, null, 69, null, 64, null, 67, null, 69, null, 67, null, 60, null, 64, null, 62, null, 65, null, 60, null, 64, null, 65, null, 64, null],
        bass: [48, null, 48, null, 53, null, 53, null, 48, null, 48, null, 55, null, 55, null, 45, null, 45, null, 50, null, 50, null, 45, null, 45, null, 43, null, 48, null],
    },
    battle: {
        tempo: 158,
        drumStyle: 'drive',
        lead: [76, 76, 79, 81, 83, 81, 79, 76, 74, 74, 77, 79, 81, 79, 77, 74, 76, 79, 84, 83, 81, 79, 77, 79, 81, 84, 88, 86, 84, 83, 81, 79],
        harmony: [67, null, 71, null, 72, null, 71, null, 65, null, 69, null, 70, null, 69, null, 67, null, 72, null, 69, null, 71, null, 72, null, 76, null, 74, null, 71, null],
        bass: [40, 40, 40, 43, 45, 45, 43, 40, 38, 38, 38, 41, 43, 43, 41, 38, 40, 40, 43, 45, 47, 47, 45, 43, 45, 45, 48, 47, 45, 43, 40, 40],
    },
    victory: {
        tempo: 132,
        drumStyle: 'fanfare',
        lead: [72, 76, 79, 84, 79, 84, 88, 91, 88, 84, 79, 76, 84, 88, 91, null],
        harmony: [64, 67, 72, 76, 72, 76, 79, 84, 79, 76, 72, 67, 76, 79, 84, null],
        bass: [48, null, 52, null, 55, null, 60, null, 55, null, 52, null, 48, 55, 60, null],
    },
    defeat: {
        tempo: 92,
        drumStyle: 'slow',
        lead: [72, null, 71, null, 67, null, 64, null, 62, null, 60, null, 59, 55, 52, null],
        harmony: [64, null, 62, null, 59, null, 55, null, 53, null, 52, null, 50, 47, 43, null],
        bass: [48, null, 47, null, 43, null, 40, null, 38, null, 36, null, 35, null, 31, null],
    },
};
let musicContext = null;
let musicMaster = null;
let musicTimer = null;
let musicStep = 0;
let activeMusicTheme = null;
let musicUnlocked = Boolean(navigator.userActivation?.hasBeenActive);

const currentMusicTheme = () => {
    if (pageMusicTheme !== 'battle') return pageMusicTheme;
    const result = document.querySelector('#battle-result');
    if (!result || result.hidden) return 'battle';
    return result.classList.contains('victory') ? 'victory' : 'defeat';
};

const noteFrequency = midi => 440 * (2 ** ((midi - 69) / 12));
const playMusicNote = (context, output, midi, start, duration, waveform, volume, detune = 0) => {
    if (midi === null || midi === undefined) return;
    const oscillator = context.createOscillator();
    const envelope = context.createGain();
    oscillator.type = waveform;
    oscillator.frequency.setValueAtTime(noteFrequency(midi), start);
    oscillator.detune.setValueAtTime(detune, start);
    envelope.gain.setValueAtTime(0.0001, start);
    envelope.gain.exponentialRampToValueAtTime(volume, start + 0.018);
    envelope.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    oscillator.connect(envelope).connect(output);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.025);
};

const noiseBuffer = context => {
    if (context.__pokelineNoise) return context.__pokelineNoise;
    const buffer = context.createBuffer(1, context.sampleRate, context.sampleRate);
    const channel = buffer.getChannelData(0);
    for (let index = 0; index < channel.length; index++) channel[index] = Math.random() * 2 - 1;
    context.__pokelineNoise = buffer;
    return buffer;
};

const playNoise = (context, output, start, duration, volume, frequency) => {
    const source = context.createBufferSource();
    const filter = context.createBiquadFilter();
    const envelope = context.createGain();
    source.buffer = noiseBuffer(context);
    filter.type = 'highpass';
    filter.frequency.setValueAtTime(frequency, start);
    envelope.gain.setValueAtTime(volume, start);
    envelope.gain.exponentialRampToValueAtTime(.0001, start + duration);
    source.connect(filter).connect(envelope).connect(output);
    source.start(start);
    source.stop(start + duration);
};

const playKick = (context, output, start, volume = .12) => {
    const oscillator = context.createOscillator();
    const envelope = context.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(145, start);
    oscillator.frequency.exponentialRampToValueAtTime(42, start + .11);
    envelope.gain.setValueAtTime(volume, start);
    envelope.gain.exponentialRampToValueAtTime(.0001, start + .14);
    oscillator.connect(envelope).connect(output);
    oscillator.start(start);
    oscillator.stop(start + .15);
};

const playChiptuneDrums = (context, output, start, index, style) => {
    if (style === 'drive') {
        if (index % 4 === 0) playKick(context, output, start, .14);
        if (index % 4 === 2) playNoise(context, output, start, .11, .07, 1250);
        playNoise(context, output, start, .035, index % 2 ? .018 : .027, 5200);
        return;
    }
    if (style === 'light') {
        if (index % 8 === 0) playKick(context, output, start, .08);
        if (index % 8 === 4) playNoise(context, output, start, .09, .035, 1600);
        if (index % 2 === 0) playNoise(context, output, start, .025, .012, 6000);
        return;
    }
    if (style === 'fanfare') {
        if (index % 4 === 0) playKick(context, output, start, .1);
        if (index % 4 === 2) playNoise(context, output, start, .08, .03, 2200);
        return;
    }
    if (index % 8 === 0) playKick(context, output, start, .065);
};

const stopMusic = () => {
    const contextToClose = musicContext;
    const masterToFade = musicMaster;
    if (musicTimer) window.clearInterval(musicTimer);
    musicTimer = null;
    musicContext = null;
    musicMaster = null;
    activeMusicTheme = null;
    musicStep = 0;
    if (!contextToClose || contextToClose.state === 'closed') return;
    const now = contextToClose.currentTime;
    masterToFade?.gain.cancelScheduledValues(now);
    masterToFade?.gain.setValueAtTime(Math.max(masterToFade.gain.value, 0.0001), now);
    masterToFade?.gain.exponentialRampToValueAtTime(0.0001, now + 0.12);
    window.setTimeout(() => contextToClose.close().catch(() => {}), 150);
};

const startMusic = async themeName => {
    if (!musicEnabled || !musicUnlocked || !musicThemes[themeName] || document.hidden) return;
    if (themeName === 'menu' && document.querySelector('#game-space-dialog')?.open) return;
    if (activeMusicTheme === themeName && musicContext) {
        if (musicContext.state === 'suspended') await musicContext.resume().catch(() => {});
        return;
    }
    stopMusic();
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;
    const context = new AudioContextClass();
    const master = context.createGain();
    const compressor = context.createDynamicsCompressor();
    compressor.threshold.value = -18;
    compressor.knee.value = 10;
    compressor.ratio.value = 5;
    master.gain.value = Math.max(.0001, musicVolume);
    master.connect(compressor).connect(context.destination);
    musicContext = context;
    musicMaster = master;
    activeMusicTheme = themeName;
    const theme = musicThemes[themeName];
    const stepDuration = 60 / theme.tempo / 2;
    const scheduleStep = () => {
        if (context !== musicContext || master !== musicMaster) return;
        const start = context.currentTime + 0.025;
        const index = musicStep % theme.lead.length;
        playMusicNote(context, master, theme.lead[index], start, stepDuration * .78, 'square', .075);
        playMusicNote(context, master, theme.lead[index], start, stepDuration * .68, 'square', .018, 7);
        playMusicNote(context, master, theme.harmony[index], start, stepDuration * .7, 'square', .028, -5);
        playMusicNote(context, master, theme.bass[index], start, stepDuration * .92, 'triangle', .105);
        if (theme.bass[index] !== null && theme.bass[index] !== undefined) {
            const arpeggio = [12, 19, 24, 19][index % 4];
            playMusicNote(context, master, theme.bass[index] + arpeggio, start, stepDuration * .42, 'square', .016);
        }
        playChiptuneDrums(context, master, start, index, theme.drumStyle);
        musicStep = (musicStep + 1) % theme.lead.length;
    };
    await context.resume().catch(() => {});
    scheduleStep();
    musicTimer = window.setInterval(scheduleStep, stepDuration * 1000);
};

const updateMusicPreference = enabled => {
    musicEnabled = enabled;
    musicUnlocked = true;
    localStorage.setItem('pokeline_music', enabled ? 'on' : 'off');
    if (musicSetting) musicSetting.checked = enabled;
    if (enabled) startMusic(currentMusicTheme());
    else stopMusic();
};

const updateSoundPreference = enabled => {
    soundEnabled = enabled;
    localStorage.setItem('pokeline_sound', enabled ? 'on' : 'off');
    if (soundSetting) soundSetting.checked = enabled;
};

const updateMusicVolume = value => {
    musicVolume = Math.max(0, Math.min(1, Number(value)));
    localStorage.setItem('pokeline_music_volume', String(musicVolume));
    if (musicVolumeSetting) {
        musicVolumeSetting.value = String(Math.round(musicVolume * 100));
        musicVolumeSetting.nextElementSibling.value = `${Math.round(musicVolume * 100)}%`;
    }
    if (musicMaster && musicContext?.state !== 'closed') {
        musicMaster.gain.setTargetAtTime(Math.max(.0001, musicVolume), musicContext.currentTime, .025);
    }
};

const updateSoundVolume = value => {
    soundVolume = Math.max(0, Math.min(1, Number(value)));
    localStorage.setItem('pokeline_sound_volume', String(soundVolume));
    if (soundVolumeSetting) {
        soundVolumeSetting.value = String(Math.round(soundVolume * 100));
        soundVolumeSetting.nextElementSibling.value = `${Math.round(soundVolume * 100)}%`;
    }
};

if (pageMusicTheme) {
    const unlockMusic = () => {
        musicUnlocked = true;
        startMusic(currentMusicTheme());
    };
    window.addEventListener('pointerdown', unlockMusic, {once: true});
    window.addEventListener('keydown', unlockMusic, {once: true});
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopMusic();
        else if (musicUnlocked) startMusic(currentMusicTheme());
    });
    window.addEventListener('pagehide', stopMusic);
    if (musicUnlocked) startMusic(currentMusicTheme());
}

window.addEventListener('storage', event => {
    if (event.key === 'pokeline_music') {
        musicEnabled = event.newValue !== 'off';
        if (musicSetting) musicSetting.checked = musicEnabled;
        if (musicEnabled) startMusic(currentMusicTheme());
        else stopMusic();
    }
    if (event.key === 'pokeline_sound') updateSoundPreference(event.newValue !== 'off');
    if (event.key === 'pokeline_music_volume') updateMusicVolume(Number(event.newValue));
    if (event.key === 'pokeline_sound_volume') updateSoundVolume(Number(event.newValue));
});

if (settingsDialog && settingsButton) {
    soundSetting.checked = soundEnabled;
    musicSetting.checked = musicEnabled;
    updateMusicVolume(musicVolume);
    updateSoundVolume(soundVolume);
    settingsButton.addEventListener('click', () => settingsDialog.showModal());
    soundSetting.addEventListener('change', () => updateSoundPreference(soundSetting.checked));
    musicSetting.addEventListener('change', () => updateMusicPreference(musicSetting.checked));
    musicVolumeSetting?.addEventListener('input', () => updateMusicVolume(Number(musicVolumeSetting.value) / 100));
    soundVolumeSetting?.addEventListener('input', () => updateSoundVolume(Number(soundVolumeSetting.value) / 100));
    window.addEventListener('keydown', event => {
        if (event.key.toLowerCase() !== 'p' || ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
        event.preventDefault();
        settingsDialog.open ? settingsDialog.close() : settingsDialog.showModal();
    });
}

const hub = document.querySelector('#world-hub');
if (hub) {
    const character = document.querySelector('#hub-character');
    const prompt = document.querySelector('#hub-prompt');
    const hubConfig = JSON.parse(hub.dataset.collisionConfig);
    const destinations = {
        solo: {...hubConfig.destinations.solo.access, url: hub.dataset.solo},
        local: {...hubConfig.destinations.local.access, url: hub.dataset.local},
        online: {...hubConfig.destinations.online.access, url: hub.dataset.online},
    };
    let position = {...hubConfig.spawn};
    let direction = 'up';
    let frame = 0;
    let near = null;
    const gameDialog = document.querySelector('#game-space-dialog');
    const gameFrame = document.querySelector('#game-space-frame');
    const pressedDirections = [];
    const directionForKey = {
        arrowleft: 'left', a: 'left', q: 'left',
        arrowright: 'right', d: 'right',
        arrowup: 'up', w: 'up', z: 'up',
        arrowdown: 'down', s: 'down',
    };
    const obstacles = hubConfig.obstacles;
    const bounds = hubConfig.bounds;
    const blocked = (x, y) => x < bounds.minX || x > bounds.maxX || y < bounds.minY || y > bounds.maxY
        || obstacles.some(area => x > area.x1 && x < area.x2 && y > area.y1 && y < area.y2);

    Object.entries(hubConfig.destinations).forEach(([key, destination]) => {
        const marker = document.querySelector(`[data-destination="${key}"]`);
        marker.style.left = `${destination.marker.x}%`;
        marker.style.top = `${destination.marker.y}%`;
    });

    const displayName = key => document.querySelector(`[data-destination="${key}"]`)?.textContent.replace(/^\s*\d+/, '').trim() || key;
    const openGame = key => {
        stopMusic();
        gameFrame.src = destinations[key].url;
        gameDialog.showModal();
    };
    const update = () => {
        character.style.left = `${position.x}%`;
        character.style.top = `${position.y}%`;
        character.className = `hub-character face-${direction}${frame ? ` walk-${frame}` : ''}`;
        let closest = null;
        let closestDistance = Infinity;
        Object.entries(destinations).forEach(([key, destination]) => {
            const distance = Math.hypot(position.x - destination.x, (position.y - destination.y) * 1.7);
            if (distance < closestDistance) { closest = key; closestDistance = distance; }
        });
        near = closestDistance < 13 ? closest : null;
        document.querySelectorAll('[data-destination]').forEach(marker => marker.classList.toggle('is-near', marker.dataset.destination === near));
        prompt.textContent = near ? `ENTER / E — ${displayName(near)}` : prompt.dataset.default || prompt.textContent;
    };
    prompt.dataset.default = prompt.textContent;
    const walk = () => {
        const activeDirection = pressedDirections.at(-1);
        const delta = {left: [-.48, 0], right: [.48, 0], up: [0, -.48], down: [0, .48]}[activeDirection];
        if (delta) {
            direction = activeDirection;
            const next = {x: position.x + delta[0], y: position.y + delta[1]};
            if (!blocked(next.x, next.y)) position = next;
            frame = Math.floor(Date.now() / 150) % 2 ? 1 : 2;
            update();
        } else if (frame) { frame = 0; update(); }
        requestAnimationFrame(walk);
    };
    const ignored = () => ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement?.tagName) || settingsDialog?.open;
    window.addEventListener('keydown', event => {
        if (ignored()) return;
        const key = event.key.toLowerCase();
        if (directionForKey[key]) {
            event.preventDefault();
            if (event.repeat) return;
            const inputDirection = directionForKey[key];
            const existing = pressedDirections.indexOf(inputDirection);
            if (existing !== -1) pressedDirections.splice(existing, 1);
            pressedDirections.push(inputDirection);
        }
        if ((key === 'enter' || key === 'e') && near) { event.preventDefault(); openGame(near); }
    });
    window.addEventListener('keyup', event => {
        const released = directionForKey[event.key.toLowerCase()];
        if (!released) return;
        const index = pressedDirections.indexOf(released);
        if (index !== -1) pressedDirections.splice(index, 1);
    });
    window.addEventListener('blur', () => pressedDirections.splice(0));
    document.querySelectorAll('[data-destination]').forEach(marker => marker.addEventListener('click', () => openGame(marker.dataset.destination)));
    document.querySelector('[data-game-close]').addEventListener('click', () => gameDialog.close());
    gameDialog.addEventListener('close', () => {
        gameFrame.src = 'about:blank';
        hub.focus();
        musicEnabled = localStorage.getItem('pokeline_music') !== 'off';
        if (musicEnabled) startMusic('menu');
    });
    window.addEventListener('message', event => { if (event.origin === window.location.origin && event.data === 'pokeline:close-game') gameDialog.close(); });
    hub.addEventListener('click', () => hub.focus());
    update(); walk(); hub.focus();
}

if (window.self !== window.top) {
    document.querySelectorAll('a').forEach(link => {
        if (link.href === `${window.location.origin}/`) link.addEventListener('click', event => {
            event.preventDefault();
            window.parent.postMessage('pokeline:close-game', window.location.origin);
        });
    });
}

const pokedexDialog = document.querySelector('#pokedex-dialog');
let pokedexTarget = null;
let pokedexMode = 'replace';
document.querySelectorAll('.pokedex-picker-button').forEach(button => button.addEventListener('click', () => {
    pokedexTarget = document.getElementById(button.dataset.pokedexTarget);
    pokedexMode = button.dataset.pokedexMode || 'replace';
    pokedexDialog?.showModal();
}));

document.querySelectorAll('[data-pokedex-browser]').forEach(browser => {
    const grid = browser.querySelector('[data-pokedex-grid]');
    const search = browser.querySelector('[data-pokedex-search]');
    const submit = browser.querySelector('[data-pokedex-submit]');
    const previous = browser.querySelector('[data-pokedex-prev]');
    const next = browser.querySelector('[data-pokedex-next]');
    const pageLabel = browser.querySelector('[data-pokedex-page]');
    let page = 1;
    let lastPage = 1;

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const selectPokemon = name => {
        if (!pokedexTarget) return;
        if (pokedexMode === 'append') {
            const selected = pokedexTarget.value.split(',').map(value => value.trim()).filter(Boolean);
            if (selected.length < 6) selected.push(name);
            pokedexTarget.value = selected.join(', ');
        } else {
            pokedexTarget.value = name;
            pokedexDialog?.close();
        }
        pokedexTarget.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const load = async requestedPage => {
        browser.classList.add('is-loading');
        grid.setAttribute('aria-busy', 'true');
        grid.innerHTML = '<div class="pokedex-loading">…</div>';
        const url = new URL(browser.dataset.catalogUrl, window.location.origin);
        url.searchParams.set('page', requestedPage);
        if (search.value.trim()) url.searchParams.set('search', search.value.trim());
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            page = payload.page;
            lastPage = payload.last_page;
            pageLabel.textContent = `${page} / ${lastPage}`;
            previous.disabled = page <= 1;
            next.disabled = page >= lastPage;
            grid.innerHTML = payload.data.map((pokemon, index) => `<button type="button" class="pokedex-card" data-pokemon="${escapeHtml(pokemon.name)}" style="animation-delay:${Math.min(index * 18, 220)}ms"><span>#${String(pokemon.id).padStart(4, '0')}</span><img src="${escapeHtml(pokemon.sprite)}" alt="" loading="lazy"><strong>${escapeHtml(pokemon.label)}</strong></button>`).join('') || '<div class="pokedex-loading">—</div>';
            grid.scrollTop = 0;
            grid.querySelectorAll('[data-pokemon]').forEach(card => card.addEventListener('click', () => {
                card.classList.add('is-selected');
                selectPokemon(card.dataset.pokemon);
            }));
        } catch (error) {
            grid.innerHTML = `<div class="pokedex-loading">${escapeHtml(error.message)}</div>`;
        } finally {
            browser.classList.remove('is-loading');
            grid.removeAttribute('aria-busy');
        }
    };
    submit.addEventListener('click', () => load(1));
    search.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); load(1); } });
    previous.addEventListener('click', () => page > 1 && load(page - 1));
    next.addEventListener('click', () => page < lastPage && load(page + 1));
    load(1);
});

const pokemonPreviewCache = new Map();
document.querySelectorAll('[data-team-input]').forEach(input => {
    const preview = document.querySelector(`[data-team-preview="${input.id}"]`);
    const itemSelects = [...document.querySelectorAll(`[data-item-select="${input.id}"]`)];
    const itemsPanel = input.closest('.player-loadout').querySelector('.arcade-items');
    const typeLabels = JSON.parse(input.closest('[data-type-labels]')?.dataset.typeLabels || '{}');
    const translatedType = type => typeLabels[type] || type;
    let previewTimer;
    let renderVersion = 0;
    const escapePreview = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const emptyMarkup = index => `<span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><div class="party-data"><div class="party-name"><b>—</b><em>Lv.—</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:0"></span></i></div><div class="party-meta"><small>— / —</small><small>—</small></div></div>`;
    const loadingMarkup = (index, name) => `<span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><div class="party-data"><div class="party-name"><b>${escapePreview(name)}</b><em>…</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:35%"></span></i></div></div>`;
    const itemLabel = select => select?.selectedOptions[0]?.dataset.itemLabel || select?.selectedOptions[0]?.textContent.split(' — ')[0] || '—';
    const occupiedMarkup = (entry, index) => `<button type="button" class="party-remove" data-remove-pokemon="${index}" aria-label="×">×</button><span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><img src="${escapePreview(entry.sprites.front)}" alt=""><div class="party-data"><div class="party-name"><b>${escapePreview(entry.label)}</b><em>Lv.100</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:100%"></span></i></div><div class="party-meta"><small>${entry.stats.hp} / ${entry.stats.hp}</small><small>${entry.types.map(type => escapePreview(translatedType(type))).join(' / ')}</small></div><div class="party-item" data-party-item>${escapePreview(itemLabel(itemSelects[index]))}</div></div>`;
    const slots = Array.from({length: 6}, (_, index) => {
        const slot = document.createElement('div');
        slot.className = 'team-preview-slot empty';
        slot.dataset.previewSlot = index;
        slot.dataset.state = 'empty';
        slot.innerHTML = emptyMarkup(index);
        preview.append(slot);
        return slot;
    });
    const pokemon = async name => {
        const cached = pokemonPreviewCache.get(name);
        if (cached) return cached instanceof Promise ? cached : Promise.resolve(cached);
        const pending = fetch(`/api/pokemon/${encodeURIComponent(name)}`, {headers: {Accept: 'application/json'}})
            .then(response => response.ok ? response.json() : null)
            .catch(() => null)
            .then(entry => {
                pokemonPreviewCache.set(name, entry);
                return entry;
            });
        pokemonPreviewCache.set(name, pending);
        return pending;
    };
    const removePokemon = removeIndex => {
        const selected = input.value.split(',').map(value => value.trim()).filter(Boolean);
        selected.splice(removeIndex, 1);
        for (let index = removeIndex; index < itemSelects.length - 1; index++) itemSelects[index].value = itemSelects[index + 1].value;
        if (itemSelects.length) itemSelects.at(-1).value = '';
        input.value = selected.join(', ');
        input.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const showOccupied = (slot, entry, index, name) => {
        slot.className = 'team-preview-slot';
        slot.dataset.name = name;
        slot.dataset.state = 'ready';
        slot.dataset.partySlot = index;
        slot.innerHTML = occupiedMarkup(entry, index);
        slot.querySelector('[data-remove-pokemon]').addEventListener('click', () => removePokemon(index));
    };
    const syncOwnedItemLimits = () => {
        const selectedCounts = itemSelects.filter(select => !select.disabled && select.value).reduce((counts, select) => counts.set(select.value, (counts.get(select.value) || 0) + 1), new Map());
        itemSelects.forEach(select => [...select.options].forEach(option => {
            if (!option.value || !option.dataset.quantity) return;
            const usedElsewhere = (selectedCounts.get(option.value) || 0) - (select.value === option.value ? 1 : 0);
            option.disabled = usedElsewhere >= Number(option.dataset.quantity);
        }));
    };
    const syncItems = (names, entries = []) => {
        itemsPanel.hidden = names.length === 0;
        itemSelects.forEach((select, index) => {
            const label = select.closest('label');
            const visible = index < names.length;
            label.hidden = !visible;
            select.disabled = !visible;
            if (!visible) select.value = '';
            const title = label.querySelector('[data-item-slot-label]');
            if (title) title.textContent = `#${index + 1} ${entries[index]?.label || (visible ? names[index] : '—')}`;
        });
        syncOwnedItemLimits();
    };
    const renderPreview = async () => {
        const currentVersion = ++renderVersion;
        const names = input.value.split(',').map(value => value.trim().toLowerCase()).filter(Boolean).slice(0, 6);
        syncItems(names);
        slots.forEach((slot, index) => {
            const name = names[index];
            if (!name) {
                if (slot.dataset.state !== 'empty') {
                    slot.className = 'team-preview-slot empty';
                    slot.dataset.state = 'empty';
                    delete slot.dataset.name;
                    delete slot.dataset.partySlot;
                    slot.innerHTML = emptyMarkup(index);
                }
                return;
            }
            const cached = pokemonPreviewCache.get(name);
            if (cached && !(cached instanceof Promise)) {
                if (slot.dataset.name !== name || slot.dataset.state !== 'ready') showOccupied(slot, cached, index, name);
                return;
            }
            if (slot.dataset.name !== name || slot.dataset.state !== 'loading') {
                slot.className = 'team-preview-slot loading';
                slot.dataset.name = name;
                slot.dataset.state = 'loading';
                delete slot.dataset.partySlot;
                slot.innerHTML = loadingMarkup(index, name);
            }
        });
        const entries = await Promise.all(names.map(pokemon));
        if (currentVersion !== renderVersion) return;
        entries.forEach((entry, index) => {
            const slot = slots[index];
            if (entry) {
                if (slot.dataset.name === names[index] && slot.dataset.state !== 'ready') showOccupied(slot, entry, index, names[index]);
            } else {
                slot.className = 'team-preview-slot invalid';
                slot.dataset.state = 'invalid';
                slot.innerHTML = `<span class="party-index">${index + 1}</span><div class="party-data"><b>${escapePreview(names[index])}</b><small>?</small></div>`;
            }
        });
        syncItems(names, entries);
    };
    input.addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(renderPreview, 350); });
    input.addEventListener('change', renderPreview);
    itemSelects.forEach(select => select.addEventListener('change', () => {
        const item = preview.querySelector(`[data-party-slot="${select.dataset.slot}"] [data-party-item]`);
        if (item) item.textContent = itemLabel(select);
        syncOwnedItemLimits();
    }));
    renderPreview();
});

document.querySelectorAll('[data-local-team-library]').forEach(library => {
    const storageKey = 'pokeline_local_teams_v1';
    const list = library.querySelector('[data-local-team-list]');
    const count = library.querySelector('[data-local-team-count]');
    const nameInput = library.querySelector('[data-local-team-name]');
    const targetIds = [...library.querySelectorAll('[data-save-local-team]')].map(button => button.dataset.saveLocalTeam);
    const readTeams = () => {
        try { return JSON.parse(localStorage.getItem(storageKey) || '[]').slice(0, 10); }
        catch { return []; }
    };
    const writeTeams = teams => localStorage.setItem(storageKey, JSON.stringify(teams.slice(0, 10)));
    const safe = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const loadTeam = (team, targetId) => {
        const target = document.getElementById(targetId);
        target.value = team.pokemon.join(', ');
        const selects = [...document.querySelectorAll(`[data-item-select="${targetId}"]`)];
        selects.forEach((select, index) => { select.value = team.items[index] || ''; });
        target.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const renderLibrary = () => {
        const teams = readTeams();
        library.classList.toggle('is-empty', teams.length === 0);
        count.textContent = `${teams.length} / 10`;
        list.innerHTML = teams.map((team, index) => `<article class="local-team-card" data-local-team-id="${safe(team.id)}"><div class="local-team-number">${String(index + 1).padStart(2, '0')}</div><div><strong>${safe(team.name)}</strong><div class="local-team-icons">${team.pokemon.map((pokemon, slot) => `<span title="${safe(pokemon)}">${team.sprites?.[slot]?`<img src="${safe(team.sprites[slot])}" alt="">`:'●'}</span>`).join('')}</div><small>${team.pokemon.length}/6</small></div><div class="local-team-buttons">${targetIds.map((targetId, player) => `<button type="button" data-load-local="${safe(team.id)}" data-load-target="${safe(targetId)}">${safe(library.dataset.load)} J${player + 1}</button>`).join('')}<button type="button" class="delete" data-delete-local="${safe(team.id)}">×</button></div></article>`).join('') || '<div class="local-library-empty">—</div>';
        list.querySelectorAll('[data-load-local]').forEach(button => button.addEventListener('click', () => {
            const team = readTeams().find(entry => String(entry.id) === button.dataset.loadLocal);
            if (team) loadTeam(team, button.dataset.loadTarget);
        }));
        list.querySelectorAll('[data-delete-local]').forEach(button => button.addEventListener('click', () => {
            writeTeams(readTeams().filter(team => String(team.id) !== button.dataset.deleteLocal));
            renderLibrary();
        }));
    };
    library.querySelectorAll('[data-save-local-team]').forEach(button => button.addEventListener('click', () => {
        const teams = readTeams();
        if (teams.length >= 10) { window.alert(library.dataset.limit); return; }
        const targetId = button.dataset.saveLocalTeam;
        const target = document.getElementById(targetId);
        const pokemon = target.value.split(',').map(value => value.trim()).filter(Boolean).slice(0, 6);
        if (!pokemon.length) { window.alert(library.dataset.empty); return; }
        const itemSelects = [...document.querySelectorAll(`[data-item-select="${targetId}"]`)];
        const sprites = [...document.querySelectorAll(`[data-team-preview="${targetId}"] [data-party-slot] img`)].map(image => image.src);
        teams.push({id: `${Date.now()}-${Math.random().toString(16).slice(2)}`, name: nameInput.value.trim() || `TEAM ${String(teams.length + 1).padStart(2, '0')}`, pokemon, items: itemSelects.map(select => select.value), sprites});
        writeTeams(teams);
        nameInput.value = '';
        renderLibrary();
    }));
    renderLibrary();
});

const battleApp = document.querySelector('#battle-app');

if (battleApp) {
    const config = {
        kind: battleApp.dataset.kind,
        mode: battleApp.dataset.mode,
        stateUrl: battleApp.dataset.stateUrl,
        actionUrl: battleApp.dataset.actionUrl,
        heartbeatUrl: battleApp.dataset.heartbeatUrl,
        channel: battleApp.dataset.channel,
        you: battleApp.dataset.you || 'p1',
        userId: battleApp.dataset.userId,
        text: JSON.parse(battleApp.dataset.translations),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const els = {
        playerSprite: document.querySelector('#player-sprite'),
        opponentSprite: document.querySelector('#opponent-sprite'),
        playerHud: document.querySelector('#player-hud'),
        opponentHud: document.querySelector('#opponent-hud'),
        moves: document.querySelector('#moves'),
        localP1: document.querySelector('#local-moves-p1'),
        localP2: document.querySelector('#local-moves-p2'),
        message: document.querySelector('#battle-message'),
        log: document.querySelector('#battle-log'),
        turn: document.querySelector('#turn-label'),
        weather: document.querySelector('#weather-label'),
        music: document.querySelector('#music-toggle'),
        sound: document.querySelector('#sound-toggle'),
        musicVolume: document.querySelector('#battle-music-volume'),
        soundVolume: document.querySelector('#battle-sound-volume'),
        result: document.querySelector('#battle-result'),
        connection: document.querySelector('#connection-label'),
    };
    let currentPayload = null;
    let busy = false;
    let animating = false;
    let refreshing = false;
    let renderedVersion = null;
    const spectator = config.kind === 'spectator';
    let opponentPresent = null;
    let opponentMissingFor = 0;
    let presenceTimer = null;

    const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
    const request = async (url, options = {}) => {
        const {headers = {}, ...requestOptions} = options;
        const response = await fetch(url, {
            ...requestOptions,
            headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...headers},
        });
        if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || `HTTP ${response.status}`);
        return response.json();
    };
    const normalized = raw => config.kind === 'session'
        ? {state: raw.state, mode: raw.mode, pending: raw.pending, you: 'p1', version: raw.state?.turn}
        : raw;
    const personalized = raw => ({
        ...raw,
        you: config.you,
        submitted: spectator ? false : Boolean(raw.submitted?.[config.you]),
        reward: spectator ? [] : (raw.rewards?.[String(config.userId)] || []),
    });
    const active = (state, key) => state.players[key].roster[state.players[key].active];
    const other = key => key === 'p1' ? 'p2' : 'p1';
    const hpPercent = (current, maximum) => Math.max(0, Math.min(100, Math.round(current / Math.max(1, maximum) * 100)));
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const typeLabel = type => config.text.types?.[type] || type;
    const hud = (pokemon, displayedHp = pokemon.current_hp) => {
        const percent = hpPercent(displayedHp, pokemon.max_hp);
        const status = pokemon.status ? `<b class="status-badge status-${escape(pokemon.status)}">${escape(pokemon.status)}</b>` : '';
        return `<div class="hud-name"><span>${escape(pokemon.label)} ${status}</span><span>Lv.100</span></div><div class="type-tags">${pokemon.types.map(type => `<span class="type-${escape(type)}">${escape(typeLabel(type))}</span>`).join('')}${pokemon.ability ? `<span>${escape(pokemon.ability)}</span>` : ''}</div><div class="hp-line"><b>${escape(config.text.hp)}</b><div class="hp-track"><div class="hp-fill ${percent < 25 ? 'low' : ''}" style="width:${percent}%"></div></div></div><div class="hp-numbers">${displayedHp} / ${pokemon.max_hp}</div>`;
    };
    const moveMarkup = (pokemon, disabled) => pokemon.moves.map((move, index) => `<button class="move-button type-${escape(move.type)}" data-move="${index}" ${(disabled || (move.current_pp ?? move.pp ?? 1) <= 0) ? 'disabled' : ''}><span>${escape(move.label)}</span><small>${escape(typeLabel(move.type))} · ${move.power || '—'} PUI · ${move.current_pp ?? move.pp ?? '?'} PP</small></button>`).join('');
    const switchMarkup = (state, key, disabled, forced = false) => `<div class="switch-strip ${forced ? 'forced-switch' : ''}"><strong>${escape(forced ? config.text.chooseReplacement : config.text.switch)}</strong>${state.players[key].roster.map((pokemon, index) => `<button type="button" data-switch="${index}" ${(disabled || index === state.players[key].active || pokemon.current_hp <= 0) ? 'disabled' : ''} title="${escape(pokemon.label)}"><img src="${escape(pokemon.sprites.front)}" alt=""><span>${escape(pokemon.label)}<small>${pokemon.current_hp}/${pokemon.max_hp}</small></span></button>`).join('')}</div>`;
    const controlsMarkup = (state, key, disabled, forced = false) => forced
        ? switchMarkup(state, key, disabled, true)
        : moveMarkup(active(state, key), disabled) + switchMarkup(state, key, disabled);
    const bindActions = container => {
        container.querySelectorAll('[data-move]').forEach(button => button.addEventListener('click', () => act({action_type: 'move', move_index: Number(button.dataset.move)})));
        container.querySelectorAll('[data-switch]').forEach(button => button.addEventListener('click', () => act({action_type: 'switch', pokemon_index: Number(button.dataset.switch)})));
    };
    const spriteFor = (state, key, you) => {
        const pokemon = active(state, key);
        return key === you ? (pokemon.sprites.back || pokemon.sprites.front) : pokemon.sprites.front;
    };
    const hudFor = (key, you) => key === you ? els.playerHud : els.opponentHud;
    const spriteElementFor = (key, you) => key === you ? els.playerSprite : els.opponentSprite;
    const setCombatant = (state, key, you) => {
        const pokemon = active(state, key);
        const sprite = spriteElementFor(key, you);
        sprite.src = spriteFor(state, key, you);
        sprite.alt = pokemon.label;
        sprite.classList.remove('faint-out', 'switch-in', 'is-fainted');
        if (pokemon.current_hp <= 0) sprite.classList.add('is-fainted');
        hudFor(key, you).innerHTML = hud(pokemon);
    };
    const resultScreen = (state, you, reward = []) => {
        if (state.phase !== 'finished') {
            els.result.hidden = true;
            return;
        }
        const victory = spectator || state.winner === you;
        startMusic(victory ? 'victory' : 'defeat');
        els.result.classList.toggle('victory', victory);
        els.result.classList.toggle('defeat', !victory);
        els.result.querySelector('[data-result-title]').textContent = spectator ? config.text.battleFinished : (victory ? config.text.victory : config.text.defeat);
        const winnerName = state.players?.[state.winner]?.name || '';
        els.result.querySelector('[data-result-message]').textContent = spectator
            ? config.text.winnerMessage.replace(':trainer', winnerName)
            : (victory ? config.text.victoryMessage : config.text.defeatMessage);
        const rewards = els.result.querySelector('[data-result-rewards]');
        rewards.hidden = !reward?.length;
        rewards.textContent = reward?.length ? `${config.text.rewards}: ${reward.join(', ')}` : '';
        els.result.hidden = false;
    };
    const showMessage = text => {
        els.message.textContent = text;
        els.message.classList.remove('message-reveal');
        void els.message.offsetWidth;
        els.message.classList.add('message-reveal');
    };
    const draw = (data, {disabled = false, preserveMessage = false, showResult = true} = {}) => {
        if (!data?.state) return;
        const state = data.state;
        const you = data.you || 'p1';
        const enemy = other(you);
        const forced = state.forced_switch || {};
        const forcedKeys = Object.keys(forced).filter(key => forced[key]);
        setCombatant(state, you, you);
        setCombatant(state, enemy, you);
        els.turn.textContent = `${config.text.turn} ${state.turn}`;
        els.weather.textContent = state.weather ? `${config.text.weather}: ${state.weather.toUpperCase()} (${state.weather_turns})` : (spectator ? config.text.liveSpectator : '');
        els.log.innerHTML = state.log.slice().reverse().map(line => `<div>› ${escape(line)}</div>`).join('');

        const submitted = Boolean(data.submitted);
        const ownForced = Boolean(forced[you]);
        const opponentForced = forcedKeys.length > 0 && !ownForced;
        const controlsDisabled = spectator || disabled || busy || animating || submitted || opponentForced || state.phase !== 'active';
        if (spectator) {
            els.moves.innerHTML = `<div class="spectator-command"><span class="live-dot"></span>${escape(config.text.spectating)}</div>`;
        } else {
            els.moves.innerHTML = controlsMarkup(state, you, controlsDisabled, ownForced);
            bindActions(els.moves);
        }

        if ((data.mode || config.mode) === 'local') {
            battleApp.classList.add('local-mode');
            const firstForced = forcedKeys[0] || null;
            const p1Disabled = disabled || busy || animating || state.phase !== 'active' || (firstForced ? firstForced !== 'p1' : data.pending !== null);
            const p2Disabled = disabled || busy || animating || state.phase !== 'active' || (firstForced ? firstForced !== 'p2' : data.pending === null);
            els.localP1.innerHTML = controlsMarkup(state, 'p1', p1Disabled, firstForced === 'p1');
            els.localP2.innerHTML = controlsMarkup(state, 'p2', p2Disabled, firstForced === 'p2');
            bindActions(els.localP1);
            bindActions(els.localP2);
        }

        if (!preserveMessage) {
            showMessage(state.phase === 'finished'
                ? (spectator ? config.text.battleFinished : (state.winner === you ? config.text.victory : config.text.defeat))
                : spectator
                    ? (state.last_events?.at(-1)?.text || config.text.spectating)
                : ownForced
                    ? config.text.chooseReplacement
                    : opponentForced
                        ? config.text.waitingReplacement
                        : submitted
                            ? config.text.waiting
                            : config.text.choose);
        }
        if (showResult) resultScreen(state, you, data.reward);
    };
    const animateHp = async (key, you, currentHp, maximumHp) => {
        const targetHud = hudFor(key, you);
        const fill = targetHud.querySelector('.hp-fill');
        const numbers = targetHud.querySelector('.hp-numbers');
        if (!fill || !numbers) return;
        const percent = hpPercent(currentHp, maximumHp);
        fill.classList.toggle('low', percent < 25);
        fill.style.width = `${percent}%`;
        numbers.textContent = `${currentHp} / ${maximumHp}`;
        await wait(760);
    };
    const playSound = name => {
        if (!soundEnabled || soundVolume <= 0) return;
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        const context = new AudioContextClass();
        const master = context.createGain();
        const compressor = context.createDynamicsCompressor();
        master.gain.value = soundVolume;
        compressor.threshold.value = -15;
        compressor.ratio.value = 7;
        master.connect(compressor).connect(context.destination);
        const now = context.currentTime;
        let lifetime = .42;
        const tone = (waveform, from, to, duration, gainValue, delay = 0, detune = 0) => {
            const start = now + delay;
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = waveform;
            oscillator.frequency.setValueAtTime(Math.max(1, from), start);
            oscillator.frequency.exponentialRampToValueAtTime(Math.max(1, to), start + duration);
            oscillator.detune.setValueAtTime(detune, start);
            gain.gain.setValueAtTime(.0001, start);
            gain.gain.exponentialRampToValueAtTime(gainValue, start + .012);
            gain.gain.exponentialRampToValueAtTime(.0001, start + duration);
            oscillator.connect(gain).connect(master);
            oscillator.start(start);
            oscillator.stop(start + duration + .02);
            lifetime = Math.max(lifetime, delay + duration + .08);
        };
        const burst = (duration, gainValue, frequency, delay = 0) => {
            playNoise(context, master, now + delay, duration, gainValue, frequency);
            lifetime = Math.max(lifetime, delay + duration + .08);
        };
        const profile = String(name || 'normal').toLowerCase();

        if (profile === 'faint') {
            lifetime = .95;
            [0, .12, .24, .36].forEach((delay, index) => tone('square', 520 - index * 85, 230 - index * 35, .2, .1, delay));
            tone('triangle', 180, 38, .72, .16, .08);
            burst(.34, .055, 650, .32);
        } else if (profile === 'critical-impact') {
            tone('square', 155, 42, .24, .18);
            tone('sawtooth', 330, 70, .17, .09, .015);
            burst(.15, .11, 720);
        } else if (profile === 'impact') {
            tone('square', 120, 48, .18, .14);
            burst(.11, .075, 900);
        } else if (profile === 'heal') {
            [0, .08, .16, .24].forEach((delay, index) => tone('square', 440 + index * 110, 660 + index * 130, .16, .055, delay));
        } else if (profile === 'switch') {
            tone('triangle', 150, 680, .3, .11);
            tone('square', 300, 980, .22, .035, .08);
        } else if (profile === 'status') {
            tone('sine', 260, 780, .36, .08);
            tone('square', 520, 390, .28, .035, .05, 5);
        } else if (profile === 'fire') {
            tone('sawtooth', 310, 95, .34, .12);
            burst(.28, .075, 1100);
        } else if (profile === 'water') {
            tone('sine', 170, 760, .34, .13);
            tone('triangle', 620, 210, .27, .07, .08);
        } else if (profile === 'electric') {
            [0, .055, .11, .165].forEach((delay, index) => tone('square', index % 2 ? 1250 : 720, index % 2 ? 610 : 1500, .07, .075, delay));
            burst(.2, .035, 4200);
        } else if (profile === 'grass' || profile === 'bug') {
            tone('triangle', 360, 920, .3, .09);
            tone('square', 740, 430, .2, .04, .07);
        } else if (profile === 'ice') {
            [0, .07, .14].forEach((delay, index) => tone('sine', 760 + index * 180, 1120 + index * 220, .18, .055, delay));
            burst(.18, .025, 6000);
        } else if (profile === 'psychic' || profile === 'fairy') {
            tone('sine', 280, 1120, .38, .1);
            tone('sine', 880, 330, .38, .065, 0, 11);
        } else if (profile === 'ghost' || profile === 'dark') {
            tone('sawtooth', 210, 55, .42, .095);
            tone('sine', 480, 125, .36, .055, .04, -16);
        } else if (profile === 'poison') {
            tone('sawtooth', 190, 340, .36, .09);
            tone('square', 115, 80, .32, .055, .06);
        } else if (profile === 'flying') {
            tone('triangle', 240, 1050, .27, .1);
            burst(.18, .035, 3600, .05);
        } else if (profile === 'dragon') {
            tone('sawtooth', 145, 720, .38, .13);
            tone('square', 310, 95, .3, .065, .08);
        } else if (['rock', 'ground', 'steel', 'fighting'].includes(profile)) {
            tone('square', profile === 'steel' ? 390 : 145, 48, .28, .145);
            burst(.18, .09, profile === 'steel' ? 2600 : 650);
        } else {
            tone('square', 260, 105, .26, .11);
            tone('triangle', 390, 170, .2, .05, .04);
        }

        window.setTimeout(() => context.close().catch(() => {}), lifetime * 1000);
    };
    const playSequence = async (events, previousState, finalState, you) => {
        const visualHp = {
            p1: active(previousState, 'p1').current_hp,
            p2: active(previousState, 'p2').current_hp,
        };
        const maximumHp = {
            p1: active(previousState, 'p1').max_hp,
            p2: active(previousState, 'p2').max_hp,
        };

        for (const event of events) {
            showMessage(event.text || '');
            if (event.type === 'attack') {
                await wait(420);
                const attacker = spriteElementFor(event.actor, you);
                attacker.classList.remove('lunge-right', 'lunge-left');
                void attacker.offsetWidth;
                attacker.classList.add(event.actor === you ? 'lunge-right' : 'lunge-left');
                playSound(event.damage_class === 'status' ? 'status' : (event.move_type || event.move));
                await wait(520);
                continue;
            }
            if (['damage', 'recoil', 'status-damage', 'weather-damage'].includes(event.type)) {
                const target = spriteElementFor(event.target, you);
                target.classList.remove('hit-shake');
                void target.offsetWidth;
                target.classList.add('hit-shake');
                playSound(Number(event.effectiveness || 1) > 1 ? 'critical-impact' : 'impact');
                await wait(180);
                visualHp[event.target] = Math.max(0, visualHp[event.target] - Number(event.amount || 0));
                await animateHp(event.target, you, visualHp[event.target], maximumHp[event.target]);
                continue;
            }
            if (event.type === 'heal') {
                playSound('heal');
                visualHp[event.target] = Math.min(maximumHp[event.target], visualHp[event.target] + Number(event.amount || 0));
                await animateHp(event.target, you, visualHp[event.target], maximumHp[event.target]);
                continue;
            }
            if (event.type === 'faint') {
                const target = spriteElementFor(event.target, you);
                await wait(420);
                target.classList.add('faint-out');
                playSound('faint');
                await wait(720);
                continue;
            }
            if (event.type === 'switch') {
                playSound('switch');
                await wait(350);
                const target = spriteElementFor(event.target, you);
                const pokemon = active(finalState, event.target);
                target.src = spriteFor(finalState, event.target, you);
                target.alt = pokemon.label;
                target.classList.remove('faint-out', 'is-fainted');
                target.classList.add('switch-in');
                hudFor(event.target, you).innerHTML = hud(pokemon);
                visualHp[event.target] = pokemon.current_hp;
                maximumHp[event.target] = pokemon.max_hp;
                await wait(780);
                continue;
            }
            await wait(520);
        }
    };
    const render = async (raw, animate = false) => {
        const next = normalized(raw);
        if (!next.state) {
            currentPayload = next;
            return;
        }
        document.querySelector('.waiting-card')?.remove();
        battleApp.classList.remove('is-waiting');
        const previous = currentPayload;
        currentPayload = next;
        els.result.hidden = true;

        if (animate && previous?.state && next.state.last_events?.length) {
            animating = true;
            draw(previous, {disabled: true, preserveMessage: true, showResult: false});
            await playSequence(next.state.last_events, previous.state, next.state, next.you || 'p1');
            animating = false;
            draw(next, {showResult: false});
            if (next.state.phase === 'finished') {
                await wait(450);
                resultScreen(next.state, next.you || 'p1', next.reward);
            }
        } else {
            draw(next);
        }
    };
    const act = async action => {
        if (busy || animating) return;
        busy = true;
        document.querySelectorAll('.moves-grid button,.switch-strip button').forEach(button => { button.disabled = true; });
        try {
            const next = await request(config.actionUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(action),
            });
            const nextNormalized = normalized(next);
            if (config.kind === 'online' && window.Echo?.connector?.pusher?.connection?.state === 'connected') {
                busy = false;

                await wait(150);
                const realtimeMatchesResponse = renderedVersion === nextNormalized.version
                    && Boolean(currentPayload?.submitted) === Boolean(nextNormalized.submitted)
                    && currentPayload?.status === nextNormalized.status;
                if (!realtimeMatchesResponse) {
                    const shouldAnimate = renderedVersion === null || nextNormalized.version !== renderedVersion;
                    renderedVersion = nextNormalized.version;
                    await render(next, shouldAnimate);
                } else if (!animating && currentPayload) {
                    draw(currentPayload);
                }

                return;
            }
            const shouldAnimate = action.action_type === 'switch' || renderedVersion === null || nextNormalized.version !== renderedVersion || config.mode === 'local';
            busy = false;
            await render(next, shouldAnimate);
            renderedVersion = nextNormalized.version;
        } catch (error) {
            busy = false;
            els.message.textContent = error.message;
            if (currentPayload) draw(currentPayload, {preserveMessage: true});
        }
    };
    const refresh = async () => {
        if (busy || animating || refreshing) return;
        refreshing = true;
        try {
            const next = await request(config.stateUrl);
            const version = normalized(next).version;
            const shouldAnimate = renderedVersion !== null && version !== renderedVersion;
            await render(next, shouldAnimate);
            renderedVersion = version;
        } catch (error) {
            els.message.textContent = error.message;
        } finally {
            refreshing = false;
        }
    };
    const receiveRealtime = async payload => {
        const next = personalized(payload);
        const version = next.version;
        const shouldAnimate = renderedVersion !== null && version !== renderedVersion;
        renderedVersion = version;
        await render(next, shouldAnimate);
    };
    const setConnectionState = (state, text) => {
        if (!els.connection) return;
        els.connection.className = `connection-label is-${state}`;
        els.connection.textContent = text;
    };
    const drawPresence = () => {
        if (!els.connection) return;
        if (currentPayload?.status !== 'active' || opponentPresent !== false) {
            setConnectionState('connected', config.text.connected);
            return;
        }
        const remaining = Math.max(0, 90 - opponentMissingFor);
        setConnectionState('disconnected', `${config.text.opponentDisconnected} · ${config.text.autoWinCountdown.replace(':seconds', remaining)}`);
    };
    const setOpponentPresence = present => {
        opponentPresent = present;
        if (present) opponentMissingFor = 0;
        clearInterval(presenceTimer);
        drawPresence();
        if (!present) {
            presenceTimer = window.setInterval(() => {
                opponentMissingFor = Math.min(90, opponentMissingFor + 1);
                drawPresence();
            }, 1000);
        }
    };
    const heartbeat = async () => {
        if (!config.heartbeatUrl || currentPayload?.state?.phase === 'finished') return;
        try {
            const response = await request(config.heartbeatUrl, {method: 'POST'});
            opponentMissingFor = Number(response.opponent_missing_for || 0);
            if (opponentPresent === false) drawPresence();
            if (response.battle) await receiveRealtime(response.battle);
        } catch (error) {
            if (!error.message.includes('409')) setConnectionState('unavailable', config.text.unavailable);
        }
    };
    const connectRealtime = () => {
        if (!window.Echo || !config.channel) {
            setConnectionState('unavailable', config.text.unavailable);
            return;
        }

        const otherSide = config.you === 'p1' ? 'p2' : 'p1';
        const channelName = `battles.${config.channel}`;
        const channel = window.Echo.join(channelName)
            .here(users => setOpponentPresence(spectator || users.some(user => user.role === 'player' && user.side === otherSide)))
            .joining(user => {
                if (!spectator && user.role === 'player' && user.side === otherSide) setOpponentPresence(true);
            })
            .leaving(user => {
                if (!spectator && user.role === 'player' && user.side === otherSide) setOpponentPresence(false);
            })
            .listen('.updated', receiveRealtime)
            .error(() => setConnectionState('unavailable', config.text.unavailable));

        window.Echo.connector.pusher.connection.bind('state_change', states => {
            if (states.current === 'connected') {
                setConnectionState('connected', config.text.connected);
                refresh();
            } else if (['connecting', 'unavailable'].includes(states.current)) {
                setConnectionState('connecting', config.text.reconnecting);
            } else if (['failed', 'disconnected'].includes(states.current)) {
                setConnectionState('unavailable', config.text.unavailable);
            }
        });

        window.addEventListener('beforeunload', () => {
            clearInterval(presenceTimer);
            window.Echo.leave(channelName);
        }, {once: true});

        return channel;
    };

    els.sound.addEventListener('click', () => {
        updateSoundPreference(!soundEnabled);
        els.sound.textContent = `${soundEnabled ? '🔊' : '🔇'} ${config.text.sound}`;
    });
    els.music.addEventListener('click', () => {
        updateMusicPreference(!musicEnabled);
        els.music.textContent = `${musicEnabled ? '🎵' : '🚫'} ${config.text.music}`;
    });
    els.musicVolume.value = String(Math.round(musicVolume * 100));
    els.soundVolume.value = String(Math.round(soundVolume * 100));
    els.musicVolume.addEventListener('input', () => updateMusicVolume(Number(els.musicVolume.value) / 100));
    els.soundVolume.addEventListener('input', () => updateSoundVolume(Number(els.soundVolume.value) / 100));
    els.sound.textContent = `${soundEnabled ? '🔊' : '🔇'} ${config.text.sound}`;
    els.music.textContent = `${musicEnabled ? '🎵' : '🚫'} ${config.text.music}`;
    refresh();
    if (config.kind === 'online' || config.kind === 'spectator') connectRealtime();
    if (config.kind === 'online') {
        heartbeat();
        setInterval(heartbeat, 5000);
    }
}
