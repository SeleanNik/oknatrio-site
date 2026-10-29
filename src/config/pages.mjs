// Контент страниц хранится в public/data/pages.json и редактируется из админки /admin/.
// Сборка читает его для SEO-снимка, а в браузере src/scripts/live-data.js подтягивает свежие данные.
import data from '../../public/data/pages.json' with { type: 'json' };
export const pagesData = data;
