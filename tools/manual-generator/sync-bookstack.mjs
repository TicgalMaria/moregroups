#!/usr/bin/env node
/**
 * sync-bookstack.mjs
 *
 * Pushes the generated manual files (plus their screenshots) into BookStack as one page
 * per file, creating the Shelf/Book/Pages the first time and updating the *same* pages on
 * every later run. Meant to be step 5 of the manual pipeline:
 *
 *   restore fixture → playwright test → render-manual.mjs → (pandoc) → sync-bookstack.mjs
 *
 * Structure in BookStack:
 *   Shelf   = language                e.g. "GLPI Plugins"      (one, shared)
 *   Book    = plugin                  e.g. "DomainManager"     (one per plugin)
 *   Chapter = optional grouping       only used if --chapter is passed
 *   Page    = one per manual file — any `NN-name.md` in --manual-dir, sorted numerically
 *             (render-manual.mjs currently emits 6: 01-intro, 02-whats-new, 03-scope,
 *             04-setup, 05-usage, 06-troubleshooting, but this isn't hardcoded to that
 *             count — a plugin with a 7th chapter, or a future 07-*.md, is picked up with
 *             no script change).
 *             Each page is titled from that file's own `# N. Title` heading.
 *
 * Requires Node 18+ (native fetch).
 *
 * Env vars: see the "BookStack sync" section in manual.env.example. This is a Node
 * script, not bash, so it can't source manual.env itself — run it via the wrapper,
 * which sources manual.env then execs this file:
 *
 *   ./tools/manual-generator/sync-bookstack.sh --plugin domainmanager --locale en_GB \
 *        --manual-dir docs/manual/en_GB \
 *        [--chapter "User Manual"]   # optional, omit to put the pages directly in the book
 *
 * --manual-dir must be the locale directory produced by render-manual.mjs (the one
 * containing its numbered `NN-name.md` files and assets/).
 *
 * Limits worth knowing about:
 *   - Request body = manual text + every embedded screenshot, base64-encoded
 *     (~33% bigger than the PNGs themselves). A large manual can trip PHP's
 *     upload_max_filesize/post_max_size or a reverse proxy's body-size limit —
 *     raise those on the BookStack server if you get a 413.
 *   - Default API rate limit is 180 req/min per user (API_REQUESTS_PER_MIN in
 *     BookStack's own .env) — a non-issue for one manual per run.
 */

import { readFile, writeFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';

// ---- config from environment --------------------------------------------

const {
  BOOKSTACK_URL,
  BOOKSTACK_TOKEN_ID,
  BOOKSTACK_TOKEN_SECRET,
  BOOKSTACK_SHELF_NAME = 'GLPI Plugins',
  PLUGIN_LOGO,
  PLUGIN_TAGLINE,
  GLPI_VERSION,
} = process.env;

for (const [k, v] of Object.entries({ BOOKSTACK_URL, BOOKSTACK_TOKEN_ID, BOOKSTACK_TOKEN_SECRET })) {
  if (!v) throw new Error(`Missing env var ${k} — check manual.env is sourced before running this`);
}

const args = parseArgs(process.argv.slice(2));
if (!args.plugin || !args.locale || !args['manual-dir']) {
  console.error('Usage: --plugin <slug> --locale <en_GB> --manual-dir <docs/manual/en_GB> [--chapter "..."]');
  process.exit(1);
}

// render-manual.mjs's own naming convention — any count, not locked to today's 4 chapters.
const MANUAL_FILE_RE = /^\d{2,}-[\w-]+\.md$/;

async function listManualFiles(manualDir) {
  const names = existsSync(manualDir) ? await readdir(manualDir) : [];
  return names.filter((n) => MANUAL_FILE_RE.test(n)).sort((a, b) => a.localeCompare(b, 'en', { numeric: true }));
}

// Shared across locales for one plugin: docs/manual/.bookstack-map.json
const MAP_FILE = path.join(path.dirname(args['manual-dir']), '.bookstack-map.json');

// ---- tiny BookStack API client --------------------------------------------

async function bs(pathname, { method = 'GET', body } = {}) {
  const res = await fetch(`${BOOKSTACK_URL}/api${pathname}`, {
    method,
    headers: {
      Authorization: `Token ${BOOKSTACK_TOKEN_ID}:${BOOKSTACK_TOKEN_SECRET}`,
      'Content-Type': 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    throw new Error(`BookStack ${method} ${pathname} -> ${res.status} ${res.statusText}: ${await res.text()}`);
  }
  return res.status === 204 ? null : res.json();
}

// Book metadata (image, description, tags) requires multipart/form-data — BookStack's own
// docs say the JSON PUT used for name/description/tags-only updates also accepts `image` as
// a file field when sent as multipart, so this replaces bs() for that one endpoint rather
// than running two separate requests.
async function bsMultipart(pathname, { method = 'POST', fields = {}, files = {} } = {}) {
  const form = new FormData();
  for (const [k, v] of Object.entries(fields)) if (v !== undefined) form.set(k, v);
  for (const [k, { buffer, filename }] of Object.entries(files)) {
    form.set(k, new Blob([buffer]), filename);
  }
  const res = await fetch(`${BOOKSTACK_URL}/api${pathname}`, {
    method,
    headers: { Authorization: `Token ${BOOKSTACK_TOKEN_ID}:${BOOKSTACK_TOKEN_SECRET}` },
    body: form,
  });
  if (!res.ok) {
    throw new Error(`BookStack ${method} ${pathname} (multipart) -> ${res.status} ${res.statusText}: ${await res.text()}`);
  }
  return res.status === 204 ? null : res.json();
}

// First sentence of the KB doc's "## Description" section — same fallback pattern as
// render-manual.mjs's own findKbFile(), but read directly since this script doesn't share
// that module.
async function fallbackTagline() {
  const kbDir = path.join('docs', 'kb');
  let files;
  try {
    files = (await readdir(kbDir)).filter((f) => f.endsWith('.md') && !/\.[a-z]{2,3}(?:_[A-Z]{2,3})?\.md$/.test(f));
  } catch {
    return '';
  }
  if (files.length !== 1) return '';
  const raw = await readFile(path.join(kbDir, files[0]), 'utf8');
  const section = raw.match(/^##\s+Description\s*\n([\s\S]*?)(?=\n##\s|$)/m)?.[1] ?? '';
  return section.trim().split(/(?<=[.!?])\s+/)[0]?.replace(/\n/g, ' ') ?? '';
}

// Applies book-level metadata (name, description, GLPI-version tag, cover image) via one
// multipart PUT — cheap enough to run on every sync so an out-of-band edit in BookStack
// (someone renaming the book by hand) gets overwritten back to the source of truth, same
// as the existing name-sync PUT this replaces.
async function syncBookMetadata(bookId, { name, glpiVersion }) {
  const tagline = PLUGIN_TAGLINE || (await fallbackTagline());
  const fields = {
    name,
    ...(tagline ? { description: tagline } : {}),
    ...(glpiVersion ? { 'tags[0][name]': 'GLPI', 'tags[0][value]': glpiVersion } : {}),
  };
  const files = {};
  if (PLUGIN_LOGO && existsSync(PLUGIN_LOGO)) {
    files.image = { buffer: await readFile(PLUGIN_LOGO), filename: path.basename(PLUGIN_LOGO) };
  }
  await bsMultipart(`/books/${bookId}`, { method: 'PUT', fields, files });
}

async function findByName(pathname, name, extraFilter = {}) {
  const params = new URLSearchParams({ count: '1', 'filter[name]': name });
  for (const [k, v] of Object.entries(extraFilter)) params.set(`filter[${k}]`, v);
  const { data } = await bs(`${pathname}?${params}`);
  return data?.[0]?.id ?? null;
}

async function getOrCreateShelf(name) {
  const existing = await findByName('/shelves', name);
  if (existing) return existing;
  const created = await bs('/shelves', { method: 'POST', body: { name, books: [] } });
  return created.id;
}

// The shelf owns the relationship: a book is attached by PUTting the shelf's
// `books` array, not by setting anything on the book itself.
async function ensureBookOnShelf(shelfId, bookId) {
  const shelf = await bs(`/shelves/${shelfId}`);
  const currentIds = (shelf.books ?? []).map((b) => b.id);
  if (currentIds.includes(bookId)) return;
  await bs(`/shelves/${shelfId}`, { method: 'PUT', body: { books: [...currentIds, bookId] } });
}

async function getOrCreateBook(name, shelfId) {
  const existing = await findByName('/books', name);
  if (existing) {
    await ensureBookOnShelf(shelfId, existing);
    return existing;
  }
  const created = await bs('/books', { method: 'POST', body: { name } });
  await ensureBookOnShelf(shelfId, created.id);
  return created.id;
}

async function getOrCreateChapter(bookId, name) {
  const existing = await findByName('/chapters', name, { book_id: bookId });
  if (existing) return existing;
  const created = await bs('/chapters', { method: 'POST', body: { book_id: bookId, name } });
  return created.id;
}

// Updates the cached page id if it still exists; falls back to creating a fresh page
// if it was deleted in BookStack since the last run (map going stale otherwise).
async function putOrCreatePage(cachedId, { bookId, chapterId, name, markdown }) {
  if (cachedId) {
    try {
      const updated = await bs(`/pages/${cachedId}`, { method: 'PUT', body: { name, markdown } });
      console.log(`Updated page ${cachedId} (slug: ${updated.slug})`);
      return { id: cachedId, slug: updated.slug };
    } catch (err) {
      if (!String(err.message).includes('404')) throw err;
      console.warn(`page ${cachedId} no longer exists in BookStack — recreating`);
    }
  }
  const created = await bs('/pages', {
    method: 'POST',
    body: { book_id: bookId, ...(chapterId ? { chapter_id: chapterId } : {}), name, markdown },
  });
  console.log(`Created page ${created.id} (slug: ${created.slug}) in book ${bookId}${chapterId ? `, chapter ${chapterId}` : ''}`);
  return { id: created.id, slug: created.slug };
}

// ---- inline local screenshots as base64 data URIs -------------------------

const MIME = { '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg' };

async function inlineImages(markdown, baseDir) {
  // Matches ![alt](relative/path.png) — skips anything already http(s):// or data:
  const imgRe = /!\[([^\]]*)\]\((?!https?:|data:)([^)\s]+)\)/g;
  let out = '';
  let last = 0;
  for (const m of markdown.matchAll(imgRe)) {
    const [full, alt, relPath] = m;
    const abs = path.resolve(baseDir, relPath);
    const mime = MIME[path.extname(abs).toLowerCase()];
    out += markdown.slice(last, m.index);
    if (mime && existsSync(abs)) {
      const b64 = (await readFile(abs)).toString('base64');
      out += `![${alt}](data:${mime};base64,${b64})`;
    } else {
      out += full; // leave untouched — already remote, or file genuinely missing
    }
    last = m.index + full.length;
  }
  out += markdown.slice(last);
  return out;
}

// ---- main -------------------------------------------------------------

// First line of each generated file is always `# N. Title` (render-manual.mjs's own
// heading convention) — reuse it as the page name so BookStack's page list reads the
// same way the manual itself does, instead of one undifferentiated blob.
function pageTitleFrom(markdown, fallback) {
  return markdown.match(/^#\s+(.+)$/m)?.[1]?.trim() ?? fallback;
}

// BookStack renders the page's own `name` as an on-page heading above the content, so
// pushing the source's leading `# N. Title` line as part of the body duplicates the title
// visually. Strip just that first H1 (and the blank line after it) — every deeper heading
// stays, since only the top-level title is BookStack's job to render.
function stripLeadingTitle(markdown) {
  return markdown.replace(/^#\s+.+\n+/, '');
}

// render-manual.mjs cross-links the 6 files with plain relative filenames
// (`[5. Usage](05-usage.md)`, `[4.2 Configuration](04-setup.md#42-configuration)`), which
// is correct for the Markdown/pandoc/PDF path but 404s in BookStack — each file becomes
// its own page at a `/books/<book-slug>/page/<page-slug>` URL, not a same-named file next
// to it. The heading anchor (`#22-configuration`) doesn't survive either: BookStack
// generates its own `#bkmrk-...` ids from the rendered heading text, unrelated to the
// source Markdown's anchor slug. Fixing the 404 (link resolves to the right page) matters
// more than preserving the in-page scroll position, so the anchor is dropped rather than
// guessed at.
const INTERNAL_LINK_RE = /\]\((\d{2,}-[\w-]+\.md)(?:#[-\w]+)?\)/g;

function resolveInternalLinks(markdown, fileToUrl) {
  return markdown.replace(INTERNAL_LINK_RE, (full, file) => (fileToUrl[file] ? `](${fileToUrl[file]})` : full));
}

async function main() {
  const manualDir = args['manual-dir'];
  const files = await listManualFiles(manualDir);
  if (!files.length) throw new Error(`No numbered manual files (NN-name.md) found in ${manualDir}`);

  const map = existsSync(MAP_FILE) ? JSON.parse(await readFile(MAP_FILE, 'utf8')) : {};
  const key = `${args.plugin}:${args.locale}`;
  const entry = map[key];

  // Same PLUGIN_NAME override render-manual.mjs uses for the manual's own banner text —
  // args.plugin (the directory/slug, e.g. "moresecurity") stays the map key so renaming
  // the display name later doesn't orphan the existing book.
  const bookTitle = process.env.PLUGIN_NAME ?? args.plugin;
  // Major series only ("11" from "11.0.8") — matches the single GLPI-11-wide tag the book
  // gets, rather than one tag per point release.
  const glpiMajor = GLPI_VERSION?.split('.')[0];

  const shelfId = entry?.shelf_id ?? (await getOrCreateShelf(BOOKSTACK_SHELF_NAME));
  let bookId = entry?.book_id;
  if (!bookId) bookId = await getOrCreateBook(bookTitle, shelfId);
  // Keep the book's name/description/tag/image in sync with source on every run — e.g. this
  // plugin's book was created before PLUGIN_NAME was set and needed renaming by hand.
  await syncBookMetadata(bookId, { name: bookTitle, glpiVersion: glpiMajor });
  const chapterId = args.chapter
    ? entry?.chapter_id ?? (await getOrCreateChapter(bookId, args.chapter))
    : entry?.chapter_id ?? null;

  const pages = entry?.pages ?? {};

  // Pass 1: push each page as-is (internal links still pointing at bare filenames) so
  // every page gets a real id/slug — a fresh page's slug isn't known until it exists.
  const rendered = {};
  for (const name of files) {
    const raw = await readFile(path.join(manualDir, name), 'utf8');
    const markdown = await inlineImages(raw, manualDir);
    const title = pageTitleFrom(markdown, name);
    const body = stripLeadingTitle(markdown);
    const { id, slug } = await putOrCreatePage(pages[name], { bookId, chapterId, name: title, markdown: body });
    pages[name] = id;
    rendered[name] = { id, slug, title, markdown: body };
  }

  // Pass 2: now that every page has a slug, rewrite `0N-name.md` links into real
  // `/books/<book-slug>/page/<page-slug>` URLs and push the corrected content.
  const { slug: bookSlug } = await bs(`/books/${bookId}`);
  const fileToUrl = Object.fromEntries(
    Object.entries(rendered).map(([name, { slug }]) => [name, `${BOOKSTACK_URL}/books/${bookSlug}/page/${slug}`]),
  );
  for (const name of files) {
    const { id, title, markdown } = rendered[name];
    const fixed = resolveInternalLinks(markdown, fileToUrl);
    if (fixed !== markdown) await putOrCreatePage(id, { bookId, chapterId, name: title, markdown: fixed });
  }

  map[key] = { shelf_id: shelfId, book_id: bookId, chapter_id: chapterId, pages };
  await writeFile(MAP_FILE, JSON.stringify(map, null, 2));
}

function parseArgs(argv) {
  const out = {};
  for (let i = 0; i < argv.length; i += 2) out[argv[i].replace(/^--/, '')] = argv[i + 1];
  return out;
}

main().catch((err) => {
  console.error(err.message);
  process.exit(1);
});
