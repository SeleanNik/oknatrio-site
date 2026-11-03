// Отзывы на главной: карусель (по 3 на десктопе), стрелки, «Читать полностью».
const list = document.querySelector('[data-reviews]');
if (list) {
  const prev = document.querySelector('[data-rev-prev]');
  const next = document.querySelector('[data-rev-next]');
  const step = () => {
    const c = list.querySelector('.rcard');
    return c ? c.getBoundingClientRect().width + parseFloat(getComputedStyle(list).columnGap || 0) : list.clientWidth;
  };
  const arrows = () => {
    const max = list.scrollWidth - list.clientWidth - 2;
    if (prev) prev.disabled = list.scrollLeft <= 2;
    if (next) next.disabled = list.scrollLeft >= max;
  };
  const sync = () => {
    arrows();
    list.querySelectorAll('.rcard').forEach((card) => {
      const q = card.querySelector('blockquote');
      const more = card.querySelector('[data-rev-more]');
      if (!q || !more || card.classList.contains('is-open')) return;
      more.hidden = q.scrollHeight <= q.clientHeight + 1;
    });
  };
  prev && prev.addEventListener('click', () => list.scrollBy({ left: -step(), behavior: 'smooth' }));
  next && next.addEventListener('click', () => list.scrollBy({ left: step(), behavior: 'smooth' }));
  list.addEventListener('scroll', arrows, { passive: true });
  list.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); next && next.click(); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); prev && prev.click(); }
  });
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
