<?php
// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

function autoload($class) {
    include str_replace('_', '/', $class) . '.php';  
}
spl_autoload_register('autoload');

// Preload
include APPLICATION_PATH . '/../data/preload/preload.php';

$front = Zend_Controller_Front::getInstance();
$front->setParam('noViewRenderer', true);
$router = apc_fetch('zfwtfopt-router');
if (!$router) {
    $router = $front->getRouter();
    $router->addRoute('user',
        new Zend_Controller_Router_Route('hello/:name')
    );
    $router->addRoute('products',
        new Zend_Controller_Router_Route_Static('products', array('action'=>'products'))
    );
    $router->addRoute('product/:slug',
        new Zend_Controller_Router_Route('product', array('action'=>'product'))
    );
    $router->addRoute('route_1',
        new Zend_Controller_Router_Route('route1/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_2',
        new Zend_Controller_Router_Route('route2/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_3',
        new Zend_Controller_Router_Route('route3/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_4',
        new Zend_Controller_Router_Route('route4/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_5',
        new Zend_Controller_Router_Route('route5/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_6',
        new Zend_Controller_Router_Route('route6/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_7',
        new Zend_Controller_Router_Route('route7/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_8',
        new Zend_Controller_Router_Route('route8/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_9',
        new Zend_Controller_Router_Route('route9/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_10',
        new Zend_Controller_Router_Route('route10/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_11',
        new Zend_Controller_Router_Route('route11/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_12',
        new Zend_Controller_Router_Route('route12/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_13',
        new Zend_Controller_Router_Route('route13/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_14',
        new Zend_Controller_Router_Route('route14/:slug', array('action'=>'route'))
    );
    $router->addRoute('route_15',
        new Zend_Controller_Router_Route('route15/:slug', array('action'=>'route'))
    );
    apc_store('zfwtfopt-router', serialize($router));
} else {
    $front->setRouter(unserialize($router));
}
$front->setControllerDirectory(APPLICATION_PATH . '/controllers')
    ->dispatch();
