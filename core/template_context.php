<?php

namespace phpbbseo\usu\core;

class template_context extends \phpbb\template\context
{
	/**
	* Assign key variable pairs from an array to a specified block
	*
	* @param string $blockname Name of block to assign $vararray to
	* @param array $vararray A hash of variable name => value pairs
	* @return true
	*/
	public function assign_block_vars($blockname, array $vararray)
	{
        // TODO: based on current location re-assign URL variables
        parent::assign_block_vars($blockname, $vararray);
    }
}