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


		/**
		 * Set configuration for check object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig   = &$poParent->aConfig;
			$this->aFields   = &$poParent->aFields;
			$this->aLL       = &$poParent->aLL;
			$this->aGP       = &$poParent->aGP;
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
			if (!empty($this->aConfig['useHiddenFieldsCheck']) && is_array($this->aFields)) {
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
		 * @param array $paErrors Will be filled with field errors if an error occurs
		 * @return FALSE if the form has errors
		 */
		public function bCheckFields (array &$paErrors) {
			// Return if no data was sent
			if (empty($this->aFields) || !is_array($this->aFields)) {
				return TRUE;
			}

			$bResult = TRUE;

			foreach ($this->aFields as $sFieldName => $aField) {

				// Bypass captcha input, it has its own check routine
				if (strtolower($sKey) == 'captcha' && !$this->bCheckCaptcha()) {
					$paErrors[$sFieldName] = array('msg_captcha');
					$bResult = FALSE;
					continue;
				}

				// Required
				if (!strlen($aField['value']) && (bool) $aField['required']) {
					$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_empty', 'required');
					$bResult = FALSE;
					continue;
				}

				// Stop checking here if no value given
				if (!strlen($aField['value'])) {
					continue;
				}

				// Too short
				if (strlen($aField['minLength']) && (int) $aField['minLength'] && strlen($aField['value']) < $aField['minLength']) {
					$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_short', 'minLength');
					$bResult = FALSE;
					continue;
				}

				// Too long
				if (strlen($aField['maxLength']) && (int) $aField['maxLength'] && strlen($aField['value']) > $aField['maxLength']) {
					$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_long', 'maxLength');
					$bResult = FALSE;
					continue;
				}

				// Disallowed signs
				if (strlen($aField['disallowed'])) {
					if ($aField['value'] !== str_replace(str_split($aField['disallowed']), '', $aField['value'])) {
						$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_disallowed', 'disallowed');
						$bResult = FALSE;
						continue;
					}
				}

				// Allowed signs
				if (strlen($aField['allowed'])) {
					if (strlen(str_replace(str_split($aField['allowed']), '', $aField['value']))) {
						$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_allowed', 'allowed');
						$bResult = FALSE;
						continue;
					}
				}

				// Regex
				if (strlen($aField['regex'])) {
					if (!preg_match($aField['regex'], $aField['value'])) {
						$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_regex', 'regex');
						$bResult = FALSE;
						continue;
					}
				}
			}

			return $bResult;
		}


		/**
		 * Check uploaded files
		 *
		 * @param array $paFiles Uploaded files
		 * @param array $paErrors Will be filled with field errors if an error occurs
		 * @return FALSE if the file check fails
		 */
		public function bCheckFiles (array $paFiles, array &$paErrors) {
			if (empty($this->aFields) || empty($paFiles)) {
				return TRUE;
			}

			$bResult = TRUE;

			foreach($paFiles as $sFieldName => $aFile) {
				$aField = (!empty($this->aFields[$sFieldName]) ? $this->aFields[$sFieldName] : array());

				// Required
				if (!empty($aField['value']) && empty($aFile['path'])) {
					$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_file_invalid');
					$bResult = FALSE;
					continue;
				}

				// Check max file size
				if (strlen($aField['fileMaxSize']) && $aFile['size'] > (int) $aField['fileMaxSize']) {
					$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_file_big', 'fileMaxSize');
					$bResult = FALSE;
					continue;
				}

				// Check min file size
				if (strlen($aField['fileMinSize']) && $aFile['size'] < (int) $aField['fileMinSize']) {
					$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_file_small', 'fileMinSize');
					$bResult = FALSE;
					continue;
				}

				// Check allowed file types
				if (strlen($aField['fileAllowed'])) {
					$aAllowedTypes = t3lib_div::trimExplode(',', $aField['fileAllowed'], TRUE);
					if (!in_array($aFile['type'], $aAllowedTypes)) {
						$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_file_allowed', 'fileAllowed');
						$bResult = FALSE;
						continue;
					}
				}

				// Check disallowed file types
				if (strlen($aField['fileDisallowed'])) {
					$aDisallowedTypes = t3lib_div::trimExplode(',', $aField['fileDisallowed'], TRUE);
					if (in_array($aFile['type'], $aDisallowedTypes)) {
						$paErrors[$sFieldName] = array('msg_' . $sFieldName . '_file_disallowed', 'fileDisallowed');
						$bResult = FALSE;
						continue;
					}
				}
			}

			return $bResult;
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

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_check.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_check.php']);
	}
?>