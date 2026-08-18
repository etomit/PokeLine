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
    const destinations = {
        solo: {x: 28, y: 31, url: hub.dataset.solo},
        local: {x: 73, y: 49, url: hub.dataset.local},
        online: {x: 25, y: 71, url: hub.dataset.online},
    };
    let position = {x: 49, y: 68};
    let direction = 'up';
    let frame = 0;
    let near = null;
    const keys = new Set();

    const displayName = key => document.querySelector(`[data-destination="${key}"]`)?.textContent.replace(/^\s*\d+/, '').trim() || key;
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
        let dx = 0, dy = 0;
        if (keys.has('arrowleft') || keys.has('a') || keys.has('q')) { dx -= .48; direction = 'left'; }
        if (keys.has('arrowright') || keys.has('d')) { dx += .48; direction = 'right'; }
        if (keys.has('arrowup') || keys.has('w') || keys.has('z')) { dy -= .48; direction = 'up'; }
        if (keys.has('arrowdown') || keys.has('s')) { dy += .48; direction = 'down'; }
        if (dx || dy) {
            position.x = Math.max(10, Math.min(90, position.x + dx));
            position.y = Math.max(14, Math.min(87, position.y + dy));
            frame = Math.floor(Date.now() / 150) % 2 ? 1 : 2;
            update();
        } else if (frame) { frame = 0; update(); }
        requestAnimationFrame(walk);
    };
    const ignored = () => ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement?.tagName) || settingsDialog?.open;
    window.addEventListener('keydown', event => {
        if (ignored()) return;
        const key = event.key.toLowerCase();
        if (['arrowleft','arrowright','arrowup','arrowdown','w','a','s','d','z','q'].includes(key)) { event.preventDefault(); keys.add(key); }
        if ((key === 'enter' || key === 'e') && near) { event.preventDefault(); window.location.href = destinations[near].url; }
    });
    window.addEventListener('keyup', event => keys.delete(event.key.toLowerCase()));
    document.querySelectorAll('[data-destination]').forEach(marker => marker.addEventListener('click', () => window.location.href = destinations[marker.dataset.destination].url));
    hub.addEventListener('click', () => hub.focus());
    update(); walk(); hub.focus();
}

const battleApp = document.querySelector('#battle-app');
if (battleApp) {
    const config = {kind:battleApp.dataset.kind,stateUrl:battleApp.dataset.stateUrl,actionUrl:battleApp.dataset.actionUrl,text:JSON.parse(battleApp.dataset.translations)};
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const els = {playerSprite:document.querySelector('#player-sprite'),opponentSprite:document.querySelector('#opponent-sprite'),playerHud:document.querySelector('#player-hud'),opponentHud:document.querySelector('#opponent-hud'),moves:document.querySelector('#moves'),localP1:document.querySelector('#local-moves-p1'),localP2:document.querySelector('#local-moves-p2'),message:document.querySelector('#battle-message'),log:document.querySelector('#battle-log'),turn:document.querySelector('#turn-label'),sound:document.querySelector('#sound-toggle')};
    let payload = null, busy = false, renderedVersion = null;
    const request = async (url,options={}) => { const response=await fetch(url,{headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,...(options.headers||{})},...options}); if(!response.ok) throw new Error((await response.json().catch(()=>({}))).message||`HTTP ${response.status}`); return response.json() };
    const normalized = raw => config.kind==='session'?{state:raw.state,mode:raw.mode,pending:raw.pending,you:'p1',version:raw.state?.turn}:raw;
    const active=(state,key)=>state.players[key].roster[state.players[key].active];
    const other=key=>key==='p1'?'p2':'p1';
    const hpPercent=pokemon=>Math.max(0,Math.round(pokemon.current_hp/pokemon.max_hp*100));
    const escape=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const hud=pokemon=>{const percent=hpPercent(pokemon);return `<div class="hud-name"><span>${escape(pokemon.label)}</span><span>Lv100</span></div><div class="type-tags">${pokemon.types.map(type=>`<span>${escape(type)}</span>`).join('')}</div><div class="hp-line"><b>HP</b><div class="hp-track"><div class="hp-fill ${percent<25?'low':''}" style="width:${percent}%"></div></div></div><div class="hp-numbers">${pokemon.current_hp} / ${pokemon.max_hp}</div>`};
    const moveMarkup=(pokemon,disabled)=>pokemon.moves.map((move,index)=>`<button class="move-button" data-move="${index}" ${disabled?'disabled':''}>${escape(move.label)}<small>${escape(move.type)} // ${move.power} PWR</small></button>`).join('');
    const bindMoves=container=>container.querySelectorAll('[data-move]').forEach(button=>button.addEventListener('click',()=>act(Number(button.dataset.move))));
    const render=(raw,animate=false)=>{payload=normalized(raw);if(!payload.state)return;document.querySelector('.waiting-card')?.remove();battleApp.classList.remove('is-waiting');const state=payload.state,you=payload.you||'p1',enemy=other(you),ownPokemon=active(state,you),enemyPokemon=active(state,enemy);els.playerSprite.src=ownPokemon.sprites.back||ownPokemon.sprites.front;els.playerSprite.alt=ownPokemon.label;els.opponentSprite.src=enemyPokemon.sprites.front;els.opponentSprite.alt=enemyPokemon.label;els.playerHud.innerHTML=hud(ownPokemon);els.opponentHud.innerHTML=hud(enemyPokemon);els.turn.textContent=`${config.text.turn} ${state.turn}`;els.log.innerHTML=state.log.slice().reverse().map(line=>`<div>› ${escape(line)}</div>`).join('');const submitted=Boolean(payload.submitted);els.moves.innerHTML=moveMarkup(ownPokemon,busy||submitted||state.phase!=='active');bindMoves(els.moves);if(payload.mode==='local'){battleApp.classList.add('local-mode');els.localP1.innerHTML=moveMarkup(active(state,'p1'),busy||payload.pending!==null||state.phase!=='active');els.localP2.innerHTML=moveMarkup(active(state,'p2'),busy||payload.pending===null||state.phase!=='active');bindMoves(els.localP1);bindMoves(els.localP2)}els.message.textContent=state.phase==='finished'?(state.winner===you?config.text.victory:config.text.defeat):(submitted?config.text.waiting:(state.last_events?.at(-1)?.text||config.text.choose));if(state.phase==='finished'&&payload.reward?.length)els.message.textContent+=` ${config.text.rewards}: ${payload.reward.join(', ')}`;if(animate&&state.last_events?.length)animateEvents(state.last_events,you)};
    const animateEvents=(events,you)=>{const attack=events.find(event=>event.type==='attack'),impact=events.find(event=>['damage','immune'].includes(event.type));if(attack){const attacker=attack.actor===you?els.playerSprite:els.opponentSprite;attacker.classList.remove('lunge-right','lunge-left');void attacker.offsetWidth;attacker.classList.add(attack.actor===you?'lunge-right':'lunge-left');playSound(attack.move)}if(impact)setTimeout(()=>{const target=impact.target===you?els.playerSprite:els.opponentSprite;target.classList.remove('hit-shake');void target.offsetWidth;target.classList.add('hit-shake');playSound('impact',true)},220)};
    const playSound=(name,impact=false)=>{if(!soundEnabled)return;const AudioContext=window.AudioContext||window.webkitAudioContext;if(!AudioContext)return;const context=new AudioContext(),oscillator=context.createOscillator(),gain=context.createGain();oscillator.type=impact?'square':'sawtooth';const seed=[...name].reduce((sum,char)=>sum+char.charCodeAt(0),0);oscillator.frequency.setValueAtTime(impact?95:160+seed%220,context.currentTime);oscillator.frequency.exponentialRampToValueAtTime(impact?55:80,context.currentTime+.16);gain.gain.setValueAtTime(.07,context.currentTime);gain.gain.exponentialRampToValueAtTime(.001,context.currentTime+.18);oscillator.connect(gain).connect(context.destination);oscillator.start();oscillator.stop(context.currentTime+.19);oscillator.addEventListener('ended',()=>context.close())};
    const act=async moveIndex=>{if(busy)return;busy=true;els.moves.querySelectorAll('button').forEach(button=>button.disabled=true);try{const next=await request(config.actionUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({move_index:moveIndex})});busy=false;render(next,true)}catch(error){els.message.textContent=error.message}finally{busy=false}};
    const refresh=async()=>{try{const next=await request(config.stateUrl),version=normalized(next).version,shouldAnimate=renderedVersion!==null&&version!==renderedVersion;render(next,shouldAnimate);renderedVersion=version}catch(error){els.message.textContent=error.message}};
    els.sound.addEventListener('click',()=>{soundEnabled=!soundEnabled;localStorage.setItem('pokeline_sound',soundEnabled?'on':'off');if(soundSetting)soundSetting.checked=soundEnabled;els.sound.textContent=`${soundEnabled?'🔊':'🔇'} ${config.text.sound}`});els.sound.textContent=`${soundEnabled?'🔊':'🔇'} ${config.text.sound}`;refresh();if(config.kind==='online')setInterval(refresh,1400);
}
