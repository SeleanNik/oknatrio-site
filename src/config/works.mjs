// TODO: реальные объекты (фото, район, тип, система) — от клиента. Сейчас — демо с фото со стоков.
// tags: apartment | cottage | balcony | aluminum | pvc
import { pagesData } from './pages.mjs';

// Список работ редактируется в админке (public/data/pages.json). images — список фото (полные URL или /uploads/...): на сайте несколько фото листаются.
export const works = (pagesData.works?.items || []).map((w) => ({ id: w.id, images: w.images || [], image: (w.images || [])[0], title: w.title, place: w.place, tags: w.tags || [] }));
// какие работы показывать на странице: их отмечают в админке внутри раздела страницы (поле worksPick); без отметок — прежнее правило
export const worksFor = (place, fallback = () => []) => {
  const picks = pagesData[place]?.worksPick;
  const list = Array.isArray(picks) ? picks.map((id) => works.find((w) => w.id === id)).filter(Boolean) : fallback();
  return list.slice(0, place === 'home' ? 3 : 4);
};

export const workFilters = [
  { key: 'all', label: 'Все' },
  { key: 'cottage', label: 'Коттеджи' },
  { key: 'aluminum', label: 'Алюминий' },
  { key: 'pvc', label: 'ПВХ' },
];
