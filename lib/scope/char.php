<?php
/**
 * WoWRoster.net WoWRoster
 *
 * Roster char scope functions
 *
 *
 * @copyright  2002-2011 WoWRoster.net
 * @license    http://www.gnu.org/licenses/gpl.html   Licensed under the GNU General Public License v3.
 * @version    SVN: $Id$
 * @link       http://www.wowroster.net
 * @since      File available since Release 2.1.0
 * @package    WoWRoster
 */

if( !defined('IN_ROSTER') )
{
	exit('Detected invalid access to this file!');
}

class CharScope
{
	function set_tpl( $data )
	{
		global $roster;

		// Create a character based icon
		if( $data['raceEn'] == '' || $data['sexid'] == '' )
		{
			$data['char_icon'] = 'unknown';
		}
		else
		{
			$data['char_icon'] = strtolower($data['raceEn']) . '-' . ($data['sexid'] == '0' ? 'male' : 'female');
		}

		/**
		 * Assigning everything this file may need to the template
		 * The only tpl vars not here are ones that need to be generated in their respective methods
		 */
		$roster->tpl->assign_vars(array(
			'CHAR_ICON'     => $data['char_icon'],
			'NAME'          => $data['name'],
			'SERVER'        => $data['server'],
			'ID'            => $data['member_id'],
			'LOCALE'        => $data['clientLocale'],
			'LEVEL'         => $data['level'],
			'RACE'          => $data['race'],
			'CLASS'         => $data['class'],
			'GUILD_TITLE'   => $data['guild_title'],
			'GUILD_NAME'    => $data['guild_name'],
			'FACTION_EN'    => strtolower($roster->data['factionEn']),
			'FACTION'       => $roster->data['faction']
			)
		);
	}

	function alt_name_hover()
	{
		global $roster;

		$alt_hover = '';
		if( active_addon('memberslist') )
		{
			$sql = "SELECT `main_id` FROM `"
				 . $roster->db->table('alts', 'memberslist')
				 . "` WHERE `member_id` = " . $roster->data['member_id'] . ";";

			$main_id = $roster->db->query_first($sql);
			if( $main_id != 0 )
			{
				// we know the main, get alt info
				$sql = "SELECT `m`.`name`, `m`.`level`, `m`.`class`, `a`.* FROM `"
					 . $roster->db->table('alts', 'memberslist') . "` AS a, `"
					 . $roster->db->table('players') . "` AS m "
					 . " WHERE `a`.`member_id` = `m`.`member_id` "
					 . " AND `a`.`main_id` = $main_id;";

				$qry = $roster->db->query($sql);
				$alts = $roster->db->fetch_all($qry, SQL_ASSOC);

				if( isset($alts[1]) )
				{
					$html = $caption = '';

					foreach( $alts as $alt )
					{
						if( $alt['main_id'] == $alt['member_id'] )
						{
							$caption = 'Alts of: <a href="' . makelink('char-info&amp;a=c:' . $alt['member_id']) . '">'
									 . $alt['name'] . ' (' . $roster->locale->act['level']
									 . ' ' . $alt['level'] . ' ' . $alt['class'] . ')</a>';
						}
						else
						{
							$html .= '<a href="' . makelink('char-info&amp;a=c:' . $alt['member_id']) . '">'
								   . $alt['name'] . ' (' . $roster->locale->act['level']
								   . ' ' . $alt['level'] . ' ' . $alt['class'] . ')</a><br />';
						}
					}
					setTooltip('alt_html', $html);
					setTooltip('alt_cap', $caption);
					$alt_hover = ' style="cursor:pointer;" onmouseover="return overlib(overlib_alt_html,CAPTION,overlib_alt_cap);" '
						. 'onclick="return overlib(overlib_alt_html,CAPTION,overlib_alt_cap,STICKY,OFFSETX,-10,OFFSETY,-10,NOCLOSE);" '
						. 'onmouseout="return nd();"';
				}
			}
		}
		$roster->tpl->assign_var('ALT_TOOLTIP', $alt_hover);
	}

	function mini_members_list()
	{
		global $roster;

		// Get the scope select data
		$query = 'SELECT `members`.`member_id`, `members`.`name`, `members`.`class`, `members`.`classid`, `members`.`level`, `members`.`guild_title`, `members`.`guild_rank`, '
			. '`players`.`race`, `players`.`raceid`, `players`.`sex`, `players`.`sexid` '
			. 'FROM `' . $roster->db->table('members') . '` AS members '
			. 'LEFT JOIN `' . $roster->db->table('players') . '` AS players ON `members`.`member_id` = `players`.`member_id` '
			. 'WHERE `members`.`guild_id` = "' . $roster->data['guild_id'] . '" '
			. 'ORDER BY `members`.`level` DESC, `members`.`name` ASC';

		$result = $roster->db->query($query);

		if( !$result )
		{
			trigger_error($roster->db->error());
			return false;
		}

		while( $data = $roster->db->fetch($result, SQL_ASSOC) )
		{
			$roster->tpl->assign_block_vars('mini_memberslist', array(
				'ID'         => $data['member_id'],
				'NAME'       => $data['name'],
				'CLASS'      => $data['class'],
				'CLASS_ID'   => $data['classid'],
				'CLASS_EN'   => isset($roster->locale->wordings['enUS']['id_to_class'][$data['classid']]) ? strtolower(str_replace(' ','',$roster->locale->wordings['enUS']['id_to_class'][$data['classid']])) : '',
				'GUILD_NAME' => $roster->data['guild_name'],
				'LEVEL'      => $data['level'],
				'TITLE'      => $data['guild_title'],
				'RANK'       => $data['guild_rank'],
				'RACE'       => $data['race'],
				'RACE_ID'    => $data['raceid'],
				'RACE_EN'    => $data['raceid'] != '' && $data['raceid'] != 0 ? strtolower(str_replace(' ','',$roster->locale->wordings['enUS']['id_to_race'][$data['raceid']])) : '',
				'SEX'        => $data['sex'],
				'SEX_ID'     => $data['sexid'],
				'U_LINK'     => ( $data['race'] != '' ? makelink('&amp;a=c:' . $data['member_id'],true) : false ),
				'S_SELECTED' => ( $data['member_id'] == $roster->data['member_id'] ? true : false )
				)
			);
		}

		$roster->tpl->assign_vars(array(
			'S_MINI_MEMBERSLIST' => ( $roster->db->num_rows() > 1 ? true : false ),
			'GUILD_NAME'         => $roster->data['guild_name']
			)
		);

		$roster->db->free_result($result);

		return true;
	}

}
