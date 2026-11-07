// Мини-разметка для документов, которые редактируются в админке (/admin/ → «Документы и правила»).
// Поддерживается: ## заголовок, ### подзаголовок, - список, 1. нумерованный список, > заметка, **жирный**, [текст](адрес).
// Работает и при сборке (Astro), и в браузере (живое обновление без пересборки).
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const safeUrl = (u) => (/^(https?:\/\/|\/|#|mailto:|tel:)/i.test(u) ? u : '#');

const inline = (t) =>
  esc(t)
    .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (m, text, url) => `<a href="${safeUrl(url.replace(/&amp;/g, '&'))}"${/^https?:/i.test(url) ? ' target="_blank" rel="noopener"' : ''}>${text}</a>`)
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[\s(])_([^_]+)_(?=[\s).,;:]|$)/g, '$1<em>$2</em>');

export function mdToHtml(src) {
  const lines = String(src || '').replace(/\r\n?/g, '\n').split('\n');
  const out = [];
  const headings = [];
  let para = [];
  let list = null; // { tag, items }
  let quote = [];
  let n = 0;

  const flushPara = () => { if (para.length) { out.push(`<p>${inline(para.join(' '))}</p>`); para = []; } };
  const flushList = () => { if (list) { out.push(`<${list.tag}>${list.items.map((i) => `<li>${inline(i)}</li>`).join('')}</${list.tag}>`); list = null; } };
  const flushQuote = () => { if (quote.length) { out.push(`<blockquote><p>${inline(quote.join(' '))}</p></blockquote>`); quote = []; } };
  const flush = () => { flushPara(); flushList(); flushQuote(); };

  for (const raw of lines) {
    const line = raw.trimEnd();
    if (!line.trim()) { flush(); continue; }
    let m;
    if ((m = line.match(/^(#{1,4})\s+(.+)$/))) {
      flush();
      const depth = Math.max(2, m[1].length); // h1 на странице уже есть
      const id = 's' + ++n;
      headings.push({ depth, slug: id, text: m[2].trim() });
      out.push(`<h${depth} id="${id}">${inline(m[2].trim())}</h${depth}>`);
    } else if ((m = line.match(/^\s*[-*•]\s+(.+)$/))) {
      flushPara(); flushQuote();
      if (!list || list.tag !== 'ul') { flushList(); list = { tag: 'ul', items: [] }; }
      list.items.push(m[1]);
    } else if ((m = line.match(/^\s*\d+[.)]\s+(.+)$/))) {
      flushPara(); flushQuote();
      if (!list || list.tag !== 'ol') { flushList(); list = { tag: 'ol', items: [] }; }
      list.items.push(m[1]);
    } else if ((m = line.match(/^>\s?(.*)$/))) {
      flushPara(); flushList();
      quote.push(m[1]);
    } else {
      flushList(); flushQuote();
      para.push(line.trim());
    }
  }
  flush();
  return { html: out.join('\n'), headings };
}

// короткая подпись текста: по ней страница понимает, что в админке документ уже изменили
export const docSig = (s) => {
  const t = String(s || '');
  let h = 0;
  for (let i = 0; i < t.length; i++) h = (h * 31 + t.charCodeAt(i)) | 0;
  return `${t.length}.${(h >>> 0).toString(36)}`;
};
