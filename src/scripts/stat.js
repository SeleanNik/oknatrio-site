// Лёгкая собственная статистика: просмотр страницы и отправка заявки. Без cookie, без персональных данных.
// Уважает «Не отслеживать» в браузере; в режиме разработки (порт 4321) не отправляется.
if (navigator.doNotTrack !== '1' && location.port !== '4321') {
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
