# 0GLPIXO
GLPI Plugin

![Version](https://img.shields.io/github/v/release/TICGAL-Dev/0GLPIxx)
![License](https://img.shields.io/github/license/TICGAL-Dev/0GLPIxx)
![Issues](https://img.shields.io/github/issues/TICGAL-Dev/0GLPIxx)
![Pull Requests](https://img.shields.io/github/issues-pr/TICGAL-Dev/0GLPIxx)
![Last Commit](https://img.shields.io/github/last-commit/TICGAL-Dev/0GLPIxx)
![Project Status](https://img.shields.io/badge/status-active-brightgreen)

## Features
- It has 👍

## Table of contents
- Instalation
- Configuration
- Use

---

<!-- bootstrap-note:start -->
> **Just created a repository from this template?** Start with
> [`GETTING_STARTED.md`](GETTING_STARTED.md): the step-by-step roadmap, including
> the right order (create the branch **before** renaming).
> For the day-to-day, see [`CONTRIBUTING.md`](CONTRIBUTING.md).

---
<!-- bootstrap-note:end -->


## Starting from this template

```bash
tools/rename_plugin.sh                  # key taken from the directory
tools/rename_plugin.sh "Leave Manager"  # + display name
```

**The plugin key is the directory name**, which is already the name of the
repository you cloned, so there is nothing to type and nothing that can disagree
with it. The single argument is optional and only sets the name shown in the
plugin list, where spaces are allowed; it defaults to the capitalised key.

If the directory is not usable as a key (uppercase, hyphens, dots) the script
stops and tells you what to rename. Renaming it is not cosmetic: GLPI derives
from it

- the PSR-4 namespace, `GlpiPlugin\Myplugin\` mapped to `src/`,
- the Twig namespace, `@myplugin/...` mapped to `templates/`,
- the route prefix, `/plugins/myplugin/...`.

A directory whose name does not match the placeholder replacement will silently
fail to autoload the plugin's classes.

## Layout

| Path | Contents |
|---|---|
| `setup.php` | version, requirements and `$PLUGIN_HOOKS` registration |
| `hook.php` | install/uninstall entry points; keep business logic in `src/` |
| `src/` | all classes, namespace `GlpiPlugin\Myplugin\` |
| `templates/` | Twig views, addressed as `@myplugin/pages/x.html.twig` |
| `front/` | page entry points |
| `ajax/` | asynchronous endpoints |
| `locales/` | translations, extracted with `tools/extract_template.sh` |

GLPI 11 registers the PSR-4 namespace for `src/` on its own, so the plugin's
`vendor/autoload.php` is never loaded by the core. The entry in `composer.json`
exists for the tooling (PHPStan, IDE, PHP-CS-Fixer), not for runtime.

### `front/` is optional for an itemtype

GLPI 11 resolves `/plugins/myplugin/front/<itemtype>[.form].php` to its generic
controllers (`GenericListController`, `GenericFormController`,
`DropdownFormController`) even when the file does not exist, as long as the class
resolves as `GlpiPlugin\Myplugin\<Itemtype>`. The `front/dropdown.php` and
`front/dropdown.form.php` shipped here are kept as an explicit example; a plain
CRUD itemtype does not need them.

## Development

```bash
composer install
bash tools/phpstan.sh        # static analysis
bash tools/php-cs-fixer.sh   # apply code style
bash tools/codesniffer.sh    # apply PHPCS fixes
```
