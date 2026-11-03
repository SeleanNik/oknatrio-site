import { getCollection } from 'astro:content';
import { siteUrl } from '../config/site.config.mjs';
import { razdely } from '../config/razdely.mjs';
import { brandList } from '../config/brands.mjs';

// sitemap.xml: все индексируемые страницы. Служебные (404, ui-kit, noindex) не попадают.
const staticPaths = ['/', '/nashi-raboty/', '/o-kompanii/', '/tehnicheskaya-informaciya/', '/kontakty/'];

export const GET = async () => {
  const entries = (await getCollection('pages')).filter((e) => !e.data.noindex);
  const paths = [
    ...staticPaths,
    ...Object.values(razdely).map((r) => r.path),
    ...brandList.map((b) => b.path),
    ...entries.map((e) => `/${e.id}/`),
  ];
  const lastmod = new Date().toISOString().slice(0, 10);
  const urls = [...new Set(paths)]
    .map((p) => `  <url><loc>${siteUrl}${p}</loc><lastmod>${lastmod}</lastmod></url>`)
    .join('\n');
  const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`;
  return new Response(xml, { headers: { 'Content-Type': 'application/xml; charset=utf-8' } });
};
