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
	 * Class that adds the flexform configuration.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_flexform {
		protected $sWizardIcon     = 'EXT:sp_bettercontact/res/images/popup.gif';
		protected $sLabelFile      = 'EXT:sp_bettercontact/locallang.php';


		/**
		 * Get flexform belonging to server configuration
		 *
		 * @param  boolean $pbShowDBTab Enable additional DB tab in Flexform
		 * @return String with flexform content
		 */
		public function sGetFlexForm ($pbShowDBTab = FALSE) {
			// Begin document
			$oXML = new SimpleXMLElement('<T3DataStructure></T3DataStructure>');

			// Add meta data
			$oMeta = $oXML->addChild('meta');
			$oMeta->addChild('langDisable', '1');
			$oMeta->addChild('langChildren', '0');

			// Add tabs (notice: PHP5 always uses references for objects)
			$oSheets = $oXML->addChild('sheets');
			$aTabs = array('sDEF','sTEMPLATE','sEMAIL','sSPAM');
			if ($pbShowDBTab) {
				$aTabs[] = 'sDB';
			}
			$aTabs = array_flip($aTabs);
			foreach($aTabs as $sKey => $sValue) {
				$aTabs[$sKey] = $this->oGetTab($oSheets, $sKey);
			}

			// Add elements to tab "default"
			$this->vAddInput($aTabs['sDEF'], 'successRedirectPage', 40, '', TRUE, 'page', '');
			$this->vAddInput($aTabs['sDEF'], 'spamRedirectPage', 40, '', TRUE, 'page', '');
			$this->vAddInput($aTabs['sDEF'], 'exhaustedRedirectPage', 40, '', TRUE, 'page', '');
			$this->vAddInput($aTabs['sDEF'], 'errorRedirectPage', 40, '', TRUE, 'page', '');
			$this->vAddCheckBox($aTabs['sDEF'], 'enableLog', TRUE);
			$this->vAddCheckBox($aTabs['sDEF'], 'enableIPLog', FALSE);

			// Add elements to tab "template"
			$this->vAddInput($aTabs['sTEMPLATE'], 'formTemplate', 40, '', TRUE, 'file', 'html,tmpl');
			$this->vAddInput($aTabs['sTEMPLATE'], 'emailTemplate', 40, '', TRUE, 'file', 'html,tmpl');
			$this->vAddInput($aTabs['sTEMPLATE'], 'stylesheetFile', 40, '', TRUE, 'file', 'css');
			$this->vAddInput($aTabs['sTEMPLATE'], 'locallangFile', 40, '', TRUE, 'file', 'xml');
			$this->vAddInput($aTabs['sTEMPLATE'], 'fieldPrefix', 20, '', FALSE, 'page', '', 'trim,nospace,alphanum_x');
			$this->vAddCheckBox($aTabs['sTEMPLATE'], 'disableAutoTemplates', FALSE);
			$this->vAddCheckBox($aTabs['sTEMPLATE'], 'redirectToAnchor', TRUE);
			$this->vAddCheckBox($aTabs['sTEMPLATE'], 'highlightFields', TRUE);
			$this->vAddCheckBox($aTabs['sTEMPLATE'], 'clearOnSuccess', TRUE);

			// Add elements to tab "email"
			$this->vAddInput($aTabs['sEMAIL'], 'emailRecipients', 40);
			$this->vAddInput($aTabs['sEMAIL'], 'emailSender', 40);
			$this->vAddInput($aTabs['sEMAIL'], 'emailAdmin', 40);
			if (!ini_get('safe_mode')) {
				$this->vAddInput($aTabs['sEMAIL'], 'emailReturnPath');
			}
			$this->vAddDropDown($aTabs['sEMAIL'], 'sendTo', array('','recipients','user','both'));
			$this->vAddDropDown($aTabs['sEMAIL'], 'replyTo', array('','sender','user'));
			$this->vAddDropDown ($aTabs['sEMAIL'], 'emailCharset', array('','iso-8859-1','iso-8859-15','utf-8','cp866','cp1251','cp1252','koi8-r','big5','gb2312','big5-hkscs','shift_jis','euc-jp'));
			$this->vAddDropDown ($aTabs['sEMAIL'], 'emailFormat', array('','plain','html'));

			// Add elements to tab "spam"
			$this->vAddDropDown($aTabs['sSPAM'], 'captchaSupport', array('','sr_freecap','jm_recaptcha','captcha','mathguard'));
			$this->vAddDropDown($aTabs['sSPAM'], 'showMaliciousInput', array('','none','clean','all'));
			$this->vAddDropDown($aTabs['sSPAM'], 'adminMails', array('','none','bot','user','both'));
			$this->vAddCheckBox($aTabs['sSPAM'], 'useRefererCheck', FALSE);
			$this->vAddInput($aTabs['sSPAM'], 'messageCount', 5, '10');
			$this->vAddInput($aTabs['sSPAM'], 'waitingTime', 5, '60');

			// Add elements to tab "db"
			if ($pbShowDBTab) {
				$this->vAddInput($aTabs['sDB'], 'database.table', 40);
				$this->vAddCheckBox($aTabs['sDB'], 'database.useDefaultValues', FALSE);
				$this->vAddText($aTabs['sDB'], 'database.fieldconf', 40, 20, $this->sGetDefaultTS(), 'off');
			}

			return $oXML->asXML();
		}


		/**
		 * Create the XML structure for a new tab
		 *
		 * @param  object $poSheets XML node of a tab sheet
		 * @param  string $psName   Name of the new tab
		 * @return Object of the "el" child
		 */
		protected function oGetTab ($poSheet, $psName) {
			$oTab = $poSheet->addChild($psName);
			$oRoot = $oTab->addChild('ROOT');
			$oTCEforms = $oRoot->addChild('TCEforms');
			$oTCEforms->addChild('sheetTitle', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName);
			$oType = $oRoot->addChild('type', 'array');

			return $oRoot->addChild('el');
		}


		/**
		 * Add a single line input field
		 *
		 * @param object  $poTab        XML node of a tab
		 * @param string  $psName       Name of the new input field
		 * @param integer $piWidth      Width of the field
		 * @param string  $psDefault    Any default value
		 * @param boolean $pbWizard     Add a wizard to field
		 * @param string  $psType       Wizard type
		 * @param string  $psExtensions Allowed file extensions
		 * @param string  $psEval       Evaluate value with this functions
		 */
		protected function vAddInput ($poTab, $psName, $piWidth = 40, $psDefault = '', $pbWizard = FALSE, $psType = 'file', $psExtensions = '', $psEval = 'trim') {
			$oElement = $poTab->addChild($psName);
			$oTCEforms = $oElement->addChild('TCEforms');
			$oTCEforms->addChild('label', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName);
			$oConfig = $oTCEforms->addChild('config');
			$oConfig->addChild('type', 'input');
			$oConfig->addChild('size', $piWidth);
			$oConfig->addChild('eval', $psEval);
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
		 * @param object $poTab     XML node of a tab
		 * @param string $psName    Name of the new checkbox
		 * @param string $psDefault Default state of the checkbox
		 */
		protected function vAddCheckBox ($poTab, $psName, $pbDefault = TRUE) {
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
		 * @param object $poTab   XML node of a tab
		 * @param string $psName  Name of the new dropdown
		 * @param array  $paItems Options of the select field
		 */
		protected function vAddDropDown ($poTab, $psName, array $paItems) {
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


		/**
		 * Add a text field
		 *
		 * @param object $poTab      XML node of a tab
		 * @param string $psName     Name of the new dropdown
		 * @param integer $piWidth   Width of the field
		 * @param integer $piHeight  Height of the field in rows
		 * @param string  $psDefault Any default value
		 * @param string  $psWrap    Set wrapping of the textarea field
		 */
		protected function vAddText ($poTab, $psName, $piWidth = 40, $piHeight = 1, $psDefault = '', $psWrap = 'virtual') {
			$oElement = $poTab->addChild($psName);
			$oTCEforms = $oElement->addChild('TCEforms');
			$oTCEforms->addChild('label', 'LLL:' . $this->sLabelFile . ':tt_content.flexform_pi1.' . $psName);
			$oConfig = $oTCEforms->addChild('config');
			$oConfig->addChild('type', 'text');
			$oConfig->addChild('cols', $piWidth);
			$oConfig->addChild('rows', $piHeight);
			$oConfig->addChild('default', $psDefault);
			$oConfig->addChild('wrap', $psWrap);
		}


		/**
		 * Get default TypoScript configuration for fieldconf
		 *
		 * @return String with configuration
		 */
		protected function sGetDefaultTS () {
			$sDefaultTS = <<< END
### Demo configuration (see manual for details) ###

# Add current page id as pid of the new dataset
# pid = TEXT
# pid.data = TSFE : id

# Add creation date automatically
# crdate = TEXT
# crdate.data = date : U

# Set default values for some fields
# hidden  = 0
# deleted = 0

# Save submitted name in field "name"
# name = TEXT
# name.data = GPvar : tx_spbettercontact_pi1-9|name

# Include external TypoScript configuration
# <INCLUDE_TYPOSCRIPT: source="FILE:fileadmin/my_setup.txt">

END;

			return $sDefaultTS;
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_flexform.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_flexform.php']);
	}
?>