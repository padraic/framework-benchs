<?php
	if (file_exists('/var/www/flow3-1.0.0alpha7/Data/Temporary/a31c33f123ac/Configuration/ProductionConfigurations.php') && \F3\FLOW3\Core\Bootstrap::REVISION === '$Revision: 3769 $') {
		return require '/var/www/flow3-1.0.0alpha7/Data/Temporary/a31c33f123ac/Configuration/ProductionConfigurations.php';
	} else {
		unlink(__FILE__);
		return array();
	}
?>