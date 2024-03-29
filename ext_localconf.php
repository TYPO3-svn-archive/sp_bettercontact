<?php
	if (!defined ('TYPO3_MODE')) {
		die ('Access denied.');
	}

	// Add Plugin to TS ( 0 = Not cached )
	if (TYPO3_MODE == 'FE') {
		t3lib_extMgm::addPItoST43('sp_bettercontact', 'pi1/class.tx_spbettercontact_pi1.php', '_pi1', 'CType', 0);
	}

	if (TYPO3_MODE == 'BE') {

		// Add module TSConfig
		t3lib_extMgm::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:sp_bettercontact/ext_ts_config.txt">');

		// Templavoila
		if (t3lib_extMgm::isLoaded('templavoila')) {
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['templavoila']['db_new_content_el']['wizardItemsHook'][] =
				'EXT:sp_bettercontact/pi1/class.tx_spbettercontact_pi1_wizard.php:&tx_spbettercontact_pi1_wizard';
		}

	}
?>