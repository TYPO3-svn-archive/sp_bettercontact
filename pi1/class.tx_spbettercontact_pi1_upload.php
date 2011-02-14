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


	/**
	 * Uploaded files handling class for the 'sp_bettercontact' extension.
	 *
	 * @author     Kai Vogel <kai.vogel ( at ) speedprogs.de>
	 * @package    TYPO3
	 * @subpackage tx_spbettercontact
	 */
	class tx_spbettercontact_pi1_upload {
		protected $aConfig      = array();
		protected $aLL          = array();
		protected $aGP          = array();
		protected $aFields      = array();
		protected $oCObj        = NULL;
		protected $oCS          = NULL;


		/**
		 * Set configuration for upload object
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
		public function aGetFiles () {
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
			foreach ($_FILES as $sFieldName => $aFile) {
				$sFieldName = strtolower(trim($sFieldName));

				// No configuration found, continue
				if (empty($this->aFields[$sFieldName]['file']) && empty($this->aFields[$sFieldName]['image'])) {
					continue;
				}

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
					$aFiles[$sFieldName] = array(
						'name' => $sFileName,
						'type' => $aFileInfo['fileext'],
						'real' => $aFileInfo['filebody'],
					);
				}

				// Manipulate file if its an image and image settings are configured
				if (!empty($this->aFields[$sFieldName]['image'])) {
					// Todo
				}
			}

			unset($oFileFunc);

			return $aFiles;
		}


		/**
		 * Convert an image
		 * 
		 * @param string $psFileName Path to image file
		 * @return string New file name
		 */
		public function sConvertImage ($psFileName) {
			return '';
		}


		/**
		 * Get files from session
		 * Compare with uploaded files and use only not already set files
		 * Generate temp name
		 * move_uploaded_file to temp name
		 * Add file to session (!)
		 * 
		 * Add file markers to use files in templates
		 * Log files in table / Show in backend
		 * Add error messages for invalid files
		 * Add configuration settings for files
		 */ 

		/**
			picture {
				required     = 0
				file {
					maxSize    = 1024
					minSize    = 0
					allowed    = gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png
					disallowed = 
				}
				image {
					maxSize    = 1024x768
					minSize    = 10x10
					convertTo  = jpg
					quality    = 80
				}
			}
		*/
	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_file.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_file.php']);
	}
?>