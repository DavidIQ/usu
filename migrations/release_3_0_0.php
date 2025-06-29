<?php
/**
*
* @package Ultimate phpBB SEO Friendly URL
* @version $$
* @copyright (c) 2017 www.phpBB-SEO.ir
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbseo\usu\migrations;

use phpbb\db\migration\migration;

class release_3_0_0 extends migration
{
	public function effectively_installed()
	{
		return empty($this->config['seo_usu_version']);
	}

	static public function depends_on()
	{
		return ['\phpbbseo\usu\migrations\release_2_0_0_b2'];
	}

	public function update_data()
	{
		return [
			['config.remove', ['seo_usu_version']],
		];
	}
}
