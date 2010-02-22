<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\action;

use \Exception;
use \lithium\util\String;
use \lithium\util\Inflector;
use \lithium\core\Libraries;

/**
 * `Dispatcher` is the outermost layer of the framework, responsible for both receiving the initial
 * HTTP request and sending back a response at the end of the request's life cycle.
 *
 * After either receiving or instantiating a `Request` object instance, the `Dispatcher` passes that
 * instance to the `Router`, which produces the parameters necessary to dispatch the request
 * (unless no route matches, in which case an exception is thrown).
 *
 * Using these parameters, the `Dispatcher` loads and instantiates the correct `Controller` object,
 * and passes it the `Request` object instance. The `Controller` returns a `Response` object to the
 * `Dispatcher`, where the headers and content are rendered and sent to the browser.
 *
 * @see lithium\net\http\Router
 * @see lithium\action\Request
 * @see lithium\action\Response
 * @see lithium\action\Controller
 */
class Dispatcher extends \lithium\core\StaticObject {

	/**
	 * Fully-namespaced router class reference.  Class must implement a `parse()` method,
	 * which must return an array with (at a minimum) 'controller' and 'action' keys.
	 *
	 * @see lithium\net\http\Router::parse()
	 * @var array
	 */
	protected static $_classes = array(
		'request' => '\lithium\action\Request',
		'router' => '\lithium\net\http\Router'
	);

	/**
	 * Contains pre-process format strings for changing Dispatcher's behavior based on 'rules'.
	 *
	 * Each key in the array represents a 'rule'; if a key that matches the rule is present (and
	 * not empty) in a route, (i.e. the result of `lithium\net\http\Router::parse()`) then the rule's
	 * value will be applied to the route before it is dispatched.  When applying a rule, any array
	 * elements array elements of the flag which are present in the route will be modified using a
	 * `lithium\util\String::insert()`-formatted string.
	 *
	 * For example, to implement action prefixes (i.e. `admin_index()`), set a rule named 'admin',
	 * with a value array containing a modifier key for the `action` element of a route, i.e.:
	 * `array('action' => 'admin_{:action}')`. Now, if the `'admin'` key is present and not empty
	 * in the parameters returned from routing, the value of `'action'` will be rewritten per the
	 * settings in the rule.
	 *
	 * @see lithium\action\Dispatcher::config()
	 * @see lithium\util\String::insert()
	 */
	protected static $_rules = array();

	/**
	 * Used to set configuration parameters for the `Dispatcher`.
	 *
	 * @see lithium\action\Dispatcher::$_rules
	 * @param array $config Possible key settings are `'classes'` which sets the class dependencies
	 *              for `Dispatcher` (i.e. `'request'` or `'router'`) and `'rules'`, which sets the
	 *              pre-processing rules for routing parameters. For more information on the
	 *              `'rules'` setting, see the `$_rules` property.
	 * @return array If no parameters are passed, returns an associative array with the current
	 *         configuration, otherwise returns `null`.
	 */
	public static function config($config = array()) {
		if (empty($config)) {
			return array('rules' => static::$_rules);
		}

		foreach ($config as $key => $val) {
			if (isset(static::${'_' . $key})) {
				static::${'_' . $key} = $val + static::${'_' . $key};
			}
		}
	}

	/**
	 * Dispatches a request based on a request object (an instance of `lithium\net\http\Request`). If
	 * `$request` is null, a new request object is instantiated based on the value of the
	 * `'request'` key in the `$_classes` array.
	 *
	 * @param object $request An instance of a request object with HTTP request information.  If
	 *        `null`, an instance will be created.
	 * @param array $options
	 * @return mixed Returns the value returned from the callable object retrieved from
	 *         `Dispatcher::_callable()`, which is either a string or an instance of
	 *         `lithium\action\Response`.
	 * @todo Add exception-handling/error page rendering
	 */
	public static function run($request = null, $options = array()) {
		$defaults = array('request' => array());
		$options += $defaults;
		$classes = static::$_classes;
		$params = compact('request', 'options');
		$method = __FUNCTION__;

		return static::_filter($method, $params, function($self, $params, $chain) use ($classes) {
			extract($params);

			$router = $classes['router'];
			$request = $request ?: new $classes['request']($options['request']);
			$request->params = $router::parse($request);
			$params = $self::invokeMethod('_applyRules', array($request->params));

			if (!$params) {
				throw new Exception('Could not route request');
			}

			$callable = $self::invokeMethod('_callable', array($request, $params, $options));
			return $self::invokeMethod('_call', array($callable, $request, $params));
		});
	}

	/**
	 * Accepts parameters generated by the `Router` class in `Dispatcher::run()`, and produces a
	 * callable controller object. By default, this method uses the `'controller'` path lookup
	 * configuration in `Libraries::locate()` to return a callable object.
	 *
	 * @param object $request The instance of the `Request` class either passed into or generated by
	 *               `Dispatcher::run()`.
	 * @param array $params The parameter array generated by routing the request.
	 * @param array $options Not currently implemented.
	 * @return object Returns a callable object which the request will be routed to.
	 */
	protected static function _callable($request, $params, $options) {
		$params = compact('request', 'params', 'options');
		return static::_filter(__FUNCTION__, $params, function($self, $params, $chain) {
			extract($params, EXTR_OVERWRITE);
			$library = '';

			if (strpos($params['controller'], '.')) {
				list($library, $params['controller']) = explode('.', $params['controller']);
				$library .= '.';
			}
			$controller = $library . Inflector::camelize($params['controller']);
			$class = Libraries::locate('controllers', $controller);

			if (class_exists($class)) {
				return new $class(compact('request'));
			}
			throw new Exception("Controller {$controller} not found");
		});
	}

	protected static function _call($callable, $request, $params) {
		$params = compact('callable', 'request', 'params');
		return static::_filter(__FUNCTION__, $params, function($self, $params, $chain) {
			if (is_callable($callable = $params['callable'])) {
				return $callable($params['request'], $params['params']);
			}
			throw new Exception('Result not callable');
		});
	}

	/**
	 * Attempts to apply a set of formatting rules from `$_rules` to a `$params` array, where each
	 * formatting rule is applied if the key of the rule in `$_rules` is present and not empty in
	 * `$params`.  Also performs sanity checking against `$params` to ensure that no value
	 * matching a rule is present unless the rule check passes.
	 *
	 * @param array $params An array of route parameters to which rules will be applied.
	 * @return array Returns the `$params` array with formatting rules applied to array values.
	 */
	protected static function _applyRules($params) {
		$result = array();

		if (!$params) {
			return false;
		}

		foreach (static::$_rules as $rule => $value) {
			foreach ($value as $k => $v) {
				if (!empty($params[$rule])) {
					$result[$k] = String::insert($v, $params);
				}

				$match = preg_replace('/\{:\w+\}/', '@', $v);
				$match = preg_replace('/@/', '.+', preg_quote($match, '/'));

				if (preg_match('/' . $match . '/i', $params[$k])) {
					return false;
				}
			}
		}
		return $result + array_diff_key($params, $result);
	}
}

?>