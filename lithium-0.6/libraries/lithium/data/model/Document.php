<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\data\model;

use \Iterator;

/**
 * `Document` is an alternative to the `model\RecordSet` class, which is optimized for organizing
 * collections of records from document-oriented databases such as CouchDB or MongoDB. A `Document`
 * object's fields can represent a collection of both simple and complex data types, as well as
 * other `Document` objects. Given the following data (document) structure:
 *
 * {{{
 * {
 * 	_id: 12345.
 * 	name: 'Acme, Inc.',
 * 	employees: {
 * 		'Larry': { email: 'larry@acme.com' },
 * 		'Curly': { email: 'curly@acme.com' },
 * 		'Moe': { email: 'moe@acme.com' }
 * 	}
 * }
 * }}}
 *
 * You can query the object as follows:
 *
 * {{{$acme = Company::find(12345);}}}
 *
 * This returns a `Document` object, populated with the raw representation of the data.
 *
 * {{{print_r($acme->to('array'));
 *
 * // Yields:
 * //	array(
 * //	'_id' => 12345,
 * //	'name' => 'Acme, Inc.',
 * //	'employees' => array(
 * //		'Larry' => array('email' => 'larry@acme.com'),
 * //		'Curly' => array('email' => 'curly@acme.com'),
 * //		'Moe' => array('email' => 'moe@acme.com')
 * //	)
 * //)}}}
 *
 * As with other database objects, a `Document` exposes its fields as object properties, like so:
 *
 * {{{echo $acme->name; // echoes 'Acme, Inc.'}}}
 *
 * However, accessing a field containing a data set will return that data set wrapped in a
 * sub-`Document` object., i.e.:
 *
 * {{{$employees = $acme->employees;
 * // returns a Document object with the data in 'employees'}}}
 */
class Document extends \lithium\util\Collection {

	/**
	 * The fully-namespaced class name of the model object to which this document is bound. This
	 * is usually the model that executed the query which created this object.
	 *
	 * @var string
	 */
	protected $_model = null;

	/**
	 * A reference to the object that originated this record set; usually an instance of
	 * `lithium\data\Source` or `lithium\data\source\Database`. Used to load column definitions and
	 * lazy-load records.
	 *
	 * @var object
	 */
	protected $_handle = null;

	/**
	 * A reference to the query object that originated this record set; usually an instance of
	 * `lithium\data\model\Query`.
	 *
	 * @var object
	 */
	protected $_query = null;

	/**
	 * A pointer or resource that is used to load records from the object (`$_handle`) that
	 * originated this record set.
	 *
	 * @var resource
	 */
	protected $_result = null;

	/**
	 * A reference to this object's parent `Document` object.
	 *
	 * @var object
	 */
	protected $_parent = null;

	/**
	 * Indicates whether this document has already been created in the database.
	 *
	 * @var boolean
	 */
	protected $_exists = false;

	protected $_errors = array();

	/**
	 * The class dependencies for `Document`.
	 *
	 * @var array
	 */
	protected $_classes = array(
		'media' => '\lithium\net\http\Media',
		'record' => '\lithium\data\model\Document',
		'recordSet' => '\lithium\data\model\Document'
	);

	protected $_hasInitialized = false;

	protected $_autoConfig = array(
		'items', 'classes' => 'merge', 'handle', 'model', 'result', 'query', 'parent', 'exists'
	);

	public function __construct($config = array()) {
		if (isset($config['data']) && !isset($config['items'])) {
			$config['items'] = $config['data'];
			unset($config['data']);
		}
		parent::__construct($config);
		$this->_items = (array) $this->_items;
	}

	/**
	 * PHP magic method used when accessing fields as document properties, i.e. `$document->_id`.
	 *
	 * @param $name The field name, as specified with an object property.
	 * @return mixed Returns the value of the field specified in `$name`, and wraps complex data
	 *         types in sub-`Document` objects.
	 */
	public function __get($name) {
		if (!isset($this->_items[$name])) {
			return null;
		}
		$items = $this->_items[$name];

		if ($this->_isComplexType($items) && !$items instanceof Iterator) {
			$this->_items[$name] = $this->_record('recordSet', $this->_items[$name]);
		}
		return $this->_items[$name];
	}

	/**
	 * PHP magic method used to check the presence of a field as document properties, i.e.
	 * `$document->_id`.
	 *
	 * @param $name The field name, as specified with an object property.
	 * @return boolean True if the field specified in `$name` exists, false otherwise.
	 */
	public function __isset($name) {
		return isset($this->_items[$name]);
	}

	/**
	 * Allows several properties to be assigned at once.
	 *
	 * For example:
	 * {{{
	 * $doc->set(array('title' => 'Lorem Ipsum', 'value' => 42));
	 * }}}
	 *
	 * @param $values An associative array of fields and values to assign to the `Document`.
	 * @return void
	 */
	public function set($values) {
		$this->__set($values);
	}

	/**
	 * PHP magic method used when setting properties on the `Document` instance, i.e.
	 * `$document->title = 'Lorem Ipsum'`. If `$value` is a complex data type (i.e. associative
	 * array), it is wrapped in a sub-`Document` object before being appended.
	 *
	 * @param $name The name of the field/property to write to, i.e. `title` in the above example.
	 * @param $value The value to write, i.e. `'Lorem Ipsum'`.
	 * @return void
	 */
	public function __set($name, $value = null) {
		if (is_array($name) && empty($value)) {
			$this->_items = $name + $this->_items;
			return;
		}
		if ($this->_isComplexType($value) && !$value instanceof Iterator) {
			$value = $this->_record('recordSet', $value);
		}
		$this->_items[$name] = $value;
	}

	/**
	 * PHP magic method used when unset() is called on a `Document` instance.
	 * Use case for this would be when you wish to edit a document and remove a field, ie. :
	 * {{{ $doc = Post::find($id); unset($doc->fieldName); $doc->save(); }}}
	 *
	 * @param unknown_type $name
	 * @return unknown_type
	 */
	public function __unset($name) {
		unset($this->_items[$name]);
	}

	/**
	 * Rewinds the collection of sub-`Document`s to the beginning and returns the first one found.
	 *
	 * @return object Returns the first `Document` object instance in the collection.
	 */
	public function rewind() {
		$this->_valid = (reset($this->_items) !== false);

		if (!$this->_valid && !$this->_hasInitialized) {
			$this->_hasInitialized = true;

			if ($record = $this->_populate()) {
				$this->_valid = true;
				return $record;
			}
		}
		return $this->__get(key($this->_items));
	}

	/**
	 * Magic php method used when model method is called on document instance.
	 * If no model is set returns null.
	 *
	 * @param $method
	 * @param $params
	 * @return mixed
	 */
	public function __call($method, $params = array()) {
		if (!$model = $this->_model) {
			return null;
		}
		array_unshift($params, $this);
		$class = $model::invokeMethod('_instance');
		return call_user_func_array(array(&$class, $method), $params);
	}

	/**
	 * Returns the next record in the set, and advances the object's internal pointer. If the end
	 * of the set is reached, a new record will be fetched from the data source connection handle
	 * (`$_handle`). If no more records can be fetched, returns `null`.
	 *
	 * @return object|null Returns the next record in the set, or `null`, if no more records are
	 *         available.
	 */
	public function next() {
		$prev = key($this->_items);
		$this->_valid = (next($this->_items) !== false);
		$cur = key($this->_items);

		if (!$this->_valid && $cur !== $prev && $cur !== null) {
			$this->_valid = true;
		}
		$this->_valid = $this->_valid ?: !is_null($this->_populate());
		return $this->_valid ? $this->__get(key($this->_items)) : null;
	}

	/**
	 * Returns `true` if the `Document` object already exists in the database, or `false` if this
	 * object is newly-instantiated (i.e. holds a record that has not yet been saved).
	 *
	 * @return boolean
	 */
	public function exists() {
		return $this->_exists;
	}

	/**
	* Access the errors of the record.
	*
	* @param array|string $field If an array, overwrites `$this->_errors`. If a string, and $value
	*        is not null, sets the corresponding key in $this->_errors to $value
	* @param string $value Value to set.
	* @return array|string Either the $this->_errors array, or single value from it.
	*/
	public function errors($field = null, $value = null) {
		if ($field === null) {
			return $this->_errors;
		}
		if (is_array($field)) {
			$this->_errors = $field;
			return $this->_errors;
		}
		if ($value === null && isset($this->_errors[$field])) {
			return $this->_errors[$field];
		}
		if ($value !== null) {
			$this->_errors[$field] = $value;
		}
		return $value;
	}

	/**
	 * Gets the raw data associated with this `Document`, or single item if '$field` is defined.
	 *
	 * @param string $field if included will only return the named item
	 * @return array Returns a raw array of `Document` data, or individual field value
	 */
	public function data($field = null) {
		if ($field) {
			return isset($this->_items[$field]) ? $this->_items[$field] : null;
		}
		return $this->to('array');
	}

	protected function _isComplexType($data) {
		if (is_object($data) && (array) $data === array()) {
			return false;
		}
		if (is_scalar($data) || !$data) {
			return false;
		}
		if (is_array($data)) {
			if (array_keys($data) === range(0, count($data) - 1)) {
				if (array_filter($data, 'is_scalar') == array_filter($data)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Called after a `Document` is saved. Updates the object's internal state to reflect the
	 * corresponding database record, and sets the `Document`'s primary key, if this is a
	 * newly-created object.
	 *
	 * @param $id The ID to assign, where applicable.
	 * @return void
	 */
	protected function _update($id = null) {
		if ($id) {
			$id = (array) $id;
			$model = $this->_model;
			foreach ((array) $model::meta('key') as $i => $key) {
				$this->__set($key, $id[$i]);
			}
		}
		$this->_exists = true;
	}

	/**
	 * Lazy-loads document records from a query using a reference to a database adapter and a query
	 * result resource.
	 *
	 * @param array $items
	 * @param mixed $key
	 * @return array
	 */
	protected function _populate($items = null, $key = null) {
		if ($this->_closed() || !$this->_handle) {
			return;
		}
		$items = $items ?: $this->_handle->result('next', $this->_result, $this);
		if (!isset($items)) {
			return $this->_close();
		}
		return $this->_items[] = $this->_record('record', $items);
	}

	/**
	 * Instantiates a new `Document` record object as a descendant of the current object, and sets
	 * all default values and internal state.
	 *
	 * @param string $classType The type of class to create, either `'record'` or `'recordSet'`.
	 * @param array $items
	 * @param array $options
	 * @return object Returns a new `Document` object instance.
	 */
	protected function _record($classType, $items, $options = array()) {
		$parent = $this;
		$model = $this->_model;
		$exists = $this->_exists;
		$options += compact('model', 'items', 'parent', 'exists');
		return new $this->_classes[$classType]($options);
	}

	/**
	 * Executes when the associated result resource pointer reaches the end of its record set. The
	 * resource is freed by the connection, and the reference to the connection is unlinked.
	 *
	 * @return void
	 */
	protected function _close() {
		if (!$this->_closed()) {
			$this->_result = $this->_handle->result('close', $this->_result, $this);
			$this->_handle = null;
		}
	}

	/**
	 * Checks to see if this record set has already fetched all available records and freed the
	 * associated result resource.
	 *
	 * @return boolean Returns true if all records are loaded and the database resources have been
	 *         freed, otherwise returns false.
	 */
	protected function _closed() {
		return (empty($this->_result) || empty($this->_handle));
	}
}

?>