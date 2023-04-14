<?php
/**
 * Attaches
 * /lib/autoloader.php
 *
 * Sets all main constants
 *
 * Version: 1.0.0
 */

if ( ! defined('DS') ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}

// Directories
define( 'CT_BTREEDB_ROOT', realpath(__DIR__ ) . DS );
define( 'CT_BTREEDB_SITE_ROOT', realpath( CT_BTREEDB_ROOT . '..') . DS );
define( 'CT_BTREEDB_LIB', CT_BTREEDB_ROOT . 'lib' . DS );
define( 'CT_BTREEDB_DATA', CT_BTREEDB_ROOT . 'data' . DS );

require_once 'lib' . DS . 'autoloader.php';

// Create empty error object




