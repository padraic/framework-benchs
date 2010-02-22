<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\mocks\data\source\database\adapter;

class MockMySQLi extends \lithium\data\source\database\adapter\MySQLi {

	public function get($var) {
		return $this->{$var};
	}
}

?>