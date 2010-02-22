<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        $this->view = new Zend_View();
        $this->view->addScriptPath(APPLICATION_PATH . '/views/scripts/index');
    }
    
    public function indexAction()
    {
        $this->view->name = $this->getRequest()->getParam('name');
        $this->getResponse()->setBody(
            $this->view->render('index.phtml')
        );
    }

    public function productsAction()
    {
        $this->view->products = array(
          array('id' => 1, 'title' => 'foo<br />'),
          array('id' => 2, 'title' => 'foo1'),
          array('id' => 3, 'title' => 'foo2'),
          array('id' => 4, 'title' => 'foo3'),
          array('id' => 5, 'title' => 'foo4'),
          array('id' => 6, 'title' => 'foo5'),
          array('id' => 7, 'title' => 'foo6'),
          array('id' => 8, 'title' => 'foo7'),
          array('id' => 9, 'title' => 'foo8'),
          array('id' => 10, 'title' => 'foo9'),
          array('id' => 11, 'title' => 'foo10'),
          array('id' => 12, 'title' => 'foo11'),
          array('id' => 13, 'title' => 'foo12'),
          array('id' => 14, 'title' => 'foo13'),
          array('id' => 15, 'title' => 'foo14'),
        );
        var_dump($this->view->products);
        $this->getResponse()->setBody(
            $this->view->render('products.phtml')
        );
    }
}
