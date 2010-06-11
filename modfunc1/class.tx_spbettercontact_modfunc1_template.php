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


	require_once(PATH_t3lib . 'class.t3lib_parsehtml.php');


	/**
	 * Template class for the 'sp_bettercontact' module.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_modfunc1_template {
		protected $oPObj    = NULL;
		protected $aLL      = array();
		protected $aConfig  = array();
		protected $aGP      = array();
		protected $aMarkers = array();


		/**
		 * Set configuration for template object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oPObj   = $poParent->pObj;
			$this->aLL     = $poParent->aLL;
			$this->aConfig = $poParent->aConfig;
			$this->aGP     = $poParent->aGP;

			// Get default markers
			$this->aMarkers = $this->aGetDefaultMarkers();
		}


		/**
		 * Predefine default markers
		 *
		 * @return Array with markers
		 */
		protected function aGetDefaultMarkers () {
			$aMarkers = array();

			// Default markers
			$aMarkers['###URL_SELF###']  = $this->sGetSelfURL();
			$aMarkers['###CSV_IMAGE###'] = $this->sGetCSVImage();
			$aMarkers['###INFO###']      = '';

			// BE-User info
			if (!empty($GLOBALS['BE_USER']->user) && is_array($GLOBALS['BE_USER']->user)) {
				foreach ($GLOBALS['BE_USER']->user as $sKey => $sValue) {
					$aMarkers['###USER:' . $sKey . '###'] = $sValue;
				}
			}

			// Locallang labels
			if (is_array($this->aLL)) {
				foreach ($this->aLL as $sKey => $sValue) {
					$aMarkers['###LLL:' . $sKey . '###'] = $sValue;
				}
			}

			return $aMarkers;
		}


		/**
		 * Get URL to current page
		 *
		 * @return URL to current page
		 */
		protected function sGetSelfURL () {
			return t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');
		}


		/**
		 * Get image for CSV download link
		 *
		 * @return Image of the link
		 */
		protected function sGetCSVImage () {
			return t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/csv.gif', '', 1);
		}


		/**
		 * Merge given markers with global marker array
		 *
		 */
		public function vAddMarkers (array $paMarkers) {
			$this->aMarkers = $paMarkers + $this->aMarkers;
		}


		/**
		 * Get content from template and markers
		 *
		 * @return Whole content
		 */
		public function sGetContent () {
			if (empty($this->aConfig['mainTemplate'])) {
				return;
			}

			// Get template
			$sFileName  = t3lib_div::getFileAbsFileName($this->aConfig['mainTemplate']);
			$sRessource = t3lib_div::getURL($sFileName);
			$sTemplate  = t3lib_parsehtml::getSubpart($sRessource, '###TEMPLATE###');
			$sTemplate  = trim($sTemplate, "\n");

			// Check for sub templates
			$this->vSubstituteSubTemplates($sRessource);

			// Get content
			$sContent = t3lib_parsehtml::substituteMarkerArray($sTemplate, $this->aMarkers, '', FALSE);
			$sContent = preg_replace('|###.*?###|i', '', $sContent); // removes also markers with colon

			return $sContent;
		}


		/**
		 * Find and substitute sub templates in marker array
		 *
		 * @param string $psRessource Template ressource
		 */
		protected function vSubstituteSubTemplates ($psRessource) {
			if (!strlen($psRessource)) {
				return;
			}

			foreach ($this->aMarkers as $sMarkerKey => $mMarkerValue) {
				if (!is_array($mMarkerValue)) {
					continue;
				}

				// Marker results in an error if it exists beside template subpart with same name
				unset($this->aMarkers[$sMarkerKey]);

				// Get subpart
				$sSubPart = t3lib_parsehtml::getSubpart($psRessource, $sMarkerKey);
				$sSubPart = trim($sSubPart, "\n");

				// Get new marker row
				$sMarkerKey = str_replace('SUB_TEMPLATE_', '', $sMarkerKey);
				foreach ($mMarkerValue as $aRows) {
					$sRow = t3lib_parsehtml::substituteMarkerArray($sSubPart, $aRows, '###|###', TRUE);
					$this->aMarkers[$sMarkerKey] .= PHP_EOL . $sRow;
				}
			}
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/modfunc1/class.tx_spbettercontact_modfunc1_template.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/modfunc1/class.tx_spbettercontact_modfunc1_template.php']);
	}
?>