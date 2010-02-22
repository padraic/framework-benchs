<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\storage\cache\adapter;

/**
 * An XCache opcode cache adapter implementation.
 *
 * The XCache adapter is meant to be used through the `Cache` interface,
 * which abstracts away key generation, adapter instantiation and filter
 * implementation.
 *
 * A simple configuration of this adapter can be accomplished in `app/config/bootstrap.php`
 * as follows:
 *
 * {{{
 * Cache::config(array(
 *     'cache-config-name' => array(
 *         'adapter' => 'XCache',
 *         'username' => 'user',
 *         'password' => 'pass'
 *     )
 * ));
 * }}}
 *
 * Note that the `username` and `password` configuration fields are only required if
 * you wish to use XCache::clear() - all other methods do not require XCache administrator
 * credentials.
 *
 * This XCache adapter provides basic support for `write`, `read`, `delete`
 * and `clear` cache functionality, as well as allowing the first four
 * methods to be filtered as per the Lithium filtering system.
 *
 * This adapter defines several methods that are _not_ implemented in other
 * adapters, and are thus non-portable - see the documentation for `Cache`
 * as to how these methods should be accessed.
 *
 * This adapter stores two keys for each written value - one which consists
 * of the data to be cached, and the other being a cache of the expiration time.
 * This is to unify the behavior of the XCache adapter to be in line with the other
 * adapters, since XCache cache expirations are only evaluated on requests subsequent
 * to their initial storage.
 *
 * @see lithium\storage\Cache::key()
 */
class XCache extends \lithium\core\Object {

	/**
	 * Class constructor
	 *
	 * @param array $config
	 * @return void
	 */
	public function __construct($config = array()) {
		$defaults = array('prefix' => '');
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
		return function($self, $params, $chain) {
			extract($params);
			$cachetime = strtotime($expiry);
			$duration = $cachetime - time();

			xcache_set($key . '_expires', $cachetime, $duration);
			return xcache_set($key, $data, $cachetime);

		};
	}

	/**
	 * Read value(s) from the cache
	 *
	 * @param string $key        The key to uniquely identify the cached item
	 * @return mixed Cached value if successful, false otherwise
	 */
	public function read($key) {
		return function($self, $params, $chain) {
			extract($params);
			$cachetime = intval(xcache_get($key . '_expires'));
			$time = time();
			return ($cachetime < $time) ? false : xcache_get($key);
		};
	}

	/**
	 * Delete value from the cache
	 *
	 * @param string $key        The key to uniquely identify the cached item
	 * @return mixed True on successful delete, false otherwise
	 */
	public function delete($key) {
		return function($self, $params, $chain) {
			extract($params);
			xcache_unset($key . '_expires');
			return xcache_unset($key);
		};
	}

	/**
	 * Performs an atomic decrement operation on specified numeric cache item.
	 *
	 * Note that, as per the XCache specification:
	 * If the item's value is not numeric, it is treated as if the value were 0.
	 * It is possible to decrement a value into the negative integers.
	 *
	 * @param string $key Key of numeric cache item to decrement
	 * @param integer $offset Offset to decrement - defaults to 1.
	 * @return mixed Item's new value on successful decrement, false otherwise
	 */
	public function decrement($key, $offset = 1) {
		return function($self, $params, $chain) use ($offset) {
			extract($params);
			return xcache_dec($key, $offset);
		};
	}

	/**
	 * Performs an atomic increment operation on specified numeric cache item.
	 *
	 * Note that, as per the XCache specification:
	 * If the item's value is not numeric, it is treated as if the value were 0.
	 *
	 * @param string $key Key of numeric cache item to increment
	 * @param integer $offset Offset to increment - defaults to 1.
	 * @return mixed Item's new value on successful increment, false otherwise
	 */
	public function increment($key, $offset = 1) {
		return function($self, $params, $chain) use ($offset) {
			extract($params);
			return xcache_inc($key, $offset);
		};
	}


	/**
	 * Clears user-space cache.
	 *
	 * This method requires valid xcache admin credentials to be set when the
	 * adapter was configured, due to the use of the xcache_clear_cache admin method.
	 *
	 * If the xcache.admin.enable_auth ini setting is set to "Off", no credentials
	 * required.
	 *
	 * @return mixed True on successful clear, false otherwise.
	 */
	public function clear() {
		$admin = (ini_get('xcache.admin.enable_auth') === "On");
		if ($admin && (!isset($this->_config['username']) || !isset($this->_config['password']))) {
			return false;
		}
		$credentials = array();

		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$credentials['username'] = $_SERVER['PHP_AUTH_USER'];
			$_SERVER['PHP_AUTH_USER'] = $this->_config['username'];
		}
		if (isset($_SERVER['PHP_AUTH_PW'])) {
			$credentials['password'] = $_SERVER['PHP_AUTH_PW'];
			$_SERVER['PHP_AUTH_PW'] = $this->_config['pass'];
		}

		for ($i = 0, $max = xcache_count(XC_TYPE_VAR); $i < $max; $i++) {
			if (xcache_clear_cache(XC_TYPE_VAR, $i) === false) {
				return false;
			}
		}

		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$_SERVER['PHP_AUTH_USER'] =
				($credentials['username'] !== null) ? $credentials['username'] : null;
		}
		if (isset($_SERVER['PHP_AUTH_PW'])) {
			$_SERVER['PHP_AUTH_PW'] =
				($credentials['password'] !== null) ? $credentials['password'] : null;
		}
		return true;
	}

	/**
	 * Determines if the APC extension has been installed and
	 * if the userspace cache is available.
	 *
	 * return boolean True if enabled, false otherwise
	 */
	public function enabled() {
		return (extension_loaded('xcache') && function_exists('xcache_info'));
	}
}

?>