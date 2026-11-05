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
