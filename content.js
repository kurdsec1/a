'use strict';

const s = document.createElement('script');
s.src = chrome.runtime.getURL('inject.js');
s.onload = () => s.remove();
(document.head || document.documentElement).appendChild(s);

const SK = 'agarz_v2';
let S = {
  autoMode: false,
  showOverlay: true,
  showRadar: true,
  macros: [
    { id: 'm1', name: 'YEM', key: 'e', color: '#34c759' },
    { id: 'm2', name: 'BÖLN', key: 'space', color: '#ff3b30' }
  ]
};

chrome.storage.local.get(SK, r => {
  if (r[SK]) S = { ...S, ...r[SK] };
  init();
});

function saveS() {
  chrome.storage.local.set({ [SK]: S });
}

let gameData = { viruses: [], mode: 'idle' };
window.addEventListener('agarz_update', e => {
  gameData = e.detail;
  updateUI();
});

let panel = null;

function createPanel() {
  panel = document.createElement('div');
  panel.id = 'agarz-panel';
  panel.innerHTML = `
    <div class="ap-header">
      <span>🎮 Agarz Pro v2</span>
      <button class="ap-close">✕</button>
    </div>

    <div class="ap-content">
      <div class="ap-stat">
        <span>🌱 Virüs Tespit:</span>
        <span class="virus-count">0</span>
      </div>

      <button class="ap-btn btn-green" id="btn-start">▶ BAŞLAT</button>
      <button class="ap-btn btn-red" id="btn-stop" style="display:none">⏹ DURDUR</button>

      <div class="ap-section">
        <div class="ap-section-title">⚡ MAKROLAR</div>
        <div class="macro-grid" id="macro-grid"></div>
      </div>

      <div class="ap-section">
        <label class="ap-check">
          <input type="checkbox" id="toggle-overlay" checked>
          Virüs Göster
        </label>
        <label class="ap-check">
          <input type="checkbox" id="toggle-radar" checked>
          Radar
        </label>
      </div>
    </div>
  `;

  document.body.appendChild(panel);

  panel.querySelector('.ap-close').onclick = () => { panel.style.display = 'none'; };

  const btnStart = panel.querySelector('#btn-start');
  const btnStop = panel.querySelector('#btn-stop');

  btnStart.onclick = () => {
    if (window.AGARZ_PRO) window.AGARZ_PRO.startAuto();
    btnStart.style.display = 'none';
    btnStop.style.display = 'block';
    S.autoMode = true;
    saveS();
  };

  btnStop.onclick = () => {
    if (window.AGARZ_PRO) window.AGARZ_PRO.stopAuto();
    btnStop.style.display = 'none';
    btnStart.style.display = 'block';
    S.autoMode = false;
    saveS();
  };

  const macroGrid = panel.querySelector('#macro-grid');
  macroGrid.innerHTML = S.macros.map(m => `
    <button class="macro-btn" data-key="${m.key}" style="background: ${m.color}">${m.name}</button>
  `).join('');

  macroGrid.querySelectorAll('.macro-btn').forEach(btn => {
    btn.onclick = () => { if (window.AGARZ_PRO) window.AGARZ_PRO.sendKey(btn.dataset.key); };
  });

  panel.querySelector('#toggle-overlay').onchange = e => {
    S.showOverlay = e.target.checked;
    saveS();
  };

  panel.querySelector('#toggle-radar').onchange = e => {
    S.showRadar = e.target.checked;
    saveS();
  };
}

function updateUI() {
  if (!panel) return;
  const count = panel.querySelector('.virus-count');
  if (count) count.textContent = gameData.viruses?.length || 0;
}

let markers = [];

function updateOverlay() {
  markers.forEach(m => m.remove());
  markers = [];

  if (!S.showOverlay || !gameData.viruses) return;

  gameData.viruses.slice(0, 5).forEach(v => {
    const div = document.createElement('div');
    div.innerHTML = '🌱';
    div.className = 'agarz-marker';
    div.style.left = (v.x - 20) + 'px';
    div.style.top = (v.y - 20) + 'px';
    document.body.appendChild(div);
    markers.push(div);
  });
}

function createToggle() {
  const btn = document.createElement('button');
  btn.innerHTML = '🎮';
  btn.id = 'agarz-toggle';
  btn.onmouseover = () => { btn.style.transform = 'scale(1.12)'; };
  btn.onmouseout = () => { btn.style.transform = 'scale(1)'; };
  btn.onclick = () => {
    panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
  };
  document.body.appendChild(btn);
}

function init() {
  createToggle();
  createPanel();
  if (!S.autoMode) panel.style.display = 'none';
}

chrome.runtime.onMessage.addListener(msg => {
  if (msg.action === 'toggle') {
    panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
  }
});

setInterval(updateOverlay, 500);
