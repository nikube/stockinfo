<?php
/* Copyright (C) 2024 Nicolas Zaoui
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

// Load Dolibarr environment
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

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

// Translations
$langs->loadLangs(array('admin', 'stocks', 'stockinfo@stockinfo'));

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

if ($action == 'update') {
    $error = 0;

    // STOCKINFO_ENABLED
    if (GETPOST('STOCKINFO_ENABLED', 'alpha') == 'on') {
        dolibarr_set_const($db, 'STOCKINFO_ENABLED', '1', 'chaine', 0, '', $conf->entity);
    } else {
        dolibarr_set_const($db, 'STOCKINFO_ENABLED', '0', 'chaine', 0, '', $conf->entity);
    }

    // STOCKINFO_CALCULATION_MODE
    $calcMode = GETPOST('STOCKINFO_CALCULATION_MODE', 'alpha');
    dolibarr_set_const($db, 'STOCKINFO_CALCULATION_MODE', $calcMode, 'chaine', 0, '', $conf->entity);

    // STOCKINFO_WAREHOUSE_ID
    $warehouseId = GETPOSTINT('STOCKINFO_WAREHOUSE_ID');
    dolibarr_set_const($db, 'STOCKINFO_WAREHOUSE_ID', $warehouseId, 'chaine', 0, '', $conf->entity);

    if (!$error) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

/*
 * View
 */

$page_name = "StockInfoSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = array();
$h = 0;
$head[$h][0] = dol_buildpath('/stockinfo/admin/setup.php', 1);
$head[$h][1] = $langs->trans("Settings");
$head[$h][2] = 'settings';
$h++;

print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleStockInfoName"), -1, 'stock');

// Setup form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td class="center" width="300">'.$langs->trans("Value").'</td>';
print '</tr>';

// STOCKINFO_ENABLED
print '<tr class="oddeven">';
print '<td>'.$langs->trans("StockInfoEnabled").'</td>';
print '<td class="center">';
print '<input type="checkbox" name="STOCKINFO_ENABLED"'.(!empty($conf->global->STOCKINFO_ENABLED) ? ' checked' : '').'>';
print '</td>';
print '</tr>';

// STOCKINFO_CALCULATION_MODE
$currentCalcMode = getDolGlobalString('STOCKINFO_CALCULATION_MODE', 'simple');
print '<tr class="oddeven">';
print '<td>'.$langs->trans("StockInfoCalculationMode").'</td>';
print '<td class="center">';
print '<select name="STOCKINFO_CALCULATION_MODE" class="flat minwidth200">';
print '<option value="simple"'.($currentCalcMode == 'simple' ? ' selected' : '').'>'.$langs->trans("StockInfoModeSimple").'</option>';
print '<option value="adjusted"'.($currentCalcMode == 'adjusted' ? ' selected' : '').'>'.$langs->trans("StockInfoModeAdjusted").'</option>';
print '</select>';
print '</td>';
print '</tr>';

// STOCKINFO_WAREHOUSE_ID
$currentWarehouseId = getDolGlobalInt('STOCKINFO_WAREHOUSE_ID', 0);
print '<tr class="oddeven">';
print '<td>'.$langs->trans("StockInfoWarehouse").'</td>';
print '<td class="center">';
print '<select name="STOCKINFO_WAREHOUSE_ID" class="flat minwidth200">';
print '<option value="0"'.($currentWarehouseId == 0 ? ' selected' : '').'>'.$langs->trans("AllWarehouses").'</option>';

// Load warehouses
$warehouse = new Entrepot($db);
$warehouseList = $warehouse->list_array(1); // 1 = only open warehouses
if (is_array($warehouseList)) {
    foreach ($warehouseList as $whId => $whLabel) {
        print '<option value="'.$whId.'"'.($currentWarehouseId == $whId ? ' selected' : '').'>'.htmlspecialchars($whLabel).'</option>';
    }
}
print '</select>';
print '</td>';
print '</tr>';

print '</table>';

print '<br>';

// Help text
print '<div class="opacitymedium">';
print '<strong>'.$langs->trans("StockInfoModeSimple").':</strong> '.$langs->trans("StockInfoModeSimpleHelp").'<br>';
print '<strong>'.$langs->trans("StockInfoModeAdjusted").':</strong> '.$langs->trans("StockInfoModeAdjustedHelp");
print '</div>';

print '<br>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

// Footer
llxFooter();
$db->close();
