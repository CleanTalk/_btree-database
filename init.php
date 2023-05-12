<?php
/**
 * Attaches
 *
 * Sets all main constants
 *
 * Version: 1.1.0
 *
 * @ToDo Need to replace defining the constants into the main class constructor
 */

if ( ! defined('DS') ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}

// Directories
define( 'CT_BTREEDB_ROOT', realpath(__DIR__ ) . DS );
define( 'CT_BTREEDB_SITE_ROOT', realpath( CT_BTREEDB_ROOT . '..') . DS );
define( 'CT_BTREEDB_LIB', CT_BTREEDB_ROOT . 'lib' . DS );
define( 'CT_BTREEDB_DATA', CT_BTREEDB_ROOT . 'data' . DS );
