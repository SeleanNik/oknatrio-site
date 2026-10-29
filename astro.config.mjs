import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import { loadEnv } from 'vite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// .env не попадає в process.env під час читання конфігу — підтягуємо вручну
Object.assign(process.env, { ...loadEnv('', process.cwd(), 'PUBLIC_'), ...process.env });
const { siteUrl } = await import('./src/config/site.config.mjs');

// Прибирає HTML-коментарі (зокрема <!-- TODO ... --> з Markdown) із зібраних сторінок
const stripComments = () => ({
  name: 'strip-html-comments',
  hooks: {
    'astro:build:done': ({ dir }) => {
      const walk = (d) => fs.readdirSync(d, { withFileTypes: true }).forEach((e) => {
        const p = path.join(d, e.name);
        if (e.isDirectory()) return walk(p);
        if (!p.endsWith('.html')) return;
        const html = fs.readFileSync(p, 'utf8');
        const clean = html.replace(/<!--(?!\[if)[\s\S]*?-->/g, '');
        if (clean !== html) fs.writeFileSync(p, clean);
      });
      walk(fileURLToPath(dir));
    },
  },
});

export default defineConfig({
  site: siteUrl,
  trailingSlash: 'always',
  build: { format: 'directory' },
  integrations: [sitemap({ filter: (p) => !p.includes('/ui-kit/') }), stripComments()],
});
