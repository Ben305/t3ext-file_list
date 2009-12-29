<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006-2009 Moreno Feltscher <moreno@feltscher.ch>
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

require_once(PATH_tslib . 'class.tslib_pibase.php');
require_once('t3lib/class.t3lib_befunc.php');

/**
 * Plugin 'File List' for the 'file_list' extension.
 *
 * @package     TYPO3
 * @subpackage  tx_filelist
 * @author      Moreno Feltscher <moreno@feltscher.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @version     SVN: $Id$
 */
class tx_filelist_pi1 extends tslib_pibase {
	
	// Members coming from tslib_pibase
	public $prefixId = 'tx_filelist_pi1';
	public $scriptRelPath = 'pi1/class.tx_filelist_pi1.php';
	public $extKey = 'file_list';

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * Parameter names
	 * @var array
	 */
	protected $params;

	/**
	 * Plugin arguments (read from URL)
	 * @var array
	 */
	protected $args;
	
	/**
	 * Main-function, returns output
	 *
	 * @param	string		$content: The Plugin content
	 * @param	array		$settings: The Plugin configuration
	 * @return	The	content that is displayed on the website
	 */
	public function main($content, array $settings) {
		$this->init($settings);
		$this->pi_setPiVarDefaults();
	
			// Parameters for frontend rendering
		$uid = $this->cObj->data['uid'];
		$this->params = array(
			'path'      => $this->pi_getClassName('path') . '-' . $uid,
			'order_by'  => $this->pi_getClassName('sort') . '-' . $uid,
			'direction' => $this->pi_getClassName('dir') . '-' . $uid,
		);

			// Retrieval of arguments
		$this->args = array(
			'path'      => t3lib_div::_GET($this->params['path']),
			'order_by'  => t3lib_div::_GET($this->params['order_by']),
			'direction' => t3lib_div::_GET($this->params['direction']),
		);
		
			// Preparing some arrays
		$tx_folders = array();
		$tx_files = array();

			// Preparing the path to the directory
		$pathOptions = t3lib_div::trimExplode(' ', $this->settings['path']);
		$this->settings['path'] = $this->sanitizePath($pathOptions[0]);

			// Is the directory readable?
		if (!@is_readable($this->settings['path'])) {
			$content = 'Could not open ' . $this->settings['path'];
		}
			// Checking get-parameters
		if (!$this->args['path']) {
			$temp_path = $this->settings['path'];
		}
		else {
			if ((substr($this->args['path'], 0, 2) !== '..') && (!preg_match('/\./', $this->args['path']))) {
				$temp_path = $this->sanitizePath($this->settings['path'] . $this->args['path']);
				if (substr($temp_path, -3, 3) === '%2F' || substr($temp_path, -4, 3) === '%2F') {
					$temp_path = preg_replace('/%2F/', '', $temp_path);
				}
			}
			else {
				$temp_path = $this->settings['path'];
			}
		}

		if ($this->settings['fe_sort'] && $this->args['direction']) {
			$this->settings['sort_direction'] = $this->args['direction']; 
		}
		if ($this->settings['fe_sort'] && $this->args['order_by']) {
			$this->settings['order_by'] = $this->args['order_by'];
			if (!t3lib_div::inList('name,date,size', $this->settings['order_by'])) {
				$this->settings['order_by'] = 'name';
			}
		}

		list($tx_folders, $tx_files) = $this->getDirectoryContent($temp_path);

			// Are there any files in the directory?
		if ((count($tx_files) == 0) && (count($tx_folders) == 0)) {
			$content = $this->pi_getClassName('no_files');
		}
		else {
				/* Sort Start */
			if (count($tx_folders) > 0 && $this->settings['order_by'] === 'name') {
				foreach ($tx_folders as $tx_key => $tx_row) {
					$sortArr[$tx_key] = $tx_row['name'];
				}
				$direction = $this->settings['sort_direction'] === 'asc' ? SORT_ASC : SORT_DESC;
				$ok_sort = array_multisort($sortArr, $direction, $tx_folders);
			}
			if (count($tx_files) > 0) {
				foreach ($tx_files as $tx_key => $tx_row) {
					$sortArr[$tx_key] = $tx_row[$this->settings['order_by']];
				}
				$direction = $this->settings['sort_direction'] === 'asc' ? SORT_ASC : SORT_DESC;
				$ok_sort = array_multisort($sortArr, $direction, $tx_files);
			}
				/* Sort End */

				// Preparing the table
			$content = '<table border="0" cellspacing="0" cellpadding="0" class="' . $this->pi_getClassName('table') . '">';
			$content .= '<tr class="' . $this->pi_getClassName('header-tr') . '">';
			$content .= '<td width="30" class="' . $this->pi_getClassName('header-icon') . '"></td>'; //Icon
			$content .= '<td align="left" valign="middle" class="' . $this->pi_getClassName('header-filename') . '">' . htmlspecialchars($this->pi_getLL('filename'));  // Filename
			if ($this->settings['fe_sort']) {
				$content .= $this->fe_sort('name', 'desc');
				$content .= $this->fe_sort('name', 'asc');
			}
			$content .= '</td>';
			$content .= '<td align="left" valign="middle" class="' . $this->pi_getClassName('header-info') . '">' . htmlspecialchars($this->pi_getLL('info')); //Info
			if ($this->settings['fe_sort']) {
				$content .= $this->fe_sort('size', 'desc');
				$content .= $this->fe_sort('size', 'asc');
			}
			$content .= '</td>';
			$content .= '<td align="left" valign="middle" class="' . $this->pi_getClassName('header-last_modification') . '">' . htmlspecialchars($this->pi_getLL('last_modification')); //Last modification
			if ($this->settings['fe_sort']) {
				$content .= $this->fe_sort('date', 'desc');
				$content .= $this->fe_sort('date', 'asc');
			}
			$content .= '</td>';
			$content .= '</tr>';

			if (count($tx_folders) >= 0) {

					// Put '..' on the start of the array
				$temp_tx_folders = array_reverse($tx_folders);
				$temp_tx_folders[] = array(
					'name' => '..',
					'path' => $temp_path . '..'
				);
				$tx_folders = array_reverse($temp_tx_folders);

					// Displays the folders in a table
				for ($d = 0; $d < count($tx_folders); $d++) {
					if (!(!$this->args['path'] && $tx_folders[$d]['name'] === '..')) {
						$content .= '<tr class="' .$this->pi_getClassName('tr') . '">';
						$content .= '<td class="' .$this->pi_getClassName('icon') . '">';
						if ($tx_folders[$d]['name'] === '..') {
							$content .= '<img src="' . $this->settings['iconsPath'] . 'move_up.png" alt="' . $tx_folders[$d]['name'] . '"';
						}
						else {
							$content .= '<img src="' . $this->settings['iconsPath'] . 'folder.png" alt="' . $tx_folders[$d]['name'] . '"';
						}
						$content .= '</td>';
						$content .= '<td class"' . $this->pi_getClassName('filename') . '">';
						$content .= '<a href="index.php?id=' . $GLOBALS['TSFE']->id;
						if (!$this->args['path']) {
							$content .= '&' . $this->params['path'] . '=' . $tx_folders[$d]['name'];
						}
						else {
							if ($tx_folders[$d]['name'] === '..' && similar_text(preg_replace('/\//', '%2F', $this->args['path']) ,'%2F') >= 3) {
								$temp = explode('%2F', preg_replace('/\//', '%2F', $this->args['path']));
								$temp1 = count($temp)-1;
								$content = $content . '&' . $this->params['path'] . '=' . preg_replace('/%2F/' . $temp[$temp1], '', preg_replace('/\//', '%2F', $this->args['path']));
							}
							else {
								if ($tx_folders[$d]['name'] !== '..') {
									$content .= '&' . $this->params['path'] . '=' . preg_replace('/\//', '%2F', $this->args['path']) . '%2F' . $tx_folders[$d]['name'];
								}
							}
						}
						$content .= '">' . $tx_folders[$d]['name'] . '</a></td>';
						$content .= '<td class="' . $this->pi_getClassName('info') . '"><font size="1">';
						$file_counter = $this->filecounter($temp_path.$tx_folders[$d]['name']);
						$content .= $file_counter . ' ' . htmlspecialchars($this->pi_getLL('files_in_directory')) . '</font></td>';
						$content .= '<td class="' . $this->pi_getClassName('last_modification') . '"><font size="1">';
						$content .= t3lib_BEfunc::datetime(@filemtime($temp_path . $tx_folders[$d]['name']));
						$content .= '</font></td>';
						$content .= '</tr>';
					}
				}
			}

				// Displays the files in a table
			if (count($tx_files) != 0) {
				for ($f = 0; $f < count($tx_files); $f++) {
					$content .= '<tr class="' . $this->pi_getClassName('tr') . '">';
					$content .= '<td class="' . $this->pi_getClassName('icon') . '">';
					$content .= '<img src="' . $this->settings['iconsPath'] . $this->fileicon($tx_files[$f]['name']) . '" alt="' . $tx_files[$f]['name'] . '">';
					$content .= '</td><td valign="bottom" class="' . $this->pi_getClassName('filename') . '">';
					$content .= '<a href="' . $tx_files[$f]['path'] . '" target="_blank">' . $tx_files[$f]['name'] . '</a> ';
					$content .= $this->show_new($tx_files[$f]['path'], $this->settings['new_duration']) . '</td>';
					$content .= '<td><font size="1">' . $this->getHRFileSize($tx_files[$f]['path']) . '</font></td>';
					$content .= '<td class="' . $this->pi_getClassName('last_modification') . '"><font size="1">';
					$content .= t3lib_BEfunc::datetime(@filemtime($temp_path.$tx_files[$f]['name'])) . '</font></td>';
				}
			}
			$content .= '</table>';
		}
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * How many files are in the directory?
	 *
	 * @param	string		Path to the specified directory
	 * @return	integer		Number of files in the directory
	 */
	protected function filecounter($counter_dir) {
		$counter = 0;
		$counter_open = @opendir($counter_dir);
		while ($counter_content = readdir($counter_open)) {
			if (is_file($counter_dir . '/' . $counter_content) && $counter_content != 'thumb') {
				$counter++;
			}
		}
		return $counter;
	}

	/**
	 * Returns the icon which represents a file-type
	 *
	 * @param	string		Path to the specified file
	 * @return	string		Filename of the icon
	 */
	protected function fileicon($fn) {
		$allfileends = array(
			'doc', 'pdf', 'pps', 'tar', 'txt', 'xls', 'swf', 'htm', 'html', 'phtml', 'gif', 'jpg', 'jpeg', 'png', 'bpm', 'mp3', 'wav', 'wmv', 'tar', 'gz', 'txt', 'mp4', 'mpg', 'mpeg', 'tif'
		);
		$normfileend = array('doc', 'pdf', 'pps', 'tar', 'txt', 'xls');
		$fileends = array(
			'draw' => array('draw'),
			'flash' => array('flash', 'swf'),
			'html' => array('html', 'htm', 'phtml'),
			'image' => array('image', 'gif', 'jpg', 'jpeg', 'png', 'bpm', 'tif'),
			'sound' => array('sound','mp3', 'wav', 'wmv'),
			'source' => array('source'),
			'tar' => array('tar', 'gz'),
			'txt' => array('txt'),
			'video' => array('video', 'mp4', 'mpg', 'mpeg')
		);
		$fileend = explode('.', $fn);
		$f_count = count($fileend) - 1;
		$fileend = strtolower($fileend[$f_count]);
		if (in_array($fileend, $allfileends)) {
			if (in_array($fileend, $normfileend)) {
				return $fileend . '.png';
			}
			else {
				foreach($fileends as $temp_fileend) {
					if (in_array($fileend, $temp_fileend)) {
						return $temp_fileend[0] . '.png';
					}
				}
			}
		}
		else {
			return 'mime.png';
		}
	}

	/**
	 * Gets content of a directory.
	 * 
	 * @param	string		$path
	 * @return	array		list(array $directories, array $files)
	 */
	protected function getDirectoryContent($path) {
		$dirs = array();
		$files = array();

			// Open the directory and read out all folders and files
		$dh = @opendir($path);
		while ($dir_content = @readdir($dh)) {
			if ($dir_content != '.' && $dir_content !== 'thumb' && $dir_content !== '..') {
				if (is_dir($path . '/' . $dir_content)) {
					$dirs[] = array(
						'name' => $dir_content,
						'path' => $path . $dir_content
					);
				}
				elseif (is_file($path . '/' . $dir_content)) {
					$files[] = array(
						'name' => $dir_content,
						'date' => filemtime($path . $dir_content),
						'size' => filesize($path . $dir_content),
						'path' => $path . $dir_content
					);
				}
			}
		}
			// Close the directory
		@closedir($dh);
		return array($dirs, $files);
	}

	/**
	 * Sanitizes a path by making sure a trailing slash is present and
	 * all directories are resolved (no more '../' within string).
	 *   
	 * @param	string		$path: Path relative to website root (normally within fileadmin/)
	 * @param	string		$openBaseDir: Optional root. If set $path must be within this directory
	 * @return	string
	 */
	protected function sanitizePath($path, $openBaseDir = 'fileadmin') {
		if (substr($path, -1, 1) !== '/') {
			$path .= '/';
		}
		// TODO: use openBaseDir
		return $path;
	}

	/**
	 * Returns a human-readable size of a file.
	 *
	 * @param	string		Path to the specified file
	 * @return	string		Size of the file
	 */
	protected function getHRFileSize($filename) {
		$units = array(
			'0' => $this->pi_getLL('units.bytes'),
			'1' => $this->pi_getLL('units.KB'),
			'2' => $this->pi_getLL('units.MB'),
			'3' => $this->pi_getLL('units.GB'),
			'4' => $this->pi_getLL('units.TB'),
		);
		$filesize = @filesize($filename);
		for ($offset = 0; $filesize >= 1024; $offset++) {
			$filesize /= 1024;
		}
		$decimalPlaces = ($offset < 2) ? 0 : $offset - 1;
		$format = '%.' . $decimalPlaces . 'f %s';
		return sprintf($format, $filesize, $units[$offset]);
	}

	/**
	 * Returns the date of the last modification
	 *
	 * @param	string		Path to the specified file
	 * @return	string		Last modification of file
	 */
    protected function file_create_date($fn) {
		$filedate = filemtime($fn);
		return date('d-m-y H:i', $filedate);
	}

	/**
	 * Returns the new-icon, when the file is selected as new
	 *
	 * @param	string		Path to the specified file
	 * @param	integer		With how much of days a file is new?
	 * @return	string		Returns the 'new-icon'
	 */
	protected function show_new($fn, $duration) {
		if ($duration > 0) {
			if (filemtime($fn) > mktime(0, 0, 0, date('m'), date('d') - $duration, date('Y'))) {
				return '<img src="' . $this->settings['iconsPath'] . $this->pi_getLL('new_icon') . '.png" alt="' . $this->pi_getLL('new_text') . '">';
			}
			else {
				return '';
			}
		}
		else {
			return '';
		}
	}

	/**
	 * Returns the icons, with witch the user on the frontend can sort the files
	 *
	 * @param	string		Order by (name, date, size)
	 * @param	string		Order sequence (ASC, DESC)
	 * @return	string		Return of images for sorting
	 */
	protected function fe_sort($order_by, $order_seq) {
		$temp_content = ' <a href="index.php?id=' . $GLOBALS['TSFE']->id;
		if ($this->args['path']) {
			$temp_content = $temp_content . '&' . $this->params['path'] . '=' . preg_replace('/\//', '%2F', $this->args['path']);
		}
		$temp_content = $temp_content . '&' . $this->params['order_by'] . '=' . $order_by . '&' . $this->params['direction'] . '=' . $order_seq . '"><img src="' . $this->settings['iconsPath'];
		if ($order_seq == 'asc') {
			$temp_content = $temp_content . 'up.gif" alt="' . htmlspecialchars($this->pi_getLL('asc')) . '" border="0"></a>';
		}
		if ($order_seq == 'desc') {
			$temp_content = $temp_content . 'down.gif" alt="' . htmlspecialchars($this->pi_getLL('desc')) . '" border="0"></a>';
		}
		return $temp_content;
	}

	/**
	 * This method performs various initializations.
	 *
	 * @param	array		$settings: Plugin configuration, as received by the main() method
	 * @return	void
	 */
	protected function init(array $settings) {
		$this->settings = $settings;

			// Load the flexform and loop on all its values to override TS setup values
			// Some properties use a different test (more strict than not empty) and yet some others no test at all
			// see http://wiki.typo3.org/index.php/Extension_Development,_using_Flexforms
		$this->pi_initPIflexForm(); // Init and get the flexform data of the plugin

			// Assign the flexform data to a local variable for easier access
		$piFlexForm = $this->cObj->data['pi_flexform'];
		
		if (is_array($piFlexForm['data'])) {
				// Traverse the entire array based on the language
				// and assign each configuration option to $this->settings array...
			foreach ($piFlexForm['data'] as $sheet => $langData) {
				foreach ($langData as $lang => $fields) {
					foreach (array_keys($fields) as $field) {
						$value = $this->pi_getFFvalue($piFlexForm, $field, $sheet);	
							
						if (!empty($value)) {
							if (in_array($field, $explodeFlexFormFields)) {
								$this->settings[$field] = explode(',', $value);
							} else {
								$this->settings[$field] = $value;
							}
						}
					}
				}
			}
		}

		if (isset($this->settings['iconsPath'])) {
			$iconsPath = $this->cObj->stdWrap($this->settings['iconsPath'], $this->settings['iconsPath.']);
			$this->settings['iconsPath'] = $this->resolveSiteRelPath($iconsPath);
		} else {	// Fallback
			$this->settings['iconsPath'] = t3lib_extMgm::siteRelPath('file_list') . 'Resources/Public/Icons/';
		}
		
			// Disable Filelist if an error occurred
		$this->error = 0;
			// Load language data
		$this->pi_loadLL();
			// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		$this->pi_USER_INT_obj = 1;
	}

	/**
	 * Resolves a site-relative path and or filename.
	 * 
	 * @param	string		$path
	 * @return	string
	 */
	protected function resolveSiteRelPath($path) {
		if (strcmp(substr($path, 0, 4), 'EXT:')) {
			return $path;
		}
		$path = substr($path, 4);	// Remove 'EXT:' at the beginning
		$extension = substr($path, 0, strpos($path, '/'));
		$references = explode(':', substr($path, strlen($extension) + 1));
		$pathOrFilename = $references[0];

		if (is_dir(t3lib_extMgm::extPath($extension) . $pathOrFilename)) {
				// Ensure a trailing slash is present
			if (substr($pathOrFilename, -1, 1) !== '/') {
				$pathOrFilename .= '/';
			}
		}

		return t3lib_extMgm::siteRelPath($extension) . $pathOrFilename;
	}

	/**
	 * Loads local-language values by looking for a "locallang.php" file in the plugin class directory ($this->scriptRelPath) and if found includes it.
	 * Also locallang values set in the TypoScript property "_LOCAL_LANG" are merged onto the values found in the "locallang.php" file.
	 * Overrides the base method to load language file from new directory structure.
	 *
	 * @return	void
	 */
	public function pi_loadLL() {
		if (!$this->LOCAL_LANG_loaded) {
			$llFile = t3lib_extMgm::extPath($this->extKey) . 'Resources/Private/Language/locallang_pi1.xml';

				// Read the strings in the required charset (since TYPO3 4.2)
			$this->LOCAL_LANG = t3lib_div::readLLfile($llFile, $this->LLkey, $GLOBALS['TSFE']->renderCharset);
			if ($this->altLLkey) {
				$tempLOCAL_LANG = t3lib_div::readLLfile($llFile, $this->altLLkey);
				$this->LOCAL_LANG = array_merge(is_array($this->LOCAL_LANG) ? $this->LOCAL_LANG : array(), $tempLOCAL_LANG);
			}

				// Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
			if (is_array($this->conf['_LOCAL_LANG.'])) {
				foreach ($this->conf['_LOCAL_LANG.'] as $k => $lA) {
					if (is_array($lA)) {
						$k = substr($k, 0, -1);
						foreach ($lA as $llK => $llV) {
							if (!is_array($llV)) {
								$this->LOCAL_LANG[$k][$llK] = $llV;
									// For labels coming from the TypoScript (database) the charset is assumed to be "forceCharset" and if that is not set, assumed to be that of the individual system languages
								$this->LOCAL_LANG_charset[$k][$llK] = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->csConvObj->charSetArray[$k];
							}
						}
					}
				}
			}
		}
		$this->LOCAL_LANG_loaded = 1;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/file_list/pi1/class.tx_filelist_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/file_list/pi1/class.tx_filelist_pi1.php']);
}

?>