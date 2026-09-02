<?php

/*
 -------------------------------------------------------------------------
 More Groups plugin for GLPI
 Copyright (c) 2022-2026 by the TICGAL Team.
 https://www.tic.gal
 -------------------------------------------------------------------------
 LICENSE
 This file is part of the More Groups plugin.
 More Groups plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.
 More Groups plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.
 You should have received a copy of the GNU Affero General Public License
 along with More Groups. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   More Groups
 @author    the TICGAL team
 @copyright Copyright (c) 2022-2026 TICGAL team
 @license   AGPL License 3.0 or (at your option) any later version
				http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://www.tic.gal
 @since     2022
 ----------------------------------------------------------------------
*/

namespace GlpiPlugin\Moregroups\Tests;

use Glpi\Tests\DbTestCase;
use Group;
use Group_User;
use MassiveAction;
use PluginMoregroupsGroup;

class PluginMoregroupsGroupTest extends DbTestCase
{
	private function createGroupWithMember(): array
	{
		$group = $this->createItem('Group', [
			'name'        => 'moregroups-test-' . uniqid(),
			'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
		]);

		$user_id = getItemByTypeName('User', TU_USER, true);

		$group_user = $this->createItem('Group_User', [
			'groups_id'  => $group->getID(),
			'users_id'   => $user_id,
			'is_manager' => 1,
		]);

		return [$group, $group_user, $user_id];
	}

	public function testGetTypeNameIsNotEmpty(): void
	{
		$this->assertNotEmpty(PluginMoregroupsGroup::getTypeName());
	}

	public function testUpdateAndCloneAreForbiddenMassiveActions(): void
	{
		$item = new PluginMoregroupsGroup();
		$forbidden = $item->getForbiddenStandardMassiveAction();

		$this->assertContains('update', $forbidden);
		$this->assertContains('clone', $forbidden);
	}

	public function testActivateMassiveActionIsAdvertised(): void
	{
		// Since the HIGH-severity fix, the action is only advertised to a
		// session holding Group_User::canUpdate() — an anonymous session
		// (no login()) correctly no longer sees it.
		$this->login();

		$item = new PluginMoregroupsGroup();
		$actions = $item->getSpecificMassiveActions();

		$found = false;
		foreach (array_keys($actions) as $key) {
			if (str_ends_with($key, 'activate')) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Activate users massive action should be advertised');
	}

	public function testInstallCreatesTable(): void
	{
		global $DB;

		$this->assertTrue($DB->tableExists(PluginMoregroupsGroup::getTable()));
	}

	public function testDeactivateThenActivateRestoresGroupMembership(): void
	{
		$this->login();

		[$group, $group_user, $user_id] = $this->createGroupWithMember();
		$group_user_id = $group_user->getID();

		$ma = new MassiveAction([
			'action'      => 'deactivate',
			'action_name' => 'Deactivate users',
			'items'       => [Group_User::class => [$group_user_id => 'on']],
		], [], 'process');

		PluginMoregroupsGroup::processMassiveActionsForOneItemtype($ma, new Group_User(), [$group_user_id]);

		$this->assertSame(1, $ma->results['ok']);
		$this->assertSame(0, $ma->results['ko']);

		$remaining_group_user = new Group_User();
		$this->assertFalse(
			$remaining_group_user->getFromDB($group_user_id),
			'The Group_User row must be gone once the member is deactivated'
		);

		global $DB;
		$rows = iterator_to_array($DB->request([
			'FROM'  => PluginMoregroupsGroup::getTable(),
			'WHERE' => [
				'users_id'  => $user_id,
				'groups_id' => $group->getID(),
			],
		]));
		$this->assertCount(1, $rows, 'Deactivating must create exactly one tracking row');
		$deactivated_id = array_key_first($rows);

		$ma2 = new MassiveAction([
			'action'      => 'activate',
			'action_name' => 'Activate users',
			'items'       => [PluginMoregroupsGroup::class => [$deactivated_id => 'on']],
		], [], 'process');

		PluginMoregroupsGroup::processMassiveActionsForOneItemtype($ma2, new PluginMoregroupsGroup(), [$deactivated_id]);

		$this->assertSame(1, $ma2->results['ok']);
		$this->assertSame(0, $ma2->results['ko']);

		$restored = new Group_User();
		$this->assertTrue(
			$restored->getFromDBByCrit([
				'users_id'  => $user_id,
				'groups_id' => $group->getID(),
			]),
			'Activating must restore the Group_User row'
		);
		$this->assertSame(1, (int) $restored->fields['is_manager']);

		$leftover = new PluginMoregroupsGroup();
		$this->assertFalse(
			$leftover->getFromDB($deactivated_id),
			'Activating must delete the tracking row (via the item_add hook)'
		);
	}

	public function testDeactivateInvalidIdIsReportedKO(): void
	{
		$this->login();

		[, $group_user] = $this->createGroupWithMember();
		$group_user_id = $group_user->getID();

		$ma = new MassiveAction([
			'action'      => 'deactivate',
			'action_name' => 'Deactivate users',
			'items'       => [Group_User::class => [$group_user_id => 'on']],
		], [], 'process');

		PluginMoregroupsGroup::processMassiveActionsForOneItemtype($ma, new Group_User(), [999999999]);

		$this->assertSame(
			0,
			$ma->results['ok'],
			'A non-existent Group_User id must be reported as KO, never silently succeed'
		);
		$this->assertSame(1, $ma->results['ko']);
	}

	public function testAddingUserToAnotherGroupDoesNotClearUnrelatedDeactivatedRecord(): void
	{
		$this->login();

		[$deactivated_group, $group_user, $user_id] = $this->createGroupWithMember();
		$group_user_id = $group_user->getID();

		$ma = new MassiveAction([
			'action'      => 'deactivate',
			'action_name' => 'Deactivate users',
			'items'       => [Group_User::class => [$group_user_id => 'on']],
		], [], 'process');

		global $DB;
		PluginMoregroupsGroup::processMassiveActionsForOneItemtype($ma, new Group_User(), [$group_user_id]);

		$other_group = $this->createItem('Group', [
			'name' => 'moregroups-test-other-' . uniqid(),
		]);
		$this->createItem('Group_User', [
			'groups_id' => $other_group->getID(),
			'users_id'  => $user_id,
		]);

		$rows = iterator_to_array($DB->request([
			'FROM'  => PluginMoregroupsGroup::getTable(),
			'WHERE' => [
				'users_id'  => $user_id,
				'groups_id' => $deactivated_group->getID(),
			],
		]));
		$this->assertCount(
			1,
			$rows,
			'Adding the user to a different group must not clear their deactivated record for another group'
		);

		$this->assertTrue((new Group_User())->getFromDBByCrit([
			'groups_id' => $other_group->getID(),
			'users_id'  => $user_id,
		]));
	}

	public function testMassiveActionDeniedWithReadOnlyRight(): void
	{
		$this->login();

		[$group, $group_user, $user_id] = $this->createGroupWithMember();
		$group_user_id = $group_user->getID();

		$tracked = $this->createItem('PluginMoregroupsGroup', [
			'groups_id' => $group->getID(),
			'users_id'  => $user_id,
		]);

		// Downgrade the active profile's "group" right to READ-only, the
		// regression case for the HIGH finding from the external Teclib'
		// security review: a user with only group READ must not be able to
		// add/remove Group_User rows through these massive actions.
		// Group_User::canUpdate() (CommonDBRelation::canRelation()) passes if
		// EITHER linked itemtype's own canUpdate() passes — User's or
		// Group's — so "user" must be stripped too, or the default test
		// session's own admin-level "user" right silently keeps it allowed.
		$_SESSION['glpiactiveprofile']['group'] = READ;
		$_SESSION['glpiactiveprofile']['user'] = READ;

		$ma = new MassiveAction([
			'action'      => 'deactivate',
			'action_name' => 'Deactivate users',
			'items'       => [Group_User::class => [$group_user_id => 'on']],
		], [], 'process');
		PluginMoregroupsGroup::processMassiveActionsForOneItemtype($ma, new Group_User(), [$group_user_id]);

		$this->assertSame(0, $ma->results['ok']);
		$this->assertGreaterThan(0, $ma->results['noright'] ?? 0);
		$this->assertTrue(
			(new Group_User())->getFromDB($group_user_id),
			'The Group_User row must survive a denied deactivate attempt'
		);

		$ma2 = new MassiveAction([
			'action'      => 'activate',
			'action_name' => 'Activate users',
			'items'       => [PluginMoregroupsGroup::class => [$tracked->getID() => 'on']],
		], [], 'process');
		PluginMoregroupsGroup::processMassiveActionsForOneItemtype($ma2, new PluginMoregroupsGroup(), [$tracked->getID()]);

		$this->assertSame(0, $ma2->results['ok']);
		$this->assertGreaterThan(0, $ma2->results['noright'] ?? 0);
		$this->assertTrue(
			(new PluginMoregroupsGroup())->getFromDB($tracked->getID()),
			'The tracking row must survive a denied activate attempt'
		);
	}

	public function testMassiveActionsHiddenWithoutRight(): void
	{
		$this->login();
		$_SESSION['glpiactiveprofile']['group'] = READ;
		$_SESSION['glpiactiveprofile']['user'] = READ;

		$item = new PluginMoregroupsGroup();
		$actions = $item->getSpecificMassiveActions();
		foreach (array_keys($actions) as $key) {
			$this->assertStringNotContainsString(
				'activate',
				$key,
				'activate must not be advertised without Group_User update right'
			);
		}

		$hook_actions = \plugin_moregroups_MassiveActions('Group_User');
		$this->assertSame(
			[],
			$hook_actions,
			'deactivate must not be advertised without Group_User update right'
		);
	}

	// PluginMoregroupsGroup::uninstall() (a DROP TABLE) is not covered here:
	// GLPI's DbTestCase wraps every test in a DB transaction for isolation,
	// and MariaDB implicitly commits on DDL, so running it here would corrupt
	// the transactional rollback for this and subsequent tests. Verified
	// manually per TESTING.md instead.
}
