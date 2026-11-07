import { site, siteUrl } from '../config/site.config.mjs';

// robots.txt: на dev/staging (PUBLIC_NOINDEX=true) индексация закрыта целиком.
export const GET = () => {
  const lines = site.noindex
    ? ['User-agent: *', 'Disallow: /']
    : [
        'User-agent: *',
        'Allow: /',
        'Disallow: /admin/',
        'Disallow: /form-handler.php',
        'Disallow: /stat.php',
        // Яндекс: метки рекламы и аналитики не создают дубли страниц
        'Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&ysclid&gclid&fbclid&_openstat /',
        '',
        // Поисковые ИИ-системы и их обходчики: сайт для них открыт
        ...['YandexBot', 'YandexAdditional', 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'ClaudeBot', 'Claude-SearchBot', 'Google-Extended', 'Applebot-Extended']
          .flatMap((b) => [`User-agent: ${b}`, 'Allow: /', 'Disallow: /admin/', '']),
        `# Описание сайта для ИИ-поиска: ${siteUrl}/llms.txt`,
        `Sitemap: ${siteUrl}/sitemap.xml`,
      ];
  return new Response(lines.join('\n') + '\n', { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
