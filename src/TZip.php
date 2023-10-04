<?php

namespace tekintian;

/**
 *
 * PHP zip打包压缩工具类 通用版
 *
 * 使用方法:
 * 1实例化对象:  $zip = new TZip();
 * CI4里面使用工厂对象来实例化 $zip = Factories::library(\App\Libraries\TZip::class);
 *
 * 2读取目录 $zip->readDir( '/usr/www/xxx', false );  添加目录 $zip->addDir('/usr/www/xxx');
 * 3下载或者打包目录中的文件 $zip->download( $file );   $zip->archive( $path );
 * 4清理  $zip->clearData();

 * Zip Compression Class
 * @Author tekintian@gmail.com
 * @website http://dev.yunnan.ws
 *
 */
class TZip {

	/**
	 * Zip data in string form
	 *
	 * @var string
	 */
	public $zipdata = '';

	/**
	 * Zip data for a directory in string form
	 *
	 * @var string
	 */
	public $directory = '';

	/**
	 * Number of files/folder in zip file
	 *
	 * @var int
	 */
	public $entries = 0;

	/**
	 * Number of files in zip
	 *
	 * @var int
	 */
	public $fileNum = 0;

	/**
	 * relative offset of local header
	 *
	 * @var int
	 */
	public $offset = 0;

	/**
	 * Reference to time at init
	 *
	 * @var int
	 */
	public $now;

	/**
	 * The level of compression
	 *
	 * Ranges from 0 to 9, with 9 being the highest level.
	 *
	 * @var	int
	 */
	public $compression_level = 2;

	/**
	 * mbstring.func_overload flag
	 *
	 * @var	bool
	 */
	protected static $func_overload;

	/**
	 * Initialize zip compression class
	 *
	 * @return	void
	 */
	public function __construct() {
		// 这里判断是否设置了self::$func_overload
		// 或者根据PHP版本是否小于8.0同时已经加载和启用了mbstring扩展来设置self::$func_overload
		isset(self::$func_overload) OR self::$func_overload = (version_compare(PHP_VERSION, '8.0', '<') && extension_loaded('mbstring') && @ini_get('mbstring.func_overload'));

		$this->now = time();

		if (function_exists('log_message')) {
			log_message('info', 'TZip Compression Class Initialized');
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Add Directory
	 *
	 * Lets you add a virtual directory into which you can place files.
	 *
	 * @param	mixed	$directory	the directory name. Can be string or array
	 * @return	void
	 */
	public function addDir($directory) {
		foreach ((array) $directory as $dir) {
			if (!preg_match('|.+/$|', $dir)) {
				$dir .= '/';
			}

			$dir_time = $this->_get_mod_time($dir);
			$this->_addDir($dir, $dir_time['file_mtime'], $dir_time['file_mdate']);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get file/directory modification time
	 *
	 * If this is a newly created file/dir, we will set the time to 'now'
	 *
	 * @param	string	$dir	path to file
	 * @return	array	filemtime/filemdate
	 */
	protected function _get_mod_time($dir) {
		// 设置默认时区为中国PRC https://www.php.net/manual/zh/timezones.php
		date_default_timezone_set('PRC');
		// filemtime() may return false, but raises an error for non-existing files
		$date = file_exists($dir) ? getdate(filemtime($dir)) : getdate($this->now);

		return array(
			'file_mtime' => ($date['hours'] << 11) + ($date['minutes'] << 5) + $date['seconds'] / 2,
			'file_mdate' => (($date['year'] - 1980) << 9) + ($date['mon'] << 5) + $date['mday'],
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Add Directory
	 *
	 * @param	string	$dir	the directory name
	 * @param	int	$file_mtime
	 * @param	int	$file_mdate
	 * @return	void
	 */
	protected function _addDir($dir, $file_mtime, $file_mdate) {
		$dir = str_replace('\\', '/', $dir);

		$this->zipdata .=
		"\x50\x4b\x03\x04\x0a\x00\x00\x00\x00\x00"
		. pack('v', $file_mtime)
		. pack('v', $file_mdate)
		. pack('V', 0) // crc32
		. pack('V', 0) // compressed filesize
		. pack('V', 0) // uncompressed filesize
		. pack('v', self::strlen($dir)) // length of pathname
		. pack('v', 0) // extra field length
		. $dir
		// below is "data descriptor" segment
		. pack('V', 0) // crc32
		. pack('V', 0) // compressed filesize
		. pack('V', 0); // uncompressed filesize

		$this->directory .=
		"\x50\x4b\x01\x02\x00\x00\x0a\x00\x00\x00\x00\x00"
		. pack('v', $file_mtime)
		. pack('v', $file_mdate)
		. pack('V', 0) // crc32
		. pack('V', 0) // compressed filesize
		. pack('V', 0) // uncompressed filesize
		. pack('v', self::strlen($dir)) // length of pathname
		. pack('v', 0) // extra field length
		. pack('v', 0) // file comment length
		. pack('v', 0) // disk number start
		. pack('v', 0) // internal file attributes
		. pack('V', 16) // external file attributes - 'directory' bit set
		. pack('V', $this->offset) // relative offset of local header
		. $dir;

		$this->offset = self::strlen($this->zipdata);
		$this->entries++;
	}

	// --------------------------------------------------------------------

	/**
	 * Add Data to Zip
	 *
	 * Lets you add files to the archive. If the path is included
	 * in the filename it will be placed within a directory. Make
	 * sure you use addDir() first to create the folder.
	 *
	 * @param	mixed	$filepath	A single filepath or an array of file => data pairs
	 * @param	string	$data		Single file contents
	 * @return	void
	 */
	public function addData($filepath, $data = NULL) {
		if (is_array($filepath)) {
			foreach ($filepath as $path => $data) {
				// $path = realpath($path) . DIRECTORY_SEPARATOR;
				$file_data = $this->_get_mod_time($path);
				$this->_addData($path, $data, $file_data['file_mtime'], $file_data['file_mdate']);
			}
		} else {
			// $filepath = realpath($filepath) . DIRECTORY_SEPARATOR;
			$file_data = $this->_get_mod_time($filepath);
			$this->_addData($filepath, $data, $file_data['file_mtime'], $file_data['file_mdate']);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Add Data to Zip
	 *
	 * @param	string	$filepath	the file name/path
	 * @param	string	$data	the data to be encoded
	 * @param	int	$file_mtime
	 * @param	int	$file_mdate
	 * @return	void
	 */
	protected function _addData($filepath, $data, $file_mtime, $file_mdate) {
		$filepath = str_replace('\\', '/', $filepath);

		$uncompressed_size = self::strlen($data);
		$crc32 = crc32($data);
		$gzdata = self::substr(gzcompress($data, $this->compression_level), 2, -4);
		$compressed_size = self::strlen($gzdata);

		$this->zipdata .=
		"\x50\x4b\x03\x04\x14\x00\x00\x00\x08\x00"
		. pack('v', $file_mtime)
		. pack('v', $file_mdate)
		. pack('V', $crc32)
		. pack('V', $compressed_size)
		. pack('V', $uncompressed_size)
		. pack('v', self::strlen($filepath)) // length of filename
		. pack('v', 0) // extra field length
		. $filepath
			. $gzdata; // "file data" segment

		$this->directory .=
		"\x50\x4b\x01\x02\x00\x00\x14\x00\x00\x00\x08\x00"
		. pack('v', $file_mtime)
		. pack('v', $file_mdate)
		. pack('V', $crc32)
		. pack('V', $compressed_size)
		. pack('V', $uncompressed_size)
		. pack('v', self::strlen($filepath)) // length of filename
		. pack('v', 0) // extra field length
		. pack('v', 0) // file comment length
		. pack('v', 0) // disk number start
		. pack('v', 0) // internal file attributes
		. pack('V', 32) // external file attributes - 'archive' bit set
		. pack('V', $this->offset) // relative offset of local header
		. $filepath;

		$this->offset = self::strlen($this->zipdata);
		$this->entries++;
		$this->fileNum++;
	}

	// --------------------------------------------------------------------

	/**
	 * Read the contents of a file and add it to the zip
	 *
	 * @param	string	$path
	 * @param	bool	$archive_filepath
	 * @return	bool
	 */
	public function readFile($path, $archive_filepath = FALSE) {
		if (file_exists($path) && FALSE !== ($data = file_get_contents($path))) {
			if (is_string($archive_filepath)) {
				$name = str_replace('\\', '/', $archive_filepath);
			} else {
				$name = str_replace('\\', '/', $path);

				if ($archive_filepath === FALSE) {
					$name = preg_replace('|.*/(.+)|', '\\1', $name);
				}
			}

			$this->addData($name, $data);
			return TRUE;
		}

		return FALSE;
	}

	// ------------------------------------------------------------------------

	/**
	 * Read a directory and add it to the zip.
	 *
	 * This function recursively reads a folder and everything it contains (including
	 * sub-folders) and creates a zip based on it. Whatever directory structure
	 * is in the original file path will be recreated in the zip file.
	 *
	 * @param	string	$path	path to source directory
	 * @param	bool	$preserve_filepath
	 * @param	string	$root_path
	 * @return	bool
	 */
	public function readDir($path, $preserve_filepath = TRUE, $root_path = NULL) {
		$path = realpath($path) . DIRECTORY_SEPARATOR;
		if (!$fp = @opendir($path)) {
			return FALSE;
		}

		// Set the original directory root for child dir's to use as relative
		if ($root_path === NULL) {
			$root_path = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, dirname($path)) . DIRECTORY_SEPARATOR;
		}

		while (FALSE !== ($file = readdir($fp))) {
			if ($file[0] === '.') {
				continue;
			}

			if (is_dir($path . $file)) {
				$this->readDir($path . $file . DIRECTORY_SEPARATOR, $preserve_filepath, $root_path);
			} elseif (FALSE !== ($data = file_get_contents($path . $file))) {
				$name = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
				if ($preserve_filepath === FALSE) {
					$name = str_replace($root_path, '', $name);
				}

				$this->addData($name . $file, $data);
			}
		}

		closedir($fp);
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Get the Zip file
	 *
	 * @return	string	(binary encoded)
	 */
	public function getZip() {
		// Is there any data to return?
		if ($this->entries === 0) {
			return FALSE;
		}

		// @see https://github.com/bcit-ci/CodeIgniter/issues/5864
		$footer = $this->directory . "\x50\x4b\x05\x06\x00\x00\x00\x00"
		. pack('v', $this->entries) // total # of entries "on this disk"
		. pack('v', $this->entries) // total # of entries overall
		. pack('V', self::strlen($this->directory)) // size of central dir
		. pack('V', self::strlen($this->zipdata)) // offset to start of central dir
		. "\x00\x00"; // .zip file comment length
		return $this->zipdata . $footer;
	}

	// --------------------------------------------------------------------

	/**
	 * Write File to the specified directory
	 *
	 * Lets you write a file
	 *
	 * @param	string	$filepath	the file name
	 * @return	bool
	 */
	public function archive($filepath) {
		if (!($fp = @fopen($filepath, 'w+b'))) {
			return FALSE;
		}

		flock($fp, LOCK_EX);

		for ($result = $written = 0, $data = $this->getZip(), $length = self::strlen($data); $written < $length; $written += $result) {
			if (($result = fwrite($fp, self::substr($data, $written))) === FALSE) {
				break;
			}
		}

		flock($fp, LOCK_UN);
		fclose($fp);

		return is_int($result);
	}

	// --------------------------------------------------------------------

	/**
	 * Download
	 *
	 * @param	string	$filename	the file name
	 * @return	void
	 */
	public function download($filename = 'backup.zip') {
		if (!preg_match('|.+?\.zip$|', $filename)) {
			$filename .= '.zip';
		}

		$getZip = $this->getZip();
		$zip_content = &$getZip;

		$contentDispositionField = 'Content-Disposition: attachment; '
		. sprintf('filename="%s"; ', rawurlencode($filename))
		. sprintf("filename*=utf-8''%s", rawurlencode($filename));

		header('Content-Type: application/octet-stream');
		header($contentDispositionField);
		print $zip_content;

		// CI4 resp download
		//response()->download($filename, $zip_content)->send();
	}

	// --------------------------------------------------------------------

	/**
	 * Initialize Data
	 *
	 * Lets you clear current zip data. Useful if you need to create
	 * multiple zips with different data.
	 *
	 * @return	TZip
	 */
	public function clearData() {
		$this->zipdata = '';
		$this->directory = '';
		$this->entries = 0;
		$this->fileNum = 0;
		$this->offset = 0;
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Byte-safe strlen()
	 *
	 * @param	string	$str
	 * @return	int
	 */
	protected static function strlen($str) {
		return (self::$func_overload)
		? mb_strlen($str, '8bit')
		: strlen($str);
	}

	// --------------------------------------------------------------------

	/**
	 * Byte-safe substr()
	 *
	 * @param	string	$str
	 * @param	int	$start
	 * @param	int	$length
	 * @return	string
	 */
	protected static function substr($str, $start, $length = NULL) {
		if (self::$func_overload) {
			// mb_substr($str, $start, null, '8bit') returns an empty
			// string on PHP 5.3
			isset($length) OR $length = ($start >= 0 ? self::strlen($str) - $start : -$start);
			return mb_substr($str, $start, $length, '8bit');
		}

		return isset($length)
		? substr($str, $start, $length)
		: substr($str, $start);
	}
}
