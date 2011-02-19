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
	 * Template class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_template {
		protected $oCObj        = NULL;
		protected $oCS          = NULL;
		protected $aLL          = array();
		protected $aConfig      = array();
		protected $aFields      = array();
		protected $aGP          = array();
		protected $aMarkers     = array();
		protected $sExtKey      = '';
		protected $sFormChar    = '';
		protected $sFieldPrefix = '';
		protected $sBECharset   = '';
		protected $iPluginId    = 0;


		/**
		 * Set configuration for template object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj        = &$poParent->cObj;
			$this->oCS          = &$poParent->oCS;
			$this->aLL          = &$poParent->aLL;
			$this->aConfig      = &$poParent->aConfig;
			$this->aFields      = &$poParent->aFields;
			$this->aGP          = &$poParent->aGP;
			$this->sExtKey      = &$poParent->extKey;
			$this->iPluginId    = &$poParent->iPluginId;
			$this->sFormChar    = &$poParent->sFormCharset;
			$this->sFieldPrefix = &$poParent->sFieldPrefix;
			$this->aMarkers     = &$poParent->aMarkers;
			$this->sBECharset   = &$poParent->sBECharset;

			// Set default markers
			$this->vAddMarkers($this->aGetDefaultMarkers());

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
			$aMarkers['HIDDEN']       = '';
			$aMarkers['MESSAGES']     = '';
			$aMarkers['INFO']         = '';
			$aMarkers['ANCHOR']       = '';

			// Anchor
			if (!empty($this->aConfig['redirectToAnchor'])) {
				$aMarkers['ANCHOR'] = '<a id="p' . $this->oCObj->data['uid'] . '" name="p' . $this->oCObj->data['uid'] . '"></a>';
			}

			// Get wrap for hidden fields
			$sHiddenWrap = '<input type="text" name="|" value="" />';
			if (!empty($this->aConfig['hiddenWrap']) && strpos($sHiddenWrap, '|') !== FALSE) {
				$sHiddenWrap = $this->aConfig['hiddenWrap'];
			}

			// Fields
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sFieldName => $aField) {
					$sFieldName = strtolower(trim($sFieldName, ' .{}()='));
					$aMarkers['HIDDEN']               .= str_replace('|', $sFieldName, $sHiddenWrap) . PHP_EOL;
					$aMarkers[$aField['valueName']]    = htmlspecialchars($aField['value']);
					$aMarkers[$aField['labelName']]    = $aField['label'];
					$aMarkers[$aField['messageName']]  = '';
					$aMarkers[$aField['errClassName']] = (!empty($this->aConfig['classNoError'])) ? $this->aConfig['classNoError'] : '';
					$aMarkers[$aField['checkedName']]  = '';
					$aMarkers[$aField['multiChkName']] = '';
					$aMarkers[$aField['multiSelName']] = '';
					$aMarkers[$aField['requiredName']] = (!empty($aField['required'])) ? $this->aLL['required'] : '';

					if (!empty($this->aGP[$sFieldName]) || (bool) $aField['value']) {
						$aMarkers[$aField['checkedName']]  = 'checked="checked"';
						$aMarkers[$aField['multiChkName']] = 'checked="checked"';
						$aMarkers[$aField['multiSelName']] = 'selected="selected"';
					}
				}
			}

			// Do not use hidden fields
			if (empty($this->aConfig['useHiddenFieldsCheck'])) {
				$aMarkers['HIDDEN'] = '';
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
		 * Add an info text
		 *
		 * @param string  $psIdentifier Key of the info text in locallang array
		 * @param string  $pmReplace    Text to add to message
		 * @param boolean $pbIsPositive Defines the info type
		 */
		public function vAddInfo ($psIdentifier, $pmReplace = '', $pbIsPositive = FALSE) {
			if (empty($psIdentifier)) {
				return;
			}

			$sWrapKey = ($pbIsPositive ? 'infoWrapPositive' : 'infoWrapNegative');
			$sWrap    = (!empty($this->aConfig[$sWrapKey]) ? $this->aConfig[$sWrapKey] : '|');
			$sMessage = (!empty($this->aLL[$psIdentifier]) ? $this->aLL[$psIdentifier] : '');

			// Add replace text to message
			if (!empty($pmReplace)) {
				if (is_array($pmReplace)) {
					$sMessage = vprintf($sMessage, $pmReplace);
				} else if (is_string($pmReplace)) {
					$sMessage = sprintf($sMessage, $pmReplace);
				}
			}

			if (!empty($sMessage)) {
				$this->aMarkers['INFO'] = str_replace('|', $sMessage, $sWrap);
			}
		}


		/**
		 * Add errors to template markers
		 *
		 * @param array $paErrors Array of errors
		 */
		public function vAddErrors (array $paErrors) {
			if (empty($paErrors)) {
				return;
			}

			$sErrorClass  = (!empty($this->aConfig['classError'])  ? $this->aConfig['classError']  : 'error');

			foreach ($paErrors as $sFieldName => $sErrorConf) {
				$sMessage = (!empty($this->aLL[$sErrorConf[0]]) ? $this->aLL[$sErrorConf[0]] : '');
				$sRuleKey = (!empty($sErrorConf[1]) ? $sErrorConf[1] : '');

				// Add rule value to message
				if (!empty($sRuleKey) && !empty($this->aFields[$sFieldName][$sRuleKey])) {
					$sReplace = $this->aFields[$sFieldName][$sRuleKey];
					if ($this->sBECharset != 'utf-8') {
						$sReplace = $this->oCS->utf8_decode($sReplace, $this->sBECharset);
					}
					$sMessage = sprintf($sMessage, htmlspecialchars($sReplace));
				}

				if (!empty($sMessage)) {
					// Add error class
					if ($this->aConfig['highlightFields']) {
						$this->aMarkers[$this->aFields[$sFieldName]['errClassName']] = $sErrorClass;
					}

					// Wrap error if configured
					if (!empty($this->aConfig['messageWrap'])) {
						$sMessage = str_replace('|', $sMessage, $this->aConfig['messageWrap']);
					}

					$this->aMarkers[$this->aFields[$sFieldName]['messageName']] = $sMessage;
				}
			}
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
		 * @param array $paFields Field names of fields to clear
		 */
		public function vClearFields (array $paFields) {
			$sShow = (!empty($this->aConfig['showMaliciousInput']) ? $this->aConfig['showMaliciousInput'] : '');
			if ($sShow == 'none') {
				$paFields = array_keys($this->aFields);
			}

			if (!empty($paFields)) {
				foreach($paFields as $sFieldName) {
					$this->aMarkers[$this->aFields[$sFieldName]['valueName']] = '';
				}
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

			$aMessages = array();
			foreach ($this->aFields as $sKey => $aField) {
				// Add field names
				$this->aMarkers[$aField['markerName']] = $this->sFieldPrefix . '[' . $sKey . ']';
				$aMultiNames['[' . $aField['labelName'] . ']'] = md5($this->aMarkers[$aField['labelName']]);

				// Get message
				if (!empty($aField['messageName'])) {
					$aMessages[] = $aField['messageName'];
				}
			}

			// Add message list
			if (!empty($aMessages)) {
				$this->aMarkers['MESSAGES'] = '<ul><li>' . implode('</li><li>', $aMessages) . '</li></ul>';
				if (!empty($this->aConfig['messageListWrap'])) {
					$this->aMarkers['MESSAGES'] = str_replace('|', $aMarkers['MESSAGES'], $this->aConfig['messageListWrap']);
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