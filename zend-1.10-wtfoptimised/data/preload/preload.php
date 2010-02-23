<?php

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Front.php 20246 2010-01-12 21:36:08Z dasprid $
 */


/** Zend_Loader */
// require_once 'Zend/Loader.php';

/** Zend_Controller_Action_HelperBroker */
// require_once 'Zend/Controller/Action/HelperBroker.php';

/** Zend_Controller_Plugin_Broker */
// require_once 'Zend/Controller/Plugin/Broker.php';

/**
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Controller_Front
{
    /**
     * Base URL
     * @var string
     */
    protected $_baseUrl = null;

    /**
     * Directory|ies where controllers are stored
     *
     * @var string|array
     */
    protected $_controllerDir = null;

    /**
     * Instance of Zend_Controller_Dispatcher_Interface
     * @var Zend_Controller_Dispatcher_Interface
     */
    protected $_dispatcher = null;

    /**
     * Singleton instance
     *
     * Marked only as protected to allow extension of the class. To extend,
     * simply override {@link getInstance()}.
     *
     * @var Zend_Controller_Front
     */
    protected static $_instance = null;

    /**
     * Array of invocation parameters to use when instantiating action
     * controllers
     * @var array
     */
    protected $_invokeParams = array();

    /**
     * Subdirectory within a module containing controllers; defaults to 'controllers'
     * @var string
     */
    protected $_moduleControllerDirectoryName = 'controllers';

    /**
     * Instance of Zend_Controller_Plugin_Broker
     * @var Zend_Controller_Plugin_Broker
     */
    protected $_plugins = null;

    /**
     * Instance of Zend_Controller_Request_Abstract
     * @var Zend_Controller_Request_Abstract
     */
    protected $_request = null;

    /**
     * Instance of Zend_Controller_Response_Abstract
     * @var Zend_Controller_Response_Abstract
     */
    protected $_response = null;

    /**
     * Whether or not to return the response prior to rendering output while in
     * {@link dispatch()}; default is to send headers and render output.
     * @var boolean
     */
    protected $_returnResponse = false;

    /**
     * Instance of Zend_Controller_Router_Interface
     * @var Zend_Controller_Router_Interface
     */
    protected $_router = null;

    /**
     * Whether or not exceptions encountered in {@link dispatch()} should be
     * thrown or trapped in the response object
     * @var boolean
     */
    protected $_throwExceptions = false;

    /**
     * Constructor
     *
     * Instantiate using {@link getInstance()}; front controller is a singleton
     * object.
     *
     * Instantiates the plugin broker.
     *
     * @return void
     */
    protected function __construct()
    {
        $this->_plugins = new Zend_Controller_Plugin_Broker();
    }

    /**
     * Enforce singleton; disallow cloning
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Singleton instance
     *
     * @return Zend_Controller_Front
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Resets all object properties of the singleton instance
     *
     * Primarily used for testing; could be used to chain front controllers.
     *
     * Also resets action helper broker, clearing all registered helpers.
     *
     * @return void
     */
    public function resetInstance()
    {
        $reflection = new ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            switch ($name) {
                case '_instance':
                    break;
                case '_controllerDir':
                case '_invokeParams':
                    $this->{$name} = array();
                    break;
                case '_plugins':
                    $this->{$name} = new Zend_Controller_Plugin_Broker();
                    break;
                case '_throwExceptions':
                case '_returnResponse':
                    $this->{$name} = false;
                    break;
                case '_moduleControllerDirectoryName':
                    $this->{$name} = 'controllers';
                    break;
                default:
                    $this->{$name} = null;
                    break;
            }
        }
        Zend_Controller_Action_HelperBroker::resetHelpers();
    }

    /**
     * Convenience feature, calls setControllerDirectory()->setRouter()->dispatch()
     *
     * In PHP 5.1.x, a call to a static method never populates $this -- so run()
     * may actually be called after setting up your front controller.
     *
     * @param string|array $controllerDirectory Path to Zend_Controller_Action
     * controller classes or array of such paths
     * @return void
     * @throws Zend_Controller_Exception if called from an object instance
     */
    public static function run($controllerDirectory)
    {
        self::getInstance()
            ->setControllerDirectory($controllerDirectory)
            ->dispatch();
    }

    /**
     * Add a controller directory to the controller directory stack
     *
     * If $args is presented and is a string, uses it for the array key mapping
     * to the directory specified.
     *
     * @param string $directory
     * @param string $module Optional argument; module with which to associate directory. If none provided, assumes 'default'
     * @return Zend_Controller_Front
     * @throws Zend_Controller_Exception if directory not found or readable
     */
    public function addControllerDirectory($directory, $module = null)
    {
        $this->getDispatcher()->addControllerDirectory($directory, $module);
        return $this;
    }

    /**
     * Set controller directory
     *
     * Stores controller directory(ies) in dispatcher. May be an array of
     * directories or a string containing a single directory.
     *
     * @param string|array $directory Path to Zend_Controller_Action controller
     * classes or array of such paths
     * @param  string $module Optional module name to use with string $directory
     * @return Zend_Controller_Front
     */
    public function setControllerDirectory($directory, $module = null)
    {
        $this->getDispatcher()->setControllerDirectory($directory, $module);
        return $this;
    }

    /**
     * Retrieve controller directory
     *
     * Retrieves:
     * - Array of all controller directories if no $name passed
     * - String path if $name passed and exists as a key in controller directory array
     * - null if $name passed but does not exist in controller directory keys
     *
     * @param  string $name Default null
     * @return array|string|null
     */
    public function getControllerDirectory($name = null)
    {
        return $this->getDispatcher()->getControllerDirectory($name);
    }

    /**
     * Remove a controller directory by module name
     *
     * @param  string $module
     * @return bool
     */
    public function removeControllerDirectory($module)
    {
        return $this->getDispatcher()->removeControllerDirectory($module);
    }

    /**
     * Specify a directory as containing modules
     *
     * Iterates through the directory, adding any subdirectories as modules;
     * the subdirectory within each module named after {@link $_moduleControllerDirectoryName}
     * will be used as the controller directory path.
     *
     * @param  string $path
     * @return Zend_Controller_Front
     */
    public function addModuleDirectory($path)
    {
        try{
            $dir = new DirectoryIterator($path);
        } catch(Exception $e) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception("Directory $path not readable", 0, $e);
        }
        foreach ($dir as $file) {
            if ($file->isDot() || !$file->isDir()) {
                continue;
            }

            $module    = $file->getFilename();

            // Don't use SCCS directories as modules
            if (preg_match('/^[^a-z]/i', $module) || ('CVS' == $module)) {
                continue;
            }

            $moduleDir = $file->getPathname() . DIRECTORY_SEPARATOR . $this->getModuleControllerDirectoryName();
            $this->addControllerDirectory($moduleDir, $module);
        }

        return $this;
    }

    /**
     * Return the path to a module directory (but not the controllers directory within)
     *
     * @param  string $module
     * @return string|null
     */
    public function getModuleDirectory($module = null)
    {
        if (null === $module) {
            $request = $this->getRequest();
            if (null !== $request) {
                $module = $this->getRequest()->getModuleName();
            }
            if (empty($module)) {
                $module = $this->getDispatcher()->getDefaultModule();
            }
        }

        $controllerDir = $this->getControllerDirectory($module);

        if ((null === $controllerDir) || !is_string($controllerDir)) {
            return null;
        }

        return dirname($controllerDir);
    }

    /**
     * Set the directory name within a module containing controllers
     *
     * @param  string $name
     * @return Zend_Controller_Front
     */
    public function setModuleControllerDirectoryName($name = 'controllers')
    {
        $this->_moduleControllerDirectoryName = (string) $name;

        return $this;
    }

    /**
     * Return the directory name within a module containing controllers
     *
     * @return string
     */
    public function getModuleControllerDirectoryName()
    {
        return $this->_moduleControllerDirectoryName;
    }

    /**
     * Set the default controller (unformatted string)
     *
     * @param string $controller
     * @return Zend_Controller_Front
     */
    public function setDefaultControllerName($controller)
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->setDefaultControllerName($controller);
        return $this;
    }

    /**
     * Retrieve the default controller (unformatted string)
     *
     * @return string
     */
    public function getDefaultControllerName()
    {
        return $this->getDispatcher()->getDefaultControllerName();
    }

    /**
     * Set the default action (unformatted string)
     *
     * @param string $action
     * @return Zend_Controller_Front
     */
    public function setDefaultAction($action)
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->setDefaultAction($action);
        return $this;
    }

    /**
     * Retrieve the default action (unformatted string)
     *
     * @return string
     */
    public function getDefaultAction()
    {
        return $this->getDispatcher()->getDefaultAction();
    }

    /**
     * Set the default module name
     *
     * @param string $module
     * @return Zend_Controller_Front
     */
    public function setDefaultModule($module)
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->setDefaultModule($module);
        return $this;
    }

    /**
     * Retrieve the default module
     *
     * @return string
     */
    public function getDefaultModule()
    {
        return $this->getDispatcher()->getDefaultModule();
    }

    /**
     * Set request class/object
     *
     * Set the request object.  The request holds the request environment.
     *
     * If a class name is provided, it will instantiate it
     *
     * @param string|Zend_Controller_Request_Abstract $request
     * @throws Zend_Controller_Exception if invalid request class
     * @return Zend_Controller_Front
     */
    public function setRequest($request)
    {
        if (is_string($request)) {
            if (!class_exists($request)) {
                // require_once 'Zend/Loader.php';
                Zend_Loader::loadClass($request);
            }
            $request = new $request();
        }
        if (!$request instanceof Zend_Controller_Request_Abstract) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Invalid request class');
        }

        $this->_request = $request;

        return $this;
    }

    /**
     * Return the request object.
     *
     * @return null|Zend_Controller_Request_Abstract
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Set router class/object
     *
     * Set the router object.  The router is responsible for mapping
     * the request to a controller and action.
     *
     * If a class name is provided, instantiates router with any parameters
     * registered via {@link setParam()} or {@link setParams()}.
     *
     * @param string|Zend_Controller_Router_Interface $router
     * @throws Zend_Controller_Exception if invalid router class
     * @return Zend_Controller_Front
     */
    public function setRouter($router)
    {
        if (is_string($router)) {
            if (!class_exists($router)) {
                // require_once 'Zend/Loader.php';
                Zend_Loader::loadClass($router);
            }
            $router = new $router();
        }

        if (!$router instanceof Zend_Controller_Router_Interface) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Invalid router class');
        }

        $router->setFrontController($this);
        $this->_router = $router;

        return $this;
    }

    /**
     * Return the router object.
     *
     * Instantiates a Zend_Controller_Router_Rewrite object if no router currently set.
     *
     * @return Zend_Controller_Router_Interface
     */
    public function getRouter()
    {
        if (null == $this->_router) {
            // require_once 'Zend/Controller/Router/Rewrite.php';
            $this->setRouter(new Zend_Controller_Router_Rewrite());
        }

        return $this->_router;
    }

    /**
     * Set the base URL used for requests
     *
     * Use to set the base URL segment of the REQUEST_URI to use when
     * determining PATH_INFO, etc. Examples:
     * - /admin
     * - /myapp
     * - /subdir/index.php
     *
     * Note that the URL should not include the full URI. Do not use:
     * - http://example.com/admin
     * - http://example.com/myapp
     * - http://example.com/subdir/index.php
     *
     * If a null value is passed, this can be used as well for autodiscovery (default).
     *
     * @param string $base
     * @return Zend_Controller_Front
     * @throws Zend_Controller_Exception for non-string $base
     */
    public function setBaseUrl($base = null)
    {
        if (!is_string($base) && (null !== $base)) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Rewrite base must be a string');
        }

        $this->_baseUrl = $base;

        if ((null !== ($request = $this->getRequest())) && (method_exists($request, 'setBaseUrl'))) {
            $request->setBaseUrl($base);
        }

        return $this;
    }

    /**
     * Retrieve the currently set base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        $request = $this->getRequest();
        if ((null !== $request) && method_exists($request, 'getBaseUrl')) {
            return $request->getBaseUrl();
        }

        return $this->_baseUrl;
    }

    /**
     * Set the dispatcher object.  The dispatcher is responsible for
     * taking a Zend_Controller_Dispatcher_Token object, instantiating the controller, and
     * call the action method of the controller.
     *
     * @param Zend_Controller_Dispatcher_Interface $dispatcher
     * @return Zend_Controller_Front
     */
    public function setDispatcher(Zend_Controller_Dispatcher_Interface $dispatcher)
    {
        $this->_dispatcher = $dispatcher;
        return $this;
    }

    /**
     * Return the dispatcher object.
     *
     * @return Zend_Controller_Dispatcher_Interface
     */
    public function getDispatcher()
    {
        /**
         * Instantiate the default dispatcher if one was not set.
         */
        if (!$this->_dispatcher instanceof Zend_Controller_Dispatcher_Interface) {
            // require_once 'Zend/Controller/Dispatcher/Standard.php';
            $this->_dispatcher = new Zend_Controller_Dispatcher_Standard();
        }
        return $this->_dispatcher;
    }

    /**
     * Set response class/object
     *
     * Set the response object.  The response is a container for action
     * responses and headers. Usage is optional.
     *
     * If a class name is provided, instantiates a response object.
     *
     * @param string|Zend_Controller_Response_Abstract $response
     * @throws Zend_Controller_Exception if invalid response class
     * @return Zend_Controller_Front
     */
    public function setResponse($response)
    {
        if (is_string($response)) {
            if (!class_exists($response)) {
                // require_once 'Zend/Loader.php';
                Zend_Loader::loadClass($response);
            }
            $response = new $response();
        }
        if (!$response instanceof Zend_Controller_Response_Abstract) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Invalid response class');
        }

        $this->_response = $response;

        return $this;
    }

    /**
     * Return the response object.
     *
     * @return null|Zend_Controller_Response_Abstract
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Add or modify a parameter to use when instantiating an action controller
     *
     * @param string $name
     * @param mixed $value
     * @return Zend_Controller_Front
     */
    public function setParam($name, $value)
    {
        $name = (string) $name;
        $this->_invokeParams[$name] = $value;
        return $this;
    }

    /**
     * Set parameters to pass to action controller constructors
     *
     * @param array $params
     * @return Zend_Controller_Front
     */
    public function setParams(array $params)
    {
        $this->_invokeParams = array_merge($this->_invokeParams, $params);
        return $this;
    }

    /**
     * Retrieve a single parameter from the controller parameter stack
     *
     * @param string $name
     * @return mixed
     */
    public function getParam($name)
    {
        if(isset($this->_invokeParams[$name])) {
            return $this->_invokeParams[$name];
        }

        return null;
    }

    /**
     * Retrieve action controller instantiation parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_invokeParams;
    }

    /**
     * Clear the controller parameter stack
     *
     * By default, clears all parameters. If a parameter name is given, clears
     * only that parameter; if an array of parameter names is provided, clears
     * each.
     *
     * @param null|string|array single key or array of keys for params to clear
     * @return Zend_Controller_Front
     */
    public function clearParams($name = null)
    {
        if (null === $name) {
            $this->_invokeParams = array();
        } elseif (is_string($name) && isset($this->_invokeParams[$name])) {
            unset($this->_invokeParams[$name]);
        } elseif (is_array($name)) {
            foreach ($name as $key) {
                if (is_string($key) && isset($this->_invokeParams[$key])) {
                    unset($this->_invokeParams[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * Register a plugin.
     *
     * @param  Zend_Controller_Plugin_Abstract $plugin
     * @param  int $stackIndex Optional; stack index for plugin
     * @return Zend_Controller_Front
     */
    public function registerPlugin(Zend_Controller_Plugin_Abstract $plugin, $stackIndex = null)
    {
        $this->_plugins->registerPlugin($plugin, $stackIndex);
        return $this;
    }

    /**
     * Unregister a plugin.
     *
     * @param  string|Zend_Controller_Plugin_Abstract $plugin Plugin class or object to unregister
     * @return Zend_Controller_Front
     */
    public function unregisterPlugin($plugin)
    {
        $this->_plugins->unregisterPlugin($plugin);
        return $this;
    }

    /**
     * Is a particular plugin registered?
     *
     * @param  string $class
     * @return bool
     */
    public function hasPlugin($class)
    {
        return $this->_plugins->hasPlugin($class);
    }

    /**
     * Retrieve a plugin or plugins by class
     *
     * @param  string $class
     * @return false|Zend_Controller_Plugin_Abstract|array
     */
    public function getPlugin($class)
    {
        return $this->_plugins->getPlugin($class);
    }

    /**
     * Retrieve all plugins
     *
     * @return array
     */
    public function getPlugins()
    {
        return $this->_plugins->getPlugins();
    }

    /**
     * Set the throwExceptions flag and retrieve current status
     *
     * Set whether exceptions encounted in the dispatch loop should be thrown
     * or caught and trapped in the response object.
     *
     * Default behaviour is to trap them in the response object; call this
     * method to have them thrown.
     *
     * Passing no value will return the current value of the flag; passing a
     * boolean true or false value will set the flag and return the current
     * object instance.
     *
     * @param boolean $flag Defaults to null (return flag state)
     * @return boolean|Zend_Controller_Front Used as a setter, returns object; as a getter, returns boolean
     */
    public function throwExceptions($flag = null)
    {
        if ($flag !== null) {
            $this->_throwExceptions = (bool) $flag;
            return $this;
        }

        return $this->_throwExceptions;
    }

    /**
     * Set whether {@link dispatch()} should return the response without first
     * rendering output. By default, output is rendered and dispatch() returns
     * nothing.
     *
     * @param boolean $flag
     * @return boolean|Zend_Controller_Front Used as a setter, returns object; as a getter, returns boolean
     */
    public function returnResponse($flag = null)
    {
        if (true === $flag) {
            $this->_returnResponse = true;
            return $this;
        } elseif (false === $flag) {
            $this->_returnResponse = false;
            return $this;
        }

        return $this->_returnResponse;
    }

    /**
     * Dispatch an HTTP request to a controller/action.
     *
     * @param Zend_Controller_Request_Abstract|null $request
     * @param Zend_Controller_Response_Abstract|null $response
     * @return void|Zend_Controller_Response_Abstract Returns response object if returnResponse() is true
     */
    public function dispatch(Zend_Controller_Request_Abstract $request = null, Zend_Controller_Response_Abstract $response = null)
    {
        if (!$this->getParam('noErrorHandler') && !$this->_plugins->hasPlugin('Zend_Controller_Plugin_ErrorHandler')) {
            // Register with stack index of 100
            // require_once 'Zend/Controller/Plugin/ErrorHandler.php';
            $this->_plugins->registerPlugin(new Zend_Controller_Plugin_ErrorHandler(), 100);
        }

        if (!$this->getParam('noViewRenderer') && !Zend_Controller_Action_HelperBroker::hasHelper('viewRenderer')) {
            // require_once 'Zend/Controller/Action/Helper/ViewRenderer.php';
            Zend_Controller_Action_HelperBroker::getStack()->offsetSet(-80, new Zend_Controller_Action_Helper_ViewRenderer());
        }

        /**
         * Instantiate default request object (HTTP version) if none provided
         */
        if (null !== $request) {
            $this->setRequest($request);
        } elseif ((null === $request) && (null === ($request = $this->getRequest()))) {
            // require_once 'Zend/Controller/Request/Http.php';
            $request = new Zend_Controller_Request_Http();
            $this->setRequest($request);
        }

        /**
         * Set base URL of request object, if available
         */
        if (is_callable(array($this->_request, 'setBaseUrl'))) {
            if (null !== $this->_baseUrl) {
                $this->_request->setBaseUrl($this->_baseUrl);
            }
        }

        /**
         * Instantiate default response object (HTTP version) if none provided
         */
        if (null !== $response) {
            $this->setResponse($response);
        } elseif ((null === $this->_response) && (null === ($this->_response = $this->getResponse()))) {
            // require_once 'Zend/Controller/Response/Http.php';
            $response = new Zend_Controller_Response_Http();
            $this->setResponse($response);
        }

        /**
         * Register request and response objects with plugin broker
         */
        $this->_plugins
             ->setRequest($this->_request)
             ->setResponse($this->_response);

        /**
         * Initialize router
         */
        $router = $this->getRouter();
        $router->setParams($this->getParams());

        /**
         * Initialize dispatcher
         */
        $dispatcher = $this->getDispatcher();
        $dispatcher->setParams($this->getParams())
                   ->setResponse($this->_response);

        // Begin dispatch
        try {
            /**
             * Route request to controller/action, if a router is provided
             */

            /**
            * Notify plugins of router startup
            */
            $this->_plugins->routeStartup($this->_request);

            try {
                $router->route($this->_request);
            }  catch (Exception $e) {
                if ($this->throwExceptions()) {
                    throw $e;
                }

                $this->_response->setException($e);
            }

            /**
            * Notify plugins of router completion
            */
            $this->_plugins->routeShutdown($this->_request);

            /**
             * Notify plugins of dispatch loop startup
             */
            $this->_plugins->dispatchLoopStartup($this->_request);

            /**
             *  Attempt to dispatch the controller/action. If the $this->_request
             *  indicates that it needs to be dispatched, move to the next
             *  action in the request.
             */
            do {
                $this->_request->setDispatched(true);

                /**
                 * Notify plugins of dispatch startup
                 */
                $this->_plugins->preDispatch($this->_request);

                /**
                 * Skip requested action if preDispatch() has reset it
                 */
                if (!$this->_request->isDispatched()) {
                    continue;
                }

                /**
                 * Dispatch request
                 */
                try {
                    $dispatcher->dispatch($this->_request, $this->_response);
                } catch (Exception $e) {
                    if ($this->throwExceptions()) {
                        throw $e;
                    }
                    $this->_response->setException($e);
                }

                /**
                 * Notify plugins of dispatch completion
                 */
                $this->_plugins->postDispatch($this->_request);
            } while (!$this->_request->isDispatched());
        } catch (Exception $e) {
            if ($this->throwExceptions()) {
                throw $e;
            }

            $this->_response->setException($e);
        }

        /**
         * Notify plugins of dispatch loop completion
         */
        try {
            $this->_plugins->dispatchLoopShutdown();
        } catch (Exception $e) {
            if ($this->throwExceptions()) {
                throw $e;
            }

            $this->_response->setException($e);
        }

        if ($this->returnResponse()) {
            return $this->_response;
        }

        $this->_response->sendResponse();
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Plugins
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Abstract.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Plugins
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Controller_Plugin_Abstract
{
    /**
     * @var Zend_Controller_Request_Abstract
     */
    protected $_request;

    /**
     * @var Zend_Controller_Response_Abstract
     */
    protected $_response;

    /**
     * Set request object
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return Zend_Controller_Plugin_Abstract
     */
    public function setRequest(Zend_Controller_Request_Abstract $request)
    {
        $this->_request = $request;
        return $this;
    }

    /**
     * Get request object
     *
     * @return Zend_Controller_Request_Abstract $request
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Set response object
     *
     * @param Zend_Controller_Response_Abstract $response
     * @return Zend_Controller_Plugin_Abstract
     */
    public function setResponse(Zend_Controller_Response_Abstract $response)
    {
        $this->_response = $response;
        return $this;
    }

    /**
     * Get response object
     *
     * @return Zend_Controller_Response_Abstract $response
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Called before Zend_Controller_Front begins evaluating the
     * request against its routes.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {}

    /**
     * Called after Zend_Controller_Router exits.
     *
     * Called after Zend_Controller_Front exits from the router.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {}

    /**
     * Called before Zend_Controller_Front enters its dispatch loop.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {}

    /**
     * Called before an action is dispatched by Zend_Controller_Dispatcher.
     *
     * This callback allows for proxy or filter behavior.  By altering the
     * request and resetting its dispatched flag (via
     * {@link Zend_Controller_Request_Abstract::setDispatched() setDispatched(false)}),
     * the current action may be skipped.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {}

    /**
     * Called after an action is dispatched by Zend_Controller_Dispatcher.
     *
     * This callback allows for proxy or filter behavior. By altering the
     * request and resetting its dispatched flag (via
     * {@link Zend_Controller_Request_Abstract::setDispatched() setDispatched(false)}),
     * a new action may be specified for dispatching.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {}

    /**
     * Called before Zend_Controller_Front exits its dispatch loop.
     *
     * @return void
     */
    public function dispatchLoopShutdown()
    {}
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Plugins
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Broker.php 20255 2010-01-13 13:23:36Z matthew $
 */

/** Zend_Controller_Plugin_Abstract */
// require_once 'Zend/Controller/Plugin/Abstract.php';

/**
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Plugins
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Controller_Plugin_Broker extends Zend_Controller_Plugin_Abstract
{

    /**
     * Array of instance of objects extending Zend_Controller_Plugin_Abstract
     *
     * @var array
     */
    protected $_plugins = array();


    /**
     * Register a plugin.
     *
     * @param  Zend_Controller_Plugin_Abstract $plugin
     * @param  int $stackIndex
     * @return Zend_Controller_Plugin_Broker
     */
    public function registerPlugin(Zend_Controller_Plugin_Abstract $plugin, $stackIndex = null)
    {
        if (false !== array_search($plugin, $this->_plugins, true)) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Plugin already registered');
        }

        $stackIndex = (int) $stackIndex;

        if ($stackIndex) {
            if (isset($this->_plugins[$stackIndex])) {
                // require_once 'Zend/Controller/Exception.php';
                throw new Zend_Controller_Exception('Plugin with stackIndex "' . $stackIndex . '" already registered');
            }
            $this->_plugins[$stackIndex] = $plugin;
        } else {
            $stackIndex = count($this->_plugins);
            while (isset($this->_plugins[$stackIndex])) {
                ++$stackIndex;
            }
            $this->_plugins[$stackIndex] = $plugin;
        }

        $request = $this->getRequest();
        if ($request) {
            $this->_plugins[$stackIndex]->setRequest($request);
        }
        $response = $this->getResponse();
        if ($response) {
            $this->_plugins[$stackIndex]->setResponse($response);
        }

        ksort($this->_plugins);

        return $this;
    }

    /**
     * Unregister a plugin.
     *
     * @param string|Zend_Controller_Plugin_Abstract $plugin Plugin object or class name
     * @return Zend_Controller_Plugin_Broker
     */
    public function unregisterPlugin($plugin)
    {
        if ($plugin instanceof Zend_Controller_Plugin_Abstract) {
            // Given a plugin object, find it in the array
            $key = array_search($plugin, $this->_plugins, true);
            if (false === $key) {
                // require_once 'Zend/Controller/Exception.php';
                throw new Zend_Controller_Exception('Plugin never registered.');
            }
            unset($this->_plugins[$key]);
        } elseif (is_string($plugin)) {
            // Given a plugin class, find all plugins of that class and unset them
            foreach ($this->_plugins as $key => $_plugin) {
                $type = get_class($_plugin);
                if ($plugin == $type) {
                    unset($this->_plugins[$key]);
                }
            }
        }
        return $this;
    }

    /**
     * Is a plugin of a particular class registered?
     *
     * @param  string $class
     * @return bool
     */
    public function hasPlugin($class)
    {
        foreach ($this->_plugins as $plugin) {
            $type = get_class($plugin);
            if ($class == $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve a plugin or plugins by class
     *
     * @param  string $class Class name of plugin(s) desired
     * @return false|Zend_Controller_Plugin_Abstract|array Returns false if none found, plugin if only one found, and array of plugins if multiple plugins of same class found
     */
    public function getPlugin($class)
    {
        $found = array();
        foreach ($this->_plugins as $plugin) {
            $type = get_class($plugin);
            if ($class == $type) {
                $found[] = $plugin;
            }
        }

        switch (count($found)) {
            case 0:
                return false;
            case 1:
                return $found[0];
            default:
                return $found;
        }
    }

    /**
     * Retrieve all plugins
     *
     * @return array
     */
    public function getPlugins()
    {
        return $this->_plugins;
    }

    /**
     * Set request object, and register with each plugin
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return Zend_Controller_Plugin_Broker
     */
    public function setRequest(Zend_Controller_Request_Abstract $request)
    {
        $this->_request = $request;

        foreach ($this->_plugins as $plugin) {
            $plugin->setRequest($request);
        }

        return $this;
    }

    /**
     * Get request object
     *
     * @return Zend_Controller_Request_Abstract $request
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Set response object
     *
     * @param Zend_Controller_Response_Abstract $response
     * @return Zend_Controller_Plugin_Broker
     */
    public function setResponse(Zend_Controller_Response_Abstract $response)
    {
        $this->_response = $response;

        foreach ($this->_plugins as $plugin) {
            $plugin->setResponse($response);
        }


        return $this;
    }

    /**
     * Get response object
     *
     * @return Zend_Controller_Response_Abstract $response
     */
    public function getResponse()
    {
        return $this->_response;
    }


    /**
     * Called before Zend_Controller_Front begins evaluating the
     * request against its routes.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        foreach ($this->_plugins as $plugin) {
            try {
                $plugin->routeStartup($request);
            } catch (Exception $e) {
                if (Zend_Controller_Front::getInstance()->throwExceptions()) {
                    throw $e;
                } else {
                    $this->getResponse()->setException($e);
                }
            }
        }
    }


    /**
     * Called before Zend_Controller_Front exits its iterations over
     * the route set.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        foreach ($this->_plugins as $plugin) {
            try {
                $plugin->routeShutdown($request);
            } catch (Exception $e) {
                if (Zend_Controller_Front::getInstance()->throwExceptions()) {
                    throw $e;
                } else {
                    $this->getResponse()->setException($e);
                }
            }
        }
    }


    /**
     * Called before Zend_Controller_Front enters its dispatch loop.
     *
     * During the dispatch loop, Zend_Controller_Front keeps a
     * Zend_Controller_Request_Abstract object, and uses
     * Zend_Controller_Dispatcher to dispatch the
     * Zend_Controller_Request_Abstract object to controllers/actions.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        foreach ($this->_plugins as $plugin) {
            try {
                $plugin->dispatchLoopStartup($request);
            } catch (Exception $e) {
                if (Zend_Controller_Front::getInstance()->throwExceptions()) {
                    throw $e;
                } else {
                    $this->getResponse()->setException($e);
                }
            }
        }
    }


    /**
     * Called before an action is dispatched by Zend_Controller_Dispatcher.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        foreach ($this->_plugins as $plugin) {
            try {
                $plugin->preDispatch($request);
            } catch (Exception $e) {
                if (Zend_Controller_Front::getInstance()->throwExceptions()) {
                    throw $e;
                } else {
                    $this->getResponse()->setException($e);
                }
            }
        }
    }


    /**
     * Called after an action is dispatched by Zend_Controller_Dispatcher.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
        foreach ($this->_plugins as $plugin) {
            try {
                $plugin->postDispatch($request);
            } catch (Exception $e) {
                if (Zend_Controller_Front::getInstance()->throwExceptions()) {
                    throw $e;
                } else {
                    $this->getResponse()->setException($e);
                }
            }
        }
    }


    /**
     * Called before Zend_Controller_Front exits its dispatch loop.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopShutdown()
    {
       foreach ($this->_plugins as $plugin) {
           try {
                $plugin->dispatchLoopShutdown();
            } catch (Exception $e) {
                if (Zend_Controller_Front::getInstance()->throwExceptions()) {
                    throw $e;
                } else {
                    $this->getResponse()->setException($e);
                }
            }
       }
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Abstract.php 20096 2010-01-06 02:05:09Z bkarwin $
 */


/** Zend_Controller_Router_Interface */
// require_once 'Zend/Controller/Router/Interface.php';

/**
 * Simple first implementation of a router, to be replaced
 * with rules-based URI processor.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Controller_Router_Abstract implements Zend_Controller_Router_Interface
{
    /**
     * Front controller instance
     * @var Zend_Controller_Front
     */
    protected $_frontController;

    /**
     * Array of invocation parameters to use when instantiating action
     * controllers
     * @var array
     */
    protected $_invokeParams = array();

    /**
     * Constructor
     *
     * @param array $params
     * @return void
     */
    public function __construct(array $params = array())
    {
        $this->setParams($params);
    }

    /**
     * Add or modify a parameter to use when instantiating an action controller
     *
     * @param string $name
     * @param mixed $value
     * @return Zend_Controller_Router
     */
    public function setParam($name, $value)
    {
        $name = (string) $name;
        $this->_invokeParams[$name] = $value;
        return $this;
    }

    /**
     * Set parameters to pass to action controller constructors
     *
     * @param array $params
     * @return Zend_Controller_Router
     */
    public function setParams(array $params)
    {
        $this->_invokeParams = array_merge($this->_invokeParams, $params);
        return $this;
    }

    /**
     * Retrieve a single parameter from the controller parameter stack
     *
     * @param string $name
     * @return mixed
     */
    public function getParam($name)
    {
        if(isset($this->_invokeParams[$name])) {
            return $this->_invokeParams[$name];
        }

        return null;
    }

    /**
     * Retrieve action controller instantiation parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_invokeParams;
    }

    /**
     * Clear the controller parameter stack
     *
     * By default, clears all parameters. If a parameter name is given, clears
     * only that parameter; if an array of parameter names is provided, clears
     * each.
     *
     * @param null|string|array single key or array of keys for params to clear
     * @return Zend_Controller_Router
     */
    public function clearParams($name = null)
    {
        if (null === $name) {
            $this->_invokeParams = array();
        } elseif (is_string($name) && isset($this->_invokeParams[$name])) {
            unset($this->_invokeParams[$name]);
        } elseif (is_array($name)) {
            foreach ($name as $key) {
                if (is_string($key) && isset($this->_invokeParams[$key])) {
                    unset($this->_invokeParams[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * Retrieve Front Controller
     *
     * @return Zend_Controller_Front
     */
    public function getFrontController()
    {
        // Used cache version if found
        if (null !== $this->_frontController) {
            return $this->_frontController;
        }

        // require_once 'Zend/Controller/Front.php';
        $this->_frontController = Zend_Controller_Front::getInstance();
        return $this->_frontController;
    }

    /**
     * Set Front Controller
     *
     * @param Zend_Controller_Front $controller
     * @return Zend_Controller_Router_Interface
     */
    public function setFrontController(Zend_Controller_Front $controller)
    {
        $this->_frontController = $controller;
        return $this;
    }

}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @version    $Id: Rewrite.php 20246 2010-01-12 21:36:08Z dasprid $
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Zend_Controller_Router_Abstract */
// require_once 'Zend/Controller/Router/Abstract.php';

/** Zend_Controller_Router_Route */
// require_once 'Zend/Controller/Router/Route.php';

/**
 * Ruby routing based Router.
 *
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @see        http://manuals.rubyonrails.com/read/chapter/65
 */
class Zend_Controller_Router_Rewrite extends Zend_Controller_Router_Abstract
{

    /**
     * Whether or not to use default routes
     *
     * @var boolean
     */
    protected $_useDefaultRoutes = true;

    /**
     * Array of routes to match against
     *
     * @var array
     */
    protected $_routes = array();

    /**
     * Currently matched route
     *
     * @var Zend_Controller_Router_Route_Interface
     */
    protected $_currentRoute = null;

    /**
     * Global parameters given to all routes
     *
     * @var array
     */
    protected $_globalParams = array();

    /**
     * Separator to use with chain names
     *
     * @var string
     */
    protected $_chainNameSeparator = '-';

    /**
     * Determines if request parameters should be used as global parameters
     * inside this router.
     *
     * @var boolean
     */
    protected $_useCurrentParamsAsGlobal = false;

    /**
     * Add default routes which are used to mimic basic router behaviour
     *
     * @return Zend_Controller_Router_Rewrite
     */
    public function addDefaultRoutes()
    {
        if (!$this->hasRoute('default')) {
            $dispatcher = $this->getFrontController()->getDispatcher();
            $request = $this->getFrontController()->getRequest();

            // require_once 'Zend/Controller/Router/Route/Module.php';
            $compat = new Zend_Controller_Router_Route_Module(array(), $dispatcher, $request);

            $this->_routes = array_merge(array('default' => $compat), $this->_routes);
        }

        return $this;
    }

    /**
     * Add route to the route chain
     *
     * If route contains method setRequest(), it is initialized with a request object
     *
     * @param  string                                 $name       Name of the route
     * @param  Zend_Controller_Router_Route_Interface $route      Instance of the route
     * @return Zend_Controller_Router_Rewrite
     */
    public function addRoute($name, Zend_Controller_Router_Route_Interface $route)
    {
        if (method_exists($route, 'setRequest')) {
            $route->setRequest($this->getFrontController()->getRequest());
        }

        $this->_routes[$name] = $route;

        return $this;
    }

    /**
     * Add routes to the route chain
     *
     * @param  array $routes Array of routes with names as keys and routes as values
     * @return Zend_Controller_Router_Rewrite
     */
    public function addRoutes($routes) {
        foreach ($routes as $name => $route) {
            $this->addRoute($name, $route);
        }

        return $this;
    }

    /**
     * Create routes out of Zend_Config configuration
     *
     * Example INI:
     * routes.archive.route = "archive/:year/*"
     * routes.archive.defaults.controller = archive
     * routes.archive.defaults.action = show
     * routes.archive.defaults.year = 2000
     * routes.archive.reqs.year = "\d+"
     *
     * routes.news.type = "Zend_Controller_Router_Route_Static"
     * routes.news.route = "news"
     * routes.news.defaults.controller = "news"
     * routes.news.defaults.action = "list"
     *
     * And finally after you have created a Zend_Config with above ini:
     * $router = new Zend_Controller_Router_Rewrite();
     * $router->addConfig($config, 'routes');
     *
     * @param  Zend_Config $config  Configuration object
     * @param  string      $section Name of the config section containing route's definitions
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Router_Rewrite
     */
    public function addConfig(Zend_Config $config, $section = null)
    {
        if ($section !== null) {
            if ($config->{$section} === null) {
                // require_once 'Zend/Controller/Router/Exception.php';
                throw new Zend_Controller_Router_Exception("No route configuration in section '{$section}'");
            }

            $config = $config->{$section};
        }

        foreach ($config as $name => $info) {
            $route = $this->_getRouteFromConfig($info);

            if ($route instanceof Zend_Controller_Router_Route_Chain) {
                if (!isset($info->chain)) {
                    // require_once 'Zend/Controller/Router/Exception.php';
                    throw new Zend_Controller_Router_Exception("No chain defined");
                }

                if ($info->chain instanceof Zend_Config) {
                    $childRouteNames = $info->chain;
                } else {
                    $childRouteNames = explode(',', $info->chain);
                }

                foreach ($childRouteNames as $childRouteName) {
                    $childRoute = $this->getRoute(trim($childRouteName));
                    $route->chain($childRoute);
                }

                $this->addRoute($name, $route);
            } elseif (isset($info->chains) && $info->chains instanceof Zend_Config) {
                $this->_addChainRoutesFromConfig($name, $route, $info->chains);
            } else {
                $this->addRoute($name, $route);
            }
        }

        return $this;
    }

    /**
     * Get a route frm a config instance
     *
     * @param  Zend_Config $info
     * @return Zend_Controller_Router_Route_Interface
     */
    protected function _getRouteFromConfig(Zend_Config $info)
    {
        $class = (isset($info->type)) ? $info->type : 'Zend_Controller_Router_Route';
        if (!class_exists($class)) {
            // require_once 'Zend/Loader.php';
            Zend_Loader::loadClass($class);
        }

        $route = call_user_func(array($class, 'getInstance'), $info);

        if (isset($info->abstract) && $info->abstract && method_exists($route, 'isAbstract')) {
            $route->isAbstract(true);
        }

        return $route;
    }

    /**
     * Add chain routes from a config route
     *
     * @param  string                                 $name
     * @param  Zend_Controller_Router_Route_Interface $route
     * @param  Zend_Config                            $childRoutesInfo
     * @return void
     */
    protected function _addChainRoutesFromConfig($name,
                                                 Zend_Controller_Router_Route_Interface $route,
                                                 Zend_Config $childRoutesInfo)
    {
        foreach ($childRoutesInfo as $childRouteName => $childRouteInfo) {
            if (is_string($childRouteInfo)) {
                $childRouteName = $childRouteInfo;
                $childRoute     = $this->getRoute($childRouteName);
            } else {
                $childRoute = $this->_getRouteFromConfig($childRouteInfo);
            }

            if ($route instanceof Zend_Controller_Router_Route_Chain) {
                $chainRoute = clone $route;
                $chainRoute->chain($childRoute);
            } else {
                $chainRoute = $route->chain($childRoute);
            }

            $chainName = $name . $this->_chainNameSeparator . $childRouteName;

            if (isset($childRouteInfo->chains)) {
                $this->_addChainRoutesFromConfig($chainName, $chainRoute, $childRouteInfo->chains);
            } else {
                $this->addRoute($chainName, $chainRoute);
            }
        }
    }

    /**
     * Remove a route from the route chain
     *
     * @param  string $name Name of the route
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Router_Rewrite
     */
    public function removeRoute($name)
    {
        if (!isset($this->_routes[$name])) {
            // require_once 'Zend/Controller/Router/Exception.php';
            throw new Zend_Controller_Router_Exception("Route $name is not defined");
        }

        unset($this->_routes[$name]);

        return $this;
    }

    /**
     * Remove all standard default routes
     *
     * @param  Zend_Controller_Router_Route_Interface Route
     * @return Zend_Controller_Router_Rewrite
     */
    public function removeDefaultRoutes()
    {
        $this->_useDefaultRoutes = false;

        return $this;
    }

    /**
     * Check if named route exists
     *
     * @param  string $name Name of the route
     * @return boolean
     */
    public function hasRoute($name)
    {
        return isset($this->_routes[$name]);
    }

    /**
     * Retrieve a named route
     *
     * @param string $name Name of the route
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Router_Route_Interface Route object
     */
    public function getRoute($name)
    {
        if (!isset($this->_routes[$name])) {
            // require_once 'Zend/Controller/Router/Exception.php';
            throw new Zend_Controller_Router_Exception("Route $name is not defined");
        }

        return $this->_routes[$name];
    }

    /**
     * Retrieve a currently matched route
     *
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Router_Route_Interface Route object
     */
    public function getCurrentRoute()
    {
        if (!isset($this->_currentRoute)) {
            // require_once 'Zend/Controller/Router/Exception.php';
            throw new Zend_Controller_Router_Exception("Current route is not defined");
        }
        return $this->getRoute($this->_currentRoute);
    }

    /**
     * Retrieve a name of currently matched route
     *
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Router_Route_Interface Route object
     */
    public function getCurrentRouteName()
    {
        if (!isset($this->_currentRoute)) {
            // require_once 'Zend/Controller/Router/Exception.php';
            throw new Zend_Controller_Router_Exception("Current route is not defined");
        }
        return $this->_currentRoute;
    }

    /**
     * Retrieve an array of routes added to the route chain
     *
     * @return array All of the defined routes
     */
    public function getRoutes()
    {
        return $this->_routes;
    }

    /**
     * Find a matching route to the current PATH_INFO and inject
     * returning values to the Request object.
     *
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Request_Abstract Request object
     */
    public function route(Zend_Controller_Request_Abstract $request)
    {
        if (!$request instanceof Zend_Controller_Request_Http) {
            // require_once 'Zend/Controller/Router/Exception.php';
            throw new Zend_Controller_Router_Exception('Zend_Controller_Router_Rewrite requires a Zend_Controller_Request_Http-based request object');
        }

        if ($this->_useDefaultRoutes) {
            $this->addDefaultRoutes();
        }

        // Find the matching route
        $routeMatched = false;
        
        foreach (array_reverse($this->_routes) as $name => $route) {
            // TODO: Should be an interface method. Hack for 1.0 BC
            if (method_exists($route, 'isAbstract') && $route->isAbstract()) {
                continue;
            }

            // TODO: Should be an interface method. Hack for 1.0 BC
            if (!method_exists($route, 'getVersion') || $route->getVersion() == 1) {
                $match = $request->getPathInfo();
            } else {
                $match = $request;
            }

            if ($params = $route->match($match)) {
                $this->_setRequestParams($request, $params);
                $this->_currentRoute = $name;
                $routeMatched        = true;
                break;
            }
        }

         if (!$routeMatched) {
             // require_once 'Zend/Controller/Router/Exception.php';
             throw new Zend_Controller_Router_Exception('No route matched the request', 404);
         }

        if($this->_useCurrentParamsAsGlobal) {
            $params = $request->getParams();
            foreach($params as $param => $value) {
                $this->setGlobalParam($param, $value);
            }
        }

        return $request;

    }

    protected function _setRequestParams($request, $params)
    {
        foreach ($params as $param => $value) {

            $request->setParam($param, $value);

            if ($param === $request->getModuleKey()) {
                $request->setModuleName($value);
            }
            if ($param === $request->getControllerKey()) {
                $request->setControllerName($value);
            }
            if ($param === $request->getActionKey()) {
                $request->setActionName($value);
            }

        }
    }

    /**
     * Generates a URL path that can be used in URL creation, redirection, etc.
     *
     * @param  array $userParams Options passed by a user used to override parameters
     * @param  mixed $name The name of a Route to use
     * @param  bool $reset Whether to reset to the route defaults ignoring URL params
     * @param  bool $encode Tells to encode URL parts on output
     * @throws Zend_Controller_Router_Exception
     * @return string Resulting absolute URL path
     */
    public function assemble($userParams, $name = null, $reset = false, $encode = true)
    {
        if ($name == null) {
            try {
                $name = $this->getCurrentRouteName();
            } catch (Zend_Controller_Router_Exception $e) {
                $name = 'default';
            }
        }

        $params = array_merge($this->_globalParams, $userParams);

        $route = $this->getRoute($name);
        $url   = $route->assemble($params, $reset, $encode);

        if (!preg_match('|^[a-z]+://|', $url)) {
            $url = rtrim($this->getFrontController()->getBaseUrl(), '/') . '/' . $url;
        }

        return $url;
    }

    /**
     * Set a global parameter
     *
     * @param  string $name
     * @param  mixed $value
     * @return Zend_Controller_Router_Rewrite
     */
    public function setGlobalParam($name, $value)
    {
        $this->_globalParams[$name] = $value;

        return $this;
    }

    /**
     * Set the separator to use with chain names
     *
     * @param string $separator The separator to use
     * @return Zend_Controller_Router_Rewrite
     */
    public function setChainNameSeparator($separator) {
        $this->_chainNameSeparator = $separator;

        return $this;
    }

    /**
     * Get the separator to use for chain names
     *
     * @return string
     */
    public function getChainNameSeparator() {
        return $this->_chainNameSeparator;
    }

    /**
     * Determines/returns whether to use the request parameters as global parameters.
     *
     * @param boolean|null $use
     *           Null/unset when you want to retrieve the current state.
     *           True when request parameters should be global, false otherwise
     * @return boolean|Zend_Controller_Router_Rewrite
     *              Returns a boolean if first param isn't set, returns an
     *              instance of Zend_Controller_Router_Rewrite otherwise.
     *
     */
    public function useRequestParametersAsGlobal($use = null) {
        if($use === null) {
            return $this->_useCurrentParamsAsGlobal;
        }

        $this->_useCurrentParamsAsGlobal = (bool) $use;

        return $this;
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @version    $Id: Abstract.php 20096 2010-01-06 02:05:09Z bkarwin $
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @see Zend_Controller_Router_Route_Interface
 */
// require_once 'Zend/Controller/Router/Route/Interface.php';

/**
 * Abstract Route
 *
 * Implements interface and provides convenience methods
 *
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Controller_Router_Route_Abstract implements Zend_Controller_Router_Route_Interface
{
    /**
     * Wether this route is abstract or not
     *
     * @var boolean
     */
    protected $_isAbstract = false;

    /**
     * Path matched by this route
     *
     * @var string
     */
    protected $_matchedPath = null;

    /**
     * Get the version of the route
     *
     * @return integer
     */
    public function getVersion()
    {
        return 2;
    }

    /**
     * Set partially matched path
     *
     * @param  string $path
     * @return void
     */
    public function setMatchedPath($path)
    {
        $this->_matchedPath = $path;
    }

    /**
     * Get partially matched path
     *
     * @return string
     */
    public function getMatchedPath()
    {
        return $this->_matchedPath;
    }

    /**
     * Check or set wether this is an abstract route or not
     *
     * @param  boolean $flag
     * @return boolean
     */
    public function isAbstract($flag = null)
    {
        if ($flag !== null) {
            $this->_isAbstract = $flag;
        }

        return $this->_isAbstract;
    }

    /**
     * Create a new chain
     *
     * @param  Zend_Controller_Router_Route_Abstract $route
     * @param  string                                $separator
     * @return Zend_Controller_Router_Route_Chain
     */
    public function chain(Zend_Controller_Router_Route_Abstract $route, $separator = '/')
    {
        // require_once 'Zend/Controller/Router/Route/Chain.php';

        $chain = new Zend_Controller_Router_Route_Chain();
        $chain->chain($this)->chain($route, $separator);

        return $chain;
    }

}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @version    $Id: Route.php 20096 2010-01-06 02:05:09Z bkarwin $
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Zend_Controller_Router_Route_Abstract */
// require_once 'Zend/Controller/Router/Route/Abstract.php';

/**
 * Route
 *
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @see        http://manuals.rubyonrails.com/read/chapter/65
 */
class Zend_Controller_Router_Route extends Zend_Controller_Router_Route_Abstract
{
    /**
     * Default translator
     *
     * @var Zend_Translate
     */
    protected static $_defaultTranslator;

    /**
     * Translator
     *
     * @var Zend_Translate
     */
    protected $_translator;

    /**
     * Default locale
     *
     * @var mixed
     */
    protected static $_defaultLocale;

    /**
     * Locale
     *
     * @var mixed
     */
    protected $_locale;

    /**
     * Wether this is a translated route or not
     *
     * @var boolean
     */
    protected $_isTranslated = false;

    /**
     * Translatable variables
     *
     * @var array
     */
    protected $_translatable = array();

    protected $_urlVariable = ':';
    protected $_urlDelimiter = '/';
    protected $_regexDelimiter = '#';
    protected $_defaultRegex = null;

    /**
     * Holds names of all route's pattern variable names. Array index holds a position in URL.
     * @var array
     */
    protected $_variables = array();

    /**
     * Holds Route patterns for all URL parts. In case of a variable it stores it's regex
     * requirement or null. In case of a static part, it holds only it's direct value.
     * In case of a wildcard, it stores an asterisk (*)
     * @var array
     */
    protected $_parts = array();

    /**
     * Holds user submitted default values for route's variables. Name and value pairs.
     * @var array
     */
    protected $_defaults = array();

    /**
     * Holds user submitted regular expression patterns for route's variables' values.
     * Name and value pairs.
     * @var array
     */
    protected $_requirements = array();

    /**
     * Associative array filled on match() that holds matched path values
     * for given variable names.
     * @var array
     */
    protected $_values = array();

    /**
     * Associative array filled on match() that holds wildcard variable
     * names and values.
     * @var array
     */
    protected $_wildcardData = array();

    /**
     * Helper var that holds a count of route pattern's static parts
     * for validation
     * @var int
     */
    protected $_staticCount = 0;

    public function getVersion() {
        return 1;
    }

    /**
     * Instantiates route based on passed Zend_Config structure
     *
     * @param Zend_Config $config Configuration object
     */
    public static function getInstance(Zend_Config $config)
    {
        $reqs = ($config->reqs instanceof Zend_Config) ? $config->reqs->toArray() : array();
        $defs = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        return new self($config->route, $defs, $reqs);
    }

    /**
     * Prepares the route for mapping by splitting (exploding) it
     * to a corresponding atomic parts. These parts are assigned
     * a position which is later used for matching and preparing values.
     *
     * @param string $route Map used to match with later submitted URL path
     * @param array $defaults Defaults for map variables with keys as variable names
     * @param array $reqs Regular expression requirements for variables (keys as variable names)
     * @param Zend_Translate $translator Translator to use for this instance
     */
    public function __construct($route, $defaults = array(), $reqs = array(), Zend_Translate $translator = null, $locale = null)
    {
        $route               = trim($route, $this->_urlDelimiter);
        $this->_defaults     = (array) $defaults;
        $this->_requirements = (array) $reqs;
        $this->_translator   = $translator;
        $this->_locale       = $locale;

        if ($route !== '') {
            foreach (explode($this->_urlDelimiter, $route) as $pos => $part) {
                if (substr($part, 0, 1) == $this->_urlVariable && substr($part, 1, 1) != $this->_urlVariable) {
                    $name = substr($part, 1);

                    if (substr($name, 0, 1) === '@' && substr($name, 1, 1) !== '@') {
                        $name                  = substr($name, 1);
                        $this->_translatable[] = $name;
                        $this->_isTranslated   = true;
                    }

                    $this->_parts[$pos]     = (isset($reqs[$name]) ? $reqs[$name] : $this->_defaultRegex);
                    $this->_variables[$pos] = $name;
                } else {
                    if (substr($part, 0, 1) == $this->_urlVariable) {
                        $part = substr($part, 1);
                    }

                    if (substr($part, 0, 1) === '@' && substr($part, 1, 1) !== '@') {
                        $this->_isTranslated = true;
                    }

                    $this->_parts[$pos] = $part;

                    if ($part !== '*') {
                        $this->_staticCount++;
                    }
                }
            }
        }
    }

    /**
     * Matches a user submitted path with parts defined by a map. Assigns and
     * returns an array of variables on a successful match.
     *
     * @param string $path Path used to match against this routing map
     * @return array|false An array of assigned values or a false on a mismatch
     */
    public function match($path, $partial = false)
    {
        if ($this->_isTranslated) {
            $translateMessages = $this->getTranslator()->getMessages();
        }

        $pathStaticCount = 0;
        $values          = array();
        $matchedPath     = '';

        if (!$partial) {
            $path = trim($path, $this->_urlDelimiter);
        }

        if ($path !== '') {
            $path = explode($this->_urlDelimiter, $path);

            foreach ($path as $pos => $pathPart) {
                // Path is longer than a route, it's not a match
                if (!array_key_exists($pos, $this->_parts)) {
                    if ($partial) {
                        break;
                    } else {
                        return false;
                    }
                }

                $matchedPath .= $pathPart . $this->_urlDelimiter;

                // If it's a wildcard, get the rest of URL as wildcard data and stop matching
                if ($this->_parts[$pos] == '*') {
                    $count = count($path);
                    for($i = $pos; $i < $count; $i+=2) {
                        $var = urldecode($path[$i]);
                        if (!isset($this->_wildcardData[$var]) && !isset($this->_defaults[$var]) && !isset($values[$var])) {
                            $this->_wildcardData[$var] = (isset($path[$i+1])) ? urldecode($path[$i+1]) : null;
                        }
                    }

                    $matchedPath = implode($this->_urlDelimiter, $path);
                    break;
                }

                $name     = isset($this->_variables[$pos]) ? $this->_variables[$pos] : null;
                $pathPart = urldecode($pathPart);

                // Translate value if required
                $part = $this->_parts[$pos];
                if ($this->_isTranslated && (substr($part, 0, 1) === '@' && substr($part, 1, 1) !== '@' && $name === null) || $name !== null && in_array($name, $this->_translatable)) {
                    if (substr($part, 0, 1) === '@') {
                        $part = substr($part, 1);
                    }

                    if (($originalPathPart = array_search($pathPart, $translateMessages)) !== false) {
                        $pathPart = $originalPathPart;
                    }
                }

                if (substr($part, 0, 2) === '@@') {
                    $part = substr($part, 1);
                }

                // If it's a static part, match directly
                if ($name === null && $part != $pathPart) {
                    return false;
                }

                // If it's a variable with requirement, match a regex. If not - everything matches
                if ($part !== null && !preg_match($this->_regexDelimiter . '^' . $part . '$' . $this->_regexDelimiter . 'iu', $pathPart)) {
                    return false;
                }

                // If it's a variable store it's value for later
                if ($name !== null) {
                    $values[$name] = $pathPart;
                } else {
                    $pathStaticCount++;
                }
            }
        }

        // Check if all static mappings have been matched
        if ($this->_staticCount != $pathStaticCount) {
            return false;
        }

        $return = $values + $this->_wildcardData + $this->_defaults;

        // Check if all map variables have been initialized
        foreach ($this->_variables as $var) {
            if (!array_key_exists($var, $return)) {
                return false;
            }
        }

        $this->setMatchedPath(rtrim($matchedPath, $this->_urlDelimiter));

        $this->_values = $values;

        return $return;

    }

    /**
     * Assembles user submitted parameters forming a URL path defined by this route
     *
     * @param  array $data An array of variable and value pairs used as parameters
     * @param  boolean $reset Whether or not to set route defaults with those provided in $data
     * @return string Route path with user submitted parameters
     */
    public function assemble($data = array(), $reset = false, $encode = false, $partial = false)
    {
        if ($this->_isTranslated) {
            $translator = $this->getTranslator();

            if (isset($data['@locale'])) {
                $locale = $data['@locale'];
                unset($data['@locale']);
            } else {
                $locale = $this->getLocale();
            }
        }

        $url  = array();
        $flag = false;

        foreach ($this->_parts as $key => $part) {
            $name = isset($this->_variables[$key]) ? $this->_variables[$key] : null;

            $useDefault = false;
            if (isset($name) && array_key_exists($name, $data) && $data[$name] === null) {
                $useDefault = true;
            }

            if (isset($name)) {
                if (isset($data[$name]) && !$useDefault) {
                    $value = $data[$name];
                    unset($data[$name]);
                } elseif (!$reset && !$useDefault && isset($this->_values[$name])) {
                    $value = $this->_values[$name];
                } elseif (!$reset && !$useDefault && isset($this->_wildcardData[$name])) {
                    $value = $this->_wildcardData[$name];
                } elseif (isset($this->_defaults[$name])) {
                    $value = $this->_defaults[$name];
                } else {
                    // require_once 'Zend/Controller/Router/Exception.php';
                    throw new Zend_Controller_Router_Exception($name . ' is not specified');
                }

                if ($this->_isTranslated && in_array($name, $this->_translatable)) {
                    $url[$key] = $translator->translate($value, $locale);
                } else {
                    $url[$key] = $value;
                }
            } elseif ($part != '*') {
                if ($this->_isTranslated && substr($part, 0, 1) === '@') {
                    if (substr($part, 1, 1) !== '@') {
                        $url[$key] = $translator->translate(substr($part, 1), $locale);
                    } else {
                        $url[$key] = substr($part, 1);
                    }
                } else {
                    if (substr($part, 0, 2) === '@@') {
                        $part = substr($part, 1);
                    }

                    $url[$key] = $part;
                }
            } else {
                if (!$reset) $data += $this->_wildcardData;
                $defaults = $this->getDefaults();
                foreach ($data as $var => $value) {
                    if ($value !== null && (!isset($defaults[$var]) || $value != $defaults[$var])) {
                        $url[$key++] = $var;
                        $url[$key++] = $value;
                        $flag = true;
                    }
                }
            }
        }

        $return = '';

        foreach (array_reverse($url, true) as $key => $value) {
            $defaultValue = null;

            if (isset($this->_variables[$key])) {
                $defaultValue = $this->getDefault($this->_variables[$key]);

                if ($this->_isTranslated && $defaultValue !== null && isset($this->_translatable[$this->_variables[$key]])) {
                    $defaultValue = $translator->translate($defaultValue, $locale);
                }
            }

            if ($flag || $value !== $defaultValue || $partial) {
                if ($encode) $value = urlencode($value);
                $return = $this->_urlDelimiter . $value . $return;
                $flag = true;
            }
        }

        return trim($return, $this->_urlDelimiter);

    }

    /**
     * Return a single parameter of route's defaults
     *
     * @param string $name Array key of the parameter
     * @return string Previously set default
     */
    public function getDefault($name) {
        if (isset($this->_defaults[$name])) {
            return $this->_defaults[$name];
        }
        return null;
    }

    /**
     * Return an array of defaults
     *
     * @return array Route defaults
     */
    public function getDefaults() {
        return $this->_defaults;
    }

    /**
     * Get all variables which are used by the route
     *
     * @return array
     */
    public function getVariables()
    {
        return $this->_variables;
    }

    /**
     * Set a default translator
     *
     * @param  Zend_Translate $translator
     * @return void
     */
    public static function setDefaultTranslator(Zend_Translate $translator = null)
    {
        self::$_defaultTranslator = $translator;
    }

    /**
     * Get the default translator
     *
     * @return Zend_Translate
     */
    public static function getDefaultTranslator()
    {
        return self::$_defaultTranslator;
    }

    /**
     * Set a translator
     *
     * @param  Zend_Translate $translator
     * @return void
     */
    public function setTranslator(Zend_Translate $translator)
    {
        $this->_translator = $translator;
    }

    /**
     * Get the translator
     *
     * @throws Zend_Controller_Router_Exception When no translator can be found
     * @return Zend_Translate
     */
    public function getTranslator()
    {
        if ($this->_translator !== null) {
            return $this->_translator;
        } else if (($translator = self::getDefaultTranslator()) !== null) {
            return $translator;
        } else {
            try {
                $translator = Zend_Registry::get('Zend_Translate');
            } catch (Zend_Exception $e) {
                $translator = null;
            }

            if ($translator instanceof Zend_Translate) {
                return $translator;
            }
        }

        // require_once 'Zend/Controller/Router/Exception.php';
        throw new Zend_Controller_Router_Exception('Could not find a translator');
    }

    /**
     * Set a default locale
     *
     * @param  mixed $locale
     * @return void
     */
    public static function setDefaultLocale($locale = null)
    {
        self::$_defaultLocale = $locale;
    }

    /**
     * Get the default locale
     *
     * @return mixed
     */
    public static function getDefaultLocale()
    {
        return self::$_defaultLocale;
    }

    /**
     * Set a locale
     *
     * @param  mixed $locale
     * @return void
     */
    public function setLocale($locale)
    {
        $this->_locale = $locale;
    }

    /**
     * Get the locale
     *
     * @return mixed
     */
    public function getLocale()
    {
        if ($this->_locale !== null) {
            return $this->_locale;
        } else if (($locale = self::getDefaultLocale()) !== null) {
            return $locale;
        } else {
            try {
                $locale = Zend_Registry::get('Zend_Locale');
            } catch (Zend_Exception $e) {
                $locale = null;
            }

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @version    $Id: Static.php 20096 2010-01-06 02:05:09Z bkarwin $
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Zend_Controller_Router_Route_Abstract */
// require_once 'Zend/Controller/Router/Route/Abstract.php';

/**
 * StaticRoute is used for managing static URIs.
 *
 * It's a lot faster compared to the standard Route implementation.
 *
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Controller_Router_Route_Static extends Zend_Controller_Router_Route_Abstract
{

    protected $_route = null;
    protected $_defaults = array();

    public function getVersion() {
        return 1;
    }

    /**
     * Instantiates route based on passed Zend_Config structure
     *
     * @param Zend_Config $config Configuration object
     */
    public static function getInstance(Zend_Config $config)
    {
        $defs = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        return new self($config->route, $defs);
    }

    /**
     * Prepares the route for mapping.
     *
     * @param string $route Map used to match with later submitted URL path
     * @param array $defaults Defaults for map variables with keys as variable names
     */
    public function __construct($route, $defaults = array())
    {
        $this->_route = trim($route, '/');
        $this->_defaults = (array) $defaults;
    }

    /**
     * Matches a user submitted path with a previously defined route.
     * Assigns and returns an array of defaults on a successful match.
     *
     * @param string $path Path used to match against this routing map
     * @return array|false An array of assigned values or a false on a mismatch
     */
    public function match($path, $partial = false)
    {
        if ($partial) {
            if (substr($path, 0, strlen($this->_route)) === $this->_route) {
                $this->setMatchedPath($this->_route);
                return $this->_defaults;
            }
        } else {
            if (trim($path, '/') == $this->_route) {
                return $this->_defaults;
            }
        }

        return false;
    }

    /**
     * Assembles a URL path defined by this route
     *
     * @param array $data An array of variable and value pairs used as parameters
     * @return string Route path with user submitted parameters
     */
    public function assemble($data = array(), $reset = false, $encode = false, $partial = false)
    {
        return $this->_route;
    }

    /**
     * Return a single parameter of route's defaults
     *
     * @param string $name Array key of the parameter
     * @return string Previously set default
     */
    public function getDefault($name) {
        if (isset($this->_defaults[$name])) {
            return $this->_defaults[$name];
        }
        return null;
    }

    /**
     * Return an array of defaults
     *
     * @return array Route defaults
     */
    public function getDefaults() {
        return $this->_defaults;
    }

}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Dispatcher
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Abstract.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/** Zend_Controller_Dispatcher_Interface */
// require_once 'Zend/Controller/Dispatcher/Interface.php';

/**
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Dispatcher
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Controller_Dispatcher_Abstract implements Zend_Controller_Dispatcher_Interface
{
    /**
     * Default action
     * @var string
     */
    protected $_defaultAction = 'index';

    /**
     * Default controller
     * @var string
     */
    protected $_defaultController = 'index';

    /**
     * Default module
     * @var string
     */
    protected $_defaultModule = 'default';

    /**
     * Front Controller instance
     * @var Zend_Controller_Front
     */
    protected $_frontController;

    /**
     * Array of invocation parameters to use when instantiating action
     * controllers
     * @var array
     */
    protected $_invokeParams = array();

    /**
     * Path delimiter character
     * @var string
     */
    protected $_pathDelimiter = '_';

    /**
     * Response object to pass to action controllers, if any
     * @var Zend_Controller_Response_Abstract|null
     */
    protected $_response = null;

    /**
     * Word delimiter characters
     * @var array
     */
    protected $_wordDelimiter = array('-', '.');

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(array $params = array())
    {
        $this->setParams($params);
    }

    /**
     * Formats a string into a controller name.  This is used to take a raw
     * controller name, such as one stored inside a Zend_Controller_Request_Abstract
     * object, and reformat it to a proper class name that a class extending
     * Zend_Controller_Action would use.
     *
     * @param string $unformatted
     * @return string
     */
    public function formatControllerName($unformatted)
    {
        return ucfirst($this->_formatName($unformatted)) . 'Controller';
    }

    /**
     * Formats a string into an action name.  This is used to take a raw
     * action name, such as one that would be stored inside a Zend_Controller_Request_Abstract
     * object, and reformat into a proper method name that would be found
     * inside a class extending Zend_Controller_Action.
     *
     * @param string $unformatted
     * @return string
     */
    public function formatActionName($unformatted)
    {
        $formatted = $this->_formatName($unformatted, true);
        return strtolower(substr($formatted, 0, 1)) . substr($formatted, 1) . 'Action';
    }

    /**
     * Verify delimiter
     *
     * Verify a delimiter to use in controllers or actions. May be a single
     * string or an array of strings.
     *
     * @param string|array $spec
     * @return array
     * @throws Zend_Controller_Dispatcher_Exception with invalid delimiters
     */
    public function _verifyDelimiter($spec)
    {
        if (is_string($spec)) {
            return (array) $spec;
        } elseif (is_array($spec)) {
            $allStrings = true;
            foreach ($spec as $delim) {
                if (!is_string($delim)) {
                    $allStrings = false;
                    break;
                }
            }

            if (!$allStrings) {
                // require_once 'Zend/Controller/Dispatcher/Exception.php';
                throw new Zend_Controller_Dispatcher_Exception('Word delimiter array must contain only strings');
            }

            return $spec;
        }

        // require_once 'Zend/Controller/Dispatcher/Exception.php';
        throw new Zend_Controller_Dispatcher_Exception('Invalid word delimiter');
    }

    /**
     * Retrieve the word delimiter character(s) used in
     * controller or action names
     *
     * @return array
     */
    public function getWordDelimiter()
    {
        return $this->_wordDelimiter;
    }

    /**
     * Set word delimiter
     *
     * Set the word delimiter to use in controllers and actions. May be a
     * single string or an array of strings.
     *
     * @param string|array $spec
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setWordDelimiter($spec)
    {
        $spec = $this->_verifyDelimiter($spec);
        $this->_wordDelimiter = $spec;

        return $this;
    }

    /**
     * Retrieve the path delimiter character(s) used in
     * controller names
     *
     * @return array
     */
    public function getPathDelimiter()
    {
        return $this->_pathDelimiter;
    }

    /**
     * Set path delimiter
     *
     * Set the path delimiter to use in controllers. May be a single string or
     * an array of strings.
     *
     * @param string $spec
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setPathDelimiter($spec)
    {
        if (!is_string($spec)) {
            // require_once 'Zend/Controller/Dispatcher/Exception.php';
            throw new Zend_Controller_Dispatcher_Exception('Invalid path delimiter');
        }
        $this->_pathDelimiter = $spec;

        return $this;
    }

    /**
     * Formats a string from a URI into a PHP-friendly name.
     *
     * By default, replaces words separated by the word separator character(s)
     * with camelCaps. If $isAction is false, it also preserves replaces words
     * separated by the path separation character with an underscore, making
     * the following word Title cased. All non-alphanumeric characters are
     * removed.
     *
     * @param string $unformatted
     * @param boolean $isAction Defaults to false
     * @return string
     */
    protected function _formatName($unformatted, $isAction = false)
    {
        // preserve directories
        if (!$isAction) {
            $segments = explode($this->getPathDelimiter(), $unformatted);
        } else {
            $segments = (array) $unformatted;
        }

        foreach ($segments as $key => $segment) {
            $segment        = str_replace($this->getWordDelimiter(), ' ', strtolower($segment));
            $segment        = preg_replace('/[^a-z0-9 ]/', '', $segment);
            $segments[$key] = str_replace(' ', '', ucwords($segment));
        }

        return implode('_', $segments);
    }

    /**
     * Retrieve front controller instance
     *
     * @return Zend_Controller_Front
     */
    public function getFrontController()
    {
        if (null === $this->_frontController) {
            // require_once 'Zend/Controller/Front.php';
            $this->_frontController = Zend_Controller_Front::getInstance();
        }

        return $this->_frontController;
    }

    /**
     * Set front controller instance
     *
     * @param Zend_Controller_Front $controller
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setFrontController(Zend_Controller_Front $controller)
    {
        $this->_frontController = $controller;
        return $this;
    }

    /**
     * Add or modify a parameter to use when instantiating an action controller
     *
     * @param string $name
     * @param mixed $value
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setParam($name, $value)
    {
        $name = (string) $name;
        $this->_invokeParams[$name] = $value;
        return $this;
    }

    /**
     * Set parameters to pass to action controller constructors
     *
     * @param array $params
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setParams(array $params)
    {
        $this->_invokeParams = array_merge($this->_invokeParams, $params);
        return $this;
    }

    /**
     * Retrieve a single parameter from the controller parameter stack
     *
     * @param string $name
     * @return mixed
     */
    public function getParam($name)
    {
        if(isset($this->_invokeParams[$name])) {
            return $this->_invokeParams[$name];
        }

        return null;
    }

    /**
     * Retrieve action controller instantiation parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_invokeParams;
    }

    /**
     * Clear the controller parameter stack
     *
     * By default, clears all parameters. If a parameter name is given, clears
     * only that parameter; if an array of parameter names is provided, clears
     * each.
     *
     * @param null|string|array single key or array of keys for params to clear
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function clearParams($name = null)
    {
        if (null === $name) {
            $this->_invokeParams = array();
        } elseif (is_string($name) && isset($this->_invokeParams[$name])) {
            unset($this->_invokeParams[$name]);
        } elseif (is_array($name)) {
            foreach ($name as $key) {
                if (is_string($key) && isset($this->_invokeParams[$key])) {
                    unset($this->_invokeParams[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * Set response object to pass to action controllers
     *
     * @param Zend_Controller_Response_Abstract|null $response
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setResponse(Zend_Controller_Response_Abstract $response = null)
    {
        $this->_response = $response;
        return $this;
    }

    /**
     * Return the registered response object
     *
     * @return Zend_Controller_Response_Abstract|null
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Set the default controller (minus any formatting)
     *
     * @param string $controller
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setDefaultControllerName($controller)
    {
        $this->_defaultController = (string) $controller;
        return $this;
    }

    /**
     * Retrieve the default controller name (minus formatting)
     *
     * @return string
     */
    public function getDefaultControllerName()
    {
        return $this->_defaultController;
    }

    /**
     * Set the default action (minus any formatting)
     *
     * @param string $action
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setDefaultAction($action)
    {
        $this->_defaultAction = (string) $action;
        return $this;
    }

    /**
     * Retrieve the default action name (minus formatting)
     *
     * @return string
     */
    public function getDefaultAction()
    {
        return $this->_defaultAction;
    }

    /**
     * Set the default module
     *
     * @param string $module
     * @return Zend_Controller_Dispatcher_Abstract
     */
    public function setDefaultModule($module)
    {
        $this->_defaultModule = (string) $module;
        return $this;
    }

    /**
     * Retrieve the default module
     *
     * @return string
     */
    public function getDefaultModule()
    {
        return $this->_defaultModule;
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Dispatcher
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Standard.php 20244 2010-01-12 21:12:56Z matthew $
 */

/** Zend_Loader */
// require_once 'Zend/Loader.php';

/** Zend_Controller_Dispatcher_Abstract */
// require_once 'Zend/Controller/Dispatcher/Abstract.php';

/**
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Dispatcher
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Controller_Dispatcher_Standard extends Zend_Controller_Dispatcher_Abstract
{
    /**
     * Current dispatchable directory
     * @var string
     */
    protected $_curDirectory;

    /**
     * Current module (formatted)
     * @var string
     */
    protected $_curModule;

    /**
     * Controller directory(ies)
     * @var array
     */
    protected $_controllerDirectory = array();

    /**
     * Constructor: Set current module to default value
     *
     * @param  array $params
     * @return void
     */
    public function __construct(array $params = array())
    {
        parent::__construct($params);
        $this->_curModule = $this->getDefaultModule();
    }

    /**
     * Add a single path to the controller directory stack
     *
     * @param string $path
     * @param string $module
     * @return Zend_Controller_Dispatcher_Standard
     */
    public function addControllerDirectory($path, $module = null)
    {
        if (null === $module) {
            $module = $this->_defaultModule;
        }

        $module = (string) $module;
        $path   = rtrim((string) $path, '/\\');

        $this->_controllerDirectory[$module] = $path;
        return $this;
    }

    /**
     * Set controller directory
     *
     * @param array|string $directory
     * @return Zend_Controller_Dispatcher_Standard
     */
    public function setControllerDirectory($directory, $module = null)
    {
        $this->_controllerDirectory = array();

        if (is_string($directory)) {
            $this->addControllerDirectory($directory, $module);
        } elseif (is_array($directory)) {
            foreach ((array) $directory as $module => $path) {
                $this->addControllerDirectory($path, $module);
            }
        } else {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Controller directory spec must be either a string or an array');
        }

        return $this;
    }

    /**
     * Return the currently set directories for Zend_Controller_Action class
     * lookup
     *
     * If a module is specified, returns just that directory.
     *
     * @param  string $module Module name
     * @return array|string Returns array of all directories by default, single
     * module directory if module argument provided
     */
    public function getControllerDirectory($module = null)
    {
        if (null === $module) {
            return $this->_controllerDirectory;
        }

        $module = (string) $module;
        if (array_key_exists($module, $this->_controllerDirectory)) {
            return $this->_controllerDirectory[$module];
        }

        return null;
    }

    /**
     * Remove a controller directory by module name
     *
     * @param  string $module
     * @return bool
     */
    public function removeControllerDirectory($module)
    {
        $module = (string) $module;
        if (array_key_exists($module, $this->_controllerDirectory)) {
            unset($this->_controllerDirectory[$module]);
            return true;
        }
        return false;
    }

    /**
     * Format the module name.
     *
     * @param string $unformatted
     * @return string
     */
    public function formatModuleName($unformatted)
    {
        if (($this->_defaultModule == $unformatted) && !$this->getParam('prefixDefaultModule')) {
            return $unformatted;
        }

        return ucfirst($this->_formatName($unformatted));
    }

    /**
     * Format action class name
     *
     * @param string $moduleName Name of the current module
     * @param string $className Name of the action class
     * @return string Formatted class name
     */
    public function formatClassName($moduleName, $className)
    {
        return $this->formatModuleName($moduleName) . '_' . $className;
    }

    /**
     * Convert a class name to a filename
     *
     * @param string $class
     * @return string
     */
    public function classToFilename($class)
    {
        return str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    }

    /**
     * Returns TRUE if the Zend_Controller_Request_Abstract object can be
     * dispatched to a controller.
     *
     * Use this method wisely. By default, the dispatcher will fall back to the
     * default controller (either in the module specified or the global default)
     * if a given controller does not exist. This method returning false does
     * not necessarily indicate the dispatcher will not still dispatch the call.
     *
     * @param Zend_Controller_Request_Abstract $action
     * @return boolean
     */
    public function isDispatchable(Zend_Controller_Request_Abstract $request)
    {
        $className = $this->getControllerClass($request);
        if (!$className) {
            return false;
        }

        if (class_exists($className, false)) {
            return true;
        }

        $fileSpec    = $this->classToFilename($className);
        $dispatchDir = $this->getDispatchDirectory();
        $test        = $dispatchDir . DIRECTORY_SEPARATOR . $fileSpec;
        return Zend_Loader::isReadable($test);
    }

    /**
     * Dispatch to a controller/action
     *
     * By default, if a controller is not dispatchable, dispatch() will throw
     * an exception. If you wish to use the default controller instead, set the
     * param 'useDefaultControllerAlways' via {@link setParam()}.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param Zend_Controller_Response_Abstract $response
     * @return void
     * @throws Zend_Controller_Dispatcher_Exception
     */
    public function dispatch(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response)
    {
        $this->setResponse($response);

        /**
         * Get controller class
         */
        if (!$this->isDispatchable($request)) {
            $controller = $request->getControllerName();
            if (!$this->getParam('useDefaultControllerAlways') && !empty($controller)) {
                // require_once 'Zend/Controller/Dispatcher/Exception.php';
                throw new Zend_Controller_Dispatcher_Exception('Invalid controller specified (' . $request->getControllerName() . ')');
            }

            $className = $this->getDefaultControllerClass($request);
        } else {
            $className = $this->getControllerClass($request);
            if (!$className) {
                $className = $this->getDefaultControllerClass($request);
            }
        }

        /**
         * Load the controller class file
         */
        $className = $this->loadClass($className);

        /**
         * Instantiate controller with request, response, and invocation
         * arguments; throw exception if it's not an action controller
         */
        $controller = new $className($request, $this->getResponse(), $this->getParams());
        if (!($controller instanceof Zend_Controller_Action_Interface) &&
            !($controller instanceof Zend_Controller_Action)) {
            // require_once 'Zend/Controller/Dispatcher/Exception.php';
            throw new Zend_Controller_Dispatcher_Exception(
                'Controller "' . $className . '" is not an instance of Zend_Controller_Action_Interface'
            );
        }

        /**
         * Retrieve the action name
         */
        $action = $this->getActionMethod($request);

        /**
         * Dispatch the method call
         */
        $request->setDispatched(true);

        // by default, buffer output
        $disableOb = $this->getParam('disableOutputBuffering');
        $obLevel   = ob_get_level();
        if (empty($disableOb)) {
            ob_start();
        }

        try {
            $controller->dispatch($action);
        } catch (Exception $e) {
            // Clean output buffer on error
            $curObLevel = ob_get_level();
            if ($curObLevel > $obLevel) {
                do {
                    ob_get_clean();
                    $curObLevel = ob_get_level();
                } while ($curObLevel > $obLevel);
            }
            throw $e;
        }

        if (empty($disableOb)) {
            $content = ob_get_clean();
            $response->appendBody($content);
        }

        // Destroy the page controller instance and reflection objects
        $controller = null;
    }

    /**
     * Load a controller class
     *
     * Attempts to load the controller class file from
     * {@link getControllerDirectory()}.  If the controller belongs to a
     * module, looks for the module prefix to the controller class.
     *
     * @param string $className
     * @return string Class name loaded
     * @throws Zend_Controller_Dispatcher_Exception if class not loaded
     */
    public function loadClass($className)
    {
        $finalClass  = $className;
        if (($this->_defaultModule != $this->_curModule)
            || $this->getParam('prefixDefaultModule'))
        {
            $finalClass = $this->formatClassName($this->_curModule, $className);
        }
        if (class_exists($finalClass, false)) {
            return $finalClass;
        }

        $dispatchDir = $this->getDispatchDirectory();
        $loadFile    = $dispatchDir . DIRECTORY_SEPARATOR . $this->classToFilename($className);

        if (Zend_Loader::isReadable($loadFile)) {
            include_once $loadFile;
        } else {
            // require_once 'Zend/Controller/Dispatcher/Exception.php';
            throw new Zend_Controller_Dispatcher_Exception('Cannot load controller class "' . $className . '" from file "' . $loadFile . "'");
        }

        if (!class_exists($finalClass, false)) {
            // require_once 'Zend/Controller/Dispatcher/Exception.php';
            throw new Zend_Controller_Dispatcher_Exception('Invalid controller class ("' . $finalClass . '")');
        }

        return $finalClass;
    }

    /**
     * Get controller class name
     *
     * Try request first; if not found, try pulling from request parameter;
     * if still not found, fallback to default
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return string|false Returns class name on success
     */
    public function getControllerClass(Zend_Controller_Request_Abstract $request)
    {
        $controllerName = $request->getControllerName();
        if (empty($controllerName)) {
            if (!$this->getParam('useDefaultControllerAlways')) {
                return false;
            }
            $controllerName = $this->getDefaultControllerName();
            $request->setControllerName($controllerName);
        }

        $className = $this->formatControllerName($controllerName);

        $controllerDirs      = $this->getControllerDirectory();
        $module = $request->getModuleName();
        if ($this->isValidModule($module)) {
            $this->_curModule    = $module;
            $this->_curDirectory = $controllerDirs[$module];
        } elseif ($this->isValidModule($this->_defaultModule)) {
            $request->setModuleName($this->_defaultModule);
            $this->_curModule    = $this->_defaultModule;
            $this->_curDirectory = $controllerDirs[$this->_defaultModule];
        } else {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('No default module defined for this application');
        }

        return $className;
    }

    /**
     * Determine if a given module is valid
     *
     * @param  string $module
     * @return bool
     */
    public function isValidModule($module)
    {
        if (!is_string($module)) {
            return false;
        }

        $module        = strtolower($module);
        $controllerDir = $this->getControllerDirectory();
        foreach (array_keys($controllerDir) as $moduleName) {
            if ($module == strtolower($moduleName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve default controller class
     *
     * Determines whether the default controller to use lies within the
     * requested module, or if the global default should be used.
     *
     * By default, will only use the module default unless that controller does
     * not exist; if this is the case, it falls back to the default controller
     * in the default module.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return string
     */
    public function getDefaultControllerClass(Zend_Controller_Request_Abstract $request)
    {
        $controller = $this->getDefaultControllerName();
        $default    = $this->formatControllerName($controller);
        $request->setControllerName($controller)
                ->setActionName(null);

        $module              = $request->getModuleName();
        $controllerDirs      = $this->getControllerDirectory();
        $this->_curModule    = $this->_defaultModule;
        $this->_curDirectory = $controllerDirs[$this->_defaultModule];
        if ($this->isValidModule($module)) {
            $found = false;
            if (class_exists($default, false)) {
                $found = true;
            } else {
                $moduleDir = $controllerDirs[$module];
                $fileSpec  = $moduleDir . DIRECTORY_SEPARATOR . $this->classToFilename($default);
                if (Zend_Loader::isReadable($fileSpec)) {
                    $found = true;
                    $this->_curDirectory = $moduleDir;
                }
            }
            if ($found) {
                $request->setModuleName($module);
                $this->_curModule    = $this->formatModuleName($module);
            }
        } else {
            $request->setModuleName($this->_defaultModule);
        }

        return $default;
    }

    /**
     * Return the value of the currently selected dispatch directory (as set by
     * {@link getController()})
     *
     * @return string
     */
    public function getDispatchDirectory()
    {
        return $this->_curDirectory;
    }

    /**
     * Determine the action name
     *
     * First attempt to retrieve from request; then from request params
     * using action key; default to default action
     *
     * Returns formatted action name
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return string
     */
    public function getActionMethod(Zend_Controller_Request_Abstract $request)
    {
        $action = $request->getActionName();
        if (empty($action)) {
            $action = $this->getDefaultAction();
            $request->setActionName($action);
        }

        return $this->formatActionName($action);
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Plugins
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Zend_Controller_Plugin_Abstract */
// require_once 'Zend/Controller/Plugin/Abstract.php';

/**
 * Handle exceptions that bubble up based on missing controllers, actions, or
 * application errors, and forward to an error handler.
 *
 * @uses       Zend_Controller_Plugin_Abstract
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Plugins
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ErrorHandler.php 20246 2010-01-12 21:36:08Z dasprid $
 */
class Zend_Controller_Plugin_ErrorHandler extends Zend_Controller_Plugin_Abstract
{
    /**
     * Const - No controller exception; controller does not exist
     */
    const EXCEPTION_NO_CONTROLLER = 'EXCEPTION_NO_CONTROLLER';

    /**
     * Const - No action exception; controller exists, but action does not
     */
    const EXCEPTION_NO_ACTION = 'EXCEPTION_NO_ACTION';

    /**
     * Const - No route exception; no routing was possible
     */
    const EXCEPTION_NO_ROUTE = 'EXCEPTION_NO_ROUTE';

    /**
     * Const - Other Exception; exceptions thrown by application controllers
     */
    const EXCEPTION_OTHER = 'EXCEPTION_OTHER';

    /**
     * Module to use for errors; defaults to default module in dispatcher
     * @var string
     */
    protected $_errorModule;

    /**
     * Controller to use for errors; defaults to 'error'
     * @var string
     */
    protected $_errorController = 'error';

    /**
     * Action to use for errors; defaults to 'error'
     * @var string
     */
    protected $_errorAction = 'error';

    /**
     * Flag; are we already inside the error handler loop?
     * @var bool
     */
    protected $_isInsideErrorHandlerLoop = false;

    /**
     * Exception count logged at first invocation of plugin
     * @var int
     */
    protected $_exceptionCountAtFirstEncounter = 0;

    /**
     * Constructor
     *
     * Options may include:
     * - module
     * - controller
     * - action
     *
     * @param  Array $options
     * @return void
     */
    public function __construct(Array $options = array())
    {
        $this->setErrorHandler($options);
    }

    /**
     * setErrorHandler() - setup the error handling options
     *
     * @param  array $options
     * @return Zend_Controller_Plugin_ErrorHandler
     */
    public function setErrorHandler(Array $options = array())
    {
        if (isset($options['module'])) {
            $this->setErrorHandlerModule($options['module']);
        }
        if (isset($options['controller'])) {
            $this->setErrorHandlerController($options['controller']);
        }
        if (isset($options['action'])) {
            $this->setErrorHandlerAction($options['action']);
        }
        return $this;
    }

    /**
     * Set the module name for the error handler
     *
     * @param  string $module
     * @return Zend_Controller_Plugin_ErrorHandler
     */
    public function setErrorHandlerModule($module)
    {
        $this->_errorModule = (string) $module;
        return $this;
    }

    /**
     * Retrieve the current error handler module
     *
     * @return string
     */
    public function getErrorHandlerModule()
    {
        if (null === $this->_errorModule) {
            $this->_errorModule = Zend_Controller_Front::getInstance()->getDispatcher()->getDefaultModule();
        }
        return $this->_errorModule;
    }

    /**
     * Set the controller name for the error handler
     *
     * @param  string $controller
     * @return Zend_Controller_Plugin_ErrorHandler
     */
    public function setErrorHandlerController($controller)
    {
        $this->_errorController = (string) $controller;
        return $this;
    }

    /**
     * Retrieve the current error handler controller
     *
     * @return string
     */
    public function getErrorHandlerController()
    {
        return $this->_errorController;
    }

    /**
     * Set the action name for the error handler
     *
     * @param  string $action
     * @return Zend_Controller_Plugin_ErrorHandler
     */
    public function setErrorHandlerAction($action)
    {
        $this->_errorAction = (string) $action;
        return $this;
    }

    /**
     * Retrieve the current error handler action
     *
     * @return string
     */
    public function getErrorHandlerAction()
    {
        return $this->_errorAction;
    }

    /**
     * Route shutdown hook -- Ccheck for router exceptions
     * 
     * @param Zend_Controller_Request_Abstract $request 
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        $this->_handleError($request);
    }

    /**
     * Post dispatch hook -- check for exceptions and dispatch error handler if
     * necessary
     *
     * @param Zend_Controller_Request_Abstract $request
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
        $this->_handleError($request);
    }

    /**
     * Handle errors and exceptions
     *
     * If the 'noErrorHandler' front controller flag has been set,
     * returns early.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    protected function _handleError(Zend_Controller_Request_Abstract $request)
    {
        $frontController = Zend_Controller_Front::getInstance();
        if ($frontController->getParam('noErrorHandler')) {
            return;
        }

        $response = $this->getResponse();

        if ($this->_isInsideErrorHandlerLoop) {
            $exceptions = $response->getException();
            if (count($exceptions) > $this->_exceptionCountAtFirstEncounter) {
                // Exception thrown by error handler; tell the front controller to throw it
                $frontController->throwExceptions(true);
                throw array_pop($exceptions);
            }
        }

        // check for an exception AND allow the error handler controller the option to forward
        if (($response->isException()) && (!$this->_isInsideErrorHandlerLoop)) {
            $this->_isInsideErrorHandlerLoop = true;

            // Get exception information
            $error            = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
            $exceptions       = $response->getException();
            $exception        = $exceptions[0];
            $exceptionType    = get_class($exception);
            $error->exception = $exception;
            switch ($exceptionType) {
                case 'Zend_Controller_Router_Exception':
                    if (404 == $exception->getCode()) {
                        $error->type = self::EXCEPTION_NO_ROUTE;
                    } else {
                        $error->type = self::EXCEPTION_OTHER;
                    }
                    break;
                case 'Zend_Controller_Dispatcher_Exception':
                    $error->type = self::EXCEPTION_NO_CONTROLLER;
                    break;
                case 'Zend_Controller_Action_Exception':
                    if (404 == $exception->getCode()) {
                        $error->type = self::EXCEPTION_NO_ACTION;
                    } else {
                        $error->type = self::EXCEPTION_OTHER;
                    }
                    break;
                default:
                    $error->type = self::EXCEPTION_OTHER;
                    break;
            }

            // Keep a copy of the original request
            $error->request = clone $request;

            // get a count of the number of exceptions encountered
            $this->_exceptionCountAtFirstEncounter = count($exceptions);

            // Forward to the error handler
            $request->setParam('error_handler', $error)
                    ->setModuleName($this->getErrorHandlerModule())
                    ->setControllerName($this->getErrorHandlerController())
                    ->setActionName($this->getErrorHandlerAction())
                    ->setDispatched(false);
        }
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Abstract.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Controller_Request_Abstract
{
    /**
     * Has the action been dispatched?
     * @var boolean
     */
    protected $_dispatched = false;

    /**
     * Module
     * @var string
     */
    protected $_module;

    /**
     * Module key for retrieving module from params
     * @var string
     */
    protected $_moduleKey = 'module';

    /**
     * Controller
     * @var string
     */
    protected $_controller;

    /**
     * Controller key for retrieving controller from params
     * @var string
     */
    protected $_controllerKey = 'controller';

    /**
     * Action
     * @var string
     */
    protected $_action;

    /**
     * Action key for retrieving action from params
     * @var string
     */
    protected $_actionKey = 'action';

    /**
     * Request parameters
     * @var array
     */
    protected $_params = array();

    /**
     * Retrieve the module name
     *
     * @return string
     */
    public function getModuleName()
    {
        if (null === $this->_module) {
            $this->_module = $this->getParam($this->getModuleKey());
        }

        return $this->_module;
    }

    /**
     * Set the module name to use
     *
     * @param string $value
     * @return Zend_Controller_Request_Abstract
     */
    public function setModuleName($value)
    {
        $this->_module = $value;
        return $this;
    }

    /**
     * Retrieve the controller name
     *
     * @return string
     */
    public function getControllerName()
    {
        if (null === $this->_controller) {
            $this->_controller = $this->getParam($this->getControllerKey());
        }

        return $this->_controller;
    }

    /**
     * Set the controller name to use
     *
     * @param string $value
     * @return Zend_Controller_Request_Abstract
     */
    public function setControllerName($value)
    {
        $this->_controller = $value;
        return $this;
    }

    /**
     * Retrieve the action name
     *
     * @return string
     */
    public function getActionName()
    {
        if (null === $this->_action) {
            $this->_action = $this->getParam($this->getActionKey());
        }

        return $this->_action;
    }

    /**
     * Set the action name
     *
     * @param string $value
     * @return Zend_Controller_Request_Abstract
     */
    public function setActionName($value)
    {
        $this->_action = $value;
        /**
         * @see ZF-3465
         */
        if (null === $value) {
            $this->setParam($this->getActionKey(), $value);
        }
        return $this;
    }

    /**
     * Retrieve the module key
     *
     * @return string
     */
    public function getModuleKey()
    {
        return $this->_moduleKey;
    }

    /**
     * Set the module key
     *
     * @param string $key
     * @return Zend_Controller_Request_Abstract
     */
    public function setModuleKey($key)
    {
        $this->_moduleKey = (string) $key;
        return $this;
    }

    /**
     * Retrieve the controller key
     *
     * @return string
     */
    public function getControllerKey()
    {
        return $this->_controllerKey;
    }

    /**
     * Set the controller key
     *
     * @param string $key
     * @return Zend_Controller_Request_Abstract
     */
    public function setControllerKey($key)
    {
        $this->_controllerKey = (string) $key;
        return $this;
    }

    /**
     * Retrieve the action key
     *
     * @return string
     */
    public function getActionKey()
    {
        return $this->_actionKey;
    }

    /**
     * Set the action key
     *
     * @param string $key
     * @return Zend_Controller_Request_Abstract
     */
    public function setActionKey($key)
    {
        $this->_actionKey = (string) $key;
        return $this;
    }

    /**
     * Get an action parameter
     *
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        $key = (string) $key;
        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        }

        return $default;
    }

    /**
     * Retrieve only user params (i.e, any param specific to the object and not the environment)
     *
     * @return array
     */
    public function getUserParams()
    {
        return $this->_params;
    }

    /**
     * Retrieve a single user param (i.e, a param specific to the object and not the environment)
     *
     * @param string $key
     * @param string $default Default value to use if key not found
     * @return mixed
     */
    public function getUserParam($key, $default = null)
    {
        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        }

        return $default;
    }

    /**
     * Set an action parameter
     *
     * A $value of null will unset the $key if it exists
     *
     * @param string $key
     * @param mixed $value
     * @return Zend_Controller_Request_Abstract
     */
    public function setParam($key, $value)
    {
        $key = (string) $key;

        if ((null === $value) && isset($this->_params[$key])) {
            unset($this->_params[$key]);
        } elseif (null !== $value) {
            $this->_params[$key] = $value;
        }

        return $this;
    }

    /**
     * Get all action parameters
     *
     * @return array
     */
     public function getParams()
     {
         return $this->_params;
     }

    /**
     * Set action parameters en masse; does not overwrite
     *
     * Null values will unset the associated key.
     *
     * @param array $array
     * @return Zend_Controller_Request_Abstract
     */
    public function setParams(array $array)
    {
        $this->_params = $this->_params + (array) $array;

        foreach ($this->_params as $key => $value) {
            if (null === $value) {
                unset($this->_params[$key]);
            }
        }

        return $this;
    }

    /**
     * Unset all user parameters
     *
     * @return Zend_Controller_Request_Abstract
     */
    public function clearParams()
    {
        $this->_params = array();
        return $this;
    }

    /**
     * Set flag indicating whether or not request has been dispatched
     *
     * @param boolean $flag
     * @return Zend_Controller_Request_Abstract
     */
    public function setDispatched($flag = true)
    {
        $this->_dispatched = $flag ? true : false;
        return $this;
    }

    /**
     * Determine if the request has been dispatched
     *
     * @return boolean
     */
    public function isDispatched()
    {
        return $this->_dispatched;
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Http.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/** @see Zend_Controller_Request_Abstract */
// require_once 'Zend/Controller/Request/Abstract.php';

/** @see Zend_Uri */
// require_once 'Zend/Uri.php';

/**
 * Zend_Controller_Request_Http
 *
 * HTTP request object for use with Zend_Controller family.
 *
 * @uses Zend_Controller_Request_Abstract
 * @package Zend_Controller
 * @subpackage Request
 */
class Zend_Controller_Request_Http extends Zend_Controller_Request_Abstract
{
    /**
     * Scheme for http
     *
     */
    const SCHEME_HTTP  = 'http';

    /**
     * Scheme for https
     *
     */
    const SCHEME_HTTPS = 'https';

    /**
     * Allowed parameter sources
     * @var array
     */
    protected $_paramSources = array('_GET', '_POST');

    /**
     * REQUEST_URI
     * @var string;
     */
    protected $_requestUri;

    /**
     * Base URL of request
     * @var string
     */
    protected $_baseUrl = null;

    /**
     * Base path of request
     * @var string
     */
    protected $_basePath = null;

    /**
     * PATH_INFO
     * @var string
     */
    protected $_pathInfo = '';

    /**
     * Instance parameters
     * @var array
     */
    protected $_params = array();

    /**
     * Raw request body
     * @var string|false
     */
    protected $_rawBody;

    /**
     * Alias keys for request parameters
     * @var array
     */
    protected $_aliases = array();

    /**
     * Constructor
     *
     * If a $uri is passed, the object will attempt to populate itself using
     * that information.
     *
     * @param string|Zend_Uri $uri
     * @return void
     * @throws Zend_Controller_Request_Exception when invalid URI passed
     */
    public function __construct($uri = null)
    {
        if (null !== $uri) {
            if (!$uri instanceof Zend_Uri) {
                $uri = Zend_Uri::factory($uri);
            }
            if ($uri->valid()) {
                $path  = $uri->getPath();
                $query = $uri->getQuery();
                if (!empty($query)) {
                    $path .= '?' . $query;
                }

                $this->setRequestUri($path);
            } else {
                // require_once 'Zend/Controller/Request/Exception.php';
                throw new Zend_Controller_Request_Exception('Invalid URI provided to constructor');
            }
        } else {
            $this->setRequestUri();
        }
    }

    /**
     * Access values contained in the superglobals as public members
     * Order of precedence: 1. GET, 2. POST, 3. COOKIE, 4. SERVER, 5. ENV
     *
     * @see http://msdn.microsoft.com/en-us/library/system.web.httprequest.item.aspx
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        switch (true) {
            case isset($this->_params[$key]):
                return $this->_params[$key];
            case isset($_GET[$key]):
                return $_GET[$key];
            case isset($_POST[$key]):
                return $_POST[$key];
            case isset($_COOKIE[$key]):
                return $_COOKIE[$key];
            case ($key == 'REQUEST_URI'):
                return $this->getRequestUri();
            case ($key == 'PATH_INFO'):
                return $this->getPathInfo();
            case isset($_SERVER[$key]):
                return $_SERVER[$key];
            case isset($_ENV[$key]):
                return $_ENV[$key];
            default:
                return null;
        }
    }

    /**
     * Alias to __get
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->__get($key);
    }

    /**
     * Set values
     *
     * In order to follow {@link __get()}, which operates on a number of
     * superglobals, setting values through overloading is not allowed and will
     * raise an exception. Use setParam() instead.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws Zend_Controller_Request_Exception
     */
    public function __set($key, $value)
    {
        // require_once 'Zend/Controller/Request/Exception.php';
        throw new Zend_Controller_Request_Exception('Setting values in superglobals not allowed; please use setParam()');
    }

    /**
     * Alias to __set()
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set($key, $value)
    {
        return $this->__set($key, $value);
    }

    /**
     * Check to see if a property is set
     *
     * @param string $key
     * @return boolean
     */
    public function __isset($key)
    {
        switch (true) {
            case isset($this->_params[$key]):
                return true;
            case isset($_GET[$key]):
                return true;
            case isset($_POST[$key]):
                return true;
            case isset($_COOKIE[$key]):
                return true;
            case isset($_SERVER[$key]):
                return true;
            case isset($_ENV[$key]):
                return true;
            default:
                return false;
        }
    }

    /**
     * Alias to __isset()
     *
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        return $this->__isset($key);
    }

    /**
     * Set GET values
     *
     * @param  string|array $spec
     * @param  null|mixed $value
     * @return Zend_Controller_Request_Http
     */
    public function setQuery($spec, $value = null)
    {
        if ((null === $value) && !is_array($spec)) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Invalid value passed to setQuery(); must be either array of values or key/value pair');
        }
        if ((null === $value) && is_array($spec)) {
            foreach ($spec as $key => $value) {
                $this->setQuery($key, $value);
            }
            return $this;
        }
        $_GET[(string) $spec] = $value;
        return $this;
    }

    /**
     * Retrieve a member of the $_GET superglobal
     *
     * If no $key is passed, returns the entire $_GET array.
     *
     * @todo How to retrieve from nested arrays
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getQuery($key = null, $default = null)
    {
        if (null === $key) {
            return $_GET;
        }

        return (isset($_GET[$key])) ? $_GET[$key] : $default;
    }

    /**
     * Set POST values
     *
     * @param  string|array $spec
     * @param  null|mixed $value
     * @return Zend_Controller_Request_Http
     */
    public function setPost($spec, $value = null)
    {
        if ((null === $value) && !is_array($spec)) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Invalid value passed to setPost(); must be either array of values or key/value pair');
        }
        if ((null === $value) && is_array($spec)) {
            foreach ($spec as $key => $value) {
                $this->setPost($key, $value);
            }
            return $this;
        }
        $_POST[(string) $spec] = $value;
        return $this;
    }

    /**
     * Retrieve a member of the $_POST superglobal
     *
     * If no $key is passed, returns the entire $_POST array.
     *
     * @todo How to retrieve from nested arrays
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getPost($key = null, $default = null)
    {
        if (null === $key) {
            return $_POST;
        }

        return (isset($_POST[$key])) ? $_POST[$key] : $default;
    }

    /**
     * Retrieve a member of the $_COOKIE superglobal
     *
     * If no $key is passed, returns the entire $_COOKIE array.
     *
     * @todo How to retrieve from nested arrays
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getCookie($key = null, $default = null)
    {
        if (null === $key) {
            return $_COOKIE;
        }

        return (isset($_COOKIE[$key])) ? $_COOKIE[$key] : $default;
    }

    /**
     * Retrieve a member of the $_SERVER superglobal
     *
     * If no $key is passed, returns the entire $_SERVER array.
     *
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getServer($key = null, $default = null)
    {
        if (null === $key) {
            return $_SERVER;
        }

        return (isset($_SERVER[$key])) ? $_SERVER[$key] : $default;
    }

    /**
     * Retrieve a member of the $_ENV superglobal
     *
     * If no $key is passed, returns the entire $_ENV array.
     *
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public function getEnv($key = null, $default = null)
    {
        if (null === $key) {
            return $_ENV;
        }

        return (isset($_ENV[$key])) ? $_ENV[$key] : $default;
    }

    /**
     * Set the REQUEST_URI on which the instance operates
     *
     * If no request URI is passed, uses the value in $_SERVER['REQUEST_URI'],
     * $_SERVER['HTTP_X_REWRITE_URL'], or $_SERVER['ORIG_PATH_INFO'] + $_SERVER['QUERY_STRING'].
     *
     * @param string $requestUri
     * @return Zend_Controller_Request_Http
     */
    public function setRequestUri($requestUri = null)
    {
        if ($requestUri === null) {
            if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
                $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
            } elseif (
                // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
                isset($_SERVER['IIS_WasUrlRewritten'])
                && $_SERVER['IIS_WasUrlRewritten'] == '1'
                && isset($_SERVER['UNENCODED_URL'])
                && $_SERVER['UNENCODED_URL'] != ''
                ) {
                $requestUri = $_SERVER['UNENCODED_URL'];
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $requestUri = $_SERVER['REQUEST_URI'];
                // Http proxy reqs setup request uri with scheme and host [and port] + the url path, only use url path
                $schemeAndHttpHost = $this->getScheme() . '://' . $this->getHttpHost();
                if (strpos($requestUri, $schemeAndHttpHost) === 0) {
                    $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
                }
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
                $requestUri = $_SERVER['ORIG_PATH_INFO'];
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $requestUri .= '?' . $_SERVER['QUERY_STRING'];
                }
            } else {
                return $this;
            }
        } elseif (!is_string($requestUri)) {
            return $this;
        } else {
            // Set GET items, if available
            if (false !== ($pos = strpos($requestUri, '?'))) {
                // Get key => value pairs and set $_GET
                $query = substr($requestUri, $pos + 1);
                parse_str($query, $vars);
                $this->setQuery($vars);
            }
        }

        $this->_requestUri = $requestUri;
        return $this;
    }

    /**
     * Returns the REQUEST_URI taking into account
     * platform differences between Apache and IIS
     *
     * @return string
     */
    public function getRequestUri()
    {
        if (empty($this->_requestUri)) {
            $this->setRequestUri();
        }

        return $this->_requestUri;
    }

    /**
     * Set the base URL of the request; i.e., the segment leading to the script name
     *
     * E.g.:
     * - /admin
     * - /myapp
     * - /subdir/index.php
     *
     * Do not use the full URI when providing the base. The following are
     * examples of what not to use:
     * - http://example.com/admin (should be just /admin)
     * - http://example.com/subdir/index.php (should be just /subdir/index.php)
     *
     * If no $baseUrl is provided, attempts to determine the base URL from the
     * environment, using SCRIPT_FILENAME, SCRIPT_NAME, PHP_SELF, and
     * ORIG_SCRIPT_NAME in its determination.
     *
     * @param mixed $baseUrl
     * @return Zend_Controller_Request_Http
     */
    public function setBaseUrl($baseUrl = null)
    {
        if ((null !== $baseUrl) && !is_string($baseUrl)) {
            return $this;
        }

        if ($baseUrl === null) {
            $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';

            if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
                $baseUrl = $_SERVER['SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
                $baseUrl = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
                $baseUrl = $_SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
            } else {
                // Backtrack up the script_filename to find the portion matching
                // php_self
                $path    = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
                $file    = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
                $segs    = explode('/', trim($file, '/'));
                $segs    = array_reverse($segs);
                $index   = 0;
                $last    = count($segs);
                $baseUrl = '';
                do {
                    $seg     = $segs[$index];
                    $baseUrl = '/' . $seg . $baseUrl;
                    ++$index;
                } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
            }

            // Does the baseUrl have anything in common with the request_uri?
            $requestUri = $this->getRequestUri();

            if (0 === strpos($requestUri, $baseUrl)) {
                // full $baseUrl matches
                $this->_baseUrl = $baseUrl;
                return $this;
            }

            if (0 === strpos($requestUri, dirname($baseUrl))) {
                // directory portion of $baseUrl matches
                $this->_baseUrl = rtrim(dirname($baseUrl), '/');
                return $this;
            }

            $truncatedRequestUri = $requestUri;
            if (($pos = strpos($requestUri, '?')) !== false) {
                $truncatedRequestUri = substr($requestUri, 0, $pos);
            }

            $basename = basename($baseUrl);
            if (empty($basename) || !strpos($truncatedRequestUri, $basename)) {
                // no match whatsoever; set it blank
                $this->_baseUrl = '';
                return $this;
            }

            // If using mod_rewrite or ISAPI_Rewrite strip the script filename
            // out of baseUrl. $pos !== 0 makes sure it is not matching a value
            // from PATH_INFO or QUERY_STRING
            if ((strlen($requestUri) >= strlen($baseUrl))
                && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0)))
            {
                $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
            }
        }

        $this->_baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    /**
     * Everything in REQUEST_URI before PATH_INFO
     * <form action="<?=$baseUrl?>/news/submit" method="POST"/>

     *
     * @return string
     */
    public function getBaseUrl()
    {
        if (null === $this->_baseUrl) {
            $this->setBaseUrl();
        }

        return $this->_baseUrl;
    }

    /**
     * Set the base path for the URL
     *
     * @param string|null $basePath
     * @return Zend_Controller_Request_Http
     */
    public function setBasePath($basePath = null)
    {
        if ($basePath === null) {
            $filename = (isset($_SERVER['SCRIPT_FILENAME']))
                      ? basename($_SERVER['SCRIPT_FILENAME'])
                      : '';

            $baseUrl = $this->getBaseUrl();
            if (empty($baseUrl)) {
                $this->_basePath = '';
                return $this;
            }

            if (basename($baseUrl) === $filename) {
                $basePath = dirname($baseUrl);
            } else {
                $basePath = $baseUrl;
            }
        }

        if (substr(PHP_OS, 0, 3) === 'WIN') {
            $basePath = str_replace('\\', '/', $basePath);
        }

        $this->_basePath = rtrim($basePath, '/');
        return $this;
    }

    /**
     * Everything in REQUEST_URI before PATH_INFO not including the filename
     * <img src="<?=$basePath?>/images/zend.png"/>
     *
     * @return string
     */
    public function getBasePath()
    {
        if (null === $this->_basePath) {
            $this->setBasePath();
        }

        return $this->_basePath;
    }

    /**
     * Set the PATH_INFO string
     *
     * @param string|null $pathInfo
     * @return Zend_Controller_Request_Http
     */
    public function setPathInfo($pathInfo = null)
    {
        if ($pathInfo === null) {
            $baseUrl = $this->getBaseUrl();

            if (null === ($requestUri = $this->getRequestUri())) {
                return $this;
            }

            // Remove the query string from REQUEST_URI
            if ($pos = strpos($requestUri, '?')) {
                $requestUri = substr($requestUri, 0, $pos);
            }

            if ((null !== $baseUrl)
                && (false === ($pathInfo = substr($requestUri, strlen($baseUrl)))))
            {
                // If substr() returns false then PATH_INFO is set to an empty string
                $pathInfo = '';
            } elseif (null === $baseUrl) {
                $pathInfo = $requestUri;
            }
        }

        $this->_pathInfo = (string) $pathInfo;
        return $this;
    }

    /**
     * Returns everything between the BaseUrl and QueryString.
     * This value is calculated instead of reading PATH_INFO
     * directly from $_SERVER due to cross-platform differences.
     *
     * @return string
     */
    public function getPathInfo()
    {
        if (empty($this->_pathInfo)) {
            $this->setPathInfo();
        }

        return $this->_pathInfo;
    }

    /**
     * Set allowed parameter sources
     *
     * Can be empty array, or contain one or more of '_GET' or '_POST'.
     *
     * @param  array $paramSoures
     * @return Zend_Controller_Request_Http
     */
    public function setParamSources(array $paramSources = array())
    {
        $this->_paramSources = $paramSources;
        return $this;
    }

    /**
     * Get list of allowed parameter sources
     *
     * @return array
     */
    public function getParamSources()
    {
        return $this->_paramSources;
    }

    /**
     * Set a userland parameter
     *
     * Uses $key to set a userland parameter. If $key is an alias, the actual
     * key will be retrieved and used to set the parameter.
     *
     * @param mixed $key
     * @param mixed $value
     * @return Zend_Controller_Request_Http
     */
    public function setParam($key, $value)
    {
        $key = (null !== ($alias = $this->getAlias($key))) ? $alias : $key;
        parent::setParam($key, $value);
        return $this;
    }

    /**
     * Retrieve a parameter
     *
     * Retrieves a parameter from the instance. Priority is in the order of
     * userland parameters (see {@link setParam()}), $_GET, $_POST. If a
     * parameter matching the $key is not found, null is returned.
     *
     * If the $key is an alias, the actual key aliased will be used.
     *
     * @param mixed $key
     * @param mixed $default Default value to use if key not found
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        $keyName = (null !== ($alias = $this->getAlias($key))) ? $alias : $key;

        $paramSources = $this->getParamSources();
        if (isset($this->_params[$keyName])) {
            return $this->_params[$keyName];
        } elseif (in_array('_GET', $paramSources) && (isset($_GET[$keyName]))) {
            return $_GET[$keyName];
        } elseif (in_array('_POST', $paramSources) && (isset($_POST[$keyName]))) {
            return $_POST[$keyName];
        }

        return $default;
    }

    /**
     * Retrieve an array of parameters
     *
     * Retrieves a merged array of parameters, with precedence of userland
     * params (see {@link setParam()}), $_GET, $_POST (i.e., values in the
     * userland params will take precedence over all others).
     *
     * @return array
     */
    public function getParams()
    {
        $return       = $this->_params;
        $paramSources = $this->getParamSources();
        if (in_array('_GET', $paramSources)
            && isset($_GET)
            && is_array($_GET)
        ) {
            $return += $_GET;
        }
        if (in_array('_POST', $paramSources)
            && isset($_POST)
            && is_array($_POST)
        ) {
            $return += $_POST;
        }
        return $return;
    }

    /**
     * Set parameters
     *
     * Set one or more parameters. Parameters are set as userland parameters,
     * using the keys specified in the array.
     *
     * @param array $params
     * @return Zend_Controller_Request_Http
     */
    public function setParams(array $params)
    {
        foreach ($params as $key => $value) {
            $this->setParam($key, $value);
        }
        return $this;
    }

    /**
     * Set a key alias
     *
     * Set an alias used for key lookups. $name specifies the alias, $target
     * specifies the actual key to use.
     *
     * @param string $name
     * @param string $target
     * @return Zend_Controller_Request_Http
     */
    public function setAlias($name, $target)
    {
        $this->_aliases[$name] = $target;
        return $this;
    }

    /**
     * Retrieve an alias
     *
     * Retrieve the actual key represented by the alias $name.
     *
     * @param string $name
     * @return string|null Returns null when no alias exists
     */
    public function getAlias($name)
    {
        if (isset($this->_aliases[$name])) {
            return $this->_aliases[$name];
        }

        return null;
    }

    /**
     * Retrieve the list of all aliases
     *
     * @return array
     */
    public function getAliases()
    {
        return $this->_aliases;
    }

    /**
     * Return the method by which the request was made
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->getServer('REQUEST_METHOD');
    }

    /**
     * Was the request made by POST?
     *
     * @return boolean
     */
    public function isPost()
    {
        if ('POST' == $this->getMethod()) {
            return true;
        }

        return false;
    }

    /**
     * Was the request made by GET?
     *
     * @return boolean
     */
    public function isGet()
    {
        if ('GET' == $this->getMethod()) {
            return true;
        }

        return false;
    }

    /**
     * Was the request made by PUT?
     *
     * @return boolean
     */
    public function isPut()
    {
        if ('PUT' == $this->getMethod()) {
            return true;
        }

        return false;
    }

    /**
     * Was the request made by DELETE?
     *
     * @return boolean
     */
    public function isDelete()
    {
        if ('DELETE' == $this->getMethod()) {
            return true;
        }

        return false;
    }

    /**
     * Was the request made by HEAD?
     *
     * @return boolean
     */
    public function isHead()
    {
        if ('HEAD' == $this->getMethod()) {
            return true;
        }

        return false;
    }

    /**
     * Was the request made by OPTIONS?
     *
     * @return boolean
     */
    public function isOptions()
    {
        if ('OPTIONS' == $this->getMethod()) {
            return true;
        }

        return false;
    }

    /**
     * Is the request a Javascript XMLHttpRequest?
     *
     * Should work with Prototype/Script.aculo.us, possibly others.
     *
     * @return boolean
     */
    public function isXmlHttpRequest()
    {
        return ($this->getHeader('X_REQUESTED_WITH') == 'XMLHttpRequest');
    }

    /**
     * Is this a Flash request?
     *
     * @return boolean
     */
    public function isFlashRequest()
    {
        $header = strtolower($this->getHeader('USER_AGENT'));
        return (strstr($header, ' flash')) ? true : false;
    }

    /**
     * Is https secure request
     *
     * @return boolean
     */
    public function isSecure()
    {
        return ($this->getScheme() === self::SCHEME_HTTPS);
    }

    /**
     * Return the raw body of the request, if present
     *
     * @return string|false Raw body, or false if not present
     */
    public function getRawBody()
    {
        if (null === $this->_rawBody) {
            $body = file_get_contents('php://input');

            if (strlen(trim($body)) > 0) {
                $this->_rawBody = $body;
            } else {
                $this->_rawBody = false;
            }
        }
        return $this->_rawBody;
    }

    /**
     * Return the value of the given HTTP header. Pass the header name as the
     * plain, HTTP-specified header name. Ex.: Ask for 'Accept' to get the
     * Accept header, 'Accept-Encoding' to get the Accept-Encoding header.
     *
     * @param string $header HTTP header name
     * @return string|false HTTP header value, or false if not found
     * @throws Zend_Controller_Request_Exception
     */
    public function getHeader($header)
    {
        if (empty($header)) {
            // require_once 'Zend/Controller/Request/Exception.php';
            throw new Zend_Controller_Request_Exception('An HTTP header name is required');
        }

        // Try to get it from the $_SERVER array first
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (!empty($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }

        // This seems to be the only way to get the Authorization header on
        // Apache
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers[$header])) {
                return $headers[$header];
            }
        }

        return false;
    }

    /**
     * Get the request URI scheme
     *
     * @return string
     */
    public function getScheme()
    {
        return ($this->getServer('HTTPS') == 'on') ? self::SCHEME_HTTPS : self::SCHEME_HTTP;
    }

    /**
     * Get the HTTP host.
     *
     * "Host" ":" host [ ":" port ] ; Section 3.2.2
     * Note the HTTP Host header is not the same as the URI host.
     * It includes the port while the URI host doesn't.
     *
     * @return string
     */
    public function getHttpHost()
    {
        $host = $this->getServer('HTTP_HOST');
        if (!empty($host)) {
            return $host;
        }

        $scheme = $this->getScheme();
        $name   = $this->getServer('SERVER_NAME');
        $port   = $this->getServer('SERVER_PORT');

        if (($scheme == self::SCHEME_HTTP && $port == 80) || ($scheme == self::SCHEME_HTTPS && $port == 443)) {
            return $name;
        } else {
            return $name . ':' . $port;
        }
    }

    /**
     * Get the client's IP addres
     *
     * @param  boolean $checkProxy
     * @return string
     */
    public function getClientIp($checkProxy = true)
    {
        if ($checkProxy && $this->getServer('HTTP_CLIENT_IP') != null) {
            $ip = $this->getServer('HTTP_CLIENT_IP');
        } else if ($checkProxy && $this->getServer('HTTP_X_FORWARDED_FOR') != null) {
            $ip = $this->getServer('HTTP_X_FORWARDED_FOR');
        } else {
            $ip = $this->getServer('REMOTE_ADDR');
        }

        return $ip;
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Abstract.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * Zend_Controller_Response_Abstract
 *
 * Base class for Zend_Controller responses
 *
 * @package Zend_Controller
 * @subpackage Response
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Controller_Response_Abstract
{
    /**
     * Body content
     * @var array
     */
    protected $_body = array();

    /**
     * Exception stack
     * @var Exception
     */
    protected $_exceptions = array();

    /**
     * Array of headers. Each header is an array with keys 'name' and 'value'
     * @var array
     */
    protected $_headers = array();

    /**
     * Array of raw headers. Each header is a single string, the entire header to emit
     * @var array
     */
    protected $_headersRaw = array();

    /**
     * HTTP response code to use in headers
     * @var int
     */
    protected $_httpResponseCode = 200;

    /**
     * Flag; is this response a redirect?
     * @var boolean
     */
    protected $_isRedirect = false;

    /**
     * Whether or not to render exceptions; off by default
     * @var boolean
     */
    protected $_renderExceptions = false;

    /**
     * Flag; if true, when header operations are called after headers have been
     * sent, an exception will be raised; otherwise, processing will continue
     * as normal. Defaults to true.
     *
     * @see canSendHeaders()
     * @var boolean
     */
    public $headersSentThrowsException = true;

    /**
     * Normalize a header name
     *
     * Normalizes a header name to X-Capitalized-Names
     *
     * @param  string $name
     * @return string
     */
    protected function _normalizeHeader($name)
    {
        $filtered = str_replace(array('-', '_'), ' ', (string) $name);
        $filtered = ucwords(strtolower($filtered));
        $filtered = str_replace(' ', '-', $filtered);
        return $filtered;
    }

    /**
     * Set a header
     *
     * If $replace is true, replaces any headers already defined with that
     * $name.
     *
     * @param string $name
     * @param string $value
     * @param boolean $replace
     * @return Zend_Controller_Response_Abstract
     */
    public function setHeader($name, $value, $replace = false)
    {
        $this->canSendHeaders(true);
        $name  = $this->_normalizeHeader($name);
        $value = (string) $value;

        if ($replace) {
            foreach ($this->_headers as $key => $header) {
                if ($name == $header['name']) {
                    unset($this->_headers[$key]);
                }
            }
        }

        $this->_headers[] = array(
            'name'    => $name,
            'value'   => $value,
            'replace' => $replace
        );

        return $this;
    }

    /**
     * Set redirect URL
     *
     * Sets Location header and response code. Forces replacement of any prior
     * redirects.
     *
     * @param string $url
     * @param int $code
     * @return Zend_Controller_Response_Abstract
     */
    public function setRedirect($url, $code = 302)
    {
        $this->canSendHeaders(true);
        $this->setHeader('Location', $url, true)
             ->setHttpResponseCode($code);

        return $this;
    }

    /**
     * Is this a redirect?
     *
     * @return boolean
     */
    public function isRedirect()
    {
        return $this->_isRedirect;
    }

    /**
     * Return array of headers; see {@link $_headers} for format
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * Clear headers
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function clearHeaders()
    {
        $this->_headers = array();

        return $this;
    }

	/**
	 * Clears the specified HTTP header
	 *
	 * @param  string $name
	 * @return Zend_Controller_Response_Abstract
	 */
	public function clearHeader($name)
	{
		if (! count($this->_headers)) {
			return $this;
		}

		foreach ($this->_headers as $index => $header) {
			if ($name == $header['name']) {
				unset($this->_headers[$index]);
			}
		}

		return $this;
	}

    /**
     * Set raw HTTP header
     *
     * Allows setting non key => value headers, such as status codes
     *
     * @param string $value
     * @return Zend_Controller_Response_Abstract
     */
    public function setRawHeader($value)
    {
        $this->canSendHeaders(true);
        if ('Location' == substr($value, 0, 8)) {
            $this->_isRedirect = true;
        }
        $this->_headersRaw[] = (string) $value;
        return $this;
    }

    /**
     * Retrieve all {@link setRawHeader() raw HTTP headers}
     *
     * @return array
     */
    public function getRawHeaders()
    {
        return $this->_headersRaw;
    }

    /**
     * Clear all {@link setRawHeader() raw HTTP headers}
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function clearRawHeaders()
    {
        $this->_headersRaw = array();
        return $this;
    }

	/**
	 * Clears the specified raw HTTP header
	 *
	 * @param  string $headerRaw
	 * @return Zend_Controller_Response_Abstract
	 */
	public function clearRawHeader($headerRaw)
	{
		if (! count($this->_headersRaw)) {
			return $this;
		}

		$key = array_search($headerRaw, $this->_headersRaw);
		unset($this->_headersRaw[$key]);

		return $this;
	}

    /**
     * Clear all headers, normal and raw
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function clearAllHeaders()
    {
        return $this->clearHeaders()
                    ->clearRawHeaders();
    }

    /**
     * Set HTTP response code to use with headers
     *
     * @param int $code
     * @return Zend_Controller_Response_Abstract
     */
    public function setHttpResponseCode($code)
    {
        if (!is_int($code) || (100 > $code) || (599 < $code)) {
            // require_once 'Zend/Controller/Response/Exception.php';
            throw new Zend_Controller_Response_Exception('Invalid HTTP response code');
        }

        if ((300 <= $code) && (307 >= $code)) {
            $this->_isRedirect = true;
        } else {
            $this->_isRedirect = false;
        }

        $this->_httpResponseCode = $code;
        return $this;
    }

    /**
     * Retrieve HTTP response code
     *
     * @return int
     */
    public function getHttpResponseCode()
    {
        return $this->_httpResponseCode;
    }

    /**
     * Can we send headers?
     *
     * @param boolean $throw Whether or not to throw an exception if headers have been sent; defaults to false
     * @return boolean
     * @throws Zend_Controller_Response_Exception
     */
    public function canSendHeaders($throw = false)
    {
        $ok = headers_sent($file, $line);
        if ($ok && $throw && $this->headersSentThrowsException) {
            // require_once 'Zend/Controller/Response/Exception.php';
            throw new Zend_Controller_Response_Exception('Cannot send headers; headers already sent in ' . $file . ', line ' . $line);
        }

        return !$ok;
    }

    /**
     * Send all headers
     *
     * Sends any headers specified. If an {@link setHttpResponseCode() HTTP response code}
     * has been specified, it is sent with the first header.
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function sendHeaders()
    {
        // Only check if we can send headers if we have headers to send
        if (count($this->_headersRaw) || count($this->_headers) || (200 != $this->_httpResponseCode)) {
            $this->canSendHeaders(true);
        } elseif (200 == $this->_httpResponseCode) {
            // Haven't changed the response code, and we have no headers
            return $this;
        }

        $httpCodeSent = false;

        foreach ($this->_headersRaw as $header) {
            if (!$httpCodeSent && $this->_httpResponseCode) {
                header($header, true, $this->_httpResponseCode);
                $httpCodeSent = true;
            } else {
                header($header);
            }
        }

        foreach ($this->_headers as $header) {
            if (!$httpCodeSent && $this->_httpResponseCode) {
                header($header['name'] . ': ' . $header['value'], $header['replace'], $this->_httpResponseCode);
                $httpCodeSent = true;
            } else {
                header($header['name'] . ': ' . $header['value'], $header['replace']);
            }
        }

        if (!$httpCodeSent) {
            header('HTTP/1.1 ' . $this->_httpResponseCode);
            $httpCodeSent = true;
        }

        return $this;
    }

    /**
     * Set body content
     *
     * If $name is not passed, or is not a string, resets the entire body and
     * sets the 'default' key to $content.
     *
     * If $name is a string, sets the named segment in the body array to
     * $content.
     *
     * @param string $content
     * @param null|string $name
     * @return Zend_Controller_Response_Abstract
     */
    public function setBody($content, $name = null)
    {
        if ((null === $name) || !is_string($name)) {
            $this->_body = array('default' => (string) $content);
        } else {
            $this->_body[$name] = (string) $content;
        }

        return $this;
    }

    /**
     * Append content to the body content
     *
     * @param string $content
     * @param null|string $name
     * @return Zend_Controller_Response_Abstract
     */
    public function appendBody($content, $name = null)
    {
        if ((null === $name) || !is_string($name)) {
            if (isset($this->_body['default'])) {
                $this->_body['default'] .= (string) $content;
            } else {
                return $this->append('default', $content);
            }
        } elseif (isset($this->_body[$name])) {
            $this->_body[$name] .= (string) $content;
        } else {
            return $this->append($name, $content);
        }

        return $this;
    }

    /**
     * Clear body array
     *
     * With no arguments, clears the entire body array. Given a $name, clears
     * just that named segment; if no segment matching $name exists, returns
     * false to indicate an error.
     *
     * @param  string $name Named segment to clear
     * @return boolean
     */
    public function clearBody($name = null)
    {
        if (null !== $name) {
            $name = (string) $name;
            if (isset($this->_body[$name])) {
                unset($this->_body[$name]);
                return true;
            }

            return false;
        }

        $this->_body = array();
        return true;
    }

    /**
     * Return the body content
     *
     * If $spec is false, returns the concatenated values of the body content
     * array. If $spec is boolean true, returns the body content array. If
     * $spec is a string and matches a named segment, returns the contents of
     * that segment; otherwise, returns null.
     *
     * @param boolean $spec
     * @return string|array|null
     */
    public function getBody($spec = false)
    {
        if (false === $spec) {
            ob_start();
            $this->outputBody();
            return ob_get_clean();
        } elseif (true === $spec) {
            return $this->_body;
        } elseif (is_string($spec) && isset($this->_body[$spec])) {
            return $this->_body[$spec];
        }

        return null;
    }

    /**
     * Append a named body segment to the body content array
     *
     * If segment already exists, replaces with $content and places at end of
     * array.
     *
     * @param string $name
     * @param string $content
     * @return Zend_Controller_Response_Abstract
     */
    public function append($name, $content)
    {
        if (!is_string($name)) {
            // require_once 'Zend/Controller/Response/Exception.php';
            throw new Zend_Controller_Response_Exception('Invalid body segment key ("' . gettype($name) . '")');
        }

        if (isset($this->_body[$name])) {
            unset($this->_body[$name]);
        }
        $this->_body[$name] = (string) $content;
        return $this;
    }

    /**
     * Prepend a named body segment to the body content array
     *
     * If segment already exists, replaces with $content and places at top of
     * array.
     *
     * @param string $name
     * @param string $content
     * @return void
     */
    public function prepend($name, $content)
    {
        if (!is_string($name)) {
            // require_once 'Zend/Controller/Response/Exception.php';
            throw new Zend_Controller_Response_Exception('Invalid body segment key ("' . gettype($name) . '")');
        }

        if (isset($this->_body[$name])) {
            unset($this->_body[$name]);
        }

        $new = array($name => (string) $content);
        $this->_body = $new + $this->_body;

        return $this;
    }

    /**
     * Insert a named segment into the body content array
     *
     * @param  string $name
     * @param  string $content
     * @param  string $parent
     * @param  boolean $before Whether to insert the new segment before or
     * after the parent. Defaults to false (after)
     * @return Zend_Controller_Response_Abstract
     */
    public function insert($name, $content, $parent = null, $before = false)
    {
        if (!is_string($name)) {
            // require_once 'Zend/Controller/Response/Exception.php';
            throw new Zend_Controller_Response_Exception('Invalid body segment key ("' . gettype($name) . '")');
        }

        if ((null !== $parent) && !is_string($parent)) {
            // require_once 'Zend/Controller/Response/Exception.php';
            throw new Zend_Controller_Response_Exception('Invalid body segment parent key ("' . gettype($parent) . '")');
        }

        if (isset($this->_body[$name])) {
            unset($this->_body[$name]);
        }

        if ((null === $parent) || !isset($this->_body[$parent])) {
            return $this->append($name, $content);
        }

        $ins  = array($name => (string) $content);
        $keys = array_keys($this->_body);
        $loc  = array_search($parent, $keys);
        if (!$before) {
            // Increment location if not inserting before
            ++$loc;
        }

        if (0 === $loc) {
            // If location of key is 0, we're prepending
            $this->_body = $ins + $this->_body;
        } elseif ($loc >= (count($this->_body))) {
            // If location of key is maximal, we're appending
            $this->_body = $this->_body + $ins;
        } else {
            // Otherwise, insert at location specified
            $pre  = array_slice($this->_body, 0, $loc);
            $post = array_slice($this->_body, $loc);
            $this->_body = $pre + $ins + $post;
        }

        return $this;
    }

    /**
     * Echo the body segments
     *
     * @return void
     */
    public function outputBody()
    {
        $body = implode('', $this->_body);
        echo $body;
    }

    /**
     * Register an exception with the response
     *
     * @param Exception $e
     * @return Zend_Controller_Response_Abstract
     */
    public function setException(Exception $e)
    {
        $this->_exceptions[] = $e;
        return $this;
    }

    /**
     * Retrieve the exception stack
     *
     * @return array
     */
    public function getException()
    {
        return $this->_exceptions;
    }

    /**
     * Has an exception been registered with the response?
     *
     * @return boolean
     */
    public function isException()
    {
        return !empty($this->_exceptions);
    }

    /**
     * Does the response object contain an exception of a given type?
     *
     * @param  string $type
     * @return boolean
     */
    public function hasExceptionOfType($type)
    {
        foreach ($this->_exceptions as $e) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the response object contain an exception with a given message?
     *
     * @param  string $message
     * @return boolean
     */
    public function hasExceptionOfMessage($message)
    {
        foreach ($this->_exceptions as $e) {
            if ($message == $e->getMessage()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the response object contain an exception with a given code?
     *
     * @param  int $code
     * @return boolean
     */
    public function hasExceptionOfCode($code)
    {
        $code = (int) $code;
        foreach ($this->_exceptions as $e) {
            if ($code == $e->getCode()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve all exceptions of a given type
     *
     * @param  string $type
     * @return false|array
     */
    public function getExceptionByType($type)
    {
        $exceptions = array();
        foreach ($this->_exceptions as $e) {
            if ($e instanceof $type) {
                $exceptions[] = $e;
            }
        }

        if (empty($exceptions)) {
            $exceptions = false;
        }

        return $exceptions;
    }

    /**
     * Retrieve all exceptions of a given message
     *
     * @param  string $message
     * @return false|array
     */
    public function getExceptionByMessage($message)
    {
        $exceptions = array();
        foreach ($this->_exceptions as $e) {
            if ($message == $e->getMessage()) {
                $exceptions[] = $e;
            }
        }

        if (empty($exceptions)) {
            $exceptions = false;
        }

        return $exceptions;
    }

    /**
     * Retrieve all exceptions of a given code
     *
     * @param mixed $code
     * @return void
     */
    public function getExceptionByCode($code)
    {
        $code       = (int) $code;
        $exceptions = array();
        foreach ($this->_exceptions as $e) {
            if ($code == $e->getCode()) {
                $exceptions[] = $e;
            }
        }

        if (empty($exceptions)) {
            $exceptions = false;
        }

        return $exceptions;
    }

    /**
     * Whether or not to render exceptions (off by default)
     *
     * If called with no arguments or a null argument, returns the value of the
     * flag; otherwise, sets it and returns the current value.
     *
     * @param boolean $flag Optional
     * @return boolean
     */
    public function renderExceptions($flag = null)
    {
        if (null !== $flag) {
            $this->_renderExceptions = $flag ? true : false;
        }

        return $this->_renderExceptions;
    }

    /**
     * Send the response, including all headers, rendering exceptions if so
     * requested.
     *
     * @return void
     */
    public function sendResponse()
    {
        $this->sendHeaders();

        if ($this->isException() && $this->renderExceptions()) {
            $exceptions = '';
            foreach ($this->getException() as $e) {
                $exceptions .= $e->__toString() . "\n";
            }
            echo $exceptions;
            return;
        }

        $this->outputBody();
    }

    /**
     * Magic __toString functionality
     *
     * Proxies to {@link sendResponse()} and returns response value as string
     * using output buffering.
     *
     * @return string
     */
    public function __toString()
    {
        ob_start();
        $this->sendResponse();
        return ob_get_clean();
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Http.php 20096 2010-01-06 02:05:09Z bkarwin $
 */


/** Zend_Controller_Response_Abstract */
// require_once 'Zend/Controller/Response/Abstract.php';


/**
 * Zend_Controller_Response_Http
 *
 * HTTP response for controllers
 *
 * @uses Zend_Controller_Response_Abstract
 * @package Zend_Controller
 * @subpackage Response
 */
class Zend_Controller_Response_Http extends Zend_Controller_Response_Abstract
{
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @version    $Id: Module.php 20096 2010-01-06 02:05:09Z bkarwin $
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Zend_Controller_Router_Route_Abstract */
// require_once 'Zend/Controller/Router/Route/Abstract.php';

/**
 * Module Route
 *
 * Default route for module functionality
 *
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @see        http://manuals.rubyonrails.com/read/chapter/65
 */
class Zend_Controller_Router_Route_Module extends Zend_Controller_Router_Route_Abstract
{
    /**
     * URI delimiter
     */
    const URI_DELIMITER = '/';

    /**
     * Default values for the route (ie. module, controller, action, params)
     * @var array
     */
    protected $_defaults;

    protected $_values      = array();
    protected $_moduleValid = false;
    protected $_keysSet     = false;

    /**#@+
     * Array keys to use for module, controller, and action. Should be taken out of request.
     * @var string
     */
    protected $_moduleKey     = 'module';
    protected $_controllerKey = 'controller';
    protected $_actionKey     = 'action';
    /**#@-*/

    /**
     * @var Zend_Controller_Dispatcher_Interface
     */
    protected $_dispatcher;

    /**
     * @var Zend_Controller_Request_Abstract
     */
    protected $_request;

    public function getVersion() {
        return 1;
    }

    /**
     * Instantiates route based on passed Zend_Config structure
     */
    public static function getInstance(Zend_Config $config)
    {
        $frontController = Zend_Controller_Front::getInstance();

        $defs       = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        $dispatcher = $frontController->getDispatcher();
        $request    = $frontController->getRequest();

        return new self($defs, $dispatcher, $request);
    }

    /**
     * Constructor
     *
     * @param array $defaults Defaults for map variables with keys as variable names
     * @param Zend_Controller_Dispatcher_Interface $dispatcher Dispatcher object
     * @param Zend_Controller_Request_Abstract $request Request object
     */
    public function __construct(array $defaults = array(),
                Zend_Controller_Dispatcher_Interface $dispatcher = null,
                Zend_Controller_Request_Abstract $request = null)
    {
        $this->_defaults = $defaults;

        if (isset($request)) {
            $this->_request = $request;
        }

        if (isset($dispatcher)) {
            $this->_dispatcher = $dispatcher;
        }
    }

    /**
     * Set request keys based on values in request object
     *
     * @return void
     */
    protected function _setRequestKeys()
    {
        if (null !== $this->_request) {
            $this->_moduleKey     = $this->_request->getModuleKey();
            $this->_controllerKey = $this->_request->getControllerKey();
            $this->_actionKey     = $this->_request->getActionKey();
        }

        if (null !== $this->_dispatcher) {
            $this->_defaults += array(
                $this->_controllerKey => $this->_dispatcher->getDefaultControllerName(),
                $this->_actionKey     => $this->_dispatcher->getDefaultAction(),
                $this->_moduleKey     => $this->_dispatcher->getDefaultModule()
            );
        }

        $this->_keysSet = true;
    }

    /**
     * Matches a user submitted path. Assigns and returns an array of variables
     * on a successful match.
     *
     * If a request object is registered, it uses its setModuleName(),
     * setControllerName(), and setActionName() accessors to set those values.
     * Always returns the values as an array.
     *
     * @param string $path Path used to match against this routing map
     * @return array An array of assigned values or a false on a mismatch
     */
    public function match($path, $partial = false)
    {
        $this->_setRequestKeys();

        $values = array();
        $params = array();

        if (!$partial) {
            $path = trim($path, self::URI_DELIMITER);
        } else {
            $matchedPath = $path;
        }

        if ($path != '') {
            $path = explode(self::URI_DELIMITER, $path);

            if ($this->_dispatcher && $this->_dispatcher->isValidModule($path[0])) {
                $values[$this->_moduleKey] = array_shift($path);
                $this->_moduleValid = true;
            }

            if (count($path) && !empty($path[0])) {
                $values[$this->_controllerKey] = array_shift($path);
            }

            if (count($path) && !empty($path[0])) {
                $values[$this->_actionKey] = array_shift($path);
            }

            if ($numSegs = count($path)) {
                for ($i = 0; $i < $numSegs; $i = $i + 2) {
                    $key = urldecode($path[$i]);
                    $val = isset($path[$i + 1]) ? urldecode($path[$i + 1]) : null;
                    $params[$key] = (isset($params[$key]) ? (array_merge((array) $params[$key], array($val))): $val);
                }
            }
        }

        if ($partial) {
            $this->setMatchedPath($matchedPath);
        }

        $this->_values = $values + $params;

        return $this->_values + $this->_defaults;
    }

    /**
     * Assembles user submitted parameters forming a URL path defined by this route
     *
     * @param array $data An array of variable and value pairs used as parameters
     * @param bool $reset Weither to reset the current params
     * @return string Route path with user submitted parameters
     */
    public function assemble($data = array(), $reset = false, $encode = true, $partial = false)
    {
        if (!$this->_keysSet) {
            $this->_setRequestKeys();
        }

        $params = (!$reset) ? $this->_values : array();

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $params[$key] = $value;
            } elseif (isset($params[$key])) {
                unset($params[$key]);
            }
        }

        $params += $this->_defaults;

        $url = '';

        if ($this->_moduleValid || array_key_exists($this->_moduleKey, $data)) {
            if ($params[$this->_moduleKey] != $this->_defaults[$this->_moduleKey]) {
                $module = $params[$this->_moduleKey];
            }
        }
        unset($params[$this->_moduleKey]);

        $controller = $params[$this->_controllerKey];
        unset($params[$this->_controllerKey]);

        $action = $params[$this->_actionKey];
        unset($params[$this->_actionKey]);

        foreach ($params as $key => $value) {
            $key = ($encode) ? urlencode($key) : $key;
            if (is_array($value)) {
                foreach ($value as $arrayValue) {
                    $arrayValue = ($encode) ? urlencode($arrayValue) : $arrayValue;
                    $url .= '/' . $key;
                    $url .= '/' . $arrayValue;
                }
            } else {
                if ($encode) $value = urlencode($value);
                $url .= '/' . $key;
                $url .= '/' . $value;
            }
        }

        if (!empty($url) || $action !== $this->_defaults[$this->_actionKey]) {
            if ($encode) $action = urlencode($action);
            $url = '/' . $action . $url;
        }

        if (!empty($url) || $controller !== $this->_defaults[$this->_controllerKey]) {
            if ($encode) $controller = urlencode($controller);
            $url = '/' . $controller . $url;
        }

        if (isset($module)) {
            if ($encode) $module = urlencode($module);
            $url = '/' . $module . $url;
        }

        return ltrim($url, self::URI_DELIMITER);
    }

    /**
     * Return a single parameter of route's defaults
     *
     * @param string $name Array key of the parameter
     * @return string Previously set default
     */
    public function getDefault($name) {
        if (isset($this->_defaults[$name])) {
            return $this->_defaults[$name];
        }
    }

    /**
     * Return an array of defaults
     *
     * @return array Route defaults
     */
    public function getDefaults() {
        return $this->_defaults;
    }

}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Loader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Loader.php 20248 2010-01-12 21:51:03Z matthew $
 */

/**
 * Static methods for loading classes and files.
 *
 * @category   Zend
 * @package    Zend_Loader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Loader
{
    /**
     * Loads a class from a PHP file.  The filename must be formatted
     * as "$class.php".
     *
     * If $dirs is a string or an array, it will search the directories
     * in the order supplied, and attempt to load the first matching file.
     *
     * If $dirs is null, it will split the class name at underscores to
     * generate a path hierarchy (e.g., "Zend_Example_Class" will map
     * to "Zend/Example/Class.php").
     *
     * If the file was not found in the $dirs, or if no $dirs were specified,
     * it will attempt to load it from PHP's include_path.
     *
     * @param string $class      - The full class name of a Zend component.
     * @param string|array $dirs - OPTIONAL Either a path or an array of paths
     *                             to search.
     * @return void
     * @throws Zend_Exception
     */
    public static function loadClass($class, $dirs = null)
    {
        if (class_exists($class, false) || interface_exists($class, false)) {
            return;
        }

        if ((null !== $dirs) && !is_string($dirs) && !is_array($dirs)) {
            // require_once 'Zend/Exception.php';
            throw new Zend_Exception('Directory argument must be a string or an array');
        }

        // Autodiscover the path from the class name
        // Implementation is PHP namespace-aware, and based on 
        // Framework Interop Group reference implementation:
        // http://groups.google.com/group/php-standards/web/psr-0-final-proposal
        $className = ltrim($class, '\\');
        $file      = '';
        $namespace = '';
        if ($lastNsPos = strripos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $file      = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $file .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

        if (!empty($dirs)) {
            // use the autodiscovered path
            $dirPath = dirname($file);
            if (is_string($dirs)) {
                $dirs = explode(PATH_SEPARATOR, $dirs);
            }
            foreach ($dirs as $key => $dir) {
                if ($dir == '.') {
                    $dirs[$key] = $dirPath;
                } else {
                    $dir = rtrim($dir, '\\/');
                    $dirs[$key] = $dir . DIRECTORY_SEPARATOR . $dirPath;
                }
            }
            $file = basename($file);
            self::loadFile($file, $dirs, true);
        } else {
            self::loadFile($file, null, true);
        }

        if (!class_exists($class, false) && !interface_exists($class, false)) {
            // require_once 'Zend/Exception.php';
            throw new Zend_Exception("File \"$file\" does not exist or class \"$class\" was not found in the file");
        }
    }

    /**
     * Loads a PHP file.  This is a wrapper for PHP's include() function.
     *
     * $filename must be the complete filename, including any
     * extension such as ".php".  Note that a security check is performed that
     * does not permit extended characters in the filename.  This method is
     * intended for loading Zend Framework files.
     *
     * If $dirs is a string or an array, it will search the directories
     * in the order supplied, and attempt to load the first matching file.
     *
     * If the file was not found in the $dirs, or if no $dirs were specified,
     * it will attempt to load it from PHP's include_path.
     *
     * If $once is TRUE, it will use include_once() instead of include().
     *
     * @param  string        $filename
     * @param  string|array  $dirs - OPTIONAL either a path or array of paths
     *                       to search.
     * @param  boolean       $once
     * @return boolean
     * @throws Zend_Exception
     */
    public static function loadFile($filename, $dirs = null, $once = false)
    {
        self::_securityCheck($filename);

        /**
         * Search in provided directories, as well as include_path
         */
        $incPath = false;
        if (!empty($dirs) && (is_array($dirs) || is_string($dirs))) {
            if (is_array($dirs)) {
                $dirs = implode(PATH_SEPARATOR, $dirs);
            }
            $incPath = get_include_path();
            set_include_path($dirs . PATH_SEPARATOR . $incPath);
        }

        /**
         * Try finding for the plain filename in the include_path.
         */
        if ($once) {
            include_once $filename;
        } else {
            include $filename;
        }

        /**
         * If searching in directories, reset include_path
         */
        if ($incPath) {
            set_include_path($incPath);
        }

        return true;
    }

    /**
     * Returns TRUE if the $filename is readable, or FALSE otherwise.
     * This function uses the PHP include_path, where PHP's is_readable()
     * does not.
     *
     * Note from ZF-2900:
     * If you use custom error handler, please check whether return value
     *  from error_reporting() is zero or not.
     * At mark of fopen() can not suppress warning if the handler is used.
     *
     * @param string   $filename
     * @return boolean
     */
    public static function isReadable($filename)
    {
        if (!$fh = @fopen($filename, 'r', true)) {
            return false;
        }
        @fclose($fh);
        return true;
    }

    /**
     * spl_autoload() suitable implementation for supporting class autoloading.
     *
     * Attach to spl_autoload() using the following:
     * <code>

     * spl_autoload_register(array('Zend_Loader', 'autoload'));
     * </code>
     *
     * @deprecated Since 1.8.0
     * @param  string $class
     * @return string|false Class name on success; false on failure
     */
    public static function autoload($class)
    {
        trigger_error(__CLASS__ . '::' . __METHOD__ . ' is deprecated as of 1.8.0 and will be removed with 2.0.0; use Zend_Loader_Autoloader instead', E_USER_NOTICE);
        try {
            @self::loadClass($class);
            return $class;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Register {@link autoload()} with spl_autoload()
     *
     * @deprecated Since 1.8.0
     * @param string $class (optional)
     * @param boolean $enabled (optional)
     * @return void
     * @throws Zend_Exception if spl_autoload() is not found
     * or if the specified class does not have an autoload() method.
     */
    public static function registerAutoload($class = 'Zend_Loader', $enabled = true)
    {
        trigger_error(__CLASS__ . '::' . __METHOD__ . ' is deprecated as of 1.8.0 and will be removed with 2.0.0; use Zend_Loader_Autoloader instead', E_USER_NOTICE);
        // require_once 'Zend/Loader/Autoloader.php';
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        if ('Zend_Loader' != $class) {
            self::loadClass($class);
            $methods = get_class_methods($class);
            if (!in_array('autoload', (array) $methods)) {
                // require_once 'Zend/Exception.php';
                throw new Zend_Exception("The class \"$class\" does not have an autoload() method");
            }

            $callback = array($class, 'autoload');

            if ($enabled) {
                $autoloader->pushAutoloader($callback);
            } else {
                $autoloader->removeAutoloader($callback);
            }
        }
    }

    /**
     * Ensure that filename does not contain exploits
     *
     * @param  string $filename
     * @return void
     * @throws Zend_Exception
     */
    protected static function _securityCheck($filename)
    {
        /**
         * Security check
         */
        if (preg_match('/[^a-z0-9\\/\\\\_.:-]/i', $filename)) {
            // require_once 'Zend/Exception.php';
            throw new Zend_Exception('Security check: Illegal character in filename');
        }
    }

    /**
     * Attempt to include() the file.
     *
     * include() is not prefixed with the @ operator because if
     * the file is loaded and contains a parse error, execution
     * will halt silently and this is difficult to debug.
     *
     * Always set display_errors = Off on production servers!
     *
     * @param  string  $filespec
     * @param  boolean $once
     * @return boolean
     * @deprecated Since 1.5.0; use loadFile() instead
     */
    protected static function _includeFile($filespec, $once = false)
    {
        if ($once) {
            return include_once $filespec;
        } else {
            return include $filespec ;
        }
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Action.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @see Zend_Controller_Action_HelperBroker
 */
// require_once 'Zend/Controller/Action/HelperBroker.php';

/**
 * @see Zend_Controller_Action_Interface
 */
// require_once 'Zend/Controller/Action/Interface.php';

/**
 * @see Zend_Controller_Front
 */
// require_once 'Zend/Controller/Front.php';

/**
 * @category   Zend
 * @package    Zend_Controller
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Controller_Action implements Zend_Controller_Action_Interface
{
    /**
     * @var array of existing class methods
     */
    protected $_classMethods;

    /**
     * Word delimiters (used for normalizing view script paths)
     * @var array
     */
    protected $_delimiters;

    /**
     * Array of arguments provided to the constructor, minus the
     * {@link $_request Request object}.
     * @var array
     */
    protected $_invokeArgs = array();

    /**
     * Front controller instance
     * @var Zend_Controller_Front
     */
    protected $_frontController;

    /**
     * Zend_Controller_Request_Abstract object wrapping the request environment
     * @var Zend_Controller_Request_Abstract
     */
    protected $_request = null;

    /**
     * Zend_Controller_Response_Abstract object wrapping the response
     * @var Zend_Controller_Response_Abstract
     */
    protected $_response = null;

    /**
     * View script suffix; defaults to 'phtml'
     * @see {render()}
     * @var string
     */
    public $viewSuffix = 'phtml';

    /**
     * View object
     * @var Zend_View_Interface
     */
    public $view;

    /**
     * Helper Broker to assist in routing help requests to the proper object
     *
     * @var Zend_Controller_Action_HelperBroker
     */
    protected $_helper = null;

    /**
     * Class constructor
     *
     * The request and response objects should be registered with the
     * controller, as should be any additional optional arguments; these will be
     * available via {@link getRequest()}, {@link getResponse()}, and
     * {@link getInvokeArgs()}, respectively.
     *
     * When overriding the constructor, please consider this usage as a best
     * practice and ensure that each is registered appropriately; the easiest
     * way to do so is to simply call parent::__construct($request, $response,
     * $invokeArgs).
     *
     * After the request, response, and invokeArgs are set, the
     * {@link $_helper helper broker} is initialized.
     *
     * Finally, {@link init()} is called as the final action of
     * instantiation, and may be safely overridden to perform initialization
     * tasks; as a general rule, override {@link init()} instead of the
     * constructor to customize an action controller's instantiation.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param Zend_Controller_Response_Abstract $response
     * @param array $invokeArgs Any additional invocation arguments
     * @return void
     */
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
        $this->setRequest($request)
             ->setResponse($response)
             ->_setInvokeArgs($invokeArgs);
        $this->_helper = new Zend_Controller_Action_HelperBroker($this);
        $this->init();
    }

    /**
     * Initialize object
     *
     * Called from {@link __construct()} as final step of object instantiation.
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Initialize View object
     *
     * Initializes {@link $view} if not otherwise a Zend_View_Interface.
     *
     * If {@link $view} is not otherwise set, instantiates a new Zend_View
     * object, using the 'views' subdirectory at the same level as the
     * controller directory for the current module as the base directory.
     * It uses this to set the following:
     * - script path = views/scripts/
     * - helper path = views/helpers/
     * - filter path = views/filters/
     *
     * @return Zend_View_Interface
     * @throws Zend_Controller_Exception if base view directory does not exist
     */
    public function initView()
    {
        if (!$this->getInvokeArg('noViewRenderer') && $this->_helper->hasHelper('viewRenderer')) {
            return $this->view;
        }

        // require_once 'Zend/View/Interface.php';
        if (isset($this->view) && ($this->view instanceof Zend_View_Interface)) {
            return $this->view;
        }

        $request = $this->getRequest();
        $module  = $request->getModuleName();
        $dirs    = $this->getFrontController()->getControllerDirectory();
        if (empty($module) || !isset($dirs[$module])) {
            $module = $this->getFrontController()->getDispatcher()->getDefaultModule();
        }
        $baseDir = dirname($dirs[$module]) . DIRECTORY_SEPARATOR . 'views';
        if (!file_exists($baseDir) || !is_dir($baseDir)) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Missing base view directory ("' . $baseDir . '")');
        }

        // require_once 'Zend/View.php';
        $this->view = new Zend_View(array('basePath' => $baseDir));

        return $this->view;
    }

    /**
     * Render a view
     *
     * Renders a view. By default, views are found in the view script path as
     * <controller>/<action>.phtml. You may change the script suffix by
     * resetting {@link $viewSuffix}. You may omit the controller directory
     * prefix by specifying boolean true for $noController.
     *
     * By default, the rendered contents are appended to the response. You may
     * specify the named body content segment to set by specifying a $name.
     *
     * @see Zend_Controller_Response_Abstract::appendBody()
     * @param  string|null $action Defaults to action registered in request object
     * @param  string|null $name Response object named path segment to use; defaults to null
     * @param  bool $noController  Defaults to false; i.e. use controller name as subdir in which to search for view script
     * @return void
     */
    public function render($action = null, $name = null, $noController = false)
    {
        if (!$this->getInvokeArg('noViewRenderer') && $this->_helper->hasHelper('viewRenderer')) {
            return $this->_helper->viewRenderer->render($action, $name, $noController);
        }

        $view   = $this->initView();
        $script = $this->getViewScript($action, $noController);

        $this->getResponse()->appendBody(
            $view->render($script),
            $name
        );
    }

    /**
     * Render a given view script
     *
     * Similar to {@link render()}, this method renders a view script. Unlike render(),
     * however, it does not autodetermine the view script via {@link getViewScript()},
     * but instead renders the script passed to it. Use this if you know the
     * exact view script name and path you wish to use, or if using paths that do not
     * conform to the spec defined with getViewScript().
     *
     * By default, the rendered contents are appended to the response. You may
     * specify the named body content segment to set by specifying a $name.
     *
     * @param  string $script
     * @param  string $name
     * @return void
     */
    public function renderScript($script, $name = null)
    {
        if (!$this->getInvokeArg('noViewRenderer') && $this->_helper->hasHelper('viewRenderer')) {
            return $this->_helper->viewRenderer->renderScript($script, $name);
        }

        $view = $this->initView();
        $this->getResponse()->appendBody(
            $view->render($script),
            $name
        );
    }

    /**
     * Construct view script path
     *
     * Used by render() to determine the path to the view script.
     *
     * @param  string $action Defaults to action registered in request object
     * @param  bool $noController  Defaults to false; i.e. use controller name as subdir in which to search for view script
     * @return string
     * @throws Zend_Controller_Exception with bad $action
     */
    public function getViewScript($action = null, $noController = null)
    {
        if (!$this->getInvokeArg('noViewRenderer') && $this->_helper->hasHelper('viewRenderer')) {
            $viewRenderer = $this->_helper->getHelper('viewRenderer');
            if (null !== $noController) {
                $viewRenderer->setNoController($noController);
            }
            return $viewRenderer->getViewScript($action);
        }

        $request = $this->getRequest();
        if (null === $action) {
            $action = $request->getActionName();
        } elseif (!is_string($action)) {
            // require_once 'Zend/Controller/Exception.php';
            throw new Zend_Controller_Exception('Invalid action specifier for view render');
        }

        if (null === $this->_delimiters) {
            $dispatcher = Zend_Controller_Front::getInstance()->getDispatcher();
            $wordDelimiters = $dispatcher->getWordDelimiter();
            $pathDelimiters = $dispatcher->getPathDelimiter();
            $this->_delimiters = array_unique(array_merge($wordDelimiters, (array) $pathDelimiters));
        }

        $action = str_replace($this->_delimiters, '-', $action);
        $script = $action . '.' . $this->viewSuffix;

        if (!$noController) {
            $controller = $request->getControllerName();
            $controller = str_replace($this->_delimiters, '-', $controller);
            $script = $controller . DIRECTORY_SEPARATOR . $script;
        }

        return $script;
    }

    /**
     * Return the Request object
     *
     * @return Zend_Controller_Request_Abstract
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Set the Request object
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return Zend_Controller_Action
     */
    public function setRequest(Zend_Controller_Request_Abstract $request)
    {
        $this->_request = $request;
        return $this;
    }

    /**
     * Return the Response object
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Set the Response object
     *
     * @param Zend_Controller_Response_Abstract $response
     * @return Zend_Controller_Action
     */
    public function setResponse(Zend_Controller_Response_Abstract $response)
    {
        $this->_response = $response;
        return $this;
    }

    /**
     * Set invocation arguments
     *
     * @param array $args
     * @return Zend_Controller_Action
     */
    protected function _setInvokeArgs(array $args = array())
    {
        $this->_invokeArgs = $args;
        return $this;
    }

    /**
     * Return the array of constructor arguments (minus the Request object)
     *
     * @return array
     */
    public function getInvokeArgs()
    {
        return $this->_invokeArgs;
    }

    /**
     * Return a single invocation argument
     *
     * @param string $key
     * @return mixed
     */
    public function getInvokeArg($key)
    {
        if (isset($this->_invokeArgs[$key])) {
            return $this->_invokeArgs[$key];
        }

        return null;
    }

    /**
     * Get a helper by name
     *
     * @param  string $helperName
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function getHelper($helperName)
    {
        return $this->_helper->{$helperName};
    }

    /**
     * Get a clone of a helper by name
     *
     * @param  string $helperName
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function getHelperCopy($helperName)
    {
        return clone $this->_helper->{$helperName};
    }

    /**
     * Set the front controller instance
     *
     * @param Zend_Controller_Front $front
     * @return Zend_Controller_Action
     */
    public function setFrontController(Zend_Controller_Front $front)
    {
        $this->_frontController = $front;
        return $this;
    }

    /**
     * Retrieve Front Controller
     *
     * @return Zend_Controller_Front
     */
    public function getFrontController()
    {
        // Used cache version if found
        if (null !== $this->_frontController) {
            return $this->_frontController;
        }

        // Grab singleton instance, if class has been loaded
        if (class_exists('Zend_Controller_Front')) {
            $this->_frontController = Zend_Controller_Front::getInstance();
            return $this->_frontController;
        }

        // Throw exception in all other cases
        // require_once 'Zend/Controller/Exception.php';
        throw new Zend_Controller_Exception('Front controller class has not been loaded');
    }

    /**
     * Pre-dispatch routines
     *
     * Called before action method. If using class with
     * {@link Zend_Controller_Front}, it may modify the
     * {@link $_request Request object} and reset its dispatched flag in order
     * to skip processing the current action.
     *
     * @return void
     */
    public function preDispatch()
    {
    }

    /**
     * Post-dispatch routines
     *
     * Called after action method execution. If using class with
     * {@link Zend_Controller_Front}, it may modify the
     * {@link $_request Request object} and reset its dispatched flag in order
     * to process an additional action.
     *
     * Common usages for postDispatch() include rendering content in a sitewide
     * template, link url correction, setting headers, etc.
     *
     * @return void
     */
    public function postDispatch()
    {
    }

    /**
     * Proxy for undefined methods.  Default behavior is to throw an
     * exception on undefined methods, however this function can be
     * overridden to implement magic (dynamic) actions, or provide run-time
     * dispatching.
     *
     * @param  string $methodName
     * @param  array $args
     * @return void
     * @throws Zend_Controller_Action_Exception
     */
    public function __call($methodName, $args)
    {
        // require_once 'Zend/Controller/Action/Exception.php';
        if ('Action' == substr($methodName, -6)) {
            $action = substr($methodName, 0, strlen($methodName) - 6);
            throw new Zend_Controller_Action_Exception(sprintf('Action "%s" does not exist and was not trapped in __call()', $action), 404);
        }

        throw new Zend_Controller_Action_Exception(sprintf('Method "%s" does not exist and was not trapped in __call()', $methodName), 500);
    }

    /**
     * Dispatch the requested action
     *
     * @param string $action Method name of action
     * @return void
     */
    public function dispatch($action)
    {
        // Notify helpers of action preDispatch state
        $this->_helper->notifyPreDispatch();

        $this->preDispatch();
        if ($this->getRequest()->isDispatched()) {
            if (null === $this->_classMethods) {
                $this->_classMethods = get_class_methods($this);
            }

            // preDispatch() didn't change the action, so we can continue
            if ($this->getInvokeArg('useCaseSensitiveActions') || in_array($action, $this->_classMethods)) {
                if ($this->getInvokeArg('useCaseSensitiveActions')) {
                    trigger_error('Using case sensitive actions without word separators is deprecated; please do not rely on this "feature"');
                }
                $this->$action();
            } else {
                $this->__call($action, array());
            }
            $this->postDispatch();
        }

        // whats actually important here is that this action controller is
        // shutting down, regardless of dispatching; notify the helpers of this
        // state
        $this->_helper->notifyPostDispatch();
    }

    /**
     * Call the action specified in the request object, and return a response
     *
     * Not used in the Action Controller implementation, but left for usage in
     * Page Controller implementations. Dispatches a method based on the
     * request.
     *
     * Returns a Zend_Controller_Response_Abstract object, instantiating one
     * prior to execution if none exists in the controller.
     *
     * {@link preDispatch()} is called prior to the action,
     * {@link postDispatch()} is called following it.
     *
     * @param null|Zend_Controller_Request_Abstract $request Optional request
     * object to use
     * @param null|Zend_Controller_Response_Abstract $response Optional response
     * object to use
     * @return Zend_Controller_Response_Abstract
     */
    public function run(Zend_Controller_Request_Abstract $request = null, Zend_Controller_Response_Abstract $response = null)
    {
        if (null !== $request) {
            $this->setRequest($request);
        } else {
            $request = $this->getRequest();
        }

        if (null !== $response) {
            $this->setResponse($response);
        }

        $action = $request->getActionName();
        if (empty($action)) {
            $action = 'index';
        }
        $action = $action . 'Action';

        $request->setDispatched(true);
        $this->dispatch($action);

        return $this->getResponse();
    }

    /**
     * Gets a parameter from the {@link $_request Request object}.  If the
     * parameter does not exist, NULL will be returned.
     *
     * If the parameter does not exist and $default is set, then
     * $default will be returned instead of NULL.
     *
     * @param string $paramName
     * @param mixed $default
     * @return mixed
     */
    protected function _getParam($paramName, $default = null)
    {
        $value = $this->getRequest()->getParam($paramName);
        if ((null === $value) && (null !== $default)) {
            $value = $default;
        }

        return $value;
    }

    /**
     * Set a parameter in the {@link $_request Request object}.
     *
     * @param string $paramName
     * @param mixed $value
     * @return Zend_Controller_Action
     */
    protected function _setParam($paramName, $value)
    {
        $this->getRequest()->setParam($paramName, $value);

        return $this;
    }

    /**
     * Determine whether a given parameter exists in the
     * {@link $_request Request object}.
     *
     * @param string $paramName
     * @return boolean
     */
    protected function _hasParam($paramName)
    {
        return null !== $this->getRequest()->getParam($paramName);
    }

    /**
     * Return all parameters in the {@link $_request Request object}
     * as an associative array.
     *
     * @return array
     */
    protected function _getAllParams()
    {
        return $this->getRequest()->getParams();
    }


    /**
     * Forward to another controller/action.
     *
     * It is important to supply the unformatted names, i.e. "article"
     * rather than "ArticleController".  The dispatcher will do the
     * appropriate formatting when the request is received.
     *
     * If only an action name is provided, forwards to that action in this
     * controller.
     *
     * If an action and controller are specified, forwards to that action and
     * controller in this module.
     *
     * Specifying an action, controller, and module is the most specific way to
     * forward.
     *
     * A fourth argument, $params, will be used to set the request parameters.
     * If either the controller or module are unnecessary for forwarding,
     * simply pass null values for them before specifying the parameters.
     *
     * @param string $action
     * @param string $controller
     * @param string $module
     * @param array $params
     * @return void
     */
    final protected function _forward($action, $controller = null, $module = null, array $params = null)
    {
        $request = $this->getRequest();

        if (null !== $params) {
            $request->setParams($params);
        }

        if (null !== $controller) {
            $request->setControllerName($controller);

            // Module should only be reset if controller has been specified
            if (null !== $module) {
                $request->setModuleName($module);
            }
        }

        $request->setActionName($action)
                ->setDispatched(false);
    }

    /**
     * Redirect to another URL
     *
     * Proxies to {@link Zend_Controller_Action_Helper_Redirector::gotoUrl()}.
     *
     * @param string $url
     * @param array $options Options to be used when redirecting
     * @return void
     */
    protected function _redirect($url, array $options = array())
    {
        $this->_helper->redirector->gotoUrl($url, $options);
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Zend_Controller_Action
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: HelperBroker.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @see Zend_Controller_Action_HelperBroker_PriorityStack
 */
// require_once 'Zend/Controller/Action/HelperBroker/PriorityStack.php';

/**
 * @see Zend_Loader
 */
// require_once 'Zend/Loader.php';

/**
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Zend_Controller_Action
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Controller_Action_HelperBroker
{
    /**
     * $_actionController - ActionController reference
     *
     * @var Zend_Controller_Action
     */
    protected $_actionController;

    /**
     * @var Zend_Loader_PluginLoader_Interface
     */
    protected static $_pluginLoader;

    /**
     * $_helpers - Helper array
     *
     * @var Zend_Controller_Action_HelperBroker_PriorityStack
     */
    protected static $_stack = null;

    /**
     * Set PluginLoader for use with broker
     *
     * @param  Zend_Loader_PluginLoader_Interface $loader
     * @return void
     */
    public static function setPluginLoader($loader)
    {
        if ((null !== $loader) && (!$loader instanceof Zend_Loader_PluginLoader_Interface)) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('Invalid plugin loader provided to HelperBroker');
        }
        self::$_pluginLoader = $loader;
    }

    /**
     * Retrieve PluginLoader
     *
     * @return Zend_Loader_PluginLoader
     */
    public static function getPluginLoader()
    {
        if (null === self::$_pluginLoader) {
            // require_once 'Zend/Loader/PluginLoader.php';
            self::$_pluginLoader = new Zend_Loader_PluginLoader(array(
                'Zend_Controller_Action_Helper' => 'Zend/Controller/Action/Helper/',
            ));
        }
        return self::$_pluginLoader;
    }

    /**
     * addPrefix() - Add repository of helpers by prefix
     *
     * @param string $prefix
     */
    static public function addPrefix($prefix)
    {
        $prefix = rtrim($prefix, '_');
        $path   = str_replace('_', DIRECTORY_SEPARATOR, $prefix);
        self::getPluginLoader()->addPrefixPath($prefix, $path);
    }

    /**
     * addPath() - Add path to repositories where Action_Helpers could be found.
     *
     * @param string $path
     * @param string $prefix Optional; defaults to 'Zend_Controller_Action_Helper'
     * @return void
     */
    static public function addPath($path, $prefix = 'Zend_Controller_Action_Helper')
    {
        self::getPluginLoader()->addPrefixPath($prefix, $path);
    }

    /**
     * addHelper() - Add helper objects
     *
     * @param Zend_Controller_Action_Helper_Abstract $helper
     * @return void
     */
    static public function addHelper(Zend_Controller_Action_Helper_Abstract $helper)
    {
        self::getStack()->push($helper);
        return;
    }

    /**
     * resetHelpers()
     *
     * @return void
     */
    static public function resetHelpers()
    {
        self::$_stack = null;
        return;
    }

    /**
     * Retrieve or initialize a helper statically
     *
     * Retrieves a helper object statically, loading on-demand if the helper
     * does not already exist in the stack. Always returns a helper, unless
     * the helper class cannot be found.
     *
     * @param  string $name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public static function getStaticHelper($name)
    {
        $name  = self::_normalizeHelperName($name);
        $stack = self::getStack();

        if (!isset($stack->{$name})) {
            self::_loadHelper($name);
        }

        return $stack->{$name};
    }

    /**
     * getExistingHelper() - get helper by name
     *
     * Static method to retrieve helper object. Only retrieves helpers already
     * initialized with the broker (either via addHelper() or on-demand loading
     * via getHelper()).
     *
     * Throws an exception if the referenced helper does not exist in the
     * stack; use {@link hasHelper()} to check if the helper is registered
     * prior to retrieving it.
     *
     * @param  string $name
     * @return Zend_Controller_Action_Helper_Abstract
     * @throws Zend_Controller_Action_Exception
     */
    public static function getExistingHelper($name)
    {
        $name  = self::_normalizeHelperName($name);
        $stack = self::getStack();

        if (!isset($stack->{$name})) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('Action helper "' . $name . '" has not been registered with the helper broker');
        }

        return $stack->{$name};
    }

    /**
     * Return all registered helpers as helper => object pairs
     *
     * @return array
     */
    public static function getExistingHelpers()
    {
        return self::getStack()->getHelpersByName();
    }

    /**
     * Is a particular helper loaded in the broker?
     *
     * @param  string $name
     * @return boolean
     */
    public static function hasHelper($name)
    {
        $name = self::_normalizeHelperName($name);
        return isset(self::getStack()->{$name});
    }

    /**
     * Remove a particular helper from the broker
     *
     * @param  string $name
     * @return boolean
     */
    public static function removeHelper($name)
    {
        $name = self::_normalizeHelperName($name);
        $stack = self::getStack();
        if (isset($stack->{$name})) {
            unset($stack->{$name});
        }

        return false;
    }

    /**
     * Lazy load the priority stack and return it
     *
     * @return Zend_Controller_Action_HelperBroker_PriorityStack
     */
    public static function getStack()
    {
        if (self::$_stack == null) {
            self::$_stack = new Zend_Controller_Action_HelperBroker_PriorityStack();
        }

        return self::$_stack;
    }

    /**
     * Constructor
     *
     * @param Zend_Controller_Action $actionController
     * @return void
     */
    public function __construct(Zend_Controller_Action $actionController)
    {
        $this->_actionController = $actionController;
        foreach (self::getStack() as $helper) {
            $helper->setActionController($actionController);
            $helper->init();
        }
    }

    /**
     * notifyPreDispatch() - called by action controller dispatch method
     *
     * @return void
     */
    public function notifyPreDispatch()
    {
        foreach (self::getStack() as $helper) {
            $helper->preDispatch();
        }
    }

    /**
     * notifyPostDispatch() - called by action controller dispatch method
     *
     * @return void
     */
    public function notifyPostDispatch()
    {
        foreach (self::getStack() as $helper) {
            $helper->postDispatch();
        }
    }

    /**
     * getHelper() - get helper by name
     *
     * @param  string $name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function getHelper($name)
    {
        $name  = self::_normalizeHelperName($name);
        $stack = self::getStack();

        if (!isset($stack->{$name})) {
            self::_loadHelper($name);
        }

        $helper = $stack->{$name};

        $initialize = false;
        if (null === ($actionController = $helper->getActionController())) {
            $initialize = true;
        } elseif ($actionController !== $this->_actionController) {
            $initialize = true;
        }

        if ($initialize) {
            $helper->setActionController($this->_actionController)
                   ->init();
        }

        return $helper;
    }

    /**
     * Method overloading
     *
     * @param  string $method
     * @param  array $args
     * @return mixed
     * @throws Zend_Controller_Action_Exception if helper does not have a direct() method
     */
    public function __call($method, $args)
    {
        $helper = $this->getHelper($method);
        if (!method_exists($helper, 'direct')) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('Helper "' . $method . '" does not support overloading via direct()');
        }
        return call_user_func_array(array($helper, 'direct'), $args);
    }

    /**
     * Retrieve helper by name as object property
     *
     * @param  string $name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function __get($name)
    {
        return $this->getHelper($name);
    }

    /**
     * Normalize helper name for lookups
     *
     * @param  string $name
     * @return string
     */
    protected static function _normalizeHelperName($name)
    {
        if (strpos($name, '_') !== false) {
            $name = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        }

        return ucfirst($name);
    }

    /**
     * Load a helper
     *
     * @param  string $name
     * @return void
     */
    protected static function _loadHelper($name)
    {
        try {
            $class = self::getPluginLoader()->load($name);
        } catch (Zend_Loader_PluginLoader_Exception $e) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('Action Helper by name ' . $name . ' not found', 0, $e);
        }

        $helper = new $class();

        if (!$helper instanceof Zend_Controller_Action_Helper_Abstract) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('Helper name ' . $name . ' -> class ' . $class . ' is not of type Zend_Controller_Action_Helper_Abstract');
        }

        self::getStack()->push($helper);
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Zend_Controller_Action
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: PriorityStack.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Zend_Controller_Action
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Controller_Action_HelperBroker_PriorityStack implements IteratorAggregate, ArrayAccess, Countable
{

    protected $_helpersByPriority = array();
    protected $_helpersByNameRef  = array();
    protected $_nextDefaultPriority = 1;

    /**
     * Magic property overloading for returning helper by name
     *
     * @param string $helperName    The helper name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function __get($helperName)
    {
        if (!array_key_exists($helperName, $this->_helpersByNameRef)) {
            return false;
        }

        return $this->_helpersByNameRef[$helperName];
    }

    /**
     * Magic property overloading for returning if helper is set by name
     *
     * @param string $helperName    The helper name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function __isset($helperName)
    {
        return array_key_exists($helperName, $this->_helpersByNameRef);
    }

    /**
     * Magic property overloading for unsetting if helper is exists by name
     *
     * @param string $helperName    The helper name
     * @return Zend_Controller_Action_Helper_Abstract
     */
    public function __unset($helperName)
    {
        return $this->offsetUnset($helperName);
    }

    /**
     * push helper onto the stack
     *
     * @param Zend_Controller_Action_Helper_Abstract $helper
     * @return Zend_Controller_Action_HelperBroker_PriorityStack
     */
    public function push(Zend_Controller_Action_Helper_Abstract $helper)
    {
        $this->offsetSet($this->getNextFreeHigherPriority(), $helper);
        return $this;
    }

    /**
     * Return something iterable
     *
     * @return array
     */
    public function getIterator()
    {
        return new ArrayObject($this->_helpersByPriority);
    }

    /**
     * offsetExists()
     *
     * @param int|string $priorityOrHelperName
     * @return Zend_Controller_Action_HelperBroker_PriorityStack
     */
    public function offsetExists($priorityOrHelperName)
    {
        if (is_string($priorityOrHelperName)) {
            return array_key_exists($priorityOrHelperName, $this->_helpersByNameRef);
        } else {
            return array_key_exists($priorityOrHelperName, $this->_helpersByPriority);
        }
    }

    /**
     * offsetGet()
     *
     * @param int|string $priorityOrHelperName
     * @return Zend_Controller_Action_HelperBroker_PriorityStack
     */
    public function offsetGet($priorityOrHelperName)
    {
        if (!$this->offsetExists($priorityOrHelperName)) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('A helper with priority ' . $priorityOrHelperName . ' does not exist.');
        }

        if (is_string($priorityOrHelperName)) {
            return $this->_helpersByNameRef[$priorityOrHelperName];
        } else {
            return $this->_helpersByPriority[$priorityOrHelperName];
        }
    }

    /**
     * offsetSet()
     *
     * @param int $priority
     * @param Zend_Controller_Action_Helper_Abstract $helper
     * @return Zend_Controller_Action_HelperBroker_PriorityStack
     */
    public function offsetSet($priority, $helper)
    {
        $priority = (int) $priority;

        if (!$helper instanceof Zend_Controller_Action_Helper_Abstract) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('$helper must extend Zend_Controller_Action_Helper_Abstract.');
        }

        if (array_key_exists($helper->getName(), $this->_helpersByNameRef)) {
            // remove any object with the same name to retain BC compailitbility
            // @todo At ZF 2.0 time throw an exception here.
            $this->offsetUnset($helper->getName());
        }

        if (array_key_exists($priority, $this->_helpersByPriority)) {
            $priority = $this->getNextFreeHigherPriority($priority);  // ensures LIFO
            trigger_error("A helper with the same priority already exists, reassigning to $priority", E_USER_WARNING);
        }

        $this->_helpersByPriority[$priority] = $helper;
        $this->_helpersByNameRef[$helper->getName()] = $helper;

        if ($priority == ($nextFreeDefault = $this->getNextFreeHigherPriority($this->_nextDefaultPriority))) {
            $this->_nextDefaultPriority = $nextFreeDefault;
        }

        krsort($this->_helpersByPriority);  // always make sure priority and LIFO are both enforced
        return $this;
    }

    /**
     * offsetUnset()
     *
     * @param int|string $priorityOrHelperName Priority integer or the helper name
     * @return Zend_Controller_Action_HelperBroker_PriorityStack
     */
    public function offsetUnset($priorityOrHelperName)
    {
        if (!$this->offsetExists($priorityOrHelperName)) {
            // require_once 'Zend/Controller/Action/Exception.php';
            throw new Zend_Controller_Action_Exception('A helper with priority or name ' . $priorityOrHelperName . ' does not exist.');
        }

        if (is_string($priorityOrHelperName)) {
            $helperName = $priorityOrHelperName;
            $helper = $this->_helpersByNameRef[$helperName];
            $priority = array_search($helper, $this->_helpersByPriority, true);
        } else {
            $priority = $priorityOrHelperName;
            $helperName = $this->_helpersByPriority[$priorityOrHelperName]->getName();
        }

        unset($this->_helpersByNameRef[$helperName]);
        unset($this->_helpersByPriority[$priority]);
        return $this;
    }

    /**
     * return the count of helpers
     *
     * @return int
     */
    public function count()
    {
        return count($this->_helpersByPriority);
    }

    /**
     * Find the next free higher priority.  If an index is given, it will
     * find the next free highest priority after it.
     *
     * @param int $indexPriority OPTIONAL
     * @return int
     */
    public function getNextFreeHigherPriority($indexPriority = null)
    {
        if ($indexPriority == null) {
            $indexPriority = $this->_nextDefaultPriority;
        }

        $priorities = array_keys($this->_helpersByPriority);

        while (in_array($indexPriority, $priorities)) {
            $indexPriority++;
        }

        return $indexPriority;
    }

    /**
     * Find the next free lower priority.  If an index is given, it will
     * find the next free lower priority before it.
     *
     * @param int $indexPriority
     * @return int
     */
    public function getNextFreeLowerPriority($indexPriority = null)
    {
        if ($indexPriority == null) {
            $indexPriority = $this->_nextDefaultPriority;
        }

        $priorities = array_keys($this->_helpersByPriority);

        while (in_array($indexPriority, $priorities)) {
            $indexPriority--;
        }

        return $indexPriority;
    }

    /**
     * return the highest priority
     *
     * @return int
     */
    public function getHighestPriority()
    {
        return max(array_keys($this->_helpersByPriority));
    }

    /**
     * return the lowest priority
     *
     * @return int
     */
    public function getLowestPriority()
    {
        return min(array_keys($this->_helpersByPriority));
    }

    /**
     * return the helpers referenced by name
     *
     * @return array
     */
    public function getHelpersByName()
    {
        return $this->_helpersByNameRef;
    }

}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Abstract.php 20210 2010-01-12 02:06:34Z yoshida@zend.co.jp $
 */

/** @see Zend_Loader */
// require_once 'Zend/Loader.php';

/** @see Zend_Loader_PluginLoader */
// require_once 'Zend/Loader/PluginLoader.php';

/** @see Zend_View_Interface */
// require_once 'Zend/View/Interface.php';

/**
 * Abstract class for Zend_View to help enforce private constructs.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_View_Abstract implements Zend_View_Interface
{
    /**
     * Path stack for script, helper, and filter directories.
     *
     * @var array
     */
    private $_path = array(
        'script' => array(),
        'helper' => array(),
        'filter' => array(),
    );

    /**
     * Script file name to execute
     *
     * @var string
     */
    private $_file = null;

    /**
     * Instances of helper objects.
     *
     * @var array
     */
    private $_helper = array();

    /**
     * Map of helper => class pairs to help in determining helper class from
     * name
     * @var array
     */
    private $_helperLoaded = array();

    /**
     * Map of helper => classfile pairs to aid in determining helper classfile
     * @var array
     */
    private $_helperLoadedDir = array();

    /**
     * Stack of Zend_View_Filter names to apply as filters.
     * @var array
     */
    private $_filter = array();

    /**
     * Stack of Zend_View_Filter objects that have been loaded
     * @var array
     */
    private $_filterClass = array();

    /**
     * Map of filter => class pairs to help in determining filter class from
     * name
     * @var array
     */
    private $_filterLoaded = array();

    /**
     * Map of filter => classfile pairs to aid in determining filter classfile
     * @var array
     */
    private $_filterLoadedDir = array();

    /**
     * Callback for escaping.
     *
     * @var string
     */
    private $_escape = 'htmlspecialchars';

    /**
     * Encoding to use in escaping mechanisms; defaults to utf-8
     * @var string
     */
    private $_encoding = 'UTF-8';

    /**
     * Flag indicating whether or not LFI protection for rendering view scripts is enabled
     * @var bool
     */
    private $_lfiProtectionOn = true;

    /**
     * Plugin loaders
     * @var array
     */
    private $_loaders = array();

    /**
     * Plugin types
     * @var array
     */
    private $_loaderTypes = array('filter', 'helper');

    /**
     * Strict variables flag; when on, undefined variables accessed in the view
     * scripts will trigger notices
     * @var boolean
     */
    private $_strictVars = false;

    /**
     * Constructor.
     *
     * @param array $config Configuration key-value pairs.
     */
    public function __construct($config = array())
    {
        // set inital paths and properties
        $this->setScriptPath(null);

        // $this->setHelperPath(null);
        $this->setFilterPath(null);

        // user-defined escaping callback
        if (array_key_exists('escape', $config)) {
            $this->setEscape($config['escape']);
        }

        // encoding
        if (array_key_exists('encoding', $config)) {
            $this->setEncoding($config['encoding']);
        }

        // base path
        if (array_key_exists('basePath', $config)) {
            $prefix = 'Zend_View';
            if (array_key_exists('basePathPrefix', $config)) {
                $prefix = $config['basePathPrefix'];
            }
            $this->setBasePath($config['basePath'], $prefix);
        }

        // user-defined view script path
        if (array_key_exists('scriptPath', $config)) {
            $this->addScriptPath($config['scriptPath']);
        }

        // user-defined helper path
        if (array_key_exists('helperPath', $config)) {
            if (is_array($config['helperPath'])) {
                foreach ($config['helperPath'] as $prefix => $path) {
                    $this->addHelperPath($path, $prefix);
                }
            } else {
                $prefix = 'Zend_View_Helper';
                if (array_key_exists('helperPathPrefix', $config)) {
                    $prefix = $config['helperPathPrefix'];
                }
                $this->addHelperPath($config['helperPath'], $prefix);
            }
        }

        // user-defined filter path
        if (array_key_exists('filterPath', $config)) {
            if (is_array($config['filterPath'])) {
                foreach ($config['filterPath'] as $prefix => $path) {
                    $this->addFilterPath($path, $prefix);
                }
            } else {
                $prefix = 'Zend_View_Filter';
                if (array_key_exists('filterPathPrefix', $config)) {
                    $prefix = $config['filterPathPrefix'];
                }
                $this->addFilterPath($config['filterPath'], $prefix);
            }
        }

        // user-defined filters
        if (array_key_exists('filter', $config)) {
            $this->addFilter($config['filter']);
        }

        // strict vars
        if (array_key_exists('strictVars', $config)) {
            $this->strictVars($config['strictVars']);
        }

        // LFI protection flag
        if (array_key_exists('lfiProtectionOn', $config)) {
            $this->setLfiProtection($config['lfiProtectionOn']);
        }

        $this->init();
    }

    /**
     * Return the template engine object
     *
     * Returns the object instance, as it is its own template engine
     *
     * @return Zend_View_Abstract
     */
    public function getEngine()
    {
        return $this;
    }

    /**
     * Allow custom object initialization when extending Zend_View_Abstract or
     * Zend_View
     *
     * Triggered by {@link __construct() the constructor} as its final action.
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Prevent E_NOTICE for nonexistent values
     *
     * If {@link strictVars()} is on, raises a notice.
     *
     * @param  string $key
     * @return null
     */
    public function __get($key)
    {
        if ($this->_strictVars) {
            trigger_error('Key "' . $key . '" does not exist', E_USER_NOTICE);
        }

        return null;
    }

    /**
     * Allows testing with empty() and isset() to work inside
     * templates.
     *
     * @param  string $key
     * @return boolean
     */
    public function __isset($key)
    {
        if ('_' != substr($key, 0, 1)) {
            return isset($this->$key);
        }

        return false;
    }

    /**
     * Directly assigns a variable to the view script.
     *
     * Checks first to ensure that the caller is not attempting to set a
     * protected or private member (by checking for a prefixed underscore); if
     * not, the public member is set; otherwise, an exception is raised.
     *
     * @param string $key The variable name.
     * @param mixed $val The variable value.
     * @return void
     * @throws Zend_View_Exception if an attempt to set a private or protected
     * member is detected
     */
    public function __set($key, $val)
    {
        if ('_' != substr($key, 0, 1)) {
            $this->$key = $val;
            return;
        }

        // require_once 'Zend/View/Exception.php';
        $e = new Zend_View_Exception('Setting private or protected class members is not allowed');
        $e->setView($this);
        throw $e;
    }

    /**
     * Allows unset() on object properties to work
     *
     * @param string $key
     * @return void
     */
    public function __unset($key)
    {
        if ('_' != substr($key, 0, 1) && isset($this->$key)) {
            unset($this->$key);
        }
    }

    /**
     * Accesses a helper object from within a script.
     *
     * If the helper class has a 'view' property, sets it with the current view
     * object.
     *
     * @param string $name The helper name.
     * @param array $args The parameters for the helper.
     * @return string The result of the helper output.
     */
    public function __call($name, $args)
    {
        // is the helper already loaded?
        $helper = $this->getHelper($name);

        // call the helper method
        return call_user_func_array(
            array($helper, $name),
            $args
        );
    }

    /**
     * Given a base path, sets the script, helper, and filter paths relative to it
     *
     * Assumes a directory structure of:
     * <code>

     * basePath/
     *     scripts/
     *     helpers/
     *     filters/
     * </code>
     *
     * @param  string $path
     * @param  string $prefix Prefix to use for helper and filter paths
     * @return Zend_View_Abstract
     */
    public function setBasePath($path, $classPrefix = 'Zend_View')
    {
        $path        = rtrim($path, '/');
        $path        = rtrim($path, '\\');
        $path       .= DIRECTORY_SEPARATOR;
        $classPrefix = rtrim($classPrefix, '_') . '_';
        $this->setScriptPath($path . 'scripts');
        $this->setHelperPath($path . 'helpers', $classPrefix . 'Helper');
        $this->setFilterPath($path . 'filters', $classPrefix . 'Filter');
        return $this;
    }

    /**
     * Given a base path, add script, helper, and filter paths relative to it
     *
     * Assumes a directory structure of:
     * <code>
     * basePath/
     *     scripts/
     *     helpers/
     *     filters/
     * </code>
     *
     * @param  string $path
     * @param  string $prefix Prefix to use for helper and filter paths
     * @return Zend_View_Abstract
     */
    public function addBasePath($path, $classPrefix = 'Zend_View')
    {
        $path        = rtrim($path, '/');
        $path        = rtrim($path, '\\');
        $path       .= DIRECTORY_SEPARATOR;
        $classPrefix = rtrim($classPrefix, '_') . '_';
        $this->addScriptPath($path . 'scripts');
        $this->addHelperPath($path . 'helpers', $classPrefix . 'Helper');
        $this->addFilterPath($path . 'filters', $classPrefix . 'Filter');
        return $this;
    }

    /**
     * Adds to the stack of view script paths in LIFO order.
     *
     * @param string|array The directory (-ies) to add.
     * @return Zend_View_Abstract
     */
    public function addScriptPath($path)
    {
        $this->_addPath('script', $path);
        return $this;
    }

    /**
     * Resets the stack of view script paths.
     *
     * To clear all paths, use Zend_View::setScriptPath(null).
     *
     * @param string|array The directory (-ies) to set as the path.
     * @return Zend_View_Abstract
     */
    public function setScriptPath($path)
    {
        $this->_path['script'] = array();
        $this->_addPath('script', $path);
        return $this;
    }

    /**
     * Return full path to a view script specified by $name
     *
     * @param  string $name
     * @return false|string False if script not found
     * @throws Zend_View_Exception if no script directory set
     */
    public function getScriptPath($name)
    {
        try {
            $path = $this->_script($name);
            return $path;
        } catch (Zend_View_Exception $e) {
            if (strstr($e->getMessage(), 'no view script directory set')) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Returns an array of all currently set script paths
     *
     * @return array
     */
    public function getScriptPaths()
    {
        return $this->_getPaths('script');
    }

    /**
     * Set plugin loader for a particular plugin type
     *
     * @param  Zend_Loader_PluginLoader $loader
     * @param  string $type
     * @return Zend_View_Abstract
     */
    public function setPluginLoader(Zend_Loader_PluginLoader $loader, $type)
    {
        $type = strtolower($type);
        if (!in_array($type, $this->_loaderTypes)) {
            // require_once 'Zend/View/Exception.php';
            $e = new Zend_View_Exception(sprintf('Invalid plugin loader type "%s"', $type));
            $e->setView($this);
            throw $e;
        }

        $this->_loaders[$type] = $loader;
        return $this;
    }

    /**
     * Retrieve plugin loader for a specific plugin type
     *
     * @param  string $type
     * @return Zend_Loader_PluginLoader
     */
    public function getPluginLoader($type)
    {
        $type = strtolower($type);
        if (!in_array($type, $this->_loaderTypes)) {
            // require_once 'Zend/View/Exception.php';
            $e = new Zend_View_Exception(sprintf('Invalid plugin loader type "%s"; cannot retrieve', $type));
            $e->setView($this);
            throw $e;
        }

        if (!array_key_exists($type, $this->_loaders)) {
            $prefix     = 'Zend_View_';
            $pathPrefix = 'Zend/View/';

            $pType = ucfirst($type);
            switch ($type) {
                case 'filter':
                case 'helper':
                default:
                    $prefix     .= $pType;
                    $pathPrefix .= $pType;
                    $loader = new Zend_Loader_PluginLoader(array(
                        $prefix => $pathPrefix
                    ));
                    $this->_loaders[$type] = $loader;
                    break;
            }
        }
        return $this->_loaders[$type];
    }

    /**
     * Adds to the stack of helper paths in LIFO order.
     *
     * @param string|array The directory (-ies) to add.
     * @param string $classPrefix Class prefix to use with classes in this
     * directory; defaults to Zend_View_Helper
     * @return Zend_View_Abstract
     */
    public function addHelperPath($path, $classPrefix = 'Zend_View_Helper_')
    {
        return $this->_addPluginPath('helper', $classPrefix, (array) $path);
    }

    /**
     * Resets the stack of helper paths.
     *
     * To clear all paths, use Zend_View::setHelperPath(null).
     *
     * @param string|array $path The directory (-ies) to set as the path.
     * @param string $classPrefix The class prefix to apply to all elements in
     * $path; defaults to Zend_View_Helper
     * @return Zend_View_Abstract
     */
    public function setHelperPath($path, $classPrefix = 'Zend_View_Helper_')
    {
        unset($this->_loaders['helper']);
        return $this->addHelperPath($path, $classPrefix);
    }

    /**
     * Get full path to a helper class file specified by $name
     *
     * @param  string $name
     * @return string|false False on failure, path on success
     */
    public function getHelperPath($name)
    {
        return $this->_getPluginPath('helper', $name);
    }

    /**
     * Returns an array of all currently set helper paths
     *
     * @return array
     */
    public function getHelperPaths()
    {
        return $this->getPluginLoader('helper')->getPaths();
    }

    /**
     * Registers a helper object, bypassing plugin loader
     *
     * @param  Zend_View_Helper_Abstract|object $helper
     * @param  string $name
     * @return Zend_View_Abstract
     * @throws Zend_View_Exception
     */
    public function registerHelper($helper, $name)
    {
        if (!is_object($helper)) {
            // require_once 'Zend/View/Exception.php';
            $e = new Zend_View_Exception('View helper must be an object');
            $e->setView($this);
            throw $e;
        }

        if (!$helper instanceof Zend_View_Interface) {
            if (!method_exists($helper, $name)) {
                // require_once 'Zend/View/Exception.php';
                $e =  new Zend_View_Exception(
                    'View helper must implement Zend_View_Interface or have a method matching the name provided'
                );
                $e->setView($this);
                throw $e;
            }
        }

        if (method_exists($helper, 'setView')) {
            $helper->setView($this);
        }

        $name = ucfirst($name);
        $this->_helper[$name] = $helper;
        return $this;
    }

    /**
     * Get a helper by name
     *
     * @param  string $name
     * @return object
     */
    public function getHelper($name)
    {
        return $this->_getPlugin('helper', $name);
    }

    /**
     * Get array of all active helpers
     *
     * Only returns those that have already been instantiated.
     *
     * @return array
     */
    public function getHelpers()
    {
        return $this->_helper;
    }

    /**
     * Adds to the stack of filter paths in LIFO order.
     *
     * @param string|array The directory (-ies) to add.
     * @param string $classPrefix Class prefix to use with classes in this
     * directory; defaults to Zend_View_Filter
     * @return Zend_View_Abstract
     */
    public function addFilterPath($path, $classPrefix = 'Zend_View_Filter_')
    {
        return $this->_addPluginPath('filter', $classPrefix, (array) $path);
    }

    /**
     * Resets the stack of filter paths.
     *
     * To clear all paths, use Zend_View::setFilterPath(null).
     *
     * @param string|array The directory (-ies) to set as the path.
     * @param string $classPrefix The class prefix to apply to all elements in
     * $path; defaults to Zend_View_Filter
     * @return Zend_View_Abstract
     */
    public function setFilterPath($path, $classPrefix = 'Zend_View_Filter_')
    {
        unset($this->_loaders['filter']);
        return $this->addFilterPath($path, $classPrefix);
    }

    /**
     * Get full path to a filter class file specified by $name
     *
     * @param  string $name
     * @return string|false False on failure, path on success
     */
    public function getFilterPath($name)
    {
        return $this->_getPluginPath('filter', $name);
    }

    /**
     * Get a filter object by name
     *
     * @param  string $name
     * @return object
     */
    public function getFilter($name)
    {
        return $this->_getPlugin('filter', $name);
    }

    /**
     * Return array of all currently active filters
     *
     * Only returns those that have already been instantiated.
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->_filter;
    }

    /**
     * Returns an array of all currently set filter paths
     *
     * @return array
     */
    public function getFilterPaths()
    {
        return $this->getPluginLoader('filter')->getPaths();
    }

    /**
     * Return associative array of path types => paths
     *
     * @return array
     */
    public function getAllPaths()
    {
        $paths = $this->_path;
        $paths['helper'] = $this->getHelperPaths();
        $paths['filter'] = $this->getFilterPaths();
        return $paths;
    }

    /**
     * Add one or more filters to the stack in FIFO order.
     *
     * @param string|array One or more filters to add.
     * @return Zend_View_Abstract
     */
    public function addFilter($name)
    {
        foreach ((array) $name as $val) {
            $this->_filter[] = $val;
        }
        return $this;
    }

    /**
     * Resets the filter stack.
     *
     * To clear all filters, use Zend_View::setFilter(null).
     *
     * @param string|array One or more filters to set.
     * @return Zend_View_Abstract
     */
    public function setFilter($name)
    {
        $this->_filter = array();
        $this->addFilter($name);
        return $this;
    }

    /**
     * Sets the _escape() callback.
     *
     * @param mixed $spec The callback for _escape() to use.
     * @return Zend_View_Abstract
     */
    public function setEscape($spec)
    {
        $this->_escape = $spec;
        return $this;
    }

    /**
     * Set LFI protection flag
     *
     * @param  bool $flag
     * @return Zend_View_Abstract
     */
    public function setLfiProtection($flag)
    {
        $this->_lfiProtectionOn = (bool) $flag;
        return $this;
    }

    /**
     * Return status of LFI protection flag
     *
     * @return bool
     */
    public function isLfiProtectionOn()
    {
        return $this->_lfiProtectionOn;
    }

    /**
     * Assigns variables to the view script via differing strategies.
     *
     * Zend_View::assign('name', $value) assigns a variable called 'name'
     * with the corresponding $value.
     *
     * Zend_View::assign($array) assigns the array keys as variable
     * names (with the corresponding array values).
     *
     * @see    __set()
     * @param  string|array The assignment strategy to use.
     * @param  mixed (Optional) If assigning a named variable, use this
     * as the value.
     * @return Zend_View_Abstract Fluent interface
     * @throws Zend_View_Exception if $spec is neither a string nor an array,
     * or if an attempt to set a private or protected member is detected
     */
    public function assign($spec, $value = null)
    {
        // which strategy to use?
        if (is_string($spec)) {
            // assign by name and value
            if ('_' == substr($spec, 0, 1)) {
                // require_once 'Zend/View/Exception.php';
                $e = new Zend_View_Exception('Setting private or protected class members is not allowed');
                $e->setView($this);
                throw $e;
            }
            $this->$spec = $value;
        } elseif (is_array($spec)) {
            // assign from associative array
            $error = false;
            foreach ($spec as $key => $val) {
                if ('_' == substr($key, 0, 1)) {
                    $error = true;
                    break;
                }
                $this->$key = $val;
            }
            if ($error) {
                // require_once 'Zend/View/Exception.php';
                $e = new Zend_View_Exception('Setting private or protected class members is not allowed');
                $e->setView($this);
                throw $e;
            }
        } else {
            // require_once 'Zend/View/Exception.php';
            $e = new Zend_View_Exception('assign() expects a string or array, received ' . gettype($spec));
            $e->setView($this);
            throw $e;
        }

        return $this;
    }

    /**
     * Return list of all assigned variables
     *
     * Returns all public properties of the object. Reflection is not used
     * here as testing reflection properties for visibility is buggy.
     *
     * @return array
     */
    public function getVars()
    {
        $vars   = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if ('_' == substr($key, 0, 1)) {
                unset($vars[$key]);
            }
        }

        return $vars;
    }

    /**
     * Clear all assigned variables
     *
     * Clears all variables assigned to Zend_View either via {@link assign()} or
     * property overloading ({@link __set()}).
     *
     * @return void
     */
    public function clearVars()
    {
        $vars   = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if ('_' != substr($key, 0, 1)) {
                unset($this->$key);
            }
        }
    }

    /**
     * Processes a view script and returns the output.
     *
     * @param string $name The script name to process.
     * @return string The script output.
     */
    public function render($name)
    {
        // find the script file name using the parent private method
        $this->_file = $this->_script($name);
        unset($name); // remove $name from local scope

        ob_start();
        $this->_run($this->_file);

        return $this->_filter(ob_get_clean()); // filter output
    }

    /**
     * Escapes a value for output in a view script.
     *
     * If escaping mechanism is one of htmlspecialchars or htmlentities, uses
     * {@link $_encoding} setting.
     *
     * @param mixed $var The output to escape.
     * @return mixed The escaped value.
     */
    public function escape($var)
    {
        if (in_array($this->_escape, array('htmlspecialchars', 'htmlentities'))) {
            return call_user_func($this->_escape, $var, ENT_COMPAT, $this->_encoding);
        }

        return call_user_func($this->_escape, $var);
    }

    /**
     * Set encoding to use with htmlentities() and htmlspecialchars()
     *
     * @param string $encoding
     * @return Zend_View_Abstract
     */
    public function setEncoding($encoding)
    {
        $this->_encoding = $encoding;
        return $this;
    }

    /**
     * Return current escape encoding
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->_encoding;
    }

    /**
     * Enable or disable strict vars
     *
     * If strict variables are enabled, {@link __get()} will raise a notice
     * when a variable is not defined.
     *
     * Use in conjunction with {@link Zend_View_Helper_DeclareVars the declareVars() helper}
     * to enforce strict variable handling in your view scripts.
     *
     * @param  boolean $flag
     * @return Zend_View_Abstract
     */
    public function strictVars($flag = true)
    {
        $this->_strictVars = ($flag) ? true : false;

        return $this;
    }

    /**
     * Finds a view script from the available directories.
     *
     * @param $name string The base name of the script.
     * @return void
     */
    protected function _script($name)
    {
        if ($this->isLfiProtectionOn() && preg_match('#\.\.[\\\/]#', $name)) {
            // require_once 'Zend/View/Exception.php';
            $e = new Zend_View_Exception('Requested scripts may not include parent directory traversal ("../", "..\\" notation)');
            $e->setView($this);
            throw $e;
        }

        if (0 == count($this->_path['script'])) {
            // require_once 'Zend/View/Exception.php';
            $e = new Zend_View_Exception('no view script directory set; unable to determine location for view script');
            $e->setView($this);
            throw $e;
        }

        foreach ($this->_path['script'] as $dir) {
            if (is_readable($dir . $name)) {
                return $dir . $name;
            }
        }

        // require_once 'Zend/View/Exception.php';
        $message = "script '$name' not found in path ("
                 . implode(PATH_SEPARATOR, $this->_path['script'])
                 . ")";
        $e = new Zend_View_Exception($message);
        $e->setView($this);
        throw $e;
    }

    /**
     * Applies the filter callback to a buffer.
     *
     * @param string $buffer The buffer contents.
     * @return string The filtered buffer.
     */
    private function _filter($buffer)
    {
        // loop through each filter class
        foreach ($this->_filter as $name) {
            // load and apply the filter class
            $filter = $this->getFilter($name);
            $buffer = call_user_func(array($filter, 'filter'), $buffer);
        }

        // done!
        return $buffer;
    }

    /**
     * Adds paths to the path stack in LIFO order.
     *
     * Zend_View::_addPath($type, 'dirname') adds one directory
     * to the path stack.
     *
     * Zend_View::_addPath($type, $array) adds one directory for
     * each array element value.
     *
     * In the case of filter and helper paths, $prefix should be used to
     * specify what class prefix to use with the given path.
     *
     * @param string $type The path type ('script', 'helper', or 'filter').
     * @param string|array $path The path specification.
     * @param string $prefix Class prefix to use with path (helpers and filters
     * only)
     * @return void
     */
    private function _addPath($type, $path, $prefix = null)
    {
        foreach ((array) $path as $dir) {
            // attempt to strip any possible separator and
            // append the system directory separator
            $dir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $dir);
            $dir = rtrim($dir, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)
                 . DIRECTORY_SEPARATOR;

            switch ($type) {
                case 'script':
                    // add to the top of the stack.
                    array_unshift($this->_path[$type], $dir);
                    break;
                case 'filter':
                case 'helper':
                default:
                    // add as array with prefix and dir keys
                    array_unshift($this->_path[$type], array('prefix' => $prefix, 'dir' => $dir));
                    break;
            }
        }
    }

    /**
     * Resets the path stack for helpers and filters.
     *
     * @param string $type The path type ('helper' or 'filter').
     * @param string|array $path The directory (-ies) to set as the path.
     * @param string $classPrefix Class prefix to apply to elements of $path
     */
    private function _setPath($type, $path, $classPrefix = null)
    {
        $dir = DIRECTORY_SEPARATOR . ucfirst($type) . DIRECTORY_SEPARATOR;

        switch ($type) {
            case 'script':
                $this->_path[$type] = array(dirname(__FILE__) . $dir);
                $this->_addPath($type, $path);
                break;
            case 'filter':
            case 'helper':
            default:
                $this->_path[$type] = array(array(
                    'prefix' => 'Zend_View_' . ucfirst($type) . '_',
                    'dir'    => dirname(__FILE__) . $dir
                ));
                $this->_addPath($type, $path, $classPrefix);
                break;
        }
    }

    /**
     * Return all paths for a given path type
     *
     * @param string $type The path type  ('helper', 'filter', 'script')
     * @return array
     */
    private function _getPaths($type)
    {
        return $this->_path[$type];
    }

    /**
     * Register helper class as loaded
     *
     * @param  string $name
     * @param  string $class
     * @param  string $file path to class file
     * @return void
     */
    private function _setHelperClass($name, $class, $file)
    {
        $this->_helperLoadedDir[$name] = $file;
        $this->_helperLoaded[$name]    = $class;
    }

    /**
     * Register filter class as loaded
     *
     * @param  string $name
     * @param  string $class
     * @param  string $file path to class file
     * @return void
     */
    private function _setFilterClass($name, $class, $file)
    {
        $this->_filterLoadedDir[$name] = $file;
        $this->_filterLoaded[$name]    = $class;
    }

    /**
     * Add a prefixPath for a plugin type
     *
     * @param  string $type
     * @param  string $classPrefix
     * @param  array $paths
     * @return Zend_View_Abstract
     */
    private function _addPluginPath($type, $classPrefix, array $paths)
    {
        $loader = $this->getPluginLoader($type);
        foreach ($paths as $path) {
            $loader->addPrefixPath($classPrefix, $path);
        }
        return $this;
    }

    /**
     * Get a path to a given plugin class of a given type
     *
     * @param  string $type
     * @param  string $name
     * @return string|false
     */
    private function _getPluginPath($type, $name)
    {
        $loader = $this->getPluginLoader($type);
        if ($loader->isLoaded($name)) {
            return $loader->getClassPath($name);
        }

        try {
            $loader->load($name);
            return $loader->getClassPath($name);
        } catch (Zend_Loader_Exception $e) {
            return false;
        }
    }

    /**
     * Retrieve a plugin object
     *
     * @param  string $type
     * @param  string $name
     * @return object
     */
    private function _getPlugin($type, $name)
    {
        $name = ucfirst($name);
        switch ($type) {
            case 'filter':
                $storeVar = '_filterClass';
                $store    = $this->_filterClass;
                break;
            case 'helper':
                $storeVar = '_helper';
                $store    = $this->_helper;
                break;
        }

        if (!isset($store[$name])) {
            $class = $this->getPluginLoader($type)->load($name);
            $store[$name] = new $class();
            if (method_exists($store[$name], 'setView')) {
                $store[$name]->setView($this);
            }
        }

        $this->$storeVar = $store;
        return $store[$name];
    }

    /**
     * Use to include the view script in a scope that only allows public
     * members.
     *
     * @return mixed
     */
    abstract protected function _run();
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: View.php 20096 2010-01-06 02:05:09Z bkarwin $
 */


/**
 * Abstract master class for extension.
 */
// require_once 'Zend/View/Abstract.php';


/**
 * Concrete class for handling view scripts.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_View extends Zend_View_Abstract
{
    /**
     * Whether or not to use streams to mimic short tags
     * @var bool
     */
    private $_useViewStream = false;

    /**
     * Whether or not to use stream wrapper if short_open_tag is false
     * @var bool
     */
    private $_useStreamWrapper = false;

    /**
     * Constructor
     *
     * Register Zend_View_Stream stream wrapper if short tags are disabled.
     *
     * @param  array $config
     * @return void
     */
    public function __construct($config = array())
    {
        $this->_useViewStream = (bool) ini_get('short_open_tag') ? false : true;
        if ($this->_useViewStream) {
            if (!in_array('zend.view', stream_get_wrappers())) {
                // require_once 'Zend/View/Stream.php';
                stream_wrapper_register('zend.view', 'Zend_View_Stream');
            }
        }

        if (array_key_exists('useStreamWrapper', $config)) {
            $this->setUseStreamWrapper($config['useStreamWrapper']);
        }

        parent::__construct($config);
    }

    /**
     * Set flag indicating if stream wrapper should be used if short_open_tag is off
     *
     * @param  bool $flag
     * @return Zend_View
     */
    public function setUseStreamWrapper($flag)
    {
        $this->_useStreamWrapper = (bool) $flag;
        return $this;
    }

    /**
     * Should the stream wrapper be used if short_open_tag is off?
     *
     * @return bool
     */
    public function useStreamWrapper()
    {
        return $this->_useStreamWrapper;
    }

    /**
     * Includes the view script in a scope with only public $this variables.
     *
     * @param string The view script to execute.
     */
    protected function _run()
    {
        if ($this->_useViewStream && $this->useStreamWrapper()) {
            include 'zend.view://' . func_get_arg(0);
        } else {
            include func_get_arg(0);
        }
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Stream.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * Stream wrapper to convert markup of mostly-PHP templates into PHP prior to
 * include().
 *
 * Based in large part on the example at
 * http://www.php.net/manual/en/function.stream-wrapper-register.php
 *
 * As well as the example provided at:
 *     http://mikenaberezny.com/2006/02/19/symphony-templates-ruby-erb/
 * written by
 *     Mike Naberezny (@link http://mikenaberezny.com)
 *     Paul M. Jones  (@link http://paul-m-jones.com)
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_View_Stream
{
    /**
     * Current stream position.
     *
     * @var int
     */
    protected $_pos = 0;

    /**
     * Data for streaming.
     *
     * @var string
     */
    protected $_data;

    /**
     * Stream stats.
     *
     * @var array
     */
    protected $_stat;

    /**
     * Opens the script file and converts markup.
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        // get the view script source
        $path        = str_replace('zend.view://', '', $path);
        $this->_data = file_get_contents($path);

        /**
         * If reading the file failed, update our local stat store
         * to reflect the real stat of the file, then return on failure
         */
        if ($this->_data === false) {
            $this->_stat = stat($path);
            return false;
        }

        /**
         * Convert <?= ?> to long-form  echo ?> and <? ?> to  ?>
         *
         */
        $this->_data = preg_replace('/\<\?\=/',          " echo ",  $this->_data);
        $this->_data = preg_replace('/<\?(?!xml|php)/s', ' ',       $this->_data);

        /**
         * file_get_contents() won't update PHP's stat cache, so we grab a stat
         * of the file to prevent additional reads should the script be
         * requested again, which will make include() happy.
         */
        $this->_stat = stat($path);

        return true;
    }

    /**
     * Included so that __FILE__ returns the appropriate info
     *
     * @return array
     */
    public function url_stat()
    {
        return $this->_stat;
    }

    /**
     * Reads from the stream.
     */
    public function stream_read($count)
    {
        $ret = substr($this->_data, $this->_pos, $count);
        $this->_pos += strlen($ret);
        return $ret;
    }


    /**
     * Tells the current position in the stream.
     */
    public function stream_tell()
    {
        return $this->_pos;
    }


    /**
     * Tells if we are at the end of the stream.
     */
    public function stream_eof()
    {
        return $this->_pos >= strlen($this->_data);
    }


    /**
     * Stream statistics.
     */
    public function stream_stat()
    {
        return $this->_stat;
    }


    /**
     * Seek to a specific point in the stream.
     */
    public function stream_seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < strlen($this->_data) && $offset >= 0) {
                $this->_pos = $offset;
                    return true;
                } else {
                    return false;
                }
                break;

            case SEEK_CUR:
                if ($offset >= 0) {
                    $this->_pos += $offset;
                    return true;
                } else {
                    return false;
                }
                break;

            case SEEK_END:
                if (strlen($this->_data) + $offset >= 0) {
                    $this->_pos = strlen($this->_data) + $offset;
                    return true;
                } else {
                    return false;
                }
                break;

            default:
                return false;
        }
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Loader
 * @subpackage PluginLoader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: PluginLoader.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/** Zend_Loader_PluginLoader_Interface */
// require_once 'Zend/Loader/PluginLoader/Interface.php';

/** Zend_Loader */
// require_once 'Zend/Loader.php';

/**
 * Generic plugin class loader
 *
 * @category   Zend
 * @package    Zend_Loader
 * @subpackage PluginLoader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Loader_PluginLoader implements Zend_Loader_PluginLoader_Interface
{
    /**
     * Class map cache file
     * @var string
     */
    protected static $_includeFileCache;

    /**
     * Instance loaded plugin paths
     *
     * @var array
     */
    protected $_loadedPluginPaths = array();

    /**
     * Instance loaded plugins
     *
     * @var array
     */
    protected $_loadedPlugins = array();

    /**
     * Instance registry property
     *
     * @var array
     */
    protected $_prefixToPaths = array();

    /**
     * Statically loaded plugin path mappings
     *
     * @var array
     */
    protected static $_staticLoadedPluginPaths = array();

    /**
     * Statically loaded plugins
     *
     * @var array
     */
    protected static $_staticLoadedPlugins = array();

    /**
     * Static registry property
     *
     * @var array
     */
    protected static $_staticPrefixToPaths = array();

    /**
     * Whether to use a statically named registry for loading plugins
     *
     * @var string|null
     */
    protected $_useStaticRegistry = null;

    /**
     * Constructor
     *
     * @param array $prefixToPaths
     * @param string $staticRegistryName OPTIONAL
     */
    public function __construct(Array $prefixToPaths = array(), $staticRegistryName = null)
    {
        if (is_string($staticRegistryName) && !empty($staticRegistryName)) {
            $this->_useStaticRegistry = $staticRegistryName;
            if(!isset(self::$_staticPrefixToPaths[$staticRegistryName])) {
                self::$_staticPrefixToPaths[$staticRegistryName] = array();
            }
            if(!isset(self::$_staticLoadedPlugins[$staticRegistryName])) {
                self::$_staticLoadedPlugins[$staticRegistryName] = array();
            }
        }

        foreach ($prefixToPaths as $prefix => $path) {
            $this->addPrefixPath($prefix, $path);
        }
    }

    /**
     * Format prefix for internal use
     *
     * @param  string $prefix
     * @return string
     */
    protected function _formatPrefix($prefix)
    {
        if($prefix == "") {
            return $prefix;
        }
        return rtrim($prefix, '_') . '_';
    }

    /**
     * Add prefixed paths to the registry of paths
     *
     * @param string $prefix
     * @param string $path
     * @return Zend_Loader_PluginLoader
     */
    public function addPrefixPath($prefix, $path)
    {
        if (!is_string($prefix) || !is_string($path)) {
            // require_once 'Zend/Loader/PluginLoader/Exception.php';
            throw new Zend_Loader_PluginLoader_Exception('Zend_Loader_PluginLoader::addPrefixPath() method only takes strings for prefix and path.');
        }

        $prefix = $this->_formatPrefix($prefix);
        $path   = rtrim($path, '/\\') . '/';

        if ($this->_useStaticRegistry) {
            self::$_staticPrefixToPaths[$this->_useStaticRegistry][$prefix][] = $path;
        } else {
            if (!isset($this->_prefixToPaths[$prefix])) {
                $this->_prefixToPaths[$prefix] = array();
            }
            if (!in_array($path, $this->_prefixToPaths[$prefix])) {
                $this->_prefixToPaths[$prefix][] = $path;
            }
        }
        return $this;
    }

    /**
     * Get path stack
     *
     * @param  string $prefix
     * @return false|array False if prefix does not exist, array otherwise
     */
    public function getPaths($prefix = null)
    {
        if ((null !== $prefix) && is_string($prefix)) {
            $prefix = $this->_formatPrefix($prefix);
            if ($this->_useStaticRegistry) {
                if (isset(self::$_staticPrefixToPaths[$this->_useStaticRegistry][$prefix])) {
                    return self::$_staticPrefixToPaths[$this->_useStaticRegistry][$prefix];
                }

                return false;
            }

            if (isset($this->_prefixToPaths[$prefix])) {
                return $this->_prefixToPaths[$prefix];
            }

            return false;
        }

        if ($this->_useStaticRegistry) {
            return self::$_staticPrefixToPaths[$this->_useStaticRegistry];
        }

        return $this->_prefixToPaths;
    }

    /**
     * Clear path stack
     *
     * @param  string $prefix
     * @return bool False only if $prefix does not exist
     */
    public function clearPaths($prefix = null)
    {
        if ((null !== $prefix) && is_string($prefix)) {
            $prefix = $this->_formatPrefix($prefix);
            if ($this->_useStaticRegistry) {
                if (isset(self::$_staticPrefixToPaths[$this->_useStaticRegistry][$prefix])) {
                    unset(self::$_staticPrefixToPaths[$this->_useStaticRegistry][$prefix]);
                    return true;
                }

                return false;
            }

            if (isset($this->_prefixToPaths[$prefix])) {
                unset($this->_prefixToPaths[$prefix]);
                return true;
            }

            return false;
        }

        if ($this->_useStaticRegistry) {
            self::$_staticPrefixToPaths[$this->_useStaticRegistry] = array();
        } else {
            $this->_prefixToPaths = array();
        }

        return true;
    }

    /**
     * Remove a prefix (or prefixed-path) from the registry
     *
     * @param string $prefix
     * @param string $path OPTIONAL
     * @return Zend_Loader_PluginLoader
     */
    public function removePrefixPath($prefix, $path = null)
    {
        $prefix = $this->_formatPrefix($prefix);
        if ($this->_useStaticRegistry) {
            $registry =& self::$_staticPrefixToPaths[$this->_useStaticRegistry];
        } else {
            $registry =& $this->_prefixToPaths;
        }

        if (!isset($registry[$prefix])) {
            // require_once 'Zend/Loader/PluginLoader/Exception.php';
            throw new Zend_Loader_PluginLoader_Exception('Prefix ' . $prefix . ' was not found in the PluginLoader.');
        }

        if ($path != null) {
            $pos = array_search($path, $registry[$prefix]);
            if ($pos === null) {
                // require_once 'Zend/Loader/PluginLoader/Exception.php';
                throw new Zend_Loader_PluginLoader_Exception('Prefix ' . $prefix . ' / Path ' . $path . ' was not found in the PluginLoader.');
            }
            unset($registry[$prefix][$pos]);
        } else {
            unset($registry[$prefix]);
        }

        return $this;
    }

    /**
     * Normalize plugin name
     *
     * @param  string $name
     * @return string
     */
    protected function _formatName($name)
    {
        return ucfirst((string) $name);
    }

    /**
     * Whether or not a Plugin by a specific name is loaded
     *
     * @param string $name
     * @return Zend_Loader_PluginLoader
     */
    public function isLoaded($name)
    {
        $name = $this->_formatName($name);
        if ($this->_useStaticRegistry) {
            return isset(self::$_staticLoadedPlugins[$this->_useStaticRegistry][$name]);
        }

        return isset($this->_loadedPlugins[$name]);
    }

    /**
     * Return full class name for a named plugin
     *
     * @param string $name
     * @return string|false False if class not found, class name otherwise
     */
    public function getClassName($name)
    {
        $name = $this->_formatName($name);
        if ($this->_useStaticRegistry
            && isset(self::$_staticLoadedPlugins[$this->_useStaticRegistry][$name])
        ) {
            return self::$_staticLoadedPlugins[$this->_useStaticRegistry][$name];
        } elseif (isset($this->_loadedPlugins[$name])) {
            return $this->_loadedPlugins[$name];
        }

        return false;
    }

    /**
     * Get path to plugin class
     *
     * @param  mixed $name
     * @return string|false False if not found
     */
    public function getClassPath($name)
    {
        $name = $this->_formatName($name);
        if ($this->_useStaticRegistry
            && !empty(self::$_staticLoadedPluginPaths[$this->_useStaticRegistry][$name])
        ) {
            return self::$_staticLoadedPluginPaths[$this->_useStaticRegistry][$name];
        } elseif (!empty($this->_loadedPluginPaths[$name])) {
            return $this->_loadedPluginPaths[$name];
        }

        if ($this->isLoaded($name)) {
            $class = $this->getClassName($name);
            $r     = new ReflectionClass($class);
            $path  = $r->getFileName();
            if ($this->_useStaticRegistry) {
                self::$_staticLoadedPluginPaths[$this->_useStaticRegistry][$name] = $path;
            } else {
                $this->_loadedPluginPaths[$name] = $path;
            }
            return $path;
        }

        return false;
    }

    /**
     * Load a plugin via the name provided
     *
     * @param  string $name
     * @param  bool $throwExceptions Whether or not to throw exceptions if the
     * class is not resolved
     * @return string|false Class name of loaded class; false if $throwExceptions
     * if false and no class found
     * @throws Zend_Loader_Exception if class not found
     */
    public function load($name, $throwExceptions = true)
    {
        $name = $this->_formatName($name);
        if ($this->isLoaded($name)) {
            return $this->getClassName($name);
        }

        if ($this->_useStaticRegistry) {
            $registry = self::$_staticPrefixToPaths[$this->_useStaticRegistry];
        } else {
            $registry = $this->_prefixToPaths;
        }

        $registry  = array_reverse($registry, true);
        $found     = false;
        $classFile = str_replace('_', DIRECTORY_SEPARATOR, $name) . '.php';
        $incFile   = self::getIncludeFileCache();
        foreach ($registry as $prefix => $paths) {
            $className = $prefix . $name;

            if (class_exists($className, false)) {
                $found = true;
                break;
            }

            $paths     = array_reverse($paths, true);

            foreach ($paths as $path) {
                $loadFile = $path . $classFile;
                if (Zend_Loader::isReadable($loadFile)) {
                    include_once $loadFile;
                    if (class_exists($className, false)) {
                        if (null !== $incFile) {
                            self::_appendIncFile($loadFile);
                        }
                        $found = true;
                        break 2;
                    }
                }
            }
        }

        if (!$found) {
            if (!$throwExceptions) {
                return false;
            }

            $message = "Plugin by name '$name' was not found in the registry; used paths:";
            foreach ($registry as $prefix => $paths) {
                $message .= "\n$prefix: " . implode(PATH_SEPARATOR, $paths);
            }
            // require_once 'Zend/Loader/PluginLoader/Exception.php';
            throw new Zend_Loader_PluginLoader_Exception($message);
       }

        if ($this->_useStaticRegistry) {
            self::$_staticLoadedPlugins[$this->_useStaticRegistry][$name]     = $className;
            self::$_staticLoadedPluginPaths[$this->_useStaticRegistry][$name] = (isset($loadFile) ? $loadFile : '');
        } else {
            $this->_loadedPlugins[$name]     = $className;
            $this->_loadedPluginPaths[$name] = (isset($loadFile) ? $loadFile : '');
        }
        return $className;
    }

    /**
     * Set path to class file cache
     *
     * Specify a path to a file that will add include_once statements for each
     * plugin class loaded. This is an opt-in feature for performance purposes.
     *
     * @param  string $file
     * @return void
     * @throws Zend_Loader_PluginLoader_Exception if file is not writeable or path does not exist
     */
    public static function setIncludeFileCache($file)
    {
        if (null === $file) {
            self::$_includeFileCache = null;
            return;
        }

        if (!file_exists($file) && !file_exists(dirname($file))) {
            // require_once 'Zend/Loader/PluginLoader/Exception.php';
            throw new Zend_Loader_PluginLoader_Exception('Specified file does not exist and/or directory does not exist (' . $file . ')');
        }
        if (file_exists($file) && !is_writable($file)) {
            // require_once 'Zend/Loader/PluginLoader/Exception.php';
            throw new Zend_Loader_PluginLoader_Exception('Specified file is not writeable (' . $file . ')');
        }
        if (!file_exists($file) && file_exists(dirname($file)) && !is_writable(dirname($file))) {
            // require_once 'Zend/Loader/PluginLoader/Exception.php';
            throw new Zend_Loader_PluginLoader_Exception('Specified file is not writeable (' . $file . ')');
        }

        self::$_includeFileCache = $file;
    }

    /**
     * Retrieve class file cache path
     *
     * @return string|null
     */
    public static function getIncludeFileCache()
    {
        return self::$_includeFileCache;
    }

    /**
     * Append an include_once statement to the class file cache
     *
     * @param  string $incFile
     * @return void
     */
    protected static function _appendIncFile($incFile)
    {
        if (!file_exists(self::$_includeFileCache)) {
            $file = '';
        } else {
            $file = file_get_contents(self::$_includeFileCache);
        }
        if (!strstr($file, $incFile)) {
            $file .= "\ninclude_once '$incFile';";
            file_put_contents(self::$_includeFileCache, $file);
        }
    }
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Interface.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface Zend_Controller_Router_Interface
{
    /**
     * Processes a request and sets its controller and action.  If
     * no route was possible, an exception is thrown.
     *
     * @param  Zend_Controller_Request_Abstract
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Request_Abstract|boolean
     */
    public function route(Zend_Controller_Request_Abstract $dispatcher);

    /**
     * Generates a URL path that can be used in URL creation, redirection, etc.
     *
     * May be passed user params to override ones from URI, Request or even defaults.
     * If passed parameter has a value of null, it's URL variable will be reset to
     * default.
     *
     * If null is passed as a route name assemble will use the current Route or 'default'
     * if current is not yet set.
     *
     * Reset is used to signal that all parameters should be reset to it's defaults.
     * Ignoring all URL specified values. User specified params still get precedence.
     *
     * Encode tells to url encode resulting path parts.
     *
     * @param  array $userParams Options passed by a user used to override parameters
     * @param  mixed $name The name of a Route to use
     * @param  bool $reset Whether to reset to the route defaults ignoring URL params
     * @param  bool $encode Tells to encode URL parts on output
     * @throws Zend_Controller_Router_Exception
     * @return string Resulting URL path
     */
    public function assemble($userParams, $name = null, $reset = false, $encode = true);

    /**
     * Retrieve Front Controller
     *
     * @return Zend_Controller_Front
     */
    public function getFrontController();

    /**
     * Set Front Controller
     *
     * @param Zend_Controller_Front $controller
     * @return Zend_Controller_Router_Interface
     */
    public function setFrontController(Zend_Controller_Front $controller);

    /**
     * Add or modify a parameter with which to instantiate any helper objects
     *
     * @param string $name
     * @param mixed $param
     * @return Zend_Controller_Router_Interface
     */
    public function setParam($name, $value);

    /**
     * Set an array of a parameters to pass to helper object constructors
     *
     * @param array $params
     * @return Zend_Controller_Router_Interface
     */
    public function setParams(array $params);

    /**
     * Retrieve a single parameter from the controller parameter stack
     *
     * @param string $name
     * @return mixed
     */
    public function getParam($name);

    /**
     * Retrieve the parameters to pass to helper object constructors
     *
     * @return array
     */
    public function getParams();

    /**
     * Clear the controller parameter stack
     *
     * By default, clears all parameters. If a parameter name is given, clears
     * only that parameter; if an array of parameter names is provided, clears
     * each.
     *
     * @param null|string|array single key or array of keys for params to clear
     * @return Zend_Controller_Router_Interface
     */
    public function clearParams($name = null);

}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @version    $Id: Interface.php 20096 2010-01-06 02:05:09Z bkarwin $
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Zend_Config */
// require_once 'Zend/Config.php';

/**
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface Zend_Controller_Router_Route_Interface {
    public function match($path);
    public function assemble($data = array(), $reset = false, $encode = false);
    public static function getInstance(Zend_Config $config);
}



/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Dispatcher
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Interface.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * Zend_Controller_Request_Abstract
 */
// require_once 'Zend/Controller/Request/Abstract.php';

/**
 * Zend_Controller_Response_Abstract
 */
// require_once 'Zend/Controller/Response/Abstract.php';

/**
 * @package    Zend_Controller
 * @subpackage Dispatcher
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface Zend_Controller_Dispatcher_Interface
{
    /**
     * Formats a string into a controller name.  This is used to take a raw
     * controller name, such as one that would be packaged inside a request
     * object, and reformat it to a proper class name that a class extending
     * Zend_Controller_Action would use.
     *
     * @param string $unformatted
     * @return string
     */
    public function formatControllerName($unformatted);

    /**
     * Formats a string into a module name.  This is used to take a raw
     * module name, such as one that would be packaged inside a request
     * object, and reformat it to a proper directory/class name that a class extending
     * Zend_Controller_Action would use.
     *
     * @param string $unformatted
     * @return string
     */
    public function formatModuleName($unformatted);

    /**
     * Formats a string into an action name.  This is used to take a raw
     * action name, such as one that would be packaged inside a request
     * object, and reformat into a proper method name that would be found
     * inside a class extending Zend_Controller_Action.
     *
     * @param string $unformatted
     * @return string
     */
    public function formatActionName($unformatted);

    /**
     * Returns TRUE if an action can be dispatched, or FALSE otherwise.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return boolean
     */
    public function isDispatchable(Zend_Controller_Request_Abstract $request);

    /**
     * Add or modify a parameter with which to instantiate an Action Controller
     *
     * @param string $name
     * @param mixed $value
     * @return Zend_Controller_Dispatcher_Interface
     */
    public function setParam($name, $value);

    /**
     * Set an array of a parameters to pass to the Action Controller constructor
     *
     * @param array $params
     * @return Zend_Controller_Dispatcher_Interface
     */
    public function setParams(array $params);

    /**
     * Retrieve a single parameter from the controller parameter stack
     *
     * @param string $name
     * @return mixed
     */
    public function getParam($name);

    /**
     * Retrieve the parameters to pass to the Action Controller constructor
     *
     * @return array
     */
    public function getParams();

    /**
     * Clear the controller parameter stack
     *
     * By default, clears all parameters. If a parameter name is given, clears
     * only that parameter; if an array of parameter names is provided, clears
     * each.
     *
     * @param null|string|array single key or array of keys for params to clear
     * @return Zend_Controller_Dispatcher_Interface
     */
    public function clearParams($name = null);

    /**
     * Set the response object to use, if any
     *
     * @param Zend_Controller_Response_Abstract|null $response
     * @return void
     */
    public function setResponse(Zend_Controller_Response_Abstract $response = null);

    /**
     * Retrieve the response object, if any
     *
     * @return Zend_Controller_Response_Abstract|null
     */
    public function getResponse();

    /**
     * Add a controller directory to the controller directory stack
     *
     * @param string $path
     * @param string $args
     * @return Zend_Controller_Dispatcher_Interface
     */
    public function addControllerDirectory($path, $args = null);

    /**
     * Set the directory where controller files are stored
     *
     * Specify a string or an array; if an array is specified, all paths will be
     * added.
     *
     * @param string|array $dir
     * @return Zend_Controller_Dispatcher_Interface
     */
    public function setControllerDirectory($path);

    /**
     * Return the currently set directory(ies) for controller file lookup
     *
     * @return array
     */
    public function getControllerDirectory();

    /**
     * Dispatches a request object to a controller/action.  If the action
     * requests a forward to another action, a new request will be returned.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @param  Zend_Controller_Response_Abstract $response
     * @return void
     */
    public function dispatch(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response);

    /**
     * Whether or not a given module is valid
     *
     * @param string $module
     * @return boolean
     */
    public function isValidModule($module);

    /**
     * Retrieve the default module name
     *
     * @return string
     */
    public function getDefaultModule();

    /**
     * Retrieve the default controller name
     *
     * @return string
     */
    public function getDefaultControllerName();

    /**
     * Retrieve the default action
     *
     * @return string
     */
    public function getDefaultAction();
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Zend_Controller_Action
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Interface.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @category   Zend
 * @package    Zend_Controller
 * @subpackage Zend_Controller_Action
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface Zend_Controller_Action_Interface
{
    /**
     * Class constructor
     *
     * The request and response objects should be registered with the
     * controller, as should be any additional optional arguments; these will be
     * available via {@link getRequest()}, {@link getResponse()}, and
     * {@link getInvokeArgs()}, respectively.
     *
     * When overriding the constructor, please consider this usage as a best
     * practice and ensure that each is registered appropriately; the easiest
     * way to do so is to simply call parent::__construct($request, $response,
     * $invokeArgs).
     *
     * After the request, response, and invokeArgs are set, the
     * {@link $_helper helper broker} is initialized.
     *
     * Finally, {@link init()} is called as the final action of
     * instantiation, and may be safely overridden to perform initialization
     * tasks; as a general rule, override {@link init()} instead of the
     * constructor to customize an action controller's instantiation.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param Zend_Controller_Response_Abstract $response
     * @param array $invokeArgs Any additional invocation arguments
     * @return void
     */
    public function __construct(Zend_Controller_Request_Abstract $request,
                                Zend_Controller_Response_Abstract $response,
                                array $invokeArgs = array());

    /**
     * Dispatch the requested action
     *
     * @param string $action Method name of action
     * @return void
     */
    public function dispatch($action);
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Interface.php 20210 2010-01-12 02:06:34Z yoshida@zend.co.jp $
 */


/**
 * Interface class for Zend_View compatible template engine implementations
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface Zend_View_Interface
{
    /**
     * Return the template engine object, if any
     *
     * If using a third-party template engine, such as Smarty, patTemplate,
     * phplib, etc, return the template engine object. Useful for calling
     * methods on these objects, such as for setting filters, modifiers, etc.
     *
     * @return mixed
     */
    public function getEngine();

    /**
     * Set the path to find the view script used by render()
     *
     * @param string|array The directory (-ies) to set as the path. Note that
     * the concrete view implentation may not necessarily support multiple
     * directories.
     * @return void
     */
    public function setScriptPath($path);

    /**
     * Retrieve all view script paths
     *
     * @return array
     */
    public function getScriptPaths();

    /**
     * Set a base path to all view resources
     *
     * @param  string $path
     * @param  string $classPrefix
     * @return void
     */
    public function setBasePath($path, $classPrefix = 'Zend_View');

    /**
     * Add an additional path to view resources
     *
     * @param  string $path
     * @param  string $classPrefix
     * @return void
     */
    public function addBasePath($path, $classPrefix = 'Zend_View');

    /**
     * Assign a variable to the view
     *
     * @param string $key The variable name.
     * @param mixed $val The variable value.
     * @return void
     */
    public function __set($key, $val);

    /**
     * Allows testing with empty() and isset() to work
     *
     * @param string $key
     * @return boolean
     */
    public function __isset($key);

    /**
     * Allows unset() on object properties to work
     *
     * @param string $key
     * @return void
     */
    public function __unset($key);

    /**
     * Assign variables to the view script via differing strategies.
     *
     * Suggested implementation is to allow setting a specific key to the
     * specified value, OR passing an array of key => value pairs to set en
     * masse.
     *
     * @see __set()
     * @param string|array $spec The assignment strategy to use (key or array of key
     * => value pairs)
     * @param mixed $value (Optional) If assigning a named variable, use this
     * as the value.
     * @return void
     */
    public function assign($spec, $value = null);

    /**
     * Clear all assigned variables
     *
     * Clears all variables assigned to Zend_View either via {@link assign()} or
     * property overloading ({@link __get()}/{@link __set()}).
     *
     * @return void
     */
    public function clearVars();

    /**
     * Processes a view script and returns the output.
     *
     * @param string $name The script name to process.
     * @return string The script output.
     */
    public function render($name);
}


/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Loader
 * @subpackage PluginLoader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Interface.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * Plugin class loader interface
 *
 * @category   Zend
 * @package    Zend_Loader
 * @subpackage PluginLoader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface Zend_Loader_PluginLoader_Interface
{
    /**
     * Add prefixed paths to the registry of paths
     *
     * @param string $prefix
     * @param string $path
     * @return Zend_Loader_PluginLoader
     */
    public function addPrefixPath($prefix, $path);

    /**
     * Remove a prefix (or prefixed-path) from the registry
     *
     * @param string $prefix
     * @param string $path OPTIONAL
     * @return Zend_Loader_PluginLoader
     */
    public function removePrefixPath($prefix, $path = null);

    /**
     * Whether or not a Helper by a specific name
     *
     * @param string $name
     * @return Zend_Loader_PluginLoader
     */
    public function isLoaded($name);

    /**
     * Return full class name for a named helper
     *
     * @param string $name
     * @return string
     */
    public function getClassName($name);

    /**
     * Load a helper via the name provided
     *
     * @param string $name
     * @return string
     */
    public function load($name);
}
