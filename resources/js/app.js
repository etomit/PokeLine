import './bootstrap';
import {
    midiMusicThemes,
    playGameSound,
    setMidiMusicVolume,
    startMidiMusic,
    stopMidiMusic,
} from './audio';

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
const isEmbeddedGame = window.self !== window.top;

const pageMusicTheme = document.querySelector('#battle-app')
    ? 'battle'
    : document.querySelector('#world-hub, .arcade-setup, .online-center-room, .online-team-page, .pokedex-page') ? 'menu' : null;
let musicUnlocked = Boolean(navigator.userActivation?.hasBeenActive);
let embeddedMusicTheme = null;

const currentMusicTheme = () => {
    if (embeddedMusicTheme) return embeddedMusicTheme;
    if (pageMusicTheme !== 'battle') return pageMusicTheme;
    const result = document.querySelector('#battle-result');
    if (result && !result.hidden) return result.classList.contains('victory') ? 'victory' : 'defeat';
    const battle = document.querySelector('#battle-app');
    if (['online', 'spectator'].includes(battle?.dataset.kind)) return 'battle-online';
    return battle?.dataset.mode === 'local' ? 'battle-local' : 'battle-solo';
};

const stopMusic = stopMidiMusic;

const startMusic = async themeName => {
    if (isEmbeddedGame) {
        window.parent.postMessage({type: 'pokeline:music-theme', theme: themeName}, window.location.origin);
        return;
    }
    if (!musicEnabled || !musicUnlocked || !midiMusicThemes[themeName] || document.hidden) return;
    setMidiMusicVolume(musicVolume);
    await startMidiMusic(themeName);
};

const updateMusicPreference = enabled => {
    musicEnabled = enabled;
    musicUnlocked = true;
    localStorage.setItem('pokeline_music', enabled ? 'on' : 'off');
    if (musicSetting) musicSetting.checked = enabled;
    if (enabled) startMusic(currentMusicTheme());
    else {
        stopMusic();
        if (isEmbeddedGame) window.parent.postMessage({type: 'pokeline:music-stop'}, window.location.origin);
    }
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
    setMidiMusicVolume(musicVolume);
};

const updateSoundVolume = value => {
    soundVolume = Math.max(0, Math.min(1, Number(value)));
    localStorage.setItem('pokeline_sound_volume', String(soundVolume));
    if (soundVolumeSetting) {
        soundVolumeSetting.value = String(Math.round(soundVolume * 100));
        soundVolumeSetting.nextElementSibling.value = `${Math.round(soundVolume * 100)}%`;
    }
};

const playInterfaceSound = name => {
    if (soundEnabled) playGameSound(name, soundVolume);
};

if (pageMusicTheme) {
    if (isEmbeddedGame) {
        startMusic(currentMusicTheme());
    } else {
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

const interfaceSoundFor = control => {
    if (control.matches('.party-remove')) return 'remove';
    if (control.matches('[data-dialog-close], [data-game-close], .battle-forfeit, .online-danger')) return 'cancel';
    if (control.matches('#settings-open, .settings-button')) return 'settings';
    if (control.matches('[data-pokedex-submit]')) return 'search';
    if (control.matches('[data-pokedex-prev], [data-pokedex-next]')) return 'page';
    if (control.matches('.pokedex-card, [data-pokemon]')) return 'pokedex-entry';
    if (control.matches('.pokedex-picker-button, .party-add-button')) return 'party-add';
    if (control.matches('[data-switch-toggle]')) return 'toggle';
    if (control.matches('[data-move], [data-switch]')) return 'battle-command';
    if (control.matches('a')) return 'navigate';
    return 'confirm';
};
document.addEventListener('click', event => {
    const control = event.target.closest('button, a');
    if (!control || control.matches(':disabled, [aria-disabled="true"], [data-destination]')) return;
    playInterfaceSound(interfaceSoundFor(control));
});
document.addEventListener('change', event => {
    if (event.target.matches('input[type="checkbox"], input[type="radio"]')) playInterfaceSound('toggle');
    else if (event.target.matches('select, input[type="range"]')) playInterfaceSound('select');
});

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
        playInterfaceSound('open');
        gameFrame.src = destinations[key].url;
        gameDialog.showModal();
    };
    const update = () => {
        character.style.left = `${position.x}%`;
        character.style.top = `${position.y}%`;
        const characterClass = `hub-character face-${direction}${pressedDirections.length ? ' is-walking' : ''}`;
        if (character.className !== characterClass) character.className = characterClass;
        let closest = null;
        let closestDistance = Infinity;
        Object.entries(destinations).forEach(([key, destination]) => {
            const distance = Math.hypot(position.x - destination.x, (position.y - destination.y) * 1.7);
            if (distance < closestDistance) { closest = key; closestDistance = distance; }
        });
        const previousNear = near;
        near = closestDistance < 13 ? closest : null;
        if (near && near !== previousNear) playInterfaceSound('notice');
        document.querySelectorAll('[data-destination]').forEach(marker => marker.classList.toggle('is-near', marker.dataset.destination === near));
        prompt.textContent = near ? `ENTER / E — ${displayName(near)}` : prompt.dataset.default || prompt.textContent;
    };
    prompt.dataset.default = prompt.textContent;
    const movementStep = .48;
    const walk = () => {
        const activeDirection = pressedDirections.at(-1);
        const horizontalStep = movementStep * hub.clientHeight / Math.max(1, hub.clientWidth);
        const delta = {
            left: [-horizontalStep, 0],
            right: [horizontalStep, 0],
            up: [0, -movementStep],
            down: [0, movementStep],
        }[activeDirection];
        if (delta) {
            direction = activeDirection;
            const next = {x: position.x + delta[0], y: position.y + delta[1]};
            if (!blocked(next.x, next.y)) position = next;
            else playInterfaceSound('collision');
            update();
        }
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
        update();
    });
    window.addEventListener('blur', () => {
        pressedDirections.splice(0);
        update();
    });
    document.querySelectorAll('[data-destination]').forEach(marker => marker.addEventListener('click', () => openGame(marker.dataset.destination)));
    document.querySelector('[data-game-close]').addEventListener('click', () => gameDialog.close());
    gameDialog.addEventListener('close', () => {
        playInterfaceSound('close');
        gameFrame.src = 'about:blank';
        embeddedMusicTheme = null;
        hub.focus();
        musicEnabled = localStorage.getItem('pokeline_music') !== 'off';
        if (musicEnabled) startMusic('menu');
    });
    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin) return;
        if (event.data === 'pokeline:close-game') {
            gameDialog.close();
            return;
        }
        if (event.data?.type === 'pokeline:music-stop') {
            stopMusic();
            return;
        }
        if (event.data?.type === 'pokeline:music-theme' && midiMusicThemes[event.data.theme]) {
            embeddedMusicTheme = event.data.theme;
            musicEnabled = localStorage.getItem('pokeline_music') !== 'off';
            musicUnlocked = true;
            if (musicEnabled) startMusic(event.data.theme);
        }
    });
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
const pokemonDetailsPage = document.querySelector('[data-pokemon-details-url]');
const pokemonDetailsDialog = document.querySelector('#pokemon-details-dialog');
const pokemonDetailsContent = pokemonDetailsDialog?.querySelector('[data-pokemon-details-content]');
const pokemonDetailsText = pokemonDetailsPage ? JSON.parse(pokemonDetailsPage.dataset.pokemonDetailsTranslations) : null;
const escapePokemonDetails = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
const showPokemonDetails = async name => {
    if (!pokemonDetailsDialog || !pokemonDetailsContent || !pokemonDetailsPage) return;
    pokemonDetailsContent.innerHTML = '<div class="pokemon-details-loading">…</div>';
    pokemonDetailsDialog.showModal();
    try {
        const response = await fetch(`${pokemonDetailsPage.dataset.pokemonDetailsUrl}/${encodeURIComponent(name)}`, {headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || `HTTP ${response.status}`);
        const pokemon = await response.json();
        const statKeys = ['hp', 'attack', 'defense', 'special_attack', 'special_defense', 'speed'];
        const highestStat = Math.max(1, ...statKeys.map(key => Number(pokemon.stats?.[key] || 0)));
        const typeLabel = type => pokemonDetailsText.types?.[type] || type;
        pokemonDetailsContent.innerHTML = `<header class="pokemon-details-head"><img src="${escapePokemonDetails(pokemon.sprites?.artwork || pokemon.sprites?.front)}" alt=""><div><small>#${String(pokemon.id).padStart(4, '0')}</small><h2 id="pokemon-details-title">${escapePokemonDetails(pokemon.label)}</h2><div class="type-tags">${pokemon.types.map(type => `<span class="type-${escapePokemonDetails(type)}">${escapePokemonDetails(typeLabel(type))}</span>`).join('')}</div></div></header><div class="pokemon-details-columns"><section><h3>${escapePokemonDetails(pokemonDetailsText.stats)}</h3><div class="pokemon-stat-list">${statKeys.map(key => { const value = Number(pokemon.stats?.[key] || 0); return `<div><span>${escapePokemonDetails(pokemonDetailsText[key])}</span><i><b style="width:${Math.round(value / highestStat * 100)}%"></b></i><strong>${value}</strong></div>`; }).join('')}</div></section><section><h3>${escapePokemonDetails(pokemonDetailsText.moves)}</h3><div class="pokemon-move-list">${pokemon.moves.map(move => `<article class="type-${escapePokemonDetails(move.type)}"><strong>${escapePokemonDetails(move.label)}</strong><span>${escapePokemonDetails(typeLabel(move.type))}</span><small>${escapePokemonDetails(pokemonDetailsText.power)} ${move.power || '—'} · ${escapePokemonDetails(pokemonDetailsText.accuracy)} ${move.accuracy}% · ${move.pp} PP</small></article>`).join('')}</div></section></div>`;
    } catch (error) {
        pokemonDetailsContent.innerHTML = `<div class="pokemon-details-loading">${escapePokemonDetails(error.message)}</div>`;
    }
};
pokemonDetailsDialog?.querySelector('[data-pokemon-details-close]')?.addEventListener('click', () => pokemonDetailsDialog.close());
document.querySelectorAll('.pokedex-picker-button').forEach(button => button.addEventListener('click', () => {
    pokedexTarget = document.getElementById(button.dataset.pokedexTarget);
    pokedexMode = button.dataset.pokedexMode || 'replace';
    pokedexDialog?.showModal();
}));

document.querySelectorAll('[data-pokedex-browser]').forEach(browser => {
    const pickerBrowser = Boolean(browser.closest('#pokedex-dialog'));
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
                if (pickerBrowser) selectPokemon(card.dataset.pokemon);
                else showPokemonDetails(card.dataset.pokemon);
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
const partySpriteOffsetCache = new Map();
const measureSpriteOffset = async image => {
    if (!image?.src) return;

    let measured = partySpriteOffsetCache.get(image.src);
    if (!measured) {
        measured = (async () => {
            try {
                await image.decode();
                const canvas = document.createElement('canvas');
                canvas.width = image.naturalWidth;
                canvas.height = image.naturalHeight;
                const context = canvas.getContext('2d', {willReadFrequently: true});
                context.drawImage(image, 0, 0);
                const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
                let minX = canvas.width;
                let minY = canvas.height;
                let maxX = -1;
                let maxY = -1;

                for (let y = 0; y < canvas.height; y++) {
                    for (let x = 0; x < canvas.width; x++) {
                        if (pixels[(y * canvas.width + x) * 4 + 3] < 8) continue;
                        minX = Math.min(minX, x);
                        minY = Math.min(minY, y);
                        maxX = Math.max(maxX, x);
                        maxY = Math.max(maxY, y);
                    }
                }

                if (maxX < minX || maxY < minY) return {x: 0, y: 0, bottom: 0};

                return {
                    x: 50 - ((minX + maxX) / 2 / canvas.width * 100),
                    y: 50 - ((minY + maxY) / 2 / canvas.height * 100),
                    bottom: (canvas.height - 1 - maxY) / canvas.height * 100,
                };
            } catch {
                return {x: 0, y: 0, bottom: 0};
            }
        })();
        partySpriteOffsetCache.set(image.src, measured);
    }

    return measured;
};
const centerPartySprite = async image => {
    const offset = await measureSpriteOffset(image);
    if (!offset) return;
    image.style.setProperty('--party-sprite-x', `${offset.x.toFixed(2)}%`);
    image.style.setProperty('--party-sprite-y', `${offset.y.toFixed(2)}%`);
};
const centerBattleSprite = async image => {
    const source = image?.src;
    const offset = await measureSpriteOffset(image);
    if (!offset || image.src !== source) return;

    requestAnimationFrame(() => {
        if (image.src !== source) return;
        const renderedSize = Math.min(image.clientWidth, image.clientHeight);
        image.style.setProperty('--battle-sprite-x', `${(offset.x / 100 * renderedSize).toFixed(2)}px`);
        image.style.setProperty('--battle-sprite-y', `${(offset.bottom / 100 * renderedSize).toFixed(2)}px`);
    });
};
document.querySelectorAll('[data-team-input]').forEach(input => {
    const preview = document.querySelector(`[data-team-preview="${input.id}"]`);
    const itemSelects = [...document.querySelectorAll(`[data-item-select="${input.id}"]`)];
    const itemsPanel = input.closest('.player-loadout').querySelector('.arcade-items');
    const itemGrid = itemsPanel.querySelector('.item-select-grid');
    const typeLabels = JSON.parse(input.closest('[data-type-labels]')?.dataset.typeLabels || '{}');
    const translatedType = type => typeLabels[type] || type;
    let previewTimer;
    let renderVersion = 0;
    const escapePreview = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const emptyMarkup = index => `<span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><div class="party-data"><div class="party-name"><b>—</b><em>Lv.—</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:0"></span></i></div><div class="party-meta"><small>— / —</small><small>—</small></div></div>`;
    const loadingMarkup = (index, name) => `<span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><div class="party-data"><div class="party-name"><b>${escapePreview(name)}</b><em>…</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:35%"></span></i></div></div>`;
    const occupiedMarkup = (entry, index) => `<button type="button" class="party-remove" data-remove-pokemon="${index}" aria-label="×">×</button><span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><img crossorigin="anonymous" src="${escapePreview(entry.sprites.front)}" alt=""><div class="party-data"><div class="party-name"><b>${escapePreview(entry.label)}</b><em>Lv.100</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:100%"></span></i></div><div class="party-meta"><small>${entry.stats.hp} / ${entry.stats.hp}</small><small>${entry.types.map(type => escapePreview(translatedType(type))).join(' / ')}</small></div><div data-party-item-host></div></div>`;
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
    const restoreItemControl = index => {
        const label = itemSelects[index]?.closest('label');
        if (!label || label.parentElement === itemGrid) return;
        label.classList.remove('party-item-control');
        itemGrid.append(label);
    };
    const showOccupied = (slot, entry, index, name) => {
        restoreItemControl(index);
        slot.className = 'team-preview-slot';
        slot.dataset.name = name;
        slot.dataset.state = 'ready';
        slot.dataset.partySlot = index;
        slot.innerHTML = occupiedMarkup(entry, index);
        const itemControl = itemSelects[index]?.closest('label');
        const itemHost = slot.querySelector('[data-party-item-host]');
        if (itemControl && itemHost) {
            itemControl.hidden = false;
            itemControl.classList.add('party-item-control');
            itemHost.replaceWith(itemControl);
        }
        centerPartySprite(slot.querySelector('img'));
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
        itemsPanel.hidden = true;
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
                    restoreItemControl(index);
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
                restoreItemControl(index);
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
                restoreItemControl(index);
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
    const arenaWrap = battleApp.closest('.arena-wrap');
    const battleScreen = battleApp.querySelector('.battle-screen');
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
        effects: document.querySelector('#attack-effects'),
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
    let realtimeQueue = Promise.resolve();
    const spectator = config.kind === 'spectator';
    let opponentPresent = null;
    let opponentMissingFor = 0;
    let presenceTimer = null;

    const fitBattleLayout = (secondPass = false) => {
        if (!arenaWrap || !battleScreen) return;
        arenaWrap.style.removeProperty('width');
        const availableWidth = Math.min(1320, arenaWrap.parentElement?.clientWidth || window.innerWidth);
        const commandPanel = battleApp.querySelector('.command-panel');
        const stableCommandHeight = battleApp.classList.contains('local-mode') ? 0 : 142;
        const reservedCommandSpace = Math.max(0, stableCommandHeight - (commandPanel?.offsetHeight || 0));
        const nonStageHeight = Math.max(0, battleApp.scrollHeight - battleScreen.offsetHeight + reservedCommandSpace);
        const availableHeight = Math.max(220, window.innerHeight - arenaWrap.getBoundingClientRect().top - nonStageHeight - 12);
        const fittedWidth = Math.min(availableWidth, availableHeight * (16 / 9));
        arenaWrap.style.width = `${Math.max(Math.min(availableWidth, 360), fittedWidth)}px`;
        if (!secondPass) {
            requestAnimationFrame(() => fitBattleLayout(true));
        } else {
            centerBattleSprite(els.playerSprite);
            centerBattleSprite(els.opponentSprite);
        }
    };

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
    const eventSignature = payload => JSON.stringify(payload?.state?.last_events || []);
    const hasUnseenBattleEvents = next => Boolean(next?.state?.last_events?.length) && (
        !currentPayload?.state
        || Number(next.version) !== Number(currentPayload.version)
        || next.state.turn !== currentPayload.state.turn
        || next.state.phase !== currentPayload.state.phase
        || eventSignature(next) !== eventSignature(currentPayload)
    );
    const active = (state, key) => state.players[key].roster[state.players[key].active];
    const other = key => key === 'p1' ? 'p2' : 'p1';
    const hpPercent = (current, maximum) => Math.max(0, Math.min(100, Math.round(current / Math.max(1, maximum) * 100)));
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const typeLabel = type => config.text.types?.[type] || type;
    const hud = (pokemon, displayedHp = pokemon.current_hp) => {
        const percent = hpPercent(displayedHp, pokemon.max_hp);
        const status = pokemon.status ? `<b class="status-badge status-${escape(pokemon.status)}">${escape(pokemon.status)}</b>` : '';
        return `<div class="hud-name"><span>${escape(pokemon.label)} ${status}</span><span>Lv.100</span></div><div class="type-tags">${pokemon.types.map(type => `<span class="type-${escape(type)}">${escape(typeLabel(type))}</span>`).join('')}${pokemon.ability ? `<span class="ability-tag">${escape(pokemon.ability)}</span>` : ''}</div><div class="hp-line"><b>${escape(config.text.hp)}</b><div class="hp-track"><div class="hp-fill ${percent < 25 ? 'low' : ''}" style="width:${percent}%"></div></div></div><div class="hp-numbers">${displayedHp} / ${pokemon.max_hp}</div>`;
    };
    const moveMarkup = (pokemon, disabled) => pokemon.moves.map((move, index) => `<button class="move-button type-${escape(move.type)}" data-move="${index}" ${(disabled || (move.current_pp ?? move.pp ?? 1) <= 0) ? 'disabled' : ''}><span>${escape(move.label)}</span><small>${escape(typeLabel(move.type))} · ${move.power || '—'} PUI · ${move.current_pp ?? move.pp ?? '?'} PP</small></button>`).join('');
    const switchMarkup = (state, key, disabled, forced = false) => `<div class="switch-control ${forced ? 'is-open' : ''}"><button type="button" class="switch-toggle" data-switch-toggle aria-expanded="${forced ? 'true' : 'false'}" ${forced ? 'data-forced' : ''}>${escape(forced ? config.text.chooseReplacement : config.text.switch)}</button><div class="switch-strip ${forced ? 'forced-switch' : ''}" ${forced ? '' : 'hidden'}>${state.players[key].roster.map((pokemon, index) => `<button type="button" data-switch="${index}" ${(disabled || index === state.players[key].active || pokemon.current_hp <= 0) ? 'disabled' : ''} title="${escape(pokemon.label)}"><img src="${escape(pokemon.sprites.front)}" alt=""><span>${escape(pokemon.label)}<small>${pokemon.current_hp}/${pokemon.max_hp}</small></span></button>`).join('')}</div></div>`;
    const controlsMarkup = (state, key, disabled, forced = false) => forced
        ? switchMarkup(state, key, disabled, true)
        : moveMarkup(active(state, key), disabled) + switchMarkup(state, key, disabled);
    const bindActions = container => {
        container.querySelectorAll('[data-move]').forEach(button => button.addEventListener('click', () => act({action_type: 'move', move_index: Number(button.dataset.move)})));
        container.querySelectorAll('[data-switch]').forEach(button => button.addEventListener('click', () => act({action_type: 'switch', pokemon_index: Number(button.dataset.switch)})));
        container.querySelectorAll('[data-switch-toggle]').forEach(button => button.addEventListener('click', () => {
            if (button.hasAttribute('data-forced')) return;
            const roster = button.nextElementSibling;
            const opening = roster.hidden;
            roster.hidden = !opening;
            button.setAttribute('aria-expanded', String(opening));
            button.closest('.switch-control')?.classList.toggle('is-open', opening);
            fitBattleLayout();
        }));
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
        centerBattleSprite(sprite);
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
        requestAnimationFrame(() => fitBattleLayout());
    };
    const animateHp = (key, you, fromHp, currentHp, maximumHp) => new Promise(resolve => {
        const targetHud = hudFor(key, you);
        const fill = targetHud.querySelector('.hp-fill');
        const numbers = targetHud.querySelector('.hp-numbers');
        if (!fill || !numbers) {
            resolve();
            return;
        }
        const difference = currentHp - fromHp;
        const changedRatio = Math.abs(difference) / Math.max(1, maximumHp);
        const duration = Math.min(1350, Math.max(340, 300 + changedRatio * 1050));
        const startedAt = performance.now();
        fill.style.transition = 'none';
        const tick = now => {
            const progress = Math.min(1, (now - startedAt) / duration);
            const eased = 1 - ((1 - progress) ** 2);
            const displayedHp = Math.round(fromHp + difference * eased);
            const percent = hpPercent(displayedHp, maximumHp);
            fill.classList.toggle('low', percent < 25);
            fill.style.width = `${percent}%`;
            numbers.textContent = `${displayedHp} / ${maximumHp}`;
            if (progress < 1) {
                requestAnimationFrame(tick);
                return;
            }
            fill.style.removeProperty('transition');
            resolve();
        };
        requestAnimationFrame(tick);
    });
    const playSound = name => {
        if (soundEnabled) playGameSound(name, soundVolume);
    };
    const attackTypes = new Set(['normal', 'fire', 'water', 'electric', 'grass', 'ice', 'fighting', 'poison', 'ground', 'flying', 'psychic', 'bug', 'rock', 'ghost', 'dragon', 'dark', 'steel', 'fairy']);
    const playAttackEffect = (type, damageClass, actor, you) => {
        if (!els.effects) return Promise.resolve();
        const effectType = attackTypes.has(type) ? type : 'normal';
        const effectClass = ['physical', 'special', 'status'].includes(damageClass) ? damageClass : 'physical';
        const effect = document.createElement('div');
        effect.className = `attack-effect type-${effectType} class-${effectClass} ${actor === you ? 'from-player' : 'from-opponent'}`;
        effect.innerHTML = '<i></i><i></i><i></i><i></i><i></i><i></i>';
        els.effects.replaceChildren(effect);
        return wait(760).then(() => {
            if (effect.parentNode) effect.remove();
        });
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
        let criticalHit = false;

        for (const event of events) {
            showMessage(event.text || '');
            if (event.type === 'attack') {
                await wait(260);
                const attacker = spriteElementFor(event.actor, you);
                attacker.classList.remove('lunge-right', 'lunge-left');
                void attacker.offsetWidth;
                attacker.classList.add(event.actor === you ? 'lunge-right' : 'lunge-left');
                playSound(`attack-${event.move_type || 'normal'}-${event.damage_class || 'physical'}`);
                await Promise.all([wait(700), playAttackEffect(event.move_type || 'normal', event.damage_class || 'physical', event.actor, you)]);
                attacker.classList.remove('lunge-right', 'lunge-left');
                continue;
            }
            if (event.type === 'critical') {
                criticalHit = true;
                await wait(300);
                continue;
            }
            if (['damage', 'recoil', 'status-damage', 'weather-damage'].includes(event.type)) {
                const target = spriteElementFor(event.target, you);
                target.classList.remove('hit-shake');
                void target.offsetWidth;
                target.classList.add('hit-shake');
                const effectiveness = Number(event.effectiveness ?? 1);
                const impactSound = criticalHit
                    ? 'critical-impact'
                    : effectiveness > 1
                        ? 'super-effective-impact'
                        : effectiveness < 1
                            ? 'resisted-impact'
                            : 'impact';
                playSound(impactSound);
                await wait(500);
                target.classList.remove('hit-shake');
                const previousHp = visualHp[event.target];
                const nextHp = Math.max(0, previousHp - Number(event.amount || 0));
                await animateHp(event.target, you, previousHp, nextHp, maximumHp[event.target]);
                visualHp[event.target] = nextHp;
                criticalHit = false;
                continue;
            }
            if (event.type === 'heal') {
                playSound('heal');
                const previousHp = visualHp[event.target];
                const nextHp = Math.min(maximumHp[event.target], previousHp + Number(event.amount || 0));
                await animateHp(event.target, you, previousHp, nextHp, maximumHp[event.target]);
                visualHp[event.target] = nextHp;
                continue;
            }
            if (event.type === 'immune') {
                playSound('immune');
                criticalHit = false;
                await wait(600);
                continue;
            }
            if (event.type === 'miss') {
                playSound('miss');
                criticalHit = false;
                await wait(520);
                continue;
            }
            if (event.type === 'protect') {
                playSound('protect');
                criticalHit = false;
                await wait(520);
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
        if (animating && Number(next.version) === Number(currentPayload?.version)) return;
        document.querySelector('.waiting-card')?.remove();
        battleApp.classList.remove('is-waiting');
        const previous = currentPayload;
        currentPayload = next;
        els.result.hidden = true;

        if (animate && previous?.state && next.state.last_events?.length) {
            animating = true;
            draw(previous, {disabled: true, preserveMessage: true, showResult: false});
            await playSequence(next.state.last_events, previous.state, next.state, next.you || 'p1');
            const finalKnockout = next.state.phase === 'finished' && next.state.last_events.some(event => event.type === 'faint');
            if (finalKnockout) await wait(1200);
            animating = false;
            draw(next, {showResult: false});
            if (next.state.phase === 'finished') {
                await wait(finalKnockout ? 650 : 450);
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
            const hasBattleEvents = hasUnseenBattleEvents(nextNormalized);
            if (config.kind === 'online' && window.Echo?.connector?.pusher?.connection?.state === 'connected') {
                busy = false;

                await wait(150);
                const realtimeMatchesResponse = renderedVersion === nextNormalized.version
                    && Boolean(currentPayload?.submitted) === Boolean(nextNormalized.submitted)
                    && currentPayload?.status === nextNormalized.status;
                if (!realtimeMatchesResponse) {
                    const shouldAnimate = hasBattleEvents || renderedVersion === null || nextNormalized.version !== renderedVersion;
                    renderedVersion = nextNormalized.version;
                    await render(next, shouldAnimate);
                } else if (!animating && currentPayload) {
                    draw(currentPayload);
                }

                return;
            }
            const shouldAnimate = hasBattleEvents || action.action_type === 'switch' || renderedVersion === null || nextNormalized.version !== renderedVersion || config.mode === 'local';
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
            const nextNormalized = normalized(next);
            const version = nextNormalized.version;
            const shouldAnimate = renderedVersion !== null && (hasUnseenBattleEvents(nextNormalized) || version !== renderedVersion);
            await render(next, shouldAnimate);
            renderedVersion = version;
        } catch (error) {
            els.message.textContent = error.message;
        } finally {
            refreshing = false;
        }
    };
    const receiveRealtime = payload => {
        realtimeQueue = realtimeQueue.then(async () => {
            const next = personalized(payload);
            const version = Number(next.version);
            if (renderedVersion !== null && version < Number(renderedVersion)) return;
            const shouldAnimate = renderedVersion !== null && (hasUnseenBattleEvents(next) || version !== Number(renderedVersion));
            renderedVersion = version;
            await render(next, shouldAnimate);
        }).catch(error => {
            console.error('[PokéLine] Realtime rendering failed.', error);
        });

        return realtimeQueue;
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
            if (response.battle) {
                await receiveRealtime(response.battle);
                return;
            }
            const sync = response.sync;
            const ownSubmission = Boolean(sync?.submitted?.[config.you]);
            const stateIsStale = sync && (
                Number(sync.version) !== Number(renderedVersion)
                || sync.status !== currentPayload?.status
                || ownSubmission !== Boolean(currentPayload?.submitted)
            );
            if (stateIsStale) await refresh();
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
                if (!spectator && user.role === 'player' && user.side === otherSide) {
                    setOpponentPresence(true);
                    refresh();
                }
            })
            .leaving(user => {
                if (!spectator && user.role === 'player' && user.side === otherSide) setOpponentPresence(false);
            })
            .listen('.updated', receiveRealtime)
            .error(error => {
                console.error('[PokéLine] Reverb channel subscription failed.', error);
                setConnectionState('unavailable', config.text.unavailable);
            });

        window.Echo.connector.pusher.connection.bind('state_change', states => {
            if (states.current === 'connected') {
                setConnectionState('connected', config.text.connected);
                refresh();
            } else if (['connecting', 'unavailable'].includes(states.current)) {
                setConnectionState('connecting', config.text.reconnecting);
            } else if (['failed', 'disconnected'].includes(states.current)) {
                console.error('[PokéLine] Reverb connection state:', states.current);
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
    window.addEventListener('resize', () => fitBattleLayout());
    refresh();
    if (config.kind === 'online' || config.kind === 'spectator') connectRealtime();
    if (config.kind === 'online') {
        heartbeat();
        setInterval(heartbeat, 5000);
    }
}
