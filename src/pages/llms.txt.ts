import { site, siteUrl } from '../config/site.config.mjs';
import { brandList } from '../config/brands.mjs';
import { typesOf, brandView } from '../config/brands.mjs';
import { works } from '../config/works.mjs';

// llms.txt: краткое описание сайта для ИИ-поиска (ChatGPT Search, Perplexity, Алиса/YandexGPT, Gemini).
// Собирается из тех же данных, что и сайт: после правок в админке и пересборки текст обновляется.
export const GET = () => {
  const brands = brandList.map((b0) => {
    const b = brandView(b0);
    const types = typesOf(b).map((t) => (t.systems?.length ? `${t.title}: ${t.systems.map((s) => s.name).join(', ')}` : t.title));
    return `- [${b.h1}](${siteUrl}${b.path}): ${b.lead}\n  Что делаем: ${types.join('; ')}.`;
  });
  const lines = [
    `# ${site.name}`,
    '',
    `> ${site.tagline}. Компания «${site.name}» (Москва): алюминиевые окна, двери и фасады Schuco и Alutech, пластиковые окна ПВХ VEKA, остекление балконов и лоджий, зимние сады. Бесплатный замер, изготовление, монтаж, гарантия.`,
    '',
    '## Контакты',
    `- Телефон: ${site.phone.display}`,
    `- E-mail: ${site.email}`,
    `- Адрес: ${site.address.full}`,
    `- Режим работы: ${site.hours}`,
    '- Район работы: Москва и Московская область',
    '',
    '## Основные страницы',
    `- [Главная](${siteUrl}/): профили, услуги, примеры работ, заявка на бесплатный замер`,
    ...brands,
    `- [Окна ПВХ](${siteUrl}/okna/pvh/): пластиковые окна, двери и балконы из профиля VEKA`,
    `- [Наши работы](${siteUrl}/nashi-raboty/): ${works.length} выполненных объектов (квартиры, коттеджи, коммерческие здания)`,
    `- [Технические каталоги](${siteUrl}/tehnicheskaya-informaciya/): параметры профилей Alutech, Schuco и VEKA, PDF-каталоги`,
    `- [О компании](${siteUrl}/o-kompanii/)`,
    `- [Контакты](${siteUrl}/kontakty/)`,
    '',
    '## Как заказать',
    '1. Оставить заявку на сайте или позвонить.',
    '2. Бесплатный замер специалистом на объекте.',
    '3. Расчёт, договор, изготовление и монтаж.',
    '',
    '## Важно',
    '- Цены на сайте не публикуются, потому что часто меняются: стоимость считается индивидуально после замера.',
    '',
  ];
  return new Response(lines.join('\n'), { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
