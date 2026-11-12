// Подтягивает актуальные тексты и работы с живого сайта (то, что вы меняли в админке) в public/data перед сборкой.
// Так в HTML попадают свежие данные, а скачивать папку data/ через FTP не нужно.
// Запуск: npm run build:live  (или отдельно: npm run sync-data)
import { writeFile, readFile, mkdir } from 'node:fs/promises';

const BASES = ['https://oknatrio.ru', 'http://oknatrio.ru']; // пока SSL не работает, откроется http
const FILES = ['pages.json', 'site.json', 'reviews.json'];
await mkdir('public/data', { recursive: true });

async function fetchJson(name) {
  for (const base of BASES) {
    try {
      const r = await fetch(`${base}/data/${name}?t=${Date.now()}`, { headers: { 'Cache-Control': 'no-cache' }, signal: AbortSignal.timeout(20000) });
      if (!r.ok) continue;
      const text = (await r.text()).replace(/^\uFEFF/, '');
      return { json: JSON.parse(text), text, base };
    } catch { /* пробуем следующий адрес */ }
  }
  return null;
}

let failed = 0;
for (const name of FILES) {
  const got = await fetchJson(name);
  if (!got) { console.warn(`! ${name}: не удалось скачать, оставляю локальный файл`); failed++; continue; }
  const path = `public/data/${name}`;
  const old = await readFile(path, 'utf8').catch(() => '');
  await writeFile(path, JSON.stringify(got.json, null, 2) + '\n', 'utf8');
  const same = old.replace(/\s+/g, '') === JSON.stringify(got.json).replace(/\s+/g, '');
  console.log(`✓ ${name}: ${same ? 'без изменений' : 'обновлён с сайта'} (${got.base})`);
}
if (failed === FILES.length) { console.error('Сайт недоступен, данные не обновлены.'); process.exit(1); }
