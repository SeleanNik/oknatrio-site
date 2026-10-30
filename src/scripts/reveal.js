// Появление блоков при прокрутке.
// Скрываем только то, что ниже первого экрана; после показа возвращаем элементу его обычные transition.
// reveal(root) можно вызывать повторно: уже обработанные элементы пропускаются (live-data.js вызывает его для новых карточек).
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

const TARGETS = [
  '.prod__head', '.tile', '.uslugi h2', '.uc', '.works .intro', '.works .w', '.rev h2', '.rcard',
  '.lead-block__left', '.lead-block__card', '.types h2', '.bhead', '.tcard', '.fgrid .fcard', '.faq h2', '.faq details', '.section details',
  '.breadcrumbs', '.sec-head > *', '.head h1', '.head .lead', '.head .chips', '.info > h1', '.rh__text > *', '.khead > *', '.filters', '.works li', '.ogrid li', '.objects .head', '.catband__card', '.brands__in', '.cta', '.cta__in', '.prose > *', '.info > .lead', '.container > .eyebrow', '.khead', '.kc', '.kform', '.kmap', '.vcard', '.file', '.banner', '.brands__row li', '.footer__col', '.footer__brand',
].join(',');

const done = new WeakSet();
let io = null;

const observer = () => io || (io = new IntersectionObserver((entries) => {
  entries.forEach((en) => {
    if (!en.isIntersecting) return;
    const el = en.target;
    io.unobserve(el);
    el.classList.add('in');
    setTimeout(() => { el.removeAttribute('data-rv'); el.classList.remove('in'); el.style.removeProperty('--d'); }, 1300);
  });
}, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' }));

export function reveal(root = document) {
  if (reduce || !('IntersectionObserver' in window)) return;
  const seen = new Map();
  const list = root.matches?.(TARGETS) ? [root, ...root.querySelectorAll(TARGETS)] : [...root.querySelectorAll(TARGETS)];
  list.forEach((el) => {
    if (done.has(el)) return;
    done.add(el);
    const parent = el.parentElement;
    const i = seen.get(parent) || 0; seen.set(parent, i + 1);
    if (el.getBoundingClientRect().top < innerHeight * 0.92) { // уже на экране: короткая анимация входа при загрузке
      el.style.setProperty('--d', `${120 + Math.min(i, 5) * 90}ms`);
      el.setAttribute('data-rl', '');
      setTimeout(() => { el.removeAttribute('data-rl'); el.style.removeProperty('--d'); }, 1600);
      return;
    }
    el.style.setProperty('--d', `${Math.min(i, 5) * 70}ms`);
    el.setAttribute('data-rv', '');
    observer().observe(el);
  });
}
