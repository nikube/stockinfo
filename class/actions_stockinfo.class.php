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
 * \file       class/actions_stockinfo.class.php
 * \ingroup    stockinfo
 * \brief      Hook actions for StockInfo module
 */

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * Class ActionsStockInfo
 * Hook actions for displaying stock information in order lines
 */
class ActionsStockInfo
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var array Errors array
     */
    public $errors = array();

    /**
     * @var string Hook result (HTML to print)
     */
    public $resprints = '';

    /**
     * @var array Results array
     */
    public $results = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get quantity of a product in orders that are shipped but not billed
     * (status = 3 (closed/delivered) and facture = 0 (not billed))
     *
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID (0 = all)
     * @return float Quantity in shipped but unbilled orders
     */
    protected function getShippedUnbilledQty($productId, $warehouseId = 0)
    {
        $sql = "SELECT SUM(cd.qty) as qty";
        $sql .= " FROM ".$this->db->prefix()."commandedet as cd";
        $sql .= " INNER JOIN ".$this->db->prefix()."commande as c ON c.rowid = cd.fk_commande";
        $sql .= " WHERE cd.fk_product = ".((int) $productId);
        $sql .= " AND c.fk_statut = 3"; // STATUS_CLOSED (delivered)
        $sql .= " AND c.facture = 0";   // Not billed

        // Warehouse filter disabled - orders don't have warehouse assigned at this point
        // if ($warehouseId > 0) {
        //     $sql .= " AND c.fk_warehouse = ".((int) $warehouseId);
        // }

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return (float) ($obj->qty ? $obj->qty : 0);
        }
        return 0;
    }

    /**
     * Get stock for a product, optionally filtered by warehouse
     *
     * @param Product $product Product object (already fetched)
     * @param int $warehouseId Warehouse ID (0 = all)
     * @return float Stock quantity
     */
    protected function getStock($product, $warehouseId = 0)
    {
        if ($warehouseId > 0) {
            // Load stock per warehouse
            $product->load_stock('warehouseopen');
            if (isset($product->stock_warehouse[$warehouseId])) {
                return (float) $product->stock_warehouse[$warehouseId]->real;
            }
            return 0;
        }
        // Return total stock
        return (float) $product->stock_reel;
    }

    /**
     * Hook to add content at end of page (inject JavaScript for stock column)
     *
     * @param array         $parameters Hook parameters
     * @param CommonObject  $object     Object
     * @param string        $action     Action
     * @param HookManager   $hookmanager Hook manager
     * @return int                      0=OK, <0=KO
     */
    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        // Check if module is enabled
        if (empty($conf->global->STOCKINFO_ENABLED)) {
            return 0;
        }

        // Check context
        $contexts = explode(':', $parameters['context']);
        if (!array_intersect(array('ordercard', 'propalcard', 'invoicecard', 'invoicereccard'), $contexts)) {
            return 0;
        }

        $langs->load('stockinfo@stockinfo');

        // Get configuration
        $calcMode = getDolGlobalString('STOCKINFO_CALCULATION_MODE', 'simple');
        $warehouseId = getDolGlobalInt('STOCKINFO_WAREHOUSE_ID', 0);

        // Build stock data for all product lines
        $stockData = array();
        if (!empty($object->lines)) {
            foreach ($object->lines as $line) {
                if (!empty($line->fk_product) && $line->fk_product > 0) {
                    $product = new Product($this->db);
                    $result = $product->fetch($line->fk_product);
                    if ($result > 0) {
                        // Get base stock (optionally filtered by warehouse)
                        $stock = $this->getStock($product, $warehouseId);

                        // If adjusted mode, subtract shipped but unbilled qty
                        if ($calcMode == 'adjusted') {
                            $shippedUnbilled = $this->getShippedUnbilledQty($line->fk_product, $warehouseId);
                            $stock = $stock - $shippedUnbilled;
                        }

                        $stockData[$line->id] = array(
                            'stock' => $stock,
                            'alert' => (float) $product->seuil_stock_alerte
                        );
                    }
                }
            }
        }

        // Output JavaScript to inject stock column
        $stockDataJson = json_encode($stockData);
        $stockLabel = addslashes($langs->trans('Stock'));

        print <<<SCRIPT
<script type="text/javascript">
(function() {
    var stockData = {$stockDataJson};
    var stockLabel = '{$stockLabel}';

    console.log('StockInfo: Script loaded, stockData=', stockData);

    function addStockColumn() {
        console.log('StockInfo: addStockColumn called');

        // Find the Qty column header directly (more reliable than finding row first)
        var qtyHeader = document.querySelector('th.linecolqty');
        console.log('StockInfo: qtyHeader=', qtyHeader);
        if (!qtyHeader) {
            console.log('StockInfo: No qty header found, retrying...');
            setTimeout(addStockColumn, 200);
            return;
        }

        // Get the header row from the qty header
        var headerRow = qtyHeader.parentNode;
        console.log('StockInfo: headerRow=', headerRow);

        // Check if stock column already exists
        if (headerRow.querySelector('th.linecolstock')) {
            console.log('StockInfo: Stock column already exists');
            return;
        }

        // Create stock header
        var stockHeader = document.createElement('th');
        stockHeader.className = 'linecolstock right nowrap';
        stockHeader.innerHTML = stockLabel;

        // Insert after qty column
        qtyHeader.parentNode.insertBefore(stockHeader, qtyHeader.nextSibling);
        console.log('StockInfo: Header inserted');

        // Add stock cells to each data row
        var dataRows = document.querySelectorAll('tr.oddeven[data-id]');
        console.log('StockInfo: Found ' + dataRows.length + ' data rows');

        dataRows.forEach(function(row) {
            var lineId = row.getAttribute('data-id');
            var qtyCell = row.querySelector('td.linecolqty');

            console.log('StockInfo: Processing row ' + lineId + ', qtyCell=', qtyCell);

            if (qtyCell && !row.querySelector('td.linecolstock')) {
                var stockCell = document.createElement('td');
                stockCell.className = 'linecolstock right nowrap';

                if (stockData[lineId]) {
                    var stock = stockData[lineId].stock;
                    var alert = stockData[lineId].alert;

                    // Format number
                    var stockFormatted = parseFloat(stock).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 2});

                    // Color coding: green for positive, red for negative or at/below alert
                    if (stock < 0 || (alert > 0 && stock <= alert)) {
                        stockCell.style.color = '#ff0000';
                        stockCell.style.fontWeight = 'bold';
                    } else if (stock > 0) {
                        stockCell.style.color = '#008800';
                    }

                    stockCell.innerHTML = stockFormatted;
                    console.log('StockInfo: Added stock ' + stockFormatted + ' to row ' + lineId);
                }

                qtyCell.parentNode.insertBefore(stockCell, qtyCell.nextSibling);
            }
        });

        // Update colspan for action cells (last cells that span multiple columns)
        var actionCells = document.querySelectorAll('tr.oddeven td[colspan]');
        actionCells.forEach(function(cell) {
            var currentColspan = parseInt(cell.getAttribute('colspan')) || 1;
            cell.setAttribute('colspan', currentColspan + 1);
        });

        console.log('StockInfo: Done');
    }

    // Run with multiple attempts to ensure DOM is ready
    setTimeout(addStockColumn, 100);
    setTimeout(addStockColumn, 500);
    setTimeout(addStockColumn, 1000);
})();
</script>
SCRIPT;

        return 0;
    }
}
