<?php

/**
 *
 * @package Ultimate phpBB SEO Friendly URL
 * @version $$
 * @copyright (c) 2017 www.phpBB-SEO.ir
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace phpbbseo\usu\event;

/**
 * @ignore
 */

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbbseo\usu\core\core;
use phpbb\config\config;
use phpbb\auth\auth;
use phpbb\template\template;
use phpbb\user;
use phpbb\request\request;
use phpbb\db\driver\driver_interface as db_driver;
use phpbb\language\language;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var core */
	private $core;

	/** @var config */
	private $config;

	/** @var auth */
	private $auth;

	/** @var template */
	private $template;

	/** @var user */
	private $user;

	/** @var request */
	private $request;

	/** @var db_driver */
	private $db;

	/** @var language */
	private $language;

	/**
	 * Current $phpbb_root_path
	 * @var string
	 */
	private $phpbb_root_path;

	/**
	 * Current $php_ext
	 * @var string
	 */
	private $php_ext;

	private $forum_id = 0;

	private $topic_id = 0;

	private $post_id = 0;

	private $start = 0;

	private $hilit_words = '';

	/**
	 * Constructor
	 *
	 * @param core			$core
	 * @param config			$config				Config object
	 * @param auth			$auth				Auth object
	 * @param template		$template			Template object
	 * @param user			$user				User object
	 * @param request		$request			Request object
	 * @param db_driver		$db					Database object
	 * @param language		$language			Language object
	 * @param string			$phpbb_root_path	Path to the phpBB root
	 * @param string			$php_ext			PHP file extension
	 *
	 */
	public function __construct(core $core, config $config, auth $auth, template $template, user $user, request $request, db_driver $db, language $language, $phpbb_root_path, $php_ext)
	{
		$this->core = $core;
		$this->template = $template;
		$this->user = $user;
		$this->config = $config;
		$this->auth = $auth;
		$this->request = $request;
		$this->db = $db;
		$this->language = $language;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.common'										=> 'core_common',
			'core.user_setup'									=> 'core_user_setup',
			'core.append_sid'									=> 'core_append_sid',
			'core.pagination_generate_page_link'				=> 'core_pagination_generate_page_link',
			'core.page_header_after'							=> 'core_page_header_after',
			'core.page_footer'									=> 'core_page_footer',
			'core.viewforum_modify_topicrow'					=> 'core_viewforum_modify_topicrow',
			'core.viewtopic_modify_page_title'					=> 'core_viewtopic_modify_page_title',
			'core.viewtopic_modify_post_row'					=> 'core_viewtopic_modify_post_row',
			'core.memberlist_view_profile'						=> 'core_memberlist_view_profile',
			'core.modify_username_string'						=> 'core_modify_username_string',
			'core.submit_post_end'								=> 'core_submit_post_end',
			'core.posting_modify_template_vars'					=> 'core_posting_modify_template_vars',
			'core.display_user_activity_modify_actives'			=> 'core_display_user_activity_modify_actives',
			'core.display_forums_modify_template_vars'			=> 'core_display_forums_modify_template_vars',
			'core.display_forums_modify_category_template_vars'	=> 'core_display_forums_modify_template_vars',
			'core.viewforum_modify_page_title'					=> 'core_viewforum_modify_page_title',
			'core.viewtopic_modify_forum_id'					=> 'core_viewtopic_modify_forum_id',
			'core.search_modify_tpl_ary'						=> 'core_search_modify_tpl_ary',
		];
	}

	public function core_user_setup($event)
	{
		if (empty($this->core->seo_opt['url_rewrite']))
		{
			return;
		}

		$user_data = $event['user_data'];

		switch ($this->core->seo_opt['req_file'])
		{
			case 'viewforum':
				global $forum_data; // god save the hax

				if ($forum_data)
				{
					if ($forum_data['forum_topics_per_page'])
					{
						$this->config['topics_per_page'] = $forum_data['forum_topics_per_page'];
					}

					$start = $this->core->seo_chk_start($this->start, $this->config['topics_per_page']);

					if ($this->start != $start)
					{
						$this->start = (int) $start;
						$this->request->overwrite('start', $this->start);
					}

					$this->forum_id = max(0, (int) $forum_data['forum_id']);
					$this->core->prepare_forum_url($forum_data);
					$this->core->seo_path['canonical'] = $this->core->drop_sid(append_sid("{$this->phpbb_root_path}viewforum.{$this->php_ext}", "f={$this->forum_id}&amp;start={$this->start}"));

					$this->core->set_parent_urls($forum_data);

					$default_sort_days = (!empty($user_data['user_topic_show_days'])) ? $user_data['user_topic_show_days'] : 0;
					$default_sort_key = (!empty($user_data['user_topic_sortby_type'])) ? $user_data['user_topic_sortby_type'] : 't';
					$default_sort_dir = (!empty($user_data['user_topic_sortby_dir'])) ? $user_data['user_topic_sortby_dir'] : 'd';

					$mark_read = $this->request->variable('mark', '');
					$sort_days = $this->request->variable('st', $default_sort_days);
					$sort_key = $this->request->variable('sk', $default_sort_key);
					$sort_dir = $this->request->variable('sd', $default_sort_dir);
					$keep_mark = in_array($mark_read, ['topics', 'topic', 'forums', 'all']) ? (bool) ($user_data['is_registered'] || $this->config['load_anon_lastread']) : false;

					$this->core->seo_opt['zero_dupe']['redir_def'] = [
						'hash'		=> ['val' => $this->request->variable('hash', ''), 'keep' => $keep_mark],
						'f'		=> ['val' => $this->forum_id, 'keep' => true, 'force' => true],
						'st'		=> ['val' => $sort_days, 'keep' => true],
						'sk'		=> ['val' => $sort_key, 'keep' => true],
						'sd'		=> ['val' => $sort_dir, 'keep' => true],
						'mark'		=> ['val' => $mark_read, 'keep' => $keep_mark],
						'mark_time'	=> ['val' => $this->request->variable('mark_time', 0), 'keep' => $keep_mark],
						'start'		=> ['val' => $this->start, 'keep' => true],
					];

					$this->core->zero_dupe();
				}
				else
				{
					if ($this->core->seo_opt['redirect_404_forum'])
					{
						$this->core->seo_redirect($this->core->seo_path['phpbb_url']);
					}
					else
					{
						send_status_line(404, 'Not Found');
					}
				}

				break;

			case 'viewtopic':
				global $topic_data, $topic_replies, $forum_id, $post_id, $view; // god save the hax

				if (empty($topic_data))
				{
					if ($this->core->seo_opt['redirect_404_topic'])
					{
						$this->core->seo_redirect($this->core->seo_path['phpbb_url']);
					}
					else
					{
						send_status_line(404, 'Not Found');
					}
					return;
				}

				$this->topic_id = $topic_id = (int) $topic_data['topic_id'];
				$this->forum_id = $forum_id;

				$this->core->set_parent_urls($topic_data);

				if (!empty($topic_data['topic_url']) || (isset($topic_data['topic_url']) && !empty($this->core->seo_opt['sql_rewrite'])))
				{
					if ($topic_data['topic_type'] == POST_GLOBAL)
					{
						// Let's make sure user will see global annoucements
						// $this->auth->cache[$forum_id]['f_read'] = 1;

						$_parent = $this->core->seo_static['global_announce'];
					}
					else
					{
						$this->core->prepare_forum_url($topic_data);
						$_parent = $this->core->seo_url['forum'][$forum_id];
					}

					if (!$this->core->check_url('topic', $topic_data['topic_url'], $_parent))
					{
						if (!empty($topic_data['topic_url']))
						{
							// Here we get rid of the seo delim (-t) and put it back even in simple mod
							// to be able to handle all cases at once
							$_url = preg_replace('`' . $this->core->seo_delim['topic'] . '$`i', '', $topic_data['topic_url']);
							$_title = $this->core->get_url_info('topic', $_url . $this->core->seo_delim['topic'] . $topic_id, 'title');
						}
						else
						{
							$_title = $this->core->modrtype > 2 ? censor_text($topic_data['topic_title']) : '';
						}

						unset($this->core->seo_url['topic'][$topic_id]);

						$topic_data['topic_url'] = $this->core->get_url_info('topic', $this->core->prepare_url('topic', $_title, $topic_id, $_parent, ((empty($_title) || ($_title == $this->core->seo_static['topic'])) ? true : false)), 'url');

						unset($this->core->seo_url['topic'][$topic_id]);

						if ($topic_data['topic_url'])
						{
							// Update the topic_url field for later re-use
							$sql = "UPDATE " . TOPICS_TABLE . " SET topic_url = '" . $this->db->sql_escape($topic_data['topic_url']) . "'
								WHERE topic_id = $topic_id";
							$this->db->sql_query($sql);
						}
					}
				}
				else
				{
					$topic_data['topic_url'] = '';
				}

				$this->core->prepare_topic_url($topic_data, $this->forum_id);

				if (!$this->request->is_set('start'))
				{
					if (!empty($post_id))
					{
						$this->start = floor((int) $topic_data['prev_posts'] / $this->config['posts_per_page']) * $this->config['posts_per_page'];
					}
				}

				$start = $this->core->seo_chk_start($this->start, $this->config['posts_per_page']);

				if ($this->start != $start)
				{
					$this->start = (int) $start;
					if (empty($post_id))
					{
						$this->request->overwrite('start', $this->start);
					}
				}

				$this->core->seo_path['canonical'] = $this->core->drop_sid(append_sid("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", "f={$this->forum_id}&amp;t={$topic_id}&amp;start={$this->start}"));

				if ($this->core->seo_opt['zero_dupe']['on'])
				{
					$highlight_match = $highlight = '';

					if ($this->hilit_words)
					{
						$highlight_match = phpbb_clean_search_string($this->hilit_words);
						$highlight = urlencode($highlight_match);
						$highlight_match = str_replace('\*', '\w+?', preg_quote($highlight_match, '#'));
						$highlight_match = preg_replace('#(?<=^|\s)\\\\w\*\?(?=\s|$)#', '\w+?', $highlight_match);
						$highlight_match = str_replace(' ', '|', $highlight_match);
					}

					if ($post_id && !$view && !$this->core->set_do_redir_post())
					{
						$this->core->seo_opt['zero_dupe']['redir_def'] = [
							'p'		=> ['val' => $post_id, 'keep' => true, 'force' => true, 'hash' => "p$post_id"],
							'hilit'	=> ['val' => (($highlight_match) ? $highlight : ''), 'keep' => !empty($highlight_match)],
						];
					}
					else
					{
						$default_sort_days = (!empty($user_data['user_topic_show_days'])) ? $user_data['user_topic_show_days'] : 0;
						$default_sort_key = (!empty($user_data['user_topic_sortby_type'])) ? $user_data['user_topic_sortby_type'] : 't';
						$default_sort_dir = (!empty($user_data['user_topic_sortby_dir'])) ? $user_data['user_topic_sortby_dir'] : 'd';

						$sort_days = $this->request->variable('st', $default_sort_days);
						$sort_key = $this->request->variable('sk', $default_sort_key);
						$sort_dir = $this->request->variable('sd', $default_sort_dir);
						$seo_watch = $this->request->variable('watch', '');
						$seo_unwatch = $this->request->variable('unwatch', '');
						$seo_bookmark = $this->request->variable('bookmark', 0);
						$keep_watch = (bool) ($seo_watch == 'topic' && $user_data['is_registered']);
						$keep_unwatch = (bool) ($seo_unwatch == 'topic' && $user_data['is_registered']);
						$keep_hash = (bool) ($keep_watch || $keep_unwatch || $seo_bookmark);
						$seo_uid = max(0, $this->request->variable('uid', 0));

						$this->core->seo_opt['zero_dupe']['redir_def'] = [
							'uid'		=> ['val' => $seo_uid, 'keep' => (bool) ($keep_hash && $seo_uid)],
							'f'		=> ['val' => $forum_id, 'keep' => true, 'force' => true],
							't'		=> ['val' => $topic_id, 'keep' => true, 'force' => true, 'hash' => $post_id ? "p{$post_id}" : ''],
							'p'		=> ['val' => $post_id, 'keep' => ($post_id && $view == 'show' ? true : false), 'hash' => "p{$post_id}"],
							'watch'		=> ['val' => $seo_watch, 'keep' => $keep_watch],
							'unwatch'	=> ['val' => $seo_unwatch, 'keep' => $keep_unwatch],
							'bookmark'	=> ['val' => $seo_bookmark, 'keep' => (bool) ($user_data['is_registered'] && $this->config['allow_bookmarks'] && $seo_bookmark)],
							'start'		=> ['val' => $this->start, 'keep' => true, 'force' => true],
							'hash'		=> ['val' => $this->request->variable('hash', ''), 'keep' => $keep_hash],
							'st'		=> ['val' => $sort_days, 'keep' => true],
							'sk'		=> ['val' => $sort_key, 'keep' => true],
							'sd'		=> ['val' => $sort_dir, 'keep' => true],
							'view'		=> ['val' => $view, 'keep' => $view == 'print' ? (bool) $this->auth->acl_get('f_print', $forum_id) : (($view == 'viewpoll' || $view == 'show') ? true : false)],
							'hilit'		=> ['val' => (($highlight_match) ? $highlight : ''), 'keep' => (bool) !(!$user_data['is_registered'] && $this->core->seo_opt['rem_hilit'])],
						];

						if ($this->core->seo_opt['zero_dupe']['redir_def']['bookmark']['keep'])
						{
							// Prevent unessecary redirections
							// Note : bookmark, watch and unwatch cases could just not be handled by the zero dupe (no redirect at all when used),
							// but the handling as well acts as a security shield so, it's worth it ;)
							unset($this->core->seo_opt['zero_dupe']['redir_def']['start']);
						}
					}

					$this->core->zero_dupe();
				}

				break;

			case 'memberlist':
				if ($this->request->is_set('un'))
				{
					$un = rawurldecode($this->request->variable('un', '', true));

					if (!$this->core->is_utf8($un))
					{
						$un = utf8_normalize_nfc(utf8_recode($un, 'ISO-8859-1'));
					}

					$this->request->overwrite('un', $un);
				}

				break;
		}
	}

	public function core_common($event)
	{
		if (empty($this->core->seo_opt['url_rewrite']))
		{
			return;
		}

		// this helps fixing several cases of relative links
		define('PHPBB_USE_BOARD_URL_PATH', true);

		$this->start = max(0, $this->request->variable('start', 0));

		switch ($this->core->seo_opt['req_file'])
		{
			case 'viewforum':
				$this->forum_id = max(0, $this->request->variable('f', 0));

				if (!$this->forum_id)
				{
					$this->core->get_forum_id($this->forum_id);

					if (!$this->forum_id)
					{
						// here we need to find out if the uri really was a forum one
						if (!preg_match('`^.+?\.' . $this->php_ext . '(\?.*)?$`', $this->core->seo_path['uri']))
						{
							// request url is rewriten
							// re-route request to app.php
							global $phpbb_container; // god save the hax

							// we need to overwrite couple SERVER variable to simulate direct app.php call
							// start with scripts
							$script_fix_list = ['SCRIPT_FILENAME', 'SCRIPT_NAME', 'PHP_SELF'];
							foreach ($script_fix_list as $varname)
							{
								if ($this->request->is_set($varname, \phpbb\request\request_interface::SERVER))
								{
									$value = $this->request->server($varname);
									if ($value)
									{
										$value = preg_replace('`^(.*?)viewforum\.' . $this->php_ext . '((\?|/).*)?$`', '\1app.' . $this->php_ext . '\2', $value);
										$this->request->overwrite($varname, $value, \phpbb\request\request_interface::SERVER);
									}
								}
							}

							// then fix query strings
							$qs_fix_list = ['QUERY_STRING', 'REDIRECT_QUERY_STRING'];
							foreach ($qs_fix_list as $varname)
							{
								if ($this->request->is_set($varname, \phpbb\request\request_interface::SERVER))
								{
									$value = $this->request->server($varname);
									if ($value)
									{
										$value = preg_replace('`^forum_uri=[^&]*(&amp;|&)start=((&amp;|&).*)?$`i', '', $value);
										$this->request->overwrite($varname, $value, \phpbb\request\request_interface::SERVER);
									}
								}
							}

							// Start session management
							$this->user->session_begin();
							$this->auth->acl($this->user->data);
							$this->user->setup('app');

							$http_kernel = $phpbb_container->get('http_kernel');
							$symfony_request = $phpbb_container->get('symfony_request');
							$response = $http_kernel->handle($symfony_request);
							$response->send();
							$http_kernel->terminate($symfony_request, $response);
						}

						if ($this->core->seo_opt['redirect_404_forum'])
						{
							$this->core->seo_redirect($this->core->seo_path['phpbb_url']);
						}
						else
						{
							send_status_line(404, 'Not Found');
						}
					}
					else
					{
						$this->request->overwrite('f', (int) $this->forum_id);
					}
				}

				break;

			case 'viewtopic':
				$this->forum_id = max(0, $this->request->variable('f', 0));
				$this->topic_id = max(0, $this->request->variable('t', 0));
				$this->post_id = max(0, $this->request->variable('p', 0));

				if (!$this->forum_id)
				{
					$this->core->get_forum_id($this->forum_id);

					if ($this->forum_id > 0)
					{
						$this->request->overwrite('f', (int) $this->forum_id);
					}
				}

				$this->hilit_words = $this->request->variable('hilit', '', true);

				if ($this->hilit_words)
				{
					$this->hilit_words = rawurldecode($this->hilit_words);

					if (!$this->core->is_utf8($this->hilit_words))
					{
						$this->hilit_words = utf8_normalize_nfc(utf8_recode($this->hilit_words, 'iso-8859-1'));
					}

					$this->request->overwrite('hilit', $this->hilit_words);
				}

				if (!$this->topic_id && !$this->post_id)
				{
					if ($this->core->seo_opt['redirect_404_forum'])
					{
						if ($this->forum_id && !empty($this->core->seo_url['forum'][$this->forum_id]))
						{
							$this->core->seo_redirect(append_sid("{$this->phpbb_root_path}viewforum.{$this->php_ext}", 'f=' . $this->forum_id));
						}
						else
						{
							$this->core->seo_redirect($this->core->seo_path['phpbb_url']);
						}
					}
					else
					{
						send_status_line(404, 'Not Found');
					}
				}

				break;
		}
	}

	public function core_page_header_after($event)
	{
		$this->template->assign_vars([
			'SEO_PHPBB_URL'		=> $this->core->seo_path['phpbb_url'],
			'SEO_ROOT_URL'		=> $this->core->seo_path['phpbb_url'],
			'SEO_BASE_HREF'		=> $this->core->seo_opt['seo_base_href'],
			'SEO_START_DELIM'	=> $this->core->seo_delim['start'],
			'SEO_SATIC_PAGE'	=> $this->core->seo_static['pagination'],
			'SEO_EXT_PAGE'		=> $this->core->seo_ext['pagination'],
			'SEO_EXTERNAL'		=> !empty($this->config['seo_ext_links']) ? 1 : '',
			'SEO_EXTERNAL_SUB'	=> !empty($this->config['seo_ext_subdomain']) ? 1 : '',
			'SEO_EXT_CLASSES'	=> !empty($this->config['seo_ext_classes']) ? preg_replace('`[^a-z0-9_|-]+`', '', str_replace(',', '|', trim($this->config['seo_ext_classes'], ', '))) : '',
			'SEO_HASHFIX'		=> $this->core->seo_opt['url_rewrite'] && $this->core->seo_opt['virtual_folder'] ? 1 : '',
			'SEO_PHPEX'			=> $this->php_ext,
		]);

		$page_title = $event['page_title'];

		if (!empty($this->config['seo_append_sitename']) && !empty($this->config['sitename']))
		{
			$event['page_title'] = $page_title && strpos($page_title, $this->config['sitename']) === false ? $page_title . ' - ' . $this->config['sitename'] : $page_title;
		}
	}

	/**
	 * Note : This mod is going to help your site a lot in Search Engines
	 * If You really cannot put this link, you should at least provide us with one visible
	 * (can be small but visible) link on your home page or your forum Index using this code for example :
	 * <a href="http://www.phpBB-SEO.ir/" title="Search Engine Optimization By phpBB SEO">phpBB SEO</a>
	 */
	public function core_page_footer($event)
	{
		if (empty($this->core->seo_opt['copyrights']['title']))
		{
			$this->core->seo_opt['copyrights']['title'] = strpos($this->config['default_lang'], 'fr') !== false  ?  'Optimisation du R&eacute;f&eacute;rencement par phpBB SEO' : 'Search Engine Optimization By phpBB SEO';
		}

		if (empty($this->core->seo_opt['copyrights']['txt']))
		{
			$this->core->seo_opt['copyrights']['txt'] = 'phpBB SEO';
		}

		if ($this->core->seo_opt['copyrights']['img'])
		{
			$output = '<a href="http://www.phpBB-SEO.ir/" title="' . $this->core->seo_opt['copyrights']['title'] . '"><img src="' . $this->core->seo_path['phpbb_url'] . 'ext/phpbbseo/usu/img/phpbb-seo.png" alt="' . $this->core->seo_opt['copyrights']['txt'] . '" width="80" height="15"></a>';
		}
		else
		{
			$output = '<a href="http://www.phpBB-SEO.ir/" title="' . $this->core->seo_opt['copyrights']['title'] . '">' . $this->core->seo_opt['copyrights']['txt'] . '</a>';
		}

		$this->language->lang('TRANSLATION_INFO', (!empty($this->language->lang('TRANSLATION_INFO')) ? $this->language->lang('TRANSLATION_INFO') . '<br>' : '') . $output);

		$this->template->assign_vars([
			'U_CANONICAL'	=> $this->core->get_canonical(),
		]);
	}

	/**
	 * This function is called for the forum lists
	 */
	public function core_display_forums_modify_template_vars($event)
	{
		$row = $event['row'];
		$this->core->prepare_forum_url($row);

		$varname = isset($event['cat_row']) ? 'cat_row' : 'forum_row';
		$template_row = $event[$varname];
		$template_row['U_VIEWFORUM'] = $this->core->url_rewrite("{$this->phpbb_root_path}viewforum.{$this->php_ext}", 'f=' . $row['forum_id'], true, false, false, true);

		$event[$varname] = $template_row;
	}

	public function core_viewforum_modify_page_title($event)
	{
		$forum_data = $event['forum_data'];
		$this->core->prepare_forum_url($forum_data);
	}

	public function core_viewforum_modify_topicrow($event)
	{
		$topic_row = $event['topic_row'];
		$row = $event['row'];

		if ($this->update_topic_list($topic_row, $row))
		{
			$event['topic_row'] = $topic_row;
		}
	}

	public function core_search_modify_tpl_ary($event)
	{
		$tpl_ary = $event['tpl_ary'];
		$row = $event['row'];

		if ($this->update_topic_list($tpl_ary, $row))
		{
			$event['tpl_ary'] = $tpl_ary;
		}
	}

	private function update_topic_list(&$template_row, $row)
	{
		if (isset($template_row['U_VIEW_TOPIC']))
		{
			$forum_id = $row['forum_id'];
			$this->core->prepare_topic_url($row, $forum_id);
			$this->core->prepare_forum_url($row);

			$template_row['U_LAST_POST'] = $this->core->url_rewrite("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", 'p=' . $row['topic_last_post_id'] . '#p' . $row['topic_last_post_id'], true, false, false, true);

			$view_topic_url = $template_row['U_VIEW_TOPIC'];
			$view_topic_url_params = parse_url($view_topic_url, PHP_URL_QUERY);

			$topic_url_with_params = $this->core->url_rewrite("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", $view_topic_url_params, true, false, false, true);
			$template_row['U_VIEW_TOPIC'] = $topic_url_with_params;
			$template_row['U_NEWEST_POST'] = $topic_url_with_params . (strstr($topic_url_with_params, '?') === false ? '?' : '&amp;') . 'view=unread#unread';
			$template_row['U_VIEW_FORUM'] = $this->core->url_rewrite("{$this->phpbb_root_path}viewforum.{$this->php_ext}", "f={$forum_id}", true, false, false, true);
			if (!empty($template_row['U_VIEW_POST']))
			{
				$template_row['U_VIEW_POST'] = $this->core->url_rewrite("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", 'p=' . $row['post_id'] . '#p' . $row['post_id'], true, false, false, true);
			}
			return true;
		}

		return false;
	}

	public function core_viewtopic_modify_forum_id($event)
	{
		$row = $event['topic_data'];
		$forum_id = $event['forum_id'];
		$topic_url = $this->core->prepare_topic_url($row, $forum_id);
		$request_uri = $this->request->server('REQUEST_URI');
		$post_id = $this->request->variable('p', 0);
		if (!$post_id && strstr($request_uri, $topic_url) === false)
		{
			// We need to redirect to the correct URL
			$url_query = parse_url(str_replace('&amp;', '&', $request_uri), PHP_URL_QUERY) ?? '';
			$query_params = [];
			parse_str($url_query, $query_params);
			$topic_query_params = (!isset($query_params['t']) ? "t={$row['topic_id']}" . (!empty($url_query) ? "&amp;" : '') : '') . $url_query;
			$redirect_url = $this->core->url_rewrite("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", $topic_query_params, true, false, false, true);
			if (strstr($redirect_url, $topic_url) !== false)
			{
				return $this->core->seo_redirect($redirect_url);
			}
		}
		$viewtopic_url = $event['viewtopic_url'];
		$event['viewtopic_url'] = $this->core->url_rewrite($viewtopic_url, false, true, false, false, true);
	}

	public function core_viewtopic_modify_post_row($event)
	{
		$post_row = $event['post_row'];
		$row = $event['row'];

		$post_row['U_MINI_POST'] = $this->core->url_rewrite("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", "p={$row['post_id']}", true, false, false, true) . "#p{$row['post_id']}";
		$post_row['U_MINI_POST_VIEW'] = $this->core->url_rewrite("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", "p={$row['post_id']}&amp;view=show", true, false, false, true) . "#p{$row['post_id']}";
		$post_row['U_APPROVE_ACTION'] = append_sid("{$this->phpbb_root_path}mcp.$this->php_ext", "i=queue&amp;p={$row['post_id']}&amp;f={$this->forum_id}&amp;redirect=" . urlencode(str_replace('&amp;', '&', append_sid("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", "f={$this->forum_id}&amp;t={$this->topic_id}&amp;p=" . $row['post_id']) . '#p' . $row['post_id'])));
		$post_row['L_POST_DISPLAY'] = ($row['hide_post']) ? $this->language->lang('POST_DISPLAY', '<a class="display_post" data-post-id="' . $row['post_id'] . '" href="' . append_sid("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", "f={$this->forum_id}&amp;t={$this->topic_id}&amp;p={$row['post_id']}&amp;view=show#p{$row['post_id']}") . '">', '</a>') : '';
		$event['post_row'] = $post_row;
	}

	public function core_viewtopic_modify_page_title($event)
	{
		$this->template->assign_vars([
			'U_PRINT_TOPIC'		=> ($this->auth->acl_get('f_print', $this->forum_id)) ? append_sid("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", "f={$this->forum_id}&amp;t={$this->topic_id}&amp;view=print") : '',
			'U_BOOKMARK_TOPIC'	=> ($this->user->data['is_registered'] && $this->config['allow_bookmarks']) ? append_sid("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", "f={$this->forum_id}&amp;t={$this->topic_id}&amp;bookmark=1&amp;hash=" . generate_link_hash("topic_{$this->topic_id}")) : '',
			'U_VIEW_RESULTS'	=> append_sid("{$this->phpbb_root_path}viewtopic.$this->php_ext", "f=$this->forum_id&amp;t=$this->topic_id&amp;view=viewpoll"),
		]);
	}

	public function core_memberlist_view_profile($event)
	{
		if (empty($this->core->seo_opt['url_rewrite']))
		{
			return;
		}

		$member = $event['member'];

		$this->core->set_user_url($member['username'], $member['user_id']);
		$this->core->seo_path['canonical'] = $this->core->drop_sid(append_sid("{$this->phpbb_root_path}memberlist.{$this->php_ext}", "mode=viewprofile&amp;u=" . $member['user_id']));
		$this->core->seo_opt['zero_dupe']['redir_def'] = [
			'mode'	=> ['val' => 'viewprofile', 'keep' => true],
			'u'		=> ['val' => $member['user_id'], 'keep' => true, 'force' => true],
		];

		$this->core->zero_dupe();

		$event['member'] = $member;
	}

	public function core_modify_username_string($event)
	{
		$modes = ['profile' => 1, 'full' => 1];

		$mode = $event['mode'];

		if (!isset($modes[$mode]))
		{
			return;
		}

		$user_id = (int) $event['user_id'];

		if (
			!$user_id ||
			$user_id == ANONYMOUS ||
			($this->user->data['user_id'] != ANONYMOUS && !$this->auth->acl_get('u_viewprofile'))
		)
		{
			return;
		}

		$username = $event['username'];
		$custom_profile_url = $event['custom_profile_url'];

		$this->core->set_user_url($username, $user_id);

		if ($custom_profile_url !== false)
		{
			$profile_url = reapply_sid($custom_profile_url . (strpos($custom_profile_url, '?') !== false ?  '&amp;' : '?') . 'u=' . (int) $user_id);
		}
		else
		{
			$profile_url = append_sid("{$this->phpbb_root_path}memberlist.{$this->php_ext}", 'mode=viewprofile&amp;u=' . (int) $user_id);
		}

		$profile_url_ary = explode(strstr($profile_url, '?') !== false ?  '?' : '&amp;', $profile_url, 2);
		if (strstr($profile_url_ary[0], "memberlist.{$this->php_ext}") !== false && isset($profile_url_ary[1]))
		{
			$profile_url = $this->core->url_rewrite("{$this->phpbb_root_path}memberlist.{$this->php_ext}", $profile_url_ary[1], true, false, false, true);
		}

		// Return profile
		$username_string = $event['username_string'];
		if ($mode == 'profile')
		{
			$username_string = $profile_url;
		}
		else
		{
			$username_string = str_replace(['{PROFILE_URL}', '{USERNAME_COLOUR}', '{USERNAME}'], [$profile_url, $event['username_colour'], $event['username']], (!$event['username_colour']) ? $event['_profile_cache']['tpl_profile'] : $event['_profile_cache']['tpl_profile_colour']);
		}

		$event['username_string'] = $username_string;
	}

	/*
	* core_append_sid event
	* you can speed up this if you add :
	*

	// www.phpBB-SEO.ir SEO TOOLKIT BEGIN
	// We bypass events/hooks here, the same effect as a standalone event/hook,
	// which we want, but much faster ;-)
	if (!empty($this->core->seo_opt['url_rewrite']))
	{
		return $this->core->url_rewrite($url, $params, $is_amp, $session_id);
	}
	// www.phpBB-SEO.ir SEO TOOLKIT END

	*
	* after :
	*

function append_sid($url, $params = false, $is_amp = true, $session_id = false)
{

	*
	* in includes/fucntions.php
	*
	*/
	public function core_append_sid($event)
	{
		if (!empty($this->core->seo_opt['url_rewrite']))
		{
			$event['append_sid_overwrite'] = $this->core->url_rewrite($event['url'], $event['params'], $event['is_amp'], $event['session_id'], $event['is_route']);
		}
	}

	public function core_pagination_generate_page_link($event)
	{
		static $paginated = [], $find = ['{SN}', '{SV}'];

		$base_url = $event['base_url'];
		$on_page = $event['on_page'];
		$start_name = $event['start_name'];
		$per_page = $event['per_page'];

		if (!is_string($base_url))
		{
			return;
		}

		// do ourselves a favor
		$base_url = trim($base_url, '?');
		if (!isset($paginated[$base_url]))
		{
			$rewriten = $this->core->url_rewrite($base_url);

			@list($rewriten, $qs) = explode('?', $rewriten, 2);
			if (
				// rewriten urls are absolute
				!preg_match('`^(https?\:)?//`i', $rewriten) ||
				// they are not php scripts
				preg_match('`\.' . $this->php_ext . '$`i', $rewriten)
			)
			{
				// in such case, do as usual
				$qs = $qs ? "?$qs&amp;" : '?';
				$paginated[$base_url] = $rewriten . $qs . '{SN}={SV}';
			}
			else
			{
				$hasExt = preg_match('`^((https?\:)?//[^/]+.+?)(\.[a-z0-9]+)$`i', $rewriten);

				if ($hasExt)
				{
					// start location is before the ext
					$rewriten = preg_replace('`^((https?\:)?//[^/]+.+?)(\.[a-z0-9]+)$`i', '\1' . $this->core->seo_delim['start'] . '{SV}\3', $rewriten);
				}
				else
				{
					// start is appened
					$rewriten = rtrim($rewriten, '/') . '/' . $this->core->seo_static['pagination'] .  '{SV}' . $this->core->seo_ext['pagination'];
				}

				$paginated[$base_url] = $rewriten . ($qs ? "?$qs" : '');
			}
		}

		// we'll see if start_name has use cases, and we can still work with rewriterules
		$event['generate_page_link_override'] = ($on_page > 1) ? str_replace($find, [$start_name, ($on_page - 1) * $per_page], $paginated[$base_url]) : $base_url;
	}

	public function core_submit_post_end($event)
	{
		global $post_data; // god save hax

		$data = $event['data'];
		$mode = $event['mode'];

		$post_id = $data['post_id'];
		$forum_id = $data['forum_id'];

		// for some reasons, $post_mode cannot be globallized without being nullized ...
		if ($mode == 'post')
		{
			$post_mode = 'post';
		}
		else if ($mode != 'edit')
		{
			$post_mode = 'reply';
		}
		else if ($mode == 'edit')
		{
			$post_mode = ($data['topic_posts_approved'] + $data['topic_posts_unapproved'] + $data['topic_posts_softdeleted'] == 1) ? 'edit_topic' : (($data['topic_first_post_id'] == $data['post_id']) ? 'edit_first_post' : (($data['topic_last_post_id'] == $data['post_id']) ? 'edit_last_post' : 'edit'));
		}

		if ($mode == 'post' || ($mode == 'edit' && $data['topic_first_post_id'] == $post_id))
		{
			$this->core->set_url($data['forum_name'], $forum_id, 'forum');

			$_parent = $post_data['topic_type'] == POST_GLOBAL ? $this->core->seo_static['global_announce'] : $this->core->seo_url['forum'][$forum_id];
			$_t = !empty($data['topic_id']) ? max(0, (int) $data['topic_id']) : 0;
			$_url = $this->core->url_can_edit($forum_id) ? utf8_normalize_nfc($this->request->variable('url', '', true)) : (isset($post_data['topic_url']) ? $post_data['topic_url'] : '');

			if (!$this->core->check_url('topic', $_url, $_parent))
			{
				if (!empty($_url))
				{
					// Here we get rid of the seo delim (-t) and put it back even in simple mod
					// to be able to handle all cases at once
					$_url = preg_replace('`' . $this->core->seo_delim['topic'] . '$`i', '', $_url);
					$_title = $this->core->get_url_info('topic', $_url . $this->core->seo_delim['topic'] . $_t);
				}
				else
				{
					$_title = $this->core->modrtype > 2 ? censor_text($post_data['post_subject']) : '';
				}

				unset($this->core->seo_url['topic'][$_t]);

				$_url = $this->core->get_url_info('topic', $this->core->prepare_url('topic', $_title, $_t, $_parent, ((empty($_title) || ($_title == $this->core->seo_static['topic'])) ? true : false)), 'url');

				unset($this->core->seo_url['topic'][$_t]);
			}

			$data['topic_url'] = $post_data['topic_url'] = $_url;
		}

		switch ($post_mode)
		{
			case 'post':
			case 'edit_topic':
			case 'edit_first_post':
				if (isset($data['topic_url']))
				{
					$sql = 'UPDATE ' . TOPICS_TABLE . '
						SET ' . $this->db->sql_build_array('UPDATE', ['topic_url' => $data['topic_url']]) . '
						WHERE topic_id = ' . (int) $data['topic_id'];
					$this->db->sql_query($sql);
				}

				break;
		}

		$this->core->set_url($data['forum_name'], $data['forum_id'], 'forum');

		$params = $add_anchor = '';

		// --> Until https://tracker.phpbb.com/browse/PHPBB3-13164 is fixed
		// we need to compute post_visibility as the global hax fails for some reasons
		$post_visibility = ITEM_APPROVED;

		// Check the permissions for post approval.
		// Moderators must go through post approval like ordinary users.
		if (!$this->auth->acl_get('f_noapprove', $data['forum_id']))
		{
			// Post not approved, but in queue
			$post_visibility = ITEM_UNAPPROVED;
			switch ($post_mode)
			{
				case 'edit_first_post':
				case 'edit':
				case 'edit_last_post':
				case 'edit_topic':
					$post_visibility = ITEM_REAPPROVE;
					break;
			}
		}

		// MODs/Extensions are able to force any visibility on posts
		if (isset($data['force_approved_state']))
		{
			$post_visibility = (in_array((int) $data['force_approved_state'], [ITEM_APPROVED, ITEM_UNAPPROVED, ITEM_DELETED, ITEM_REAPPROVE])) ? (int) $data['force_approved_state'] : $post_visibility;
		}
		if (isset($data['force_visibility']))
		{
			$post_visibility = (in_array((int) $data['force_visibility'], [ITEM_APPROVED, ITEM_UNAPPROVED, ITEM_DELETED, ITEM_REAPPROVE])) ? (int) $data['force_visibility'] : $post_visibility;
		}

		$data['post_visibility'] = $post_visibility;
		// <-- Until https://tracker.phpbb.com/browse/PHPBB3-13164 is fixed

		if ($data['post_visibility'] == ITEM_APPROVED)
		{
			$params .= '&amp;t=' . $data['topic_id'];

			if ($mode != 'post')
			{
				$params .= '&amp;p=' . $data['post_id'];
				$add_anchor = '#p' . $data['post_id'];
			}
		}
		else if ($mode != 'post' && $post_mode != 'edit_first_post' && $post_mode != 'edit_topic')
		{
			$params .= '&amp;t=' . $data['topic_id'];
		}

		if ($params)
		{
			$data['topic_type'] = $post_data['topic_type'];

			$this->core->prepare_topic_url($data);
		}

		$url = (!$params) ? "{$this->phpbb_root_path}viewforum.{$this->php_ext}" : "{$this->phpbb_root_path}viewtopic.{$this->php_ext}";
		$url = $this->core->url_rewrite($url, 'f=' . $data['forum_id'] . $params, true, false, false, isset($params)) . $add_anchor;

		$event['url'] = $url;
		$event['data'] = $data;
	}

	public function core_posting_modify_template_vars($event)
	{
		$page_data = $event['page_data'];
		$submit = $event['submit'];
		$preview = $event['preview'];
		$refresh = $event['refresh'];
		$mode = $event['mode'];
		$post_id = $event['post_id'];
		$forum_id = $event['forum_id'];
		$post_data = $event['post_data'];

		if ($submit || $preview || $refresh)
		{
			if ($mode == 'post' || ($mode == 'edit' && $post_data['topic_first_post_id'] == $post_id))
			{
				$this->core->set_url($post_data['forum_name'], $forum_id, 'forum');

				$_parent = $post_data['topic_type'] == POST_GLOBAL ? $this->core->seo_static['global_announce'] : $this->core->seo_url['forum'][$forum_id];
				$_t = !empty($post_data['topic_id']) ? max(0, (int) $post_data['topic_id']) : 0;
				$_url = $this->core->url_can_edit($forum_id) ? utf8_normalize_nfc($this->request->variable('url', '', true)) : (isset($post_data['topic_url']) ? $post_data['topic_url'] : '');

				if (!$this->core->check_url('topic', $_url, $_parent))
				{
					if (!empty($_url))
					{
						// Here we get rid of the seo delim (-t) and put it back even in simple mod
						// to be able to handle all cases at once
						$_url = preg_replace('`' . $this->core->seo_delim['topic'] . '$`i', '', $_url);
						$_title = $this->core->get_url_info('topic', $_url . $this->core->seo_delim['topic'] . $_t);
					}
					else
					{
						$_title = $this->core->modrtype > 2 ? censor_text($post_data['post_subject']) : '';
					}

					unset($this->core->seo_url['topic'][$_t]);

					$_url = $this->core->get_url_info('topic', $this->core->prepare_url('topic', $_title, $_t, $_parent, ((empty($_title) || ($_title == $this->core->seo_static['topic'])) ? true : false)), 'url');

					unset($this->core->seo_url['topic'][$_t]);
				}

				$post_data['topic_url'] = $_url;
			}
		}
		$page_data['TOPIC_URL'] = isset($post_data['topic_url']) ? preg_replace('`' . $this->core->seo_delim['topic'] . '$`i', '', $post_data['topic_url']) : '';
		$page_data['S_URL'] = ($mode == 'post' || ($mode == 'edit' && $post_id == $post_data['topic_first_post_id'])) ? $this->core->url_can_edit($forum_id) : false;

		$event['page_data'] = $page_data;
	}

	public function core_display_user_activity_modify_actives($event)
	{

		$active_t_row = $event['active_t_row'];
		$active_f_row = $event['active_f_row'];

		if (!empty($active_t_row))
		{
			$sql_array = [
				'SELECT'	=> 't.topic_title, t.topic_type ' . (!empty($this->core->seo_opt['sql_rewrite']) ? ', t.topic_url' : '') . ', f.forum_id, f.forum_name',
				'FROM'		=> [
					TOPICS_TABLE	=> 't',
				],
				'LEFT_JOIN' => [
					[
						'FROM'	=> [FORUMS_TABLE => 'f'],
						'ON'	=> 'f.forum_id = t.forum_id',
					],
				],
				'WHERE' => 't.topic_id = ' . (int) $active_t_row['topic_id']
			];
			$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_array));
			$seo_active_t_row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			if ($seo_active_t_row)
			{
				$active_t_row = array_merge($active_t_row, $seo_active_t_row);
				$active_t_forum_id = (int) $active_t_row['forum_id'];
				$this->core->prepare_topic_url($active_t_row);
			}
		}

		if (!empty($active_f_row['num_posts']))
		{
			$this->core->set_url($active_f_row['forum_name'], $active_f_row['forum_id'], 'forum');
		}
	}
}
