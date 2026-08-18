import './bootstrap';

const settingsDialog = document.querySelector('#settings-dialog');
const settingsButton = document.querySelector('#settings-open');
const soundSetting = document.querySelector('#global-sound');
let soundEnabled = localStorage.getItem('pokeline_sound') !== 'off';

if (settingsDialog && settingsButton) {
    soundSetting.checked = soundEnabled;
    settingsButton.addEventListener('click', () => settingsDialog.showModal());
    soundSetting.addEventListener('change', () => {
        soundEnabled = soundSetting.checked;
        localStorage.setItem('pokeline_sound', soundEnabled ? 'on' : 'off');
    });
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
    gameDialog.addEventListener('close', () => { gameFrame.src = 'about:blank'; hub.focus(); });
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
            if (!selected.includes(name) && selected.length < 6) selected.push(name);
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

document.querySelectorAll('[data-team-input]').forEach(input => {
    const preview = document.querySelector(`[data-team-preview="${input.id}"]`);
    const itemSelects = [...document.querySelectorAll(`[data-item-select="${input.id}"]`)];
    const typeLabels = JSON.parse(input.closest('[data-type-labels]')?.dataset.typeLabels || '{}');
    const translatedType = type => typeLabels[type] || type;
    let previewTimer;
    const escapePreview = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const renderPreview = async () => {
        const names = input.value.split(',').map(value => value.trim().toLowerCase()).filter(Boolean).slice(0, 6);
        if (!names.length) {
            preview.innerHTML = Array.from({length: 6}, (_, index) => `<div class="team-preview-slot empty"><span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><div class="party-data"><div class="party-name"><b>—</b><em>Lv.—</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:0"></span></i></div><div class="party-meta"><small>— / —</small><small>—</small></div></div></div>`).join('');
            itemSelects.forEach((select, index) => {
                const label = select.closest('label')?.querySelector('[data-item-slot-label]');
                if (label) label.textContent = `#${index + 1} —`;
            });
            return;
        }
        preview.innerHTML = names.map((name, index) => `<div class="team-preview-slot loading"><span>${index + 1}</span><b>${escapePreview(name)}</b></div>`).join('');
        const pokemon = await Promise.all(names.map(async name => {
            try {
                const response = await fetch(`/api/pokemon/${encodeURIComponent(name)}`, {headers: {Accept: 'application/json'}});
                return response.ok ? response.json() : null;
            } catch { return null; }
        }));
        preview.innerHTML = pokemon.map((entry, index) => entry
            ? `<div class="team-preview-slot" data-party-slot="${index}"><button type="button" class="party-remove" data-remove-pokemon="${index}" aria-label="×">×</button><span class="party-index">${index + 1}</span><i class="party-ball" aria-hidden="true"></i><img src="${escapePreview(entry.sprites.front)}" alt=""><div class="party-data"><div class="party-name"><b>${escapePreview(entry.label)}</b><em>Lv.100</em></div><div class="party-hp"><strong>PV</strong><i><span style="width:100%"></span></i></div><div class="party-meta"><small>${entry.stats.hp} / ${entry.stats.hp}</small><small>${entry.types.map(type => escapePreview(translatedType(type))).join(' / ')}</small></div><div class="party-item" data-party-item>${escapePreview(itemSelects[index]?.selectedOptions[0]?.textContent.split(' — ')[0] || '—')}</div></div></div>`
            : `<div class="team-preview-slot invalid"><span>${index + 1}</span><b>${escapePreview(names[index])}</b><small>?</small></div>`).join('');
        itemSelects.forEach((select, index) => {
            const label = select.closest('label')?.querySelector('[data-item-slot-label]');
            if (label) label.textContent = `#${index + 1} ${pokemon[index]?.label || '—'}`;
        });
        preview.querySelectorAll('[data-remove-pokemon]').forEach(button => button.addEventListener('click', () => {
            const removeIndex = Number(button.dataset.removePokemon);
            const selected = input.value.split(',').map(value => value.trim()).filter(Boolean);
            selected.splice(removeIndex, 1);
            for (let index = removeIndex; index < itemSelects.length - 1; index++) itemSelects[index].value = itemSelects[index + 1].value;
            if (itemSelects.length) itemSelects.at(-1).value = '';
            input.value = selected.join(', ');
            input.dispatchEvent(new Event('change', {bubbles: true}));
        }));
    };
    input.addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(renderPreview, 350); });
    input.addEventListener('change', renderPreview);
    itemSelects.forEach(select => select.addEventListener('change', () => {
        const item = preview.querySelector(`[data-party-slot="${select.dataset.slot}"] [data-party-item]`);
        if (item) item.textContent = select.selectedOptions[0]?.textContent.split(' — ')[0] || '—';
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
    const config = {kind:battleApp.dataset.kind,mode:battleApp.dataset.mode,stateUrl:battleApp.dataset.stateUrl,actionUrl:battleApp.dataset.actionUrl,text:JSON.parse(battleApp.dataset.translations)};
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const els = {playerSprite:document.querySelector('#player-sprite'),opponentSprite:document.querySelector('#opponent-sprite'),playerHud:document.querySelector('#player-hud'),opponentHud:document.querySelector('#opponent-hud'),moves:document.querySelector('#moves'),localP1:document.querySelector('#local-moves-p1'),localP2:document.querySelector('#local-moves-p2'),message:document.querySelector('#battle-message'),log:document.querySelector('#battle-log'),turn:document.querySelector('#turn-label'),weather:document.querySelector('#weather-label'),sound:document.querySelector('#sound-toggle')};
    let payload = null, busy = false, renderedVersion = null;
    const request = async (url,options={}) => { const {headers={},...requestOptions}=options;const response=await fetch(url,{...requestOptions,headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,...headers}}); if(!response.ok) throw new Error((await response.json().catch(()=>({}))).message||`HTTP ${response.status}`); return response.json() };
    const normalized = raw => config.kind==='session'?{state:raw.state,mode:raw.mode,pending:raw.pending,you:'p1',version:raw.state?.turn}:raw;
    const active=(state,key)=>state.players[key].roster[state.players[key].active];
    const other=key=>key==='p1'?'p2':'p1';
    const hpPercent=pokemon=>Math.max(0,Math.round(pokemon.current_hp/pokemon.max_hp*100));
    const escape=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const typeLabel=type=>config.text.types?.[type]||type;
    const hud=pokemon=>{const percent=hpPercent(pokemon),status=pokemon.status?`<b class="status-badge status-${escape(pokemon.status)}">${escape(pokemon.status)}</b>`:'';return `<div class="hud-name"><span>${escape(pokemon.label)} ${status}</span><span>Lv.100</span></div><div class="type-tags">${pokemon.types.map(type=>`<span class="type-${escape(type)}">${escape(typeLabel(type))}</span>`).join('')}${pokemon.ability?`<span>${escape(pokemon.ability)}</span>`:''}</div><div class="hp-line"><b>${escape(config.text.hp)}</b><div class="hp-track"><div class="hp-fill ${percent<25?'low':''}" style="width:${percent}%"></div></div></div><div class="hp-numbers">${pokemon.current_hp} / ${pokemon.max_hp}</div>`};
    const moveMarkup=(pokemon,disabled)=>pokemon.moves.map((move,index)=>`<button class="move-button type-${escape(move.type)}" data-move="${index}" ${(disabled||(move.current_pp??move.pp??1)<=0)?'disabled':''}><span>${escape(move.label)}</span><small>${escape(typeLabel(move.type))} · ${move.power||'—'} PUI · ${move.current_pp??move.pp??'?'} PP</small></button>`).join('');
    const switchMarkup=(state,key,disabled)=>`<div class="switch-strip"><strong>${escape(config.text.switch)}</strong>${state.players[key].roster.map((pokemon,index)=>`<button type="button" data-switch="${index}" ${(disabled||index===state.players[key].active||pokemon.current_hp<=0)?'disabled':''} title="${escape(pokemon.label)}"><img src="${escape(pokemon.sprites.front)}" alt=""><span>${escape(pokemon.label)}<small>${pokemon.current_hp}/${pokemon.max_hp}</small></span></button>`).join('')}</div>`;
    const controlsMarkup=(state,key,disabled)=>moveMarkup(active(state,key),disabled)+switchMarkup(state,key,disabled);
    const bindActions=container=>{container.querySelectorAll('[data-move]').forEach(button=>button.addEventListener('click',()=>act({action_type:'move',move_index:Number(button.dataset.move)})));container.querySelectorAll('[data-switch]').forEach(button=>button.addEventListener('click',()=>act({action_type:'switch',pokemon_index:Number(button.dataset.switch)})))};
    const render=(raw,animate=false)=>{payload=normalized(raw);if(!payload.state)return;document.querySelector('.waiting-card')?.remove();battleApp.classList.remove('is-waiting');const state=payload.state,you=payload.you||'p1',enemy=other(you),ownPokemon=active(state,you),enemyPokemon=active(state,enemy);els.playerSprite.src=ownPokemon.sprites.back||ownPokemon.sprites.front;els.playerSprite.alt=ownPokemon.label;els.opponentSprite.src=enemyPokemon.sprites.front;els.opponentSprite.alt=enemyPokemon.label;els.playerHud.innerHTML=hud(ownPokemon);els.opponentHud.innerHTML=hud(enemyPokemon);els.turn.textContent=`${config.text.turn} ${state.turn}`;els.weather.textContent=state.weather?`${config.text.weather}: ${state.weather.toUpperCase()} (${state.weather_turns})`:'';els.log.innerHTML=state.log.slice().reverse().map(line=>`<div>› ${escape(line)}</div>`).join('');const submitted=Boolean(payload.submitted),disabled=busy||submitted||state.phase!=='active';els.moves.innerHTML=controlsMarkup(state,you,disabled);bindActions(els.moves);if((payload.mode||config.mode)==='local'){battleApp.classList.add('local-mode');els.localP1.innerHTML=controlsMarkup(state,'p1',busy||payload.pending!==null||state.phase!=='active');els.localP2.innerHTML=controlsMarkup(state,'p2',busy||payload.pending===null||state.phase!=='active');bindActions(els.localP1);bindActions(els.localP2)}els.message.textContent=state.phase==='finished'?(state.winner===you?config.text.victory:config.text.defeat):(submitted?config.text.waiting:(state.last_events?.at(-1)?.text||config.text.choose));if(state.phase==='finished'&&payload.reward?.length)els.message.textContent+=` ${config.text.rewards}: ${payload.reward.join(', ')}`;if(animate&&state.last_events?.length)animateEvents(state.last_events,you)};
    const animateEvents=(events,you)=>{const attack=events.find(event=>event.type==='attack'),impact=events.find(event=>['damage','immune'].includes(event.type));if(attack){const attacker=attack.actor===you?els.playerSprite:els.opponentSprite;attacker.classList.remove('lunge-right','lunge-left');void attacker.offsetWidth;attacker.classList.add(attack.actor===you?'lunge-right':'lunge-left');playSound(attack.move)}if(impact)setTimeout(()=>{const target=impact.target===you?els.playerSprite:els.opponentSprite;target.classList.remove('hit-shake');void target.offsetWidth;target.classList.add('hit-shake');playSound('impact',true)},220)};
    const playSound=(name,impact=false)=>{if(!soundEnabled)return;const AudioContext=window.AudioContext||window.webkitAudioContext;if(!AudioContext)return;const context=new AudioContext(),oscillator=context.createOscillator(),gain=context.createGain();oscillator.type=impact?'square':'sawtooth';const seed=[...name].reduce((sum,char)=>sum+char.charCodeAt(0),0);oscillator.frequency.setValueAtTime(impact?95:160+seed%220,context.currentTime);oscillator.frequency.exponentialRampToValueAtTime(impact?55:80,context.currentTime+.16);gain.gain.setValueAtTime(.07,context.currentTime);gain.gain.exponentialRampToValueAtTime(.001,context.currentTime+.18);oscillator.connect(gain).connect(context.destination);oscillator.start();oscillator.stop(context.currentTime+.19);oscillator.addEventListener('ended',()=>context.close())};
    const act=async action=>{if(busy)return;busy=true;document.querySelectorAll('.moves-grid button,.switch-strip button').forEach(button=>button.disabled=true);try{const next=await request(config.actionUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(action)});busy=false;render(next,true)}catch(error){els.message.textContent=error.message}finally{busy=false}};
    const refresh=async()=>{try{const next=await request(config.stateUrl),version=normalized(next).version,shouldAnimate=renderedVersion!==null&&version!==renderedVersion;render(next,shouldAnimate);renderedVersion=version}catch(error){els.message.textContent=error.message}};
    els.sound.addEventListener('click',()=>{soundEnabled=!soundEnabled;localStorage.setItem('pokeline_sound',soundEnabled?'on':'off');if(soundSetting)soundSetting.checked=soundEnabled;els.sound.textContent=`${soundEnabled?'🔊':'🔇'} ${config.text.sound}`});els.sound.textContent=`${soundEnabled?'🔊':'🔇'} ${config.text.sound}`;refresh();if(config.kind==='online')setInterval(refresh,1400);
}
