<?php

namespace Cleantalk\Common\BtreeDatabase;

use Cleantalk\Common\BtreeDatabase\Storage\Storage;
use Cleantalk\Common\BtreeDatabase\Index\BTree;

class FileDB
{
    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var string
     */
    private $name;

    private $meta;

    private $indexes;
    private $indexed_column;
    private $index_type;

    // Query params
    private $columns;
    private $where;
    private $where_columns;
    private $offset;
    private $amount;
    private $data_check;
    private $data;

    /**
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $errors;

    // NEW ADDED
    private $db_location;


    /**
     * FileDB constructor.
     *
     * @param string $db_name Name of the DB
     * @param string $db_location Absolute path to the DB location
     * @throws \Exception
     * @psalm-suppress UndefinedPropertyFetch
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct($db_name, $db_location)
    {
        $this->data_check = 'NOT_CHECKED';

        // Set file storage name
        $this->name = $db_name;
        $this->db_location = $db_location;
        $this->meta = $this->getMetaData();
        $this->storage = new Storage($db_name, $this->meta->cols, $this->db_location);

        // Set indexes only if we have information about them
        if ( $this->meta->indexes ) {
            $this->getIndexes();
        }
    }

    /**
     * Getting metadata and creates new file if not exists
     *
     * @return \ArrayObject
     * @throws \Exception
     */
    private function getMetaData()
    {
        $meta_name = $this->name . '_meta';
        $data = null;

        // dir doesn't exist, make it
        if ( !is_dir($this->db_location) && !mkdir($this->db_location) ) {
            throw new \Exception(sprintf('Directory "%s" was not created', $this->db_location));
        }

        if ( !file_exists($this->db_location . DIRECTORY_SEPARATOR . $meta_name . '.php') ) {
            // Get sample data
            $meta_file_template_path = __DIR__ . '/Index/meta.template';
            require_once $meta_file_template_path;
            $data = isset($$meta_name) ? $$meta_name : array();
        }

        $meta = new \Cleantalk\Common\Storage\Storage($meta_name, $data, $this->db_location);

        if ( !$meta->is_empty() ) {
            $meta->line_length = array_sum(array_column($meta->cols, 'length'));
            $meta->cols_num = count($meta->cols);
        }

        return $meta;
    }

    /**
     * Generates BTree instance if it necessary
     *
     * @return void
     */
    private function getIndexes()
    {
        foreach ( $this->meta->indexes as $index ) {
            // Index file name = databaseName_allColumnsNames.indexType
            $index_name =
                $this->name
                . '_' . lcfirst(
                    array_reduce(
                        $index['columns'],
                        function ($result, $item) {
                            return $result . ucfirst($item);
                        }
                    )
                )
                . '.' . $index['type'];

            // @todo extend indexes on a few columns
            switch ( $index['type'] ) {
                case 'btree':
                default:
                    $this->indexes[$index['columns'][0]] = new BTree(
                        $this->db_location . DIRECTORY_SEPARATOR . $index_name
                    );
                    break;
            }
        }
    }

    /**
     * Checking if the received data is in suitable format.
     *
     * @param array $data
     * @return $this
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function prepareData($data)
    {
        $this->data_check = 'OK';

        if ( isset($data[0]) && !is_array($data[0]) ) {
            $this->data_check = 'FAILED_ON_PREPARE';
            return $this;
        }

        $meta_structure = array_keys($this->meta->cols);
        foreach ( $meta_structure as $meta_key ) {
            foreach ( $data as $data_row ) {
                if ( !array_key_exists($meta_key, $data_row) ) {
                    $this->data_check = false;
                    return $this;
                }
            }
        }

        $this->data = $data;

        return $this;
    }


    /**
     * Inserting the data into the Storage and into the Btree index
     *
     * @return int
     * @throws \Exception
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function insert()
    {
        if ( $this->data_check === 'OK' ) {
            $data = $this->data;
        } else {
            if ( $this->data_check === 'NOT_CHECKED' ) {
                throw new \Exception('Data preparing failed, call method prepareData() before call method insert()');
            }

            throw new \Exception('Data preparing failed, check if data to insert is compatible with meta structure');
        }

        $inserted = 0;
        for ( $number = 0; isset($data[$number]); $number++ ) {
            if ( $this->addIndex($number + 1, $data[$number]) == true ) {
                if ( $this->storage->put($data[$number]) ) {
                    $inserted++;
                }
            }
        }

        if ( $inserted ) {
            $this->meta->rows += $inserted;
            $this->meta->save();
        }

        return $inserted;
    }

    /**
     * Clears the meta and delete the storage
     *
     * @return true
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function delete()
    {
        // Clear indexes
        if ( $this->meta->indexes ) {
            foreach ( $this->meta->indexes as &$index ) {
                // @todo make multiple indexes support
                $column_to_index = $index['columns'][0];

                switch ( $index['type'] ) {
                    case 'bintree':
                        $this->indexes[$column_to_index]->clear_tree();
                        break;
                    case 'btree':
                        $this->indexes[$column_to_index]->clear();
                        break;
                }
                $index['status'] = false;
            }
            unset($index);
        }

        // Reset rows amount
        $this->meta->rows = 0;
        $this->meta->save();

        // Clear and delete a storage
        $this->storage->delete();

        return true;
    }

    /**
     * Set what columns to select
     * Could be skipped
     *
     * @param mixed ...$cols
     *
     * @return $this
     * @throws \Exception
     */
    public function setWhat(...$cols)
    {
        $cols = $cols ?: array_keys($this->meta->cols);

        // Check columns for existence
        $result = $this->checkColumn($cols);
        if ( $result !== true ) {
            throw new \Exception('Unknown column: ' . $result);
        }

        $this->columns = $cols;

        return $this;
    }

    /**
     * Set what columns and values should be selected
     * Check for columns right names
     *
     * @param array $where
     *
     * @return $this
     * @throws \Exception
     */
    public function setWhere($where = [])
    {
        $where = $where ?: array_keys($this->meta->cols);

        $result = $this->checkColumn(array_keys($where));
        if ( $result !== true ) {
            throw new \Exception('Unknown column in where: ' . $result);
        }

        $this->where = $where;
        $this->where_columns = array_keys($where);

        return $this;
    }

    /**
     * Checks and sets limits
     * Slice from the main result to get sub result starting from $offset, $amount length
     *
     * @param int $offset should be more than 0
     * @param int $amount should be more than 0
     *
     * @return $this
     * @throws \Exception
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setLimit($offset, $amount)
    {
        if ( !is_int($offset) && $offset >= 0 ) {
            throw new \Exception('Offset value is wrong: ' . $offset);
        }

        if ( !is_int($amount) && $amount > 0 ) {
            throw new \Exception('Amount value is wrong: ' . $amount);
        }

        $this->offset = $offset;
        $this->amount = $amount;
        return $this;
    }


    /**
     * Fires the prepared request and check column names if passed
     * Is no columns passed to select, returns all columns in result
     *
     * @param mixed ...$cols
     *
     * @return array|bool
     * @throws \Exception
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function select(...$cols)
    {
        // Set what columns to select if it's not
        if ( !$this->columns ) {
            $this->setWhat(...$cols);
        }

        // Set the where if it's not
        if ( !$this->where || !$this->where_columns ) {
            $this->setWhere();
        }

        // Check is "where" columns are indexed
        if ( $this->where && $this->where_columns ) {
            $this->isWhereIndexed();
        }

        $result = $this->getData();

        if ( $result && is_array($result) ) {
            // Filter by requested columns
            foreach ( $result as &$item ) {
                foreach ( $item as $column_name => $_value ) {
                    if ( !in_array($column_name, $this->columns) ) {
                        unset($item[$column_name]);
                    }
                }
            }
            unset($item);

            // Filter by limit
            $result = array_slice($result, $this->offset, $this->amount);
        }

        return $result;
    }

    /**
     * Recursive
     * Check columns for existence
     *
     * @param string|array $column
     *
     * @return string|bool
     */
    private function checkColumn($column)
    {
        if ( is_array($column) ) {
            foreach ( $column as $col ) {
                $result = $this->checkColumn($col);
                if ( $result !== true ) {
                    return $result;
                }
            }
        } elseif ( !isset($this->meta->cols[$column]) ) {
            return $column;
        }

        return true;
    }

    /**
     * Gathering data from the Btree index
     *
     * @return array|false
     */
    private function getData()
    {
        $addresses = array();

        foreach ( $this->where as $values ) {
            switch ( $this->index_type ) {
                case 'btree':
                    foreach ( $values as $value ) {
                        $tree_result = $this->indexes[$this->indexed_column]->get($value);
                        if ( $tree_result !== false ) {
                            foreach ( $tree_result as $node ) {
                                $addresses[] = $node->getValue();
                            }
                        }
                    }
                    break;
            }
        }

        return $this->storage->get($addresses);
    }

    /**
     * Recursive
     * Check columns for existence
     *
     * @param $column
     *
     * @return bool
     * @psalm-suppress UnusedReturnValue
     */
    private function isWhereIndexed($column = null)
    {
        $column = $column ?: $this->where_columns;

        // Recursion
        if ( is_array($column) ) {
            foreach ( $column as $column_name ) {
                $result = $this->isWhereIndexed($column_name);
                if ( $result !== true ) {
                    return $result;
                }
            }
            // One of where is not indexed
        } else {
            $indexed = false;
            foreach ( $this->meta->indexes as $index ) {
                if ( in_array($column, $index['columns'], true) && $index['status'] === 'ready' ) {
                    $indexed = true;
                    $this->index_type = $index['type'];
                    $this->indexed_column = $column;
                }
            }

            if ( !$indexed ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Adds index into the Btree
     *
     * @param $number
     * @param $data
     * @return array
     * @throws \Exception
     */
    private function addIndex($number, $data)
    {
        foreach ( $this->meta->indexes as $key => &$index ) {
            // @todo this is a crunch
            $column_to_index = $index['columns'][0];

            $value_to_index = $data[$column_to_index];

            switch ( $index['type'] ) {
                case 'btree':
                    $result = $this->indexes[$column_to_index]->put($value_to_index, $this->meta->rows + $number);
                    break;
                default:
                    $result = false;
                    break;
            }

            if ( is_int($result) && $result > 0 ) {
                $index['status'] = 'ready';
                $out[$key] = true;
            } elseif ( $result === true ) {
                throw new \Exception(
                    'Insertion: Duplicate key for column "' . $index . '": ' . $data[array_search($index, $column_to_index)]
                );
            } elseif ( $result === false ) {
                throw new \Exception(
                    'Insertion: No index added for column "' . $index . '": ' . array_search($index, $column_to_index)
                );
            } else {
                $out[$key] = false;
            }
        }
        unset($index);

        return $out;
    }
}
