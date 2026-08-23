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
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Moregroups\Controller\GroupActionController;
use Group_User;
use PluginMoregroupsGroup;
use Symfony\Component\HttpFoundation\Request;

class GroupActionControllerTest extends DbTestCase
{
	public function testMissingParametersAreBadRequest(): void
	{
		$this->login();

		$controller = new GroupActionController();
		$request = new Request([], ['rowaction' => '', 'rowid' => '']);

		$this->expectException(BadRequestHttpException::class);
		$controller($request);
	}

	public function testActivateWithoutRightsIsDenied(): void
	{
		$this->login();

		$group = $this->createItem('Group', [
			'name'        => 'moregroups-ctrl-test-' . uniqid(),
			'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
		]);
		$user_id = getItemByTypeName('User', TU_USER, true);

		$tracked = $this->createItem('PluginMoregroupsGroup', [
			'groups_id' => $group->getID(),
			'users_id'  => $user_id,
		]);

		// Strip the "group" right from the active profile in-session (the
		// standard GLPI core test pattern for exercising a rights check)
		// rather than needing a dedicated low-rights fixture user.
		$_SESSION['glpiactiveprofile']['group'] = 0;

		$controller = new GroupActionController();
		$request = new Request([], ['rowaction' => 'activate', 'rowid' => (string) $tracked->getID()]);

		$this->expectException(AccessDeniedHttpException::class);
		$controller($request);
	}

	public function testActivateRestoresGroupUserRow(): void
	{
		$this->login();

		$group = $this->createItem('Group', [
			'name'        => 'moregroups-ctrl-test-' . uniqid(),
			'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
		]);
		$user_id = getItemByTypeName('User', TU_USER, true);

		$tracked = $this->createItem('PluginMoregroupsGroup', [
			'groups_id' => $group->getID(),
			'users_id'  => $user_id,
		]);

		$controller = new GroupActionController();
		$request = new Request(
			[],
			['rowaction' => 'activate', 'rowid' => (string) $tracked->getID()],
			[],
			[],
			[],
			['HTTP_REFERER' => '/front/group.form.php?id=' . $group->getID()]
		);

		$response = $controller($request);

		$this->assertSame(302, $response->getStatusCode());

		$restored = new Group_User();
		$this->assertTrue($restored->getFromDBByCrit([
			'groups_id' => $group->getID(),
			'users_id'  => $user_id,
		]));

		$leftover = new PluginMoregroupsGroup();
		$this->assertFalse($leftover->getFromDB($tracked->getID()));
	}
}
