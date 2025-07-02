<?php

namespace phpbbseo\usu\core;

use phpbbseo\usu\core\core;

class template_context extends \phpbb\template\context
{
	/** @var core */
	private $core;

	/** @var string */
	private $php_ext;

	/** @var string */
	private $phpbb_root_path;

	/**
	 * Constructor
	 *
	 * @param core			$core
	 *
	 */
	public function __construct(core $core, $php_ext, $phpbb_root_path)
	{
		parent::__construct();

		$this->core = $core;
		$this->php_ext = $php_ext;
		$this->phpbb_root_path = $phpbb_root_path;
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
		switch ($blockname)
		{
			case 'navlinks':
				$forum_id = (int) ($vararray['FORUM_ID'] ?? 0);
				if ($forum_id > 0)
				{
					$forum_data = ['forum_id' => $forum_id, 'forum_name' => $vararray['BREADCRUMB_NAME'] ?? $vararray['FORUM_NAME'] ?? ''];
					if (!empty($forum_data['forum_name']))
					{
						$this->core->prepare_forum_url($forum_data);
					}
					$url_params = "f={$forum_id}";
					$key = isset($vararray['U_BREADCRUMB']) ? 'U_BREADCRUMB' : 'U_VIEW_FORUM';
					$vararray[$key] = $this->core->url_rewrite("{$this->phpbb_root_path}viewforum.{$this->php_ext}", $url_params, true, false, false, true);
				}
				break;

			case 'pagination':
				$page_url = $vararray['PAGE_URL'] ?? '';
				if (isset($page_url) && strstr($page_url, $this->php_ext) !== false)
				{
					$split_url = explode('?', $page_url, 2);
					$file_path = [];
					preg_match("/\/([A-Za-z]+\.{$this->php_ext})$/i", $split_url[0], $file_path);

					if (isset($file_path[1]))
					{
						$vararray['PAGE_URL'] = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", isset($split_url[1]) ? $split_url[1] : false);
					}
				}
				break;

			case 'forumrow':
				$forum_id = (int) ($vararray['FORUM_ID'] ?? 0);
				if ($forum_id > 0)
				{
					$forum_data = ['forum_id' => $forum_id, 'forum_name' => $vararray['FORUM_NAME'] ?? ''];
					if (!empty($forum_data['forum_name']))
					{
						$this->core->prepare_forum_url($forum_data);
					}
				}
				break;
		}

		foreach ($vararray as $varname => &$varval)
		{
			$this->var_value_replace($varname, $varval);
		}

		return parent::assign_block_vars($blockname, $vararray);
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
		$this->var_value_replace($varname, $varval);
		parent::assign_var($varname, $varval);
	}

	/**
	 * Replace variable values based on their names.
	 * 
	 * @param string $varname The name of the variable
	 * @param mixed $varval The value of the variable, passed by reference
	 * @return void
	 */
	private function var_value_replace($varname, &$varval)
	{
		if (strstr($varname, 'U_') !== false || strstr($varname, '_LINK') !== false || strstr($varname, '_URL') !== false)
		{
			$split_url = explode('?', $varval, 2);
			$file_path = [];
			if (preg_match("/\/([A-Za-z]+\.{$this->php_ext})$/i", $split_url[0], $file_path) && isset($file_path[1]))
			{
				switch ($file_path[1])
				{
					case 'viewforum.' . $this->php_ext:
					case 'viewtopic.' . $this->php_ext:
						$fragment = parse_url($split_url[0], PHP_URL_FRAGMENT) ?? '';
						parse_str(str_replace("#{$fragment}", '', str_replace('&amp;', '&', $split_url[1] ?? '')), $url_params);
						if (isset($url_params['p']))
						{
							$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", "p={$url_params['p']}") . $fragment;
						}
						else if (isset($url_params['t']))
						{
							$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", "t={$url_params['t']}") . $fragment;
						}
						else if (isset($url_params['f']))
						{
							$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", "f={$url_params['f']}") . $fragment;
						}
						break;

					default:
						$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", isset($split_url[1]) ? $split_url[1] : false);
				}
			}
		}
	}
}
