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

	/** @var string */
	private $phpbb_adm_relative_path;

	/**
	 * Constructor
	 *
	 * @param core			$core
	 *
	 */
	public function __construct(core $core, $php_ext, $phpbb_root_path, $phpbb_adm_relative_path)
	{
		parent::__construct();

		$this->core = $core;
		$this->php_ext = $php_ext;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->phpbb_adm_relative_path = $phpbb_adm_relative_path;
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
		if (is_string($varval) && strstr($varval, $this->phpbb_adm_relative_path) !== false)
		{
			// Don't rewrite admin URLs
			return;
		}

		if (str_starts_with($varname, 'U_') || str_ends_with($varname, '_LINK') || str_ends_with($varname, '_URL'))
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
						$fragment = !empty($fragment) ? "#{$fragment}" : '';
						$url_params = [];
						parse_str(str_replace($fragment, '', str_replace('&amp;', '&', isset($split_url[1]) ? $split_url[1] : '')), $url_params);
						$start = isset($url_params['start']) ? "&amp;start={$url_params['start']}" : '';
						if (isset($url_params['p']))
						{
							$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", "p={$url_params['p']}") . $fragment;
						}
						else if (isset($url_params['t']))
						{
							$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", "t={$url_params['t']}{$start}") . $fragment;
						}
						else if (isset($url_params['f']))
						{
							$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", "f={$url_params['f']}{$start}") . $fragment;
						}
						break;

					default:
						$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", isset($split_url[1]) ? $split_url[1] : false);
				}
			}
		}
		else if ($varname === 'LEGEND')
		{
			$pattern = '/href\s*=\s*(?:"
                 ([^"]*)      # double-quoted
               "|\'([^\']*)\'   # single-quoted
               |([^>\s]+)     # unquoted
               )/ix';

			$matches = [];
			preg_match_all($pattern, $varval, $matches);

			$hrefs = array_filter(array_merge(
				$matches[1],
				$matches[2],
				$matches[3]
			));

			foreach ($hrefs as $href)
			{
				$split_url = explode('?', $href, 2);
				if (isset($split_url[1]))
				{
					$query_string = str_replace('&amp;', '&', $split_url[1]);
					parse_str($query_string, $url_params);
					$group_id = isset($url_params['g']) ? (int) $url_params['g'] : 0;
					$user_id = isset($url_params['u']) ? (int) $url_params['u'] : 0;
					if ($group_id > 0)
					{
						$url = "{$this->phpbb_root_path}memberlist.{$this->php_ext}";
						$this->core->prepare_url('group', $url, $group_id);
						$rewritten_url = $this->core->url_rewrite($url, $query_string, true, false, false, true);
						$varval = str_replace($href, $rewritten_url, $varval);
					}
					else if ($user_id > 0)
					{
						$url = "{$this->phpbb_root_path}memberlist.{$this->php_ext}";
						$this->core->prepare_url('user', $url, $user_id);
						$rewritten_url = $this->core->url_rewrite($url, $query_string);
						$varval = str_replace($href, $rewritten_url, $varval);
					}
				}
			}
		}
	}
}
