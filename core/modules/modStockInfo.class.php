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
 * \defgroup   stockinfo   Module StockInfo
 * \brief      Display stock information in order/proposal/invoice lines
 * \file       core/modules/modStockInfo.class.php
 * \ingroup    stockinfo
 * \brief      Module descriptor for StockInfo
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class to describe and enable module StockInfo
 */
class modStockInfo extends DolibarrModules
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Module ID (unique)
        $this->numero = 194053;

        // Module family
        $this->family = "products";

        // Module position
        $this->module_position = '90';

        // Module name
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description
        $this->description = "Display stock information column in order/proposal/invoice lines";
        $this->descriptionlong = "Adds a Stock column to order, proposal and invoice line tables showing available stock with low-stock alerts.";

        // Author
        $this->editor_name = 'Nicolas Zaoui';
        $this->editor_url = '';

        // Version
        $this->version = '1.1.0';

        // Min Dolibarr version
        $this->need_dolibarr_version = array(18, 0);

        // Constants
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Module parts (hooks)
        $this->module_parts = array(
            'hooks' => array(
                'ordercard',
                'propalcard',
                'invoicecard',
                'invoicereccard'
            )
        );

        // Data directories
        $this->dirs = array();

        // Config page
        $this->config_page_url = array("setup.php@stockinfo");

        // Dependencies
        $this->hidden = false;
        $this->depends = array('modStock');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("stockinfo@stockinfo");

        // Module constants
        $this->const = array();

        // Tabs
        $this->tabs = array();

        // Dictionaries
        $this->dictionaries = array();

        // Boxes
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();

        // Permissions
        $this->rights = array();
        $this->rights_class = 'stockinfo';

        // Menus
        $this->menu = array();
    }

    /**
     * Function called when module is enabled
     *
     * @param string $options Options when enabling module
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        return $this->_init(array(), $options);
    }

    /**
     * Function called when module is disabled
     *
     * @param string $options Options when disabling module
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
