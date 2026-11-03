// Навигация: единый источник для шапки, футера и мобильного меню.
// У пункта: href и groups: [{ title, href, links: [[текст, ссылка]] }] (выпадающее меню).
import { razdely, typeLinks } from './razdely.mjs';
import { brandList, typesOf } from './brands.mjs';

const group = (r) => ({ title: r.title, href: r.path, links: typeLinks(r) });

export const nav = [
  { title: 'Продукция', groups: brandList.map((b) => ({ title: (b.parent === 'pvh' ? 'ПВХ ' : 'Алюминий ') + b.name, href: b.path, links: typesOf(b).map((t) => [t.title, `${b.path}#${t.id}`]) })) },
  { title: 'Наши работы', href: '/nashi-raboty/' },
  { title: 'Техническая информация', href: '/tehnicheskaya-informaciya/' },
  { title: 'Контакты', href: '/kontakty/' },
];

export const footerExtra = [
  ['О компании', '/o-kompanii/'],
  ['Техническая информация', '/tehnicheskaya-informaciya/'],
  ['Контакты', '/kontakty/'],
  ['Политика конфиденциальности', '/politika-konfidencialnosti/'],
];
