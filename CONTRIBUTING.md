# Contributing to 0GLPIXO

## Branch policy

`develop`, `main` and `master` are protected by an **organisation ruleset**
(`proteger-ramas-principales-web`), not by a setting of this repository. It
applies automatically and cannot be turned off from here. It forbids:

- direct pushes: every change goes in through a pull request,
- merging without **1 approval**,
- force-pushing and deleting those branches.

In practice: **never commit on `develop`**. If you try, the push is rejected and
you will have to move the commits onto a separate branch.

### Naming

| Prefix | For |
|---|---|
| `feature/<description>` | new functionality or a change in behaviour |
| `bugfix/<description>` | fixing a defect |

The ruleset does **not** validate branch names: this is a team convention. It
does have a real effect though, because continuous integration only triggers on
branches with these prefixes. A branch named otherwise breaks nothing, it simply
skips the checks until you open the pull request.

## Workflow

```bash
git switch develop
git pull --ff-only
git switch -c feature/my-change

# ... work, commits ...

git push -u origin feature/my-change
gh pr create --base develop --fill
```

The pull request needs 1 approval. If the base branch moved while you worked,
update with `git pull --rebase origin develop` before asking for review.

## Checks before requesting review

```bash
composer install           # first time only
bash tools/phpstan.sh      # static analysis
bash tools/php-cs-fixer.sh # applies the code style
bash tools/codesniffer.sh  # applies the PHPCS fixes
```

Run them **before** pushing, not after the CI fails. The four checks (PHPCS,
PHP-CS-Fixer, PHPStan and TwigCS) are sensitive to details that are easy to miss
— a trailing comma on the last argument of a multiline call, the spacing inside
Twig hashes — and that IDE diagnostics do not cover.

If you touch Twig templates, two rules the CI enforces and the IDE does not warn
about:

- no padding inside a hash: `{key: value}`, never `{ key: value }`,
- ternaries on a single line: `condition ? a : b`.

## Continuous integration

Every pull request against `develop` or `main` runs five workflows, all delegated
to `TICGAL-Dev/.github-private`:

| Workflow | What it checks |
|---|---|
| `static-analysis-scan` | static analysis over the plugin code |
| `secrets-scan` | leaked credentials in the diff |
| `dependency-scan` | vulnerabilities in the dependencies |
| `ticgal-constraints` | internal conventions |
| `localazy-sync` | translation synchronisation |

All of them can be triggered manually from the *Actions* tab. Note that they
**filter by base branch**: a pull request aimed at anything other than `develop`
or `main` does not trigger them, and the absence of checks does not mean they
passed.

## Translations

Every user-facing string goes through `__('text', '0GLPIxx')`. To regenerate the
catalogue after adding new strings:

```bash
bash tools/extract_template.sh
```

## Releasing

```bash
bash tools/make_release.sh 1.0.0
```

The script requires the version to appear literally in `setup.php`, to follow
semantic versioning, and **nothing left uncommitted** (it uses
`git checkout-index`). It leaves the `.tar.bz2` in `/tmp`, already stripped of
`tools/`, the CI configuration and the development dependencies.

Before releasing, bump the version in `setup.php` and add the matching entry to
`CHANGELOG.md`.
