<?php
/**
 * WoWRoster.net WoWRoster
 *
 *
 * @copyright  2002-2011 WoWRoster.net
 * @license    http://www.gnu.org/licenses/gpl.html   Licensed under the GNU General Public License v3.
 * @version    SVN: $Id$
 * @link       http://www.wowroster.net
 * @package    MembersList
 */

if ( !defined('IN_ROSTER') )
{
	exit('Detected invalid access to this file!');
}

include_once ($addon['inc_dir'] . 'memberslist.php');

$memberlist = new memberslist(array('group_alts'=>-1));

$mainQuery =
	'SELECT '.
	'`guild`.`guild_name`, '.
	'`guild`.`guild_id`, '.
	'`guild`.`faction`, '.
	'`guild`.`factionEn`, '.
	'`guild`.`guild_num_members`, '.
	'`guild`.`guild_num_accounts`, '.
	'`guild`.`guild_motd` '.

	'FROM `'.$roster->db->table('guild').'` AS guild ';
$where[] = '`guild`.`server` = "'.$roster->db->escape($roster->data['server']).'"';
$order_last[] = '`guild`.`guild_name` ASC';

$FIELD['guild_name'] = array (
	'lang_field' => 'guild',
	'order'      => array( '`guild`.`guild_name` ASC' ),
	'order_d'    => array( '`guild`.`guild_name` DESC' ),
	'value'      => 'guild_value',
	'display'    => 3,
);

$FIELD['faction'] = array (
	'lang_field' => 'faction',
	'order'      => array( '`guild`.`faction` ASC' ),
	'order_d'    => array( '`guild`.`faction` DESC' ),
	'value'      => 'faction_value',
	'display'    => 2,
);

$FIELD['guild_num_members'] = array (
	'lang_field' => 'members',
	'order'      => array( '`guild`.`guild_num_members` ASC' ),
	'order_d'    => array( '`guild`.`guild_num_members` DESC' ),
	'display'    => 2,
);

$FIELD['guild_num_accounts'] = array (
	'lang_field' => 'accounts',
	'order'      => array( '`guild`.`guild_num_accounts` ASC' ),
	'order_d'    => array( '`guild`.`guild_num_accounts` DESC' ),
	'display'    => 2,
);

$FIELD['guild_motd'] = array (
	'lang_field' => 'motd',
	'order'      => array( '`guild`.`guild_motd` ASC' ),
	'order_d'    => array( '`guild`.`guild_motd` DESC' ),
	'value'      => 'note_value',
	'display'    => 2,
);

$memberlist->prepareData($mainQuery, $where, null, null, $order_last, $FIELD, 'memberslist');

// Start output
echo $memberlist->makeMembersList('syellow');


/**
 * Controls Output of a Note Column
 *
 * @param array $row - of character data
 * @return string - Formatted output
 */
function note_value ( $row, $field )
{
	global $roster, $addon;

	if( !empty($row[$field]) )
	{
		$note = htmlspecialchars(nl2br($row[$field]));

		if( $addon['config']['compress_note'] )
		{
			$note = '<img src="'.$roster->config['theme_path'].'/images/note.gif" style="cursor:help;" '.makeOverlib($note,$roster->locale->act['note'],'',1,'',',WRAP').' alt="[]" />';
		}
		else
		{
			$value = $note;
		}
	}
	else
	{
		$note = '&nbsp;';
		if( $addon['config']['compress_note'] )
		{
			$note = '<img src="'.$roster->config['theme_path'].'/images/no_note.gif" alt="[]" />';
		}
		else
		{
			$value = $note;
		}
	}

	return '<div style="display:none;">'.htmlentities($row[$field]).'</div>'.$note;
}

/**
 * Controls Output of the Guild Name Column
 *
 * @param array $row
 * @return string - Formatted output
 */
function guild_value ( $row, $field )
{
	global $roster;

	if( $row['guild_id'] )
	{
		return '<div style="display:none;">' . $row['guild_name'] . '</div><a href="' . makelink('guild-memberslist&amp;a=g:' . $row['guild_id']) . '">' . $row['guild_name'] . '</a>';
	}
	else
	{
		return '<div style="display:none;">' . $row['guild_name'] . '</div>' . $row['guild_name'];
	}
}

/**
 * Controls Output of the Faction Column
 *
 * @param array $row
 * @return string - Formatted output
 */
function faction_value ( $row, $field )
{
	global $roster, $addon;

	if ( $row['factionEn'] )
	{
		$faction = ( isset($row['factionEn']) ? $row['factionEn'] : '' );

		switch( substr($faction,0,1) )
		{
			case 'A':
				$icon = '<img class="middle" src="' . $roster->config['img_url'] . 'icon_alliance.png" alt="" width="24" height="24" /> ';
				break;
			case 'H':
				$icon = '<img class="middle" src="' . $roster->config['img_url'] . 'icon_horde.png" alt="" width="24" height="24" /> ';
				break;
			default:
				$icon = '<img class="middle" src="' . $roster->config['img_url'] . 'icon_neutral.png" alt="" width="24" height="24" /> ';
				break;
		}
	}
	else
	{
		$icon = '';
	}

	$cell_value = $icon . $row['faction'];

	return '<div style="display:none;">' . $row['faction'] . '</div>' . $cell_value;
}
