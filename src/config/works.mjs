// TODO: реальные объекты (фото, район, тип, система) — от клиента. Сейчас — демо с фото со стоков.
// tags: apartment | cottage | balcony | aluminum | pvc
import { pagesData } from './pages.mjs';

// Список работ редактируется в админке (public/data/pages.json). images — список фото (полные URL или /uploads/...): на сайте несколько фото листаются.
export const works = (pagesData.works?.items || []).map((w) => ({ images: w.images || [], image: (w.images || [])[0], title: w.title, place: w.place, tags: w.tags || [] }));

export const workFilters = [
  { key: 'all', label: 'Все' },
  { key: 'apartment', label: 'Квартиры' },
  { key: 'cottage', label: 'Коттеджи' },
  { key: 'balcony', label: 'Балконы' },
  { key: 'aluminum', label: 'Алюминий' },
  { key: 'pvc', label: 'ПВХ' },
];
