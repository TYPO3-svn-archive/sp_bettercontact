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
	 * Database class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_db {
		protected $oCS        = NULL;
		protected $aConfig    = array();
		protected $aGP        = array();
		protected $aLL        = array();
		protected $sLastError = '';
		protected $sLogTable  = 'tx_spbettercontact_log';
		protected $sFormChar  = 'iso-8859-1';


		/**
		 * Set configuration for db object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig   = $poParent->aConfig;
			$this->aGP       = $poParent->aGP;
			$this->aLL       = $poParent->aLL;
			$this->oCS       = $poParent->oCS;
			$this->sFormChar = $poParent->sFormCharset;
		}


		/**
		 * Log a valid request
		 *
		 * @return Integer with ID of last inserted table row
		 */
		public function iLog () {
			if (empty($this->aConfig['enableLog'])) {
				return 0;
			}

			// Add default fields
			$aFields = array(
				'pid'       => (int) $GLOBALS['TSFE']->id,
				'tstamp'    => (int) $GLOBALS['SIM_EXEC_TIME'],
				'crdate'    => (int) $GLOBALS['SIM_EXEC_TIME'],
				'cruser_id' => (!empty($GLOBALS['TSFE']->fe_user->user['uid'])) ? (int) $GLOBALS['TSFE']->fe_user->user['uid'] : 0,
				'deleted'   => 0,
				'ip'        => (!empty($this->aConfig['enableIPLog'])) ? t3lib_div::getIndpEnv('REMOTE_ADDR') : '',
				'agent'     => t3lib_div::getIndpEnv('HTTP_USER_AGENT'),
				'method'    => ($_SERVER['REQUEST_METHOD'] == 'GET') ? 'GET' : 'POST',
			);

			// Add GPVars
			$aInput = $this->aGP;
			if (!empty($this->aConfig['fields.'])) {
				$aConfFields = t3lib_div::removeDotsFromTS($this->aConfig['fields.']);
				$aInput      = array_intersect_key($this->aGP, $aConfFields);
			}
			$aFields['params'] = json_encode($aInput);
			ksort($aInput); // Keep order for hash if fields will be reordered in form
			$aFields['hash']   = md5(implode('|', array_keys($aInput)));

			// Insert new row
			$sNoQuoteFields = 'pid,tstamp,crdate,cruser_id,deleted';
			if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($this->sLogTable, $aFields, $sNoQuoteFields)) {
				return $GLOBALS['TYPO3_DB']->sql_insert_id();
			}

			$this->sLastError = $GLOBALS['TYPO3_DB']->sql_error();
			return 0;
		}


		/**
		 * Save values into a user specified table
		 *
		 * @return Integer with ID of last inserted row
		 */
		public function iSave () {
			if (empty($this->aConfig['database.']['table'])
			 || empty($this->aConfig['database.']['fieldconf.'])
			 || !is_array($this->aConfig['database.']['fieldconf.'])
			) {
				return 0;
			}

			$aDBConf    = $this->aConfig['database.'];
			$aFields    = $this->aConfig['database.']['fieldconf.'];
			$sIDField   = (!empty($aDBConf['idField'])) ? $aDBConf['idField'] : 'uid';
			$aTableConf = array();

			// Check for external database first
			if (!empty($aDBConf['driver']) || !empty($aDBConf['host']) || !empty($aDBConf['port'])
			 || !empty($aDBConf['database']) || !empty($aDBConf['username']) || !empty($aDBConf['password'])
			) {
				return $this->iSaveExternal($aDBConf, $aFields, $sIDField);
			}

			// Create default fieldconf if configured
			if (!empty($aDBConf['useDefaultValues'])) {
				$aFields = $this->aAddDefaultFields($aFields, $aDBConf['table']);
			}

			// Update
			if (!empty($aFields['uid'])) {
				$sWhere = $sIDField . ' = ' . (int) $aFields[$sIDField];

				// Check if row exists and update (else insert new row - see below)
				if ($GLOBALS['TYPO3_DB']->exec_SELECTcountRows($sIDField, $aDBConf['table'], $sWhere)) {
					if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($aDBConf['table'], $sWhere, $aFields)) {
						return (int) $aFields[$sIDField];
					}
				}
			}

			// Insert
			if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($aDBConf['table'], $aFields)) {
				return (int) $GLOBALS['TYPO3_DB']->sql_insert_id();
			}

			$this->sLastError = $GLOBALS['TYPO3_DB']->sql_error();
			return 0;
		}


		/**
		 * Save values into an external database
		 *
		 * @param  array  $paDBConf  Database configuration
		 * @param  array  $paFields  Field configuration
		 * @param  string $psIDField Field name to identify a row in update mode
		 * @return Integer with ID of the last inserted row
		 */
		protected function iSaveExternal (array $paDBConf, array $paFields, $psIDField = 'uid') {
			if (!count($paDBConf) || !count($paFields) || !strlen($psIDField) || !t3lib_extMgm::isLoaded('adodb')) {
				return 0;
			}

			require_once(t3lib_extMgm::extPath('adodb') . 'adodb/adodb.inc.php');

			// Set global variable to automatically quote values for external tables
			global $ADODB_QUOTE_FIELDNAMES;
			$bOldQuoteState = $ADODB_QUOTE_FIELDNAMES;
			$ADODB_QUOTE_FIELDNAMES = TRUE;

			// Get configuration
			$sDriver   = (!empty($paDBConf['driver']))        ? $paDBConf['driver']        : 'mysql';
			$sHost     = (!empty($paDBConf['host']))          ? $paDBConf['host']          : 'localhost';
			$sHost    .= (!empty($paDBConf['port']))          ? ':' . $paDBConf['port']    : '';
			$sUsername = (!empty($paDBConf['username']))      ? $paDBConf['username']      : '';
			$sPassword = (!empty($paDBConf['password']))      ? $paDBConf['password']      : '';
			$sCharset  = (!empty($paDBConf['force_charset'])) ? $paDBConf['force_charset'] : '';
			$iReturn   = 0;

			// Force given charset for field values
			if ($sCharset) {
				$this->oCS->convArray($paFields, $this->sFormChar, $sCharset);
			}

			// Connect
			$oDB = &NewADOConnection($sDriver);
			if (!empty($paDBConf['database'])) {
				$oDB->Connect($sHost, $sUsername, $sPassword, $paDBConf['database']);
			} else {
				$oDB->Connect($sHost, $sUsername, $sPassword);
			}

			// Update
			if (!empty($paFields[$psIDField])) {
				$sWhere  = $psIDField . ' = ' . (int) $paFields[$psIDField];
				$sSelect = 'SELECT COUNT(*) FROM ' . $paDBConf['table'] . ' WHERE ' . $sWhere . ' LIMIT 1';
				$oResult = $oDB->Execute($sSelect);

				// Check if row exists and update (else insert new row - see below)
				if ($oResult->RowCount()) {
					if ($oDB->AutoExecute($paDBConf['table'], $paFields, 'UPDATE', $sWhere)) {
						$iReturn = (int) $paFields[$psIDField];
					}
				}
			}

			// Insert
			if (!$iReturn && $oDB->AutoExecute($paDBConf['table'], $paFields, 'INSERT')) {
				$iReturn = (int) $oDB->Insert_ID($paDBConf['table']);
			}

			// Check result
			if (!$iReturn) {
				$this->sLastError = $oDB->ErrorMsg();
			}

			// Close db connection and revert global quoting variable
			$oDB->Close();
			$ADODB_QUOTE_FIELDNAMES = $bOldQuoteState;

			return $iReturn;
		}


		/**
		 * Add default table field values (BETA)
		 *
		 * @param  array $paFields Array with field configuration
		 * @return Array with new field configuration
		 */
		protected function aAddDefaultFields (array $paFields, $psTable) {
			if (!count($paFields) || !strlen($psTable)) {
				return $paFields;
			}

			$aColumns   = array();
			$aNewFields = array();

			// Get table columns
			if ($oResult  = $GLOBALS['TYPO3_DB']->sql_query('SHOW COLUMNS FROM ' . $psTable)) {
				while ($aRow = $GLOBALS['TYPO3_DB']->sql_fetch_row($oResult)) {
					$aColumns[$aRow[0]] = TRUE;
				}
			}

			if (empty($aColumns)) {
				return $paFields;
			}

			// PID
			if (isset($aColumns['pid'])) {
				$aNewFields['pid'] = (int) $GLOBALS['TSFE']->id;
			}

			// TSTAMP
			if (isset($aColumns['tstamp'])) {
				$aNewFields['tstamp'] = (int) $GLOBALS['SIM_EXEC_TIME'];
			}

			// CRDATE
			if (isset($aColumns['crdate'])) {
				$aNewFields['crdate'] = (int) $GLOBALS['SIM_EXEC_TIME'];
			}

			// CRUSER_ID
			if ((isset($aColumns['cruser_id'])) && !empty($GLOBALS['TSFE']->fe_user->user['uid'])) {
				$aNewFields['cruser_id'] = (int) $GLOBALS['TSFE']->fe_user->user['uid'];
			}

			return $paFields + $aNewFields; // Do not overwrite existing fields
		}


		/**
		 * Get last error
		 *
		 * @return String with last database error
		 */
		public function sGetError() {
			if ($this->sLastError) {
				return sprintf($this->aLL['msg_db_failed'], $this->sLastError);
			}

			return '';
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_db.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_db.php']);
	}
?>