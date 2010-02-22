<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\data;

/**
 * This is the base class for Lithium's data abstraction layer.
 *
 * In addition to utility methods and standardized properties, it defines the implementation tasks
 * for all Lithium classes that work with external data, such as connections to remote resources
 * (`connect()` and `disconnect()`), introspecting available data objects (`entities()` and
 * `describe()`), and a standard read/write interface (`create()`, `read()`, `update()` and
 * `delete()`).
 *
 * Subclasses may implement any other non-standard functionality, but the above methods define the
 * requirements for interacting with `Model` objects, and other classes within `lithium\data`.
 */
abstract class Source extends \lithium\core\Object {

	/**
	 * Stores a connection to a remote resource. Usually a database connection (`resource` type),
	 * or an HTTP connection object ('object' type).
	 *
	 * @var mixed
	 */
	protected $_connection = null;

	/**
	 * Stores the status of this object's connection. Updated when `connect()` or `disconnect()` are
	 * called, or if an error occurs that closes the object's connection.
	 *
	 * @var boolean
	 */
	protected $_isConnected = false;

	/**
	 * Constructor. Sets defaults and returns object.
	 *
	 * Options defined:
	 * - 'autoConnect' `boolean` If true, a connection is made on initialisation. Defaults to true.
	 *
	 * @param array $config
	 * @return Source object
	 */
	public function __construct($config = array()) {
		$defaults = array('autoConnect' => true);
		parent::__construct((array) $config + $defaults);
	}

	/**
	 * Ensures the connection is closed, before the object is destroyed.
	 *
	 * @return void
	 */
	public function __destruct() {
		if ($this->isConnected()) {
			$this->disconnect();
		}
	}

	protected function _init() {
		if ($this->_config['autoConnect']) {
			$this->connect();
		}
	}

	/**
	 * Checks the connection status of this data source. If the `'autoConnect'` option is set to
	 * true and the source connection is not currently active, a connection attempt will be made
	 * before returning the result of the connection status.
	 *
	 * @param array $options The options available for this method:
	 *        - 'autoConnect': If true, and the connection is not currently active, calls
	 *        `connect()` on this object. Defaults to `false`.
	 * @return boolean Returns the current value of `$_isConnected`, indicating whether or not
	 *         the object's connection is currently active.  This value may not always be accurate,
	 *         as the connection could have timed out or otherwise been dropped by the remote
	 *         resource during the course of the request.
	 */
	public function isConnected($options = array()) {
		$defaults = array('autoConnect' => false);
		$options += $defaults;

		if (!$this->_isConnected && $options['autoConnect']) {
			$this->connect();
		}
		return $this->_isConnected;
	}

	/**
	 * Abstract. Must be defined by child classes.
	 */
	abstract public function connect();

	/**
	 * Abstract. Must be defined by child classes.
	 */
	abstract public function disconnect();

	/**
	 * Returns a list of objects (entities) that models can bind to, i.e. a list of tables in the
	 * case of a database, or REST collections, in the case of a web service.
	 *
	 * @param string $model The fully-name-spaced class name of the object making the request.
	 * @return array Returns an array of objects to which models can connect.
	 * @filter This method can be filtered.
	 */
	abstract public function entities($class = null);

	abstract public function describe($entity, $meta = array());

	/**
	 * Abstract. Must be defined by child classes.
	 *
	 * @param mixed $query
	 * @param array $options
	 * @return boolean Returns true if the operation was a success, otherwise false.
	 */
	abstract public function create($query, $options);

	/**
	 * Abstract. Must be defined by child classes.
	 *
	 * @param mixed $query
	 * @param array $options
	 * @return boolean Returns true if the operation was a success, otherwise false.
	 */
	abstract public function read($query, $options);

	/**
	 * Updates a set of records in a concrete data store.
	 *
	 * @param mixed $query An object which defines the update operation(s) that should be performed
	 *        against the data store.  This can be a `Query`, a `RecordSet`, a `Record`, or a
	 *        subclass of one of the three. Alternatively, `$query` can be an adapter-specific
	 *        query string.
	 * @param array $options Options to execute, which are defined by the concrete implementation.
	 * @return boolean Returns true if the update operation was a success, otherwise false.
	 */
	abstract public function update($query, $options);

	/**
	 * Abstract. Must be defined by child classes.
	 *
	 * @param mixed $query
	 * @param array $options
	 * @return boolean Returns true if the operation was a success, otherwise false.
	 */
	abstract public function delete($query, $options);

	/**
	 * A method which can be optionally implemented to configure a model class.
	 *
	 * @param string $class The name of the model class to be configured.
	 * @return array This method should return an array one or more of the following keys: `'meta'`,
	 *         `'classes'` or `'finders'`. These keys maps to the three corresponding properties in
	 *         `lithium\data\Model`, and are used to override the base-level default settings and
	 *         dependencies.
	 * @see lithium\data\Model::$_meta
	 * @see lithium\data\Model::$_finders
	 * @see lithium\data\Model::$_classes
	 */
	public function configureClass($class) {
		return array();
	}
}

?>