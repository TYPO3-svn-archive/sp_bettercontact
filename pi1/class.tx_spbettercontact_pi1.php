<?php
	/*********************************************************************
	 *  Copyright notice
	 *
	 *  (c) 2007-2012 Kai Vogel <kai.vogel@speedprogs.de>, Speedprogs.de
	 *
	 *  All rights reserved
	 *
	 *  This script is part of the TYPO3 project. The TYPO3 project is
	 *  free software; you can redistribute it and/or modify
	 *  it under the terms of the GNU General Public License as published
	 *  by the Free Software Foundation; either version 3 of the License,
	 *  or (at your option) any later version.
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
	 ********************************************************************/

	t3lib_div::requireOnce(PATH_tslib . 'class.tslib_pibase.php');

	/**
	 * Contact form plugin
	 */
	class tx_spbettercontact_pi1 extends tslib_pibase {
		public $prefixId      = 'tx_spbettercontact_pi1';
		public $scriptRelPath = 'pi1/class.tx_spbettercontact_pi1.php';
		public $extKey        = 'sp_bettercontact';
		public $sEmailCharset = 'iso-8859-1';
		public $sFormCharset  = 'iso-8859-1';
		public $sFieldPrefix  = '';
		public $aLL           = array();
		public $aGP           = array();
		public $aConfig       = array();
		public $aFields       = array();
		public $oTemplate     = NULL;
		public $oSession      = NULL;
		public $oCheck        = NULL;
		public $oEmail        = NULL;
		public $cObj          = NULL;
		public $oCS           = NULL;


		/**
		 * The main method of the PlugIn
		 *
		 * @param  string $content The PlugIn content
		 * @param  array  $conf    The PlugIn configuration
		 * @return The content that is displayed on the website
		 */
		public function main ($psContent, array $paConf) {
			$this->pi_USER_INT_obj = 1;

			// Get merged config from TS and Flexform
			$oTS = $this->oMakeInstance('ts');
			$this->aConfig = $oTS->aGetConfig($paConf);

			// Call preUserFunc to do something before checks and rendering
			$this->vCheckUserFunc('preUserFunc');

			// Set default templates if not configured
			$this->vSetDefaultTemplates();

			// Check configuration
			if ($sMessage = $this->sCheckConfiguration()) {
				return $this->pi_wrapInBaseClass($sMessage);
			}

			// Init required attributes and objects
			$this->vInit();

			// Stop here if form was not submitted
			if ((!empty($this->aConfig['postOnly']) && empty($_POST)) || !isset($this->aGP['submit'])) {
				// Add timestamp to session for elapsed time check
				$this->oSession->vAddValue('start', $GLOBALS['SIM_EXEC_TIME']);
				$this->oSession->vSave();
				return $this->sGetContent();
			}

			// Call submitUserFunc to do something if form was submitted
			$this->vCheckUserFunc('submitUserFunc');

			// Check if a bot tries to send spam
			if ($this->oCheck->bIsSpam($this->oSession->mGetValue('start'))) {
				$this->vSendWarning('bot');
				$this->vCheckRedirect('spam');
				return $this->pi_wrapInBaseClass($this->aLL['msg_not_allowed']);
			}

			// Check form data
			if (!$this->oCheck->bCheckFields()) {
				if (!$this->bIsFormEmpty($this->aGP)) {
					$this->vSendWarning('user');
				}
				$this->oTemplate->vAddMarkers($this->oCheck->aGetMessages());
				$this->oTemplate->vClearFields($this->oCheck->aGetMaliciousFields());
				return $this->sGetContent();
			}

			// Check if the user has already sent multiple emails
			if ($this->oSession->bHasAlreadySent()) {
				$this->vCheckRedirect('exhausted');
				$this->oTemplate->vAddMarkers($this->oSession->aGetMessages());
				return $this->sGetContent();
			}

			// Add new entry in log table and save values into specified table
			$this->oSession->vAddValue('lastLogRowID', $this->oDB->iLog());
			$this->oSession->vAddValue('lastRowID', $this->oDB->iSave());
			if ($sMessage = $this->oDB->sGetError()) {
				return $this->pi_wrapInBaseClass($sMessage);
			}

			// Send emails
			$this->oEmail->vSendMails();
			$this->oTemplate->vAddMarkers($this->oEmail->aGetMessages());
			if ($this->oEmail->bHasError()) {
				$this->vCheckRedirect('error');
				return $this->sGetContent();
			}

			// Save timestamp into session for multiple mails check
			$this->oSession->vAddSubmitData();
			$this->oSession->vSave();

			// Call saveUserFunc to do something before redirect or output
			$this->vCheckUserFunc('postUserFunc');

			// Clear all input fields if configured
			if (!empty($this->aConfig['clearOnSuccess'])) {
				$this->oTemplate->vClearFields($this->aFields);
			}

			// Redirect if configured or return content
			$this->vCheckRedirect('success');
			return $this->sGetContent();
		}


		/**
		 * Set default templates if they are not configured
		 *
		 */
		protected function vSetDefaultTemplates () {
			if (!empty($this->aConfig['disableAutoTemplates'])) {
				return;
			}

			// Get filenames
			$aFiles = array(
				'formTemplate'   => 'EXT:' . $this->extKey . '/res/templates/frontend/form.html',
				'emailTemplate'  => 'EXT:' . $this->extKey . '/res/templates/frontend/email.html',
			);

			// Add stylesheet only if formTemplate is empty (will only be used if also not configured)
			if (empty($this->aConfig['formTemplate'])) {
				$aFiles['stylesheetFile'] = 'EXT:' . $this->extKey . '/res/templates/frontend/stylesheet.css';
			}

			// Add only these files which are not configured
			foreach ($aFiles as $sKey => $sFileName) {
				if (empty($this->aConfig[$sKey])) {
					$this->aConfig[$sKey] = $sFileName;
				}
			}
		}


		/**
		 * Check configuration
		 *
		 * @return TRUE if configuration is ok
		 */
		protected function sCheckConfiguration () {
			$aMessages = array();

			// Check email field
			if (empty($this->aConfig['fields.']['email.']) || !is_array($this->aConfig['fields.']['email.'])) {
				$aMessages[] = 'Please configure the required email field in your TypoScript setup!';
			}

			// Check captcha
			if (!empty($this->aConfig['captchaSupport']) && empty($this->aConfig['fields.']['captcha.'])) {
				$aMessages[] = 'Please configure the required captcha field in your TypoScript setup!';
			}

			// Check form template
			if (empty($this->aConfig['formTemplate'])) {
				$aMessages[] = 'Please define a template for the form!';
			}

			// Check email template
			if (empty($this->aConfig['emailTemplate'])) {
				$aMessages[] = 'Please define a template for the email!';
			}

			// Check recipients email address
			if (($this->aConfig['sendTo'] == 'recipients' || $this->aConfig['sendTo'] == 'both') && empty($this->aConfig['emailRecipients'])) {
				$aMessages[] = 'Please define an email address for the recipients!';
			}

			// Check sender email address
			if ((($this->aConfig['sendTo'] == 'user' || $this->aConfig['sendTo'] == 'both')
			  || ($this->aConfig['sendTo'] == 'recipients' && $this->aConfig['sendFrom'] == 'sender')) && empty($this->aConfig['emailSender'])) {
				$aMessages[] = 'Please define an email address for the sender!';
			}

			// Check admin email address
			if ($this->aConfig['adminMails'] != 'none' && empty($this->aConfig['emailAdmin'])) {
				$aMessages[] = 'Please define an email address for the admin!';
			}

			// Check if adodb is installed to use an external database
			if ((!empty($this->aConfig['database.']['driver'])   || !empty($this->aConfig['database.']['host'])
			  || !empty($this->aConfig['database.']['port'])     || !empty($this->aConfig['database.']['database'])
			  || !empty($this->aConfig['database.']['username']) || !empty($this->aConfig['database.']['password']))
			  && !t3lib_extMgm::isLoaded('adodb')
			) {
				$aMessages[] = 'Please install the required extension "adodb" to use an external database!';
			}

			// Return a list of error messages
			if (count($aMessages)) {
				return '<strong>Configuration of "Better Contact" is incorrect:</strong><br /><ul><li>' . implode('</li><li>', $aMessages) . '</li></ul>';
			}

			return '';
		}


		/**
		 * Init required attributes and objects
		 *
		 */
		protected function vInit () {
			$this->sFieldPrefix  = $this->sGetPrefix();
			$this->oCS           = $this->oGetCSObject();
			$this->aGP           = $this->aGetGP();
			$this->aLL           = $this->aGetLL();
			$this->aFields       = $this->aGetFields();
			$this->sEmailCharset = $this->sGetCharset('email');
			$this->sFormCharset  = $this->sGetCharset('form');
			$this->oTemplate     = $this->oMakeInstance('template');
			$this->oSession      = $this->oMakeInstance('session');
			$this->oCheck        = $this->oMakeInstance('check');
			$this->oEmail        = $this->oMakeInstance('email');
			$this->oDB           = $this->oMakeInstance('db');
		}


		/**
		 * Get fieldname prefix
		 *
		 * @return The prefix
		 */
		protected function sGetPrefix () {
			if (!empty($this->aConfig['fieldPrefix'])) {
				return $this->aConfig['fieldPrefix'];
			}

			return $this->prefixId . '-' . (int) $this->cObj->data['uid'];
		}


		/**
		 * Get charset convert object
		 *
		 * @return Instance of the csConvObj
		 */
		protected function oGetCSObject () {
			if (!class_exists('t3lib_cs')) {
				t3lib_div::requireOnce(PATH_t3lib . 'class.t3lib_cs.php');
			}

			if (isset($GLOBALS['LANG']->csConvObj) && $GLOBALS['LANG']->csConvObj instanceof t3lib_cs) {
				return $GLOBALS['LANG']->csConvObj;
			} else if (isset($GLOBALS['TSFE']->csConvObj) && $GLOBALS['TSFE']->csConvObj instanceof t3lib_cs) {
				return $GLOBALS['TSFE']->csConvObj;
			}

			return t3lib_div::makeInstance('t3lib_cs');
		}


		/**
		 * Get merged $_GET and $_POST
		 *
		 * @return Array with GPVars
		 */
		protected function aGetGP () {
			$aGP = t3lib_div::array_merge_recursive_overrule($_GET, $_POST);

			// Merge prefixed values to first level
			if (strlen($this->sFieldPrefix) && isset($aGP[$this->sFieldPrefix])) {
				$aGP = array_merge($aGP, $aGP[$this->sFieldPrefix]);
				unset($aGP[$this->sFieldPrefix]);
			}

			t3lib_div::stripSlashesOnArray($aGP);

			// Add default piVars if configured
			if (!empty($this->aConfig['_DEFAULT_PI_VARS.']) && is_array($this->aConfig['_DEFAULT_PI_VARS.'])) {
				$aGP = t3lib_div::array_merge_recursive_overrule($this->aConfig['_DEFAULT_PI_VARS.'], $aGP);
			}

			return $aGP;
		}


		/**
		 * Get whole language array
		 *
		 * @return Array of all localized labels
		 */
		protected function aGetLL () {
			$sLangKey   = (strtolower($this->LLkey) == 'en') ? 'default' : $this->LLkey;
			$aLanguages = ($sLangKey != 'default') ? array('default', $sLangKey) : array('default');
			$aLocalLang = array();

			// Get all locallang sources
			$aSources = array(
				'ext'  => 'EXT:' . $this->extKey . '/res/templates/frontend/locallang.xml',
				'ts'   => (!empty($this->aConfig['_LOCAL_LANG.']))  ? $this->aConfig['_LOCAL_LANG.']  : FALSE,
				'user' => (!empty($this->aConfig['locallangFile'])) ? $this->aConfig['locallangFile'] : FALSE,
			);

			foreach ($aSources as $mSource) {
				if (empty($mSource)) {
					continue;
				}

				// Read language file
				if (is_string($mSource)) {
					$sFile   = t3lib_div::getFileAbsFileName($mSource);
					$mSource = t3lib_div::readLLXMLfile($sFile, $sLangKey, $this->oCS->renderCharset);
				}

				if (!is_array($mSource)) {
					continue;
				}

				// Remove dots from TS keys if any
				$mSource = t3lib_div::removeDotsFromTS($mSource);

				// Combine labels
				foreach ($aLanguages as $sLang) {
					if (!empty($mSource[$sLang]) && is_array($mSource[$sLang])) {
						$aLocalLang = $mSource[$sLang] + $aLocalLang;
					}
				}
			}

			// Fixes issue #31884 (Language markers broken in TYPO3 4.6.0)
			foreach ($aLocalLang as $key => $label) {
				if (isset($label[0]['target'])) {
					$aLocalLang[$key] = $label[0]['target'];
				}
			}

			return $aLocalLang;
		}


		/**
		 * Get an array of all fields
		 *
		 * @return Array of all formular fields
		 */
		protected function aGetFields () {
			if (empty($this->aConfig['fields.']) || !is_array($this->aConfig['fields.'])) {
				return array();
			}

			$aFields = array();

			foreach ($this->aConfig['fields.'] as $sKey => $aField) {
				// Get configuration
				$sName      = strtolower(trim($sKey, ' .{}()'));
				$sDefault   = (isset($aField['default'])) ? $aField['default'] : '';
				$sValue     = (isset($this->aGP[$sName])) ? $this->aGP[$sName] : $sDefault;
				$sUpperName = strtoupper($sName);
				$sMultiName = $sUpperName . '_' . md5($sValue);

				// Build the field
				$aFields[$sName] = array (
					'markerName'   => $sUpperName,
					'messageName'  => 'MSG_'      . $sUpperName,
					'errClassName' => 'ERR_'      . $sUpperName,
					'valueName'    => 'VALUE_'    . $sUpperName,
					'labelName'    => 'LABEL_'    . $sUpperName,
					'checkedName'  => 'CHECKED_'  . $sUpperName,
					'requiredName' => 'REQUIRED_' . $sUpperName,
					'multiChkName' => 'CHECKED_'  . $sMultiName,
					'multiSelName' => 'SELECTED_' . $sMultiName,
					'regex'        => (isset($aField['regex']))      ? $aField['regex']      : '',
					'disallowed'   => (isset($aField['disallowed'])) ? $aField['disallowed'] : '',
					'allowed'      => (isset($aField['allowed']))    ? $aField['allowed']    : '',
					'required'     => (isset($aField['required']))   ? $aField['required']   : 0,
					'minLength'    => (isset($aField['minLength']))  ? $aField['minLength']  : 0,
					'maxLength'    => (isset($aField['maxLength']))  ? $aField['maxLength']  : 0,
					'label'        => (isset($this->aLL[$sName]))    ? $this->aLL[$sName]    : ucfirst($sName),
					'value'        => $sValue,
				);
			}

			return $aFields;
		}


		/**
		 * Get the charset for the form and emails
		 *
		 * @param  string $psType Type of media which needs the charset
		 * @return The character encoding
		 */
		protected function sGetCharset ($psType = 'form') {
			$sType    = strtolower(trim($psType)) . 'Charset';
			$sCharset = 'iso-8859-1';

			if (t3lib_div::int_from_ver(TYPO3_version) >= 4005000) {
				$sCharset = 'utf-8';
			}

			if (!empty($GLOBALS['LANG']->charSet)) {
				$sCharset = $GLOBALS['LANG']->charSet;
			} else if (!empty($GLOBALS['TSFE']->renderCharset)) {
				$sCharset = $GLOBALS['TSFE']->renderCharset;
			} else if (!empty($GLOBALS['TSFE']->defaultCharSet)) {
				$sCharset = $GLOBALS['TSFE']->defaultCharSet;
			}

			if (!empty($this->aConfig[$sType])) {
				$sCharset = $this->aConfig[$sType];
			}

			return strtolower($sCharset);
		}


		/**
		 * Make an instance of any class
		 *
		 * @return Instance of the new object
		 */
		protected function oMakeInstance ($psClassPostfix) {
			if (!strlen($psClassPostfix)) {
				return NULL;
			}

			$sClassName = strtolower($this->prefixId . '_' . $psClassPostfix);
			$sFileName  = t3lib_extMgm::extPath($this->extKey) . 'pi1/class.' . $sClassName . '.php';

			if (@file_exists($sFileName)) {
				t3lib_div::requireOnce($sFileName);
				return t3lib_div::makeInstance($sClassName, $this);
			}

			return NULL;
		}


		/**
		 * Check if form is empty
		 *
		 * @param  array $paValues Values to check
		 * @return TRUE if the form is empty
		 */
		protected function bIsFormEmpty (array $paValues) {
			foreach ($paValues as $sKey => $mValue) {
				// Check first if its an array
				if (is_array($mValue)) {
					if (!$this->bIsFormEmpty($mValue)) {
						return FALSE;
					}
					continue;
				}

				// Now check if field is empty
				if (!empty($mValue)) {
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
			if (empty($this->aConfig['adminMails']) || ($this->aConfig['adminMails'] != strtolower($psType) && $this->aConfig['adminMails'] != 'both')) {
				return;
			}

			$this->oEmail->vSendSpamWarning();
		}


		/**
		 * Execute a given userFunc if configured
		 *
		 * @param string $psName Name of the userFunc option
		 */
		protected function vCheckUserFunc ($psName) {
			if (!is_string($psName) || !strlen($psName) || empty($this->aConfig[$psName])) {
				return;
			}

			$aConfig = (!empty($this->aConfig[$psName . '.'])) ? $this->aConfig[$psName . '.'] : array();

			t3lib_div::callUserFunction($this->aConfig[$psName], $aConfig, $this, '');
		}


		/**
		 * Redirect if page is configured
		 *
		 */
		protected function vCheckRedirect ($psType = 'success') {
			if (empty($this->aConfig[$psType . 'RedirectPage'])) {
				return;
			}

			$sURL = $this->cObj->typoLink_URL(array(
				'parameter' => $this->aConfig[$psType . 'RedirectPage'],
			));

			Header('Location: ' . t3lib_div::locationHeaderUrl($sURL));
			exit();
		}


		/**
		 * Get content
		 *
		 * @return Whole content
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