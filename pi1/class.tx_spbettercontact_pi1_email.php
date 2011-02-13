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


	t3lib_div::requireOnce(PATH_t3lib . 'class.t3lib_htmlmail.php');
	t3lib_div::requireOnce(PATH_tslib . 'class.tslib_pibase.php');


	/**
	 * Mailing class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
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
		protected $sEmailChar   = '';
		protected $sFormChar    = '';
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
		 * Check string for multiple email addresses and return only the first
		 *
		 * @return String with first email address
		 */
		protected function sGetSingleAddress ($psAddress) {
			if (!strlen($psAddress)) {
				return 'webmaster@' . $_SERVER['SERVER_ADDR'];
			}

			$aSigns = array(',', '&', ' ', chr(9), "\r\n", "\n\r", "\n", "\r");
			foreach ($aSigns as $sSign) {
				if (str_replace($sSign, '', $psAddress) !== $psAddress) {
					$aAddresses = explode($sSign, $psAddress);
					return reset($aAddresses);
				}
			}

			return $psAddress;
		}


		/**
		 * Get email addresses
		 *
		 * @return Array of email addesses
		 */
		protected function aGetMailAddresses () {
			$aMailAddresses = array(
				'recipients' => '',
				'sender'     => '',
				'admin'      => '',
				'return'     => '',
				'user'       => '',
			);

			// Get E-Mail addresses
			foreach ($aMailAddresses as $sKey => $sValue) {
				$sIdent   = ($sKey == 'return') ? 'ReturnPath' : ucfirst($sKey);
				$sName    = 'email' . $sIdent;
				$sReplace = ($sKey == 'sender') ? '/[^a-z0-9_\-,\.@<> ]/i' : '/[^a-z0-9_\-,\.@]/i';

				if ($sKey != 'user') {
					$aMailAddresses[$sKey] = $this->aConfig[$sName];
				} else {
					$aMailAddresses[$sKey] = $this->sGetSingleAddress($this->aGP['email']);
				}

				// Set sender to default if empty
				if ($sKey == 'sender' && !strlen($aMailAddresses[$sKey])) {
					$aMailAddresses[$sKey] = 'webmaster@' . $_SERVER['SERVER_ADDR'];
				}

				// Cleanup
				$aMailAddresses[$sKey] = preg_replace($sReplace, '', $aMailAddresses[$sKey]);
			}

			return $aMailAddresses;
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

			// Add field markers
			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					$sName = strtoupper($sKey);
					$aMarkers['SPAM_CHECKED_' . $sName] = $this->aLL['unchecked'];
					if (isset($this->aGP[$sKey])) {
						$aMarkers['SPAM_VALUE_'   . $sName] = $this->sGetCleaned($this->aGP[$sKey]);
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
		 * @param mixed  $pmRecipients   List or array of recipients
		 * @param string $psSender       Sender email address
		 * @param string $psReplyTo      Reply-to email address
		 * @param string $psSubject      Subject of the mail
		 * @param string $psMessagePlain Plain message
		 * @param string $psMessageHTML  HTML message
		 * @param string $psReturnPath   Return-Path
		 * @param mixed  $pmAttachements List or array of attachements
		 */
		protected function vMail ($pmRecipients, $psSender, $psReplyTo = '', $psSubject = '', $psMessagePlain = '', $psMessageHTML = '', $psReturnPath = '', $pmAttachements = '') {
			if (empty($pmRecipients) || !strlen($psSender) || (!strlen($psMessagePlain) && !strlen($psMessageHTML))) {
				$this->bHasError = TRUE;
				return;
			}

			// Start email
			$oMail = t3lib_div::makeInstance('t3lib_htmlmail');
			$oMail->start();
			$oMail->charset = $this->sEmailChar;
			$oMail->mailer  = 'TYPO3'; // Do not show version because it is a security issue
			$oMail->useQuotedPrintable();

			// Enable returnPath (if $TYPO3_CONF_VARS['SYS']['forceReturnPath'] was not enabled)
			if (!empty($this->aConfig['allowReturnPath'])) {
				$oMail->forceReturnPath = TRUE;
			}

			// Set addresses
			$oMail->from_email    = $psSender;
			$oMail->replyto_email = (!empty($psReplyTo) ? $psReplyTo : $psSender);
			$oMail->returnPath    = $psReturnPath;

			// Set subject and plain content
			$oMail->subject = $psSubject;
			$oMail->addPlain($psMessagePlain);

			// Set HTML content
			if ($this->sEmailFormat == 'html' && strlen($psMessageHTML)) {
				// Parse HTML content. Thanks to Jens Schmietendorf
				$oMail->theParts['html']['content'] = $psMessageHTML;
				$oMail->extractMediaLinks();
				$oMail->extractHyperLinks();
				$oMail->fetchHTMLMedia();
				$oMail->substMediaNamesInHTML(0); // 0 = relative
				$oMail->substHREFsInHTML();
				$oMail->setHtml($oMail->encodeMsg($oMail->theParts['html']['content']));
			}

			// Add attachements if given
			if (!empty($pmAttachements)) {
				if (is_string($pmAttachements)) {
					$pmAttachements = t3lib_div::trimExplode(',', $pmAttachements);
				}
				if (is_array($pmAttachements)) {
					foreach ($pmAttachements as $sAttachement) {
						$sAttachement = t3lib_div::getFileAbsFileName($sAttachement);
						if (file_exists($sAttachement)) {
							$oMail->addAttachment($sAttachement);
						}
					}
				}
			}

			// Check for recipient list
			if (is_string($pmRecipients)) {
				$pmRecipients = t3lib_div::trimExplode(',', $pmRecipients, TRUE);
			}

			// Send email to any user in array
			if (is_array($pmRecipients)) {
				foreach ($pmRecipients as $sRecipient) {
					if (!$oMail->send($sRecipient)) {
						$this->bHasError = TRUE;
					}
				}
			}
		}


		/**
		 * Check email addresses
		 *
		 * @param  All addresses to check
		 * @return TRUE if the email addresses are ok
		 */
		protected function bCheckMailAddresses () {
			$aAddresses = func_get_args();

			foreach ($aAddresses as $sValue) {
				if (empty($this->aAddresses[$sValue])) {
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
				return;
			}

			// Send mail to admin
			$this->vMail(
				$this->aAddresses['admin'],
				$this->aAddresses['sender'],
				$this->aAddresses['sender'],
				$this->aTemplates['subject_spam'],
				$this->aTemplates['message_spam_plain'],
				$this->aTemplates['message_spam_html'],
				$this->aAddresses['return']
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
			if (($sSendTo == 'both' || $sSendTo == 'recipients') && $this->bCheckMailAddresses('recipients', 'sender', $sReplyTo)) {
				$this->vMail(
					$this->aAddresses['recipients'],
					$this->aAddresses[$sSendFrom],
					$this->aAddresses[$sReplyTo],
					$this->aTemplates['subject_admin'],
					$this->aTemplates['message_admin_plain'],
					$this->aTemplates['message_admin_html'],
					$this->aAddresses['return']
				);
			}

			// Send email to user
			if (($sSendTo == 'both' || $sSendTo == 'user') && $this->bCheckMailAddresses('user', 'sender')) {
				$this->vMail(
					$this->aAddresses['user'],
					$this->aAddresses['sender'],
					$this->aAddresses['sender'],
					$this->aTemplates['subject_sender'],
					$this->aTemplates['message_sender_plain'],
					$this->aTemplates['message_sender_html'],
					$this->aAddresses['return']
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


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_email.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_email.php']);
	}
?>