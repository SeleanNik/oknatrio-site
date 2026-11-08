// «Живые» данные из админки: контакты, отзывы и контент страниц лежат в /data/*.json
// и обновляются без пересборки сайта. В HTML значения на момент сборки уже есть (для SEO),
// здесь только подтягиваем актуальные. Карточки не создаются с нуля, а клонируются из готовых,
// чтобы сохранялись стили (Astro scoped).
import { applyGallery, initGalleries } from './slider.js';
import './lightbox.js';
import { reveal } from './reveal.js';
import { mdToHtml, docSig } from './mini-md.js';

const get = (obj, path) => String(path).split('.').reduce((a, k) => (a == null ? a : a[k]), obj);
const load = (url) => fetch(url, { cache: 'no-cache' }).then((r) => (r.ok ? r.json() : null)).catch(() => null);
const setImg = (img, url, alt) => {
  if (!img) return;
  if (url) { img.removeAttribute('srcset'); img.removeAttribute('sizes'); img.src = url; }
  if (alt != null) img.alt = alt;
};
const lastText = (el, text) => { // меняет текст после иконки, не трогая svg
  const t = [...el.childNodes].reverse().find((n) => n.nodeType === 3);
  if (t) t.textContent = text; else el.append(text);
};
const rebuild = (container, tplSel, items, fill) => {
  const tpl = container.querySelector(tplSel);
  if (!tpl) return;
  const frag = document.createDocumentFragment();
  items.forEach((it, i) => {
    const n = tpl.cloneNode(true);
    // клон не должен унаследовать «скрыто до появления» от анимации прокрутки (ui.js): его никто бы не показал
    [n, ...n.querySelectorAll('[data-rv], [data-rl]')].forEach((el) => {
      el.removeAttribute('data-rv'); el.removeAttribute('data-rl'); el.classList.remove('in'); el.style.removeProperty('--d');
    });
    fill(n, it, i);
    frag.append(n);
  });
  container.replaceChildren(frag);
  reveal(container); // новые карточки появляются с той же анимацией, что и остальные блоки
};

// ---------- контакты ----------
if (document.querySelector('[data-bind], [data-bind-href]')) {
  load('/data/site.json').then((d) => {
    if (!d) return;
    document.querySelectorAll('[data-bind]').forEach((el) => {
      const v = get(d, el.dataset.bind);
      if (typeof v === 'string' && v) el.textContent = v;
    });
    document.querySelectorAll('[data-bind-href]').forEach((el) => {
      const v = get(d, el.dataset.bindHref);
      if (typeof v === 'string' && v) el.setAttribute('href', (el.dataset.bindPrefix || '') + v);
    });
  });
}

// ---------- отзывы на главной ----------
const revList = document.querySelector('[data-reviews]');
const revTpl = revList && revList.querySelector('.rcard');
if (revTpl) {
  load('/data/reviews.json').then((items) => {
    if (!Array.isArray(items)) return;
    const shown = items.filter((r) => r && r.home !== false && r.text).slice(0, 6); // карусель — до шести отзывов
    if (!shown.length) return;
    rebuild(revList, '.rcard', shown, (li, r) => {
      li.querySelector('[data-rev="text"]').textContent = r.text;
      li.querySelector('[data-rev="name"]').textContent = r.name || '';
      const ava = li.querySelector('[data-rev="ava"]');
      if (ava) ava.textContent = (r.name || '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
    });
  });
}

// ---------- контент страниц ----------
const EDITABLE = '[data-doc], [data-ed-cards], [data-plain], [data-ed], [data-ed-types], [data-ed-specs], [data-ed-faq], [data-ed-works-tag], [data-ed-files], [data-ed-profile], [data-ed-work], [data-works]';
if (document.querySelector(EDITABLE)) {
  load('/data/pages.json').then((d) => {
    if (!d) return;
    const works = d.works && Array.isArray(d.works.items) ? d.works.items : [];
    const tagLabel = { cottage: 'Коттедж', aluminum: 'Алюминий', pvc: 'ПВХ' };
    // где показывать работу задаёт админка (поле where); у старых записей без него действует прежнее правило
    const worksAt = (place, fallback) => {
      const picks = d[place] && d[place].worksPick;
      const list = Array.isArray(picks) ? picks.map((id) => works.find((w) => w.id === id)).filter(Boolean) : fallback;
      return list.slice(0, place === 'home' ? 3 : 4);
    };

    // документы и правила (политика, согласия, соглашение)
    document.querySelectorAll('[data-doc]').forEach((art) => {
      const doc = (d.docs && d.docs.items || []).find((x) => x.id === art.dataset.doc);
      if (!doc) return;
      const { html, headings } = mdToHtml(doc.body);
      if (docSig(doc.body) !== art.dataset.sig) art.innerHTML = html;
      const h1 = document.querySelector('[data-doc-h1]');
      if (h1 && doc.title) h1.textContent = doc.title;
      const lead = document.querySelector('[data-doc-lead]');
      if (lead) { lead.textContent = doc.lead || ''; lead.hidden = !doc.lead; }
      const ol = document.querySelector('#toc ol');
      if (ol && art.dataset.docToc) {
        const h2 = headings.filter((h) => h.depth === 2);
        rebuild(ol, 'li', h2, (li, h) => { const a = li.querySelector('a'); a.href = '#' + h.slug; a.textContent = h.text.replace(/^\d+\.\s*/, ''); });
      }
    });

    // простые тексты
    document.querySelectorAll('[data-ed]').forEach((el) => {
      const v = get(d, el.dataset.ed);
      if (typeof v === 'string') el.textContent = v;
    });

    // главная: карточка со статистикой (число + подпись)
    const hs = d.home && d.home.heroStats;
    if (Array.isArray(hs)) {
      document.querySelectorAll('.hero__stats li[data-stat]').forEach((li) => {
        const [num, ...rest] = String(hs[li.dataset.stat] || '').split(' ');
        const b = li.querySelector('b');
        if (b.dataset.val !== num) { // значение отличается от того, что в HTML: обновляем и запускаем счёт заново
          b.dataset.val = num; b.textContent = num;
          document.dispatchEvent(new CustomEvent('hero:stat', { detail: Number(li.dataset.stat) }));
        }
        li.querySelector('span').textContent = rest.join(' ');
      });
    }

    // главная: «Продукция» (5 фиксированных карточек)
    const cards = d.home && d.home.prod && d.home.prod.cards;
    if (cards) {
      document.querySelectorAll('#produkciya .cards > li').forEach((li, i) => {
        const c = cards[i];
        if (!c) return;
        const a = li.querySelector('a.tile');
        if (a && c.href) a.setAttribute('href', c.href);
        setImg(li.querySelector('.tile__img'), c.image, c.brand ? `${c.title} ${c.brand}` : c.title);
        const small = li.querySelector('.tile__top small');
        const strong = li.querySelector('.tile__top strong');
        if (small) { small.hidden = !c.brand; small.textContent = c.brand ? c.title : ''; }
        if (strong) { strong.textContent = c.brand || c.title; strong.classList.toggle('is-brand', !!c.brand); }
      });
    }

    // главная: «Наши работы» (первые 3 работы в карточках)
    document.querySelectorAll('[data-ed-work]').forEach((fig) => {
      const w = worksAt('home', works)[Number(fig.dataset.edWork)];
      fig.hidden = !w;
      if (!w) return;
      applyGallery(fig, w.images, w.title);
      const cap = fig.querySelector('figcaption');
      if (cap) { cap.querySelector('strong').textContent = w.title; cap.querySelector('span').textContent = w.place || ''; }
    });

    // страница «Наши работы»
    const wl = document.querySelector('[data-works]');
    if (wl && works.length) {
      rebuild(wl, 'li', works, (li, w) => {
        li.hidden = false;
        li.dataset.tags = (w.tags || []).join(' ');
        applyGallery(li.querySelector('.wmedia'), w.images, w.title);
        const cap = li.querySelector('figcaption');
        cap.querySelector('small').textContent = (w.tags || []).map((t) => tagLabel[t]).filter(Boolean).join(' · ');
        cap.querySelector('strong').textContent = w.title;
        cap.querySelector('span').textContent = w.place || '';
      });
      document.querySelector('[data-filter][aria-pressed="true"]')?.click(); // применить текущий фильтр
    }

    // страницы брендов: «Наши работы» (до 4, выбранных в админке)
    document.querySelectorAll('[data-ed-where]').forEach((ul) => {
      const id = ul.dataset.edWhere;
      const list = worksAt(id, works.filter((w) => `${w.title} ${w.place}`.toLowerCase().includes(id)));
      if (!list.length) return;
      rebuild(ul, 'li', list, (li, w) => applyGallery(li.querySelector('figure'), w.images, w.title));
    });

    // раздел: «Наши работы» (до 4 по тегу)
    document.querySelectorAll('[data-ed-works-tag]').forEach((ul) => {
      const tag = ul.dataset.edWorksTag;
      const list = works.filter((w) => (w.tags || []).includes(tag)).slice(0, 4);
      if (!list.length) return;
      rebuild(ul, 'li', list, (li, w) => applyGallery(li.querySelector('figure'), w.images, w.title));
    });

    // раздел: виды (карточки)
    const firstGrid = document.querySelector('.tgrid');
    document.querySelectorAll('[data-ed-types]').forEach((grid) => {
      const types = get(d, grid.dataset.edTypes);
      if (!Array.isArray(types)) return;
      const brand = grid.dataset.edTypes.split('.').pop();
      const prefix = grid === firstGrid || !grid.dataset.edTypes.includes('typesByBrand') ? '' : brand.toLowerCase() + '-';
      rebuild(grid, '.tcard', types, (n, t) => {
        n.id = prefix + t.id;
        applyGallery(n.querySelector('.tcard__media'), t.images, t.title);
        n.querySelector('h3').textContent = t.title;
        n.querySelector('.tcard__text').textContent = t.text;
        const ul = n.querySelector('.tcard__feats');
        const points = String(t.points || '').split('\n').map((x) => x.trim()).filter(Boolean);
        if (ul) rebuild(ul, 'li', points, (li, p) => lastText(li, p));
      });
    });

    // страницы брендов: карточки систем (вкладка + панель)
    const lines = (v) => String(v || '').split('\n').map((x) => x.trim()).filter(Boolean);
    document.querySelectorAll('[data-ed-cards]').forEach((g) => {
      const list = get(d, g.dataset.edCards);
      if (!Array.isArray(list) || !list.length) return;
      const brand = g.dataset.brandName || '';
      const tabs = g.querySelector('.sys__tabs');
      const panels = g.querySelector('[data-panels]');
      const hint = g.querySelector('.sys__hint');
      if (!tabs || !panels) return;
      tabs.hidden = list.length < 2;
      if (hint) hint.hidden = list.length < 2;
      rebuild(tabs, '.stab', list, (t, c, i) => {
        t.style.setProperty('--k', i);
        t.id = `tab-${c.id}`; t.setAttribute('aria-controls', `panel-${c.id}`); t.dataset.sys = c.id;
        t.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
        const img = t.querySelector('.stab__thumb img'), b = t.querySelector('.stab__thumb b');
        if (img) { img.hidden = !c.image; if (c.image) img.src = c.image; else img.removeAttribute('src'); }
        if (b) { b.hidden = !!c.image; b.textContent = c.name; }
        t.querySelector('.stab__name').textContent = c.name;
        t.querySelector('.stab__tag').textContent = c.tag || '';
      });
      rebuild(panels, '[data-spanel]', list, (p, c) => {
        p.id = `panel-${c.id}`; p.setAttribute('aria-labelledby', `tab-${c.id}`); p.dataset.spanel = c.id;
        const img = p.querySelector('.spanel__media img'), ph = p.querySelector('.spanel__ph');
        if (img) { img.hidden = !c.image; if (c.image) { img.src = c.image; img.alt = `Профиль ${brand} ${c.name} в разрезе`; } else img.removeAttribute('src'); }
        if (ph) { ph.hidden = !!c.image; ph.querySelector('b').textContent = c.name; }
        p.querySelector('.spanel__tag').textContent = c.tag || '';
        p.querySelector('h4').textContent = `${brand} ${c.name}`;
        p.querySelector('.spanel__body > p').textContent = c.text || '';
        const ul = p.querySelector('.spanel__specs');
        if (ul) rebuild(ul, 'li', lines(c.specs), (li, t) => lastText(li, t));
      });
      g.dispatchEvent(new Event('systems:refresh'));
    });

    // страницы брендов: виды из одной карточки (количество фиксировано)
    document.querySelectorAll('[data-plain]').forEach((art) => {
      const [bid, tid] = art.dataset.plain.split(':');
      const c = ((d[bid] && d[bid].plain) || []).find((x) => x.id === tid);
      if (!c) return;
      const img = art.querySelector('.spanel__media img');
      if (img && c.image) { img.removeAttribute('srcset'); img.src = c.image; }
      const p = art.querySelector('.spanel__body > p');
      if (p && c.text) p.textContent = c.text;
      const ul = art.querySelector('.spanel__specs');
      const sp = lines(c.specs);
      if (ul && sp.length) rebuild(ul, 'li', sp, (li, t) => lastText(li, t));
    });

    // раздел: характеристики
    document.querySelectorAll('[data-ed-specs]').forEach((ul) => {
      const specs = get(d, ul.dataset.edSpecs);
      if (!Array.isArray(specs)) return;
      rebuild(ul, 'li', specs, (li, s, i) => {
        li.className = `fcard fcard--t${(i % 5) + 1}`;
        li.querySelector('.fcard__tag').textContent = s.param;
        li.querySelector('.fcard__body strong').textContent = s.value;
      });
    });

    // раздел: вопросы и ответы
    document.querySelectorAll('[data-ed-faq]').forEach((box) => {
      const items = get(d, box.dataset.edFaq);
      if (!Array.isArray(items)) return;
      const section = box.closest('section');
      if (section) section.hidden = items.length === 0;
      rebuild(box, 'details', items, (n, f) => {
        n.open = false;
        n.classList.remove('is-open');
        n.removeAttribute('style');
        n.querySelector('summary').textContent = f.q;
        n.querySelector('p').textContent = f.a;
      });
    });

    // техническая информация: списки PDF
    document.querySelectorAll('[data-ed-files]').forEach((ul) => {
      const files = get(d, ul.dataset.edFiles);
      if (!Array.isArray(files)) return;
      rebuild(ul, '.file', files, (n, f) => {
        n.querySelector('.file__name b').textContent = f.title;
        n.querySelector('.file__name small').textContent = `PDF${f.size ? ' · ' + f.size : ''}`;
        n.querySelectorAll('a').forEach((a) => a.setAttribute('href', f.pdf));
      });
    });

    // техническая информация: карточки профилей VEKA
    document.querySelectorAll('[data-ed-profile]').forEach((li) => {
      const p = d.tech && d.tech.veka && d.tech.veka.profiles && d.tech.veka.profiles[Number(li.dataset.edProfile)];
      if (!p) return;
      li.querySelector('.vcard__tag').textContent = p.tag;
      setImg(li.querySelector('.vcard__img img'), p.image, `Профиль VEKA ${p.name} в разрезе`);
      li.querySelector('h3').textContent = p.name;
      li.querySelector(':scope > p').textContent = p.text;
      const specs = String(p.specs || '').split('\n').map((x) => x.trim()).filter(Boolean);
      const ul = li.querySelector('.vcard__specs');
      if (ul) rebuild(ul, 'li', specs, (s, t) => lastText(s, t));
    });
  });
}

// карточки с несколькими фото (из разметки сборки)
initGalleries();
