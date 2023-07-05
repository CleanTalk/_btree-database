# CleanTalk file based database - 

## Install
```
composer require cleantalk/btree_database
```

## Using

Connect database to your project:

```php
<?php

use Cleantalk\Common\BtreeDatabase\FileDB;

//require initialization and autoloader
require_once 'vendor/autoload.php';

$db_location = __DIR__ . '/data';

// Example array contains networks
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
