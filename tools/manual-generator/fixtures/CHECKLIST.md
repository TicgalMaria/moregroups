# Golden fixture checklist — moregroups

Build this state once by hand, then `tools/manual-generator/fixtures/dump.sh`. Everything
here exists to be photographed, so choose values that read as examples in any language and
that exercise the cases the manual needs to explain.

## 1. Documentation users (one per locale) *(keep)*

The reason for these is Trap 4: GLPI renders in the **user profile's** language, so the
locale of a screenshot is decided at login, not by Playwright.

| Login | Language | Surname / First name | Profile |
|---|---|---|---|
| `manual_en` | English (UK) | Documentation / Manual | Super-Admin |
| `manual_es` | Español (España) | Documentación / Manual | Super-Admin |

- Password: the `DOC_USER_PASS` value from `manual.env` (`ManualDocs2026`).
- `bin/console user:create` assigns **Self-Service** as the new user's *default* profile,
  even when `user:grant` also grants Super-Admin. Since GLPI logs the session into the
  default profile, this silently produced "Access denied" on the Group form until fixed
  directly on `glpi_profiles_users`: delete the Self-Service row and set
  `is_default_profile=1` on the Super-Admin row for that user.
- Set a neutral display name — the header appears in nearly every screenshot.

## 2. The Support Team group and its members *(keep — this plugin's actual fixture)*

One `Group` (id 1, `Support Team`, entity 0) with members chosen to exercise every state
the **Deactivated users** panel needs to show:

| User | Role in the fixture | `Group_User`/tracking id | Flags |
|---|---|---|---|
| `alice.martin` (Martin Alice) | active manager, never touched by the spec | `Group_User` id 1 | `is_manager=1` |
| `bruno.silva` (Silva Bruno) | active member the spec deactivates then reactivates | `Group_User` id 2 → `PluginMoregroupsGroup` and back | none |
| `carla.dubois` (Dubois Carla) | active bystander, confirms deactivating one member doesn't touch others | `Group_User` id 3 | none |
| `diego.fernandez` (Fernández Diego) | already deactivated before the spec runs, so the panel has content on the very first screenshot | `PluginMoregroupsGroup` id 1 | none |

`display` values in `tools/manual-generator/specs/fixtures.ts` are GLPI's own "Lastname
Firstname" rendering, confirmed against the running fixture — not derived from the login
name.

## 3. `url_base` must match `BASE_URL` *(keep — easy to lose on a rebuild)*

`glpi_configs` (context `core`, name `url_base`) defaults to whatever hostname the browser
used to access GLPI's install wizard — for a fresh container that's `http://localhost`, not
`http://glpi`. GLPI compares the request's `Origin` header against this value on POSTs
(login included) and silently rejects with a generic "Incorrect username or password" if
they don't match — curl doesn't send `Origin` by default, so this only shows up under a real
browser. Set it explicitly to match `BASE_URL` in `manual.env`:

```sql
UPDATE glpi_configs SET value='http://glpi' WHERE context='core' AND name='url_base';
```

then `bin/console cache:clear` before dumping.

## 4. The plugin auto-deactivates on a version bump *(keep)*

If the GLPI container bind-mounts this repo's live working tree (ours does, via the
`fuse-overlayfs` merge at `~/containers/all-plugins/`, run once with
`~/dev/glpi-plugins/mount-plugins-folder.sh`), then any commit that changes `setup.php`'s
version define while the container is already running makes GLPI deactivate the plugin on
its next boot. `restore.sh`'s bundled install+activate step (Trap 9) handles this
automatically on every restore.

## 5. Tidy up before dumping *(keep)*

- Empty the trashbin, so deleted-item counts don't appear in list headers.
- Clear notifications / the alert count in the top bar.
- Check the GLPI footer version matches what you'll stamp as `GLPI_VERSION` in `manual.env`.
- One last pass for anything real: client names, internal hostnames, your own email.

## 6. When to regenerate the fixture *(keep)*

Only for a plugin schema migration or genuinely new demo data. Treat it as a deliberate
commit of its own, because it rewrites every screenshot in the repo and you want that diff
reviewable in isolation.
