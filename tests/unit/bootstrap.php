<?php
/**
 * Unit test bootstrap: no WordPress is loaded. Only pure-PHP classes are tested here.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPU_PATH', dirname( __DIR__, 2 ) . '/' );

require_once WPU_PATH . 'src/autoload.php';

foreach ( glob( __DIR__ . '/Support/*.php' ) as $support_file ) {
	require_once $support_file;
}
