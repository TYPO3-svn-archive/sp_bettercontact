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
	 * Session class for the 'sp_bettercontact' extension.
	 *
	 * @author		Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package		TYPO3
	 * @subpackage	tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_session {
		public $aConfig			= array();
		public $aFields			= array();
		public $aPiVars			= array();
		public $aLL				= array();
		public $sExtKey			= '';
		public $iCount			= 0;
		public $iWaitingTime	= 0;

		/**
		 * Set configuration for session object
		 *
		 * @param	object	$poParent: Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->aConfig 	= $poParent->aConfig;
			$this->aFields	= $poParent->aFields;
			$this->aPiVars	= $poParent->piVars;
			$this->aLL 		= $poParent->aLL;
			$this->sExtKey	= $poParent->extKey;
		}


		/**
		 * Check session if form was allready sent
		 *
		 * @return	True if current fe user has already sent a lot of emails
		 */
		public function bHasAlreadySent () {
			$aSessionContent = unserialize($GLOBALS['TSFE']->fe_user->getKey('ses', $this->sExtKey.$GLOBALS['TSFE']->id));

			if (!is_array($aSessionContent)) {
				return false;
			}

			$this->aConfig['messageCount']	= ($this->aConfig['messageCount'] > 0)	? $this->aConfig['messageCount']	: 1;
			$this->aConfig['waitingTime']	= ($this->aConfig['waitingTime'] > 0)	? $this->aConfig['waitingTime']		: 1;

			// Set counter
			$this->iCount = $aSessionContent['cnt'];

			// Check for message count
			if ($aSessionContent['cnt'] < $this->aConfig['messageCount']) {
				return false;
			}

			// Check waiting time
			$iLockEnd = $aSessionContent['tstmp'] + ($this->aConfig['waitingTime'] * 60); // minutes

			if (time() < $iLockEnd) {
				$this->iWaitingTime = ($iLockEnd - time());
    			return true;
			}

			// Reset counter
			$this->iCount = 0;

			return false;
		}


		/**
		 * Get messages
		 *
		 * @return	Array of messages form session check
		 */
		public function aGetMessages () {
			$sWrapNegative	= $this->aConfig['infoWrapNegative'] ? $this->aConfig['infoWrapNegative'] : '|';
			$iTime 			= ($this->iWaitingTime > 60) ? ($this->iWaitingTime / 60) : 1;
			$iCount 		= ($this->iCount <= $this->aConfig['messageCount']) ? $this->iCount : $this->aConfig['messageCount']; // bugfix
			$sMessage		= sprintf($this->aLL['msg_already_sent'], $iCount, $iTime);
			$sMessage		= str_replace('|', $sMessage, $sWrapNegative);

			return array('###INFO###' => $sMessage);
		}


		/**
		 * Add timestamp and count to session and save it
		 *
		 */
		public function vSave () {
			$this->iCount++;
			$aSessionContent = array(
				'tstmp'	=> time(),
				'cnt'	=> $this->iCount,
			);

			$GLOBALS['TSFE']->fe_user->setKey('ses', $this->sExtKey.$GLOBALS['TSFE']->id, serialize($aSessionContent));
			$GLOBALS['TSFE']->storeSessionData();
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_session.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_session.php']);
	}
?>