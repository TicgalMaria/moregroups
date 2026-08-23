# tools/manual-generator — how the manual is built

This directory builds the user manual, published to `docs/manual/<locale>/` as six numbered,
cross-linked files (`01-intro.md`, `02-whats-new.md`, `03-scope.md`, `04-setup.md`,
`05-usage.md`, `06-troubleshooting.md`). The manual itself is a build artifact; the sources are
the narrated Playwright specs in `specs/`, an optional `docs/kb/*.md` doc (auto-pulled into
Intro/Scope/Setup if present), and two hand-written files per locale directory under
`docs/manual/` — `_whats-new.md` and `_troubleshooting.md` — kept at that leading-underscore
name specifically so they never collide with the generated `02-whats-new.md`/
`06-troubleshooting.md` sitting next to them.

Dev-only: this whole directory is excluded from the plugin package. The manual files in
`docs/manual/<locale>/` are published to the repo and synced to BookStack. See

## One-time setup

```bash
npm i -D @playwright/test          # then pin it: no ^ in package.json
cp tools/manual-generator/manual.env.example tools/manual-generator/manual.env   # if you keep a template
$EDITOR tools/manual-generator/manual.env     # fill in the five CONFIRM values
```

Build the demo data by hand following `fixtures/CHECKLIST.md`, then freeze it:

```bash
npm run manual:fixture:dump
```

## Every run

```bash
npm run manual                     # restore fixture → capture → render the 6 manual files
```

`npm run manual` is idempotent. On unchanged UI it produces byte-identical PNGs, so:

```bash
git status --short docs/manual     # empty = the UI didn't change
```

A screenshot diff you didn't expect is one of three things — a real UI change (update the
prose in the spec too), a determinism leak (fix it, don't just commit the new PNG), or a
regression. That is the second job this pipeline does.

## package.json

```json
{
  "scripts": {
    "manual": "npm run manual:fixture:restore && npm run manual:capture && npm run manual:render",
    "manual:capture": "tools/manual-generator/run.sh",
    "manual:render": "node tools/manual-generator/render-manual.mjs",
    "manual:fixture:dump": "tools/manual-generator/fixtures/dump.sh",
    "manual:fixture:restore": "tools/manual-generator/fixtures/restore.sh",
    "manual:all": "for l in en_GB es_ES; do MANUAL_LOCALE=$l npm run manual; done"
  },
  "devDependencies": {
    "@playwright/test": "PIN-EXACT-VERSION"
  }
}
```

Pin `@playwright/test` to an exact version. Two reasons: the container image tag must match
it exactly or Chromium isn't found, and browser rendering shifts between Playwright releases
would rewrite every screenshot in the repo.

## .gitignore

```gitignore
docs/manual/*/.manifests/
tools/manual-generator/manual.env
tools/manual-generator/test-results/
tools/manual-generator/playwright-report/
```

Manifests are intermediates. `manual.env` is machine-specific — commit a
`manual.env.example` instead. **Do** commit the PNGs and the 6 generated manual files:
reviewers need to see documentation changes in the pull request, and the repo doubles as
the published manual.

## Adding a chapter

1. New spec in `specs/`, numbered with a gap: `40-webhooks.manual.spec.ts`.
2. Import the demo data from `specs/fixtures.ts` — never inline a literal that the fixture
   also defines, and never generate one.
3. Narrate with `manual.step(id, title, prose, fn)`; capture with `manual.shot()`, scoped to
   a locator rather than the full page.
4. `npm run manual`, then read the rendered chapter as a client would.

## Layout

```
tools/manual-generator/
  manual.env            stack-specific values (gitignored); every script sources it
  run.sh                capture in a version-matched Playwright container
  render-manual.mjs     manifests → 01-intro.md/02-whats-new.md/03-scope.md/04-setup.md/05-usage.md/06-troubleshooting.md
  lib/                  manual-recorder.ts (the fixture), glpi.ts (login, routes, masking)
  specs/                narrated scenarios + fixtures.ts
  fixtures/             golden.sql.gz, dump.sh, restore.sh, CHECKLIST.md

docs/manual/<locale>/   _troubleshooting.md (hand-written); 0{1,2,3,4}-*.md, assets/, .manifests/ (generated)
```
