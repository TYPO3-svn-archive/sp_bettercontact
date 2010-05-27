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

	// Add Plugin to TS ( 0 = Not cached )
	t3lib_extMgm::addPItoST43('sp_bettercontact', 'pi1/class.tx_spbettercontact_pi1.php', '_pi1', 'CType', 0);

	## Add module TSConfig
	t3lib_extMgm::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:sp_bettercontact/ext_ts_config.txt">');

	// Add new wizard item in Version 4.3
	if (t3lib_div::int_from_ver(TYPO3_version) >= 4003000) {
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms']['db_new_content_el']['wizardItemsHook'][] = 'EXT:sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizard.php:&tx_spbettercontact_pi1_wizard';
	}
?>