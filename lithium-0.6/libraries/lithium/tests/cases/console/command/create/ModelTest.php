<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\console\command\create;

use \lithium\console\command\create\Model;
use \lithium\console\Request;
use \lithium\core\Libraries;

class ModelTest extends \lithium\test\Unit {

	public $request;

	protected $_backup = array();

	protected $_testPath = null;

	public function setUp() {
		$this->classes = array('response' => '\lithium\tests\mocks\console\MockResponse');
		$this->_backup['cwd'] = getcwd();
		$this->_backup['_SERVER'] = $_SERVER;
		$_SERVER['argv'] = array();
		$this->_testPath = LITHIUM_APP_PATH . '/resources/tmp/tests';

		Libraries::add('create_test', array('path' => $this->_testPath . '/create_test'));
		$this->request = new Request(array('input' => fopen('php://temp', 'w+')));
		$this->request->params = array('library' => 'create_test');
	}

	public function tearDown() {
		$_SERVER = $this->_backup['_SERVER'];
		chdir($this->_backup['cwd']);
		$this->_cleanUp();
	}

	public function testRun() {
		$model = new Model(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$model->path = $this->_testPath;
		$model->run('Post');
		$expected = "Post created in create_test\\models.\n";
		$result = $model->response->output;
		$this->assertEqual($expected, $result);

		$expected = <<<'test'


namespace create_test\models;

class Post extends \lithium\data\Model {

	public $validates = array();
}


test;
		$replace = array("<?php", "?>");
		$result = str_replace($replace, '',
			file_get_contents($this->_testPath . '/create_test/models/Post.php')
		);
		$this->assertEqual($expected, $result);
	}
}

?>