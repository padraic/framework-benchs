<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\integration\g11n;

use \lithium\g11n\Catalog;
use \lithium\g11n\catalog\adapter\Memory;
use \lithium\util\Inflector;

class CatalogInflectorTest extends \lithium\test\Unit {

	protected $_backups = array();

	public function setUp() {
		$this->_backups['catalogConfig'] = Catalog::config();
		Catalog::reset();
		Catalog::config(array(
			'runtime' => array('adapter' => new Memory())
		));
		Inflector::reset();
	}

	public function tearDown() {
		Catalog::reset();
		Catalog::config($this->_backups['catalogConfig']);
	}

	public function testTransliteration() {
		$data = array(
			'transliteration' => array(
				'\$' => 'dollar',
				'&' => 'and'
			)
		);
		Catalog::write('inflection', 'en', $data, array('name' => 'runtime'));

		Inflector::rules('transliteration', Catalog::read('inflection.transliteration', 'en'));

		$result = Inflector::slug('this & that');
		$expected = 'this-and-that';
		$this->assertEqual($expected, $result);

		$data = array(
			'transliteration' => array(
				't' => 'd',
				'&' => 'und'
			)
		);
		Catalog::write('inflection', 'de', $data, array('name' => 'runtime'));

		Inflector::rules('transliteration', Catalog::read('inflection.transliteration', 'de'));

		$result = Inflector::slug('this & that');
		$expected = 'dhis-und-dhad';
		$this->assertEqual($expected, $result);
	}
}

?>