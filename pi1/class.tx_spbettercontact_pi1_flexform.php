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
	 * Class that adds the flexform configuration.
	 *
	 * @author      Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package     TYPO3
	 * @subpackage  tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_flexform {
		public $sWizardIcon     = 'EXT:sp_bettercontact/res/images/popup.gif';
		public $sLabelFile      = 'EXT:sp_bettercontact/locallang.php';


		/**
		 * Get flexform belonging to server configuration
		 *
		 * @return  String with flexform content
		 */
		public function sGetFlexForm () {
			// Get max width of fields belonging to current TYPO3 version
			// We do that because labels are displayed in one line with fields
			$iMaxWidth = (t3lib_div::int_from_ver(TYPO3_version) < 4002000) ? 30 : 40;

			// Begin document
			$oXML = new SimpleXMLElement('<T3DataStructure></T3DataStructure>');

			// Add meta data
			$oMeta = $oXML->addChild('meta');
			$oMeta->addChild('langDisable', '1');
			$oMeta->addChild('langChildren', '0');

			// Add tabs (notice: PHP5 always uses references for objects)
			$oSheets = $oXML->addChild('sheets');
			$aTabs = array('sDEF'=>'','sEMAIL'=>'','sSPAM'=>'');
			foreach($aTabs as $sKey => $sValue) {
				$aTabs[$sKey] = $this->oGetTab($oSheets, $sKey);
			}

			// Add elements to tab "default"
			$this->vAddInput($aTabs['sDEF'], 'redirectPage', $iMaxWidth, '', TRUE, 'page', '');
			$this->vAddCheckBox($aTabs['sDEF'], 'redirectToAnchor', TRUE);
			$this->vAddCheckBox($aTabs['sDEF'], 'highlightFields', FALSE);
			$this->vAddCheckBox($aTabs['sDEF'], 'useDefaultTemplates', TRUE);
			$this->vAddInput($aTabs['sDEF'], 'formTemplate', $iMaxWidth, '', TRUE, 'file', 'html,tmpl');
			$this->vAddInput($aTabs['sDEF'], 'emailTemplate', $iMaxWidth, '', TRUE, 'file', 'html,tmpl');
			$this->vAddInput($aTabs['sDEF'], 'stylesheetFile', $iMaxWidth, '', TRUE, 'file', 'css');
			$this->vAddInput($aTabs['sDEF'], 'locallangFile', $iMaxWidth, '', TRUE, 'file', 'xml');

			// Add elements to tab "email"
			$this->vAddInput($aTabs['sEMAIL'], 'emailRecipients', $iMaxWidth);
			$this->vAddInput($aTabs['sEMAIL'], 'emailSender', $iMaxWidth);
			$this->vAddInput($aTabs['sEMAIL'], 'emailAdmin', $iMaxWidth);
			if (!ini_get('safe_mode')) {
				$this->vAddInput($aTabs['sEMAIL'], 'emailReturnPath');
			}
			$this->vAddDropDown($aTabs['sEMAIL'], 'sendTo', array('','recipients','user','both'));
			$this->vAddDropDown($aTabs['sEMAIL'], 'replyTo', array('','sender','user'));
			if (extension_loaded('mbstring') && function_exists('mb_convert_encoding')) {
				$this->vAddDropDown ($aTabs['sEMAIL'], 'emailCharset', array('','iso-8859-1','iso-8859-15','utf-8','cp866','cp1251','cp1252','koi8-r','big5','gb2312','big5-hkscs','shift_jis','euc-jp'));
			}

			// Add elements to tab "spam"
			$this->vAddDropDown($aTabs['sSPAM'], 'captchaSupport', array('','sr_freecap','jm_recaptcha','captcha'));
			$this->vAddDropDown($aTabs['sSPAM'], 'showMaliciousInput', array('','none','clean','all'));
			$this->vAddDropDown($aTabs['sSPAM'], 'adminMails', array('','none','bot','user','both'));
			$this->vAddCheckBox($aTabs['sSPAM'], 'useRefererCheck', TRUE);
			$this->vAddInput($aTabs['sSPAM'], 'messageCount', 5, '10');
			$this->vAddInput($aTabs['sSPAM'], 'waitingTime', 5, '60');

			return $oXML->asXML();
		}


		/**
		 * Create the XML structure for a new tab
		 *
		 * @return  Object of the "el" child
		 */
		protected function oGetTab ($poSheets, $psName) {
			$oTab = $poSheets->addChild($psName);
			$oRoot = $oTab->addChild('ROOT');
			$oTCEforms = $oRoot->addChild('TCEforms');
			$oTCEforms->addChild('sheetTitle', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName);
			$oType = $oRoot->addChild('type', 'array');

			return $oRoot->addChild('el');
		}


		/**
		 * Add a single line input field
		 *
		 */
		protected function vAddInput ($poTab, $psName, $piWidth=40, $psDefault='', $pbWizard=FALSE, $psType='file', $psExtensions='') {
			$oElement = $poTab->addChild($psName);
			$oTCEforms = $oElement->addChild('TCEforms');
			$oTCEforms->addChild('label', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName);
			$oConfig = $oTCEforms->addChild('config');
			$oConfig->addChild('type', 'input');
			$oConfig->addChild('size', $piWidth);
			$oConfig->addChild('eval', 'trim');
			$oConfig->addChild('default', $psDefault);

			if ($pbWizard) {
				$sOptions = trim(str_replace($psType . ',', '', 'mail,page,file,spec,url,folder,'), ',');
				$oWizard = $oConfig->addChild('wizards');
				$oWizard->addAttribute('type', 'array');
				$oWizard->addChild('_PADDING', '2');
				$oLink = $oWizard->addChild('link');
				$oLink->addAttribute('type', 'array');
				$oLink->addChild('type', 'popup');
				$oLink->addChild('title', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.wizard');
				$oLink->addChild('icon', $this->sWizardIcon);
				$oLink->addChild('script', 'browse_links.php?mode=wizard&amp;act=' . $psType);
				$oLink->addChild('JSopenParams', 'height=300,width=500,status=0,menubar=0,scrollbars=1');
				$oParams = $oLink->addChild('params');
				$oParams->addAttribute('type', 'array');
				$oParams->addChild('blindLinkOptions', $sOptions);
				$oParams->addChild('allowedExtensions', $psExtensions);
			}
		}


		/**
		 * Add a check box field
		 *
		 */
		protected function vAddCheckBox ($poTab, $psName, $pbDefault=TRUE) {
			$oElement = $poTab->addChild($psName);
			$oTCEforms = $oElement->addChild('TCEforms');
			$oTCEforms->addChild('label', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName);
			$oConfig = $oTCEforms->addChild('config');
			$oConfig->addChild('type', 'check');
			$oConfig->addChild('default', (int) $pbDefault);
		}


		/**
		 * Add a drop down field
		 *
		 */
		protected function vAddDropDown ($poTab, $psName, $paItems) {
			$oElement = $poTab->addChild($psName);
			$oTCEforms = $oElement->addChild('TCEforms');
			$oTCEforms->addChild('label', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName);
			$oConfig = $oTCEforms->addChild('config');
			$oConfig->addChild('type', 'select');
			$oConfig->addChild('default', $psDefault);
			$oConfig->addChild('minitems', '0');
			$oConfig->addChild('maxitems', '1');
			$oConfig->addChild('size', '1');
			$oItems = $oConfig->addChild('items');
			$oItems->addAttribute('type', 'array');

			foreach ($paItems as $iKey => $sValue) {
				$oOption = $oItems->addChild('numIndex');
				$oOption->addAttribute('index', $iKey);
				$oOption->addAttribute('type', 'array');
				$oLabel = $oOption->addChild('numIndex', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName . '.' . $iKey);
				$oLabel->addAttribute('index', '0');
				$oValue = $oOption->addChild('numIndex', $sValue);
				$oValue->addAttribute('index', '1');
			}
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_flexform.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_flexform.php']);
	}
?>