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
	 * Class that adds the wizard icon.
	 *
	 * @author      Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package     TYPO3
	 * @subpackage  tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_wizicon {

		/**
		 * Processing the wizard items array
		 *
		 * @param   array   $wizardItems: The wizard items
		 * @return  Modified array with wizard items
		 */
		public function proc ($aWizardItems) {
			if (!is_array($aWizardItems) || !count($aWizardItems)) {
				return $aWizardItems;
			}

			// Get configuration
			global $LANG;
			$sLangFile      = t3lib_extMgm::extPath('sp_bettercontact') . 'locallang.xml';
			$aLL            = t3lib_div::readLLXMLfile($sLangFile, $GLOBALS['LANG']->lang);
			$sLastKey       = '';
			$aNewItems      = array();

			// Get plugin
			$aPlugin = array(
				'icon'                  => t3lib_extMgm::extRelPath('sp_bettercontact') . 'res/images/wizard.gif',
				'title'                 => $LANG->getLLL('pi1_title', $aLL),
				'description'           => $LANG->getLLL('pi1_plus_wiz_description', $aLL),
				'tt_content_defValues'  => array('CType' => 'sp_bettercontact_pi1'),
			);

			// Add plugin in forms area
			foreach ($aWizardItems as $sKey => $aValue) {
				if (@substr($sLastKey, 0, 5) == 'forms' && @substr($sKey, 0, 5) != 'forms') {
					$aNewItems['forms_contact'] = $aPlugin;
				}

				$aNewItems[$sKey]   = $aValue;
				$sLastKey           = $sKey;
			}

			return $aNewItems;
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizicon.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizicon.php']);
	}
?>