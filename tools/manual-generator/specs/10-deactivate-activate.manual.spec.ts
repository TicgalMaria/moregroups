import { test, expect } from '../lib/manual-recorder';
import { login, routes } from '../lib/glpi';
import { GROUP, MEMBERS, ALREADY_DEACTIVATED } from './fixtures';

test('Deactivating and reactivating a group member', async ({ manual, page }) => {
  await login(page, manual.locale);

  manual.about({
    slug: 'deactivate-activate',
    title: 'Deactivating and reactivating a group member',
    intro:
      'More Groups adds a **Deactivated users** panel to every Group\'s Users tab. ' +
      'Deactivating a member removes them from the active member list without deleting ' +
      'their membership details — their manager, dynamic and delegatee flags are kept and ' +
      'restored automatically when they are reactivated.',
  });

  await manual.step('open', 'Open the group and its Users tab',
    'Open the **' + GROUP.name + '** group and switch to its **Users** tab. The active ' +
    'members are listed at the top; the **Deactivated users** panel appears underneath, ' +
    'showing anyone already deactivated for this group.',
    async () => {
      await page.goto(routes.group(GROUP.id));
      await page.getByRole('tab', { name: /^Users/ }).click();
      await page.getByText('Deactivated users').waitFor({ state: 'visible', timeout: 30_000 });

      const panel = page.locator('.card.m-n2', { has: page.locator('.card-title', { hasText: 'Deactivated users' }) });
      await expect(panel).toContainText(ALREADY_DEACTIVATED.display);
      await manual.shot('users-tab', { target: page.locator('.card-tabs') });
    });

  await manual.step('deactivate', 'Deactivate a member',
    'Next to an active member\'s row, click the deactivate button. The member disappears ' +
    'from the active list and appears in the **Deactivated users** panel below, with their ' +
    'Manager/Dynamic/Delegatee flags carried over.',
    async () => {
      const activeRow = page.locator(`tr[data-itemtype="Group_User"][data-id="${MEMBERS.toDeactivate.groupUserId}"]`);
      await expect(activeRow).toContainText(MEMBERS.toDeactivate.display);
      await manual.shot('active-members', {
        target: page.locator('table').filter({ has: activeRow }).first(),
        caption: 'Active members before deactivation',
      });

      await activeRow.locator('button[title="Deactivate user"]').click();
      const panel = page.locator('.card.m-n2', { has: page.locator('.card-title', { hasText: 'Deactivated users' }) });
      await expect(panel).toContainText(MEMBERS.toDeactivate.display, { timeout: 30_000 });
      await manual.shot('deactivated-panel', { target: panel, caption: 'The member now listed as deactivated' });
    });

  manual.note('Re-adding the same user through GLPI\'s native "Add user to group" form clears their stale deactivated record automatically.');

  await manual.step('activate', 'Reactivate the member',
    'In the **Deactivated users** panel, click the activate button on the member\'s row. ' +
    'They disappear from the panel and reappear in the active members list with the same ' +
    'flags they had before.',
    async () => {
      const panel = page.locator('.card.m-n2', { has: page.locator('.card-title', { hasText: 'Deactivated users' }) });
      const deactivatedRow = panel.locator('tr', { hasText: MEMBERS.toDeactivate.display });
      await deactivatedRow.locator('button[title="Activate user"]').click();

      const membersTable = page.locator('table').filter({ hasText: MEMBERS.toDeactivate.display }).first();
      await expect(membersTable).toBeVisible({ timeout: 30_000 });
      await manual.shot('reactivated', {
        target: page.locator('.card-tabs'),
        caption: 'The member restored to the active list',
      });
    });
});
