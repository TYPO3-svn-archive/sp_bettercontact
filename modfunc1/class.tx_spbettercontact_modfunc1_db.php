<?php
	/***************************************************************
	*  Copyright notice
	*
	*  (c) 2010 Kai Vogel <kai.vogel ( at ) speedprogs.de>
	*  All rights reserved
	*
	*  This script is part of the TYPO3 project. The TYPO3 project is
	*  free software; you can redistribute it and/or modify
	*  it under the terms of the GNU General Public License as published by
	*  the Free Software Foundation; either version 2 of the License, or
	*  (at your option) any later version.
	*
	*  The GNU General Public License can be found at
	*  http://www.gnu.org/copyleft/gpl.html.
	*
	*  This script is distributed in the hope that it will be useful,
	*  but WITHOUT ANY WARRANTY; without even the implied warranty of
	*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	*  GNU General Public License for more details.
	*
	*  This copyright notice MUST APPEAR in all copies of the script!
	***************************************************************/


	/**
	 * Database class for the 'sp_bettercontact' module.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_modfunc1_db {
		protected $aConfig   = array();
		protected $sLogTable = 'tx_spbettercontact_log';
		protected $id        = 0;


		/**
		 * Set configuration for db object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig = $poParent->aConfig;
			$this->id      = $poParent->id;
		}


		/**
		 * Get all rows from log table for current page
		 *
		 * @param  integer $piPID PID of page to show
		 * @param  integer $piPeriod Period to show
		 * @return Array with table rows
		 */
		public function aGetLogTable ($piPID = 0, $piPeriod = 0) {
			$sWhere  = 'deleted = 0';
			$sWhere .= ($piPID) ? ' AND pid = ' . (int) $piPID : '';
			$sWhere .= ($piPeriod) ? ' AND tstamp > ' . (int) $piPeriod : '';

			// Get rows from current page
			if (!$aRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $this->sLogTable, $sWhere, '', 'tstamp DESC')) {
				return array();
			}

			return $aRows;
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/modfunc1/class.tx_spbettercontact_modfunc1_db.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/modfunc1/class.tx_spbettercontact_modfunc1_db.php']);
	}
?>