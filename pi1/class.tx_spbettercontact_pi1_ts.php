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
	 * TypoScript parser class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_ts {
		protected $oCObj   = NULL;
		protected $aConfig = array();


		/**
		 * Set configuration for ts parser object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj   = $poParent->cObj;
			$this->aConfig = $poParent->aConfig;
		}


		/**
		 * Get merged config from TS and FlexForm
		 *
		 * @return Array with configuration
		 */
		public function aGetConfig (array $paConfig) {
			// Parse TypoScript configuration
			$aResult = $this->aParseTS($paConfig);

			// Stop here if no Flexform found
			if (empty($this->oCObj->data['pi_flexform'])) {
				return $aResult;
			}

			// Get flexform content
			$mFlex = t3lib_div::xml2array($this->oCObj->data['pi_flexform']);
			if (!is_array($mFlex)) {
				return $aResult;
			}

			// Check if DB tab is visible in Flexform
			$bShowDBTab = FALSE;
			if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sp_bettercontact'])) {
				$aConfig    = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sp_bettercontact']);
				$bShowDBTab = !empty($aConfig['enableDBTab']);
			}

			// Override TS with FlexForm values
			foreach ($mFlex['data'] as $sTab => $aData) {
				if (empty($aData['lDEF']) && !is_array($aData['lDEF'])) {
					continue;
				}

				// Exclude DB tab if disabled
				if (!$bShowDBTab && $sTab == 'sDB') {
					continue;
				}

				foreach ($aData['lDEF'] as $sKey => $aValue) {
					if (empty($aValue['vDEF'])) {
						continue;
					}

					// Its only a string
					if (strpos($sKey, '.') === FALSE) {
						$aResult[$sKey] = $aValue['vDEF'];
						continue;
					}

					// Build TypoScript array from FlexForm field
					$aResult = $this->aParseTSFromFlex($aResult, $sKey, $aValue['vDEF']);
				}
			}

			return $aResult;
		}


		/**
		 * Parse TypoScript configuration
		 *
		 * @param  mixed $pmConfig Configuration array
		 * @return Array with parsed config
		 */
		public function aParseTS ($paConfig) {
			if (empty($paConfig)) {
				return array();
			}

			// Execute TypoScript
			$aResult = array();
			foreach ($paConfig as $sKey => $mValue) {
				$sIdent = rtrim($sKey, '.');
				if (is_array($mValue)) {
					if (!empty($paConfig[$sIdent])) {
						$aResult[$sIdent] = $this->oCObj->cObjGetSingle($paConfig[$sIdent], $mValue);
					} else {
						$aResult[$sKey] = $this->aParseTS($mValue);
					}
				} else if (is_string($mValue) && $sKey == $sIdent) {
					$aResult[$sKey] = $mValue;
				}
			}

			return $aResult;
		}


		/**
		 * Parse TypoScript configuration from a FlexForm field
		 * 
		 * This method finds include lines at any level of the
		 * configuration and merges them with the other setup.
		 *
		 * @param  array  $paBaseArray Configuration array
		 * @param  string $psKeys      Keys to add
		 * @param  string $psValue     Value to add
		 * @return New configuration array
		 */
		public function aParseTSFromFlex (array $paBaseArray, $psKeys, $psValue) {
			if (!count($paBaseArray) || !strlen($psKeys) || !strlen($psValue)) {
				return $paBaseArray;
			}

			require_once(PATH_t3lib . 'class.t3lib_tsparser.php');

			$aIncludes = array();
			$aParts    = array($psValue);

			// Check for includes
			if (strpos($psValue, 'INCLUDE_TYPOSCRIPT') !== FALSE) {
				// Remove uncommented lines
				$psValue = preg_replace('|^\s*#.*|m', '', $psValue);    // Single line
				$psValue = preg_replace('|\/\*.*\*\/|s', '', $psValue); // Multi line
				$psValue = trim($psValue);

				if (!strlen($psValue)) {
					return $paBaseArray;
				}

				// Get all include lines
				preg_match_all('|<INCLUDE_TYPOSCRIPT:.*>|', $psValue, $aIncludes);
				$aIncludes = (!empty($aIncludes)) ? reset($aIncludes) : array();
				$aIncludes = t3lib_TSparser::checkIncludeLines_array($aIncludes);

				// Get all other TypoScript lines around them
				$aParts = preg_split('|<INCLUDE_TYPOSCRIPT:.*>|', $psValue);
			}

			// Build new TypoScript configuration
			$sWrapMulti  = "plugin.tx_spbettercontact_pi1." . $psKeys . " {\n%s\n}\n";
			$sWrapSingle = "plugin.tx_spbettercontact_pi1." . $psKeys . " = %s\n";
			$sWrap       = (strpos($psValue, "\n") === FALSE) ? $sWrapSingle : $sWrapMulti;
			$iCount      = (count($aIncludes) > count($aParts)) ? count($aIncludes) : count($aParts);
			$sResult     = '';
			for ($i = 0; $i < $iCount; $i++) {
				$sResult .= (!empty($aParts[$i]))    ? sprintf($sWrap, $aParts[$i]) : '';
				$sResult .= (!empty($aIncludes[$i])) ? $aIncludes[$i]               : '';
			}

			if (!strlen($sResult)) {
				return $paBaseArray;
			}

			// Parse TypoScript into array
			$aResult = array();
			$oParser = t3lib_div::makeInstance('t3lib_TSparser');
			$oParser->parse($sResult);
			$aResult = $oParser->setup;
			unset($oParser);

			// Combine arrays
			$aBase['plugin.']['tx_spbettercontact_pi1.'] = $paBaseArray;
			$aResult = array_merge_recursive($aBase, $aResult);

			// Return only plugin configuration
			if (!empty($aResult['plugin.']['tx_spbettercontact_pi1.'])) {
				return $this->aParseTS($aResult['plugin.']['tx_spbettercontact_pi1.']);
			}

			return $paBaseArray;
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_ts.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_ts.php']);
	}
?>