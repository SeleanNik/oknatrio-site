import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

// Информационная страница (О компании, Политика…). Файл src/content/pages/o-kompanii.md -> /o-kompanii/
// Строки с двоеточием в frontmatter брать в двойные кавычки.
// Страницы-разделы (алюминий, ПВХ, балконы, комплектующие) описаны в src/config/razdely.mjs
const info = z.object({
  title: z.string(),
  seoTitle: z.string(),
  description: z.string(),
  h1: z.string(),
  lead: z.string().optional(),
  breadcrumbs: z.array(z.object({ title: z.string(), href: z.string().optional() })).default([]),
  cta: z.boolean().default(true),         // показывать блок «Оставить заявку» внизу
  noindex: z.boolean().default(false),
});

export const collections = {
  pages: defineCollection({ loader: glob({ pattern: '**/*.md', base: './src/content/pages' }), schema: info }),
};
