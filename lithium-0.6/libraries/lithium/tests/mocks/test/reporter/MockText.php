<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\mocks\test\reporter;

class MockText extends \lithium\test\reporter\Text {

	public function result($stats) {
		return $this->_result($stats);
	}

	public function fail($fails) {
		return $this->_fail($fails);
	}

	public function exception($exceptions) {
		return $this->_exception($exceptions);
	}
}

?>