// Лёгкая собственная статистика: просмотр страницы и отправка заявки. Без cookie, без персональных данных.
// Уважает «Не отслеживать» в браузере; в режиме разработки (порт 4321) не отправляется.
// Свои визиты владельца не считаем: устройство отмечается при входе в админку; вручную — ссылка ?nostat=1 (снять — ?nostat=0)
let owner = false;
try {
  const q = new URLSearchParams(location.search).get('nostat');
  if (q === '1') localStorage.setItem('trio_owner', '1');
  if (q === '0') localStorage.removeItem('trio_owner');
  owner = localStorage.getItem('trio_owner') === '1';
} catch { /* без хранилища считаем как обычно */ }
if (!owner && navigator.doNotTrack !== '1' && location.port !== '4321') {
  const send = (e) => {
    const body = JSON.stringify({ p: location.pathname, r: document.referrer, e });
    try {
      if (!(navigator.sendBeacon && navigator.sendBeacon('/stat.php', new Blob([body], { type: 'text/plain' })))) {
        fetch('/stat.php', { method: 'POST', body, keepalive: true }).catch(() => {});
      }
    } catch { /* статистика не должна ломать сайт */ }
  };
  send('view');
  document.addEventListener('lead:sent', () => send('lead'));
}
