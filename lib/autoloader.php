<?php

spl_autoload_register( 'btree_autoloader' );

/**
 * Autoloader for \BTreeDb\* classes
 *
 * @param string $class
 *
 * @return void
 */
function btree_autoloader( $class ){
	
	// Register class auto loader
	// Custom modules
	if( strpos( $class, 'BTreeDb' ) !== false ){
		$class = str_replace( '\\', DS, $class );
        $class_file = __DIR__ . DS . $class . '.php';
        if( file_exists( $class_file ) ){
            require_once( $class_file );
		}
	}
}