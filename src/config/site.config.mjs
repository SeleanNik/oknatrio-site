// Єдине місце з налаштуваннями сайту. Домен ніде більше не зашивати.
// Контакты (телефон, e-mail, адрес, график, мессенджеры) живут в public/data/site.json и редактируются из админки /admin/.
import contacts from '../../public/data/site.json' with { type: 'json' };

const read = (k, d = '') => import.meta.env?.[k] ?? globalThis.process?.env?.[k] ?? d;

export const siteUrl = read('PUBLIC_SITE_URL', 'https://oknatrio.ru').replace(/\/$/, '');

export const site = {
  name: 'Трио',
  legalName: '', // TODO: юридическое название (ООО «…») — пока не выводится
  requisites: '', // TODO: «ИНН 0000000000 · ОГРН 0000000000000» — пока не выводится
  tagline: 'Алюминиевые окна и окна ПВХ в Москве',
  url: siteUrl,
  noindex: read('PUBLIC_NOINDEX', 'true') === 'true',
  ymId: read('PUBLIC_YM_ID', ''), // Яндекс.Метрика: пусто = счётчик не подключается
  phone: contacts.phone,
  email: contacts.email,
  address: { region: 'Москва', country: 'RU', ...contacts.address },
  geo: { lat: 55.6485, lng: 37.3936 }, // TODO: проверить координаты
  hours: contacts.hours,
  // Технические каталоги (PDF в public/ или внешняя ссылка). Пусто = кнопка открывает форму «запросить каталог».
  catalogs: {
    alu: '', // TODO: например '/catalog-aluminium.pdf'
    pvc: '', // TODO: например '/catalog-pvc.pdf'
  },
  messengers: { whatsapp: contacts.whatsapp, telegram: contacts.telegram },
  // Адрес получателя заявок задаётся в public/form-config.php (на сервере)
};
