// Страница «Техническая информация»: каталоги PDF по системам и примеры угловых профилей.
// PDF лежат в public/docs/<бренд>/, картинки профилей VEKA — на хостинге (src/config/cdn.mjs).
import { cdn } from './cdn.mjs';
import { pagesData } from './pages.mjs';
// TODO: PDF очень тяжёлые (до 100 МБ) — при возможности сжать перед выкладкой на хостинг.

export const techCatalogs = [
  {
    id: 'alutech', brand: 'Alutech', title: 'Алюминиевые профили Alutech',
    text: 'Профильные системы Alutech: окна, двери, раздвижные и фасадные конструкции. Каталоги систем, сечений и комплектующих.',
    files: [
      { title: 'C48: холодная система', pdf: '/docs/alutech/c48-holodnaya.pdf', size: '64 МБ' },
      { title: 'W62 HS', pdf: '/docs/alutech/w62-hs.pdf', size: '18 МБ' },
      { title: 'W72: тёплая система', pdf: '/docs/alutech/w72-teplaya.pdf', size: '108 МБ' },
      { title: 'SL160: раздвижная система', pdf: '/docs/alutech/sl160.pdf', size: '73 МБ' }, // TODO: уточнить название (в файле «прд»)
      { title: 'F50: стоечно-ригельная система', pdf: '/docs/alutech/f50-stoyka-rigel.pdf', size: '73 МБ' },
      { title: 'GS-106', pdf: '/docs/alutech/gs-106.pdf', size: '41 МБ' }, // TODO: уточнить название (в файле «пск»)
    ],
  },
  {
    id: 'schuco', brand: 'Schuco', title: 'Алюминиевые профили Schuco',
    text: 'Профильные системы Schuco: окна, двери, подъёмно-раздвижные порталы и фасады. Каталоги систем, сечений и комплектующих.',
    files: [
      { title: 'Обзорный каталог Schuco', pdf: '/docs/schuco/obzornyj-katalog.pdf', size: '37 МБ' },
      { title: 'AWS 75 SI', pdf: '/docs/schuco/aws-75-si.pdf', size: '0,7 МБ' },
      { title: 'Ручки', pdf: '/docs/schuco/ruchki.pdf', size: '1,3 МБ' },
    ],
  },
];

// Профили VEKA (раздел «Пластик ПВХ»). TODO: проверить параметры у клиента.
export const vekaProfiles = [
  { id: 'euroline-58', name: 'Euroline 58', src: cdn.euroline58, w: 430, h: 430, tag: 'Базовый', text: 'Классическая система для квартир и дач: надёжно и доступно.', specs: ['Монтажная глубина 58 мм', '3 камеры', 'Стеклопакет до 32 мм'] },
  { id: 'softline-70', name: 'Softline 70', src: cdn.softline70, w: 500, h: 500, tag: 'Универсальный', text: 'Оконные конструкции практически любой сложности.', specs: ['Монтажная глубина 70 мм', '5 камер', 'Стеклопакет до 42 мм'] },
  { id: 'softline-82', name: 'Softline 82', src: cdn.softline82, w: 500, h: 500, tag: 'Энергосберегающий', text: 'Максимальная тепло- и шумоизоляция окна.', specs: ['Монтажная глубина 82 мм', '6 камер', 'Стеклопакет до 51 мм'] },
];

// Раздел «ПВХ профили VEKA» (третий на странице): шапка-баннер + карточки систем.
export const vekaSection = { id: 'plastik', brand: 'VEKA', title: 'ПВХ профили VEKA', text: 'Три системы для пластиковых окон: от базовой до максимально тёплой.' };

// Каталоги VEKA (файлы в public/docs/veka/)
export const vekaFiles = [
  { title: 'Обзор систем VEKA', pdf: '/docs/veka/obzor-sistem-veka.pdf', size: '9 МБ' },
  { title: 'VEKA: фасадные соединители', pdf: '/docs/veka/veka-fasadnye-soediniteli.pdf', size: '11 МБ' },
];

// Админка (public/data/pages.json) переопределяет тексты, файлы и карточки профилей.
const T = pagesData.tech;
if (T) {
  if (T.alutech) { techCatalogs[0].text = T.alutech.text; techCatalogs[0].files = T.alutech.files; }
  if (T.schuco) { techCatalogs[1].text = T.schuco.text; techCatalogs[1].files = T.schuco.files; }
  if (T.veka) {
    vekaSection.text = T.veka.text;
    vekaFiles.length = 0;
    vekaFiles.push(...(T.veka.files || []));
    (T.veka.profiles || []).forEach((e, i) => {
      const p = vekaProfiles[i];
      if (!p) return;
      Object.assign(p, { name: e.name, tag: e.tag, text: e.text, src: e.image, specs: String(e.specs || '').split('\n').map((x) => x.trim()).filter(Boolean) });
    });
  }
}
