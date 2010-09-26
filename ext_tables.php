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


	if (!defined ('TYPO3_MODE')) {
		die ('Access denied.');
	}

	if (TYPO3_MODE == 'BE') {

		// Check if DB tab is visible in Flexform
		$bShowDBTab = FALSE;
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sp_bettercontact'])) {
			$aConfig    = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sp_bettercontact']);
			$bShowDBTab = !empty($aConfig['enableDBTab']);
		}

		// Check for required SimpleXML and get flexform
		if (class_exists('SimpleXMLElement')) {
			t3lib_div::requireOnce(t3lib_extMgm::extPath('sp_bettercontact') . 'pi1/class.tx_spbettercontact_pi1_flexform.php');
			$oFlexForm = t3lib_div::makeInstance('tx_spbettercontact_pi1_flexform');
			$sFlexData = $oFlexForm->sGetFlexForm($bShowDBTab);
			unset($oFlexForm);
		} else if ($bShowDBTab) {
			$sFlexData = 'FILE:EXT:sp_bettercontact/res/fallback/flexform.xml';
		} else {
			$sFlexData = 'FILE:EXT:sp_bettercontact/res/fallback/flexform_no_db.xml';
		}

		// Get plugin data
		$aPlugin = array(
			'LLL:EXT:sp_bettercontact/locallang.xml:tt_content.type_label',
			'sp_bettercontact_pi1',
			'../typo3conf/ext/sp_bettercontact/res/images/list.gif',
		);

		// Add flexform
		t3lib_div::loadTCA('tt_content');
		$GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][',sp_bettercontact_pi1'] = $sFlexData;
		$GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds_pointerField'] = 'list_type,CType';
		$GLOBALS['TCA']['tt_content']['types']['sp_bettercontact_pi1']['showitem'] = 'CType;;4;button,hidden,1-1-1, header;;3;;2-2-2, linkToTop;;;;3-3-3, --div--;LLL:EXT:sp_bettercontact/locallang.xml:tabs.form,pi_flexform;;;;1-1-1, --div--;LLL:EXT:cms/locallang_tca.xml:pages.tabs.access,starttime, endtime, cPos';

		// Add plugin to correct position in CType list
		$aCTypes    = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'];
		$aNewItems  = array();
		$bAdded     = FALSE;
		foreach ($aCTypes as $aValue) {
			if (strpos($aValue[0], 'special') !== FALSE) {
				$aNewItems[] = $aPlugin;
				$bAdded      = TRUE;
			}
			$aNewItems[] = $aValue;
		}
		if (!$bAdded) {
			$aNewItems[] = $aPlugin;
		}
		$GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] = $aNewItems;

		// Add wizard icon
		if (t3lib_div::int_from_ver(TYPO3_version) >= 4003000) {
			t3lib_extMgm::addPageTSConfig("
				mod.wizards.newContentElement.wizardItems.forms {\n
					elements.contact {\n
						icon        = " . t3lib_extMgm::extRelPath('sp_bettercontact') . "res/images/wizard.gif\n
						title       = LLL:EXT:sp_bettercontact/locallang.xml:pi1_title\n
						description = LLL:EXT:sp_bettercontact/locallang.xml:pi1_plus_wiz_description\n\n
						tt_content_defValues {\n
							CType = sp_bettercontact_pi1\n
						}\n
					}\n\n
					show := addToList(contact)\n
				}
			");
		} else {
			$GLOBALS['TBE_MODULES_EXT']['xMOD_db_new_content_el']['addElClasses']['tx_spbettercontact_pi1_wizicon'] = t3lib_extMgm::extPath('sp_bettercontact').'pi1/class.tx_spbettercontact_pi1_wizicon.php';
		}

		// Add backend module to web->info
		t3lib_extMgm::insertModuleFunction(
			'web_info',
			'tx_spbettercontact_modfunc1',
			t3lib_extMgm::extPath('sp_bettercontact') . 'modfunc1/class.tx_spbettercontact_modfunc1.php',
			'LLL:EXT:sp_bettercontact/locallang.xml:moduleFunction.tx_spbettercontact_modfunc1'
		);

	}
?>