<?php
	/***************************************************************
	*  Copyright notice
	*
	*  (c) 2011 Kai Vogel <kai.vogel ( at ) speedprogs.de>
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


	require_once(PATH_t3lib . 'class.t3lib_basicfilefunc.php');
	require_once(PATH_t3lib . 'class.t3lib_stdgraphic.php');


	/**
	 * File handling class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_file {
		protected $aConfig      = array();
		protected $aLL          = array();
		protected $aGP          = array();
		protected $aFields      = array();
		protected $oCObj        = NULL;
		protected $oCS          = NULL;


		/**
		 * Set configuration for file object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj        = $poParent->cObj;
			$this->oCS          = $poParent->oCS;
			$this->aConfig      = $poParent->aConfig;
			$this->aFields      = $poParent->aFields;
			$this->aLL          = $poParent->aLL;
			$this->aGP          = $poParent->aGP;
		}


		/**
		 * Get uploaded files from $_FILES
		 * 
		 * @return array All valid uploaded files
		 */
		public function aGetUploadedFiles () {
			if (empty($_FILES) || !is_array($_FILES)) {
				return array();
			}

			// Get upload dir
			$sPath = PATH_site . 'uploads/tx_spbettercontact/';
			if (!file_exists($sPath)) {
				return array();
			}

			// Get basic file functions
			$oFileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');

			// Get files
			$aFiles = array();
			foreach ($_FILES as $sKey => $aFile) {
				// Check if uploaded file is valid
				if (!is_uploaded_file($aFile['tmp_name']) || $aFile['error'] != 'UPLOAD_ERR_OK' || $aFile['size'] <= 0) {
					continue;
				}

				// Get file info
				$aFileInfo = t3lib_div::split_fileref($aFile['tmp_name']);
				$sFileName = $oFileFunc->getUniqueName($aFile['name'], $sPath);

				// Move to upload dir
				if (!file_exists($aFile['tmp_name']) || !move_uploaded_file($aFile['tmp_name'], $sFileName)) {
					continue;
				}

				// Check file again
				if (is_readable($sFileName)) {
					$aFiles[$sKey] = array(
						'name' => $sFileName,
						'type' => $aFileInfo['fileext'],
						'real' => $aFileInfo['filebody'],
						'size' => ((int) @filesize($sFileName) * 1024), // KB
					);
				}
			}

			unset($oFileFunc);

			return $aFiles;
		}


		/**
		 * Convert all image files
		 * 
		 * @param string $paFiles All uploaded files
		 * @return string New file name
		 */
		public function vConvertImages (array &$paFiles) {
			if (empty($paFiles)) {
				return;
			}

			// Get basic image functions
			$oImageFunc = t3lib_div::makeInstance('t3lib_stdGraphic');
			$oImageFunc->init();
			$oImageFunc->absPrefix = PATH_site;

			// Convert images into new format
			foreach ($paFiles as $sKey => $aFile) {
				if (empty($this->aFields[$sKey]['image'])) {
					continue;
				}

				$aField     = $this->aFields[$sKey];
				$sFileName  = $aFile['name'];
				$sFileType  = (!empty($aField['imageConvertTo']) ? ltrim($aField['imageConvertTo'], '.') : '');
				$aOptions   = array(
					'maxW' => (!empty($aField['imageMaxWidth'])  ? $aField['imageMaxWidth'])  : ''),
					'maxH' => (!empty($aField['imageMaxHeight']) ? $aField['imageMaxHeight']) : ''),
					'minH' => (!empty($aField['imageMinWidth'])  ? $aField['imageMinWidth'])  : ''),
					'minH' => (!empty($aField['imageMinHeight']) ? $aField['imageMinHeight']) : ''),
				);

				// Convert...
				$aFileInfo = $oImageFunc->imageMagickConvert($aFile['name'], $sFileType, '', '', '', '', $aOptions, TRUE);

				// Overwrite current file with new one
				if (!empty($aFileInfo[3]) && copy($aFileInfo[3], $sFileName)) {
					continue;
				}

				// Remove temp file
				unlink($aFileInfo[3]);

				// Set new file type
				if (!empty($aFileInfo[2])) {
					$paFiles[$sKey]['type'] = $aFileInfo[2];
				}
			}

			unset($oImageFunc);
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_file.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_file.php']);
	}
?>