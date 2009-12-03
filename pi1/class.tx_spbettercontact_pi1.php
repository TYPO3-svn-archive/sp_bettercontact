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


	require_once(PATH_tslib . 'class.tslib_pibase.php');


	/**
	 * Plugin 'Better Contact Form' for the 'sp_bettercontact' extension.
	 *
	 * @author      Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package     TYPO3
	 * @subpackage  tx_spbettercontact
	 */
	class tx_spbettercontact_pi1 extends tslib_pibase {
		public $prefixId        = 'tx_spbettercontact_pi1';
		public $scriptRelPath   = 'pi1/class.tx_spbettercontact_pi1.php';
		public $extKey          = 'sp_bettercontact';
		public $sEmailCharset   = 'iso-8859-1';
		public $sFormCharset    = 'iso-8859-1';
		public $aLL             = array();
		public $aConfig         = array();
		public $aFields         = array();
		public $aUserMarkers    = array();
		public $oTemplate       = NULL;
		public $oSession        = NULL;
		public $oCheck          = NULL;
		public $oEmail          = NULL;
		public $cObj            = NULL;


		/**
		 * The main method of the PlugIn
		 *
		 * @param   string      $content: The PlugIn content
		 * @param   array       $conf: The PlugIn configuration
		 * @return  The content that is displayed on the website
		 */
		public function main ($psContent, $paConf) {
			$this->pi_USER_INT_obj = 1;
			$this->aConfig = $paConf;
			$this->pi_setPiVarDefaults();

			// Override typoscript config with flexform values
			$this->vFlexOverride();

			// Set default templates if set
			if ($this->aConfig['useDefaultTemplates']) {
				$this->vSetDefaultTemplates();
			}

			// Check configuration
			if ($sMessage = $this->sCheckConfiguration()) {
				return $this->pi_wrapInBaseClass($sMessage);
			}

			// Get user defined markers
			$this->aUserMarkers = $this->aGetUserMarkers();

			// Get required things...
			$this->aLL              = $this->aGetLL();
			$this->aFields          = $this->aGetFields();
			$this->sEmailCharset    = $this->sGetCharset('email');
			$this->sFormCharset     = $this->sGetCharset('form');
			$this->oTemplate        = $this->oMakeInstance('template');
			$this->oSession         = $this->oMakeInstance('session');
			$this->oCheck           = $this->oMakeInstance('check');
			$this->oEmail           = $this->oMakeInstance('email');

			// Stop here if form was not submitted (ignore $_GET)
			if (!is_array($_POST) || !count($_POST)) {
				return $this->sGetContent();
			}

			//  Check if a bot tries to send spam
			if ($this->oCheck->bIsSpam()) {
				$this->vSendWarning('bot');
				return $this->pi_wrapInBaseClass($this->aLL['msg_not_allowed']);
			}

			// Check form data
			if (!$this->oCheck->bCheckFields()) {
				if (!$this->bIsFormEmpty()) {
					$this->vSendWarning('user');
				}
				$this->oTemplate->vAddMarkers($this->oCheck->aGetMessages());
				$this->oTemplate->vClearMalicious($this->oCheck->aGetBadFields());
				return $this->sGetContent();
			}

			// Check if the user has already sent multiple emails
			if ($this->oSession->bHasAlreadySent()) {
				$this->oTemplate->vAddMarkers($this->oSession->aGetMessages());
				return $this->sGetContent();
			}

			// Send emails
			$this->oEmail->vSendMails();
			$this->oTemplate->vAddMarkers($this->oEmail->aGetMessages());
			if ($this->oEmail->bHasError) {
				return $this->sGetContent();
			}

			// Save timestamp into session for multiple mails check
			$this->oSession->vSave();

			// Redirect if set
			if (strlen($this->aConfig['redirectPage'])) {
				Header('Location: ' . $this->sGetRedirectURL());
				exit();
			}

			// Return whole content
			return $this->sGetContent();
		}


		/**
		 * Override TypoScipt settings with Flexform values
		 *
		 */
		protected function vFlexOverride () {
			$this->pi_initPIflexForm('pi_flexform_CType');

			if (!is_array($this->cObj->data['pi_flexform_CType'])) {
				return;
			}

			// Override TS
			foreach ($this->cObj->data['pi_flexform_CType']['data'] as $aData) {
				if (is_array($aData)) {
					foreach ($aData['lDEF'] as $sKey => $aValue) {
						if (strlen($aValue['vDEF'])) {
							$this->aConfig[$sKey] = $aValue['vDEF'];
						}
					}
				}
			}
		}


		/**
		 * Get the charset for the form and emails
		 *
		 * @param   string      $psType: Type of media which needs the charset
		 * @return  The character encoding
		 */
		protected function sGetCharset ($psType='form') {
			$sType = strtolower(trim($psType)) . 'Charset';

			$sCharset   = $GLOBALS['LANG']->charSet         ? $GLOBALS['LANG']->charSet         : 'iso-8859-1';
			$sCharset   = $GLOBALS['TSFE']->renderCharset   ? $GLOBALS['TSFE']->renderCharset   : $sCharset;
			$sCharset   = $GLOBALS['TSFE']->metaCharset     ? $GLOBALS['TSFE']->metaCharset     : $sCharset;

			if (strlen($this->aConfig[$sType])) {
				$sCharset = $this->aConfig[$sType];
			}

			return strtolower($sCharset);
		}


		/**
		 * Set default templates
		 *
		 */
		protected function vSetDefaultTemplates () {
			$this->aConfig['formTemplate']      = 'EXT:' . $this->extKey . '/res/templates/form.html';
			$this->aConfig['emailTemplate']     = 'EXT:' . $this->extKey . '/res/templates/email.html';
			$this->aConfig['stylesheetFile']    = 'EXT:' . $this->extKey . '/res/templates/stylesheet.css';
		}


		/**
		 * Get TypoScript value from String / cObject
		 *
		 * @return  String with value
		 */
		protected function sGetTSValue($paConfig, $psKey) {
			if (!is_array($paConfig) || !count($paConfig) || !strlen($psKey) || substr($psKey, -1) == '.') {
				return '';
			}

			if (isset($paConfig[$psKey . '.'])) {
				return $this->cObj->cObjGetSingle($paConfig[$psKey], $paConfig[$psKey . '.']);
			}

			return $paConfig[$psKey];
		}


		/**
		 * Get user defined markers
		 *
		 * @return  Array with user markers
		 */
		protected function aGetUserMarkers () {
			$aMarkers = array();

			// User defined markers
			if (isset($this->aConfig['markers.']) && is_array($this->aConfig['markers.'])) {
				foreach ($this->aConfig['markers.'] as $sKey => $mValue) {
					if (substr($sKey, -1) != '.') {
						$aMarkers['###' . strtoupper($sKey) . '###'] = $this->sGetTSValue($this->aConfig['markers.'], $sKey);
					}
				}
			}

			return $aMarkers;
		}


		/**
		 * Get user language array
		 *
		 * @return  Array of user localized labels
		 */
		protected function aGetUserLabels ($psLang='default') {
			$sFile      = t3lib_div::getFileAbsFileName($this->aConfig['locallangFile']);
			$aOwnLabels = array();

			if (strlen($sFile)) {
				$aXML = t3lib_div::xml2array(t3lib_div::getUrl($sFile));

				if (!is_array($aXML['data'][$psLang])) {
					$psLang = 'default';
				}

				$aOwnLabels = $aXML['data'][$psLang];
			}

			// Convert to utf-8
			if ($this->sGetCharset('default') != 'utf-8' && is_array($aOwnLabels)) {
				foreach ($aOwnLabels as $sKey => $sValue) {
					$aOwnLabels[$sKey] = utf8_decode($sValue);
				}
			}

			return $aOwnLabels;
		}


		/**
		 * Get whole language array
		 *
		 * @return  Array of all localized labels
		 */
		protected function aGetLL() {
			$this->pi_loadLL();

			$sLangKey       = (strtolower($this->LLkey) == 'en') ? 'default' : $this->LLkey;
			$aLocalLang     = $this->LOCAL_LANG[$sLangKey];
			$aOtherLabels   = array(
				$this->aConfig['_LOCAL_LANG.'][$sLangKey  . '.'],
				$this->aGetUserLabels($sLangKey),
			);

			if (!is_array($this->LOCAL_LANG[$sLangKey]) || !count($this->LOCAL_LANG[$sLangKey])) {
				$aLocalLang = $this->LOCAL_LANG['default'];
			}

			// Add and override labels
			if (is_array($aOtherLabels)) {
				foreach ($aOtherLabels as $aLabels) {
					if (is_array($aLabels) && count($aLabels)) {
						foreach ($aLabels as $sKey => $sLabel) {
							$aLocalLang[$sKey] = $sLabel;
						}
					}
				}
			}

			return $aLocalLang;
		}


		/**
		 * Check configuration
		 *
		 * @return  TRUE if configuration is ok
		 */
		protected function sCheckConfiguration () {
			$aMessages = array();

			// Check email field
			if (!isset($this->aConfig['fields.'])
			 || !is_array($this->aConfig['fields.'])
			 || !isset($this->aConfig['fields.']['email.'])
			 || !is_array($this->aConfig['fields.']['email.'])) {
				$aMessages[] = 'Please configure the required email field in your TypoScript!';
			}

			// Check captcha
			if (strlen($this->aConfig['captchaSupport'])
			 && (!isset($this->aConfig['fields.']['captcha.'])
			 || !is_array($this->aConfig['fields.']['captcha.']))) {
				$aMessages[] = 'Please configure the required captcha field in your TypoScript!';
			}

			// Check form template
			if (!strlen($this->aConfig['formTemplate'])) {
				$aMessages[] = 'Please define a template for the form!';
			}

			// Check email template
			if (!strlen($this->aConfig['emailTemplate'])) {
				$aMessages[] = 'Please define a template for the email!';
			}

			// Check recipients email address
			if (($this->aConfig['sendTo'] == 'recipients' || $this->aConfig['sendTo'] == 'both')
			 && !strlen($this->aConfig['emailRecipients'])) {
				$aMessages[] = 'Please define an email address for the recipients!';
			}

			// Check sender email address ("none" will be available if db support was implemented)
			if ($this->aConfig['sendTo'] != 'none' && !strlen($this->aConfig['emailSender'])) {
				$aMessages[] = 'Please define an email address for the sender!';
			}

			// Check admin email address
			if (strlen($this->aConfig['adminMails']) && $this->aConfig['adminMails'] != 'none'
			 && !strlen($this->aConfig['emailAdmin'])) {
				$aMessages[] = 'Please define an email address for the admin!';
			}

			// Return a list of error messages
			if (count($aMessages)) {
				return '<strong>Configuration of "Better Contact" is incorrect:</strong><br /><ul><li>' . implode('</li><li>', $aMessages) . '</li></ul>';
			}

			return '';
		}


		/**
		 * Get an array of all fields
		 *
		 * @return  Array of all formular fields
		 */
		protected function aGetFields () {
			if (!is_array($this->aConfig['fields.']) || !count($this->aConfig['fields.'])) {
				return array();
			}

			$aFields = array();

			foreach ($this->aConfig['fields.'] as $sKey => $aUserField) {
				// Get configuration
				$sName      = strtolower(trim($sKey, ' .{}()'));
				$sValue     = isset($_POST[$sName]) ? $this->piVars[$sName] : $this->sGetTSValue($aUserField, 'default');
				$sUpperName = strtoupper($sName);
				$sMultiName = $sUpperName . '_' . md5($sValue);

				// Build the field
				$aFields[$sName] = array (
					'markerName'    => '###'          . $sUpperName . '###',
					'messageName'   => '###MSG_'      . $sUpperName . '###',
					'errClassName'  => '###ERR_'      . $sUpperName . '###',
					'valueName'     => '###VALUE_'    . $sUpperName . '###',
					'labelName'     => '###LABEL_'    . $sUpperName . '###',
					'checkedName'   => '###CHECKED_'  . $sUpperName . '###',
					'multiChkName'  => '###CHECKED_'  . $sMultiName . '###',
					'multiSelName'  => '###SELECTED_' . $sMultiName . '###',
					'regex'         => $aUserField['regex']         ? $aUserField['regex']      : '',
					'disallowed'    => $aUserField['disallowed']    ? $aUserField['disallowed'] : '',
					'allowed'       => $aUserField['allowed']       ? $aUserField['allowed']    : '',
					'required'      => $aUserField['required']      ? $aUserField['required']   : 0,
					'minLength'     => $aUserField['minLength']     ? $aUserField['minLength']  : 0,
					'maxLength'     => $aUserField['maxLength']     ? $aUserField['maxLength']  : 0,
					'label'         => $this->aLL[$sName]           ? $this->aLL[$sName]        : ucfirst($sName) . ':',
					'value'         => $sValue,
				);
			}

			return $aFields;
		}


		/**
		 * Make an instance of any class
		 *
		 * @return  Instance of the new object
		 */
		protected function oMakeInstance ($psClassPostfix) {
			if (!strlen($psClassPostfix)) {
				return NULL;
			}

			$sClassName = strtolower($this->prefixId . '_' . $psClassPostfix);
			$sFileName  = t3lib_extMgm::extPath($this->extKey) . 'pi1/class.' . $sClassName . '.php';

			if (@file_exists($sFileName)) {
				include_once($sFileName);

				$sClass  = t3lib_div::makeInstanceClassName($sClassName);
				$oResult = new $sClass($this);

				return $oResult;
			}

			return NULL;
		}


		/**
		 * Check if form is empty
		 *
		 * @return  TRUE if the form is empty
		 */
		protected function bIsFormEmpty () {
			if (!is_array($this->piVars)) {
				return FALSE;
			}

			foreach ($this->piVars as $sKey => $sValue) {
				if (strlen($sValue)) {
					return FALSE;
				}
			}

			return TRUE;
		}


		/**
		 * Check config and send warning
		 *
		 */
		protected function vSendWarning ($psType) {
			if ($this->aConfig['adminMails'] !== strtolower($psType) && $this->aConfig['adminMails'] !== 'both') {
				return;
			}

			$this->oEmail->vSendSpamWarning();
		}


		/**
		 * Get redirect url
		 *
		 * @return  URL of the page which should be shown after successfully sent mail
		 */
		protected function sGetRedirectURL () {
			$sPage = $this->aConfig['redirectPage'];

			if (!strlen($sPage)) {
				$sPage = $GLOBALS['TSFE']->id;
			}

			return $this->cObj->typoLink('', array(
					'parameter' => $sPage,
					'returnLast' => 'url',
				)
			);

		}


		/**
		 * Get content
		 *
		 * @return  Whole content
		 */
		protected function sGetContent () {
			$sContent = $this->oTemplate->sGetContent();

			return $this->pi_wrapInBaseClass($sContent);
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1.php']);
	}
?>