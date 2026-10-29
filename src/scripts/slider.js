// Галерея в карточке: если фото несколько, они плавно листаются (автоматически, по точкам и свайпом).
// Разметка на сборке: контейнер с одним <img> и data-gallery='["url1","url2"]'. Здесь она «оживает».
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const timers = new WeakMap();

const stop = (box) => { const t = timers.get(box); if (t) { clearInterval(t); timers.delete(box); } };

// Возвращает контейнер к одной картинке (обложке) и останавливает автолистание
function teardown(box) {
  stop(box);
  const slider = box.querySelector(':scope > .slider');
  if (!slider) return box.querySelector('img');
  const first = slider.querySelector('img');
  first.classList.remove('is-on');
  slider.replaceWith(first);
  return first;
}

export function applyGallery(box, urls, alt) {
  if (!box) return;
  const list = (urls || []).filter(Boolean);
  const base = teardown(box);
  if (!base) return;
  if (list.length) base.loading = 'eager'; // обложка нужна сразу, остальные фото подгружаются по мере листания
  if (list.length) {
    base.removeAttribute('srcset'); base.removeAttribute('sizes'); base.src = list[0];
    box.dataset.gallery = JSON.stringify(list); // по клику эти фото открываются в полноэкранном просмотре
  }
  if (alt != null) base.alt = alt;
  if (list.length < 2) return;

  // размеры стилей известны только у элемента в документе: клоны из rebuild() подключаются чуть позже
  const mount = () => {
    const abs = getComputedStyle(base).position === 'absolute';
    const bs = getComputedStyle(box);
    const fill = abs || bs.aspectRatio !== 'auto' || bs.position === 'absolute'; // контейнер с заданным размером: слайдер занимает его целиком
    // обложка остаётся в потоке и держит размер карточки, остальные фото накладываются поверх
    const extra = list.slice(1).map((u) => {
      const im = base.cloneNode(true);
      im.removeAttribute('srcset'); im.removeAttribute('sizes');
      im.src = u; im.alt = ''; im.loading = 'lazy';
      return im;
    });
    const imgs = [base, ...extra];
    const slider = document.createElement('div');
    slider.className = 'slider' + (fill ? ' slider--fill' : '') + (abs ? ' slider--abs' : '');
    const dots = document.createElement('div');
    dots.className = 'slider__dots';
    const btns = list.map((_, i) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'slider__dot' + (i === 0 ? ' is-on' : ''); b.setAttribute('aria-label', `Фото ${i + 1}`);
      dots.append(b); return b;
    });
    const arrow = (dir) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = `slider__nav slider__nav--${dir}`; b.setAttribute('aria-label', dir === 'prev' ? 'Предыдущее фото' : 'Следующее фото');
      b.innerHTML = dir === 'prev'
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>';
      return b;
    };
    const prevBtn = arrow('prev');
    const nextBtn = arrow('next');
    base.replaceWith(slider);
    base.classList.add('is-on');
    slider.append(base, ...extra, dots, prevBtn, nextBtn);
  let cur = 0;
  let token = 0;
  const apply = (n) => {
    cur = n;
    imgs.forEach((im, i) => im.classList.toggle('is-on', i === cur));
    btns.forEach((b, i) => b.classList.toggle('is-on', i === cur));
  };
  // переключаемся только на уже загруженное фото, чтобы вместо него не мелькал тёмный фон
  const show = (n) => {
    n = (n + imgs.length) % imgs.length;
    const im = imgs[n]; const t = ++token;
    if (im.loading === 'lazy') im.loading = 'eager';
    if (im.complete && im.naturalWidth) return apply(n);
    im.addEventListener('load', () => { if (t === token) apply(n); }, { once: true });
  };
  const start = () => { if (!reduce && !timers.has(box)) timers.set(box, setInterval(() => show(cur + 1), 4500 + Math.random() * 800)); };
  prevBtn.addEventListener('click', () => { show(cur - 1); stop(box); start(); });
  nextBtn.addEventListener('click', () => { show(cur + 1); stop(box); start(); });
  dots.addEventListener('click', (e) => { const i = btns.indexOf(e.target.closest('button')); if (i >= 0) { show(i); stop(box); start(); } });
  // свайп
  let x0 = null;
  box.addEventListener('pointerdown', (e) => { x0 = e.clientX; });
  box.addEventListener('pointerup', (e) => { if (x0 != null && Math.abs(e.clientX - x0) > 40) { show(cur + (e.clientX < x0 ? 1 : -1)); box.dataset.swiped = '1'; setTimeout(() => delete box.dataset.swiped, 80); } x0 = null; });
  box.addEventListener('mouseenter', () => stop(box));
  box.addEventListener('mouseleave', start);
  // листаем только то, что видно на экране
  if ('IntersectionObserver' in window) {
    new IntersectionObserver((en) => en.forEach((e) => { if (e.isIntersecting) { extra.forEach((im) => { im.loading = 'eager'; }); start(); } else stop(box); }), { threshold: 0.2 }).observe(box);
  } else start();
  };
  if (box.isConnected) mount(); else queueMicrotask(mount);
}

export function initGalleries(root = document) {
  root.querySelectorAll('[data-gallery]').forEach((box) => {
    try { applyGallery(box, JSON.parse(box.dataset.gallery)); } catch { /* пропускаем */ }
  });
}
