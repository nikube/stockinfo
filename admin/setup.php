<?php
/* Copyright (C) 2024-2026 Nicolas Zaoui
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       admin/setup.php
 * \ingroup    stockinfo
 * \brief      Setup page for StockInfo module
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$langs->loadLangs(array('admin', 'stocks', 'stockinfo@stockinfo'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$warehouses = (new Entrepot($db))->list_array(1);
if (!is_array($warehouses)) {
	$warehouses = array();
}

/*
 * Actions
 */

if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
	$calcMode = GETPOST('STOCKINFO_CALCULATION_MODE', 'aZ09');
	$warehouseId = GETPOSTINT('STOCKINFO_WAREHOUSE_ID');
	if (!in_array($calcMode, array('simple', 'adjusted')) || ($warehouseId && !isset($warehouses[$warehouseId]))) {
		setEventMessages($langs->trans("ErrorBadValue"), null, 'errors');
	} elseif (dolibarr_set_const($db, 'STOCKINFO_CALCULATION_MODE', $calcMode, 'chaine', 0, '', $conf->entity) > 0
		&& dolibarr_set_const($db, 'STOCKINFO_WAREHOUSE_ID', $warehouseId, 'chaine', 0, '', $conf->entity) > 0) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("StockInfoSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("StockInfoSetup"), $linkback, 'title_setup');

$head = array(array(dol_buildpath('/stockinfo/admin/setup.php', 1), $langs->trans("Settings"), 'settings'));
print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleStockInfoName"), -1, 'stock');

$currentCalcMode = getDolGlobalString('STOCKINFO_CALCULATION_MODE', 'simple');
$currentWarehouseId = getDolGlobalInt('STOCKINFO_WAREHOUSE_ID');

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td class="center" width="300">'.$langs->trans("Value").'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("StockInfoCalculationMode").'</td><td class="center">';
print '<select name="STOCKINFO_CALCULATION_MODE" class="flat minwidth200">';
foreach (array('simple' => 'StockInfoModeSimple', 'adjusted' => 'StockInfoModeAdjusted') as $k => $v) {
	print '<option value="'.$k.'"'.($currentCalcMode == $k ? ' selected' : '').'>'.$langs->trans($v).'</option>';
}
print '</select></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("StockInfoWarehouse").'</td><td class="center">';
print '<select name="STOCKINFO_WAREHOUSE_ID" class="flat minwidth200">';
print '<option value="0"'.($currentWarehouseId == 0 ? ' selected' : '').'>'.$langs->trans("AllWarehouses").'</option>';
foreach ($warehouses as $whId => $whLabel) {
	print '<option value="'.((int) $whId).'"'.($currentWarehouseId == $whId ? ' selected' : '').'>'.dol_escape_htmltag($whLabel).'</option>';
}
print '</select>';
if ($currentWarehouseId && !isset($warehouses[$currentWarehouseId])) {
	print '<br><span class="error">'.$langs->trans("StockInfoWarehouseUnavailable").'</span>';
}
print '</td></tr>';
print '</table>';

print '<br><div class="opacitymedium">';
print '<strong>'.$langs->trans("StockInfoModeSimple").' :</strong> '.$langs->trans("StockInfoModeSimpleHelp").'<br>';
print '<strong>'.$langs->trans("StockInfoModeAdjusted").' :</strong> '.$langs->trans("StockInfoModeAdjustedHelp");
if ($currentCalcMode == 'adjusted' && !getDolGlobalString('STOCK_CALCULATE_ON_BILL')) {
	print '<br><span class="error">'.$langs->trans("StockInfoAdjustedNeedsBill").'</span>';
}
print '</div>';

print '<br><div class="center"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
