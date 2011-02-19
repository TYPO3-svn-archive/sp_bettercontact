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


	t3lib_div::requireOnce(PATH_t3lib . 'class.t3lib_extobjbase.php');
	t3lib_div::requireOnce(PATH_t3lib . 'class.t3lib_befunc.php');


	/**
	 * Module Extension 'Better Contact Log' for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_modfunc1 extends t3lib_extobjbase {
		public $modName     = 'tx_spbettercontact_modfunc1';
		public $extKey      = 'sp_bettercontact';
		public $LLkey       = 'default';
		public $sCharset    = '';
		public $sDateFormat = '';
		public $thisPath    = '';
		public $aLL         = array();
		public $aGP         = array();
		public $aConfig     = array();
		public $oTemplate   = NULL;
		public $pObj        = NULL;
		public $oCS         = NULL;
		public $oDB         = NULL;
		public $id          = 0;

		/**
		 * Init module atributes
		 *
		 */
		public function init(&$poPObj, array $paConfig) {
			$this->pObj     = $poPObj;
			$this->id       = $this->pObj->id;
			$this->thisPath = realpath(dirname($paConfig['path']));
			$this->LLkey    = (!empty($GLOBALS['LANG']->lang)) ? $GLOBALS['LANG']->lang : 'default';
			$this->aConfig  = $this->aGetConfig();
			$this->oCS      = $this->oGetCSObject();
			$this->sCharset = $this->sGetBECharset();
			$this->aLL      = $this->aGetLL();
			$this->aGP      = $this->aGetGP();

			// Get default date format
			if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'])) {
				$this->sDateFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'];
			}

			// Set default templates if not configured
			$this->vSetDefaultTemplates();

			// Get module menu
			$aModuleItems         = $this->aGetModMenu();
			$this->pObj->MOD_MENU = $aModuleItems + $this->pObj->MOD_MENU;
		}


		/**
		 * The main method of the module
		 *
		 * @return The content that is displayed in backend site
		 */
		public function main () {
			$this->oTemplate = $this->oMakeInstance('template');
			$this->oDB       = $this->oMakeInstance('db');

			// Get filters
			$iPID      = $this->iGetPID();
			$iPeriod   = $this->iGetPeriod();
			$aSelected = $this->aGetSelected();

			// Delete selected rows if param "del" exists
			if (!empty($this->aGP['del'])) {
				$this->oDB->vRemoveLogRows($aSelected);
			}

			// Get rows from log table
			$aRows = $this->oDB->aGetLogRows($iPID, $iPeriod);
			$aRows = $this->aPrepareLogTableRows($aRows);

			// Get some markers
			$aMarkers = array(
				'###SUB_TEMPLATE_LOG_TABLE_ROWS###' => $aRows,
				'###SUB_TEMPLATE_CSV_ROWS###'       => $aRows,
				'###MENU_MODE###'                   => $this->sGetFuncMenu('mode'),
				'###MENU_PERIOD###'                 => $this->sGetFuncMenu('period'),
				'###ROW_COUNT###'                   => count($aRows),
			);

			// No rows found
			if (empty($aRows)) {
				$aMarkers['###INFO###'] = $this->aLL['msg_no_rows'];
				$this->oTemplate->vAddMarkers($aMarkers);
				return $this->oTemplate->sGetContent();
			}

			// Get template content
			$this->oTemplate->vAddMarkers($aMarkers);
			$sContent = $this->oTemplate->sGetContent();

			// Create CSV if param "csv" exists
			if (!empty($this->aGP['csv'])) {
				// Filter rows
				$aRows = $this->aFilterLogTableRows($aRows, $aSelected); 
				$aMarkers['###SUB_TEMPLATE_LOG_TABLE_ROWS###'] = $aRows;
				$aMarkers['###SUB_TEMPLATE_CSV_ROWS###']       = $aRows;
				$aMarkers['###ROW_COUNT###']                   = count($aRows);

				// Create CSV file
				$oCSV = $this->oMakeInstance('csv');
				$oCSV->vAddMarkers($aMarkers);
				$oCSV->vCreateCSV();
			}

			// Output
			return $sContent;
		}


		/**
		 * Get module configuration
		 *
		 * @return Array with module TSConfig
		 */
		protected function aGetConfig () {
			$aTSConfig = t3lib_BEfunc::getModTSconfig($this->id, 'mod.' . $this->modName);

			if (isset($aTSConfig['properties'])) {
				return $aTSConfig['properties'];
			}

			return array();
		}


		/**
		 * Get charset convert object
		 *
		 * @return Instance of the csConvObj
		 */
		protected function oGetCSObject () {
			if (!class_exists('t3lib_cs')) {
				t3lib_div::requireOnce(PATH_t3lib . 'class.t3lib_cs.php');
			}

			if (isset($GLOBALS['LANG']->csConvObj) && $GLOBALS['LANG']->csConvObj instanceof t3lib_cs) {
				return $GLOBALS['LANG']->csConvObj;
			}

			return t3lib_div::makeInstance('t3lib_cs');
		}


		/**
		 * Get backend charset
		 *
		 * @return Charset of the ts configuration
		 */
		protected function sGetBECharset () {
			$sCharset = 'iso-8859-1';

			if (!empty($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'])) {
				$sCharset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
			} else if (!empty($GLOBALS['LANG']->charSet)) {
				$sCharset = $GLOBALS['LANG']->charSet;
			}

			return strtolower($sCharset);
		}


		/**
		 * Get whole language array
		 *
		 * @return Array of all localized labels
		 */
		protected function aGetLL() {
			$sLangKey    = (strtolower($this->LLkey) == 'en') ? 'default' : $this->LLkey;
			$aLanguages  = ($sLangKey != 'default') ? array('default', $sLangKey) : array('default');
			$sCharset    =
			$aLocalLang  = array();

			// Get all locallang sources
			$aSources = array(
				'ext'  => 'EXT:' . $this->extKey . '/res/templates/backend/locallang.xml',
				'ts'   => (!empty($this->aConfig['_LOCAL_LANG.']))  ? $this->aConfig['_LOCAL_LANG.']  : FALSE,
				'user' => (!empty($this->aConfig['locallangFile'])) ? $this->aConfig['locallangFile'] : FALSE,
			);

			foreach ($aSources as $mSource) {
				if (empty($mSource)) {
					continue;
				}

				// Read language file
				if (is_string($mSource)) {
					$sFile   = t3lib_div::getFileAbsFileName($mSource);
					$mSource = t3lib_div::readLLXMLfile($sFile, $sLangKey, $this->sCharset, TRUE);
				}

				if (!is_array($mSource)) {
					continue;
				}

				// Remove dots from TS keys if any
				$mSource = t3lib_div::removeDotsFromTS($mSource);

				// Combine labels
				foreach ($aLanguages as $sLang) {
					if (!empty($mSource[$sLang]) && is_array($mSource[$sLang])) {
						$aLocalLang = $mSource[$sLang] + $aLocalLang;
					}
				}
			}

			return $aLocalLang;
		}


		/**
		 * Get merged $_GET and $_POST
		 *
		 * @return Array with GPVars
		 */
		protected function aGetGP () {
			$aGP = t3lib_div::array_merge_recursive_overrule($_GET, $_POST);
			t3lib_div::stripSlashesOnArray($aGP);

			// Add default piVars if configured
			if (!empty($this->aConfig['_DEFAULT_PI_VARS.']) && is_array($this->aConfig['_DEFAULT_PI_VARS.'])) {
				$aGP = t3lib_div::array_merge_recursive_overrule($this->aConfig['_DEFAULT_PI_VARS.'], $aGP);
			}

			return $aGP;
		}


		/**
		 * Set default templates if they are not configured
		 *
		 */
		protected function vSetDefaultTemplates () {
			if (!empty($this->aConfig['disableAutoTemplates'])) {
				return;
			}

			// Get filenames
			$aFiles = array(
				'mainTemplate' => 'EXT:' . $this->extKey . '/res/templates/backend/template.html',
				'csvTemplate'  => 'EXT:' . $this->extKey . '/res/templates/backend/csv.html',
			);

			// Add stylesheet only if mainTemplate is empty (will only be used if also not configured)
			if (empty($this->aConfig['mainTemplate'])) {
				$aFiles['stylesheetFile'] = 'EXT:' . $this->extKey . '/res/templates/backend/stylesheet.css';
				$aFiles['javascriptFile'] = 'EXT:' . $this->extKey . '/res/templates/backend/javascript.js';
			}

			// Add only these files which are not configured
			foreach ($aFiles as $sKey => $sFileName) {
				if (empty($this->aConfig[$sKey])) {
					$this->aConfig[$sKey] = $sFileName;
				}
			}
		}


		/**
		 * Returns the module menu
		 *
		 * @return Array with menuitems
		 */
		function aGetModMenu() {
			return array (
				'mode' => array (
					'all'       => $this->aLL['mode_all'],
					'page'      => $this->aLL['mode_this'],
				),
				'period' => array (
					'0'        => $this->aLL['period_all'],
					'365'      => $this->aLL['period_year'],
					'30'       => $this->aLL['period_month'],
					'7'        => $this->aLL['period_week'],
					'1'        => $this->aLL['period_day'],
				),
			);
		}


		/**
		 * Get current view mode
		 *
		 * @return Integer with current Uid
		 */
		protected function iGetPID () {
			return ($this->pObj->MOD_SETTINGS['mode'] == 'page') ? $this->id : 0;
		}


		/**
		 * Get current period
		 *
		 * @return Integer with time period
		 */
		protected function iGetPeriod () {
			if ($iPeriod = $this->pObj->MOD_SETTINGS['period']) {
				return (time() - (int) $iPeriod * 24 * 60 * 60);
			}

			return 0;
		}


		/**
		 * Get selected rows in log table
		 *
		 * @return Array with uids
		 */
		protected function aGetSelected () {
			if (!empty($this->aGP['rows'])) {
				return t3lib_div::intExplode(',', $this->aGP['rows'], TRUE);
			}

			return array();
		}


		/**
		 * Get selector for view mode and period
		 *
		 * @return String with menu
		 */
		protected function sGetFuncMenu ($psType) {
			if (!strlen($psType)) {
				return '';
			}

			return t3lib_BEfunc::getFuncMenu(
				$this->id,
				'SET[' . $psType . ']',
				$this->pObj->MOD_SETTINGS[$psType],
				$this->pObj->MOD_MENU[$psType],
				'index.php'
			);
		}


		/**
		 * Make an instance of any class
		 *
		 * @return Instance of the new object
		 */
		protected function oMakeInstance ($psClassPostfix) {
			if (!strlen($psClassPostfix)) {
				return NULL;
			}

			$sClassName = strtolower($this->modName . '_' . $psClassPostfix);
			$sFileName  = t3lib_extMgm::extPath($this->extKey) . 'modfunc1/class.' . $sClassName . '.php';

			if (@file_exists($sFileName)) {
				t3lib_div::requireOnce($sFileName);

				if (t3lib_div::int_from_ver(TYPO3_version) >= 4003000) {
					return t3lib_div::makeInstance($sClassName, $this);
				}

				$sClass = t3lib_div::makeInstanceClassName($sClassName);
				return new $sClass($this);
			}

			return NULL;
		}


		/**
		 * Prepare log table rows
		 *
		 * @param  array $paRows Table rows
		 * @return Array with rows
		 */
		protected function aPrepareLogTableRows (array $paRows) {
			if (!count($paRows)) {
				return array();
			}

			$aResult = array();

			// Modify rows
			foreach ($paRows as $iKey => $aRow) {
				$aResult[$iKey] = $aRow;

				// Make readable dates
				foreach (array('tstamp', 'crdate') as $sKey) {
					if (empty($this->aConfig['dateFormat'])) {
						$aResult[$iKey][$sKey] = date($this->sDateFormat, $aRow[$sKey]);
					} else {
						$aResult[$iKey][$sKey] = strftime($this->aConfig['dateFormat'], $aRow[$sKey]);
					}
				}

				// Add frontend user information
				if (!empty($aRow['cruser_id'])) {
					$aUser = $this->oDB->aGetUserInfo($aRow['cruser_id']);
					foreach ($aUser as $sKey => $sValue) {
						$aResult[$iKey]['FE_USER_' . $sKey] = $sValue;
					}
				}

				// Add user input in different formats
				if (!empty($aRow['params'])) {
					$aUserInput = json_decode($aRow['params'], TRUE);

					foreach ($aUserInput as $sKey => $sValue) {
						$aResult[$iKey]['input_html']  .= htmlspecialchars($sKey) . ': ' . htmlspecialchars($sValue) . '<br />';
						$aResult[$iKey]['input_plain'] .= $sKey . ': ' . str_replace('"', '”', $sValue) . PHP_EOL; // Replace for CSV
						$aResult[$iKey]['VALUE_' . $sKey] = $sValue;
					}
				}
			}

			return $aResult;
		}


		/**
		 * Filter log table rows with selected UIDs
		 *
		 * @param array $paRows     All log table rows
		 * @param array $paSelected Selected UIDs
		 * @return Array with filtered rows
		 */
		protected function aFilterLogTableRows (array $paRows, array $paSelected = array()) {
			if (empty($paSelected)) {
				return $paRows;
			}

			if (empty($paRows)) {
				return array();
			}

			return array_intersect_key($paRows, array_flip($paSelected));
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/modfunc1/class.tx_spbettercontact_modfunc1.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/modfunc1/class.tx_spbettercontact_modfunc1.php']);
	}
?>