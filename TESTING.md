# Testing (QA acceptance)

Human QA checklist for **More Groups**. Run on a GLPI 11 instance with the
plugin installed and activated. For each row, mark the **Result** column
**OK** or **KO** only, and use **Comments** for any error message, screenshot
reference, or note on what went wrong.

| # | Check | Steps | Expected | Result | Comments |
|---|-------|-------|----------|--------|----------|
| 1 | Plugin installs | Marketplace/Plugins page → install More Groups | Installs without error, no leftover message | | |
| 2 | Plugin activates | Plugins page → activate More Groups | Activates without error | | |
| 3 | Deactivated users panel appears | Open any **Group** → **Users** tab | A "Deactivated users" panel is visible below the members table | | |
| 4 | Panel is empty by default | On a group with no deactivated members | Panel shows no rows | | |
| 5 | Deactivate one member | On a group's Users tab, click the deactivate button next to one member | Member disappears from the active list and appears in "Deactivated users" | | |
| 6 | Deactivated member keeps their flags | Deactivate a member marked as Manager | The deactivated-users row shows the Manager column checked | | |
| 7 | Activate a deactivated member | In the "Deactivated users" panel, click the activate button on one row | Member disappears from the panel and reappears in the active members list, with the same Manager/Dynamic/Delegatee flags as before | | |
| 8 | Re-adding a user natively clears the stale record | Deactivate a member, then use GLPI's own "Add user to group" to add them back | Member appears as active only; no duplicate/stale row remains in "Deactivated users" | | |
| 9 | Natively adding a user who is already listed as deactivated | Deactivate a member of group A, then go to a **different** group B's Users tab and use GLPI's native "Add user to group" to add that same user to group B | User appears as an active member of group B; that user's deactivated record for group A is untouched (still listed there) | | |
| 10 | Massive action: deactivate | On the Users tab, select several members → massive action "Deactivate users" | All selected members move to "Deactivated users" | | |
| 11 | Massive action: activate | On the "Deactivated users" panel, select several rows → massive action "Activate users" | All selected members return to the active list | | |
| 12 | No update/clone massive action offered | On the "Deactivated users" panel's massive action list | No "Update" or "Clone" option is offered for this itemtype | | |
| 13 | Rights are enforced | Log in as a profile without the Group_User update right, try to deactivate/activate | Action is refused (access denied), nothing changes | | |
| 14 | Entity isolation | Log in as a user restricted to one entity, try to act on a group in a different entity (e.g. via direct URL/id) | Action is refused, nothing changes | | |
| 15 | Plugin uninstalls cleanly | Deactivate then uninstall the plugin | No error; the plugin's database table is removed | | |

## Known limitations
- The "Deactivated users" panel is only shown on the **Group** item's Users
  tab — there is no global cross-group list of deactivated memberships.
- Deactivating/activating does not send any notification; this is by design.
