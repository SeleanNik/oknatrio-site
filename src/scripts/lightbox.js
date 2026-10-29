// Полноэкранный просмотр фото: клик по карточке с data-gallery открывает все её фото целиком.
// Управление: стрелки, миниатюры, клавиши ← → Esc, свайп, клик по тёмному фону.
let dlg;
let state = { urls: [], i: 0 };

const ICON = {
  prev: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>',
  next: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>',
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
  });
  let x0 = null;
  const stage = dlg.querySelector('.lb__stage');
  stage.addEventListener('pointerdown', (e) => { x0 = e.clientX; });
  stage.addEventListener('pointerup', (e) => {
    if (x0 != null && Math.abs(e.clientX - x0) > 50) go(state.i + (e.clientX < x0 ? 1 : -1));
    x0 = null;
  });
  document.body.classList.remove('lb-open');
  dlg.addEventListener('close', () => { document.body.style.overflow = ''; });
}

function go(n) {
  const len = state.urls.length;
  state.i = (n + len) % len;
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
  if (!box || e.target.closest('.slider__nav, .slider__dot, a, button')) return;
  if (box.dataset.swiped) return;
  let urls;
  try { urls = JSON.parse(box.dataset.gallery); } catch { return; }
  const on = box.querySelector('.slider img.is-on');
  const idx = on ? [...box.querySelectorAll('.slider img')].indexOf(on) : 0;
  const title = (box.closest('figure, article, li')?.querySelector('h3, strong')?.textContent || '').trim();
  openLightbox(urls, Math.max(0, idx), title);
});
