// После сборки убираем из dist тяжёлые PDF-каталоги (сотни МБ): они уже лежат на сервере и не должны ехать по FTP при каждой заливке.
// Новые каталоги загружаются через админку («Тех. информация»). Чтобы оставить PDF в dist (новый сервер), запустите с KEEP_PDF=1.
import { readdir, rm, stat } from 'node:fs/promises';
import { join } from 'node:path';

if (process.env.KEEP_PDF) { console.log('KEEP_PDF: PDF оставлены в dist'); process.exit(0); }
let removed = 0, bytes = 0;
async function walk(dir) {
  for (const e of await readdir(dir, { withFileTypes: true }).catch(() => [])) {
    const p = join(dir, e.name);
    if (e.isDirectory()) await walk(p);
    else if (e.name.toLowerCase().endsWith('.pdf')) { bytes += (await stat(p)).size; await rm(p); removed++; }
  }
}
await walk('dist/docs');
console.log(`PDF убраны из dist/docs: ${removed} файлов, ${(bytes / 1048576).toFixed(0)} МБ (на сервере остаются)`);
