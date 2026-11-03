import { site, siteUrl } from '../config/site.config.mjs';

// robots.txt: на dev/staging (PUBLIC_NOINDEX=true) индексация закрыта целиком.
export const GET = () => {
  const lines = site.noindex
    ? ['User-agent: *', 'Disallow: /']
    : [
        'User-agent: *',
        'Allow: /',
        'Disallow: /admin/',
        'Disallow: /uploads/',
        'Disallow: /form-handler.php',
        'Disallow: /stat.php',
        '',
        `Sitemap: ${siteUrl}/sitemap.xml`,
      ];
  return new Response(lines.join('\n') + '\n', { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
