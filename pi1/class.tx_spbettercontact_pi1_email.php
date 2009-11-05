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


	require_once(PATH_t3lib . 'class.t3lib_htmlmail.php');
	require_once(PATH_tslib . 'class.tslib_pibase.php');


	/**
	 * Mailing class for the 'sp_bettercontact' extension.
	 *
	 * @author      Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package     TYPO3
	 * @subpackage  tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_email {
		public $oCObj           = NULL;
		public $aConfig         = array();
		public $aLL             = array();
		public $aPiVars         = array();
		public $aFields         = array();
		public $aMarkers        = array();
		public $aTemplates      = array();
		public $aAddresses      = array();
		public $bHasError       = FALSE;
		public $bMBActive       = FALSE;
		public $bUnequalChar    = FALSE;
		public $sEmailChar      = 'iso-8859-1';
		public $sFormChar       = 'iso-8859-1';


		/**
		 * Set configuration for mail object
		 *
		 * @param   object  $poParent: Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj        = $poParent->cObj;
			$this->aConfig      = $poParent->aConfig;
			$this->aFields      = $poParent->aFields;
			$this->aLL          = $poParent->aLL;
			$this->aPiVars      = $poParent->piVars;

			// If mbstring module is not activated in php.ini reduce functionallity
			if (extension_loaded('mbstring') && function_exists('mb_convert_encoding')) {
				$this->sEmailChar   = $poParent->sEmailCharset;
				$this->sFormChar    = $poParent->sFormCharset;
				$this->bMBActive    = TRUE;
			} else if ($poParent->sFormCharset === 'utf-8') {
				$this->sFormChar    = 'utf-8';
			}

			// Check if character sets are different
			$this->bUnequalChar = ($this->sEmailChar !== $this->sFormChar) ? TRUE : FALSE;

			// Set email addresses
			$this->aAddresses = $this->aGetMailAddresses();

			// Add default markers
			$this->vAddDefaultMarkers();

			// Add additional remote user information for spam notifications
			$this->vAddSpamMarkers();

			// Encode array data
			$this->vEncodeMarkers();

			// Get and fill templates
			$this->vSetTemplates();
		}


		/**
		 * Get email addresses
		 *
		 * @return  Array of email addesses
		 */
		protected function aGetMailAddresses () {
			$aMailAddresses = array(
				'recipients'    => $this->aConfig['emailRecipients']    ? $this->aConfig['emailRecipients'] : 'webmaster@' . $_SERVER['SERVER_ADDR'],
				'sender'        => $this->aConfig['emailSender']        ? $this->aConfig['emailSender']     : 'webmaster@' . $_SERVER['SERVER_ADDR'],
				'admin'         => $this->aConfig['emailAdmin']         ? $this->aConfig['emailAdmin']      : 'webmaster@' . $_SERVER['SERVER_ADDR'],
				'return'        => $this->aConfig['emailReturnPath']    ? $this->aConfig['emailReturnPath'] : 'webmaster@' . $_SERVER['SERVER_ADDR'],
				'user'          => $this->aPiVars['email']              ? $this->aPiVars['email']           : 'webmaster@' . $_SERVER['SERVER_ADDR'],
			);

			// Check for multiple addresses in user email
			$aSigns = array(',', '&', ' ', chr(9));
			foreach ($aSigns as $sSign) {
				if (str_replace($sSign, '', $aMailAddresses['user']) !== $aMailAddresses['user']) {
					$aUsers = explode($sSign, $aMailAddresses['user']);
					$aMailAddresses['user'] = $aUsers[0];
					break;
				}
			}

			// Cleanup
			$aMailAddresses['recipients']   = preg_replace('/[^a-z0-9_\-,\.@]/i', '', $aMailAddresses['recipients']);
			$aMailAddresses['sender']       = preg_replace('/[^a-z0-9_\-,\.@<> ]/i', '', $aMailAddresses['sender']);
			$aMailAddresses['admin']        = preg_replace('/[^a-z0-9_\-,\.@]/i', '', $aMailAddresses['admin']);
			$aMailAddresses['return']       = preg_replace('/[^a-z0-9_\-,\.@]/i', '', trim($aMailAddresses['return'], '-fF '));
			$aMailAddresses['user']         = preg_replace('/[^a-z0-9_\-,\.@]/i', '', $aMailAddresses['user']);

			return $aMailAddresses;
		}


		/**
		 * Merge global marker array with new markers
		 *
		 * @param   array   $paMarkers: Array with new markers
		 */
		protected function vAddMarkers ($paMarkers) {
			if (is_array($paMarkers) && count($paMarkers)) {
				if (count($this->aMarkers)) {
					$this->aMarkers = array_merge($this->aMarkers, $paMarkers);
				} else {
					$this->aMarkers = $paMarkers;
				}
			}
		}


		/**
		 * Add default markers
		 *
		 */
		protected function vAddDefaultMarkers () {
			$aMarkers   = array();
			$sName      = '';
			$sType      = '';

			// Get fields
			foreach ($this->aFields as $sKey => $aField) {
				$sName  = strtolower(trim($sKey, ' .{}()='));
				$sValue = $this->sGetCleaned($aField['value']);
				$aMarkers[$aField['valueName']]     = $sValue;
				$aMarkers[$aField['labelName']]     = $aField['label'];
				$aMarkers[$aField['checkedName']]   = (isset($this->aPiVars[$sName]) || intval($sValue) == 1) ? $this->aLL['checked'] : $this->aLL['unchecked'];
			}


			// Get user defined markers
			if (is_array($this->aConfig['markers.'])) {
				foreach ($this->aConfig['markers.'] as $sKey => $mValue) {
					if (substr($sKey, -1) !== '.' && is_string($mValue)) {
						$sName = $sKey;
						$sType = $mValue;
					} else if ($sName !== '' && $sType !== '') {
						$aMarkers['###' . strtoupper($sName) . '###'] = $this->oCObj->cObjGetSingle($sType, $mValue, $sKey);
						$sName = '';
						$sType = '';
					}
				}
			}

			// Add locallang markers
			foreach ($this->aLL as $sKey => $sValue) {
				$aMarkers['###LLL:' . $sKey . '###'] = $sValue;
			}

			$this->vAddMarkers($aMarkers);
		}


		/**
		 * Get browser or system information
		 *
		 * @param   string  $psType: Type to check for
		 * @return  Name of current browser or system
		 */
		protected function sGetSysInfo ($psType='system') {
			$sName  = 'unknown';
			$sAgent = t3lib_div::getIndpEnv('HTTP_USER_AGENT');

			if (strlen($sAgent) && is_array($this->aConfig[$psType . 's.'])) {
				foreach($this->aConfig[$psType . 's.'] as $sKey => $aConfig) {
					if (eregi($aConfig['ident'], $sAgent)) {
						$sName = $aConfig['name'];
					}
				}
			}

			return $sName;
		}


		/**
		 * Cleanup strings for email
		 *
		 * @param   string  $psValue: Value to clean
		 * @return  Cleaned string
		 */
		protected function sGetCleaned ($psValue) {
			if (!strlen($psValue)) {
				return '';
			}

			$aReplacings = array(
				'\n'        => ' [ \ n ] ',
				'\t'        => ' [ \ t ] ',
				'\r'        => ' [ \ r ] ',
				'\x'        => ' [ \ x ] ',
				'&#'        => ' [ & # ] ',
				'<?'        => ' [ < ? ] ',
				'<%'        => ' [ < % ] ',
				'<script'   => ' [ < ! ] ',
				'%>'        => ' [ % > ] ',
				'?>'        => ' [ ? > ] ',
				'script>'   => ' [ ! > ] ',
			);

			return trim(str_replace(array_keys($aReplacings), array_values($aReplacings), $psValue));
		}


		/**
		 * Add spam markers
		 *
		 */
		protected function vAddSpamMarkers () {
			$aMarkers['###SPAM_DATE###']        = gmdate("M d Y", time()) . ' (GMT)';
			$aMarkers['###SPAM_TIME###']        = gmdate("H:i:s", time()) . ' (GMT)';
			$aMarkers['###SPAM_IP###']          = $this->sGetCleaned(t3lib_div::getIndpEnv('REMOTE_ADDR'));
			$aMarkers['###SPAM_REFERER###']     = $this->sGetCleaned(t3lib_div::getIndpEnv('HTTP_REFERER'));
			$aMarkers['###SPAM_AGENT###']       = $this->sGetCleaned(t3lib_div::getIndpEnv('HTTP_USER_AGENT'));
			$aMarkers['###SPAM_METHOD###']      = $this->sGetCleaned(strtolower($_SERVER['REQUEST_METHOD']));
			$aMarkers['###SPAM_BROWSER###']     = $this->sGetSysInfo('browser');
			$aMarkers['###SPAM_SYSTEM###']      = $this->sGetSysInfo('system');

			if (is_array($this->aFields)) {
				foreach ($this->aFields as $sKey => $aField) {
					$sName = strtolower(trim($sKey, ' .{}()='));
					$aMarkers['###SPAM_VALUE_' . strtoupper($sKey) . '###']     = $this->sGetCleaned($_POST[$sKey]);
					$aMarkers['###SPAM_CHECKED_' . strtoupper($sKey) . '###']   = isset($_POST[$sKey]) ? $this->aLL['checked'] : $this->aLL['unchecked'];
				}
			}

			$this->vAddMarkers($aMarkers);
		}


		/**
		 * Convert character set of array values
		 *
		 * @param   array   $paArray: Array to encode
		 * @return  Converted array
		 */
		protected function vEncodeMarkers () {
			$aResult = array();

			if ($this->bUnequalChar) {
				if ($this->bMBActive) {
					foreach ($this->aMarkers as $sKey => $sValue) {
						$this->aMarkers[$sKey] = mb_convert_encoding($sValue, $this->sEmailChar, $this->sFormChar);
					}
				} else {
					foreach ($this->aMarkers as $sKey => $sValue) {
						$this->aMarkers[$sKey] = utf8_encode($sValue);
					}
				}
			} else {
				foreach ($this->aMarkers as $sKey => $sValue) {
					$this->aMarkers[$sKey] = $sValue;
				}
			}
		}


		/**
		 * Set email templates and replace markers
		 *
		 */
		protected function vSetTemplates () {
			if (!strlen($this->aConfig['emailTemplate'])) {
				return;
			}

			// Get configuration
			$sRessource = $this->oCObj->fileResource($this->aConfig['emailTemplate']);
			$aTemplates = array(
				'message_sender',
				'message_admin',
				'message_spam',
				'subject_sender',
				'subject_admin',
				'subject_spam',
			);

			// Replace all markers and remove unused
			foreach ($aTemplates as $sValue) {
				$this->aTemplates[$sValue] = $this->oCObj->getSubpart($sRessource, '###MAIL_' . strtoupper($sValue) . '###');
				$this->aTemplates[$sValue] = str_replace(array_keys($this->aMarkers), array_values($this->aMarkers), $this->aTemplates[$sValue]);
				$this->aTemplates[$sValue] = str_replace(array(PHP_EOL, '<br />', '<br>'), "\n", $this->aTemplates[$sValue]);
				$this->aTemplates[$sValue] = preg_replace('|###.*?###|i', '', $this->aTemplates[$sValue]);
			}
		}


		/**
		 * Send emails with mail() function
		 *
		 * @param   string  $psRecipients: List of recipients for the mail
		 * @param   string  $psSender: Sender email address
		 * @param   string  $psReplyTo: Reply-to email address
		 * @param   string  $psSubject: Subject of the mail
		 * @param   string  $psMessage: Message to send in mail
		 * @param   string  $psReturnPath: Return-Path email address
		 */
		protected function vMail ($psRecipients, $psSender, $psReplyTo, $psSubject, $psMessage, $psReturnPath='') {
			if (empty($psRecipients) || empty($psSender) || empty($psReplyTo) || empty($psSubject) || empty($psMessage)) {
				$this->bHasError = TRUE;
				return;
			}

			// Encode header data and content
			$sSender        = trim(t3lib_div::encodeHeader($psSender, 'quoted-printable', $this->sEmailChar));
			$sVersion       = trim(t3lib_div::encodeHeader(phpversion(), 'quoted-printable', $this->sEmailChar));
			$sReplyTo       = trim(t3lib_div::encodeHeader($psReplyTo, 'quoted-printable', $this->sEmailChar));
			$sRecipients    = trim(t3lib_div::encodeHeader($psRecipients, 'quoted-printable', $this->sEmailChar));
			$sSubject       = trim(t3lib_div::encodeHeader($psSubject, 'quoted-printable', $this->sEmailChar));
			$sReturnPath    = trim(t3lib_div::encodeHeader($psReturnPath, 'quoted-printable', $this->sEmailChar));
			$sMessage       = trim(t3lib_div::quoted_printable($psMessage));

			// Get headers
			$aHeaders = array(
				'From: ' . $sSender,
				'X-Mailer: PHP/' . $sVersion,
				'X-Priority: 3',
				'Mime-Version: 1.0',
				'Reply-To: ' . $sReplyTo,
				'Sender: ' . $sSender,
				'Content-Type: text/plain; charset=' . trim($this->sEmailChar),
				'Content-Transfer-Encoding: quoted-printable',
			);

			// Add return path to headers
			if (strlen($sReturnPath)) {
				$aHeaders[] = 'Return-Path: ' . $sReturnPath;
				$aHeaders[] = 'Errors-To: ' . $sReturnPath;
			}

			// Get linebreak
			$sLinebreak = chr(10);
			if (strtolower(TYPO3_OS) == 'win') {
				$sLinebreak = chr(13) . chr(10);
			}

			// Get header string
			$sHeaders = trim(implode($sLinebreak, $aHeaders));

			// Send email
			if (strlen($sReturnPath)) {
				if (!mail($sRecipients, $sSubject, $sMessage, $sHeaders, '-f' . $sReturnPath)) {
					  $this->bHasError = TRUE;
				}
			} else {
				if (!mail($sRecipients, $sSubject, $sMessage, $sHeaders)) {
					  $this->bHasError = TRUE;
				}
			}
		}


		/**
		 * Check email addresses
		 *
		 * @param   array   $paAddresses: Array of all addresses to check
		 * @return  TRUE if the email addresses are ok
		 */
		protected function bCheckMailAddresses ($paAddresses) {
			if (!is_array($paAddresses)) {
				return FALSE;
			}

			foreach ($paAddresses as $sValue) {
				if (!strlen($this->aAddresses[$sValue])) {
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
			if (!$this->bCheckMailAddresses(array('admin', 'sender'))) {
				return;
			}

			// Send mail to admin
			$this->vMail(
				$this->aAddresses['admin'],
				$this->aAddresses['sender'],
				$this->aAddresses['sender'],
				$this->aTemplates['subject_spam'],
				$this->aTemplates['message_spam'],
				((!ini_get('safe_mode')) ? $this->aAddresses['return'] : '')
			);
		}


		/**
		 * Send notification mails
		 *
		 */
		public function vSendMails () {
			// Get configuration
			$sSendTo        = strtolower($this->aConfig['sendTo']);
			$sReturnPath    = (!ini_get('safe_mode')) ? $this->aAddresses['return'] : '';
			$sReplyTo       = (strtolower($this->aConfig['replyTo']) == 'user') ? 'user' : 'sender';

			// Send emails to all recipients
			if (($sSendTo == 'both' || $sSendTo == 'recipients') && $this->bCheckMailAddresses(array('recipients', 'sender', $sReplyTo))) {
				$this->vMail(
					$this->aAddresses['recipients'],
					$this->aAddresses['sender'],
					$this->aAddresses[$sReplyTo],
					$this->aTemplates['subject_admin'],
					$this->aTemplates['message_admin'],
					$sReturnPath
				);
			}

			// Send email to user
			if (($sSendTo == 'both' || $sSendTo == 'user') && $this->bCheckMailAddresses(array('user', 'sender'))) {
				$this->vMail(
					$this->aAddresses['user'],
					$this->aAddresses['sender'],
					$this->aAddresses['sender'],
					$this->aTemplates['subject_sender'],
					$this->aTemplates['message_sender'],
					$sReturnPath
				);
			}
		}


		/**
		 * Get messages
		 *
		 */
		public function aGetMessages () {
			$sWrapNegative  = $this->aConfig['infoWrapNegative'] ? $this->aConfig['infoWrapNegative'] : '|';
			$sWrapPositive  = $this->aConfig['infoWrapPositive'] ? $this->aConfig['infoWrapPositive'] : '|';
			$aMarkers       = array();

			if ($this->bHasError) {
				$aMarkers['###INFO###'] = str_replace('|', $this->aLL['msg_email_failed'], $sWrapNegative);
			} else {
				$aMarkers['###INFO###'] = str_replace('|', $this->aLL['msg_email_passed'], $sWrapPositive);
			}

			return $aMarkers;
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_email.php'])	{
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_email.php']);
	}
?>