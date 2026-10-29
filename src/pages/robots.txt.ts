import { site, siteUrl } from '../config/site.config.mjs';

export const GET = () => {
  const body = site.noindex
    ? 'User-agent: *\nDisallow: /\n'
    : `User-agent: *\nAllow: /\nDisallow: /form-handler.php\nDisallow: /ui-kit/\n\nSitemap: ${siteUrl}/sitemap-index.xml\n`;
  return new Response(body, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
