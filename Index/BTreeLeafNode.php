<?php

namespace Cleantalk\Common\BtreeDatabase\Index;

class BTreeLeafNode
{
    public $key;
    public $value;

    public $link;
    public $link_right;
    public $link_left;

    /**
     * BTreeLeafNode constructor.
     *
     * @param array $args Array of params
     */
    public function __construct(...$args)
    {
        $args = isset($args[0]) && is_array($args[0]) ? $args[0] : $args;
        $args = array_values($args);

        for ( $i = 0; $i < 4; $i++ ) {
            $args[$i] = isset($args[$i]) ? $args[$i] : null; // Set missing params if there are
        }

        $this->key = $args[0];
        $this->value = $args[1];
        $this->link_right = $args[2];
        $this->link_left = $args[3];
        $this->link = null;
    }

    /**
     * Get value of the node
     *
     * @return null|string
     */
    public function getValue()
    {
        return $this->value;
    }
}
