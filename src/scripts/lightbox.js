// Полноэкранный просмотр фото: клик по карточке с data-gallery открывает все её фото целиком.
// Управление: стрелки, миниатюры, клавиши ← → Esc, свайп, клик по тёмному фону.
let dlg;
let state = { urls: [], i: 0 };
const Z = { s: 1, x: 0, y: 0 }; // приближение: масштаб и сдвиг
const Z_MAX = 5;

const ICON = {
  prev: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>',
  next: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  minus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/></svg>',
  close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
};

function build() {
  dlg = document.createElement('dialog');
  dlg.className = 'lb';
  dlg.setAttribute('aria-label', 'Просмотр фото');
  dlg.innerHTML = `
    <div class="lb__stage">
      <span class="lb__count"></span><span class="lb__title"></span>
      <button type="button" class="lb__btn lb__close" aria-label="Закрыть">${ICON.close}</button>
      <div class="lb__zoom"><button type="button" class="lb__btn lb__zout" aria-label="Уменьшить">${ICON.minus}</button><button type="button" class="lb__btn lb__zin" aria-label="Приблизить">${ICON.plus}</button></div>
      <button type="button" class="lb__btn lb__prev" aria-label="Предыдущее фото">${ICON.prev}</button>
      <button type="button" class="lb__btn lb__next" aria-label="Следующее фото">${ICON.next}</button>
      <img class="lb__img" alt="">
    </div>
    <div class="lb__thumbs"></div>`;
  document.body.append(dlg);

  dlg.querySelector('.lb__close').addEventListener('click', () => dlg.close());
  dlg.querySelector('.lb__prev').addEventListener('click', () => go(state.i - 1));
  dlg.querySelector('.lb__next').addEventListener('click', () => go(state.i + 1));
  dlg.addEventListener('click', (e) => { if (e.target === dlg || e.target.classList.contains('lb__stage')) dlg.close(); });
  dlg.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') go(state.i - 1);
    else if (e.key === 'ArrowRight') go(state.i + 1);
    else if (e.key === '+' || e.key === '=') zoomCenter(1.5);
    else if (e.key === '-' || e.key === '_') zoomCenter(1 / 1.5);
    else if (e.key === '0') resetZoom();
  });
  const stage = dlg.querySelector('.lb__stage');
  dlg.querySelector('.lb__zin').addEventListener('click', () => zoomCenter(1.5));
  dlg.querySelector('.lb__zout').addEventListener('click', () => zoomCenter(1 / 1.5));
  stage.addEventListener('wheel', (e) => { e.preventDefault(); zoomAt(Z.s * Math.exp(-e.deltaY * 0.002), e.clientX, e.clientY); }, { passive: false });
  stage.addEventListener('dblclick', (e) => { if (e.target.closest('.lb__btn')) return; zoomAt(Z.s > 1.05 ? 1 : 2.5, e.clientX, e.clientY); });

  // указатели: один палец/мышь двигает приближенное фото или листает, два пальца приближают
  const ptr = new Map();
  let start = null; let pinch = null;
  stage.addEventListener('pointerdown', (e) => {
    if (e.target.closest('.lb__btn')) return;
    ptr.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (ptr.size === 1) start = { x: e.clientX, y: e.clientY, zx: Z.x, zy: Z.y };
    if (ptr.size === 2) {
      const [a, b2] = [...ptr.values()];
      pinch = { d: Math.hypot(a.x - b2.x, a.y - b2.y) || 1, s: Z.s };
    }
    stage.setPointerCapture?.(e.pointerId);
  });
  stage.addEventListener('pointermove', (e) => {
    if (!ptr.has(e.pointerId)) return;
    ptr.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (ptr.size === 2 && pinch) {
      const [a, b2] = [...ptr.values()];
      zoomAt(pinch.s * (Math.hypot(a.x - b2.x, a.y - b2.y) / pinch.d), (a.x + b2.x) / 2, (a.y + b2.y) / 2);
    } else if (ptr.size === 1 && start && Z.s > 1.001) {
      Z.x = start.zx + (e.clientX - start.x); Z.y = start.zy + (e.clientY - start.y);
      clampZ(); applyZ();
      dlg.classList.add('lb--drag');
    }
  });
  const up = (e) => {
    if (!ptr.has(e.pointerId)) return;
    const wasSingle = ptr.size === 1;
    ptr.delete(e.pointerId);
    dlg.classList.remove('lb--drag');
    if (ptr.size < 2) pinch = null;
    if (wasSingle && start && Z.s <= 1.001 && Math.abs(e.clientX - start.x) > 50 && Math.abs(e.clientX - start.x) > Math.abs(e.clientY - start.y)) go(state.i + (e.clientX < start.x ? 1 : -1));
    if (ptr.size === 0) start = null;
  };
  stage.addEventListener('pointerup', up);
  stage.addEventListener('pointercancel', up);
  document.body.classList.remove('lb-open');
  dlg.addEventListener('close', () => { document.body.style.overflow = ''; });
}

function applyZoom() {
  const im = dlg.querySelector('.lb__img');
  im.style.scale = Z.s;
  im.style.translate = `${Z.x}px ${Z.y}px`;
  dlg.classList.toggle('lb--zoomed', Z.s > 1.001);
}
function clampZ() {
  const im = dlg.querySelector('.lb__img');
  const mx = (im.offsetWidth * (Z.s - 1)) / 2 + 40;
  const my = (im.offsetHeight * (Z.s - 1)) / 2 + 40;
  Z.x = Math.max(-mx, Math.min(mx, Z.x)); Z.y = Math.max(-my, Math.min(my, Z.y));
}
// приближает так, чтобы точка (cx, cy) под курсором осталась на месте
function zoomAt(next, cx, cy) {
  const s2 = Math.max(1, Math.min(Z_MAX, next));
  const r = dlg.querySelector('.lb__img').getBoundingClientRect();
  const c0x = r.left + r.width / 2 - Z.x; const c0y = r.top + r.height / 2 - Z.y;
  const k = s2 / Z.s;
  Z.x = cx - c0x - (cx - c0x - Z.x) * k; Z.y = cy - c0y - (cy - c0y - Z.y) * k;
  Z.s = s2;
  if (s2 <= 1.001) { Z.s = 1; Z.x = 0; Z.y = 0; }
  clampZ(); applyZoom();
}
function zoomCenter(k) {
  const r = dlg.querySelector('.lb__stage').getBoundingClientRect();
  zoomAt(Z.s * k, r.left + r.width / 2, r.top + r.height / 2);
}
function resetZoom() { Z.s = 1; Z.x = 0; Z.y = 0; if (dlg) applyZoom(); }

function go(n) {
  const len = state.urls.length;
  state.i = (n + len) % len;
  resetZoom();
  const img = dlg.querySelector('.lb__img');
  img.style.animation = 'none'; void img.offsetWidth; img.style.animation = '';
  img.src = state.urls[state.i];
  img.alt = state.title || '';
  dlg.querySelector('.lb__count').textContent = `${state.i + 1} / ${len}`;
  dlg.querySelectorAll('.lb__thumbs button').forEach((b, i) => b.classList.toggle('is-on', i === state.i));
  // подгружаем соседние фото заранее
  [state.i + 1, state.i - 1].forEach((k) => { const u = state.urls[(k + len) % len]; if (u) new Image().src = u; });
}

export function openLightbox(urls, start = 0, title = '') {
  const list = (urls || []).filter(Boolean);
  if (!list.length) return;
  if (!dlg) build();
  state = { urls: list, i: start, title };
  dlg.classList.toggle('lb--single', list.length < 2);
  dlg.querySelector('.lb__title').textContent = title || '';
  const thumbs = dlg.querySelector('.lb__thumbs');
  thumbs.replaceChildren(...list.map((u, i) => {
    const b = document.createElement('button');
    b.type = 'button'; b.setAttribute('aria-label', `Фото ${i + 1}`);
    const im = document.createElement('img');
    im.src = u; im.alt = ''; im.loading = 'lazy';
    b.append(im);
    b.addEventListener('click', () => go(i));
    return b;
  }));
  document.body.style.overflow = 'hidden';
  dlg.showModal();
  go(start);
}

document.addEventListener('click', (e) => {
  const box = e.target.closest('[data-gallery]');
  if (!box || box.hasAttribute('data-nolb') || e.target.closest('.slider__nav, .slider__dot, a, button')) return; // data-nolb: карточки без полноэкранного просмотра
  if (box.dataset.swiped) return;
  let urls;
  try { urls = JSON.parse(box.dataset.gallery); } catch { return; }
  const on = box.querySelector('.slider img.is-on');
  const idx = on ? [...box.querySelectorAll('.slider img')].indexOf(on) : 0;
  const title = (box.closest('figure, article, li')?.querySelector('h3, strong')?.textContent || '').trim();
  openLightbox(urls, Math.max(0, idx), title);
});
