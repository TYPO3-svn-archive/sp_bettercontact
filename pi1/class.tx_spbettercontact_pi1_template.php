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
	 * Template class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_template {
		protected $oCObj        = NULL;
		protected $aLL          = array();
		protected $aConfig      = array();
		protected $aFields      = array();
		protected $aGP          = array();
		protected $aMarkers     = array();
		protected $sExtKey      = 'sp_bettercontact';
		protected $sFormChar    = 'iso-8859-1';
		protected $sFieldPrefix = '';
		protected $iPluginId    = 0;


		/**
		 * Set configuration for template object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj        = $poParent->cObj;
			$this->aLL          = $poParent->aLL;
			$this->aConfig      = $poParent->aConfig;
			$this->aFields      = $poParent->aFields;
			$this->aGP          = $poParent->aGP;
			$this->sExtKey      = $poParent->extKey;
			$this->iPluginId    = $poParent->iPluginId;
			$this->sFormChar    = $poParent->sFormCharset;
			$this->sFieldPrefix = $poParent->sFieldPrefix;

			// Set default markers
			$this->aMarkers = $this->aGetDefaultMarkers();

			// Add captcha fields
			$this->vAddCaptcha();

			// Set stylesheet
			$this->vSetStylesheet();
		}


		/**
		 * Predefine default markers
		 *
		 * @return Array with markers
		 */
		protected function aGetDefaultMarkers () {
			$aMarkers = array();

			// Default markers
			$aMarkers['URL_SELF']     = $this->sGetSelfLink();
			$aMarkers['FORM_NAME']    = $this->sFieldPrefix . '[form]';
			$aMarkers['FORM_ID']      = $this->sFieldPrefix;
			$aMarkers['SUBMIT']       = $this->sFieldPrefix . '[submit]';
			$aMarkers['SUBMIT_VALUE'] = $this->aLL['submit'];
			$aMarkers['CHARSET']      = $this->sFormChar;
			$aMarkers['HIDDEN']       = PHP_EOL;
			$aMarkers['MESSAGES']     = '';
			$aMarkers['INFO']         = '';
			$aMarkers['ANCHOR']       = '';

			// Anchor
			if (!empty($this->aConfig['redirectToAnchor'])) {
				$aMarkers['ANCHOR'] = '<a id="p' . $this->oCObj->data['uid'] . '" name="p' . $this->oCObj->data['uid'] . '"></a>';
			}

			// Fields
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					$sName = strtolower(trim($sKey, ' .{}()='));
					$aMarkers['HIDDEN']               .= '<input type="text" name="' . $sKey.'" value="" />' . PHP_EOL;
					$aMarkers[$aField['valueName']]    = htmlspecialchars($aField['value']);
					$aMarkers[$aField['labelName']]    = $aField['label'];
					$aMarkers[$aField['messageName']]  = '';
					$aMarkers[$aField['errClassName']] = (!empty($this->aConfig['classNoError'])) ? $this->aConfig['classNoError'] : '';
					$aMarkers[$aField['checkedName']]  = '';
					$aMarkers[$aField['multiChkName']] = '';
					$aMarkers[$aField['multiSelName']] = '';
					$aMarkers[$aField['requiredName']] = (!empty($aField['required'])) ? $this->aLL['required'] : '';

					if (!empty($this->aGP[$sName]) || (bool) $aField['value']) {
						$aMarkers[$aField['checkedName']]  = 'checked="checked"';
						$aMarkers[$aField['multiChkName']] = 'checked="checked"';
						$aMarkers[$aField['multiSelName']] = 'selected="selected"';
					}
				}
			}

			// Page info
			if (!empty($GLOBALS['TSFE']->page) && is_array($GLOBALS['TSFE']->page)) {
				foreach ($GLOBALS['TSFE']->page as $sKey => $sValue) {
					$aMarkers['PAGE:' . $sKey] = $sValue;
				}
			}

			// Plugin info
			if (!empty($this->oCObj->data) && is_array($this->oCObj->data)) {
				foreach ($this->oCObj->data as $sKey => $sValue) {
					$aMarkers['PLUGIN:' . $sKey] = $sValue;
				}
			}

			// FE-User info
			if (!empty($GLOBALS['TSFE']->fe_user->user) && is_array($GLOBALS['TSFE']->fe_user->user)) {
				$aUserData = $GLOBALS['TSFE']->fe_user->user;
				foreach ($aUserData as $sKey => $sValue) {
					$aMarkers['USER:' . $sKey] = $sValue;
				}
			}

			// Locallang labels
			if (is_array($this->aLL)) {
				foreach ($this->aLL as $sKey => $sValue) {
					$aMarkers['LLL:' . $sKey] = $sValue;
				}
			}

			// User defined markers
			if (!empty($this->aConfig['markers.']) && is_array($this->aConfig['markers.'])) {
				foreach ($this->aConfig['markers.'] as $sKey => $sValue) {
					$aMarkers[strtoupper($sKey)] = $sValue;
				}
			}

			return $aMarkers;
		}


		/**
		 * Get URL to current page
		 *
		 * @return URL to current page
		 */
		protected function sGetSelfLink () {
			// Default link config
			$aLinkParams = array(
				'parameter'       => $GLOBALS['TSFE']->id,
				'returnLast'      => 'url',
				'addQueryString'  => 1,
				'addQueryString.' => array(
					'method'  => 'GET',
					'exclude' => 'id',
				),
			);

			// Add anchor
			if ($this->aConfig['redirectToAnchor']) {
				$aLinkParams['section'] = 'p' . $this->oCObj->data['uid'];
			}

			// Get URL
			$sURL = $this->oCObj->typoLink('', $aLinkParams);
			return t3lib_div::locationHeaderUrl($sURL);
		}


		/**
		 * Merge given markers with global marker array
		 *
		 */
		public function vAddMarkers (array $paMarkers) {
			$this->aMarkers = $paMarkers + $this->aMarkers;
		}


		/**
		 * Add captcha markers
		 *
		 */
		protected function vAddCaptcha () {
			$sExtKey = strtolower(trim($this->aConfig['captchaSupport']));
			$aField  = $this->aFields['captcha'];

			// Add captcha
			if (strlen($sExtKey) && t3lib_extMgm::isLoaded(strtolower(trim($sExtKey)))) {
				$this->aMarkers[$aField['valueName']] = '';

				switch ($sExtKey) {
					case 'sr_freecap' :
						t3lib_div::requireOnce(t3lib_extMgm::extPath($sExtKey) . 'pi2/class.tx_srfreecap_pi2.php');
						$oCaptcha = t3lib_div::makeInstance('tx_srfreecap_pi2');
						$aMarkers = $oCaptcha->makeCaptcha();
						foreach ($aMarkers as $sKey => $sValue) {
							$this->aMarkers[trim($sKey, '#')] = $sValue;
						}
						$this->aMarkers['CAPTCHA_DATA'] = implode(PHP_EOL, array(
							'<div class="tx_srfreecap_pi2">',
								'<div class="tx_srfreecap_pi2_image">',
									$this->aMarkers['SR_FREECAP_IMAGE'],
								'</div>',
								'<div class="tx_srfreecap_pi2_cant_read">',
									$this->aMarkers['SR_FREECAP_CANT_READ'],
								'</div>',
								'<div class="tx_srfreecap_pi2_accessible">',
									$this->aMarkers['SR_FREECAP_ACCESSIBLE'],
								'</div>',
							'</div>',
						));
					break;
					case 'jm_recaptcha' :
						t3lib_div::requireOnce(t3lib_extMgm::extPath($sExtKey) . 'class.tx_jmrecaptcha.php');
						$oCaptcha = t3lib_div::makeInstance('tx_jmrecaptcha');
						$this->aMarkers['CAPTCHA_DATA'] = $oCaptcha->getReCaptcha();
					break;
					case 'captcha' :
						$sFileName = t3lib_extMgm::siteRelPath('captcha') . 'captcha/captcha.php';
						$this->aMarkers['CAPTCHA_FILE'] = $sFileName;
						$this->aMarkers['CAPTCHA_DATA'] = implode(PHP_EOL, array(
							'<div class="tx_captcha">',
								'<div class="tx_captcha_image">',
									'<img src="' . $sFileName . '" alt="captcha image" />',
								'</div>',
							'</div>',
						));
					break;
					case 'mathguard' :
						t3lib_div::requireOnce(t3lib_extMgm::extPath($sExtKey) . 'class.tx_mathguard.php');
						$oCaptcha = t3lib_div::makeInstance('tx_mathguard');
						$this->aMarkers['CAPTCHA_DATA'] = $oCaptcha->getCaptcha();
					break;
					default:
						return;
					break;
				}
			}
		}


		/**
		 * Add stylesheet to HTML head
		 *
		 */
		protected function vSetStylesheet () {
			$sFile = $this->aConfig['stylesheetFile'];
			$sTag  = '<link rel="stylesheet" href="%s" type="text/css" />';

			// Check for extension relative path
			if (substr($sFile, 0, 4) == 'EXT:') {
				list($sExtKey, $sFilePath) = explode('/', substr($sFile, 4), 2);
				$sExtKey = strtolower($sExtKey);

				if ($sExtKey == $this->sExtKey || t3lib_extMgm::isLoaded($sExtKey)) {
					$sFile = t3lib_extMgm::siteRelPath($sExtKey) . $sFilePath;
				}
			}

			if (strlen($sFile)) {
				$sFile = t3lib_div::locationHeaderUrl($sFile);
				$GLOBALS['TSFE']->additionalHeaderData[md5($sFile)] = sprintf($sTag, $sFile);
			}
		}


		/**
		 * Clear given input fields in marker array
		 *
		 */
		public function vClearFields (array $paFields) {
			foreach ($paFields as $aField) {
				$this->aMarkers[$aField['valueName']] = '';

				// Fixes issue #30600 (Clear all input fields if form was successfully sent)
				$this->aMarkers[$aField['checkedName']]  = '';
				$this->aMarkers[$aField['multiChkName']] = '';
				$this->aMarkers[$aField['multiSelName']] = '';
			}
		}


		/**
		 * Get content from template and markers
		 *
		 * @return Whole content
		 */
		public function sGetContent () {
			if (empty($this->aConfig['formTemplate'])) {
				return;
			}

			// Add field names
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					$this->aMarkers[$aField['markerName']] = $this->sFieldPrefix . '[' . $sKey . ']';
					$aMultiNames['[' . $aField['labelName'] . ']'] = md5($this->aMarkers[$aField['labelName']]);
				}
			}

			// Get templates and markers
			$sRessource = $this->oCObj->fileResource($this->aConfig['formTemplate']);
			$sTemplate  = $this->oCObj->getSubpart($sRessource, '###TEMPLATE###');
			$sTemplate  = trim($sTemplate, "\n");
			$aSpecial   = $this->aGetSpecialMarkers($sTemplate); // Replace sepcial markers like checked radio buttons
			$sTemplate  = $this->oCObj->substituteMarkerArray($sTemplate, $aSpecial, '[###|###]');

			// Add captcha if configured
			if (strlen($this->aConfig['captchaSupport']) && strlen($this->aMarkers['CAPTCHA_DATA'])) {
				$sCaptchaExt  = strtoupper($this->aConfig['captchaSupport']);
				$sCaptchaTmpl = $this->oCObj->getSubpart($sRessource, '###SUB_TEMPLATE_' . $sCaptchaExt . '###');
				$sCaptchaTmpl = trim($sCaptchaTmpl, "\n");
				$this->aMarkers['CAPTCHA_FIELD'] = $this->oCObj->substituteMarkerArray($sCaptchaTmpl, $this->aMarkers, '###|###');
			}

			// Get content
			$sContent = $this->oCObj->substituteMarkerArray($sTemplate, $this->aMarkers, '###|###', FALSE);
			$sContent = preg_replace('|###.*?###|i', '', $sContent); // removes also markers with colon

			return $sContent;
		}



		/**
		 * Get names to replace for radio buttons and select options
		 *
		 * @return Array with replacements
		 */
		protected function aGetSpecialMarkers ($psTemplate) {
			if (!strlen($psTemplate)) {
				return array();
			}

			$aResults = array();
			$aMarkers = array();

			preg_match_all('|_\[.*?\]#|i', $psTemplate, $aResults);

			if (empty($aResults[0]) || !is_array($aResults[0])) {
				return array();
			}

			foreach ($aResults[0] as $sValue) {
				$sName   = substr($sValue, 5, -5);
				$sMarker = (isset($this->aMarkers[$sName])) ? $this->aMarkers[$sName] : '';
				$sValue  = (strpos($sValue, '###') !== FALSE) ? $sMarker : $sName;
				$aMarkers[$sName] = md5($sValue);
			}

			return $aMarkers;
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_template.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_template.php']);
	}
?>