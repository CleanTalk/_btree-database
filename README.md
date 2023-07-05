# CleanTalk file based database - 

## Install
```
composer require cleantalk/btree_database
```

## Using
1. Configure database metadata in /data, see the example test_db_meta:
```php
<?php
$test_db_meta = array (
  'line_length' => 26,
  'cols' => 
  array (
    'network' => 
    array (
      'type' => 'int',
      'length' => 11,
    ),
    'mask' => 
    array (
      'type' => 'int',
      'length' => 11,
    ),
    'status' => 
    array (
      'type' => 'int',
      'length' => 2,
    ),
    'is_personal' => 
    array (
      'type' => 'int',
      'length' => 2,
    ),
  ),
  'description' => 'Test',
  'indexes' => 
  array (
    0 => 
    array (
      'columns' => 
      array (
        0 => 'network',
      ),
      'status' => false,
      'type' => 'btree',
    ),
  ),
  'cols_num' => 4,
  'rows' => 0,
);
```
2. Then connect database to your project:
```php
<?php

use Cleantalk\Common\BtreeDatabase\FileDB;

//require initialization and autoloader
require_once 'vendor/autoload.php';

$db_location = __DIR__ . '/data';
$data = array(
    'network'     => '2130706433',
    'mask'        => '4294967295',
    'status'      => 1,
    'is_personal' => 0,
);


try {
    // Instantiate the DB main controller
    $file_db = new FileDB('fw_nets', $db_location);

    // Prepare and insert data using fluid interface
    $insert = $file_db->prepareData(array($data))->insert();

    // Perform request using fluid interface
    $db_results = $file_db
        ->setWhere( array( 'network' => array('2130706433'), ) )
        ->setLimit( 0, 20 )
        ->select( 'network', 'mask', 'status', 'is_personal' );
        
    //Use ->delete to delete database
    //$file_db->delete();
} catch (\Exception $e) {
    print $e->getMessage();
}

var_dump($db_results);
```
