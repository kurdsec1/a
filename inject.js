'use strict';

window.AGARZ_PRO = {
  viruses: [],
  players: [],
  myCell: { x: 0, y: 0, size: 0 },
  autoMode: false
};

const origArc = CanvasRenderingContext2D.prototype.arc;
const origFill = CanvasRenderingContext2D.prototype.fill;
let lastArc = null;

CanvasRenderingContext2D.prototype.arc = function(x, y, r, sa, ea, ccw) {
  lastArc = { x, y, r, ctx: this };
  return origArc.call(this, x, y, r, sa, ea, ccw);
};

CanvasRenderingContext2D.prototype.fill = function(rule) {
  if (lastArc) {
    try {
      const style = this.fillStyle?.toString?.()?.toLowerCase() || '';
      const rgb = style.match(/\d+/g) || [];

      if (rgb.length >= 3) {
        const [r, g, b] = rgb.map(Number);

        if (g > 150 && g > r * 1.5 && g > b * 1.5) {
          window.AGARZ_PRO.viruses.push({
            x: lastArc.x,
            y: lastArc.y,
            r: lastArc.r,
            time: Date.now()
          });
        }
      }
    } catch (e) {
      // Ignore errors from fillStyle parsing (e.g., CanvasGradient objects)
    }
    lastArc = null;
  }
  return origFill.call(this, rule);
};

const KEY_CODES = { space: 32, e: 69, w: 87, r: 82, a: 65, s: 83 };

window.AGARZ_PRO.sendKey = function(key) {
  const canvas = document.querySelector('canvas');
  if (!canvas) return;

  const code = KEY_CODES[key] || key.charCodeAt(0);

  canvas.dispatchEvent(new KeyboardEvent('keydown', { key, keyCode: code, bubbles: true }));
  setTimeout(() => {
    canvas.dispatchEvent(new KeyboardEvent('keyup', { key, keyCode: code, bubbles: true }));
  }, 50);
};

window.AGARZ_PRO.startAuto = function() {
  this.autoMode = true;
  this.interval = setInterval(() => {
    if (!this.autoMode) return;

    const now = Date.now();
    this.viruses = this.viruses.filter(v => now - v.time < 3000);

    if (!this.viruses.length) return;

    const nearest = this.viruses.reduce((a, b) => {
      const da = Math.hypot(a.x - this.myCell.x, a.y - this.myCell.y);
      const db = Math.hypot(b.x - this.myCell.x, b.y - this.myCell.y);
      return da < db ? a : b;
    });

    window.dispatchEvent(new CustomEvent('agarz_update', {
      detail: { viruses: this.viruses, nearest, mode: 'auto' }
    }));
  }, 300);
};

window.AGARZ_PRO.stopAuto = function() {
  this.autoMode = false;
  clearInterval(this.interval);
};

console.log('✓ Agarz Pro Inject Aktif');
