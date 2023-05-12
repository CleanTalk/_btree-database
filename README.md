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
//use Binary tree class
use BTreeDb\File\FileDB;

require_once 'vendor/autoload.php';
require_once 'vendor/cleantalk/btree_database/init.php';

$file_db = new FileDB('test_db');
//use Helper class to convert canonical record to longint records.
$ip_array = \BTreeDb\Common\Helper::ip__canonical_to_long('127.0.0.1/32');
$data = array(
    'network'         => $ip_array['network'],
    'mask'        => $ip_array['mask'],
    'status'      => 2,
    'is_personal' => 0,
);
//use fluid interface to prepare data to insert
$insert = $file_db->prepareData(array($data))->insert();
if ($insert === 0 && $file_db->errors::check()){
    error_log('[Errors:]: ' . var_export($file_db->errors::get_all(),true));
}
//use fluid interface to perform request
$db_results = $file_db
    ->setWhere( array( 'network' => array('2130706433'), ) )
    ->setLimit( 0, 20 )
    ->select( 'network', 'mask', 'status', 'is_personal' );
if (!$file_db->errors::check()){
    error_log('[Select result:]: ' . var_export($db_results,true));
} else {
    error_log('[Errors:]: ' . var_export($file_db->errors::get_all(),true));
}
//Use ->delete to delete database
//$file_db->delete();
```
