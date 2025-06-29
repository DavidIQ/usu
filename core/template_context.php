<?php

namespace phpbbseo\usu\core;

use phpbbseo\usu\core\core;

class template_context extends \phpbb\template\context
{
	/** @var core */
	private $core;

	/** @var string */
	private $php_ext;

	/**
	* Constructor
	*
	* @param core			$core
	*
	*/
	public function __construct(core $core, $php_ext)
	{
		parent::__construct();

		$this->core = $core;
		$this->php_ext = $php_ext;
	}

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

	/**
	* Assign a single scalar value to a single key.
	*
	* Value can be a string, an integer or a boolean.
	*
	* @param string $varname Variable name
	* @param string $varval Value to assign to variable
	* @return true
	*/
	public function assign_var($varname, $varval)
	{
		switch ($varname)
		{
			case 'U_VIEW_FORUM':
			case 'U_VIEW_OLDER_TOPIC':
			case 'U_VIEW_NEWER_TOPIC':
				$split_url = explode('?', $varval, 2);
				if (strstr($split_url[0], 'viewforum.' . $this->php_ext) !== false)
				{
					$varval = $this->core->url_rewrite($split_url[0], $split_url[1], true, false, false, true);
				}
				break;

			case 'U_TOPIC':
				$split_url = explode('?', $varval, 2);
				if (strstr($split_url[0], 'viewtopic.' . $this->php_ext) !== false)
				{
					$varval = $this->core->url_rewrite($split_url[0], $split_url[1], true, false, false, true);
				}
				break;
		}
		parent::assign_var($varname, $varval);
	}
}
