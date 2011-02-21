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


	t3lib_div::requireOnce(PATH_tslib . 'class.tslib_pibase.php');


	/**
	 * Plugin 'Better Contact Form' for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1 extends tslib_pibase {
		public $prefixId      = 'tx_spbettercontact_pi1';
		public $scriptRelPath = 'pi1/class.tx_spbettercontact_pi1.php';
		public $extKey        = 'sp_bettercontact';
		public $sEmailCharset = '';
		public $sFormCharset  = '';
		public $sFieldPrefix  = '';
		public $aLL           = array();
		public $aGP           = array();
		public $aConfig       = array();
		public $aFields       = array();
		public $aMarkers      = array();
		public $aFiles        = array();
		public $oTemplate     = NULL;
		public $oSession      = NULL;
		public $oCheck        = NULL;
		public $oEmail        = NULL;
		public $oFile         = NULL;
		public $cObj          = NULL;
		public $oCS           = NULL;


		/**
		 * The main method of the PlugIn
		 *
		 * @param  string $psContent The PlugIn content
		 * @param  array  $paConf    The PlugIn configuration
		 * @return The content that is displayed on the website
		 */
		public function main ($psContent, array $paConf) {
			// Init required attributes and objects
			if ($sContent = $this->sInit($paConf)) {
				return $this->sWrapContent($sContent);
			}

			// Check configuration
			if ($sContent = $this->sCheckConfiguration()) {
				return $this->sWrapContent($sContent);
			}

			// Stop here if form was not submitted
			if ($sContent = $this->sProcessSubmit()) {
				return $this->sWrapContent($sContent);
			}

			// Check if a bot tries to send spam
			if ($sContent = $this->sProcessSpamCheck()) {
				return $this->sWrapContent($sContent);
			}

			// Check if the user has already sent multiple emails
			if ($sContent = $this->sProcessSendingsCheck()) {
				return $this->sWrapContent($sContent);
			}

			// Handle file uploads and image creation
			if ($sContent = $this->sProcessFiles()) {
				// Check form data before returning file errors
				// TODO: Maybe a user will not call userFunc in sProcessValueCheck()
				//       in case of errors from sProcessFiles()
				if ($sContent = $this->sProcessValueCheck()) {
					return $this->sWrapContent($sContent);
				}
				return $this->sWrapContent($sContent);
			}

			// Check form data
			if ($sContent = $this->sProcessValueCheck()) {
				return $this->sWrapContent($sContent);
			}

			// Handle DB storing and logging
			if ($sContent = $this->sProcessDB()) {
				return $this->sWrapContent($sContent);
			}

			// Send emails
			if ($sContent = $this->sProcessEmails()) {
				return $this->sWrapContent($sContent);
			}

			// Finalize request and return main content
			if ($sContent = $this->sFinalize()) {
				return $this->sWrapContent($sContent);
			}

			return $psContent;
		}


		/**
		 * Init required attributes and objects
		 *
		 * @return string Any content to show
		 */
		protected function sInit (array $paConf) {
			$this->pi_USER_INT_obj = 1;

			// Get merged config from TS and Flexform
			$oTS = $this->oMakeInstance('ts');
			$this->aConfig = $oTS->aGetConfig($paConf);

			// Set default templates if not configured
			$this->vSetDefaultTemplates();

			// Set default attributes
			$this->sFieldPrefix  = $this->sGetPrefix();
			$this->oCS           = $this->oGetCSObject();
			$this->aGP           = $this->aGetGP();
			$this->aLL           = $this->aGetLL();
			$this->aFields       = $this->aGetFields();
			$this->aMarkers      = $this->aGetMarkers();
			$this->sEmailCharset = $this->sGetCharset('email');
			$this->sFormCharset  = $this->sGetCharset('form');
			$this->sBECharset    = $this->sGetBECharset();

			// Load required objects
			$this->oTemplate     = $this->oMakeInstance('template');
			$this->oSession      = $this->oMakeInstance('session');
			$this->oCheck        = $this->oMakeInstance('check');
			$this->oEmail        = $this->oMakeInstance('email');
			$this->oDB           = $this->oMakeInstance('db');
			$this->oFile         = $this->oMakeInstance('file');

			// Load stored files from session
			$this->aFiles = $this->oSession->mGetValue('uploadedFiles');

			return $this->sProcessUserFunc('init');
		}


		/**
		 * Handle submit and return empty form if not submitted
		 *
		 * @return string Any content to show
		 */
		protected function sProcessSubmit () {
			if ((!empty($this->aConfig['postOnly']) && empty($_POST)) || !isset($this->aGP['submit'])) {
				// Add timestamp to session for elapsed time check
				$this->oSession->vAddValue('start', $GLOBALS['SIM_EXEC_TIME']);

				// Clear uploaded files
				$this->oSession->vAddValue('uploadedFiles', array());
				$this->aFiles = array();
				$this->oSession->vSave();

				return $this->oTemplate->sGetContent();
			}

			return $this->sProcessUserFunc('submit');
		}


		/**
		 * Check if a bot tries to send spam
		 *
		 * @return string Any content to show
		 */
		protected function sProcessSpamCheck () {
			if ($this->oCheck->bIsSpam($this->oSession->mGetValue('start'))) {
				$this->vSendWarning('bot');
				$this->vCheckRedirect('spam');

				return $this->aLL['msg_not_allowed'];
			}

			return $this->sProcessUserFunc('spamCheck');
		}


		/**
		 * Check if the user has already sent multiple emails
		 *
		 * @return string Any content to show
		 */
		protected function sProcessSendingsCheck () {
			$iCount = 0;
			$iTime  = 0;

			if ($this->oSession->bHasAlreadySent($iCount, $iTime)) {
				$this->vCheckRedirect('exhausted');
				if (!empty($iCount) || !empty($iTime)) {
					$this->oTemplate->vAddInfo('msg_already_sent', array($iCount, $iTime));
				}
				return $this->oTemplate->sGetContent();
			}

			return $this->sProcessUserFunc('sendingsCheck');
		}


		/**
		 * Handle file uploads and image creation
		 *
		 * @return string Any content to show
		 */
		protected function sProcessFiles () {
			if (empty($this->aConfig['enableFileTab'])) {
				return '';
			}

			// Check uploaded files
			$aErrors = array();
			if ($aFiles = $this->oFile->aGetFiles($_FILES)) {
				if (!$this->oCheck->bCheckFiles($aFiles, $aErrors)) {
					$this->oTemplate->vAddErrors($aErrors);
					// Cleanup files in session
					if (empty($this->aConfig['keepLastFileOnError'])) {
						$this->aFiles = array_diff_key($this->aFiles, $aErrors);
						$this->oSession->vAddValue('uploadedFiles', $this->aFiles);
						$this->oSession->vSave();
					}
					return $this->oTemplate->sGetContent();
				}

				// Convert images and add files to global array
				$this->oFile->vConvertImages($aFiles);
				$this->aFiles = t3lib_div::array_merge_recursive_overrule($this->aFiles, $aFiles);
				$this->oSession->vAddValue('uploadedFiles', $this->aFiles);
				$this->oSession->vSave();
			}

			// Add file markers to templates
			if (!empty($this->aFiles)) {
				$aMarkers = $this->oFile->aGetMarkers($this->aFiles);
				$this->oTemplate->vAddMarkers($aMarkers);
				$this->oEmail->vAddMarkers($aMarkers);

				// Set file as field value (will be distributed to other classes by reference)
				foreach ($this->aFiles as $sKey => $aFile) {
					if (!empty($this->aFields[$sKey])) {
						$this->aFields[$sKey]['value'] = $aFile['path'];
						$this->aGP[$sKey] = $aFile['path'];
					}
				}
			}

			return $this->sProcessUserFunc('fileHandling');
		}


		/**
		 * Check form values
		 *
		 * @return string Any content to show
		 */
		protected function sProcessValueCheck () {
			$aErrors = array();
			if (!$this->oCheck->bCheckFields($aErrors)) {
				if (!$this->bIsFormEmpty($this->aGP)) {
					$this->vSendWarning('user');
				}
				$this->oTemplate->vAddErrors($aErrors);
				$this->oTemplate->vClearFields(array_keys($aErrors));
				return $this->oTemplate->sGetContent();
			}

			return $this->sProcessUserFunc('valueCheck');
		}


		/**
		 * Add new entry in log table and save values into specified table
		 *
		 * @return string Any content to show
		 */
		protected function sProcessDB () {
			// Store form data into user defined table
			$sInfo = '';
			$aErrors = array();
			if (!empty($this->aConfig['enableDBTab'])) {
				// Get last inserted / updated row ID
				if (!$this->oDB->bIsExternalDB()) {
					$iRowID = $this->oDB->iSave($sInfo, $aErrors);
				} else {
					$iRowID = $this->oDB->iSaveExternal($sInfo, $aErrors);
				}

				// Check for errors
				if (!empty($sInfo) || !empty($aErrors)) {
					$this->oTemplate->vAddInfo('msg_db_failed', $sInfo);
					$this->oTemplate->vAddErrors($aErrors);
					return $this->oTemplate->sGetContent();
				}

				$this->oSession->vAddValue('lastRowID', $iRowID);
			}

			// Add log entry
			$iLogRowID = $this->oDB->iLog($sInfo);
			if (!empty($sInfo)) {
				$this->oTemplate->vAddInfo('msg_db_failed', $sInfo);
				return $this->oTemplate->sGetContent();
			}
			$this->oSession->vAddValue('lastLogRowID', $iLogRowID);

			return $this->sProcessUserFunc('dbHandling');
		}


		/**
		 * Handle emailing
		 *
		 * @return string Any content to show
		 */
		protected function sProcessEmails () {
			if (!$this->oEmail->bSendMails()) {
				$this->oTemplate->vAddInfo('msg_email_failed');
				$this->vCheckRedirect('error');

				return $this->oTemplate->sGetContent();
			}

			$this->oTemplate->vAddInfo('msg_email_passed', TRUE);

			return $this->sProcessUserFunc('emailHandling');
		}


		/**
		 * Save values, clear fields, check redirect
		 *
		 * @return string Any content to show
		 */
		protected function sFinalize () {
			// Save timestamp into session for multiple mails check
			$this->oSession->vAddSubmitData();
			$this->oSession->vSave();

			// Clear all input fields if configured
			if (!empty($this->aConfig['clearOnSuccess'])) {
				$this->oTemplate->vClearFields($this->aFields);
			}

			// Call finalizeUserFunc to do something before redirect or final output
			if ($sContent = $this->sProcessUserFunc('finalize')) {
				return $sContent;
			}

			// Redirect if configured
			$this->vCheckRedirect('success');

			return $this->oTemplate->sGetContent();
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
				'formTemplate'  => 'EXT:' . $this->extKey . '/res/templates/frontend/form.html',
				'emailTemplate' => 'EXT:' . $this->extKey . '/res/templates/frontend/email.html',
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
			$sWrap     = '<strong>Configuration of "Better Contact" is incorrect:</strong><br /><ul><li>%s</li></ul>';

			// Check if default setup is loaded
			if (!isset($this->aConfig['systems.']) || !isset($this->aConfig['browsers.'])) {
				return sprintf($sWrap, 'Please include the required static TypoScript setup on root page!');
			}

			// Check wrap for hidden fields
			if (!empty($this->aConfig['hiddenWrap'])) {
				if (strpos($this->aConfig['hiddenWrap'], '<input') === FALSE || strrpos($this->aConfig['hiddenWrap'], '/>') === FALSE) {
					$aMessages[] = 'Please use a valid input field for the "hiddenWrap"!';
				} else if (strpos($this->aConfig['hiddenWrap'], 'value=""') === FALSE) {
					$aMessages[] = 'Please set the value of the "hiddenWrap" input field to an empty value (value="")!';
				}
			}

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
				return sprintf($sWrap, implode('</li><li>', $aMessages));
			}

			return '';
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

				// Build the basic field
				$aFields[$sName] = array (
					'markerName'     => $sUpperName,
					'messageName'    => 'MSG_'      . $sUpperName,
					'errClassName'   => 'ERR_'      . $sUpperName,
					'valueName'      => 'VALUE_'    . $sUpperName,
					'labelName'      => 'LABEL_'    . $sUpperName,
					'fileName'       => 'FILE_'     . $sUpperName,
					'imageName'      => 'IMAGE_'    . $sUpperName,
					'thumbName'      => 'THUMB_'    . $sUpperName,
					'checkedName'    => 'CHECKED_'  . $sUpperName,
					'requiredName'   => 'REQUIRED_' . $sUpperName,
					'multiChkName'   => 'CHECKED_'  . $sMultiName,
					'multiSelName'   => 'SELECTED_' . $sMultiName,
					'regex'          => (isset($aField['regex']))          ? $aField['regex']          : '',
					'disallowed'     => (isset($aField['disallowed']))     ? $aField['disallowed']     : '',
					'allowed'        => (isset($aField['allowed']))        ? $aField['allowed']        : '',
					'required'       => (isset($aField['required']))       ? $aField['required']       : 0,
					'minLength'      => (isset($aField['minLength']))      ? $aField['minLength']      : 0,
					'maxLength'      => (isset($aField['maxLength']))      ? $aField['maxLength']      : 0,
					'fileMaxSize'    => (isset($aField['fileMaxSize']))    ? $aField['fileMaxSize']    : 0,
					'fileMinSize'    => (isset($aField['fileMinSize']))    ? $aField['fileMinSize']    : 0,
					'fileAllowed'    => (isset($aField['fileAllowed']))    ? $aField['fileAllowed']    : '',
					'fileDisallowed' => (isset($aField['fileDisallowed'])) ? $aField['fileDisallowed'] : '',
					'imageMaxWidth'  => (isset($aField['imageMaxWidth']))  ? $aField['imageMaxWidth']  : 0,
					'imageMaxHeight' => (isset($aField['imageMaxHeight'])) ? $aField['imageMaxHeight'] : 0,
					'imageMinWidth'  => (isset($aField['imageMinWidth']))  ? $aField['imageMinWidth']  : 0,
					'imageMinHeight' => (isset($aField['imageMinHeight'])) ? $aField['imageMinHeight'] : 0,
					'imageConvertTo' => (isset($aField['imageConvertTo'])) ? $aField['imageConvertTo'] : '',
					'imageTitle'     => (isset($aField['imageTitle']))     ? $aField['imageTitle']     : '',
					'imageAlt'       => (isset($aField['imageAlt']))       ? $aField['imageAlt']       : '',
					'thumbWidth'     => (isset($aField['thumbWidth']))     ? $aField['thumbWidth']     : 0,
					'thumbHeight'    => (isset($aField['thumbHeight']))    ? $aField['thumbHeight']    : 0,
					'thumbConvertTo' => (isset($aField['thumbConvertTo'])) ? $aField['thumbConvertTo'] : '',
					'thumbTitle'     => (isset($aField['thumbTitle']))     ? $aField['thumbTitle']     : '',
					'thumbAlt'       => (isset($aField['thumbAlt']))       ? $aField['thumbAlt']       : '',
					'dbField'        => (isset($aField['dbField']))        ? $aField['dbField']        : '',
					'dbNoAutofill'   => (isset($aField['dbNoAutofill']))   ? $aField['dbNoAutofill']   : 0,
					'label'          => (isset($this->aLL[$sName]))        ? $this->aLL[$sName]        : ucfirst($sName),
					'value'          => $sValue,
				);
			}

			return $aFields;
		}


		/**
		 * Get basic markers for templates
		 *
		 * @return Array with markers
		 */
		protected function aGetMarkers () {
			$aMarkers = array();

			// Page info
			if (!empty($GLOBALS['TSFE']->page) && is_array($GLOBALS['TSFE']->page)) {
				foreach ($GLOBALS['TSFE']->page as $sKey => $sValue) {
					$aMarkers['PAGE:' . $sKey] = $sValue;
				}
			}

			// Plugin info
			if (!empty($this->cObj->data) && is_array($this->cObj->data)) {
				foreach ($this->cObj->data as $sKey => $sValue) {
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
			foreach ($this->aLL as $sKey => $sValue) {
				$aMarkers['LLL:' . $sKey] = $sValue;
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
		 * Get the charset for the form and emails
		 *
		 * @param  string $psType Type of media which needs the charset
		 * @return The character encoding
		 */
		protected function sGetCharset ($psType = 'form') {
			$sType    = strtolower(trim($psType)) . 'Charset';
			$sCharset = (t3lib_div::compat_version('4.5') ? 'utf-8' : 'iso-8859-1');

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

				if (t3lib_div::int_from_ver(TYPO3_version) >= 4003000) {
					return t3lib_div::makeInstance($sClassName, $this);
				}

				$sClass = t3lib_div::makeInstanceClassName($sClassName);
				return new $sClass($this);
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

			$this->oEmail->bSendSpamWarning();
		}


		/**
		 * Execute a given userFunc if configured
		 *
		 * @param string $psName Name of the userFunc option
		 * @return string Any result of the userFunc
		 */
		protected function sProcessUserFunc ($psName) {
			if (empty($psName)) {
				return '';
			}

			if (empty($this->aConfig['hooks.'][$psName])) {
				return '';
			}

			$aConfig = (!empty($this->aConfig['hooks.'][$psName . '.'])) ? $this->aConfig['hooks.'][$psName . '.'] : array();
			$aConfig['parentObj'] = &$this;

			$mContent = $this->cObj->callUserFunction($this->aConfig['hooks.'][$psName], $aConfig, '');
			if (!empty($mContent) && is_string($mContent)) {
				return $mContent;
			}

			return '';
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
		 * @param string $psContent Content to wrap
		 * @return Wrapped content
		 */
		protected function sWrapContent ($psContent) {
			return $this->pi_wrapInBaseClass($psContent);
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1.php']);
	}
?>