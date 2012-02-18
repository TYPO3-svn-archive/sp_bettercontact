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
	 * Session handler
	 */
	class tx_spbettercontact_pi1_session {
		protected $aConfig         = array();
		protected $aFields         = array();
		protected $aGP             = array();
		protected $aLL             = array();
		protected $aSessionContent = array();
		protected $sExtKey         = '';
		protected $sSessionName    = '';
		protected $iCount          = 0;
		protected $iWaitingTime    = 0;

		/**
		 * Set configuration for session object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig   = $poParent->aConfig;
			$this->aFields   = $poParent->aFields;
			$this->aGP       = $poParent->aGP;
			$this->aLL       = $poParent->aLL;
			$this->sExtKey   = $poParent->extKey;
			$this->iPluginID = $poParent->cObj->data['uid'];

			// Load session content
			$this->aSessionContent = $this->aLoad();
		}


		/**
		 * Load session data
		 *
		 * @return Array with session content
		 */
		public function aLoad () {
			$aSession = $GLOBALS['TSFE']->fe_user->getKey('ses', $this->sExtKey);

			if (isset($aSession[$this->iPluginID]) && is_array($aSession[$this->iPluginID])) {
				return $aSession[$this->iPluginID];
			}

			return array();
		}


		/**
		 * Check session if form was already sent
		 *
		 * @return TRUE if current fe user has already sent a lot of emails
		 */
		public function bHasAlreadySent () {
			if (empty($this->aSessionContent) || !is_array($this->aSessionContent)) {
				return FALSE;
			}

			$this->aConfig['messageCount'] = ($this->aConfig['messageCount'] > 0)  ? $this->aConfig['messageCount']    : 1;
			$this->aConfig['waitingTime']  = ($this->aConfig['waitingTime'] > 0)   ? $this->aConfig['waitingTime']     : 1;

			// Set counter
			$this->iCount = $this->aSessionContent['cnt'];

			// Check for message count
			if ($this->iCount < $this->aConfig['messageCount']) {
				return FALSE;
			}

			// Check waiting time
			$iLockEnd = $this->aSessionContent['tstmp'] + ($this->aConfig['waitingTime'] * 60); // minutes

			if ($GLOBALS['SIM_EXEC_TIME'] < $iLockEnd) {
				$this->iWaitingTime = ($iLockEnd - $GLOBALS['SIM_EXEC_TIME']);
    			return TRUE;
			}

			// Reset counter
			$this->iCount = 0;

			return FALSE;
		}


		/**
		 * Get a value from session data
		 *
		 * @return Mixed value
		 */
		public function mGetValue ($psKey) {
			if (strlen($psKey) && isset($this->aSessionContent[$psKey])) {
				return $this->aSessionContent[$psKey];
			}

			return FALSE;
		}


		/**
		 * Add a value to session data
		 *
		 * @param string $psKey   Key of the new entry
		 * @param mixed  $pmValue Value of the new entry
		 */
		public function vAddValue ($psKey, $pmValue) {
			if (!strlen($psKey)) {
				return;
			}

			$this->aSessionContent[$psKey] = $pmValue;
		}


		/**
		 * Add informations about last sending
		 *
		 */
		public function vAddSubmitData () {
			$this->aSessionContent['tstmp']  = $GLOBALS['SIM_EXEC_TIME'];
			$this->aSessionContent['cnt']    = ++$this->iCount;
			$this->aSessionContent['gpVars'] = $this->aGP;
		}


		/**
		 * Store data into session
		 *
		 */
		public function vSave () {
			// Add a new entry for each plugin and update "lastEntryID"
			$aContent = $GLOBALS['TSFE']->fe_user->getKey('ses', $this->sExtKey);
			$aContent[$this->iPluginID] = $this->aSessionContent;
			$aContent['lastEntryID']    = $this->iPluginID;

			// Update session content
			$GLOBALS['TSFE']->fe_user->setKey('ses', $this->sExtKey, $aContent);
			$GLOBALS['TSFE']->storeSessionData();
		}


		/**
		 * Get messages
		 *
		 * @return Array of messages form session check
		 */
		public function aGetMessages () {
			$sWrapNegative = $this->aConfig['infoWrapNegative'] ? $this->aConfig['infoWrapNegative'] : '|';
			$iTime         = ($this->iWaitingTime > 60) ? ($this->iWaitingTime / 60) : 1;
			$iCount        = ($this->iCount <= $this->aConfig['messageCount']) ? $this->iCount : $this->aConfig['messageCount']; // bugfix
			$sMessage      = sprintf($this->aLL['msg_already_sent'], $iCount, $iTime);
			$sMessage      = str_replace('|', $sMessage, $sWrapNegative);

			return array('INFO' => $sMessage);
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_session.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_session.php']);
	}
?>