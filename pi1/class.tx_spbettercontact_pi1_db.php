<?php
	/***************************************************************
	*  Copyright notice
	*
	*  (c) 2011 Kai Vogel <kai.vogel ( at ) speedprogs.de>
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
		protected $aConfig       = array();
		protected $aLL           = array();
		protected $aGP           = array();
		protected $aFields       = array();
		protected $aUniqueErrors = array();
		protected $oCS           = NULL;
		protected $sLogTable     = 'tx_spbettercontact_log';
		protected $sFormChar     = '';


		/**
		 * Set configuration for db object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig   = &$poParent->aConfig;
			$this->aFields   = &$poParent->aFields;
			$this->aGP       = &$poParent->aGP;
			$this->aLL       = &$poParent->aLL;
			$this->oCS       = &$poParent->oCS;
			$this->sFormChar = &$poParent->sFormCharset;
		}


		/**
		 * Log a valid request
		 *
		 * @param string $psError Will be set with an error if occurs
		 * @return Integer with ID of last inserted table row
		 */
		public function iLog (&$psError) {
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

			// Force a user defined PID
			if (!empty($this->aConfig['forceLogPID'])) {
				$aFields['pid'] = (int) $this->aConfig['forceLogPID'];
			}

			// Add GPvars
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

			$psError = $GLOBALS['TYPO3_DB']->sql_error();
			return 0;
		}


		/**
		 * Save values into a user specified table
		 *
		 * @param string $psError Will be set with an error if occurs
		 * @return Integer with ID of last inserted row
		 */
		public function iSave (&$psError) {
			if (empty($this->aConfig['database.']['table'])) {
				return 0;
			}

			$aDBConf    = $this->aConfig['database.'];
			$aFields    = (!empty($aDBConf['fieldconf.']) ? $aDBConf['fieldconf.'] : array());
			$sIDField   = (!empty($aDBConf['idField'])    ? $aDBConf['idField']    : 'uid');
			$aTableConf = array();

			// Automatically fill fields if configured (useDefaultValues is deprecated since 2.5.0)
			if (!empty($aDBConf['autoFillDefault']) || !empty($aDBConf['useDefaultValues']) || !empty($aDBConf['autoFillExisting'])) {
				$aFields = $this->aAddAutoFields($aFields, $aDBConf);
			}

			// Update
			if (!empty($aFields[$sIDField])) {
				$sWhere = $sIDField . ' = ' . (int) $aFields[$sIDField];

				// Check if row exists and update (else insert new row - see below)
				if ($GLOBALS['TYPO3_DB']->exec_SELECTcountRows($sIDField, $aDBConf['table'], $sWhere)) {
					if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($aDBConf['table'], $sWhere, $aFields)) {
						return (int) $aFields[$sIDField];
					}
				}
			}

			// Check for unique fields
			if ($this->bHasDuplicates($aFields)) {
				$psError = 'Duplicate entries found';
				return 0;
			}

			// Insert
			if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($aDBConf['table'], $aFields)) {
				return (int) $GLOBALS['TYPO3_DB']->sql_insert_id();
			}

			$psError = $GLOBALS['TYPO3_DB']->sql_error();
			return 0;
		}


		/**
		 * Save values into an external database
		 *
		 * @param string $psError Will be set with an error if occurs
		 * @return Integer with ID of the last inserted row
		 */
		protected function iSaveExternal (&$psError) {
			if (!t3lib_extMgm::isLoaded('adodb')) {
				return 0;
			}

			t3lib_div::requireOnce(t3lib_extMgm::extPath('adodb') . 'adodb/adodb.inc.php');

			// Set global variable to automatically quote values for external tables
			global $ADODB_QUOTE_FIELDNAMES;
			$bOldQuoteState = $ADODB_QUOTE_FIELDNAMES;
			$ADODB_QUOTE_FIELDNAMES = TRUE;

			// Get configuration
			$aDBConf   = $this->aConfig['database.'];
			$aFields   = (!empty($aDBConf['fieldconf.'])     ? $aDBConf['fieldconf.']    : array());
			$sIDField  = (!empty($aDBConf['idField'])        ? $aDBConf['idField']       : 'uid');
			$sDriver   = (!empty($aDBConf['driver']))        ? $aDBConf['driver']        : 'mysql';
			$sHost     = (!empty($aDBConf['host']))          ? $aDBConf['host']          : 'localhost';
			$sHost    .= (!empty($aDBConf['port']))          ? ':' . $aDBConf['port']    : '';
			$sUsername = (!empty($aDBConf['username']))      ? $aDBConf['username']      : '';
			$sPassword = (!empty($aDBConf['password']))      ? $aDBConf['password']      : '';
			$sCharset  = (!empty($aDBConf['force_charset'])) ? $aDBConf['force_charset'] : '';
			$iReturn   = 0;

			// Force given charset for field values
			if ($sCharset) {
				$this->oCS->convArray($aFields, $this->sFormChar, $sCharset);
			}

			// Connect
			$oDB = &NewADOConnection($sDriver);
			if (!empty($aDBConf['database'])) {
				$oDB->Connect($sHost, $sUsername, $sPassword, $aDBConf['database']);
			} else {
				$oDB->Connect($sHost, $sUsername, $sPassword);
			}

			// Update
			if (!empty($aFields[$sIDField])) {
				$sWhere  = $sIDField . ' = ' . (int) $aFields[$sIDField];
				$sSelect = 'SELECT COUNT(' . $sIDField . ') FROM ' . $aDBConf['table'] . ' WHERE ' . $sWhere . ' LIMIT 1';
				$oResult = $oDB->Execute($sSelect);

				// Check if row exists and update (else insert new row - see below)
				if ($oResult->RowCount()) {
					if ($oDB->AutoExecute($aDBConf['table'], $aFields, 'UPDATE', $sWhere)) {
						$iReturn = (int) $aFields[$sIDField];
					}
				}
			}

			// Check for unique fields
			if ($this->bHasDuplicates($aFields, $oDB)) {
				$psError = 'Duplicate entries found';
			}

			// Insert
			if (!$iReturn && !$psError && $oDB->AutoExecute($aDBConf['table'], $aFields, 'INSERT')) {
				$iReturn = (int) $oDB->Insert_ID($aDBConf['table']);
			}

			// Check result
			if (!$iReturn && !$psError) {
				$psError = $oDB->ErrorMsg();
			}

			// Close db connection and revert global quoting variable
			$oDB->Close();
			$ADODB_QUOTE_FIELDNAMES = $bOldQuoteState;

			return $iReturn;
		}


		/**
		 * Automatically fill table fields
		 *
		 * @param array $paFields Array with field configuration
		 * @param array $paDBConf Array with database configuration
		 * @return Array with new field configuration
		 */
		protected function aAddAutoFields (array $paFields, array $paDBConf) {
			if (empty($paDBConf['table'])) {
				return $paFields;
			}

			$aColumns   = array();
			$aNewFields = array();

			// Get table columns
			if ($oResult  = $GLOBALS['TYPO3_DB']->sql_query('SHOW COLUMNS FROM ' . $paDBConf['table'])) {
				while ($aRow = $GLOBALS['TYPO3_DB']->sql_fetch_row($oResult)) {
					$aColumns[$aRow[0]] = TRUE;
				}
			}

			if (empty($aColumns)) {
				return $paFields;
			}

			// Add default fields (useDefaultValues is deprecated since 2.5.0)
			if (!empty($paDBConf['autoFillDefault']) || !empty($paDBConf['useDefaultValues'])) {
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
			}

			// Add existing form fields
			if (!empty($paDBConf['autoFillExisting'])) {
				foreach ($this->aFields as $sKey => $aField) {
					$sDBField = (!empty($aField['dbField']) ? $aField['dbField'] : $sKey);
					if (!empty($aField['value']) && isset($aColumns[$sDBField]) && empty($aField['dbNoAutofill'])) {
						$aNewFields[$sDBField] = $aField['value'];
					}
				}
			}

			// Do not overwrite manually configured fields
			return $paFields + $aNewFields;
		}


		/**
		 * Check for unique fields
		 *
		 * @param array $paFields Array with field configuration
		 * @param object $poConnection External DB Connection
		 * @return boolean TRUE if any duplicated value was found
		 */
		protected function bHasDuplicates (array $paFields, &$poDB = NULL) {
			if (empty($paFields) || empty($this->aConfig['database.']['uniqueFields'])) {
				return FALSE;
			}

			$aWhere        = array();
			$aUniqueFields = t3lib_div::trimExplode(',', $this->aConfig['database.']['uniqueFields'], TRUE);
			$sColumns      = implode(',', array_keys($paFields));
			$sTable        = $this->aConfig['database.']['table'];

			// Get WHERE statement
			foreach ($aUniqueFields as $sFieldName) {
				if (!empty($this->aFields[$sFieldName]['value'])) {
					$sValue = $this->aFields[$sFieldName]['value'];
					if (empty($poDB)) {
						$sValue = $GLOBALS['TYPO3_DB']->fullQuoteStr($sValue, $sTable);
					} else {
						$sValue = $poDB->qstr($sValue);
					}
					$aWhere[] = $sFieldName . ' = ' . $sValue;
				}
			}
			if (empty($aWhere)) {
				return FALSE;
			}

			// Get existing rows
			$sWhere = implode(' OR ', $aWhere);
			$aRows  = array();
			if (empty($poDB)) {
				$aRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($sColumns, $sTable, $sWhere);
			} else {
				$sSelect = 'SELECT ' . $sColumns . ' FROM ' . $sTable . ' WHERE ' . $sWhere;
				$oResult = $oDB->Execute($sSelect);
				if (!empty($oResult)) {
					$aRows = $oDB->GetArray();
				}
			}
			if (empty($aRows)) {
				return FALSE;
			}

			// Get not unique fields
			foreach ($paFields as $sKey => $sValue) {
				foreach ($aRows as $aRow) {
					if (empty($aRow[$sKey]) || $aRow[$sKey] != $sValue) {
						continue;
					}
					foreach ($this->aFields as $sFieldName => $aField) {
						if ($sFieldName == $sKey || $aField['dbField'] == $sKey) {
							$this->aUniqueErrors[$sFieldName] = array('key' => 'msg_' . $sFieldName . '_not_unique');
						}
					}
				}
			}

			return !empty($this->aUniqueErrors);
		}


		/**
		 * Get errors for duplicated fields
		 *
		 * @return array Duplicated field errors
		 */
		public function aGetUniqueErrors () {
			return $this->aUniqueErrors;
		}


		/**
		 * Check if an external database is configured
		 *
		 * @return boolean TRUE if external db config was found
		 */
		public function bIsExternalDB () {
			$aKeys = array('driver', 'host', 'port', 'database', 'username', 'password');
			foreach ($aKeys as $sKey) {
				if (!empty($this->aConfig['database.'][$sKey])) {
					return TRUE;
				}
			}

			return FALSE;
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_db.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_db.php']);
	}
?>