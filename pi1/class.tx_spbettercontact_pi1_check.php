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
	 * Check class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_check {
		protected $aConfig    = array();
		protected $aLL        = array();
		protected $aGP        = array();
		protected $aFields    = array();
		protected $aMarkers   = array();
		protected $oCS        = NULL;
		protected $sBECharset = '';


		/**
		 * Set configuration for check object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig = $poParent->aConfig;
			$this->aFields = $poParent->aFields;
			$this->aLL     = $poParent->aLL;
			$this->aGP     = $poParent->aGP;
			$this->oCS     = $poParent->oCS;

			// Get backend charset
			$this->sBECharset = $this->sGetBECharset();
		}


		/**
		 * Get backend charset
		 *
		 * @return Charset of the ts configuration
		 */
		protected function sGetBECharset () {
			$sCharset = (t3lib_div::compat_version('4.5') ? 'utf-8' : 'iso-8859-1');

			if (!empty($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'])) {
				$sCharset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
			} else if (isset($GLOBALS['LANG'])) {
				$sCharset = $GLOBALS['LANG']->charSet;
			}

			return strtolower($sCharset);
		}


		/**
		 * Check for spam
		 *
		 * @return TRUE if the form was filled by a spam-bot
		 */
		public function bIsSpam ($piStartTime = 0) {
			// Check referer (TRUE = form was not sent from current server)
			if (!empty($this->aConfig['useRefererCheck'])) {
				$sRefererHost = @parse_url(t3lib_div::getIndpEnv('HTTP_REFERER'), PHP_URL_HOST);
				if ($sRefererHost != t3lib_div::getIndpEnv('HTTP_HOST')) {
					return TRUE;
				}
			}

			// Check hidden fields
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					if (!empty($_POST[$sKey]) || !empty($_GET[$sKey])) {
						return TRUE;
					}
				}
			}

			// Check elapsed time (real users need at least configured period to fill out the form)
			if (!empty($this->aConfig['minElapsedTime']) && !empty($piStartTime)) {
				if (($GLOBALS['SIM_EXEC_TIME'] - $piStartTime) <= (int) $this->aConfig['minElapsedTime']) {
					return TRUE;
				}
			}

			return FALSE;
		}


		/**
		 * Execute checks and set errors
		 *
		 * @return FALSE if the form has errors
		 */
		public function bCheckFields () {
			// Return if no data was sent
			if (empty($this->aGP) || empty($this->aFields) || !is_array($this->aGP)|| !is_array($this->aFields)) {
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
					}
				}
			}

			return $bResult;
		}


		/**
		 * Get formated error message
		 *
		 * @param  string $psName       Name of the field
		 * @param  string $psIdentifier Key to identify a message label
		 * @param  string $psType       Type of the message
		 * @return String with final error message
		 */
		protected function sGetMessage ($psName, $psIdentifier, $psType) {
			if (!strlen($psIdentifier) || !strlen($psName)) {
				return '';
			}

			// Get configuration
			$psName       = trim($psName);
			$psIdentifier = trim($psIdentifier);
			$psType       = trim($psType);

			// Get replacement
			$sReplace = $this->aFields[$psName][$psType];

			// Convert to utf-8 for internal use
			if ($this->sBECharset != 'utf-8') {
				$sReplace = $this->oCS->utf8_decode($sReplace, $this->sBECharset);
			}

			// Check for defined signes and replace them
			if (strlen($sReplace)) {
			  return sprintf($this->aLL['msg_' . $psName . '_' . $psIdentifier], htmlspecialchars($sReplace));
			}

			return $this->aLL['msg_' . $psName . '_' . $psIdentifier];
		}


		/**
		 * Check captcha input
		 *
		 * @return FALSE if the captcha test failes
		 */
		protected function bCheckCaptcha () {
			if (empty($this->aConfig['captchaSupport'])) {
				return TRUE;
			}

			$sExtKey = strtolower(trim($this->aConfig['captchaSupport']));
			$sInput  = (!empty($this->aGP['captcha'])) ? $this->aGP['captcha'] : '';
			$bResult = TRUE;

			if (!strlen($sExtKey) || !t3lib_extMgm::isLoaded($sExtKey)) {
				return TRUE;
			}

			// Check captcha
			switch ($sExtKey) {
				case 'sr_freecap' :
					if (!strlen($sInput)) {
						return FALSE;
					}
					t3lib_div::requireOnce(t3lib_extMgm::extPath($sExtKey) . 'pi2/class.tx_srfreecap_pi2.php');
					$oCaptcha = t3lib_div::makeInstance('tx_srfreecap_pi2');
					return $oCaptcha->checkWord($sInput);
					break;
				case 'jm_recaptcha' :
					t3lib_div::requireOnce(t3lib_extMgm::extPath($sExtKey) . 'class.tx_jmrecaptcha.php');
					$oCaptcha  = t3lib_div::makeInstance('tx_jmrecaptcha');
					$aResponse = $oCaptcha->validateReCaptcha();
					return (isset($aResponse['verified']) && (bool) $aResponse['verified']);
					break;
				case 'captcha' :
					session_start();
					$sCaptcha = $_SESSION['tx_captcha_string'];
					return ($sInput === $sCaptcha);
					break;
				case 'mathguard' :
					t3lib_div::requireOnce(t3lib_extMgm::extPath($sExtKey) . 'class.tx_mathguard.php');
					$oCaptcha = t3lib_div::makeInstance('tx_mathguard');
					return $oCaptcha->validateCaptcha();
					break;
				default:
					return FALSE;
			}

			return FALSE;
		}


		/**
		 * Get an array of all fields with malicious content
		 *
		 * @return Array of bad fields
		 */
		public function aGetMaliciousFields () {
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
		 * @return Array of all messages
		 */
		public function aGetMessages () {
			if (empty($this->aGP) || !is_array($this->aGP)) {
				return array();
			}

			$aMessages        = array();
			$sErrorClass      = (!empty($this->aConfig['classError'])       ? $this->aConfig['classError']       : 'error');
			$sWrapMessage     = (!empty($this->aConfig['messageWrap'])      ? $this->aConfig['messageWrap']      : '|');
			$sWrapMessageList = (!empty($this->aConfig['messageListWrap'])  ? $this->aConfig['messageListWrap']  : '|');
			$sWrapNegative    = (!empty($this->aConfig['infoWrapNegative']) ? $this->aConfig['infoWrapNegative'] : '|');
			$sWrapPositive    = (!empty($this->aConfig['infoWrapPositive']) ? $this->aConfig['infoWrapPositive'] : '|');

			// Get field order from GPvars
			$aFields = $this->aGP;
			unset($aFields['submit']);

			foreach($aFields as $sKey => $aField) {
				if (empty($this->aFields[$sKey]['messageName'])) {
					continue;
				}

				$sMessage = $this->aMarkers[$this->aFields[$sKey]['messageName']];
				if (!empty($sMessage)) {
					// Add message to list
					$aMessages[] = '<li>' . $sMessage . '</li>';

					// Add error class
					if ($this->aConfig['highlightFields']) {
						$this->aMarkers[$this->aFields[$sKey]['errClassName']] = $sErrorClass;
					}

					// Wrap message if configured
					if (!empty($sWrapMessage)) {
						$this->aMarkers[$this->aFields[$sKey]['messageName']] = str_replace('|', $sMessage, $sWrapMessage);
					}
				}
			}

			// Add message list
			if (count($aMessages)) {
				$this->aMarkers['MESSAGES'] = '<ul>' . implode(PHP_EOL, $aMessages) . '</ul>';
				if (!empty($sWrapMessageList)) {
					$this->aMarkers['MESSAGES'] = str_replace('|', $this->aMarkers['MESSAGES'], $sWrapMessageList);
				}
			}

			// Add info text
			if (count($this->aMarkers)) {
				$this->aMarkers['INFO'] = str_replace('|', $this->aLL['msg_check_failed'], $sWrapNegative);
			} else {
				$this->aMarkers['INFO'] = str_replace('|', $this->aLL['msg_check_passed'], $sWrapPositive);
			}

			return $this->aMarkers;
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_check.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_check.php']);
	}
?>