#!/usr/bin/env node
/**
 * render-manual.mjs — manifests → 01-intro.md / 02-whats-new.md / 03-scope.md /
 * 04-setup.md / 05-usage.md / 06-troubleshooting.md
 *
 * Place in tools/manual-generator/. Run after a capture pass, from the repo root:
 *   MANUAL_LOCALE=es_ES node tools/manual-generator/render-manual.mjs
 *
 * The manual is six numbered, cross-linked files instead of one monolithic MANUAL.md:
 *   1. Introduction     — 01-intro.md   (KB-sourced: what/why/features — reference-only,
 *                          no setup, usage or scope detail)
 *   2. What's New        — 02-whats-new.md, generated from the hand-written
 *      _whats-new.md (no automatable source — release notes are authored by hand,
 *      linking into the sections below, same convention as troubleshooting)
 *   3. Scope             — 03-scope.md  (KB-sourced: which GLPI elements the plugin
 *                          touches — assets, automatic actions, notifications, rules,
 *                          permissions — split out of Introduction so each BookStack page
 *                          stays focused on one thing)
 *   4. Setup             — 04-setup.md  (KB-sourced install/config/permissions/automatic
 *                          actions, plus whichever chapters MANUAL_SETUP_SLUGS names)
 *   5. Usage             — 05-usage.md  (KB "How to use", plus every remaining chapter)
 *   6. Troubleshooting   — 06-troubleshooting.md, generated from the hand-written
 *      _troubleshooting.md (no automatable source for troubleshooting content)
 * _whats-new.md and _troubleshooting.md are hand-written and the renderer never overwrites
 * them — never read 02-whats-new.md/06-troubleshooting.md back as an input, or every
 * re-render duplicates its own banner.
 * Section and subsection numbers (2.3, 2.3.1, ...) are assigned by this script from
 * manifest order and MANUAL_SETUP_SLUGS, so inserting/reordering a chapter renumbers the
 * manual consistently. Set MANUAL_SETUP_SLUGS to a comma-separated list of manifest slugs
 * that belong under Setup (rights/permissions/driver-config style chapters); everything
 * else lands under Usage in manifest order.
 *
 * This run also (re)generates a per-locale KB deliverable, docs/kb/<slug>.<locale>.md, from
 * the single hand-written docs/kb/<slug>.md — same run, so the two documents never drift.
 * Its `<!-- shot: <chapter-slug>/<filename-without-ext> -->` markers resolve to real image
 * embeds pointing at the *manual's own* screenshots (shared files, never copied or
 * regenerated) under the base locale's assets/ — see loadLabelGlossary's header comment for
 * why no locale other than MANUAL_BASE_LOCALE ever gets its own screenshots (Trap 15).
 */
import fs from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const LOCALE = process.env.MANUAL_LOCALE ?? 'en_GB';
// The only locale screenshots are ever captured for. Every other locale's manual and KB
// deliverable reuse these same PNGs verbatim — see the header comment above and Trap 15 in
// per locale is exactly the churn this pipeline exists to avoid paying twice for.
const BASE_LOCALE = process.env.MANUAL_BASE_LOCALE ?? 'en_GB';
const ROOT = process.env.MANUAL_ROOT ?? path.join('docs', 'manual');
const OUT = process.env.MANUAL_OUT ?? path.join(ROOT, LOCALE);
const KB_DIR = process.env.MANUAL_KB_DIR ?? path.join('docs', 'kb');
const LABELS_DIR = process.env.MANUAL_LABELS_DIR ?? path.join('tools', 'manual-generator', 'i18n');
// Same asset the BookStack book-cover sync uses (sync-bookstack.mjs's PLUGIN_LOGO) — reused
// here as a small per-page mark so every generated manual file carries it, not just the book.
const PLUGIN_LOGO = process.env.PLUGIN_LOGO;
const SETUP_SLUGS = (process.env.MANUAL_SETUP_SLUGS ?? '')
  .split(',')
  .map((s) => s.trim())
  .filter(Boolean);

const STRINGS = {
  en_GB: {
    note: 'Note', banner: (p, g, d) =>
      `Generated for **${p}** on GLPI ${g} — ${d}.`,
    intro: 'Introduction', whatsNew: "What's New", scope: 'Scope', setup: 'Setup', usage: 'Usage', troubleshooting: 'Troubleshooting',
  },
};
const S = STRINGS[LOCALE] ?? STRINGS.en_GB;

// Mirrors GitHub's own heading-anchor algorithm (strip punctuation, keep spaces, lowercase,
// spaces -> hyphens) so links like [4.2 Configuration](04-setup.md#42-configuration) resolve.
const slugify = (s) => s.toLowerCase().replace(/[^\p{L}\p{N}\s-]/gu, '').trim().replace(/\s+/g, '-');
const anchor = (heading) => `#${slugify(heading)}`;

async function readIfPresent(p) {
  try { return (await fs.readFile(p, 'utf8')).trim(); } catch { return ''; }
}

// Matches the per-locale KB deliverables this script itself generates (<slug>.<locale>.md,
// e.g. "domain-manager.en_GB.md") so findKbFile() below doesn't mistake its own previous
// output for a second hand-written source and give up disambiguating.
const KB_LOCALE_SUFFIX_RE = /\.[a-z]{2,3}(?:_[A-Z]{2,3})?\.md$/;

/** Locate the plugin's hand-written KB/marketplace-style doc (docs/kb/*.md, or MANUAL_KB override). */
async function findKbFile() {
  if (process.env.MANUAL_KB) return process.env.MANUAL_KB;
  let files;
  try {
    files = (await fs.readdir(KB_DIR))
      .filter((f) => f.endsWith('.md') && !KB_LOCALE_SUFFIX_RE.test(f));
  } catch {
    return null;
  }
  return files.length === 1 ? path.join(KB_DIR, files[0]) : null;
}

/** Split a KB doc into { headingText: fullBlockIncludingHeadingAndNestedSubheadings }. */
function parseKbSections(md) {
  const sections = new Map();
  let current = null;
  let buf = [];
  for (const line of md.split('\n')) {
    const h2 = line.match(/^##\s+(.+)$/);
    if (h2) {
      if (current) sections.set(current, buf.join('\n').trim());
      current = h2[1].trim();
      buf = [line];
    } else if (current) {
      buf.push(line);
    }
  }
  if (current) sections.set(current, buf.join('\n').trim());
  return sections;
}

/** Strip a KB section's own "## Heading" line — the caller re-numbers and re-emits it. */
function stripHeading(block) {
  return block ? block.replace(/^##\s+.+\n?/, '').trim() : '';
}

/** First bullet-list line's leading label (before the em dash/colon), for a names-only reference list. */
function namesOnly(block) {
  if (!block) return '';
  const names = [];
  for (const line of block.split('\n')) {
    const bullet = line.match(/^-\s+(.+)$/);
    if (bullet) {
      const label = bullet[1].split(/\s+—\s+|\s+-\s+|:/)[0].trim();
      names.push(label);
      continue;
    }
    const row = line.match(/^\|\s*([^|]+?)\s*\|/);
    if (row && !/^-+$/.test(row[1]) && !/^name$/i.test(row[1])) names.push(row[1].trim());
  }
  return names.length ? names.map((n) => `- ${n}`).join('\n') : '';
}

// A manual is a client deliverable: it must never advertise a dev/alpha/beta/rc build as
// if it were the released product. Matches a trailing -dev, -alpha, -beta, -rc (optionally
// followed by a number), case-insensitively, e.g. "1.7.1-beta2", "2.0.0-rc1", "1.0-dev".
const PRERELEASE_RE = /-(dev|alpha|beta|rc)\.?\d*$/i;

/** Plugin name and version from setup.php; env wins if provided. */
async function pluginInfo() {
  let name = process.env.PLUGIN_NAME ?? path.basename(process.cwd());
  let version = process.env.PLUGIN_VERSION ?? '';
  if (!version) {
    const setup = await readIfPresent('setup.php');
    version = setup.match(/define\(\s*'PLUGIN_\w+_VERSION'\s*,\s*'([^']+)'/)?.[1] ?? 'unknown';
    const declared = setup.match(/PLUGIN_(\w+)_VERSION/)?.[1];
    if (declared && !process.env.PLUGIN_NAME) name = declared.toLowerCase();
  }
  if (PRERELEASE_RE.test(version) && process.env.MANUAL_ALLOW_PRERELEASE !== '1') {
    throw new Error(
      `refusing to build the manual for pre-release version '${version}' — this pipeline ` +
      `only targets production releases (no -dev/-alpha/-beta/-rc). Bump the version past ` +
      `the pre-release stage before regenerating, or set MANUAL_ALLOW_PRERELEASE=1 for a ` +
      `local-only test render.`,
    );
  }
  return { name, version, glpi: process.env.GLPI_VERSION ?? '11.0' };
}

async function loadManifests() {
  const dir = path.join(OUT, '.manifests');
  let files;
  try {
    files = (await fs.readdir(dir)).filter((f) => f.endsWith('.json'));
  } catch {
    throw new Error(`no manifests in ${dir} — run the capture pass first`);
  }
  if (files.length === 0) throw new Error(`no manifests in ${dir} — run the capture pass first`);
  const all = await Promise.all(
    files.map(async (f) => JSON.parse(await fs.readFile(path.join(dir, f), 'utf8'))),
  );
  return all.sort((a, b) => a.order - b.order || a.slug.localeCompare(b.slug));
}

/** Chapter asset folders are prefixed with the zero-padded chapter position so they sort like the manual. */
function assetDirName(chapterNum, slug) {
  return `${String(chapterNum).padStart(2, '0')}-${slug}`;
}

/**
 * Rename each manifest's assets/<slug> folder to assets/<N>-<slug>, N being its 1-based
 * position in manifest order (independent of which document section it lands in).
 * Idempotent: a folder already carrying its current prefix is left alone.
 */
async function renumberAssetDirs(manifests) {
  const assetsRoot = path.join(OUT, 'assets');
  for (const [i, m] of manifests.entries()) {
    const wanted = assetDirName(i + 1, m.slug);
    const wantedAbs = path.join(assetsRoot, wanted);
    const alreadyRenamed = await fs.stat(wantedAbs).then(() => true, () => false);
    if (alreadyRenamed) continue;
    let entries;
    try {
      entries = await fs.readdir(assetsRoot);
    } catch {
      continue;
    }
    const stale = entries.find((e) => e === m.slug || e.endsWith(`-${m.slug}`));
    if (stale && stale !== wanted) {
      await fs.rename(path.join(assetsRoot, stale), wantedAbs);
    }
  }
}

/**
 * Copies PLUGIN_LOGO into this locale's assets/logo.png, downscaled to a small mark, and
 * returns the markdown image line every generated page's banner carries. Markdown image
 * syntax, not a raw HTML `<img>`, on purpose: BookStack's markdown import only auto-uploads
 * (and rewrites to a real gallery URL) images written as `![alt](path)` — a raw `<img
 * src="data:...">` tag round-trips through the page's stored markdown but gets silently
 * dropped from the *rendered* HTML, i.e. invisible on the actual page (confirmed against a
 * live docs.tic.gal page: the tag survived in the API's `markdown` field but never made it
 * into `html`). Markdown syntax also has no width attribute, so the file itself is shrunk
 * with ImageMagick (`convert`) when available; sizing best-effort, not required — a missing
 * `convert` binary just leaves the mark at the source logo's own size rather than failing the
 * whole render. Returns '' when PLUGIN_LOGO is unset, so a plugin with no committed logo yet
 * still renders a manual, just without the mark.
 */
async function placeManualLogo() {
  if (!PLUGIN_LOGO || !existsSync(PLUGIN_LOGO)) return '';
  const dest = path.join(OUT, 'assets', 'logo.png');
  await fs.mkdir(path.dirname(dest), { recursive: true });
  try {
    execFileSync('convert', [PLUGIN_LOGO, '-resize', '96x96', dest], { stdio: 'ignore' });
  } catch {
    await fs.copyFile(PLUGIN_LOGO, dest);
  }
  return '![logo](assets/logo.png)';
}

/**
 * Render one chapter under a dotted section prefix, e.g. prefix "2.3" -> "2.3", "2.3.1",
 * "2.3.2"... `localize` runs over every piece of prose (chapter intro, step body, notes) —
 * the step's own screenshot is never touched, since it was captured once against the base
 * locale's UI and is reused as-is for every other locale (Trap 15).
 */
function renderChapter(m, assetDir, prefix, localize) {
  const out = [`## ${prefix} ${m.title}`, ''];
  if (m.intro) out.push(localize(m.intro), '');
  for (const step of m.steps) {
    out.push(`### ${prefix}.${step.seq} ${step.title}`, '');
    if (step.body) out.push(localize(step.body), '');
    for (const shot of step.shots) {
      const alt = (shot.caption ?? `${m.title} — ${step.title}`).replace(/[[\]]/g, '');
      out.push(`![${alt}](assets/${assetDir}/${shot.file})`, '');
      if (shot.caption) out.push(`*${shot.caption}*`, '');
    }
    for (const note of step.notes) out.push(`> **${S.note}:** ${localize(note)}`, '');
  }
  return out.join('\n');
}

/**
 * Load { English label -> localized label } for LOCALE from tools/manual-generator/i18n/
 * labels.<locale>.json, e.g. { "Setup": "Configuración", "General": "General" }. Returns
 * null for the base locale (nothing to localize) or when no glossary file exists yet.
 *
 * This glossary is NOT a translation exercise for an LLM to do — every value in it must come
 * from GLPI's own rendered UI (core + this plugin) in that locale: log in as that locale's
 * documentation user (the fixture already has one per Trap 4) and copy the real label text
 * from the DOM, once, into this file. A glossary entry that was guessed/machine-translated
 * instead of read off the actual UI is worse than a missing one — a reader trusts it to be
 * the literal button/menu text they'll see, not an approximation of it.
 */
async function loadLabelGlossary() {
  if (LOCALE === BASE_LOCALE) return null;
  const raw = await readIfPresent(path.join(LABELS_DIR, `labels.${LOCALE}.json`));
  if (!raw) return null;
  try {
    return new Map(Object.entries(JSON.parse(raw)));
  } catch {
    throw new Error(`labels.${LOCALE}.json is not valid JSON — fix or delete it`);
  }
}

/**
 * Append each glossary hit as "**English (Localized)**" beside every bolded UI-label mention
 * this pipeline emits — KB prose, Setup/Intro text pulled from the KB, and chapter step
 * bodies alike. Longest keys are substituted first so "Setup > Automatic actions" doesn't
 * get its "Setup" swapped out from under the more specific match. A no-op when `glossary`
 * is null (base locale, or no glossary file yet for this locale).
 */
function localizeLabels(text, glossary) {
  if (!text || !glossary) return text;
  const keys = [...glossary.keys()].sort((a, b) => b.length - a.length);
  let out = text;
  for (const key of keys) {
    const escaped = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    out = out.replace(new RegExp(`\\*\\*${escaped}\\*\\*`, 'g'), `**${key} (${glossary.get(key)})**`);
  }
  return out;
}

/**
 * Resolve `<!-- shot: <chapter-slug>/<filename-without-ext> --> markers in the KB doc into
 * real image embeds against the *manual's own* screenshots — same PNG files the manual
 * itself references, always under the base locale's assets/ regardless of which locale's KB
 * deliverable is being rendered (Trap 15: no locale but the base one ever gets its own
 * screenshots). Throws on a marker whose chapter/file doesn't exist, rather than silently
 * dropping the image — a KB doc referencing a screenshot that was never captured is a bug in
 * the marker, not something to paper over.
 *
 * `assetsPathPrefix` differs by consumer: the manual's own 0N-*.md files live inside
 * docs/manual/<LOCALE>/ already, right next to assets/, so they need a bare "assets/...";
 * the KB deliverable lives in docs/kb/, so it needs to reach across into
 * "../manual/<BASE_LOCALE>/assets/...". Same markers, same underlying PNGs either way —
 * only the relative path differs.
 */
function resolveKbShotMarkers(text, manifests, assetsPathPrefix) {
  const assetDirByChapterSlug = new Map(manifests.map((m, i) => [m.slug, assetDirName(i + 1, m.slug)]));
  const shotFilesByChapterSlug = new Map(
    manifests.map((m) => [m.slug, new Set(m.steps.flatMap((s) => s.shots.map((sh) => sh.file)))]),
  );
  return text.replace(/<!--\s*shot:\s*([\w-]+)\/([\w-]+)\s*-->/g, (whole, chapterSlug, fileStem) => {
    const assetDir = assetDirByChapterSlug.get(chapterSlug);
    const file = `${fileStem}.png`;
    if (!assetDir || !shotFilesByChapterSlug.get(chapterSlug)?.has(file)) {
      throw new Error(`KB doc shot marker '${chapterSlug}/${fileStem}' has no matching chapter/screenshot`);
    }
    return `![${chapterSlug} — ${fileStem}](${assetsPathPrefix}/${assetDir}/${file})`;
  });
}

/**
 * docs/kb/<slug>.<locale>.md — generated in the same run as the manual from the single
 * hand-written docs/kb/<slug>.md, with its shot markers resolved and (for any locale but the
 * base one) its bolded UI labels given a localized twin from the glossary. Never overwrites
 * the hand-written source it reads from — that file has no locale suffix.
 */
async function renderKbDoc(kbPath, kbRawWithShots, glossary) {
  if (!kbPath) return;
  const localized = localizeLabels(kbRawWithShots, glossary);
  const slug = path.basename(kbPath, '.md');
  const target = path.join(KB_DIR, `${slug}.${LOCALE}.md`);
  await fs.writeFile(target, localized.trimEnd() + '\n', 'utf8');
  console.log(`${target}: generated from ${kbPath} (${LOCALE}${glossary ? ', localized labels' : ''})`);
}

const main = async () => {
  const { name, version, glpi } = await pluginInfo();
  const manifests = await loadManifests();
  await renumberAssetDirs(manifests);
  const logoLine = await placeManualLogo();
  const date = new Date().toISOString().slice(0, 10);
  // The logo sits at the top, right-aligned on the page — the classic GitHub-README
  // div-wrap trick: a bare `<div align="right">` line opens its own single-line HTML block
  // per CommonMark and ends at the next blank line, so the markdown image paragraph after
  // it still gets processed normally (path resolved, uploaded to BookStack's gallery, etc.)
  // — only the wrapper tags are raw HTML, never the `<img>` itself, which is what broke last
  // time (a raw `<img src="data:...">` tag, not any raw HTML at all, is what BookStack drops).
  const logoBlock = logoLine ? `<div align="right">\n\n${logoLine}\n\n</div>` : '';
  // The "Generated for..." stamp moves to the bottom of each page, as a footer, instead of
  // sitting right under the title.
  const footer = `> ${S.banner(`${name} ${version}`, glpi, date)}`;

  // Shot markers are resolved once, here, on the raw KB text — before it's split into
  // sections — so the manual's Intro/Setup pull-through and the KB deliverable both get the
  // same embedded images from the same source text, rather than the manual leaking raw
  // `<!-- shot: ... -->` comments because only the KB doc's own render step resolved them.
  // Two variants because the two consumers sit in different directories (see
  // resolveKbShotMarkers's header comment for why the path prefix differs).
  const kbPath = await findKbFile();
  const kbSourceText = kbPath ? await fs.readFile(kbPath, 'utf8') : '';
  const kbRawForManual = kbPath ? resolveKbShotMarkers(kbSourceText, manifests, 'assets') : '';
  const kbRawForKb = kbPath ? resolveKbShotMarkers(kbSourceText, manifests, `../manual/${BASE_LOCALE}/assets`) : '';
  const kb = kbPath ? parseKbSections(kbRawForManual) : new Map();
  const glossary = await loadLabelGlossary();
  // Every mention of a KB section below (and step prose from a chapter manifest, which was
  // authored once against the base locale's UI — Trap 15) is routed through this, not just
  // raw kb.get() calls, so "**Setup**" reads "**Setup (Configuración)**" everywhere it
  // appears, not only inside the KB deliverable itself.
  const localize = (text) => localizeLabels(text, glossary);

  // Chapters split between Setup (2.x) and Usage (3.x) by MANUAL_SETUP_SLUGS; manifest
  // order (not the env var's order) drives numbering within each section.
  const bySlug = new Map(manifests.map((m, i) => [m.slug, { m, assetDir: assetDirName(i + 1, m.slug) }]));
  const setupChapters = manifests
    .filter((m) => SETUP_SLUGS.includes(m.slug))
    .map((m) => bySlug.get(m.slug));
  const usageChapters = manifests
    .map((m, i) => ({ m, assetDir: assetDirName(i + 1, m.slug) }))
    .filter(({ m }) => !SETUP_SLUGS.includes(m.slug));

  const SEE_ALSO = (current) => {
    const docs = [
      ['1. Introduction', '01-intro.md'],
      ["2. What's New", '02-whats-new.md'],
      ['3. Scope', '03-scope.md'],
      ['4. Setup', '04-setup.md'],
      ['5. Usage', '05-usage.md'],
      ['6. Troubleshooting', '06-troubleshooting.md'],
    ].filter(([label]) => label !== current);
    return `*Part of the ${name} manual — see also ${docs.map(([l, f]) => `[${l}](${f})`).join(', ')}.*`;
  };

  // ---- 1. Introduction ----------------------------------------------------
  const introDoc = [
    `# 1. ${S.intro}`,
    '',
    logoBlock,
    '',
    SEE_ALSO('1. Introduction'),
    '',
    `## 1.1 What ${name} does`,
    '',
    localize(stripHeading(kb.get('Description'))),
    '',
    '## 1.2 Pain points it addresses',
    '',
    localize(stripHeading(kb.get('Why this plugin?'))),
    '',
    '## 1.3 Features',
    '',
    localize(stripHeading(kb.get('Features list'))),
    '',
    footer,
    '',
  ].join('\n');

  // ---- 2. What's New (hand-written, never overwritten) ------------------
  // Hand-written source lives at _whats-new.md (never overwritten by this script);
  // 02-whats-new.md itself is the generated file this section produces — reading it back
  // as an input would duplicate the banner/see-also block on every re-render. Empty/missing
  // source (nothing shipped yet) renders as a placeholder rather than an empty page.
  const whatsNewBody = localize((await readIfPresent(path.join(OUT, '_whats-new.md'))).trim());
  const whatsNewDoc = [
    `# 2. ${S.whatsNew}`,
    '',
    logoBlock,
    '',
    SEE_ALSO("2. What's New"),
    '',
    whatsNewBody || '_Nothing to report yet._',
    '',
    footer,
    '',
  ].join('\n');

  // ---- 3. Scope (which GLPI elements the plugin touches) -------------------
  const impactedNamesOnly = namesOnly(kb.get('Impacted GLPI items'));
  const scopeDoc = [
    `# 3. ${S.scope}`,
    '',
    logoBlock,
    '',
    SEE_ALSO('3. Scope'),
    '',
    'Reference only — see [4. Setup](04-setup.md) for how to configure each of these.',
    '',
    '## 3.1 Assets, management & administration items',
    '',
    // Demote the KB's own nested "### Assets"/"### Management" subheadings to bold labels —
    // they're one level too deep to be their own numbered heading under 3.1.
    localize((stripHeading(kb.get('Impacted GLPI items')) || impactedNamesOnly).replace(/^###\s+(.+)$/gm, '**$1**')),
    '',
    '## 3.2 Automatic actions',
    '',
    namesOnly(kb.get('Automatic Actions')) || '_None._',
    '',
    `See [4.2 Configuration](04-setup.md${anchor('4.2 Configuration')}).`,
    '',
    '## 3.3 Notifications',
    '',
    localize(stripHeading(kb.get('Notifications'))) || '_None._',
    '',
    '## 3.4 Rules',
    '',
    localize(stripHeading(kb.get('Rules'))) || '_None._',
    '',
    '## 3.5 Permissions',
    '',
    namesOnly(kb.get('Permissions')) || '_None._',
    '',
    `See [4.3 Permissions](04-setup.md${anchor('4.3 Permissions')}).`,
    '',
    footer,
    '',
  ].join('\n');

  // ---- 4. Setup -------------------------------------------------------------
  const automaticActionsBlock = localize(stripHeading(kb.get('Automatic Actions')));
  const setupParts = [
    `# 4. ${S.setup}`,
    logoBlock,
    SEE_ALSO('4. Setup'),
    '## 4.1 Installation',
    localize((stripHeading(kb.get('Setup')).match(/### Installation\n([\s\S]*?)(?=\n### |$)/)?.[1] ?? '').trim()),
    '## 4.2 Configuration',
    localize((stripHeading(kb.get('Setup')).match(/### Configuration\n([\s\S]*?)(?=\n### |$)/)?.[1] ?? '').trim()),
  ];
  if (automaticActionsBlock) setupParts.push('### 4.2.1 Automatic actions', automaticActionsBlock);
  setupParts.push(
    '## 4.3 Permissions',
    localize(stripHeading(kb.get('Permissions'))),
    ...setupChapters.map(({ m, assetDir }, i) => renderChapter(m, assetDir, `4.${4 + i}`, localize)),
    footer,
  );
  const setupDoc = setupParts.join('\n\n');

  // ---- 5. Usage ---------------------------------------------------------
  // "## 5.1 How to use" only appears when the KB doc actually has content for it — an
  // empty heading with nothing under it (docs/kb/*.md has no "## How to use" section) reads
  // as broken, not merely brief, so it's dropped entirely and the chapter steps take over
  // the numbering from 5.1 instead of 5.2.
  const howToUse = localize(stripHeading(kb.get('How to use')));
  const usageDoc = [
    `# 5. ${S.usage}`,
    '',
    logoBlock,
    '',
    SEE_ALSO('5. Usage'),
    '',
    ...(howToUse ? ['## 5.1 How to use', '', howToUse, ''] : []),
    ...usageChapters.map(({ m, assetDir }, i) => renderChapter(m, assetDir, `5.${i + (howToUse ? 2 : 1)}`, localize)),
    '',
    footer,
    '',
  ].join('\n');

  // ---- 6. Troubleshooting (hand-written, never overwritten) ------------
  // Hand-written source lives at _troubleshooting.md (never overwritten by this script);
  // 06-troubleshooting.md itself is the generated file this section produces — reading it
  // back as an input would duplicate the banner/see-also block on every re-render.
  const troubleshootingBody = localize((
    await readIfPresent(path.join(OUT, '_troubleshooting.md'))
    || await readIfPresent(path.join(OUT, '4-troubleshooting.md')) // legacy pre-split filename
  ).replace(/^##\s+Troubleshooting\s*\n/, '').trim());
  const troubleshootingDoc = [
    '# 6. Troubleshooting',
    '',
    logoBlock,
    '',
    SEE_ALSO('6. Troubleshooting'),
    '',
    troubleshootingBody,
    '',
    footer,
    '',
  ].join('\n');

  const files = {
    '01-intro.md': introDoc,
    '02-whats-new.md': whatsNewDoc,
    '03-scope.md': scopeDoc,
    '04-setup.md': setupDoc,
    '05-usage.md': usageDoc,
    '06-troubleshooting.md': troubleshootingDoc,
  };

  for (const [filename, content] of Object.entries(files)) {
    const target = path.join(OUT, filename);
    await fs.writeFile(target, content.replace(/\n{3,}/g, '\n\n').trimEnd() + '\n', 'utf8');
  }

  await renderKbDoc(kbPath, kbRawForKb, glossary);

  const shots = manifests.reduce((n, m) => n + m.steps.reduce((k, s) => k + s.shots.length, 0), 0);
  console.log(`${OUT}: ${Object.keys(files).length} files (${Object.keys(files).map((f) => f.replace(/\.md$/, '')).join(', ')}), ${manifests.length} chapters, ${shots} screenshots, ${LOCALE}`);
};

main().catch((err) => {
  console.error(String(err.message ?? err));
  process.exit(1);
});
