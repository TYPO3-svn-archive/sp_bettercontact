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

	t3lib_div::requireOnce(PATH_typo3 . 'interfaces/interface.cms_newcontentelementwizarditemshook.php');

	/**
	 * Templavoila wizard icon hook
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
			$aWizardItems = $this->proc($aWizardItems);

			if (isset($aWizardItems['forms_contact'])) {
				$aWizardItems['forms_contact']['params'] = '&defVals[tt_content][CType]=sp_bettercontact_pi1';
			}

			unset($oWizard);
		}


		/**
		 * Processing the wizard items array
		 *
		 * @param  array $aWizardItems The wizard items
		 * @return Modified array with wizard items
		 */
		public function proc (array $aWizardItems) {
			if (empty($aWizardItems)) {
				$aWizardItems = array(
					'forms'         => $this->aGetHeader(),
					'forms_contact' => $this->aGetPlugin(),
				);
			}

			$sSearchKey = (array_key_exists('forms', $aWizardItems) ? 'forms' : 'special');
			$aNewItems  = array();
			$sLastKey   = '';

			// Add plugin to forms area
			foreach ($aWizardItems as $sKey => $aValue) {
				if (strpos($sLastKey, $sSearchKey) !== FALSE && strpos($sKey, $sSearchKey) === FALSE) {
					if ($sSearchKey == 'special') {
						$aNewItems['forms'] = $this->aGetHeader();
					}
					$aNewItems['forms_contact'] = $this->aGetPlugin();
				}

				$aNewItems[$sKey] = $aValue;
				$sLastKey         = $sKey;
			}

			return $aNewItems;
		}


		/**
		 * Get forms header
		 *
		 * @return Header data array
		 */
		protected function aGetHeader () {
			$sLangFile = t3lib_extMgm::extPath('cms') . 'layout/locallang_db_new_content_el.xml';
			$aLLCms    = t3lib_div::readLLXMLfile($sLangFile, $GLOBALS['LANG']->lang);

			return array(
				'header' => $GLOBALS['LANG']->getLLL('forms', $aLLCms),
			);
		}


		/**
		 * Get plugin data
		 *
		 * @return Plugin data array
		 */
		protected function aGetPlugin () {
			$sLangFile = t3lib_extMgm::extPath('sp_bettercontact') . 'locallang.xml';
			$aLL       = t3lib_div::readLLXMLfile($sLangFile, $GLOBALS['LANG']->lang);

			return array(
				'icon'                 => t3lib_extMgm::extRelPath('sp_bettercontact') . 'res/images/wizard.gif',
				'title'                => $GLOBALS['LANG']->getLLL('pi1_title', $aLL),
				'description'          => $GLOBALS['LANG']->getLLL('pi1_plus_wiz_description', $aLL),
				'tt_content_defValues' => array('CType' => 'sp_bettercontact_pi1'),
			);
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizard.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizard.php']);
	}
?>