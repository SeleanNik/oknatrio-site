import sharp from 'sharp'; import fs from 'fs'; import path from 'path';
const root = 'public/img/works';
const dirs = fs.readdirSync(root).filter((d) => !/^[\x00-\x7f]+$/.test(d));
const find = (start) => { const d = dirs.find((x) => x.startsWith(start)); if (!d) throw new Error(start); return d; };
const AL = ['cottage', 'aluminum'];
const plan = [
  ['Колычево зимний сад', [{ slug: 'kolychevo-zimnij-sad', title: 'Зимний сад, Колычево', place: 'Колычево · Alutech', pick: null }]],
  ['Коттедж в поселке Мещерском', [{ slug: 'meshcherskoe-kottedzh', title: 'Коттедж, Мещерское полесье', place: 'Мещерское полесье · Schuco, Alutech', pick: null }]],
  ['коттедж г.Красногорск', [{ slug: 'krasnogorsk-kottedzh', title: 'Коттедж, Красногорск', place: 'Красногорск · алюминий', pick: null }]],
  ['коттедж деревня Колычево', [
    { slug: 'kolychevo-kottedzh-fasad', title: 'Коттедж, Колычево', place: 'Колычево · Schuco, Alutech', pick: [3, 4, 6, 7, 8, 10] },
    { slug: 'kolychevo-kottedzh-panoramnye', title: 'Панорамные окна в коттедже', place: 'Колычево · Schuco', pick: [1, 2, 5, 9] }]],
  ['коттеджный поселок полуостров Коттедж', [
    { slug: 'poluostrov-kottedzh-fasady', title: 'Коттедж, фасады', place: 'Полуостров · Schuco', pick: [7, 14, 15, 16, 17] },
    { slug: 'poluostrov-kottedzh-okna', title: 'Панорамные окна и двери', place: 'Полуостров · Schuco', pick: [1, 2, 5, 6, 9, 10, 13] },
    { slug: 'poluostrov-kottedzh-terrasa', title: 'Терраса и зенитные фонари', place: 'Полуостров · Schuco', pick: [3, 4, 8, 11, 12] }]],
  ['коттеджный поселок полуостров Навес', [{ slug: 'poluostrov-naves', title: 'Навес, Alutech F50', place: 'Полуостров', pick: null }]],
  ['навес Мещерское полесье', [{ slug: 'meshcherskoe-naves', title: 'Навес, Мещерское полесье', place: 'Alutech F50', pick: null }]],
];
const moves = [];
const pagesPath = 'public/data/pages.json';
const data = JSON.parse(fs.readFileSync(pagesPath, 'utf8'));
for (const [start, cards] of plan) {
  const dir = find(start); const src = path.join(root, dir);
  const files = fs.readdirSync(src).filter((f) => /\.jpe?g$/i.test(f)).sort();
  for (const c of cards) {
    const sel = c.pick ? c.pick.map((n) => files[n - 1]) : files;
    const out = path.join(root, c.slug); fs.mkdirSync(out, { recursive: true });
    const images = []; let i = 1;
    for (const f of sel) {
      const n = String(i++).padStart(2, '0') + '.webp';
      await sharp(path.join(src, f)).rotate().resize({ width: 1280, height: 1280, fit: 'inside', withoutEnlargement: true }).webp({ quality: 72 }).toFile(path.join(out, n));
      images.push(`/img/works/${c.slug}/${n}`);
    }
    data.works.items.push({ title: c.title, place: c.place, tags: AL, images });
  }
  moves.push([src, cards[0].slug]);
}
// видео-папка (в карточках видео не показываем) — в архив
const vid = dirs.find((d) => d.startsWith('видео'));
if (vid) moves.push([path.join(root, vid), 'video-portaly']);
fs.writeFileSync(pagesPath, JSON.stringify(data, null, 2));
fs.writeFileSync('_moves.json', JSON.stringify(moves));
console.log(data.works.items.length);
