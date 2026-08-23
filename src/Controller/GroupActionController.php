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

namespace GlpiPlugin\Moregroups\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Group;
use Group_User;
use PluginMoregroupsGroup;
use Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupActionController extends AbstractController
{
	#[Route('/GroupAction', name: 'moregroups_group_action', methods: ['POST'])]
	public function __invoke(Request $request): Response
	{
		$rowaction = $request->request->get('rowaction');
		$rowid     = $request->request->get('rowid');

		if (empty($rowaction) || empty($rowid)) {
			throw new BadRequestHttpException();
		}

		if (!Group_User::canUpdate()) {
			throw new AccessDeniedHttpException();
		}

		if ($rowaction === 'activate') {
			$this->activate((int) $rowid);
		} elseif ($rowaction === 'deactivate') {
			$this->deactivate((int) $rowid);
		}

		return new RedirectResponse($request->headers->get('referer') ?? '/');
	}

	private function canAccessGroup(array $fields): bool
	{
		$group = new Group();
		return $group->can($fields['groups_id'] ?? 0, READ);
	}

	private function activate(int $rowid): void
	{
		$item = new PluginMoregroupsGroup();
		if (!$item->getFromDB($rowid)) {
			Session::addMessageAfterRedirect($item->getErrorMessage(ERROR_NOT_FOUND), false, ERROR);
			return;
		}
		if (!$this->canAccessGroup($item->fields)) {
			throw new AccessDeniedHttpException();
		}

		$input = $item->fields;
		unset($input['id']);
		$group_user = new Group_User();
		if (!$group_user->add($input)) {
			// plugin_moregroups_group_user_add (hook.php) deletes the matching
			// PluginMoregroupsGroup row as soon as the Group_User is added; an
			// explicit delete here would race it and fail against an
			// already-removed row.
			Session::addMessageAfterRedirect($group_user->getErrorMessage(ERROR_NOT_FOUND), false, ERROR);
		}
	}

	private function deactivate(int $rowid): void
	{
		$item = new Group_User();
		if (!$item->getFromDB($rowid)) {
			Session::addMessageAfterRedirect($item->getErrorMessage(ERROR_NOT_FOUND), false, ERROR);
			return;
		}
		if (!$this->canAccessGroup($item->fields)) {
			throw new AccessDeniedHttpException();
		}

		$input = $item->fields;
		unset($input['id']);
		$group = new PluginMoregroupsGroup();
		if ($group->add($input)) {
			$group_user = new Group_User();
			if (!$group_user->delete(['id' => $rowid])) {
				Session::addMessageAfterRedirect($group_user->getErrorMessage(ERROR_NOT_FOUND), false, ERROR);
			}
		} else {
			Session::addMessageAfterRedirect($group->getErrorMessage(ERROR_NOT_FOUND), false, ERROR);
		}
	}
}
