// Админка: компактные списки с окном редактирования, галереи фото, права, генератор пароля.
(() => {
  const flash = document.querySelector('.flash--ok');
  if (flash) setTimeout(() => { flash.style.transition = 'opacity .5s'; flash.style.opacity = '0'; }, 5000);

  // ---------- права доступа и роль ----------
  const perms = [...document.querySelectorAll('[data-perm]')];
  if (perms.length) {
    const presets = { all: () => true, none: () => false, pages: (k) => k.startsWith('page.'), analytics: (k) => k === 'analytics.view' };
    document.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => {
      perms.forEach((c) => { c.checked = presets[b.dataset.preset](c.dataset.perm); });
    }));
    const roles = [...document.querySelectorAll('[data-role]')];
    const body = document.querySelector('[data-permbody]');
    const note = document.querySelector('[data-ownernote]');
    const sync = () => {
      const owner = roles.some((r) => r.checked && r.value === 'owner');
      if (body) body.classList.toggle('is-dim', owner);
      if (note) note.hidden = !owner;
    };
    roles.forEach((r) => r.addEventListener('change', sync));
    sync();
  }

  // ---------- генератор пароля ----------
  document.querySelector('[data-genpw]')?.addEventListener('click', () => {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const buf = new Uint32Array(14);
    crypto.getRandomValues(buf);
    document.getElementById('pw').value = [...buf].map((n) => chars[n % chars.length]).join('');
  });

  const form = document.querySelector('form.pageform');
  if (!form) return;

  // ---------- «есть несохранённые изменения» ----------
  const msg = form.querySelector('[data-savemsg]');
  const msgDefault = msg ? msg.textContent : '';
  let dirty = false;
  const markDirty = () => {
    if (dirty) return;
    dirty = true;
    if (msg) { msg.textContent = 'Есть несохранённые изменения'; msg.classList.add('is-dirty'); }
  };
  form.addEventListener('input', markDirty);
  form.addEventListener('change', markDirty);
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---------- перетаскивание мышкой (и долгим нажатием на телефоне) ----------
  let justDragged = false;
  const makeSortable = (container, itemSel, vertical) => {
    if (container.dataset.sortable) return;
    container.dataset.sortable = '1';
    let drag = null;

    const flip = (apply) => { // плавное перестроение: запоминаем позиции до и после
      const items = [...container.querySelectorAll(`:scope > ${itemSel}`)];
      const before = new Map(items.map((el) => [el, el.getBoundingClientRect()]));
      apply();
      items.forEach((el) => {
        const a = before.get(el); const b = el.getBoundingClientRect();
        const dx = a.left - b.left; const dy = a.top - b.top;
        if (!dx && !dy) return;
        el.animate([{ transform: `translate(${dx}px, ${dy}px)` }, { transform: 'none' }], { duration: 180, easing: 'ease-out' });
      });
    };

    const start = () => {
      if (!drag || drag.started) return;
      drag.started = true;
      const r = drag.item.getBoundingClientRect();
      drag.dx = drag.x0 - r.left; drag.dy = drag.y0 - r.top;
      const g = drag.item.cloneNode(true);
      g.querySelectorAll('dialog').forEach((d) => d.remove());
      g.classList.add('drag-ghost');
      g.style.cssText = `position:fixed;left:0;top:0;width:${r.width}px;height:${r.height}px;z-index:9999;pointer-events:none;margin:0;`;
      document.body.append(g);
      drag.ghost = g;
      drag.item.classList.add('is-drag');
      document.body.classList.add('is-sorting');
      drag.px = drag.x0; drag.py = drag.y0;
      move(drag.x0, drag.y0);
      // прокрутка страницы, когда карточку подносят к верхнему или нижнему краю экрана
      const tick = () => {
        if (!drag || !drag.started) return;
        const edge = 90;
        const step = drag.py < edge ? -Math.ceil((edge - drag.py) / 6) : drag.py > innerHeight - edge ? Math.ceil((drag.py - (innerHeight - edge)) / 6) : 0;
        if (step) { scrollBy(0, step); move(drag.px, drag.py); }
        drag.raf = requestAnimationFrame(tick);
      };
      drag.raf = requestAnimationFrame(tick);
    };
    const move = (x, y) => {
      drag.ghost.style.transform = `translate(${x - drag.dx}px, ${y - drag.dy}px) rotate(1.5deg)`;
      const under = document.elementsFromPoint(x, y).find((el) => el !== drag.item && el.matches?.(itemSel) && el.parentElement === container);
      if (!under) return;
      const r = under.getBoundingClientRect();
      const after = vertical ? y > r.top + r.height / 2 : (y > r.bottom ? true : y < r.top ? false : x > r.left + r.width / 2);
      const next = after ? under.nextElementSibling : under;
      if (next === drag.item || (after && under.nextElementSibling === drag.item)) return;
      flip(() => (after ? under.after(drag.item) : under.before(drag.item)));
    };
    const end = () => {
      if (!drag) return;
      clearTimeout(drag.timer);
      cancelAnimationFrame(drag.raf);
      if (drag.started) {
        drag.ghost.remove();
        drag.item.classList.remove('is-drag');
        document.body.classList.remove('is-sorting');
        justDragged = true; setTimeout(() => { justDragged = false; }, 60);
        markDirty();
        container.dispatchEvent(new Event('sorted', { bubbles: true }));
      }
      drag = null;
    };

    container.addEventListener('pointerdown', (e) => {
      if (e.button !== 0 || drag) return;
      const item = e.target.closest(itemSel);
      if (!item || item.parentElement !== container) return;
      if (e.target.closest('[data-del], [data-gdel], [data-up], [data-down], [data-gl], [data-gr], input, textarea, select')) return;
      const inDlg = e.target.closest('dialog'); // окно редактирования внутри плитки не должно запускать перетаскивание самой плитки
      if (inDlg && !inDlg.contains(container)) return;
      drag = { item, x0: e.clientX, y0: e.clientY, started: false, touch: e.pointerType === 'touch' };
      if (drag.touch) drag.timer = setTimeout(start, 380);
    });
    window.addEventListener('pointermove', (e) => {
      if (!drag) return;
      const dist = Math.hypot(e.clientX - drag.x0, e.clientY - drag.y0);
      if (!drag.started) {
        if (drag.touch ? dist > 10 : dist > 6) { if (drag.touch) { clearTimeout(drag.timer); drag = null; } else start(); }
        return;
      }
      drag.px = e.clientX; drag.py = e.clientY;
      move(e.clientX, e.clientY);
    });
    window.addEventListener('pointerup', end);
    window.addEventListener('pointercancel', end);
    // пока тащим пальцем, страница не должна прокручиваться
    window.addEventListener('touchmove', (e) => { if (drag && drag.started) e.preventDefault(); }, { passive: false });
    // клик, которым закончилось перетаскивание, не должен открывать окно
    container.addEventListener('click', (e) => { if (justDragged) { e.stopPropagation(); e.preventDefault(); } }, true);
  };

  // ---------- галерея фото внутри карточки ----------
  const bindGallery = (gal) => {
    if (gal.dataset.bound) return;
    gal.dataset.bound = '1';
    const grid = gal.querySelector('.gal__grid');
    makeSortable(grid, '[data-slot]', false);
    const tpl = gal.querySelector('template');
    const max = Number(gal.dataset.max || 8);
    const count = () => grid.querySelectorAll('[data-slot]').length;
    const addSlot = (url, file) => {
      if (count() >= max) { alert(`Можно добавить не больше ${max} фото.`); return false; }
      grid.append(tpl.content.cloneNode(true));
      const slot = grid.lastElementChild;
      const img = slot.querySelector('[data-prev]');
      if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        slot.querySelector('[data-g-file]').files = dt.files;
        img.src = URL.createObjectURL(file);
      } else {
        slot.querySelector('[data-g-url]').value = url;
        img.src = url;
      }
      markDirty();
      gal.dispatchEvent(new Event('gal:change', { bubbles: true }));
      return true;
    };
    gal.querySelector('[data-gadd]')?.addEventListener('change', (e) => {
      for (const f of e.target.files) if (!addSlot('', f)) break;
      e.target.value = '';
    });
    const urlInput = gal.querySelector('[data-gurl]');
    const addUrl = () => {
      const v = urlInput.value.trim();
      if (!v) return;
      if (!/^https:\/\//.test(v)) { alert('Ссылка должна начинаться с https://'); return; }
      if (addSlot(v)) urlInput.value = '';
    };
    gal.querySelector('[data-gurl-add]')?.addEventListener('click', addUrl);
    urlInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addUrl(); } });
    grid.addEventListener('click', (e) => {
      const slot = e.target.closest('[data-slot]');
      if (!slot) return;
      if (e.target.closest('[data-gdel]')) slot.remove();
      else if (e.target.closest('[data-gl]') && slot.previousElementSibling) slot.previousElementSibling.before(slot);
      else if (e.target.closest('[data-gr]') && slot.nextElementSibling) slot.nextElementSibling.after(slot);
      else return;
      markDirty();
      gal.dispatchEvent(new Event('gal:change', { bubbles: true }));
    });
  };

  // ---------- одиночные поля «фото / PDF» ----------
  const bindSingle = (scope) => {
    scope.querySelectorAll('[data-file]').forEach((input) => {
      if (input.dataset.bound) return;
      input.dataset.bound = '1';
      input.addEventListener('change', () => {
        const f = input.files && input.files[0];
        if (!f) return;
        const box = input.closest('.imgf');
        box?.classList.add('is-new');
        const url = box?.querySelector('[data-url]');
        if (url) url.placeholder = 'Будет загружен: ' + f.name;
        const img = box?.querySelector('[data-prev]');
        if (img && f.type.startsWith('image/')) { img.src = URL.createObjectURL(f); img.hidden = false; }
        scope.dispatchEvent(new Event('gal:change', { bubbles: true }));
      });
    });
    scope.querySelectorAll('[data-url]').forEach((url) => url.dataset.bound ? null : (url.dataset.bound = '1', url.addEventListener('input', () => {
      const img = url.closest('.imgf')?.querySelector('[data-prev]');
      if (img) { img.src = url.value; img.hidden = !url.value; }
    })));
    scope.querySelectorAll('[data-gal]').forEach(bindGallery);
  };

  // ---------- списки: плитки + окно редактирования ----------
  const refreshTile = (row, list) => {
    const val = (key) => row.querySelector(`[data-f="${key}"]`)?.value?.trim() || '';
    const t = row.querySelector('[data-t]');
    if (t) t.textContent = val(list.dataset.rowKey) || 'Новый элемент';
    const dt = row.querySelector('[data-dtitle]');
    if (dt) dt.textContent = val(list.dataset.rowKey) ? ': ' + val(list.dataset.rowKey) : '';
    const s = row.querySelector('[data-s]');
    const subKey = list.dataset.subKey;
    if (s && subKey) { const v = val(subKey); s.textContent = v.length > 90 ? v.slice(0, 90) + '…' : v; }
    const thumbKey = list.dataset.thumbKey;
    if (thumbKey) {
      const img = row.querySelector('[data-thumb]');
      const noimg = row.querySelector('.item__noimg');
      const cnt = row.querySelector('[data-count]');
      let src = '';
      let n = 0;
      const gal = row.querySelector(`[data-gal][data-f="${thumbKey}"]`);
      if (gal) {
        const slots = [...gal.querySelectorAll('[data-slot]')];
        n = slots.length;
        src = slots[0]?.querySelector('[data-prev]')?.getAttribute('src') || '';
      } else {
        const single = row.querySelector(`[data-url][data-f="${thumbKey}"]`);
        const prev = single?.closest('.imgf')?.querySelector('[data-prev]');
        src = prev && !prev.hidden ? prev.getAttribute('src') : (single?.value || '');
      }
      if (img) { img.hidden = !src; if (src) img.src = src; }
      if (noimg) noimg.hidden = !!src;
      if (cnt) { cnt.hidden = n < 2; cnt.textContent = `${n} фото`; }
    }
  };

  document.querySelectorAll('[data-list]').forEach((list) => {
    const rows = list.querySelector('.list__rows');
    const tpl = list.querySelector(':scope > template');
    const max = Number(list.dataset.max || 100);
    const counter = list.querySelector('[data-listcount]');
    const recount = () => { if (counter) counter.textContent = rows.querySelectorAll('[data-row]').length; };

    const bindRow = (row) => {
      bindSingle(row);
      const dlg = row.querySelector('dialog');
      const update = () => refreshTile(row, list);
      row.addEventListener('input', update);
      row.addEventListener('change', update);
      row.addEventListener('gal:change', update);
      row.querySelector('[data-edit]').addEventListener('click', () => dlg.showModal());
      dlg.addEventListener('click', (e) => { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
      dlg.addEventListener('close', update);
      update();
    };
    rows.querySelectorAll('[data-row]').forEach(bindRow);
    if (!list.hasAttribute('data-fixed')) makeSortable(rows, '.item', list.classList.contains('list--rows'));

    list.querySelector('[data-add]')?.addEventListener('click', () => {
      if (rows.children.length >= max) { alert(`Можно создать не больше ${max}.`); return; }
      rows.append(tpl.content.cloneNode(true));
      const row = rows.lastElementChild;
      bindRow(row);
      recount();
      markDirty();
      row.querySelector('dialog').showModal();
      row.querySelector('dialog input:not([type=hidden]):not([type=file]), dialog textarea')?.focus();
    });

    rows.addEventListener('click', (e) => {
      const row = e.target.closest('[data-row]');
      if (!row || e.target.closest('dialog')) return;
      if (e.target.closest('[data-del]')) {
        if (confirm('Удалить этот элемент? Изменение применится после нажатия «Сохранить».')) { row.remove(); recount(); markDirty(); }
      } else if (e.target.closest('[data-up]') && row.previousElementSibling) {
        row.previousElementSibling.before(row); markDirty();
      } else if (e.target.closest('[data-down]') && row.nextElementSibling) {
        row.nextElementSibling.after(row); markDirty();
      }
    });
  });

  // обычные одиночные поля вне списков
  bindSingle(form.querySelector('.panel__body') ? form : document);

  // ---------- перед отправкой нумеруем строки и фото в порядке на экране ----------
  form.addEventListener('submit', () => {
    form.querySelectorAll('[data-list]').forEach((list) => {
      const base = list.dataset.name;
      [...list.querySelectorAll('[data-row]')].forEach((row, i) => {
        row.querySelectorAll('[data-f]').forEach((el) => {
          if (el.closest('[data-gal]')) return;
          el.name = `${base}[${i}][${el.dataset.f}]${el.hasAttribute('data-arr') ? '[]' : ''}`;
        });
        row.querySelectorAll('[data-gal]').forEach((gal) => {
          const f = gal.dataset.f;
          [...gal.querySelectorAll('[data-slot]')].forEach((slot, j) => {
            slot.querySelector('[data-g-url]').name = `${base}[${i}][${f}][${j}]`;
            slot.querySelector('[data-g-file]').name = `${base}[${i}][${f}__file][${j}]`;
          });
        });
      });
    });
  });
})();
