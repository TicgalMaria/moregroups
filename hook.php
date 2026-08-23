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

function plugin_moregroups_install()
{
	$migration = new Migration(PLUGIN_MOREGROUPS_VERSION);

	foreach (glob(dirname(__FILE__) . '/inc/*') as $filepath) {
		if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
			$classname = 'PluginMoregroups' . ucfirst($matches[1]);
			include_once($filepath);
			if (method_exists($classname, 'install')) {
				$classname::install($migration);
			}
		}
	}
	$migration->executeMigration();

	return true;
}

function plugin_moregroups_uninstall()
{
	$migration = new Migration(PLUGIN_MOREGROUPS_VERSION);

	foreach (glob(dirname(__FILE__) . '/inc/*') as $filepath) {
		if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
			$classname = 'PluginMoregroups' . ucfirst($matches[1]);
			include_once($filepath);
			if (method_exists($classname, 'uninstall')) {
				$classname::uninstall($migration);
			}
		}
	}
	$migration->executeMigration();

	return true;
}

function plugin_moregroups_MassiveActions($type) {
	$actions = [];

	if ($type == 'Group_User') {
		$myclass = 'PluginMoregroupsGroup';
		$action_key = 'deactivate';
		$action_label = __('Deactivate users', 'moregroups');
		$actions[$myclass.MassiveAction::CLASS_ACTION_SEPARATOR.$action_key] = $action_label;
	}

	return $actions;
}

function plugin_moregroups_post_show_tab($params = []) {
	if (isset($params['item']) && $params['item'] instanceof CommonDBTM && isset($params['options']['itemtype']) && $params['options']['itemtype'] == 'Group_User'){
		$item = $params['item'];
		if ($item->getType() == 'Group') {
			$group = new PluginMoregroupsGroup();
			$group->showDeactivated($item);
		}
	}
}

function plugin_moregroups_group_user_add(Group_User $group_user) {
	$group = new PluginMoregroupsGroup();
	$group->deleteByCriteria([
		'users_id'  => $group_user->fields['users_id'],
		'groups_id' => $group_user->fields['groups_id'],
	]);
}