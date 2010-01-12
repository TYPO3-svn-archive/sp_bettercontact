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
	if (!defined ('TYPO3_MODE')) {
		die ('Access denied.');
	}

	// Check for required SimpleXML and get flexform
	if (class_exists('SimpleXMLElement')) {
		include_once(t3lib_extMgm::extPath('sp_bettercontact') . 'pi1/class.tx_spbettercontact_pi1_flexform.php');
		$oFlexForm = t3lib_div::makeInstance('tx_spbettercontact_pi1_flexform');
		$sFlexData = $oFlexForm->sGetFlexForm();
		unset($oFlexForm);
	} else {
		$sFlexData = 'FILE:EXT:sp_bettercontact/res/fallback/flexform.xml';
	}

	// Get plugin data
	$aPlugin = array(
		'LLL:EXT:sp_bettercontact/locallang.xml:tt_content.type_label',
		'sp_bettercontact_pi1',
		'../typo3conf/ext/sp_bettercontact/res/images/list.gif',
	);

	// Get new flexform field -> method "t3lib_BEfunc::getFlexFormDS" doesn't support CType
	$aNewFlexField = array(
		'pi_flexform_CType' => array(
			'exclude'   => 1,
			'label'     => 'LLL:EXT:cms/locallang_ttc.php:pi_flexform',
			'config'    => array(
				'type'  => 'flex',
				'ds'    => array(
					'default' => $sFlexData,
				)
			)
		)
	);

	// Add plugin to TCA
	t3lib_div::loadTCA('tt_content');
	t3lib_extMgm::addTCAcolumns('tt_content', $aNewFlexField, 1);
	$TCA['tt_content']['types']['sp_bettercontact_pi1']['showitem'] = 'CType;;4;button,hidden,1-1-1, header;;3;;2-2-2, linkToTop;;;;3-3-3, --div--;LLL:EXT:cms/locallang_ttc.xml:tabs.plugin,pi_flexform_CType;;;;1-1-1, --div--;LLL:EXT:cms/locallang_tca.xml:pages.tabs.access,starttime, endtime, cPos';

	// Add plugin to right position in list
	$aCTypes    = $TCA['tt_content']['columns']['CType']['config']['items'];
	$iT3Version = t3lib_div::int_from_ver(TYPO3_version);
	$aNewItems  = array();
	foreach ($aCTypes as $aValue) {
		if (substr($aValue[0], -7) == 'special' || ($iT3Version < 4002000 && $aValue[1] == 'splash')) {
			$aNewItems[] = $aPlugin;
		}
		$aNewItems[] = $aValue;
	}
	$TCA['tt_content']['columns']['CType']['config']['items'] = $aNewItems;

	// Add wizard icon
	if (TYPO3_MODE == 'BE') {
		$TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses']['tx_spbettercontact_pi1_wizicon'] = t3lib_extMgm::extPath('sp_bettercontact').'pi1/class.tx_spbettercontact_pi1_wizicon.php';
	}
?>