<?php

namespace Cleantalk\Common\BtreeDatabase\Index;

class BTreeLeaf
{
	// Node structure
	private $max_elems_in_node;
	private $key_size;
	private $val_size;
	private $link_size;
	private $eod;
	private $end_of_node;
	private $elem_size;
	private $leaf_size;

	public $key    = null;
	public $parent = null;

	public $link;
	public $link_left;
	public $link_parent;

	public $elements = array();

	private $size;
	private $stream;
	
    /**
     * BTreeLeaf constructor.
     * 
     * Creates new BTree or opens existing one.
     *
     * @param array $params Array of params
     * @param array|string $link_or_elems Link to the node or array of elements
     */
	public function __construct($params, $link_or_elems)
	{
		foreach ($params as $param_name => $value) {
			$this->$param_name = $value;
		}
		
		if (is_array($link_or_elems)) {
			$this->elements = $link_or_elems;
		} else {
			$this->link = $link_or_elems === '' ? $this->link_size : $link_or_elems;

			fseek($this->stream, $this->link); // Set position to search
			
			$raw_leaf = fread($this->stream, $this->leaf_size); // Read node
			$this->unserialize($raw_leaf);
		}
	}

	/**
	 * Inserting element to the leaf and sorting elements by key
	 * 
	 * @param string $key
	 * @param string $val
	 * @param string $link
	 * 
	 * @return void
	 */
	public function insert($key, $val, $link = '')
	{
		$this->elements[] = array(
			'key' => $key,
			'val' => $val,
			'link' => $link,
		);
		$this->size++;
		
		$keys = array_column($this->elements, 'key');
		array_multisort($keys, SORT_ASC, SORT_NUMERIC, $this->elements);
	}
	
	/**
	 * Searching for element in leaf using a key
	 *
	 * @param int $key_to_search
	 *
	 * @return false|array of BTreeLeafNode
	 */
	public function searchForKey($key_to_search)
    {
        $first_node = new BTreeLeafNode(reset($this->elements));
        $last_node = new BTreeLeafNode(end($this->elements));

		$out = false;
		
		if ($this->isEmpty()) {
			return false;
        }

		// Leaf contains the exact key. Get all nodes with this key.
		if (in_array($key_to_search, array_column($this->elements, 'key'))) {
            return $this->getNodesByKey($key_to_search);
		}
		
		// Check if it's on the right
		if ($key_to_search > $last_node->key && $last_node->link_right ) {
            $last_node->link = $last_node->link_right;
            return array($last_node);
		}
		
		// Check if it's on the left
		if ($key_to_search < $first_node->key && $this->link_left) {
            $first_node->link = $this->link_left;
            return array($first_node);
		}
   
		$position = $this->binarySearch($key_to_search);
		$node = new BTreeLeafNode($this->elements[$position]);
		
		$node->link = $node->key < $key_to_search ? $node->link_right : $node->link_left;
		
		return array($node);
	}

	/**
	 * Splitting leaf into 3 parts
	 * 
	 * @return array
	 */
	public function split()
	{
		return array(
			'left'   => array_slice( $this->elements, 0, floor( $this->max_elems_in_node / 2 ), true  ),
			'middle' => array_slice( $this->elements, floor( $this->max_elems_in_node / 2 ), 1, true ),
			'right'  => array_slice( $this->elements, floor( $this->max_elems_in_node / 2 ) + 1, null, true ),
		);
	}

	/**
	 * Binary search
	 *
	 * @param int $key_to_search
	 *
	 * @return int
	 */
	private function binarySearch($key_to_search)
	{
		$top = $this->size - 1;
		$bot = 0;
		$position = 0;
		
		while($top >= $bot) {
			$position = (int) floor( ( $top + $bot ) / 2 );
			
			if ($this->elements[ $position ]['key'] < $key_to_search) {
				$bot = $position + 1;
			} elseif($this->elements[ $position ]['key'] > $key_to_search) {
				$top = $position - 1;
			} else {
				break;
			}
		}
		
		return $position;
	}
	
	/**
	 * Get all elements with such key from the node
	 *
	 * @param $key
	 *
	 * @return false|array of BTreeLeafNode
	 */
	private function getNodesByKey($key)
	{
        $out = array();
	    
		foreach($this->elements as $array_key => $element) {
			if ($element['key'] == $key) {
                $out[] = new BTreeLeafNode($element);
            }
		}
		
		return $out ?: false;
	}
	
	/**
	 * Unserialize raw node
	 * 
	 * @param string $leaf__raw
	 * 
	 * @return null|void
	 */
	private function unserialize($leaf__raw)
	{
		if (strlen($leaf__raw) < $this->leaf_size) {
			return null;
		}
		
		$this->link_left = str_replace("\x00", '', substr( $leaf__raw, 0, $this->link_size ));
		$this->link_parent = str_replace("\x00", '', substr( $leaf__raw, $this->link_size, $this->link_size ));
		
		// Cut useless data
		$leaf__raw = substr($leaf__raw, $this->link_size * 2, strpos( $leaf__raw, $this->eod ) - $this->link_size * 2);
		
		// Get data from raw and write it to $this->node
        $previous_link = $this->link_left;
		while ($leaf__raw) {
            $right_link       = str_replace("\x00", '', substr( $leaf__raw, $this->key_size + $this->val_size, $this->link_size ) );
            $this->elements[] = array(
                'key'       => str_replace("\x00", '', substr( $leaf__raw, 0, $this->key_size )),
                'val'       => str_replace("\x00", '', substr( $leaf__raw, $this->key_size, $this->val_size )),
                'link'      => $right_link,
                'link_left' => $previous_link,
            );
            $previous_link    = $right_link;
            $leaf__raw        = substr($leaf__raw, $this->elem_size);
		}

		$this->size = $this->elements ? count($this->elements) : 0;
		
	}

	/**
	 * Serialize node
	 * 
	 * @param string $raw
	 * 
	 * @return string
	 */
	public function serialize($raw = '')
	{
		$raw .= str_pad( $this->link_left, $this->link_size, "\x00" );
		$raw .= str_pad( $this->link_parent, $this->link_size, "\x00" );

		foreach ($this->elements as $elem) {
			$raw .= str_pad( $elem['key'], $this->key_size, "\x00" );
			$raw .= str_pad( $elem['val'], $this->val_size, "\x00" );
			$raw .= str_pad( $elem['link'], $this->link_size, "\x00" );
		}

		$raw .= $this->eod;
		$raw = str_pad( $raw, $this->leaf_size - strlen( $this->end_of_node ), "\x00" );
		$raw .= $this->end_of_node;

		return $raw;
	}
	
	/**
	 * Save node to the file
	 * 
	 * @return int|false
	 */
	public function save()
	{
		fseek($this->stream, $this->link);
		return fwrite($this->stream, $this->serialize());
	}
	
	/**
	 * Check if node is empty
	 * 
	 * @return bool
	 */
	public function isEmpty()
	{
		return ! $this->elements;
	}

	/**
	 * Get size of the node
	 * 
	 * @return mixed
	 */
	public function getSize()
	{
		return $this->size;
	}
}
