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


	require_once(PATH_typo3 . 'interfaces/interface.cms_newcontentelementwizarditemshook.php');
	require_once(t3lib_extMgm::extPath('sp_bettercontact') . 'pi1/class.tx_spbettercontact_pi1_wizicon.php');


	/**
	 * Class that adds the wizard icon in version 4.3
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_wizard implements cms_newContentElementWizardsHook {

		/**
		 * Processing the wizard items array
		 *
		 * @param  array $aWizardItems The wizard items
		 * @param  array $oParent New Content element wizard object
		 * @return Modified array with wizard items
		 */
		public function manipulateWizardItems (&$aWizardItems, &$oParent) {
			$oWizard      = t3lib_div::makeInstance('tx_spbettercontact_pi1_wizicon');
			$aWizardItems = $oWizard->proc($aWizardItems);

			if (isset($aWizardItems['forms_contact'])) {
				$aWizardItems['forms_contact']['params'] = '&defVals[tt_content][CType]=sp_bettercontact_pi1';
			}

			unset($oWizard);
		}
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizard.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizard.php']);
	}
?>