## Description

**More Groups** adds a **Deactivated users** panel to every Group's Users tab in GLPI. It lets you take a member out of a group's active roster without deleting their membership record, and put them back with one click, with their original flags intact.

<!-- shot: deactivate-activate/01-users-tab -->

## Why this plugin?

GLPI's native way to remove a user from a group is to delete the `Group_User` link outright, which discards whether that member was a manager, a dynamic (LDAP-synced) member, or a delegatee. Teams that temporarily pause a member's involvement in a group — a parental-leave cover, a seasonal support rotation, a contractor between assignments — end up either leaving stale memberships in place or losing that context when they remove and later re-add the person. More Groups keeps the membership on file, just marked inactive, so reactivating it is a single click that restores exactly what was there before.

This fits on-call rosters, shift rotations and other groups whose membership changes on a schedule or by hand rather than through LDAP/AD sync — a dynamic group tied to an external source doesn't need this (removing someone there is the sync's job), but a plain GLPI group used to track "who's on call this week" or "who's covering this shift" does. The point isn't to build scheduling into GLPI: it's to let whoever manages that roster add or remove a name in a couple of clicks, on the group's own Users tab, with no separate integration or sync job to configure and no risk of losing a manager/delegatee flag along the way.

## Features list

- A **Deactivated users** panel on every Group's Users tab, listing members deactivated for that group
- One-click deactivate/activate per member, preserving the Manager, Dynamic and Delegatee flags across the round trip
- Massive actions to deactivate or activate several members at once
- Re-adding a deactivated user through GLPI's own "Add user to group" form automatically clears their stale deactivated record
- Rights and entity isolation enforced through GLPI's native `Group_User` update right — no separate permission to manage

<!-- shot: deactivate-activate/03-deactivated-panel -->

## Impacted GLPI items

### Assets, management & administration items

- **Group** — gains the Deactivated users panel on its Users tab; no other change to the Group form itself
- **Group_User** — the native membership record; deactivating one moves it out to the plugin's own tracking table and back

## Setup

### Installation

Standard plugin install: drop it in `plugins/`, install and activate from **Setup > Plugins**.

### Configuration

There is nothing to configure. Once activated, the **Deactivated users** panel appears automatically on every Group's Users tab, for every user who can see that tab.

<!-- shot: deactivate-activate/02-active-members -->

<!-- shot: deactivate-activate/04-reactivated -->

## Usage

On a Group's **Users** tab: click the eye-off icon next to an active member to deactivate them, or the eye icon on a row in the **Deactivated users** panel to reactivate. Also available as massive actions ("Deactivate users" / "Activate users") on both tables.

Deactivating moves the member's `Group_User` row into the plugin's own `glpi_plugin_moregroups_groups` table (preserving `is_manager`/`is_dynamic`/`is_userdelegate`); activating moves it back. Re-adding the same user via GLPI's native "add user" control on the tab auto-clears any stale deactivated row for them (`plugin_moregroups_group_user_add` hook on `Group_User::item_add`).

## Permissions

No dedicated right is added. Deactivating or activating a member requires the native **Group_User: Update** right, and access to the member's group is still subject to GLPI's normal entity restrictions — enforced by a `Group::can($groups_id, READ)` check on the target group, in both the massive action path (`inc/group.class.php`) and the Symfony controller (`src/Controller/GroupActionController.php::canAccessGroup()`).

## Automatic Actions

None.

## Notifications

None — deactivating or activating a member does not send any notification, by design.

## Rules

None.

## Troubleshooting

**Buttons don't show up** — account lacks `Group_User: Update`. Panel itself still renders (read-only) if they can see the tab at all.

**Click does nothing / "Access denied"** — `Group::can($groups_id, READ)` failed: entity restriction on the group changed, or the group moved entities. Not a plugin bug, just the entity check doing its job.

**A member vanished from both tables** — expected: someone used GLPI's native "add user" control instead of the activate button, which triggers the cleanup hook. Working as intended.

**Panel doesn't appear at all** — check `glpi:plugin:list`; a `setup.php` version bump can silently deactivate the plugin if the container's plugin table is out of sync with the code on disk. `glpi:plugin:install` + `glpi:plugin:activate` fixes it.

**No notifications** — by design, not a bug.

## Known limitations

- Deactivated-users view is per-Group only; no cross-group list.
- `uninstall()` (raw `DROP TABLE`) isn't covered by the PHPUnit suite — `DbTestCase`'s per-test transaction can't survive DDL's implicit commit. Verified manually instead (`TESTING.md` #15).
