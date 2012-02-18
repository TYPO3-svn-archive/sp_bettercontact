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

	/**
	 * Mailing handler
	 */
	class tx_spbettercontact_pi1_email {
		protected $aConfig      = array();
		protected $aLL          = array();
		protected $aGP          = array();
		protected $aFields      = array();
		protected $aMarkers     = array();
		protected $aTemplates   = array();
		protected $aAddresses   = array();
		protected $aUserMarkers = array();
		protected $oCObj        = NULL;
		protected $oCS          = NULL;
		protected $bHasError    = FALSE;
		protected $sEmailChar   = 'iso-8859-1';
		protected $sFormChar    = 'iso-8859-1';
		protected $sEmailFormat = 'plain';


		/**
		 * Set configuration for mail object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj        = $poParent->cObj;
			$this->oCS          = $poParent->oCS;
			$this->aConfig      = $poParent->aConfig;
			$this->aFields      = $poParent->aFields;
			$this->aLL          = $poParent->aLL;
			$this->aGP          = $poParent->aGP;
			$this->aUserMarkers = $poParent->aUserMarkers;
			$this->sEmailChar   = $poParent->sEmailCharset;
			$this->sFormChar    = $poParent->sFormCharset;

			// Set email type
			if (!empty($this->aConfig['emailFormat']) && $this->aConfig['emailFormat'] == 'html') {
				$this->sEmailFormat = 'html';
			}

			// Set email addresses
			$this->aAddresses = $this->aGetMailAddresses();

			// Set default markers
			$this->aMarkers = $this->aGetDefaultMarkers();

			// Add additional remote user information for spam notifications
			$aSpamMarkers   = $this->aGetSpamMarkers();
			$this->aMarkers = $aSpamMarkers + $this->aMarkers;

			// Encode marker array
			$this->oCS->convArray($this->aMarkers, $this->sFormChar, $this->sEmailChar);

			// Get and fill templates
			$this->vSetTemplates();
		}


		/**
		 * Explode and filter a list of email addresses
		 *
		 * @param string $sAddress The email address
		 * @return array Cleaned up addresses
		 */
		protected function getFilteredAddresses($sAddress) {
			$aAddresses = t3lib_div::trimExplode(',', $sAddress, TRUE);
			$aResult = array();

			foreach ($aAddresses as $sAddress) {
				// Format: Name <emai@domain.tld>
				$mName = NULL;
				if (strpos($sAddress, '<') !== FALSE) {
					$sAddress = rtrim($sAddress, ' >');
					list($mName, $sAddress) = t3lib_div::trimExplode('<', $sAddress);
					if (!ctype_alnum(str_replace(' ', '', $mName))) {
						$mName = NULL;
					}
				}

				// Check address
				if (!t3lib_div::validEmail($sAddress)) {
					continue;
				}

				$aResult[] = array($sAddress => $mName);
			}

			return $aResult;
		}


		/**
		 * Get email addresses
		 *
		 * @return Array of email addesses
		 */
		protected function aGetMailAddresses () {
			$aResult = array(
				'recipients' => '',
				'sender'     => '',
				'admin'      => '',
				'return'     => '',
				'user'       => '',
			);

			foreach ($aResult as $sKey => $sValue) {
				if ($sKey !== 'user') {
					$sName = 'email' . ($sKey == 'return' ? 'ReturnPath' : ucfirst($sKey));
					$sValue = $this->aConfig[$sName];
				} else {
					$sValue = $this->aGP['email'];
				}

				$aAddresses = $this->getFilteredAddresses($sValue);
				if ($sKey !== 'recipients') {
					$aResult[$sKey] = reset($aAddresses);
				} else {
					$aResult[$sKey] = $aAddresses;
				}
			}

			return $aResult;
		}


		/**
		 * Add default markers
		 *
		 * @return Array with markers
		 */
		protected function aGetDefaultMarkers () {
			$aMarkers = array();
			$sName    = '';
			$sType    = '';

			// Get fields
			foreach ($this->aFields as $sKey => $aField) {
				$sName  = strtolower(trim($sKey, ' .{}()='));
				$sValue = $this->sGetCleaned($aField['value']);
				$aMarkers[$aField['valueName']]    = $sValue;
				$aMarkers[$aField['labelName']]    = $aField['label'];
				$aMarkers[$aField['checkedName']]  = (!empty($this->aGP[$sName]))  ? $this->aLL['checked']  : $this->aLL['unchecked'];
				$aMarkers[$aField['requiredName']] = (!empty($aField['required'])) ? $this->aLL['required'] : '';
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
		 * Get browser or system information
		 *
		 * @param  string $psType Type to check for
		 * @return Name of current browser or system
		 */
		protected function sGetSysInfo ($psType = 'system') {
			$sName  = 'unknown';
			$sAgent = t3lib_div::getIndpEnv('HTTP_USER_AGENT');

			if (strlen($sAgent) && is_array($this->aConfig[$psType . 's.'])) {
				foreach($this->aConfig[$psType . 's.'] as $sKey => $aConfig) {
					if (preg_match('/' . addcslashes($aConfig['ident'], '/') . '/i', $sAgent)) {
						$sName = $aConfig['name'];
					}
				}
			}

			return $sName;
		}


		/**
		 * Cleanup strings for email
		 *
		 * @param  string $psValue Value to clean
		 * @return Cleaned string
		 */
		protected function sGetCleaned ($psValue) {
			if (!strlen($psValue)) {
				return '';
			}

			$aReplacings = array(
				'\n'      => ' [ \ n ] ',
				'\t'      => ' [ \ t ] ',
				'\r'      => ' [ \ r ] ',
				'\x'      => ' [ \ x ] ',
				'&#'      => ' [ & # ] ',
				'<?'      => ' [ < ? ] ',
				'<%'      => ' [ < % ] ',
				'<script' => ' [ < ! ] ',
				'%>'      => ' [ % > ] ',
				'?>'      => ' [ ? > ] ',
				'script>' => ' [ ! > ] ',
			);

			return trim(str_replace(array_keys($aReplacings), array_values($aReplacings), $psValue));
		}


		/**
		 * Add spam markers
		 *
		 * @return Array with markers
		 */
		protected function aGetSpamMarkers () {
			$aMarkers['SPAM_IP']      = $this->sGetCleaned(t3lib_div::getIndpEnv('REMOTE_ADDR'));
			$aMarkers['SPAM_REFERER'] = $this->sGetCleaned(t3lib_div::getIndpEnv('HTTP_REFERER'));
			$aMarkers['SPAM_AGENT']   = $this->sGetCleaned(t3lib_div::getIndpEnv('HTTP_USER_AGENT'));
			$aMarkers['SPAM_METHOD']  = $this->sGetCleaned(strtolower($_SERVER['REQUEST_METHOD']));
			$aMarkers['SPAM_BROWSER'] = $this->sGetSysInfo('browser');
			$aMarkers['SPAM_SYSTEM']  = $this->sGetSysInfo('system');
			$aMarkers['SPAM_DATE']    = gmdate('M d Y', $GLOBALS['SIM_EXEC_TIME']) . ' (GMT)';
			$aMarkers['SPAM_TIME']    = gmdate('H:i:s', $GLOBALS['SIM_EXEC_TIME']) . ' (GMT)';

			// Use own date and time format
			foreach (array('Date', 'Time') as $sKey) {
				if (!empty($this->aConfig['spam' . $sKey . 'Format'])) {
					$sDateTime = strftime($this->aConfig['spam' . $sKey . 'Format'], $GLOBALS['SIM_EXEC_TIME']);
					$aMarkers['SPAM_' . strtoupper($sKey)] = $sDateTime;
				}
			}

			// Fixes issue #25750 (Spam fields filled with valid field entries)
			$aGP = t3lib_div::array_merge_recursive_overrule($_GET, $_POST);

			// Add field markers
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					$sName = strtoupper($sKey);
					$aMarkers['SPAM_CHECKED_' . $sName] = $this->aLL['unchecked'];
					if (isset($aGP[$sKey])) {
						$aMarkers['SPAM_VALUE_'   . $sName] = $this->sGetCleaned($aGP[$sKey]);
						$aMarkers['SPAM_CHECKED_' . $sName] = $this->aLL['checked'];
					}
				}
			}

			return $aMarkers;
		}


		/**
		 * Set email templates and replace markers
		 *
		 */
		protected function vSetTemplates () {
			if (empty($this->aConfig['emailTemplate'])) {
				return;
			}

			// Get configuration
			$sRessource = $this->oCObj->fileResource($this->aConfig['emailTemplate']);
			$aSubparts  = array(
				'subject_sender',
				'subject_admin',
				'subject_spam',
				'message_sender_plain',
				'message_admin_plain',
				'message_spam_plain',
				'message_sender_html',
				'message_admin_html',
				'message_spam_html',
			);

			foreach ($aSubparts as $sValue) {
				// Leave HTML subparts blank if not used
				if ($this->sEmailFormat != 'html' && substr($sValue, -4) == 'html') {
					$this->aTemplates[$sValue] = '';
					continue;
				}

				// Replace all markers and remove unused
				$this->aTemplates[$sValue] = $this->oCObj->getSubpart($sRessource, '###MAIL_' . strtoupper($sValue) . '###');
				$this->aTemplates[$sValue] = trim($this->aTemplates[$sValue], "\n"); // Remove newline behind and before subpart markers
				$this->aTemplates[$sValue] = $this->oCObj->substituteMarkerArray($this->aTemplates[$sValue], $this->aMarkers, '###|###', FALSE, TRUE);
			}
		}


		/**
		 * Send emails
		 *
		 * @param array  $paAddresses    All addresses
		 * @param string $psSubject      Subject of the mail
		 * @param string $psMessagePlain Plain message
		 * @param string $psMessageHTML  HTML message
		 * @param string $psAttachement  Attachement
		 */
		protected function vMail (array $paAddresses, $psSubject, $psMessagePlain = '', $psMessageHTML = '', $psAttachement = '') {
			if (empty($paAddresses)  || (empty($psMessagePlain) && empty($psMessageHTML))) {
				$this->bHasError = TRUE;
				return;
			}

			// Use SwiftMailer
			if (!empty($GLOBALS['TYPO3_CONF_VARS']['MAIL']['substituteOldMailAPI']) && class_exists('t3lib_mail_Message')) {
				$sMessage = (!empty($psMessageHTML) ? $psMessageHTML : $psMessagePlain);
				$this->vSendSwiftMail($paAddresses, $psSubject, $sMessage, !empty($psMessageHTML), $psAttachement);
				return;
			}

			// Start email
			$oMail = t3lib_div::makeInstance('t3lib_htmlmail');
			$oMail->start();
			$oMail->charset = $this->sEmailChar;
			$oMail->mailer  = 'TYPO3';
			$oMail->useQuotedPrintable();

			// Enable returnPath (if $TYPO3_CONF_VARS['SYS']['forceReturnPath'] was not enabled)
			if (!empty($this->aConfig['allowReturnPath'])) {
				$oMail->forceReturnPath = TRUE;
			}

			// Set addresses
			$oMail->from_email = $this->mGetMailAddress($paAddresses, 'sender', TRUE);
			$oMail->replyto_email = $this->mGetMailAddress($paAddresses, 'reply', TRUE);
			$oMail->returnPath = $this->mGetMailAddress($paAddresses, 'return', TRUE);

			// Set subject and plain content
			$oMail->subject = $psSubject;
			$oMail->addPlain($psMessagePlain);

			// Set HTML content
			if ($this->sEmailFormat == 'html' && strlen($psMessageHTML)) {
				$oMail->setHTML($oMail->encodeMsg($psMessageHTML));
			}

			// Add attachement if given
			if (strlen($psAttachement)) {
				$psAttachement = t3lib_div::getFileAbsFileName($psAttachement);
				if (file_exists($psAttachement)) {
					$oMail->addAttachment($psAttachement);
				}
			}

			// Send email to any user in array
			$aRecipients = $this->mGetMailAddress($paAddresses, 'recipients');
			foreach ($aRecipients as $aAddress) {
				$sAddress = key($aAddress);
				$mName = reset($aAddress);
				if (is_string($mName)) {
					$sAddress = $mName . ' <' . $sAddress . '>';
				}
				if (!$oMail->send($sAddress)) {
					$this->bHasError = TRUE;
				}
			}
		}


		/**
		 * Send emails via SwiftMailer
		 *
		 * @param array   $paAddresses    All addresses
		 * @param string  $psSubject      Subject of the mail
		 * @param string  $psMessage      The email content
		 * @param boolean $pbIsHtml       Content is HTML
		 * @param string  $psAttachement  Attachement
		 */
		protected function vSendSwiftMail (array $paAddresses, $psSubject, $psMessage, $pbIsHtml, $psAttachement = '') {
			if (empty($paAddresses) || empty($psMessage)) {
				$this->bHasError = TRUE;
				return;
			}

			// Build mail
			$oMail = t3lib_div::makeInstance('t3lib_mail_Message');
			$oMail->setSubject($psSubject);
			$oMail->setCharset($this->sEmailChar);
			$oMail->setFrom($this->mGetMailAddress($paAddresses, 'sender'));

			// Add reply to
			$oMail->setReplyTo($this->mGetMailAddress($paAddresses, 'reply', TRUE));

			// Add return path
			$oMail->setReturnPath($this->mGetMailAddress($paAddresses, 'return', TRUE));

			// Add content
			$sFormat = ($pbIsHtml ? 'html' : 'plain');
			$oMail->setBody($psMessage, 'text/' . $sFormat);

			// Add attachement
			if (!empty($psAttachement) && class_exists('Swift_Attachment')) {
				$psAttachement = t3lib_div::getFileAbsFileName($psAttachement);
				if (file_exists($psAttachement)) {
					$oMail->attach(Swift_Attachment::fromPath($psAttachement));
				}
			}

			// Send email to any user in array
			$aRecipients = $this->mGetMailAddress($paAddresses, 'recipients');
			foreach ($aRecipients as $aAddress) {
				$oMail->getHeaders()->removeAll('To');
				$oMail->setTo($aAddress);
				$iCount = $oMail->send();
				if (!$oMail->isSent() || $iCount === 0) {
					$this->bHasError = TRUE;
					return;
				}
			}
		}


		/**
		 * Returns a mail address from given address pool
		 *
		 * @param array $paAddresses The address pool
		 * @param string $psKey The key to search for
		 * @param boolean $pbPlain Return plain result
		 */
		protected function mGetMailAddress (array $paAddresses, $psKey, $pbPlain = FALSE) {
			if (empty($paAddresses[$psKey])) {
				if (($psKey === 'reply' || $psKey === 'return') && !empty($paAddresses['sender'])) {
					$paAddresses[$psKey] = $paAddresses['sender'];
				} else {
					return ($pbPlain ? '' : array('' => NULL));
				}
			}

			$mKey = key($paAddresses[$psKey]);
			if (!is_numeric($mKey) && $pbPlain) {
				return $mKey;
			}

			return $paAddresses[$psKey];
		}


		/**
		 * Check email addresses
		 *
		 * @param  All addresses to check
		 * @return TRUE if the email addresses are ok
		 */
		protected function bCheckMailAddresses () {
			$aKeys = func_get_args();
			$aAddresses = array_intersect_key($this->aAddresses, array_flip($aKeys));

			foreach ($aAddresses as $mKey => $aAddress) {
				if (is_numeric($mKey)) {
					$mKey = key(reset($aAddress));
				}
				if (empty($mKey)) {
					return FALSE;
				}
			}

			return TRUE;
		}


		/**
		 * Send bot warning mail
		 *
		 */
		public function vSendSpamWarning () {
			if (!$this->bCheckMailAddresses('admin', 'sender')) {
				$this->bHasError = TRUE;
				return;
			}

			$aAddresses = array(
				'recipients' => array($this->aAddresses['admin']),
				'sender'     => $this->aAddresses['sender'],
				'reply'      => $this->aAddresses['sender'],
				'return'     => $this->aAddresses['return'],
			);

			// Send mail to admin
			$this->vMail(
				$aAddresses,
				$this->aTemplates['subject_spam'],
				$this->aTemplates['message_spam_plain'],
				$this->aTemplates['message_spam_html']
			);
		}


		/**
		 * Send notification mails
		 *
		 */
		public function vSendMails () {
			// Get configuration
			$sSendTo   = (!empty($this->aConfig['sendTo']))   ? strtolower($this->aConfig['sendTo'])   : 'both';
			$sSendFrom = (!empty($this->aConfig['sendFrom'])) ? strtolower($this->aConfig['sendFrom']) : 'sender';
			$sReplyTo  = (!empty($this->aConfig['replyTo']))  ? strtolower($this->aConfig['replyTo'])  : 'user';
			$sReplyTo  = (strtolower($sReplyTo) == 'user') ? 'user' : 'sender';

			// Send emails to all recipients
			if ($sSendTo == 'both' || $sSendTo == 'recipients') {
				if (!$this->bCheckMailAddresses('recipients', 'sender', $sReplyTo)) {
					$this->bHasError = TRUE;
					return;
				}
				$aAddresses = array(
					'recipients' => $this->aAddresses['recipients'],
					'sender'     => $this->aAddresses[$sSendFrom],
					'reply'      => $this->aAddresses[$sReplyTo],
					'return'     => $this->aAddresses['return'],
				);
				$this->vMail(
					$aAddresses,
					$this->aTemplates['subject_admin'],
					$this->aTemplates['message_admin_plain'],
					$this->aTemplates['message_admin_html']
				);
			}

			// Send email to user
			if ($sSendTo == 'both' || $sSendTo == 'user') {
				if (!$this->bCheckMailAddresses('user', 'sender')) {
					$this->bHasError = TRUE;
					return;
				}
				$aAddresses = array(
					'recipients' => array($this->aAddresses['user']),
					'sender'     => $this->aAddresses['sender'],
					'reply'      => $this->aAddresses['sender'],
					'return'     => $this->aAddresses['return'],
				);
				$this->vMail(
					$aAddresses,
					$this->aTemplates['subject_sender'],
					$this->aTemplates['message_sender_plain'],
					$this->aTemplates['message_sender_html']
				);
			}
		}


		/**
		 * Get messages
		 *
		 * @return Array with info message
		 */
		public function aGetMessages () {
			$sWrapNegative  = (!empty($this->aConfig['infoWrapNegative'])) ? $this->aConfig['infoWrapNegative'] : '|';
			$sWrapPositive  = (!empty($this->aConfig['infoWrapPositive'])) ? $this->aConfig['infoWrapPositive'] : '|';
			$aMarkers       = array();

			if ($this->bHasError) {
				$aMarkers['INFO'] = str_replace('|', $this->aLL['msg_email_failed'], $sWrapNegative);
			} else {
				$aMarkers['INFO'] = str_replace('|', $this->aLL['msg_email_passed'], $sWrapPositive);
			}

			return $aMarkers;
		}


		/**
		 * Get error state
		 *
		 * @return TRUE if emailing results in an error
		 */
		public function bHasError() {
			return $this->bHasError;
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_email.php'])	{
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_email.php']);
	}
?>