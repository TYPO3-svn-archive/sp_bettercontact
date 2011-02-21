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
		protected $oImageFunc   = NULL;


		/**
		 * Set configuration for file object
		 *
		 * @param object $poParent Instance of the parent object
		 */
		public function __construct ($poParent) {
			$this->oCObj        = &$poParent->cObj;
			$this->oCS          = &$poParent->oCS;
			$this->aConfig      = &$poParent->aConfig;
			$this->aFields      = &$poParent->aFields;
			$this->aLL          = &$poParent->aLL;
			$this->aGP          = &$poParent->aGP;
		}


		/**
		 * Get valid files from given array
		 *
		 * @param array $paFiles Files to parse
		 * @return array All valid uploaded files
		 */
		public function aGetFiles (array $paFiles) {
			if (empty($paFiles)) {
				return array();
			}

			// Get upload dir
			if (!$sPath = $this->sGetUploadPath('file')) {
				return array();
			}

			// Get basic file functions
			$oFileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');

			// Get files
			$aResult = array();
			foreach ($paFiles as $aData) {
				$aFiles = $this->aPrepareFiles($aData);
				foreach ($aFiles as $sKey => $aFile) {
					// Check if uploaded file is valid
					if (!is_uploaded_file($aFile['tmp_name']) || $aFile['error'] != 'UPLOAD_ERR_OK' || $aFile['size'] <= 0) {
						continue;
					}

					// Get unique file name
					$sFileName = $oFileFunc->getUniqueName($aFile['name'], $sPath);

					// Move to upload dir
					@move_uploaded_file($aFile['tmp_name'], $sFileName);

					// Check file again
					if (is_readable($sFileName)) {
						$aFileInfo = t3lib_div::split_fileref($sFileName);
						$sLink     = $this->sGetRelativePath($sFileName);
						$aResult[$sKey] = array(
							'path' => $sFileName,
							'link' => $sLink,
							'type' => $aFileInfo['fileext'],
							'name' => $aFileInfo['filebody'],
							'size' => round((int) filesize($sFileName) / 1024), // KB
						);
					}
				}
			}

			unset($oFileFunc);

			return $aResult;
		}


		/**
		 * Find or create upload path for given type
		 *
		 * @param string $psType Type of the folder
		 * @return string Path to upload folder
		 */
		protected function sGetUploadPath ($psType) {
			$sPath = PATH_site . 'uploads/tx_spbettercontact/';

			// Check configuration
			$psType = strtolower($psType);
			if (!empty($psType) && !empty($this->aConfig[$psType . 'Path'])) {
				$sPath = t3lib_div::getFileAbsFileName($this->aConfig[$psType . 'Path']);
			}

			// Try to create if not exists
			if (!file_exists($sPath)) {
				if (!mkdir($sPath, 0644, TRUE)) {
					return '';
				}
			}

			return rtrim($sPath, '/') . '/';
		}


		/**
		 * Returns relative path of an absolute one
		 *
		 * @param string $psPath Absolute path
		 * @return string Relative path
		 */
		protected function sGetRelativePath ($psPath) {
			$psPath = str_replace(PATH_site, '', $psPath);
			return t3lib_div::locationHeaderUrl($psPath);
		}


		/**
		 * Prepare file array
		 *
		 * @param array $paData Unformated data from $_FILES array
		 * @return array Clean file array
		 */
		protected function aPrepareFiles (array $paData) {
			if (empty($paData)) {
				return array();
			}

			$aFiles = array();
			foreach ($paData as $sInfoKey => $aInfoData) {
				foreach ($aInfoData as $sFieldKey => $sFieldData) {
					$aFiles[$sFieldKey][$sInfoKey] = $sFieldData;
				}
			}

			return $aFiles;
		}


		/**
		 * Convert all image files
		 *
		 * @param string $paFiles All uploaded files
		 */
		public function vConvertImages (array &$paFiles) {
			if (empty($paFiles) || empty($GLOBALS['TYPO3_CONF_VARS']['GFX']['im'])
			 || empty($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_path'])) {
				return;
			}

			if (empty($this->aConfig['enableImages']) && empty($this->aConfig['enableThumbnails'])) {
				return;
			}

			// Get image upload dir
			if (!$sImagePath = $this->sGetUploadPath('image')) {
				return;
			}

			// Get basic image functions
			$this->oImageFunc = t3lib_div::makeInstance('t3lib_stdGraphic');
			$this->oImageFunc->init();
			$this->oImageFunc->absPrefix = PATH_site;

			// Convert images into new format
			foreach ($paFiles as $sKey => $aFile) {
				$aOptions = $this->aFields[$sKey];
				$aOptions['imagePath'] = $sImagePath;

				// Get image configuration
				$aOptions['image'] = array(
					'maxW'   => (!empty($aOptions['imageMaxWidth'])  ? $aOptions['imageMaxWidth']  : ''),
					'maxH'   => (!empty($aOptions['imageMaxHeight']) ? $aOptions['imageMaxHeight'] : ''),
					'minW'   => (!empty($aOptions['imageMinWidth'])  ? $aOptions['imageMinWidth']  : ''),
					'minH'   => (!empty($aOptions['imageMinHeight']) ? $aOptions['imageMinHeight'] : ''),
				);

				// Get thumbnail configuration
				$aOptions['thumb'] = array(
					'width'  => (!empty($aOptions['thumbWidth'])     ? $aOptions['thumbWidth']     : ''),
					'height' => (!empty($aOptions['thumbHeight'])    ? $aOptions['thumbHeight']    : ''),
				);

				// Get image
				$paFiles[$sKey] = $this->aConvertSingleImage($aFile, $aOptions);
			}

			unset($this->oImageFunc);
		}


		/**
		 * Convert a single image
		 * 
		 * @param array $paFile File information
		 * @param array $paOptions Image configuration options
		 * @return array New file information
		 */
		protected function aConvertSingleImage (array $paFile, array $paOptions) {
			if (empty($paFile) || empty($paOptions)) {
				return $paFile;
			}

			$paFile['image'] = array();
			$paFile['thumb'] = array();

			// Check what to do
			$bConvertImage = (!empty($paOptions['imageConvertTo']) || !empty($paOptions['imageMaxWidth'])
			 || !empty($paOptions['imageMaxHeight']) || !empty($paOptions['imageMinWidth']) || !empty($paOptions['imageMinHeight']));
			$bConvertThumb = (!empty($paOptions['thumbWidth']) || !empty($paOptions['thumbHeight']) || !empty($paOptions['thumbConvertTo']));

			// Get image information for current file if no conversion is required
			if (!$bConvertImage && !empty($this->aConfig['enableImages'])) {
				if ($aImageInfo = $this->oImageFunc->getImageDimensions($paFile['path'])) {
					$paFile['image']['width']  = (int) $aImageInfo[0];
					$paFile['image']['height'] = (int) $aImageInfo[1];
				}
				if (!$bConvertThumb) {
					return $paFile;
				}
			}

			// Create thumbnail first
			if ($bConvertThumb && !empty($this->aConfig['enableThumbnails'])) {
				// Convert and add to image directory
				$sNewType  = (!empty($paOptions['thumbConvertTo']) ? ltrim($paOptions['thumbConvertTo'], '.') : '');
				$iWidth    = (int) $paOptions['thumb']['width'];
				$iHeight   = (int) $paOptions['thumb']['height'];
				$aFileInfo = $this->oImageFunc->imageMagickConvert($paFile['path'], $sNewType, $iWidth, $iHeight, '', '', '', TRUE);
				$sFileName = $paOptions['imagePath'] . $paFile['name'] . '_thumb.' . $aFileInfo[2];
				copy($aFileInfo[3], $sFileName);
				unlink($aFileInfo[3]);

				// Add thumbnail information
				$paFile['thumb']['type']   = $aFileInfo[2];
				$paFile['thumb']['path']   = $sFileName;
				$paFile['thumb']['link']   = $this->sGetRelativePath($sFileName);
				$paFile['thumb']['width']  = (int) $aFileInfo[0];
				$paFile['thumb']['height'] = (int) $aFileInfo[1];
			}

			// Create image
			if ($bConvertImage && !empty($this->aConfig['enableImages'])) {
				// Convert and overwrite existing image
				$sNewType  = (!empty($paOptions['imageConvertTo']) ? ltrim($paOptions['imageConvertTo'], '.') : '');
				$aFileInfo = $this->oImageFunc->imageMagickConvert($paFile['path'], $sNewType, '', '', '', '', $paOptions['image'], TRUE);
				print_r('<pre>');print_r($aFileInfo);die('</pre>');
				$sFileName = $paOptions['imagePath'] . $paFile['name'] . '.' . $aFileInfo[2];
				copy($aFileInfo[3], $sFileName);
				unlink($aFileInfo[3]);

				// Remove old image if type or folder has changed
				if ($sFileName != $paFile['path']) {
					unlink($paFile['path']);
				}

				// Add image information
				$paFile['type'] = $aFileInfo[2];
				$paFile['path'] = $sFileName;
				$paFile['link'] = $this->sGetRelativePath($sFileName);
				$paFile['image']['width']  = (int) $aFileInfo[0];
				$paFile['image']['height'] = (int) $aFileInfo[1];
			}


			// print_r('<pre>');print_r($paFile);die('</pre>');
			// TODO: No ending at image file path ???


			return $paFile;
		}


		/**
		 * Get file markers
		 *
		 * @param array $paFiles Uploaded files
		 * @return Array with markers
		 */
		public function aGetMarkers (array $paFiles) {
			$sImageTypes = (!empty($GLOBALS['GFX']['imagefile_ext']) ? $GLOBALS['GFX']['imagefile_ext'] : 'gif,jpg,png');
			$aImageTypes = t3lib_div::trimExplode(',', $sImageTypes, TRUE);
			$sImageWrap  = (!empty($this->aConfig['imageWrap']) ? $this->aConfig['imageWrap'] : '<img src="###SRC###" />');
			$sThumbWrap  = (!empty($this->aConfig['thumbWrap']) ? $this->aConfig['thumbWrap'] : '<img src="###SRC###" />');
			$aMarkers    = array();

			foreach ($paFiles as $sKey => $aFile) {
				$aField = $this->aFields[$sKey];

				// Add file marker
				$aMarkers[$aField['fileName']] = $aFile['link'];

				// Add image marker
				if (!empty($this->aConfig['enableImages']) && in_array($aFile['type'], $aImageTypes)) {
					$sWidth    = (!empty($aFile['image']['width'])  ? $aFile['image']['width']  : '');
					$sHeight   = (!empty($aFile['image']['height']) ? $aFile['image']['height'] : '');
					$sTitle    = $aField['imageTitle'];
					$sAlt      = (empty($aField['imageAlt']) ? $aField['imageTitle'] : $aField['imageAlt']);
					$sImageTag = str_replace(
						array('###SRC###', '###HEIGHT###', '###WIDTH###', '###TITLE###', '###ALT###'),
						array($aFile['link'], $sHeight, $sWidth, $sTitle, $sAlt),
						$sImageWrap
					);
					$aMarkers[$aField['imageName']] = $sImageTag;
				}

				// Add thumbnail marker
				if (!empty($this->aConfig['enableThumbnails']) && !empty($aFile['thumb']['type']) && in_array($aFile['thumb']['type'], $aImageTypes)) {
					$sWidth    = (!empty($aFile['thumb']['width'])  ? $aFile['thumb']['width']  : '');
					$sHeight   = (!empty($aFile['thumb']['height']) ? $aFile['thumb']['height'] : '');
					$sTitle    = $aField['thumbTitle'];
					$sAlt      = (empty($aField['thumbAlt']) ? $aField['thumbTitle'] : $aField['thumbAlt']);
					$sThumbTag = str_replace(
						array('###SRC###', '###HEIGHT###', '###WIDTH###', '###TITLE###', '###ALT###'),
						array($aFile['thumb']['link'], $sHeight, $sWidth, $sTitle, $sAlt),
						$sThumbWrap
					);
					$aMarkers[$aField['thumbName']] = $sThumbTag;
				}
			}

			return $aMarkers;
		}

	}


	if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_file.php']) {
		include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sp_bettercontact/pi1/class.tx_spbettercontact_pi1_file.php']);
	}
?>