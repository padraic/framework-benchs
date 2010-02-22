<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\util;

/**
 * The parent class for all collection objects. Contains methods for collection iteration, 
 * conversion, and filtering. Implements `ArrayAccess`, `Iterator`, and `Countable`.
 *
 * Collection objects can act very much like arrays. This is especially evident in creating new
 * objects, or by converting Collection into an actual array:
 *
 * {{{
 * $coll = new Collection();
 * $coll[] = 'foo';
 * // $coll[0] --> 'foo'
 *
 * $coll = new Collection(array('items' => array('foo')));
 * // $coll[0] --> 'foo'
 * 
 * $array = $coll->to('array);
 * }}}
 *
 * Apart from array-like data access, Collections allow for filtering and iteration methods:
 * 
 * {{{
 *
 * $coll = new Collection(array('items' => array(0, 1, 2, 3, 4)));
 *
 * $coll->first();   // 1 (the first non-empty value)
 * $coll->current(); // 0
 * $coll->next();    // 1
 * $coll->next();    // 2
 * $coll->next();    // 3
 * $coll->prev();    // 2
 * $coll->rewind();  // 0
 * }}}
 *
 * @link http://us.php.net/manual/en/class.arrayaccess.php
 * @link http://us.php.net/manual/en/class.iterator.php
 * @link http://us.php.net/manual/en/class.countable.php
 */
class Collection extends \lithium\core\Object implements \ArrayAccess, \Iterator, \Countable {

	/**
	 * A central registry of global format handlers for `Collection` objects and subclasses.
	 * Accessed via the `formats()` method.
	 *
	 * @see \lithium\util\Collection::formats()
	 * @var array
	 */
	protected static $_formats = array(
		'array' => '\lithium\util\Collection::_toArray'
	);

	/**
	 * The items contained in the collection.
	 *
	 * @var array
	 */
	protected $_items = array();

	/**
	 * Indicates whether the current position is valid or not.
	 *
	 * @var boolean
	 * @see lithium\util\Collection::valid()
	 */
	protected $_valid = false;

	/**
	 * Allows a collection's items to be automatically assigned from class construction options.
	 *
	 * @var array
	 */
	protected $_autoConfig = array('items');

	/**
	 * Accessor method for adding format handlers to instances and subclasses of `Collection`.
	 *
	 * @param string $format
	 * @param mixed $handler
	 * @return mixed
	 */
	public static function formats($format, $handler = null) {
		if ($format === false) {
			return static::$_formats = array();
		}
		if ((is_null($handler)) && class_exists($format)) {
			return static::$_formats[] = $format;
		}
		return static::$_formats[$format] = $handler;
	}

	/**
	 * Initializes the collection object by merging in collection items and removing redundant
	 * object properties.
	 *
	 * @return void
	 */
	protected function _init() {
		parent::_init();
		unset($this->_config['items']);
	}

	/**
	 * Handles dispatching of methods against all items in the collection.
	 *
	 * @param string $method
	 * @param array $parameters
	 * @param array $options Specifies options for how to run the given method against the object
	 *              collection. The available options are:
	 *              - `'collect'`: If `true`, the results of this method call will be returned
	 *                wrapped in a new Collection object or subclass.
	 *              - `'merge'`: Used primarily if the method being invoked returns an array.  If
	 *                set to `true`, merges all results arrays into one.
	 * @todo Implement filtering.
	 * @return mixed
	 */
	public function invoke($method, $parameters = array(), $options = array()) {
		$defaults = array('merge' => false, 'collect' => false);
		$options += $defaults;
		$results = array();
		$isCore = null;

		foreach ($this->_items as $key => $value) {
			if (is_null($isCore)) {
				$isCore = (method_exists(current($this->_items), 'invokeMethod'));
			}

			if ($isCore) {
				$result = $this->_items[$key]->invokeMethod($method, $parameters);
			} else {
				$result = call_user_func_array(array(&$this->_items[$key], $method), $parameters);
			}

			if (!empty($options['merge'])) {
				$results = array_merge($results, $result);
			} else {
				$results[$key] = $result;
			}
		}

		if ($options['collect']) {
			$class = get_class($this);
			$results = new $class(array('items' => $results));
		}
		return $results;
	}

	/**
	 * Hook to handle dispatching of methods against all items in the collection.
	 *
	 * @param string $method
	 * @param array $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters = array()) {
		return $this->invoke($method, $parameters);
	}

	/**
	 * Converts the Collection object to another type of object, or a simple type such as an array.
	 *
	 * @param string $format Currently only `'array'` is supported.
	 * @param $options Options for converting this collection:
	 *        - 'internal': Boolean indicating whether the current internal representation of the
	 *          collection should be exported. Defaults to `false`, which uses the standard iterator
	 *          interfaces. This is useful for exporting record sets, where records are lazy-loaded,
	 *          and the collection must be iterated in order to fetch all objects.
	 * @return mixed The converted object.
	 */
	public function to($format, $options = array()) {
		$defaults = array('internal' => false);
		$options += $defaults;
		$data = $options['internal'] ? $this->_items : $this;

		if (is_object($format) && is_callable($format)) {
			return $format($data, $options);
		}

		if (isset(static::$_formats[$format]) && is_callable(static::$_formats[$format])) {
			$handler = static::$_formats[$format];
			$handler = is_string($handler) ? explode('::', $handler, 2) : $handler;

			if (is_array($handler)) {
				list($class, $method) = $handler;
				return $class::$method($data, $options);
			}
			return $handler($data, $options);
		}

		foreach (static::$_formats as $key => $handler) {
			if (!is_int($key)) {
				continue;
			}
			if (in_array($format, $handler::formats($format, $data, $options))) {
				return $handler::to($format, $data, $options);
			}
		}
	}

	/**
	 * Filters a copy of the items in the collection.
	 *
	 * @param callback $filter Callback to use for filtering.
	 * @param array $options The available options are:
	 *              - `'collect'`: If `true`, the results will be returned wrapped
	 *              in a new Collection object or subclass.
	 * @return array|object The filtered items.
	 */
	public function find($filter, $options = array()) {
		$defaults = array('collect' => true);
		$options += $defaults;
		$items = array_filter($this->_items, $filter);

		if ($options['collect']) {
			$class = get_class($this);
			$items = new $class(compact('items'));
		}
		return $items;
	}

	/**
	 * Returns the first non-empty value in the collection after a filter is applied, or rewinds the
	 * collection and returns the first value.
	 *
	 * @param callback $filter A closure through which collection values will be
	 *                 passed. If the return value of this function is non-empty,
	 *                 it will be returned as the result of the method call. If `null`, the
	 *                 collection is rewound (see `rewind()`) and the first item is returned.
	 * @return mixed Returns the first non-empty collection value returned from `$filter`.
	 * @see lithium\util\Collection::rewind()
	 */
	public function first($filter = null) {
		if (empty($filter)) {
			return $this->rewind();
		}

		foreach ($this->_items as $item) {
			if ($value = $filter($item)) {
				return $value;
			}
		}
	}

	/**
	 * Applies a callback to all items in the collection.
	 *
	 * @param callback $filter The filter to apply.
	 * @return object This collection instance.
	 */
	public function each($filter) {
		$this->_items = array_map($filter, $this->_items);
		return $this;
	}

	/**
	 * Applies a callback to a copy of all items in the collection
	 * and returns the result.
	 *
	 * @param callback $filter The filter to apply.
	 * @param array $options The available options are:
	 *              - `'collect'`: If `true`, the results will be returned wrapped
	 *              in a new Collection object or subclass.
	 * @return array|object The filtered items.
	 */
	public function map($filter, $options = array()) {
		$defaults = array('collect' => true);
		$options += $defaults;
		$items = array_map($filter, $this->_items);

		if ($options['collect']) {
			$class = get_class($this);
			return new $class(compact('items'));
		}
		return $items;
	}

	/**
	 * Checks whether or not an offset exists.
	 *
	 * @param string $offset An offset to check for.
	 * @return boolean `true` if offset exists, `false` otherwise.
	 */
	public function offsetExists($offset) {
		return isset($this->_items[$offset]);
	}

	/**
	 * Returns the value at specified offset.
	 *
	 * @param string $offset The offset to retrieve.
	 * @return mixed Value at offset.
	 */
	public function offsetGet($offset) {
		return $this->_items[$offset];
	}

	/**
	 * Assigns a value to the specified offset.
	 *
	 * @param string $offset The offset to assign the value to.
	 * @param mixed $value The value to set.
	 * @return mixed The value which was set.
	 */
	public function offsetSet($offset, $value) {
		if (is_null($offset)) {
			return $this->_items[] = $value;
		}
		return $this->_items[$offset] = $value;
	}

	/**
	 * Unsets an offset.
	 *
	 * @param string $offset The offset to unset.
	 * @return void
	 */
	public function offsetUnset($offset) {
		unset($this->_items[$offset]);
	}

	/**
	 * Rewinds to the first item.
	 *
	 * @return mixed The current item after rewinding.
	 */
	public function rewind() {
		$this->_valid = (reset($this->_items) !== false);
		return current($this->_items);
	}

	/**
	 * Moves forward to the last item.
	 *
	 * @return mixed The current item after moving.
	 */
	public function end() {
		$this->_valid = (end($this->_items) !== false);
		return current($this->_items);
	}

	/**
	 * Checks if current position is valid.
	 *
	 * @return boolean `true` if valid, `false` otherwise.
	 */
	public function valid() {
		return $this->_valid;
	}

	/**
	 * Returns the current item.
	 *
	 * @return mixed The current item.
	 */
	public function current() {
		return current($this->_items);
	}

	/**
	 * Returns the key of the current item.
	 *
	 * @return scalar Scalar on success `0` on failure.
	 */
	public function key() {
		return key($this->_items);
	}

	/**
	 * Moves backward to the previous item.  If already at the first item,
	 * moves to the last one.
	 *
	 * @return mixed The current item after moving.
	 */
	public function prev() {
		if (!prev($this->_items)) {
			end($this->_items);
		}
		return current($this->_items);
	}

	/**
	 * Move forwards to the next item.
	 *
	 * @return The current item after moving.
	 */
	public function next() {
		$this->_valid = (next($this->_items) !== false);
		return current($this->_items);
	}

	/**
	 * Appends an item.
	 *
	 * @param mixed $value The item to append.
	 * @return void
	 */
	public function append($value) {
		is_object($value) ? $this->_items[] =& $value : $this->_items[] = $value;
	}

	/**
	 * Counts the items of the object.
	 *
	 * @return integer Number of items.
	 */
	public function count() {
		$count = iterator_count($this);
		$this->rewind();
		return $count;
	}

	/**
	 * Returns the item keys.
	 *
	 * @return array The keys of the items.
	 */
	public function keys() {
		return array_keys($this->_items);
	}

	/**
	 * Exports a `Collection` instance to an array. Used by `Collection::to()`.
	 *
	 * @param mixed $data Either a `Collection` instance, or an array representing a `Collection`'s
	 *              internal state.
	 * @return array Returns the value of `$data` as a pure PHP array, recursively converting all
	 *         sub-objects and other values to their closest array or scalar equivalents.
	 */
	protected static function _toArray($data) {
		$result = array();

		foreach ($data as $key => $item) {
			switch (true) {
				case (!is_object($item)):
					$result[$key] = $item;
				break;
				case (method_exists($item, 'to')):
					$result[$key] = $item->to('array');
				break;
				case ($vars = get_object_vars($item)):
					$result[$key] = $vars;
				break;
				case (method_exists($item, '__toString')):
					$result[$key] = (string) $item;
				break;
			}
		}
		return $result;
	}
}

?>