<?php
	/***************************************************************
	*  Copyright notice
	*
	*  (c) 2009 Kai Vogel <kai.vogel ( at ) speedprogs.de>
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
	 * Check class for the 'sp_bettercontact' extension.
	 *
	 * @author      Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package     TYPO3
	 * @subpackage  tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_check {
		public $aConfig     = array();
		public $aLL         = array();
		public $aPiVars     = array();
		public $aFields     = array();
		public $aMarkers    = array();


		/**
		 * Set configuration for check object
		 *
		 * @param   object  $poParent: Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig  = $poParent->aConfig;
			$this->aFields  = $poParent->aFields;
			$this->aLL      = $poParent->aLL;
			$this->aPiVars  = $poParent->piVars;
		}


		/**
		 * Check for spam
		 *
		 * @return  TRUE if the form was filled by a spam-bot
		 */
		public function bIsSpam () {
			$bRefererCheck = isset($this->aConfig['useRefererCheck']) ? (bool) $this->aConfig['useRefererCheck'] : TRUE;

			// Check referer (TRUE = form was not sent from this server)
			if ($bRefererCheck) {
				$sRefererHost = @parse_url(t3lib_div::getIndpEnv('HTTP_REFERER'), PHP_URL_HOST);
				if ($sRefererHost != t3lib_div::getIndpEnv('HTTP_HOST')) {
					return TRUE;
				}
			}

			// Check hidden fields
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					if (strlen($_POST[$sKey]) || strlen($_GET[$sKey])) {
						return TRUE;
					}
				}
			}

			return FALSE;
		}


		/**
		 * Execute checks and set errors
		 *
		 * @return  FALSE if the form has errors
		 */
		public function bCheckFields () {
			// Return if no data was sent
			if (!is_array($this->aPiVars) || !count($this->aPiVars) || !is_array($this->aFields)) {
				return TRUE;
			}

			$bResult = TRUE;
			foreach ($this->aFields as $sKey => $aField) {

				// Bypass captcha input, it has its own check routine
				if (strtolower($sKey) == 'captcha' && !$this->bCheckCaptcha()) {
					$this->aMarkers[$aField['messageName']] = $this->aLL['msg_captcha'];
					$bResult = FALSE;
					continue;
				}

				// Required
				if (!strlen($aField['value']) && (bool) $aField['required']) {
					$this->aMarkers[$aField['messageName']] = $this->sGetMessage($sKey, 'empty', 'required');
					$bResult = FALSE;
					continue;
				}

				// Stop checking here if no value given
				if (!strlen($aField['value'])) {
					continue;
				}

				// Too short
				if (strlen($aField['minLength']) && (int) $aField['minLength'] && strlen($aField['value']) < $aField['minLength']) {
					$this->aMarkers[$aField['messageName']] = $this->sGetMessage($sKey, 'short', 'minLength');
					$bResult = FALSE;
					continue;
				}

				// Too long
				if (strlen($aField['maxLength']) && (int) $aField['maxLength'] && strlen($aField['value']) > $aField['maxLength']) {
					$this->aMarkers[$aField['messageName']] = $this->sGetMessage($sKey, 'long', 'maxLength');
					$bResult = FALSE;
					continue;
				}

				// Disallowed signs
				if (strlen($aField['disallowed'])) {
					if ($aField['value'] !== str_replace(str_split($aField['disallowed']), '', $aField['value'])) {
						$this->aMarkers[$aField['messageName']] = $this->sGetMessage($sKey, 'disallowed', 'disallowed');
						$bResult = FALSE;
						continue;
					}
				}

				// Allowed signs
				if (strlen($aField['allowed'])) {
					if (strlen(str_replace(str_split($aField['allowed']), '', $aField['value']))) {
						$this->aMarkers[$aField['messageName']] = $this->sGetMessage($sKey, 'allowed', 'allowed');
						$bResult = FALSE;
						continue;
					}
				}

				// Regex
				if (strlen($aField['regex'])) {
					if (!preg_match($aField['regex'], $aField['value'])) {
						$this->aMarkers[$aField['messageName']] = $this->sGetMessage($sKey, 'regex', 'regex');
						$bResult = FALSE;
						continue;
					}
				}
			}

			return $bResult;
		}


		/**
		 * Get formated error message
		 *
		 * @return String with final error message
		 */
		protected function sGetMessage($psName, $psIdentifier, $psType) {
			if (!strlen($psIdentifier) || !strlen($psName)) {
				return '';
			}

			// Get configuration
			$psName         = trim($psName);
			$psIdentifier   = trim($psIdentifier);
			$psType         = trim($psType);

			// Check for defined signes and replace formated
			if ($sReplace = htmlspecialchars(utf8_decode($this->aFields[$psName][$psType]))) {
			  return sprintf($this->aLL['msg_' . $psName . '_' . $psIdentifier], $sReplace);
			}

			return $this->aLL['msg_' . $psName . '_' . $psIdentifier];
		}


		/**
		 * Check captcha input
		 *
		 * @return FALSE if the captcha test failes
		 */
		protected function bCheckCaptcha () {
			$sExtKey    = strtolower(trim($this->aConfig['captchaSupport']));
			$sInput     = $this->aPiVars['captcha'];
			$bResult    = TRUE;

			if (!strlen($sExtKey) || !t3lib_extMgm::isLoaded($sExtKey)) {
				return TRUE;
			}

			// Check captcha
			switch ($sExtKey) {
				case 'sr_freecap' :
					if (!strlen($sInput)) {
						$bResult    = FALSE;
					} else {
						require_once(t3lib_extMgm::extPath($sExtKey) . 'pi2/class.tx_srfreecap_pi2.php');
						$oCaptcha   = t3lib_div::makeInstance('tx_srfreecap_pi2');
						$bResult    = $oCaptcha->checkWord($sInput);
					}
					break;
				case 'jm_recaptcha' :
					if (!strlen(t3lib_div::_GP('recaptcha_response_field'))) {
						$bResult    = FALSE;
					} else {
						require_once(t3lib_extMgm::extPath($sExtKey) . 'class.tx_jmrecaptcha.php');
						$oCaptcha   = t3lib_div::makeInstance('tx_jmrecaptcha');
						$aResponse  = $oCaptcha->validateReCaptcha();
						if (is_array($aResponse) && count($aResponse)) {
							$bResult = $aResponse['verified'] ? (bool) $aResponse['verified'] : FALSE;
						}
					}
					break;
				case 'captcha' :
					if (!strlen($sInput)) {
						$bResult    = FALSE;
					} else {
						session_start();
						$sCaptcha   = $_SESSION['tx_captcha_string'];
						$bResult    = ($sInput === $sCaptcha);
					}
					break;
				default:
					return FALSE;
			}

			return $bResult;
		}


		/**
		 * Get an array of all fields with malicious content
		 *
		 * @return  Array of bad fields
		 */
		public function aGetBadFields () {
			$aResult    = array();
			$sShow      = strtolower($this->aConfig['showMaliciousInput']);

			if ($sShow == 'clean') {
				foreach($this->aFields as $sKey => $aField) {
					$sMessage = $this->aMarkers[$this->aFields[$sKey]['messageName']];
					if (strlen($sMessage)) {
						$aResult[] = $aField;
					}
				}
			} elseif ($sShow == 'none') {
				$aResult = $this->aFields;
			}

			return $aResult;
		}


		/**
		 * Get an array of error messages from check
		 *
		 * @return  Array of all messages
		 */
		public function aGetMessages () {
			if (!is_array($this->aPiVars)) {
				return array();
			}

			$aMessages = array();
			$sErrorClass = strlen($this->aConfig['errorClass']) ? $this->aConfig['errorClass'] : 'error';

			// Get field order from piVars
			$aFields = $this->aPiVars;
			unset($aFields['submit']);

			foreach($aFields as $sKey => $aField) {
				if (!isset($this->aFields[$sKey]) || !strlen($this->aFields[$sKey]['messageName'])) {
					continue;
				}

				$sMessage = $this->aMarkers[$this->aFields[$sKey]['messageName']];
				if (strlen($sMessage)) {
					// Add message to list
					$aMessages[] = '<li>' . $sMessage . '</li>';

					// Add error class
					if ($this->aConfig['highlightFields']) {
						$this->aMarkers[$this->aFields[$sKey]['errClassName']] = $sErrorClass;
					}
				}
			}

			if (count($aMessages)) {
				$this->aMarkers['###MESSAGES###'] = '<ul>' . implode(PHP_EOL, $aMessages) . '</ul>';
			}

			// Add info text
			$sWrapNegative = $this->aConfig['infoWrapNegative'] ? $this->aConfig['infoWrapNegative'] : '|';
			$sWrapPositive = $this->aConfig['infoWrapPositive'] ? $this->aConfig['infoWrapPositive'] : '|';
			if (count($this->aMarkers)) {
				$this->aMarkers['###INFO###'] = str_replace('|', $this->aLL['msg_check_failed'], $sWrapNegative);
			} else {
				$this->aMarkers['###INFO###'] = str_replace('|', $this->aLL['msg_check_passed'], $sWrapPositive);
			}

			return $this->aMarkers;
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_check.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_check.php']);
	}
?>