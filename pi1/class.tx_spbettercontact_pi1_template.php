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
	 * Template class for the 'sp_bettercontact' extension.
	 *
	 * @author      Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package     TYPO3
	 * @subpackage  tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_template {
		public $oCObj       = NULL;
		public $aLL         = array();
		public $aConfig     = array();
		public $aFields     = array();
		public $aPiVars     = array();
		public $sFormChar   = 'iso-8859-1';
		public $sExtKey     = '';
		public $sPrefix     = '';
		public $iPluginId   = 0;


		/**
		 * Set configuration for template object
		 *
		 * @param   object   $poParent: Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj        = $poParent->cObj;
			$this->aLL          = $poParent->aLL;
			$this->aConfig      = $poParent->aConfig;
			$this->aFields      = $poParent->aFields;
			$this->aPiVars      = $poParent->piVars;
			$this->sExtKey      = $poParent->extKey;
			$this->sPrefix      = $poParent->prefixId;
			$this->iPluginId    = $poParent->iPluginId;
			$this->sFormChar    = $poParent->sFormCharset;

			// Set default markers
			$this->vAddDefaultMarkers();

			// Add captcha fields
			$this->vAddCaptcha();

			// Set stylesheet
			$this->vSetStylesheet();
		}


		/**
		 * Predefine default markers
		 *
		 */
		protected function vAddDefaultMarkers () {
			$aLinkParams = array(
				'parameter'  => $GLOBALS['TSFE']->id,
				'returnLast' => 'url',
			);

			if ($this->aConfig['redirectToAnchor']) {
				$aLinkParams['section'] = 'p' . $this->oCObj->data['uid'];
			}

			// Default markers
			$this->aMarkers['###URL_SELF###']       = $this->oCObj->typoLink('', $aLinkParams);
			$this->aMarkers['###SUBMIT###']         = $this->sExtKey . '[submit]';
			$this->aMarkers['###SUBMIT_VALUE###']   = $this->aLL['submit'];
			$this->aMarkers['###CHARSET###']        = $this->sFormChar;
			$this->aMarkers['###MESSAGES###']       = '';
			$this->aMarkers['###HIDDEN###']         = '';
			$this->aMarkers['###INFO###']           = '';
			$this->aMarkers['###ANCHOR###']         = ($this->aConfig['redirectToAnchor']) ? '<a id="p' . $this->oCObj->data['uid'] . '" name="p' . $this->oCObj->data['uid'] . '"></a>' : '';

			// Fields
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					$sName = strtolower(trim($sKey, ' .{}()='));
					$this->aMarkers['###HIDDEN###']            .= '<input type="text" name="' . $sKey.'" value="" />' . PHP_EOL;
					$this->aMarkers[$aField['valueName']]       = $aField['value'];
					$this->aMarkers[$aField['labelName']]       = $aField['label'];
					$this->aMarkers[$aField['messageName']]     = '';
					$this->aMarkers[$aField['errClassName']]    = '';
					$this->aMarkers[$aField['checkedName']]     = '';
					$this->aMarkers[$aField['multiChkName']]    = '';
					$this->aMarkers[$aField['multiSelName']]    = '';
					if (isset($this->aPiVars[$sName]) || (bool) $aField['value']) {
						$this->aMarkers[$aField['checkedName']]     = 'checked="checked"';
						$this->aMarkers[$aField['multiChkName']]    = 'checked="checked"';
						$this->aMarkers[$aField['multiSelName']]    = 'selected="selected"';
					}
				}
			}

			// User defined markers
			if (is_array($this->aConfig['markers.'])) {
				$sName = '';
				$sType = '';

				foreach ($this->aConfig['markers.'] as $sKey => $mValue) {
					if (substr($sKey, -1) !== '.' && is_string($mValue)) {
						$sName = $sKey;
						$sType = $mValue;
					} else if ($sName !== '' && $sType !== '') {
						$this->aMarkers['###' . strtoupper($sName) . '###'] = $this->oCObj->cObjGetSingle($sType, $mValue);
						$sName = '';
						$sType = '';
					}
				}
			}

			// Locallang labels
			if (is_array($this->aLL)) {
				foreach ($this->aLL as $sKey => $sValue) {
					$this->aMarkers['###LLL:' . $sKey . '###'] = $sValue;
				}
			}
		}


		/**
		 * Add captcha markers
		 *
		 */
		protected function vAddCaptcha () {
			$sExtKey    = strtolower(trim($this->aConfig['captchaSupport']));
			$aField     = $this->aFields['captcha'];

			// Add captcha
			if (strlen($sExtKey) && t3lib_extMgm::isLoaded(strtolower(trim($sExtKey)))) {
				$this->aMarkers[$aField['valueName']] = '';

				switch ($sExtKey) {
					case 'sr_freecap' :
						require_once(t3lib_extMgm::extPath($sExtKey) . 'pi2/class.tx_srfreecap_pi2.php');
						$oCaptcha   = t3lib_div::makeInstance('tx_srfreecap_pi2');
						$aMarkers   = $oCaptcha->makeCaptcha();
						$this->vAddMarkers($aMarkers);
						$this->aMarkers['###CAPTCHA_DATA###'] = implode(PHP_EOL, array(
							'<div class="tx_srfreecap_pi2">',
								'<div class="tx_srfreecap_pi2_image">',
									$aMarkers['###SR_FREECAP_IMAGE###'],
								'</div>',
								'<div class="tx_srfreecap_pi2_cant_read">',
									$aMarkers['###SR_FREECAP_CANT_READ###'],
								'</div>',
								'<div class="tx_srfreecap_pi2_accessible">',
									$aMarkers['###SR_FREECAP_ACCESSIBLE###'],
								'</div>',
							'</div>',
						));
					break;
					case 'jm_recaptcha' :
						require_once(t3lib_extMgm::extPath($sExtKey) . 'class.tx_jmrecaptcha.php');
						$oCaptcha = t3lib_div::makeInstance('tx_jmrecaptcha');
						$this->aMarkers['###CAPTCHA_DATA###'] = $oCaptcha->getReCaptcha();
					break;
					case 'captcha' :
						$sFileName = t3lib_extMgm::siteRelPath('captcha') . 'captcha/captcha.php';
						$this->aMarkers['###CAPTCHA_FILE###'] = $sFileName;
						$this->aMarkers['###CAPTCHA_DATA###'] = implode(PHP_EOL, array(
							'<div class="tx_captcha">',
								'<div class="tx_captcha_image">',
									'<img src="' . $sFileName . '" alt="captcha image" />',
								'</div>',
							'</div>',
						));
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

			// Check for extension relative path
			if (substr($sFile, 0, 4) == 'EXT:') {
				list($sExtKey, $sFilePath) = explode('/', substr($sFile, 4), 2);
				$sExtKey = strtolower($sExtKey);

				if ($sExtKey == $this->sExtKey || t3lib_extMgm::isLoaded($sExtKey)) {
					$sFile = t3lib_extMgm::siteRelPath($sExtKey) . $sFilePath;
				}
			}

			if (strlen($sFile)) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->sExtKey] = '<link rel="stylesheet" href="' . $sFile . '" type="text/css" />';
			}
		}


		/**
		 * Merge markers given with class marker array
		 *
		 */
		public function vAddMarkers ($paMarkers) {
			if (!is_array($paMarkers)) {
				return;
			}

			// Combine arrays
			if (count($this->aMarkers)) {
				$this->aMarkers = array_merge($this->aMarkers, $paMarkers);
			} else {
				$this->aMarkers = $paMarkers;
			}
		}


		/**
		 * Remove malicious input from markers
		 *
		 */
		public function vClearMalicious ($paFields) {
			if (!is_array($paFields)) {
				return;
			}

			// Remove field values
			foreach ($paFields as $aField) {
				$this->aMarkers[$aField['valueName']] = '';
			}
		}


		/**
		 * Get content from template and markers
		 *
		 * @return	Whole content
		 */
		public function sGetContent () {
			if (!strlen($this->aConfig['formTemplate'])) {
				return;
			}

			// Add field names
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					$this->aMarkers[$aField['markerName']] = $this->sPrefix . '[' . $sKey . ']';
					$aMultiNames['[' . $aField['labelName'] . ']'] = md5($this->aMarkers[$aField['labelName']]);
				}
			}

			// Get templates and markers
			$sRessource     = $this->oCObj->fileResource($this->aConfig['formTemplate']);
			$sTemplate      = $this->oCObj->getSubpart($sRessource, '###TEMPLATE###');
			$aSpecial       = $this->aGetSpecialMarkers($sTemplate);

			// Add captcha if configured
			if (strlen($this->aConfig['captchaSupport']) && strlen($this->aMarkers['###CAPTCHA_DATA###'])) {
				$sCaptchaExt    = strtoupper($this->aConfig['captchaSupport']);
				$sCaptchaTmpl   = $this->oCObj->getSubpart($sRessource, '###SUB_TEMPLATE_' . $sCaptchaExt . '###');
				$this->aMarkers['###CAPTCHA_FIELD###'] = $this->oCObj->substituteMarkerArray($sCaptchaTmpl, $this->aMarkers);
			}

			// Build output
			$sTemplate      = $this->oCObj->substituteMarkerArray($sTemplate, $aSpecial);
			$sContent       = $this->oCObj->substituteMarkerArray($sTemplate, $this->aMarkers);
			$sContent       = preg_replace('|###.*?###|i', '', $sContent);

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

			$aResults   = array();
			$aMarkers   = array();

			preg_match_all('|_\[.*?\]#|i', $psTemplate, $aResults);

			if (isset($aResults[0]) && is_array($aResults[0])) {
				foreach ($aResults[0] as $sValue) {
					if (strpos($sValue, '###') !== FALSE) {
						$sName = substr($sValue, 2, -2);
						$aMarkers['[' . $sName . ']'] = md5($this->aMarkers[$sName]);
						continue;
					} else {
						$sName = substr($sValue, 1, -1);
						$aMarkers[$sName] = md5(substr($sValue, 2, -2));
					}
				}
			}

			return $aMarkers;
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_template.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_template.php']);
	}
?>