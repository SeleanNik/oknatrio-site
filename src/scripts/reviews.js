// Отзывы на главной: кнопка «Читать полностью» у длинных текстов.
const list = document.querySelector('[data-reviews]');
if (list) {
  const sync = () => {
    list.querySelectorAll('.rcard').forEach((card) => {
      const q = card.querySelector('blockquote');
      const more = card.querySelector('[data-rev-more]');
      if (!q || !more || card.classList.contains('is-open')) return;
      more.hidden = q.scrollHeight <= q.clientHeight + 1;
    });
  };
  list.addEventListener('click', (e) => {
    const b = e.target.closest('[data-rev-more]');
    if (!b) return;
    const card = b.closest('.rcard');
    const open = card.classList.toggle('is-open');
    b.textContent = open ? 'Свернуть' : 'Читать полностью';
    b.hidden = false;
  });
  window.addEventListener('resize', sync);
  new MutationObserver(sync).observe(list, { childList: true });
  addEventListener('load', sync);
  sync();
}

// Карусель отзывов: подсветка карточки по центру и точки-индикатор
const track = document.querySelector('[data-reviews]');
const dots = document.querySelector('[data-rev-dots]');
if (track && dots) {
  let cards = [];
  const mark = () => {
    if (!cards.length) return;
    const c = track.scrollLeft + track.clientWidth / 2;
    let best = 0, bd = Infinity;
    cards.forEach((el, i) => {
      const d = Math.abs(el.offsetLeft + el.offsetWidth / 2 - c);
      if (d < bd) { bd = d; best = i; }
    });
    cards.forEach((el, i) => el.classList.toggle('is-active', i === best));
    [...dots.children].forEach((d, i) => d.classList.toggle('is-on', i === best));
  };
  const build = () => {
    cards = [...track.querySelectorAll('.rcard')];
    dots.innerHTML = '';
    cards.forEach((el, i) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('aria-label', 'Отзыв ' + (i + 1));
      b.addEventListener('click', () => track.scrollTo({ left: el.offsetLeft - (track.clientWidth - el.offsetWidth) / 2, behavior: 'smooth' }));
      dots.appendChild(b);
    });
    mark();
  };
  let raf = 0;
  track.addEventListener('scroll', () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(mark); }, { passive: true });
  window.addEventListener('resize', mark);
  new MutationObserver(build).observe(track, { childList: true });
  addEventListener('load', build);
  build();
}

// Мышь: колесо листает отзывы по одной карточке, а перетаскивание работает как свайп на телефоне
if (track) {
  const items = () => [...track.querySelectorAll('.rcard')];
  const current = () => {
    const c = track.scrollLeft + track.clientWidth / 2;
    let best = 0, bd = Infinity;
    items().forEach((el, i) => { const d = Math.abs(el.offsetLeft + el.offsetWidth / 2 - c); if (d < bd) { bd = d; best = i; } });
    return best;
  };
  const go = (i) => {
    const el = items()[i];
    if (el) track.scrollTo({ left: el.offsetLeft - (track.clientWidth - el.offsetWidth) / 2, behavior: 'smooth' });
  };
  let lock = 0;
  track.addEventListener('wheel', (e) => {
    if (!matchMedia('(min-width: 900px)').matches) return;
    const d = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    if (!d || e.ctrlKey) return;
    const i = current(), next = i + (d > 0 ? 1 : -1);
    if (next < 0 || next >= items().length) return; // на краю отдаём прокрутку странице
    e.preventDefault();
    if (Date.now() < lock) return;
    lock = Date.now() + 450;
    go(next);
  }, { passive: false });

  let down = null, moved = false;
  track.addEventListener('pointerdown', (e) => {
    if (e.pointerType !== 'mouse' || e.button !== 0 || e.target.closest('button')) return;
    down = { x: e.clientX, left: track.scrollLeft };
    moved = false;
  });
  window.addEventListener('pointermove', (e) => {
    if (!down) return;
    const dx = e.clientX - down.x;
    if (Math.abs(dx) > 5 && !moved) { moved = true; track.style.scrollSnapType = 'none'; track.style.cursor = 'grabbing'; }
    if (moved) track.scrollLeft = down.left - dx;
  });
  const end = () => {
    if (!down) return;
    down = null;
    if (!moved) return;
    track.style.scrollSnapType = '';
    track.style.cursor = '';
    go(current());
  };
  window.addEventListener('pointerup', end);
  window.addEventListener('pointercancel', end);
  track.addEventListener('dragstart', (e) => e.preventDefault());
  track.style.cursor = 'grab';
}
