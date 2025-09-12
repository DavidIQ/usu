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

			case 'forumrow.subforum':
				$split_url = explode('?', $vararray['U_SUBFORUM'], 2);
				if (isset($split_url[1]))
				{
					$query_string = str_replace('&amp;', '&', $split_url[1]);
					parse_str($query_string, $url_params);
					$subforum_id = isset($url_params['f']) ? (int) $url_params['f'] : 0;
					$forum_data = ['forum_id' => $subforum_id, 'forum_name' => $vararray['SUBFORUM_NAME'] ?? ''];
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
						$fragment = parse_url($split_url[1], PHP_URL_FRAGMENT) ?? '';
						$fragment = !empty($fragment) ? "#{$fragment}" : '';
						$url_params = [];
						parse_str(str_replace($fragment, '', str_replace('&amp;', '&', isset($split_url[1]) ? $split_url[1] : '')), $url_params);
						$param_value = '';
						$check_params = ['p', 't', 'f'];
						foreach ($check_params as $param)
						{
							if ($this->get_param($url_params, $param, $fragment, $param_value))
							{
								$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", "{$param}={$param_value}{$url_params}", true, false, false, !empty($url_params)) . $fragment;
								break;
							}
						}
						break;

					default:
						// need to skip any subdirectories in the URL not just admin
						$board_url = generate_board_url();
						$basic_url = str_replace([$this->phpbb_root_path, $board_url], '', $split_url[0]);
						$basic_url = str_starts_with($basic_url, '/') ? substr($basic_url, 1) : $basic_url;
						if (substr_count($basic_url, '/') === 0)
						{
							$varval = $this->core->url_rewrite("{$this->phpbb_root_path}{$file_path[1]}", isset($split_url[1]) ? $split_url[1] : false);
						}
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

	/**
	 * Get a parameter from the URL parameters array.
	 *
	 * @param array|string $params The URL parameters array
	 * @param string $param_key The key of the parameter to retrieve
	 * @param string $fragment The fragment to remove from the parameter value
	 * @param string &$param_value The variable to store the retrieved parameter value
	 * 
	 * @return bool True if the parameter was found and retrieved, false otherwise
	 */
	private function get_param(array|string &$params, string $param_key, string $fragment, string &$param_value): bool
	{
		$value = isset($params[$param_key]) ? str_replace($fragment, '', $params[$param_key]) : false;
		if ($value !== false)
		{
			$param_value = $value;
			$params = array_filter($params, function ($key) use ($param_key)
			{
				return $key !== $param_key;
			}, ARRAY_FILTER_USE_KEY);
			$params = !empty($params) ? '&amp;' . http_build_query($params) : '';
			return true;
		}
		else
		{
			$param_value = '';
			return false;
		}
	}
}
