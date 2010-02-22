<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\net\http;

use \lithium\net\http\Media;
use \lithium\action\Request;
use \lithium\action\Response;
use \lithium\core\Libraries;

class MediaTest extends \lithium\test\Unit {

	/**
	 * Tests setting, getting and removing custom media types.
	 *
	 * @return void
	 */
	public function testMediaTypes() {
		$result = Media::types();

		$this->assertTrue(is_array($result));
		$this->assertTrue(in_array('json', $result));
		$this->assertFalse(in_array('my', $result));

		$this->assertEqual($result, Media::formats());

		$result = Media::type('json');
		$expected = 'application/json';
		$this->assertEqual($expected, $result['content']);

		$expected = array(
			'view' => false, 'layout' => false, 'encode' => 'json_encode', 'decode' => 'json_decode'
		);
		$this->assertEqual($expected, $result['options']);

		Media::type('my', 'text/x-my', array('view' => '\my\custom\View', 'layout' => false));

		$result = Media::types();
		$this->assertTrue(in_array('my', $result));

		$result = Media::type('my');
		$expected = 'text/x-my';
		$this->assertEqual($expected, $result['content']);

		$expected = array(
			'view' => '\my\custom\View',
			'template' => null,
			'layout' => null,
			'encode' => null,
			'decode' => null
		);
		$this->assertEqual($expected, $result['options']);

		Media::type('my', false);
		$result = Media::types();
		$this->assertFalse(in_array('my', $result));
	}

	public function testAssetTypeHandling() {
		$result = Media::assets();
		$expected = array('js', 'css', 'image', 'generic');
		$this->assertEqual($expected, array_keys($result));

		$result = Media::assets('css');
		$expected = '.css';
		$this->assertEqual($expected, $result['suffix']);
		$this->assertTrue(isset($result['path']['{:base}/{:library}/css/{:path}']));

		$result = Media::assets('my');
		$this->assertNull($result);

		$result = Media::assets('my', array('suffix' => '.my', 'path' => array(
			'{:base}/my/{:path}' => array('base', 'path')
		)));
		$this->assertNull($result);

		$result = Media::assets('my');
		$expected = '.my';
		$this->assertEqual($expected, $result['suffix']);
		$this->assertTrue(isset($result['path']['{:base}/my/{:path}']));

		$this->assertNull($result['filter']);
		Media::assets('my', array('filter' => array('/my/' => '/your/')));

		$result = Media::assets('my');
		$expected = array('/my/' => '/your/');
		$this->assertEqual($expected, $result['filter']);

		$expected = '.my';
		$this->assertEqual($expected, $result['suffix']);

		Media::assets('my', false);
		$result = Media::assets('my');
		$this->assertNull($result);
	}

	public function testAssetPathGeneration() {
		$result = Media::asset('scheme://host/subpath/file', 'js');
		$expected = 'scheme://host/subpath/file';
		$this->assertEqual($expected, $result);

		$result = Media::asset('subpath/file', 'js');
		$expected = '/js/subpath/file.js';
		$this->assertEqual($expected, $result);

		$result = Media::asset('this.file.should.not.exist', 'css', array('check' => true));
		$this->assertFalse($result);

		$result = Media::asset('base', 'css', array('check' => 'true', 'library' => 'app'));
		$expected = '/css/base.css';
		$this->assertEqual($expected, $result);

		$result = Media::asset('base', 'css', array('timestamp' => true));
		$this->assertPattern('%^/css/base\.css\?\d+$%', $result);

		$result = Media::asset('base.css?type=test', 'css', array(
			'check' => 'true', 'base' => 'foo'
		));
		$expected = 'foo/css/base.css?type=test';
		$this->assertEqual($expected, $result);

		$result = Media::asset('base.css?type=test', 'css', array(
			'check' => 'true', 'base' => 'foo', 'timestamp' => true
		));
		$this->assertPattern('%^foo/css/base\.css\?type=test&\d+$%', $result);
	}

	public function testCustomAssetPathGeneration() {
		Media::assets('my', array('suffix' => '.my', 'path' => array(
			'{:base}/my/{:path}' => array('base', 'path')
		)));

		$result = Media::asset('subpath/file', 'my');
		$expected = '/my/subpath/file.my';
		$this->assertEqual($expected, $result);

		Media::assets('my', array('filter' => array('/my/' => '/your/')));

		$result = Media::asset('subpath/file', 'my');
		$expected = '/your/subpath/file.my';
		$this->assertEqual($expected, $result);

		$result = Media::asset('subpath/file', 'my', array('base' => '/app/path'));
		$expected = '/app/path/your/subpath/file.my';
		$this->assertEqual($expected, $result);

		$result = Media::asset('subpath/file', 'my', array('base' => '/app/path/'));
		$expected = '/app/path//your/subpath/file.my';
		$this->assertEqual($expected, $result);
	}

	public function testMultiLibraryAssetPaths() {
		$result = Media::asset('path/file', 'js', array('library' => 'app', 'base' => '/app/base'));
		$expected = '/app/base/js/path/file.js';
		$this->assertEqual($expected, $result);

		Libraries::add('plugin', array('li3_foo_blog' => array(
			'path' => LITHIUM_APP_PATH . '/libraries/plugins/blog',
			'bootstrap' => false,
			'route' => false
		)));

		$result = Media::asset('path/file', 'js', array(
			'library' => 'li3_foo_blog', 'base' => '/app/base'
		));
		$expected = '/app/base/blog/js/path/file.js';
		$this->assertEqual($expected, $result);

		Libraries::remove('li3_foo_blog');
	}

	public function testManualAssetPaths() {
		$result = Media::asset('/path/file', 'js', array('base' => '/base'));
		$expected = '/base/path/file.js';
		$this->assertEqual($expected, $result);

		$result = Media::asset('/foo/bar', 'js', array('base' => '/base', 'check' => true));
		$this->assertFalse($result);

		$result = Media::asset('/css/base', 'css', array('base' => '/base', 'check' => true));
		$expected = '/base/css/base.css';
		$this->assertEqual($expected, $result);

		$result = Media::asset('/css/base.css', 'css', array('base' => '/base', 'check' => true));
		$expected = '/base/css/base.css';
		$this->assertEqual($expected, $result);

		$result = Media::asset('/css/base.css?foo', 'css', array(
			'base' => '/base', 'check' => true
		));
		$expected = '/base/css/base.css?foo';
		$this->assertEqual($expected, $result);
	}

	public function testRender() {
		$response = new Response();
		$response->type('json');
		$data = array('something');
		Media::render($response, $data);

		$expected = array('Content-type: application/json');
		$result = $response->headers();
		$this->assertEqual($expected, $result);

		$expected = json_encode($data);
		$result = $response->body();
		$this->assertEqual($expected, $result);
	}

	public function testCustomEncodeHandler() {
		$response = new Response();
		$response->type = 'csv';

		Media::type('csv', 'application/csv', array('encode' => function($data) {
			ob_start();
			$out = fopen('php://output', 'w');
			foreach ($data as $record) {
				fputcsv($out, $record);
			}
			fclose($out);
			return ob_get_clean();
		}));

		$data = array(
			array('John', 'Doe', '123 Main St.', 'Anytown, CA', '91724'),
			array('Jane', 'Doe', '124 Main St.', 'Anytown, CA', '91724')
		);

		Media::render($response, $data);
		$result = $response->body;
		$expected = 'John,Doe,"123 Main St.","Anytown, CA",91724' . "\n";
		$expected .= 'Jane,Doe,"124 Main St.","Anytown, CA",91724' . "\n";
		$this->assertEqual(array($expected), $result);

		$result = $response->headers['Content-type'];
		$expected = 'application/csv';
		$this->assertEqual($expected, $result);
	}

	/**
	 * Tests that rendering plain text correctly returns the render data as-is.
	 *
	 * @return void
	 */
	public function testPlainTextOutput() {
		$response = new Response();
		$response->type = 'text';
		Media::render($response, "Hello, world!");

		$expected = array("Hello, world!");
		$result = $response->body;
		$this->assertEqual($expected, $result);
	}

	/**
	 * Tests that an exception is thrown for cases where an attempt is made to render content for
	 * a type which is not registered.
	 *
	 * @return void
	 */
	public function testUndhandledContent() {
		$response = new Response();
		$response->type = 'bad';

		$this->expectException("Unhandled media type 'bad'");
		Media::render($response, array('foo' => 'bar'));

		$result = $response->body;
		$this->assertNull($result);
	}

	/**
	 * Tests that attempts to render a media type with no handler registered produces an
	 * 'unhandled media type' exception, even if the type itself is a registered content type.
	 *
	 * @return void
	 */
	public function testUnregisteredContentHandler() {
		$response = new Response();
		$response->type = 'xml';

		$this->expectException("Unhandled media type 'xml'");
		Media::render($response, array('foo' => 'bar'));

		$result = $response->body;
		$this->assertNull($result);
	}

	/**
	 * Tests handling content type manually using parameters to `Media::render()`, for content types
	 * that are registered but have no default handler.
	 *
	 * @return void
	 */
	public function testManualContentHandling() {
		Media::type('custom', 'text/x-custom');
		$response = new Response();
		$response->type = 'custom';

		Media::render($response, 'Hello, world!', array(
			'layout' => false,
			'template' => false,
			'encode' => function($data) { return "Message: {$data}"; }
		));

		$result = $response->body;
		$expected = array("Message: Hello, world!");
		$this->assertEqual($expected, $result);

		$this->expectException("/Template not found/");
		Media::render($response, 'Hello, world!');

		$result = $response->body;
		$this->assertNull($result);
	}

	/**
	 * Tests that parameters from the `Request` object passed into `render()` via
	 * `$options['request']` are properly merged into the `$options` array passed to render
	 * handlers.
	 *
	 * @return void
	 */
	public function testRequestOptionMerging() {
		$request = new Request();
		$request->params['foo'] = 'bar';

		$response = new Response();
		$response->type = 'custom';

		Media::render($response, null, compact('request') + array(
			'layout' => false,
			'template' => false,
			'encode' => function($data, $handler, $options) { return $options['foo']; }
		));
		$this->assertEqual(array('bar'), $response->body);
	}

	public function testMediaEncoding() {
		$data = array('hello', 'goodbye', 'foo' => array('bar', 'baz' => 'dib'));
		$expected = json_encode($data);
		$result = Media::encode('json', $data);
		$this->assertEqual($expected, $result);

		$this->assertEqual($result, Media::to('json', $data));

		$result = Media::encode('badness', $data);
		$this->assertNull($result);
	}

	public function testRenderWithOptionsMerging() {
		$request = new Request();
		$request->params['controller'] = 'pages';

		$response = new Response();
		$response->type = 'html';

		Media::render($response, null, compact('request') + array(
			'layout' => false,
			'template' => 'home',
		));
		$this->assertPattern('/Home/', $response->body());
	}
}

?>