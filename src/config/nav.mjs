// Навигация: единый источник для шапки, футера и мобильного меню.
// У пункта: href и groups: [{ title, href, links: [[текст, ссылка]] }] (выпадающее меню).
import { razdely, typeLinks } from './razdely.mjs';

const group = (r) => ({ title: r.title, href: r.path, links: typeLinks(r) });

export const nav = [
  { title: 'Продукция', groups: [group(razdely.alyuminievye), group(razdely.pvh)] },
  { title: 'Наши работы', href: '/nashi-raboty/' },
  { title: 'Техническая информация', href: '/tehnicheskaya-informaciya/' },
  { title: 'Контакты', href: '/kontakty/' },
];

export const footerExtra = [
  ['Техническая информация', '/tehnicheskaya-informaciya/'],
  ['Контакты', '/kontakty/'],
  ['Политика конфиденциальности', '/politika-konfidencialnosti/'],
];
