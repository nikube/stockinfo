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
 * \file       class/actions_stockinfo.class.php
 * \ingroup    stockinfo
 * \brief      Hook: add a "Stock" column after Qty on order/proposal/invoice lines
 */

class ActionsStockInfo
{
	/** @var DoliDB */
	protected $db;
	public $error = '';
	public $errors = array();
	public $resprints = '';
	public $results = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Real stock per product over open warehouses (or one warehouse), one query.
	 *
	 * @param int[] $productIds
	 * @param int   $warehouseId 0 = all open warehouses
	 * @return array<int,float>|null null on SQL error
	 */
	protected function getStockByProduct(array $productIds, $warehouseId)
	{
		$sql = "SELECT ps.fk_product, SUM(ps.reel) as reel";
		$sql .= " FROM ".$this->db->prefix()."product_stock as ps";
		$sql .= " INNER JOIN ".$this->db->prefix()."entrepot as e ON e.rowid = ps.fk_entrepot";
		$sql .= " WHERE ps.fk_product IN (".$this->db->sanitize(implode(',', $productIds)).")";
		$sql .= " AND e.entity IN (".getEntity('stock').")";
		$sql .= " AND e.statut = 1";
		if ($warehouseId > 0) {
			$sql .= " AND ps.fk_entrepot = ".((int) $warehouseId);
		}
		$sql .= " GROUP BY ps.fk_product";

		return $this->fetchQtyMap($sql);
	}

	/**
	 * Qty on orders classified delivered (status 3) but not billed yet, per product, one query.
	 * Only meaningful when stock is decremented on invoice validation (STOCK_CALCULATE_ON_BILL):
	 * those quantities are physically gone but still counted in real stock.
	 *
	 * @param int[] $productIds
	 * @return array<int,float>|null null on SQL error
	 */
	protected function getShippedUnbilledByProduct(array $productIds)
	{
		$sql = "SELECT cd.fk_product, SUM(cd.qty) as reel";
		$sql .= " FROM ".$this->db->prefix()."commandedet as cd";
		$sql .= " INNER JOIN ".$this->db->prefix()."commande as c ON c.rowid = cd.fk_commande";
		$sql .= " WHERE cd.fk_product IN (".$this->db->sanitize(implode(',', $productIds)).")";
		$sql .= " AND c.entity IN (".getEntity('commande').")";
		$sql .= " AND c.fk_statut = 3"; // Commande::STATUS_CLOSED (class not loaded on propal/invoice cards)
		$sql .= " AND c.facture = 0";
		// ponytail: no warehouse filter, orders carry no warehouse at this stage
		$sql .= " GROUP BY cd.fk_product";

		return $this->fetchQtyMap($sql);
	}

	/**
	 * @param string $sql Query returning (fk_product, reel)
	 * @return array<int,float>|null
	 */
	private function fetchQtyMap($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return null;
		}
		$map = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$map[(int) $obj->fk_product] = (float) $obj->reel;
		}
		return $map;
	}

	/**
	 * Hook addMoreActionsButtons: inject the Stock column via JS (no line-level hook exists for a new column).
	 *
	 * @param array        $parameters
	 * @param CommonObject $object
	 * @param string       $action
	 * @param HookManager  $hookmanager
	 * @return int 0
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		$contexts = explode(':', $parameters['context']);
		if (!array_intersect(array('ordercard', 'propalcard', 'invoicecard', 'invoicereccard'), $contexts)) {
			return 0;
		}
		if (!$user->hasRight('stock', 'lire') || empty($object->lines)) {
			return 0;
		}

		$productIds = array();
		foreach ($object->lines as $line) {
			if (!empty($line->fk_product)) {
				$productIds[(int) $line->fk_product] = (int) $line->fk_product;
			}
		}
		if (empty($productIds)) {
			return 0;
		}

		$warehouseId = getDolGlobalInt('STOCKINFO_WAREHOUSE_ID');
		$stocks = $this->getStockByProduct($productIds, $warehouseId);
		if ($stocks === null) {
			return 0;
		}
		if (getDolGlobalString('STOCKINFO_CALCULATION_MODE') == 'adjusted' && getDolGlobalString('STOCK_CALCULATE_ON_BILL')) {
			$shipped = $this->getShippedUnbilledByProduct($productIds);
			if ($shipped === null) {
				return 0;
			}
			foreach ($shipped as $pid => $qty) {
				$stocks[$pid] = (isset($stocks[$pid]) ? $stocks[$pid] : 0) - $qty;
			}
		}

		// Alert thresholds
		$alerts = array();
		$sql = "SELECT rowid, seuil_stock_alerte FROM ".$this->db->prefix()."product WHERE rowid IN (".$this->db->sanitize(implode(',', $productIds)).")";
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$alerts[(int) $obj->rowid] = (float) $obj->seuil_stock_alerte;
		}

		// lineId => [formatted stock, css class], formatted server-side with user's language
		$cells = array();
		foreach ($object->lines as $line) {
			$pid = (int) $line->fk_product;
			if (!$pid) {
				continue;
			}
			$stock = isset($stocks[$pid]) ? $stocks[$pid] : 0;
			$alert = isset($alerts[$pid]) ? $alerts[$pid] : 0;
			$class = 'opacitymedium';
			if ($stock < 0 || ($alert > 0 && $stock <= $alert)) {
				$class = 'error bold';
			} elseif ($stock > 0) {
				$class = 'ok';
			}
			$cells[$line->id] = array(price($stock, 0, $langs, 0, 0), $class);
		}

		$langs->load('stockinfo@stockinfo');
		$json = json_encode(array('label' => $langs->trans('Stock'), 'cells' => $cells));

		print '<script>
(function () {
	var d = '.$json.';
	function run() {
		var qtyHeader = document.querySelector("th.linecolqty");
		if (!qtyHeader || qtyHeader.parentNode.querySelector("th.linecolstock")) return;
		var th = document.createElement("th");
		th.className = "linecolstock right nowrap";
		th.textContent = d.label;
		qtyHeader.after(th);
		qtyHeader.closest("table").querySelectorAll("td.linecolqty").forEach(function (qtyCell) {
			var td = document.createElement("td");
			td.className = "linecolstock right nowrap";
			var c = d.cells[qtyCell.parentNode.getAttribute("data-id")];
			if (c) { td.textContent = c[0]; td.className += " " + c[1]; }
			qtyCell.after(td);
		});
	}
	document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", run) : run();
})();
</script>';

		return 0;
	}
}
