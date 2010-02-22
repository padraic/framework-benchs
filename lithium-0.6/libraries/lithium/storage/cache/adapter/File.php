<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\storage\cache\adapter;

use \SplFileInfo;
use \DirectoryIterator;

/**
 * A minimal file-based cache.
 *
 * This File adapter provides basic support for `write`, `read`, `delete`
 * and `clear` cache functionality, as well as allowing the first four
 * methods to be filtered as per the Lithium filtering system.
 *
 * The path that the cached files will be written to defaults to
 * `LITHIUM_APP_PATH/resources/tmp/cache`, but is user-configurable on cache configuration.
 *
 * Note that the cache expiration time is stored within the first few bytes
 * of the cached data, and is transparently added and/or removed when values
 * are stored and/or retrieved from the cache.
 *
 */
class File extends \lithium\core\Object {

	/**
	 * Class constructor
	 *
	 * @param array $config
	 * @return void
	 */
	public function __construct($config = array()) {
		$defaults = array('path' => LITHIUM_APP_PATH . '/resources/tmp/cache');
		parent::__construct($config + $defaults);
	}

	/**
	 * Write value(s) to the cache
	 *
	 * @param string $key The key to uniquely identify the cached item
	 * @param mixed $data The value to be cached
	 * @param string $expiry A strtotime() compatible cache time
	 * @return boolean True on successful write, false otherwise
	 */
	public function write($key, $data, $expiry) {
		$path = $this->_config['path'];

		return function($self, $params, $chain) use (&$path) {
			extract($params);
			$expiry = strtotime($expiry);
			$data = "{:expiry:{$expiry}}\n{$data}";
			$path = "$path/$key";

			return file_put_contents($path, $data);
		};

	}

	/**
	 * Read value(s) from the cache
	 *
	 * @param string $key The key to uniquely identify the cached item
	 * @return mixed Cached value if successful, false otherwise
	 */
	public function read($key) {
		$path = $this->_config['path'];

		return function($self, $params, $chain) use (&$path) {
			extract($params);
			$path = "$path/$key";
			$file = new SplFileInfo($path);

			if (!$file->isFile() || !$file->isReadable())  {
				return false;
			}

			$data = file_get_contents($path);
			preg_match('/^\{\:expiry\:(\d+)\}\\n/', $data, $matches);
			$expiry = $matches[1];

			if ($expiry < time()) {
				unlink($path);
				return false;
			}
			return preg_replace('/^\{\:expiry\:\d+\}\\n/', '', $data, 1);

		};

	}

	/**
	 * Delete value from the cache
	 *
	 * @param string $key The key to uniquely identify the cached item
	 * @return mixed True on successful delete, false otherwise
	 */
	public function delete($key) {
		$path = $this->_config['path'];

		return function($self, $params, $chain) use (&$path) {
			extract($params);
			$path = "$path/$key";
			$file = new SplFileInfo($path);

			if ($file->isFile() && $file->isReadable())  {
				return unlink($path);
			}

			return false;
		};
	}

	/**
	 * The File adapter does not provide any facilities for atomic incrementing
	 * of cache items. If you need this functionality, please use a cache adapter
	 * which provides native support for atomic increment.
	 *
	 * This method is not implemented, and will simply return false.
	 *
	 * @param string $key Key of numeric cache item to increment
	 * @param integer $offset Offset to increment - defaults to 1.
	 * @return boolean False - this method is not implemented
	 */
	public function increment($key, $offset = 1) {
		return false;
	}

	/**
	 * The File adapter does not provide any facilities for atomic decrementing
	 * of cache items. If you need this functionality, please use a cache adapter
	 * which provides native support for atomic decrement.
	 *
	 * This method is not implemented, and will simply return false.
	 *
	 * @param string $key Key of numeric cache item to decrement
	 * @param integer $offset Offset to increment - defaults to 1.
	 * @return boolean False - this method is not implemented
	 */
	public function decrement($key, $offset = 1) {
		return false;
	}

	/**
	 * Clears user-space cache
	 *
	 * @return mixed True on successful clear, false otherwise
	 */
	public function clear() {
		$directory = new DirectoryIterator($this->_config['path']);

		foreach ($directory as $file) {
			if ($file->isFile()) {
				unlink($file->getPathInfo());
			}
		}
		return true;

	}

	/**
	 * Determines if the File adapter can read and write
	 * to the configured path.
	 *
	 * return boolean True if enabled, false otherwise
	 */
	public function enabled() {
		$directory = new SplFileInfo($this->_config['path']);

		return ($directory->isDir() && $directory->isReadable() && $directory->isWritable());
	}
}

?>