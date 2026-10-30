// Меню, модалки, отправка форм. Без зависимостей.

// ---- Мобильное меню ----
const burger = document.querySelector('[data-burger]');
const mmenu = document.querySelector('[data-mmenu]');
if (burger && mmenu) {
  const setOpen = (open) => {
    burger.setAttribute('aria-expanded', String(open));
    burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    mmenu.hidden = !open;
    document.body.style.overflow = open ? 'hidden' : '';
  };
  burger.addEventListener('click', () => setOpen(mmenu.hidden));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !mmenu.hidden) { setOpen(false); burger.focus(); } });
  mmenu.addEventListener('click', (e) => { if (e.target.closest('a')) setOpen(false); });
}

// ---- Ссылки на форму заявки: если форма есть на этой странице, просто прокручиваем ----
document.addEventListener('click', (e) => {
  const a = e.target.closest('a[href="/#zayavka"]');
  const target = document.getElementById('zayavka');
  if (!a || !target) return;
  e.preventDefault();
  if (mmenu && !mmenu.hidden) burger.click();
  target.scrollIntoView({ behavior: 'smooth' });
  target.querySelector('input:not([type=hidden]):not([tabindex="-1"])')?.focus({ preventScroll: true });
});

// ---- Модалки ----
document.addEventListener('click', (e) => {
  const opener = e.target.closest('[data-modal]');
  if (opener) {
    const dlg = document.getElementById(`modal-${opener.dataset.modal}`);
    if (dlg && !dlg.open) {
      if (mmenu && !mmenu.hidden) burger.click();
      dlg.showModal();
      dlg.querySelector('input:not([type=hidden]):not([tabindex="-1"])')?.focus();
    }
    return;
  }
  const dlg = e.target.closest('[data-modal-dialog]');
  if (e.target.closest('[data-modal-close]')) dlg?.close();
  else if (e.target.matches?.('[data-modal-dialog]')) e.target.close(); // клик по подложке
});

// ---- Формы ----
const goal = (name) => { try { window.ym && window.ym(window.__YM_ID__, 'reachGoal', name); } catch {} };
const phoneOk = (v) => v.replace(/\D/g, '').length >= 10;

document.querySelectorAll('[data-lead-form]').forEach((form) => {
  const stamp = () => { form.querySelector('[data-ts]').value = String(Math.floor(Date.now() / 1000)); };
  form.querySelector('[data-page]').value = location.pathname;
  stamp();

  const setErr = (name, msg) => {
    const el = form.querySelector(`[data-error-for="${name}"]`);
    const input = form.elements[name];
    if (el) el.textContent = msg || '';
    if (input && input.type !== 'checkbox') input.setAttribute('aria-invalid', msg ? 'true' : 'false');
  };
  // Файлы: до 3 шт., по 10 МБ; список с удалением (через DataTransfer)
  const fileInput = form.querySelector('[data-files]');
  const fileList = form.querySelector('[data-file-list]');
  const MAX_FILES = 3, MAX_SIZE = 10 * 1024 * 1024;
  let picked = [];
  const syncFiles = () => {
    const dt = new DataTransfer();
    picked.forEach((f) => dt.items.add(f));
    fileInput.files = dt.files;
    fileList.innerHTML = '';
    const n = picked.length;
    form.querySelector('[data-upload-label]').textContent = n === 0 ? 'Прикрепить файл' : n < MAX_FILES ? 'Добавить ещё файл' : 'Файлы выбраны';
    form.querySelector('[data-upload-hint]').textContent = n === 0 ? 'До 3 файлов, по 10 МБ' : `Прикреплено ${n} из ${MAX_FILES}`;
    picked.forEach((f, i) => {
      const li = document.createElement('li');
      const mk = (cls, text) => { const el = document.createElement('span'); el.className = cls; el.textContent = text; return el; };
      const ext = mk('ext', (f.name.split('.').pop() || 'file').slice(0, 4));
      const name = mk('nm', f.name); name.title = f.name;
      const size = mk('sz', `${(f.size / 1048576).toFixed(1)} МБ`);
      const rm = document.createElement('button');
      rm.type = 'button'; rm.setAttribute('aria-label', `Убрать ${f.name}`); rm.textContent = '×';
      rm.addEventListener('click', () => { picked.splice(i, 1); syncFiles(); });
      li.append(ext, name, size, rm); fileList.append(li);
    });
  };
  fileInput?.addEventListener('change', () => {
    let msg = '';
    for (const f of [...fileInput.files]) {
      if (f.size > MAX_SIZE) { msg = `Файл «${f.name}» больше 10 МБ`; continue; }
      if (picked.length >= MAX_FILES) { msg = 'Можно прикрепить не больше 3 файлов'; break; }
      picked.push(f);
    }
    setErr('files', msg); syncFiles();
  });

  const status = form.querySelector('[data-status]');
  const show = (state, text) => { status.dataset.state = state; status.textContent = text; };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    let bad = false;
    if (!form.elements.name.value.trim()) { setErr('name', 'Укажите, как к вам обращаться'); bad = true; } else setErr('name');
    if (!phoneOk(form.elements.phone.value)) { setErr('phone', 'Введите телефон полностью'); bad = true; } else setErr('phone');
    const em = form.elements.email;
    if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(em.value.trim())) { setErr('email', em.value.trim() ? 'Проверьте адрес почты' : 'Укажите вашу почту'); bad = true; } else if (em) setErr('email');
    if (!form.elements.consent.checked) { setErr('consent', 'Нужно согласие на обработку данных'); bad = true; } else setErr('consent');
    if (bad) { form.querySelector('[aria-invalid="true"]')?.focus(); return; }

    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true; show('', '');
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || 'fail');
      show('ok', 'Спасибо! Заявка отправлена, мы свяжемся с вами в ближайшее время.');
      goal(`lead_${form.elements.form_type.value}`);
      form.reset(); picked = []; if (fileInput) syncFiles(); form.querySelector('[data-page]').value = location.pathname; stamp();
      document.dispatchEvent(new CustomEvent('lead:sent')); // калькулятор пересчитает скрытое поле
    } catch {
      show('err', 'Не удалось отправить заявку. Позвоните нам или попробуйте ещё раз.');
    } finally { btn.disabled = false; }
  });
});

// ---- Появление блоков при прокрутке (reveal.js; после обновления данных из админки вызывается снова) ----
import { reveal } from './reveal.js';
reveal();
