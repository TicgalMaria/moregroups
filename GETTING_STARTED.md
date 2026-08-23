# Getting started

Roadmap to turn this template into a working plugin. Read once.

> **This file deletes itself.** `tools/rename_plugin.sh` removes it when it
> finishes, because from that point on it describes a state that no longer
> exists. If you need it again, it lives in the template repository on GitHub.

---

## ⚠️ First of all: create your branch

**Create your working branch before running the rename.** The rename touches
almost every file in the repository and produces a huge diff. If you run it while
on `develop` you will not be able to commit it — that branch requires a pull
request with an approval — and you will have to redo the work or move the commits
by hand.

```bash
git switch -c feature/initialise-plugin
```

See `CONTRIBUTING.md` for the full branch policy.

---

## 1. Put the repository inside your GLPI instance

The directory has to sit inside your GLPI `plugins/` folder **before** you rename
anything, and its name is what becomes the plugin key.

```bash
cd <your-glpi>/plugins
git clone <repository-url>
cd <plugin-key>
```

## 2. Rename

```bash
tools/rename_plugin.sh                # key comes from the directory
tools/rename_plugin.sh "My Plugin"    # + display name
```

**The plugin key is the directory name**, which is already the name of the
repository you cloned. You do not type it, so it cannot disagree with the
repository. The single argument is optional and only changes the name shown in
the plugin list; it accepts spaces and accents. Omit it and you get the
capitalised key.

If the directory is not usable as a key (uppercase, hyphens, dots) the script
stops and tells you what to rename — GLPI only accepts lowercase letters and
digits. When the repository is named differently, clone it under the right name:
`git clone <url> <plugin-key>`.

Run it **from the plugin directory**. The script:

- replaces the four placeholders with your plugin's variants,
- turns `setup_template.php` into `setup.php`,
- injects the license headers,
- warns if any placeholder survived,
- deletes this file.

### Why the directory name is not cosmetic

GLPI derives the **plugin key** from it, and three things come from that key:

| Derived | Example for `myplugin` |
|---|---|
| PSR-4 namespace | `GlpiPlugin\Myplugin\` over `src/` |
| Twig namespace | `@myplugin/pages/x.html.twig` |
| Route prefix | `/plugins/myplugin/...` |

If the directory does not match, classes fail to autoload and the error does not
explain why.

## 3. Dependencies

```bash
composer install
```

Only needed for the tooling (PHPCS and the PHPStan rules). **It is not used at
runtime**: GLPI registers the PSR-4 namespace for `src/` on its own and never
loads the plugin's `vendor/autoload.php`. The `composer.json` entry exists for
static analysis and your IDE.

## 4. Install into GLPI

```bash
C=$(docker ps --format '{{.Names}}' | grep -i glpi | grep -vi mariadb)
docker exec $C php /var/www/html/glpi/bin/console plugin:install <plugin-key> --allow-superuser -n
docker exec $C php /var/www/html/glpi/bin/console plugin:activate <plugin-key> --allow-superuser -n
```

## 5. What already works

This is not a dead skeleton. Freshly installed, the plugin ships:

- two tables (`glpi_plugin_<key>_configs` with its seed row, and `..._dropdowns`),
- the plugin right on every profile, granted to the super-admin,
- a configuration tab under Setup → General,
- a dropdown CRUD page at `/plugins/<key>/front/dropdown.php`,
- its own tab on each Profile, with the rights matrix.

The install deliberately shows a "table not configured" warning: it is the
reminder that the tables do not have your own columns yet.

## 6. Make it yours

1. Rename or delete the sample classes in `src/`. `Dropdown` is an example;
   `Config` and `Profile` usually stay.
2. Declare your real columns in `Config::install()` and `Dropdown::install()`,
   using the `Traits\InstallableTable` trait.
3. Uncomment the fields in `templates/pages/config.html.twig`.
4. **Remove the `Session::addMessageAfterRedirect(... WARNING)`** calls from the
   `install()` methods.
5. Keep `plugin_<key>_installable_classes()` in `hook.php` up to date as you add
   classes owning a table: order matters if there are foreign keys, and the
   uninstall walks the list backwards.
6. Register the hooks you need in `setup.php`.
7. Set the version in `setup.php`, `CHANGELOG.md` and `README.md`.

### Layout

| Path | Contents |
|---|---|
| `setup.php` | version, requirements and `$PLUGIN_HOOKS` registration |
| `hook.php` | install entry points; the logic belongs in `src/` |
| `src/` | every class, namespace `GlpiPlugin\<Key>\` |
| `templates/` | Twig views, addressed as `@<key>/pages/x.html.twig` |
| `front/` | page entry points |
| `ajax/` | asynchronous endpoints |
| `locales/` | translations |

In GLPI 11 the `front/*.php` files of an itemtype are **optional**: the core
routes `/plugins/<key>/front/<itemtype>[.form].php` to its generic controllers
even when the file does not exist, as long as the class resolves. The two
`dropdown` ones are kept as an explicit example.

## 7. First pull request

```bash
git add -A
git commit -m "chore: initialise plugin from the template"
git push -u origin feature/initialise-plugin
gh pr create --base develop --fill
```

From here on, the day-to-day is in `CONTRIBUTING.md`.
