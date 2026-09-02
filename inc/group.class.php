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

if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;

class PluginMoregroupsGroup extends CommonDBChild
{
	public static $itemtype = 'Group';
	public static $items_id = 'groups_id';
	public $dohistory = true;

	static function getTypeName($nb = 0)
	{
		return __('Deactivated users', 'moregroups');
	}

	public function getForbiddenStandardMassiveAction()
	{
		$forbidden   = parent::getForbiddenStandardMassiveAction();
		$forbidden[] = 'update';
		$forbidden[] = 'clone';
		return $forbidden;
	}

	public function getSpecificMassiveActions($checkitem = null)
	{
		$actions = [];
		if (Group_User::canUpdate()) {
			$actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'activate'] = __('Activate users', 'moregroups');
		}

		$actions += parent::getSpecificMassiveActions($checkitem);
		return $actions;
	}

	static function showMassiveActionsSubForm(MassiveAction $ma)
	{
		switch ($ma->getAction()) {
			case 'deactivate':
				$submitname = _sx('button', 'Deactivate users', 'moregroups');
				echo Html::submit($submitname, ['name' => 'massiveaction', 'class' => 'btn btn-sm btn-primary']);
				return true;
			case 'activate':
				$submitname = _sx('button', 'Activate users', 'moregroups');
				echo Html::submit($submitname, ['name' => 'massiveaction', 'class' => 'btn btn-sm btn-primary']);
				return true;
		}
		return parent::showMassiveActionsSubForm($ma);
	}

	static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
	{
		switch ($ma->getAction()) {
			case 'deactivate':
				foreach ($ids as $id) {
					if (!$item->getFromDB($id)) {
						$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
						$ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
					} elseif (!$item->can($id, UPDATE)) {
						$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
						$ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
					} else {
						$input = $item->fields;
						unset($input['id']);
						$group = new self();
						if ($group->add($input)) {
							$group_user = new Group_User();
							if ($group_user->delete(['id' => $id])) {
								$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
							} else {
								$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
								$ma->addMessage($group_user->getErrorMessage(ERROR_NOT_FOUND));
							}
						} else {
							$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
							$ma->addMessage($group->getErrorMessage(ERROR_NOT_FOUND));
						}
					}
				}
				return true;

			case 'activate':
				foreach ($ids as $id) {
					if (!$item->getFromDB($id)) {
						$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
						$ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
					} elseif (!$item->can($id, UPDATE) || !Group_User::canUpdate()) {
						$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
						$ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
					} else {
						$input = $item->fields;
						unset($input['id']);
						$group_user = new Group_User();
						if ($group_user->add($input)) {
							// Purga el registro de la tabla del plugin tras reactivar exitosamente al usuario
							$item->delete(['id' => $id], true);
							$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
						} else {
							$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
							$ma->addMessage($group_user->getErrorMessage(ERROR_NOT_FOUND));
						}
					}
				}
				return true;
		}
		parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
	}

	public static function showDeactivated($item)
	{
		global $DB;

		if ($item->getType() != 'Group') {
			return false;
		}

		$ID = $item->getID();
		if (!User::canView() || !$item->can($ID, READ)) {
			return false;
		}

		$query = [
			'FROM'  => self::getTable(),
			'WHERE' => [
				'groups_id' => $ID,
			],
		];

		$canedit = Group_User::canUpdate();
		$rand    = mt_rand();

		echo "<div class='card m-n2 border-0 shadow-none'>";
		echo "<div class='card-header'>";
		echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'>";
		echo "<i class='ti ti-users-group fa-2x'></i>";
		echo "</div>";
		echo "<h4 class='card-title ps-5'>" . __('Deactivated users', 'moregroups') . "</h4>";
		echo "</div>";
		echo "<div class='card-body'>";
		$entries = [];
		foreach ($DB->request($query) as $row) {
			$user = new User();
			$user->getFromDB($row['users_id']);

			$entry = [
				'itemtype'  => __CLASS__,
				'id'        => $row['id'],
				'row_class' => 'table-danger',
				'user'      => $user->getLink(),
				'dynamic'   => $row['is_dynamic'] ? "<i class='ti ti-check'></i>" : '',
				'manager'   => $row['is_manager'] ? "<i class='ti ti-check'></i>" : '',
				'delegatee' => $row['is_userdelegate'] ? "<i class='ti ti-check'></i>" : '',
			];
			if ($canedit) {
				$entry['actions'] = "<button type='button' onclick='getElementById(\"activateForm\").rowaction.value=\"activate\";getElementById(\"activateForm\").rowid.value=" . $row['id'] . ";getElementById(\"activateForm\").submit();' class='btn btn-sm btn-primary' title='" . _sx('button', 'Activate user', 'moregroups') . "'><i class='ti ti-eye'></i></button>";
			}
			$entries[] = $entry;
		}

		$columns = [
			'user'      => User::getTypeName(1),
			'dynamic'   => __('Dynamic'),
			'manager'   => _n('Manager', 'Managers', 1),
			'delegatee' => __('Delegatee'),
		];
		$formatters = [
			'user'      => 'raw_html',
			'dynamic'   => 'raw_html',
			'manager'   => 'raw_html',
			'delegatee' => 'raw_html',
		];
		if ($canedit) {
			$columns['actions'] = '';
			$formatters['actions'] = 'raw_html';
		}

		TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
			'is_tab'             => true,
			'nofilter'           => true,
			'nosort'             => true,
			'columns'            => $columns,
			'formatters'         => $formatters,
			'entries'            => $entries,
			'total_number'       => count($entries),
			'filtered_number'    => count($entries),
			'showmassiveactions' => $canedit,
			'massiveactionparams' => [
				'num_displayed' => count($entries),
				'container'     => 'mass' . __CLASS__ . $rand,
			],
		]);

		if ($canedit) {
			$label = htmlspecialchars(_sx('button', 'Deactivate user', 'moregroups'), ENT_QUOTES);
			$script = <<<JAVASCRIPT
                $(document).ready(function() {
                    $("input[name^='item[Group_User]']").each(function() {
                        var name = $(this).attr('name');
                        const myarray = name.split('[');
                        if (myarray.length >= 3) {
                            name = myarray[2].split(']')[0];
                            $(this).parent().parent().append("<td class='center'><button type='button' onclick='getElementById(\"activateForm\").rowaction.value=\"deactivate\";getElementById(\"activateForm\").rowid.value="+name+";getElementById(\"activateForm\").submit();' class='btn btn-sm btn-primary' title='{$label}'><i class='ti ti-eye-off'></i></button></td>");
                        }
                    });
                });
            JAVASCRIPT;

			echo Html::scriptBlock($script);
		}

		global $CFG_GLPI;
		echo "<form id='activateForm' action='" . $CFG_GLPI['root_doc'] . "/plugins/moregroups/GroupAction' method='post'>";
		echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		echo "<input type='hidden' name='rowaction' value=''>";
		echo "<input type='hidden' name='rowid' value=''>";
		Html::closeForm();
		echo "</div>";
		echo "</div>";
	}

	static function install(Migration $migration)
	{
		global $DB;

		$default_charset = DBConnection::getDefaultCharset();
		$default_collation = DBConnection::getDefaultCollation();
		$default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

		$table = self::getTable();
		if (!$DB->tableExists($table)) {
			$migration->displayMessage("Installing $table");
			$query = "CREATE TABLE IF NOT EXISTS $table (
                `id` int {$default_key_sign} NOT NULL auto_increment,
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `groups_id` int unsigned NOT NULL DEFAULT '0',
                `is_dynamic` tinyint NOT NULL DEFAULT '0',
                `is_manager` tinyint NOT NULL DEFAULT '0',
                `is_userdelegate` tinyint NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`users_id`,`groups_id`),
                KEY `groups_id` (`groups_id`),
                KEY `is_dynamic` (`is_dynamic`),
                KEY `is_manager` (`is_manager`),
                KEY `is_userdelegate` (`is_userdelegate`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

			if (!$DB->doQuery($query)) {
				\Session::addMessageAfterRedirect(
					sprintf(__('Error creating table %s: %s', 'moregroups'), 'glpi_plugin_moregroups_groups', $DB->error()),
					false,
					ERROR
				);
				return false;
			}
		}
	}

	static function uninstall(Migration $migration)
	{
		$table = self::getTable();
		$migration->displayMessage("Uninstalling $table");
		$migration->dropTable($table);
	}
}
