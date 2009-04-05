<?php
/**
 * WoWRoster.net WoWRoster
 *
 * Character class
 *
 * LICENSE: Licensed under the Creative Commons
 *          "Attribution-NonCommercial-ShareAlike 2.5" license
 *
 * @copyright  2002-2008 WoWRoster.net
 * @license    http://creativecommons.org/licenses/by-nc-sa/2.5   Creative Commons "Attribution-NonCommercial-ShareAlike 2.5"
 * @version    SVN: $Id$
 * @link       http://www.wowroster.net
 * @package    CharacterInfo
 * @subpackage CharacterLib
*/

if( !defined('IN_ROSTER') )
{
    exit('Detected invalid access to this file!');
}

require_once (ROSTER_LIB . 'item.php');
require_once ($addon['inc_dir'] . 'bag.php');
require_once (ROSTER_LIB . 'quest.php');
require_once (ROSTER_LIB . 'recipes.php');

/**
 * Character Information Class
 * @package    CharacterInfo
 * @subpackage CharacterLib
 *
 */
class char
{
	var $data;
	var $equip;
	var $talent_build_url;
	var $locale;

	/**
	 * Constructor
	 *
	 * @param array $data
	 * @return char
	 */
	function char( $data )
	{
		global $roster, $addon;

		$this->data = $data;
		$this->locale = $roster->locale->wordings[$this->data['clientLocale']];

		$querystr = "SELECT * FROM `" . $roster->db->table('display',$addon['basename']) . "` WHERE `member_id` = '" . $this->data['member_id'] . "';";

		$result = $roster->db->query($querystr);

		$row = $roster->db->fetch($result,SQL_ASSOC);

		$disp_array = array(
			'show_money',
			'show_played',
			'show_tab2',
			'show_tab3',
			'show_tab4',
			'show_tab5',
			'show_talents',
			'show_spellbook',
			'show_mail',
			'show_bags',
			'show_bank',
			'show_quests',
			'show_recipes',
			'show_item_bonuses'
		);

		foreach( $disp_array as $setting )
		{
			if( $addon['config'][$setting] == -1 )
			{
				$addon['config'][$setting] = $row[$setting];
			}
		}

		// Create a character based icon
		if( $this->data['raceEn'] == '' || $this->data['sexid'] == '' )
		{
			$this->data['char_icon'] = 'unknown';
		}
		else
		{
			$this->data['char_icon'] = strtolower($this->data['raceEn']) . '-' . ($this->data['sexid'] == '0' ? 'male' : 'female');
		}

		/**
		 * Assigning everything this file may need to the template
		 * The only tpl vars not here are ones that need to be generated in their respective methods
		 */
		$roster->tpl->assign_vars(array(
			'S_MAX_LEVEL' => ROSTER_MAXCHARLEVEL,

			'S_PLAYED'     => $roster->auth->getAuthorized($addon['config']['show_played']),
			'S_MONEY'      => $roster->auth->getAuthorized($addon['config']['show_money']),
			'S_PET_TAB'    => $roster->auth->getAuthorized($addon['config']['show_tab2']),
			'S_REP_TAB'    => $roster->auth->getAuthorized($addon['config']['show_tab3']),
			'S_SKILL_TAB'  => $roster->auth->getAuthorized($addon['config']['show_tab4']),
			'S_PVP_TAB'    => $roster->auth->getAuthorized($addon['config']['show_tab5']),
			'S_TALENT_TAB' => $roster->auth->getAuthorized($addon['config']['show_talents']),
			'S_SPELL_TAB'  => $roster->auth->getAuthorized($addon['config']['show_spellbook']),
			'S_BONUS_TAB'  => $roster->auth->getAuthorized($addon['config']['show_item_bonuses']),

			'S_PETS'       => false,
			'S_MOUNTS'     => false,
			'S_COMPANIONS' => false,

			'L_CHAR_POWER'    => $this->data['power'],
			'L_CHAR_POWER_ID' => strtolower($this->data['power']),

			'HEALTH'        => $this->data['health'],
			'POWER'         => $this->data['mana'],
			'TALENT_POINTS' => $this->data['talent_points'],
			'CHAR_ICON'     => $this->data['char_icon'],
			'NAME'          => $this->data['name'],
			'ID'            => $this->data['member_id'],
			'LOCALE'        => $this->data['clientLocale'],
			'LEVEL'         => $this->data['level'],
			'RACE'          => $this->data['race'],
			'CLASS'         => $this->data['class'],
			'GUILD_TITLE'   => $this->data['guild_title'],
			'GUILD_NAME'    => $this->data['guild_name'],
			'FACTION_EN'    => strtolower($roster->data['factionEn']),
			'FACTION'       => $roster->data['faction'],

			'MONEY_G' => $this->data['money_g'],
			'MONEY_S' => $this->data['money_s'],
			'MONEY_C' => $this->data['money_c'],
			)
		);
	}


	/**
	 * Gets a value from the character data
	 *
	 * @param string $field
	 * @return mixed
	 */
	function get( $field )
	{
		return $this->data[$field];
	}


	/**
	 * Gathers all of the characters equiped items into an array
	 * Array $this->equip
	 *
	 */
	function fetchEquip()
	{
		$this->equip = item::fetchManyItems($this->data['member_id'], 'equip', 'full');
	}


	function show_buffs()
	{
		global $roster;

		// Get char professions for quick links
		$query = "SELECT * FROM `" . $roster->db->table('buffs') . "` WHERE `member_id` = '" . $this->data['member_id'] . "';";
		$result = $roster->db->query($query);

		$return_string = '';
		if( $roster->db->num_rows($result) > 0 )
		{
			$return_string .= '<div class="buff_icons">';
			while( $row = $roster->db->fetch($result,SQL_ASSOC) )
			{
				$tooltip = makeOverlib($row['tooltip'],'','ffdd00',1,'',',RIGHT');

				$roster->tpl->assign_block_vars('buff',array(
					'NAME'    => $row['name'],
					'RANK'    => $row['rank'],
					'COUNT'   => $row['count'],
					'ICON'    => $row['icon'],
					'TOOLTIP' => $tooltip
					)
				);
			}
		}
	}


	/**
	 * Build quests
	 *
	 * @return string
	 */
	function show_quests()
	{
		global $roster, $addon;

		$quests = quest_get_many($this->data['member_id']);

		$roster->tpl->assign_vars(array(
			'S_QUESTS'    => count($quests),
			'S_MAXQUESTS' => ROSTER_MAXQUESTS,
			)
		);

		if( isset($quests[0]) )
		{
			$quests_arr = array();
			foreach( $quests as $object )
			{
				$zone = $object->data['zone'];
				$quest_name = $object->data['quest_name'];
				$quests_arr[$zone][$quest_name]['quest_id'] = $object->data['quest_id'];
				$quests_arr[$zone][$quest_name]['quest_index'] = $object->data['quest_index'];
				$quests_arr[$zone][$quest_name]['quest_level'] = $object->data['quest_level'];
				$quests_arr[$zone][$quest_name]['quest_tag'] = $object->data['quest_tag'];
				$quests_arr[$zone][$quest_name]['difficulty'] = $object->data['difficulty'];

				$description = str_replace('<class>',$this->data['class'],$object->data['description']);
				$description = str_replace('<name>',$this->data['name'],$description);
				$quests_arr[$zone][$quest_name]['description'] = nl2br($description);

				$objective = str_replace('<class>',$this->data['class'],$object->data['objective']);
				$objective = str_replace('<name>',$this->data['name'],$objective);
				$quests_arr[$zone][$quest_name]['objective'] = nl2br($objective);

				$quests_arr[$zone][$quest_name]['reward_money'] = $object->data['reward_money'];
				$quests_arr[$zone][$quest_name]['daily'] = $object->data['daily'];
				$quests_arr[$zone][$quest_name]['group'] = $object->data['group'];
				$quests_arr[$zone][$quest_name]['is_complete'] = $object->data['is_complete'];
			}

			foreach( $quests_arr as $zone => $quest )
			{
				$roster->tpl->assign_block_vars('zone',array(
					'NAME' => $zone,
					)
				);

				foreach( $quest as $quest_name => $data )
				{
					switch( $data['difficulty'] )
					{
						case 4:
							$color = 'red';
							break;

						case 3:
							$color = 'orange';
							break;

						case 2:
							$color = 'yellow';
							break;

						case 1:
							$color = 'green';
							break;

						case 0:
						default:
							$color = 'grey';
							break;
					}

					$reward_money_c = $reward_money_s = $reward_money_g = 0;
					if( $data['reward_money'] > 0 )
					{
						$money = $data['reward_money'];

						$reward_money_c = $money % 100;
						$money = floor( $money / 100 );

						if( !empty($money) )
						{
							$reward_money_s = $money % 100;
							$money = floor( $money / 100 );
						}
						if( !empty($money) )
						{
							$reward_money_g = $money;
						}
					}

					$roster->tpl->assign_block_vars('zone.quest',array(
						'ROW_CLASS'    => $roster->switch_row_class(),
						'NAME'         => $quest_name,
						'COLOR'        => $color,
						'ID'           => $data['quest_id'],
						'INDEX'        => $data['quest_index'],
						'LEVEL'        => $data['quest_level'],
						'DIFFICULTY'   => $data['difficulty'],
						'TAG'          => $data['quest_tag'],
						'COMPLETE'     => $data['is_complete'],
						'DESCRIPTION'  => $data['description'],
						'REWARD_MONEY_C' => $reward_money_c,
						'REWARD_MONEY_S' => $reward_money_s,
						'REWARD_MONEY_G' => $reward_money_g,
						'OBJECTIVE'    => $data['objective'],
						'DAILY'        => $data['daily'],
						'GROUP'        => $data['group'],
						)
					);

					foreach( $roster->locale->act['questlinks'] as $link )
					{
						$roster->tpl->assign_block_vars('zone.quest.links',array(
							'NAME' => $link['name'],
							'LINK' => sprintf($link['url'],$data['quest_id']),
							)
						);
					}
				}
			}
		}
		$roster->tpl->set_filenames(array('quests' => $addon['basename'] . '/quests.html'));
		return $roster->tpl->fetch('quests');
	}


	/**
	 * Build Recipes
	 *
	 * @return string
	 */
	function show_recipes()
	{
		global $roster, $addon;

		$roster->tpl->assign_vars(array(
			'S_RECIPE_HIDE' => $addon['config']['recipe_disp'],

			'U_ITEM'     => makelink('char-info-recipes&amp;s=item'),
			'U_NAME'     => makelink('char-info-recipes&amp;s=name'),
			'U_DIFFICULTY' => makelink('char-info-recipes&amp;s=difficulty'),
			'U_TYPE'     => makelink('char-info-recipes&amp;s=type'),
			'U_LEVEL'    => makelink('char-info-recipes&amp;s=level'),
			'U_REAGENTS' => makelink('char-info-recipes&amp;s=reagents'),
			)
		);

		// Get recipe sort mode
		$sort = (isset($_GET['s']) ? $_GET['s'] : '');

		$recipes = recipe_get_many( $this->data['member_id'],'', $sort );

		if( isset($recipes[0]) )
		{
			$recipe_arr = array();
			foreach( $recipes as $object )
			{
				$skill = $object->data['skill_name'];
				$recipe = $object->data['recipe_name'];
				$recipe_arr[$skill][$recipe]['recipe_type'] = $object->data['recipe_type'];
				$recipe_arr[$skill][$recipe]['difficulty'] = $object->data['difficulty'];
				$recipe_arr[$skill][$recipe]['item_color'] = $object->data['item_color'];
				$recipe_arr[$skill][$recipe]['reagents'] = $object->data['reagents'];
				$recipe_arr[$skill][$recipe]['recipe_texture'] = $object->data['recipe_texture'];
				$recipe_arr[$skill][$recipe]['level'] = $object->data['level'];
				$recipe_arr[$skill][$recipe]['item_id'] = $object->data['item_id'];
				$recipe_arr[$skill][$recipe]['recipe_id'] = $object->data['recipe_id'];
				$recipe_arr[$skill][$recipe]['item'] = $object->out();
			}

			foreach( $recipe_arr as $skill_name => $recipe )
			{
				$roster->tpl->assign_block_vars('recipe',array(
					'ID'   => strtolower(str_replace(' ','',$skill_name)),
					'NAME' => $skill_name,
					'ICON' => $this->locale['ts_iconArray'][$skill_name],
					'LINK' => makelink('#' . strtolower(str_replace(' ','',$skill_name))),
					)
				);
				foreach( $recipe as $name => $data )
				{
					if( $data['difficulty'] == '4' )
					{
						$difficultycolor = 'ff9900';
					}
					elseif( $data['difficulty'] == '3' )
					{
						$difficultycolor = 'ffff66';
					}
					elseif( $data['difficulty'] == '2' )
					{
						$difficultycolor = '339900';
					}
					elseif( $data['difficulty'] == '1' )
					{
						$difficultycolor = 'cccccc';
					}
					else
					{
						$difficultycolor = 'ffff80';
					}

					$roster->tpl->assign_block_vars('recipe.row',array(
						'ROW_CLASS'    => $roster->switch_row_class(),
						'DIFFICULTY'   => $data['difficulty'],
						'L_DIFFICULTY' => $roster->locale->act['recipe_' . $data['difficulty']],
						'ITEM_COLOR'   => $data['item_color'],
						'NAME'         => $name,
						'DIFFICULTY_COLOR' => $difficultycolor,
						'TYPE'         => $data['recipe_type'],
						'LEVEL'        => $data['level'],
						'REAGENTS'     => str_replace('<br>','&nbsp;<br />&nbsp;',$data['reagents']),
						'ICON'         => $data['item'],
						)
					);

					$reagents = explode('<br>',$data['reagents']);
					foreach( $reagents as $reagent )
					{
						$roster->tpl->assign_block_vars('recipe.row.reagents',array(
							'DATA' => $reagent,
							)
						);
					}
				}
			}
		}
		$roster->tpl->set_filenames(array('recipes' => $addon['basename'] . '/recipes.html'));
		return $roster->tpl->fetch('recipes');
	}


	/**
	 * Build Mail
	 *
	 * @return string
	 */
	function show_mailbox()
	{
		global $roster, $addon;

		$sqlquery = "SELECT * FROM `" . $roster->db->table('mailbox') . "` "
				  . "WHERE `member_id` = '" . $this->data['member_id'] . "' "
				  . "ORDER BY `mailbox_days`;";

		$result = $roster->db->query($sqlquery);

		$roster->tpl->assign_vars(array(
			'S_MAIL_DISP' => $addon['config']['mail_disp'],
			'S_MAIL' => false,
			)
		);

		if( $result && $roster->db->num_rows($result) > 0 )
		{
			$roster->tpl->assign_var('S_MAIL', true);

			while( $row = $roster->db->fetch($result,SQL_ASSOC) )
			{
				$maildateutc = strtotime($this->data['maildateutc']);

				// Get money in mail
				$money_included = '';
				if( $row['mailbox_coin'] > 0 && $roster->auth->getAuthorized($addon['config']['show_money']) )
				{
					$db_money = $row['mailbox_coin'];

					$mail_money['c'] = $db_money % 100;
					$db_money = floor( $db_money / 100 );
					$money_included = $mail_money['c'] . '<img src="' . $roster->config['img_url'] . 'coin_copper.gif" alt="c" />';

					if( !empty($db_money) )
					{
						$mail_money['s'] = $db_money % 100;
						$db_money = floor( $db_money / 100 );
						$money_included = $mail_money['s'] . '<img src="' . $roster->config['img_url'] . 'coin_silver.gif" alt="s" /> ' . $money_included;
					}
					if( !empty($db_money) )
					{
						$mail_money['g'] = $db_money;
						$money_included = $mail_money['g'] . '<img src="' . $roster->config['img_url'] . 'coin_gold.gif" alt="g" /> ' . $money_included;
					}
				}

				// Start the tooltips
				$tooltip_h = $row['mailbox_subject'];

				// first line is sender
				$tooltip = $roster->locale->act['mail_sender'] . ': ' . $row['mailbox_sender'] . '<br />';

				$expires_line = date($roster->locale->act['phptimeformat'],($row['mailbox_days']*24*3600)+$maildateutc) . ' ' . $roster->config['timezone'];

				if( (($row['mailbox_days']*24*3600)+$maildateutc) - time() < (3*24*3600) )
				{
					$color = 'ff0000';
				}
				else
				{
					$color = 'ffffff';
				}

				$tooltip .= $roster->locale->act['mail_expires'] . ": <span style=\"color:#$color;\">$expires_line</span><br />";

				// Join money with main tooltip
				if( !empty($money_included) )
				{
					$tooltip .= $roster->locale->act['mail_money'] . ': ' . $money_included;
				}

				$tooltipcode = makeOverlib($tooltip,$tooltip_h,'',2,$this->data['clientLocale']);

				if( $addon['config']['mail_disp'] > 0 )
				{
					// Set up box display
					$row['item_slot'] = 'Mail ' . $row['mailbox_slot'];
					$row['item_id'] = '0:0:0:0:0';
					$row['item_name'] = $row['mailbox_subject'];
					$row['item_level'] = 0;
					$row['item_texture'] = $row['mailbox_icon'];
					$row['item_parent'] = 'Mail';
					$row['item_tooltip'] = $tooltip;
					$row['item_color'] = '';
					$row['item_quantity'] = 0;
					$row['locale'] = $this->data['clientLocale'];

					$attach = new bag($row);
					$attach->out();
				}

				$roster->tpl->assign_block_vars('mail',array(
					'ROW_CLASS' => $roster->switch_row_class(),
					'TOOLTIP'   => $tooltipcode,
					'ITEM_ICON' => $row['mailbox_icon'],
					'SENDER'    => $row['mailbox_sender'],
					'SUBJECT'   => $row['mailbox_subject'],
					'EXPIRES'   => $expires_line,
					)
				);
			}
		}

		$roster->tpl->set_filenames(array('mailbox' => $addon['basename'] . '/mailbox.html'));
		return $roster->tpl->fetch('mailbox');
	}


	/**
	 * Build Spellbook
	 *
	 * @return bool
	 */
	function show_spellbook()
	{
		global $roster, $addon;

		// Initialize $spellbook array
		$spellbook[$this->data['name']] = array();

		$query = "SELECT `spelltree`.*, `talenttree`.`order`
			FROM `" . $roster->db->table('spellbooktree') . "` AS spelltree
			LEFT JOIN `" . $roster->db->table('talenttree') . "` AS talenttree
				ON `spelltree`.`member_id` = `talenttree`.`member_id`
				AND `spelltree`.`spell_type` = `talenttree`.`tree`
			WHERE `spelltree`.`member_id` = " . $this->data['member_id'] . "
			ORDER BY `talenttree`.`order` ASC;";

		$result = $roster->db->query($query);

		if( !$result )
		{
			return false;
		}

		$num_trees = $roster->db->num_rows($result);

		if( $num_trees == 0 )
		{
			return false;
		}

		for( $t=0; $t < $num_trees; $t++)
		{
			$row = $roster->db->fetch($result,SQL_ASSOC);

			$spell_type = $row['spell_type'];
			$spellbook[$this->data['name']][$spell_type]['order'] = $t;
			$spellbook[$this->data['name']][$spell_type]['icon'] = $row['spell_texture'];
			$spellbook[$this->data['name']][$spell_type]['tooltip'] = makeOverlib($spell_type,'','',2,'',',WRAP,RIGHT');

			// Get the spell data
			$query2 = "SELECT * FROM `" . $roster->db->table('spellbook') . "` WHERE `member_id` = '" . $this->data['member_id'] . "' AND `spell_type` = '" . $roster->db->escape($spell_type) . "' ORDER BY `spell_name`;";

			$result2 = $roster->db->query($query2);

			$s = $p = 0;
			while( $row2 = $roster->db->fetch($result2,SQL_ASSOC) )
			{
				if( ($s / 14) == 1 )
				{
					$s = 0;
					++$p;
				}
				$spell_name = $row2['spell_name'];
				$spellbook[$this->data['name']][$spell_type]['spells'][$p][$spell_name]['num'] = $s;
				$spellbook[$this->data['name']][$spell_type]['spells'][$p][$spell_name]['icon'] = $row2['spell_texture'];
				$spellbook[$this->data['name']][$spell_type]['spells'][$p][$spell_name]['rank'] = $row2['spell_rank'];
				$spellbook[$this->data['name']][$spell_type]['spells'][$p][$spell_name]['tooltip'] = makeOverlib($row2['spell_tooltip'],'','',0,$this->data['clientLocale'],',RIGHT');
				++$s;
			}
			$roster->db->free_result($result2);
		}

		$roster->db->free_result($result);


		// Get the PET spell data
		$query = "SELECT `spell`.*, `pet`.`name`
			FROM `" . $roster->db->table('pet_spellbook') . "` as spell
			LEFT JOIN `" . $roster->db->table('pets') . "` AS pet
			ON `spell`.`pet_id` = `pet`.`pet_id`
			WHERE `spell`.`member_id` = '" . $this->data['member_id'] . "' ORDER BY `spell`.`spell_name`;";

		$result = $roster->db->query($query);

		$pet_rows = $roster->db->num_rows($result);

		if( $pet_rows > 0 )
		{
			$s = $p = 0;
			while( $row = $roster->db->fetch($result,SQL_ASSOC) )
			{
				if( ($s / 14) == 1 )
				{
					$s = 0;
					++$p;
				}
				$petname = $row['name'];
				$spell_name = $row['spell_name'];

				$spellbook[$petname][0]['order'] = 0;
				$spellbook[$petname][0]['icon'] = 'ability_kick';
				$spellbook[$petname][0]['tooltip'] = '';

				$spellbook[$petname][0]['spells'][0][$spell_name]['num'] = $s;
				$spellbook[$petname][0]['spells'][0][$spell_name]['icon'] = $row['spell_texture'];
				$spellbook[$petname][0]['spells'][0][$spell_name]['rank'] = $row['spell_rank'];
				$spellbook[$petname][0]['spells'][0][$spell_name]['tooltip'] = makeOverlib($row['spell_tooltip'],'','',0,$this->data['clientLocale'],',RIGHT');
				++$s;
			}
		}
		$roster->db->free_result($result);

		foreach( $spellbook as $name => $spell_tree )
		{
			$roster->tpl->assign_block_vars('spell_book',array(
				'NAME'    => $name,
				'ID'      => strtolower(str_replace("'",'',$name)),
				'S_TREES' => !isset($spell_tree[0])
				)
			);
			foreach( $spell_tree as $spell_type => $spell_tree )
			{
				$roster->tpl->assign_block_vars('spell_book.tree',array(
					'NAME'  => $spell_type,
					'ORDER' => $spell_tree['order'],
					'ID'    => strtolower(str_replace(' ','',$spell_type)),
					'ICON'  => $spell_tree['icon'],
					'TOOLTIP' => $spell_tree['tooltip'],
					)
				);
				foreach( $spell_tree['spells'] as $page => $spell )
				{
					$roster->tpl->assign_block_vars('spell_book.tree.page',array(
						'ID'   => $page,
						'NUM'  => $page+1,
						'PREV' => ( isset($spell_tree['spells'][$page-1]) ? $page-1 : false ),
						'NEXT' => ( isset($spell_tree['spells'][$page+1]) ? $page+1 : false ),
						)
					);
					foreach( $spell as $spell_name => $spell_data )
					{
						$roster->tpl->assign_block_vars('spell_book.tree.page.spell',array(
							'NUM'  => $spell_data['num'],
							'NAME' => $spell_name,
							'ICON' => $spell_data['icon'],
							'RANK' => $spell_data['rank'],
							'TOOLTIP' => $spell_data['tooltip'],
							)
						);
					}
				}
			}
		}

		return true;
	}


	/**
	 * Build Companions
	 *
	 * @return bool
	 */
	function show_companions()
	{
		global $roster, $addon;
		return false;

		$query = "SELECT * FROM `" . $roster->db->table('companions') . "` WHERE `member_id` = '" . $this->data['member_id'] . "';";
		$result = $roster->db->query($query);

		$mount_num = $comp_num = 0;
		if( $roster->db->num_rows($result) > 0 )
		{
			while( $row = $roster->db->fetch($result,SQL_ASSOC) )
			{
				if( $row['icon'] == '' || !isset($row['icon']) )
				{
					$row['icon'] = 'inv_misc_questionmark';
				}

				if( $row['type'] == 'Mount' )
				{
					$roster->tpl->assign_block_vars('mounts',array(
						'ID'        => $mount_num,
						'NAME'      => $row['name'],
						'ICON'      => $row['icon'],
						'TOOLTIP' => makeOverlib($row['tooltip']),
						)
					);
					$mount_num++;
				}

				if( $row['type'] == 'Critter' )
				{
					$roster->tpl->assign_block_vars('companions',array(
						'ID'        => $comp_num,
						'NAME'      => $row['name'],
						'ICON'      => $row['icon'],
						'TOOLTIP' => makeOverlib($row['tooltip']),
						)
					);
					$comp_num++;
				}
			}

			$roster->tpl->assign_vars(array(
				'S_MOUNTS'     => (bool)$mount_num,
				'S_COMPANIONS' => (bool)$comp_num,
				)
			);

			return true;
		}
		return false;
	}


	/**
	 * Build Pet
	 *
	 * @return bool
	 */
	function show_pets()
	{
		global $roster, $addon;

		$query = "SELECT * FROM `" . $roster->db->table('pets') . "` WHERE `member_id` = '" . $this->data['member_id'] . "';";
		$result = $roster->db->query( $query );

		$petNum = 0;
		if( $roster->db->num_rows($result) > 0 )
		{
			$roster->tpl->assign_var('S_PETS',true);

			while ($row = $roster->db->fetch($result,SQL_ASSOC))
			{
				$expbar_show = true;
				$expbar_amount = $expbar_max = '';

				if( $row['level'] == ROSTER_MAXCHARLEVEL )
				{
					$exp_percent = 100;
					$expbar_text = $roster->locale->act['max_exp'];
				}
				else
				{
					$xp = explode(':',$row['xp']);
					if( isset($xp[1]) && $xp[1] != '0' && $xp[1] != '' )
					{
						$exp_percent = ( $xp[1] > 0 ? floor($xp[0] / $xp[1] * 100) : 0);

						$expbar_amount = $xp[0];
						$expbar_max = $xp[1];
					}
					else
					{
						$expbarshow = false;
						$exp_percent = 0;

					}
				}


				// Start Warlock Pet Icon Fix
				if( $row['type'] == $this->locale['Imp'] )
				{
					$row['icon'] = 'spell_shadow_summonimp';
				}
				elseif( $row['type'] == $this->locale['Voidwalker'] )
				{
					$row['icon'] = 'spell_shadow_summonvoidwalker';
				}
				elseif( $row['type'] == $this->locale['Succubus'] )
				{
					$row['icon'] = 'spell_shadow_summonsuccubus';
				}
				elseif( $row['type'] == $this->locale['Felhunter'] )
				{
					$row['icon'] = 'spell_shadow_summonfelhunter';
				}
				elseif( $row['type'] == $this->locale['Felguard'] )
				{
					$row['icon'] = 'spell_shadow_summonfelguard';
				}
				elseif( $row['type'] == $this->locale['Infernal'] )
				{
					$row['icon'] = 'spell_shadow_summoninfernal';
				}
				// End Warlock Pet Icon Fix

				if( $row['icon'] == '' || !isset($row['icon']) )
				{
					$row['icon'] = 'inv_misc_questionmark';
				}

				$roster->tpl->assign_block_vars('pet',array(
					'ID'        => $petNum,
					'NAME'      => stripslashes($row['name']),
					'LEVEL'     => $row['level'],
					'TYPE'      => stripslashes($row['type']),
					'HEALTH'    => (isset($row['health']) ? $row['health'] : '0'),
					'POWER'     => (isset($row['mana']) ? $row['mana'] : '0'),
					'ICON'      => $row['icon'],
					'TOTAL_TP'  => $row['totaltp'],

					'TOOLTIP' => makeOverlib($row['name'],$row['type'],'',2,'',',WRAP'),

					'L_POWER' => $row['power'],

					'S_EXP'      => $expbar_show,
					'EXP_AMOUNT' => $expbar_amount,
					'EXP_MAX'    => $expbar_max,
					'EXP_PERC'   => $exp_percent,
					)
				);

				// Print Resistance
				$this->pet_resist('arcane',$row);
				$this->pet_resist('fire',$row);
				$this->pet_resist('nature',$row);
				$this->pet_resist('frost',$row);
				$this->pet_resist('shadow',$row);

				// Print stats boxes
				$roster->tpl->assign_block_vars('pet.box_stats',array());
				$this->pet_stat('stat_str',$row);
				$this->pet_stat('stat_agl',$row);
				$this->pet_stat('stat_sta',$row);
				$this->pet_stat('stat_int',$row);
				$this->pet_stat('stat_spr',$row);
				$this->pet_stat('stat_armor',$row);

				$roster->tpl->assign_block_vars('pet.box_stats',array());
				$this->pet_wskill($row);
				$this->pet_wdamage($row);
				$this->pet_stat('melee_power',$row);
				$this->pet_stat('melee_hit',$row);
				$this->pet_stat('melee_crit',$row);
				$this->pet_resilience($row);

				$petNum++;
			}
		}
		return (bool)$petNum;
	}


	/**
	 * Build Pet stats
	 *
	 * @param string $statname
	 * @param array $data
	 * @return string
	 */
	function pet_stat( $statname , $data )
	{
		global $roster;

		switch( $statname )
		{
			case 'stat_str':
				$name = $roster->locale->act['strength'];
				$tooltip = $roster->locale->act['strength_tooltip'];
				break;

			case 'stat_int':
				$name = $roster->locale->act['intellect'];
				$tooltip = $roster->locale->act['intellect_tooltip'];
				break;

			case 'stat_sta':
				$name = $roster->locale->act['stamina'];
				$tooltip = $roster->locale->act['stamina_tooltip'];
				break;

			case 'stat_spr':
				$name = $roster->locale->act['spirit'];
				$tooltip = $roster->locale->act['spirit_tooltip'];
				break;

			case 'stat_agl':
				$name = $roster->locale->act['agility'];
				$tooltip = $roster->locale->act['agility_tooltip'];
				break;

			case 'stat_armor':
				$name = $roster->locale->act['armor'];
				$tooltip = sprintf($roster->locale->act['armor_tooltip'],$this->data['mitigation']);
				break;

			case 'melee_power':
				$lname = $roster->locale->act['melee_att_power'];
				$name = $roster->locale->act['power'];
				$tooltip = sprintf($roster->locale->act['melee_att_power_tooltip'], $data['melee_power_dps']);
				break;

			case 'melee_hit':
				$name = $roster->locale->act['weapon_hit_rating'];
				$tooltip = $roster->locale->act['weapon_hit_rating_tooltip'];
				break;

			case 'melee_crit':
				$name = $roster->locale->act['weapon_crit_rating'];
				$tooltip = sprintf($roster->locale->act['weapon_crit_rating_tooltip'], $data['melee_crit_chance']);
				break;
		}

		if( isset($lname) )
		{
			$tooltipheader = $lname . ' ' . $this->rating_long($statname,$data);
		}
		else
		{
			$tooltipheader = $name . ' ' . $this->rating_long($statname,$data);
		}

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->pet_stat_line($name, $this->rating_short($statname,$data), $line);
	}


	/**
	 * Build Pet weapon skill
	 *
	 * @param array $data
	 * @return string
	 */
	function pet_wskill( $data )
	{
		global $roster;

		$value = '<strong class="white">' . $data['melee_mhand_skill'] . '</strong>';
		$name = $roster->locale->act['weapon_skill'];
		$tooltipheader = $roster->locale->act['mainhand'];
		$tooltip = sprintf($roster->locale->act['weapon_skill_tooltip'], $data['melee_mhand_skill'], $data['melee_mhand_rating']);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->pet_stat_line($name, $value, $line);
	}


	/**
	 * Build Pet weapon damage
	 *
	 * @param array $data
	 * @return string
	 */
	function pet_wdamage( $data )
	{
		global $roster;

		$value = '<strong class="white">' . $data['melee_mhand_mindam'] . '</strong>' . '-' . '<strong class="white">' . $data['melee_mhand_maxdam'] . '</strong>';
		$name = $roster->locale->act['damage'];
		$tooltipheader = $roster->locale->act['mainhand'];
		$tooltip = sprintf($roster->locale->act['damage_tooltip'], $data['melee_mhand_speed'], $data['melee_mhand_mindam'], $data['melee_mhand_maxdam'], $data['melee_mhand_dps']);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->pet_stat_line($name, $value, $line);
	}


	/**
	 * Build Pet resists
	 *
	 * @param string $resname
	 * @param array $data
	 * @return string
	 */
	function pet_resist( $resname , $data )
	{
		global $roster;

		switch( $resname )
		{
			case 'fire':
				$name = $roster->locale->act['res_fire'];
				$tooltip = $roster->locale->act['res_fire_tooltip'];
				$color = 'red';
				break;

			case 'nature':
				$name = $roster->locale->act['res_nature'];
				$tooltip = $roster->locale->act['res_nature_tooltip'];
				$color = 'green';
				break;

			case 'arcane':
				$name = $roster->locale->act['res_arcane'];
				$tooltip = $roster->locale->act['res_arcane_tooltip'];
				$color = 'yellow';
				break;

			case 'frost':
				$name = $roster->locale->act['res_frost'];
				$tooltip = $roster->locale->act['res_frost_tooltip'];
				$color = 'blue';
				break;

			case 'shadow':
				$name = $roster->locale->act['res_shadow'];
				$tooltip = $roster->locale->act['res_shadow_tooltip'];
				$color = 'purple';
				break;
		}

		$tooltip = '<span style="color:' . $color . ';font-size:11px;font-weight:bold;">' . $name . '</span> ' . $this->rating_long('res_' . $resname,$data) . '<br />'
				 . '<span style="color:#DFB801;text-align:left;">' . $tooltip . '</span>';

		$roster->tpl->assign_block_vars('pet.resist',array(
			'NAME'  => $name,
			'CLASS' => $resname,
			'COLOR' => $color,
			'VALUE' => $data['res_' . $resname . '_c'],
			'TOOLTIP' => makeOverlib($tooltip,'','',2,'',''),
			)
		);
	}


	/**
	 * Build Pet resilience
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	function pet_resilience( $data )
	{
		global $roster;

		$name = $roster->locale->act['resilience'];
		$value = min($data['stat_res_melee'],$data['stat_res_ranged'],$data['stat_res_spell']);

		$tooltipheader = $name;
		$tooltip  = '<div><span style="float:right;">' . $data['stat_res_melee'] . '</span>' . $roster->locale->act['melee'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . $data['stat_res_ranged'] . '</span>' . $roster->locale->act['ranged'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . $data['stat_res_spell'] . '</span>' . $roster->locale->act['spell'] . '</div>';


		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->pet_stat_line($name, '<strong class="white">' . $value . '</strong>', $line);
	}


	/**
	 * Build stat line
	 *
	 * @param string $label
	 * @param string $value
	 * @param string $tooltip
	 * @return string
	 */
	function pet_stat_line( $label , $value , $tooltip )
	{
		global $roster;

		$roster->tpl->assign_block_vars('pet.box_stats.statline',array(
			'NAME'  => $label,
			'VALUE' => $value,
			'TOOLTIP' => makeOverlib($tooltip,'','',2,'','')
			)
		);
	}


	/**
	 * Build stat line
	 *
	 * @param string $label
	 * @param string $value
	 * @param string $tooltip
	 * @return string
	 */
	function stat_line( $label , $value , $tooltip )
	{
		global $roster;

		$roster->tpl->assign_block_vars('box_stats.statline',array(
			'NAME'  => $label,
			'VALUE' => $value,
			'TOOLTIP' => makeOverlib($tooltip,'','',2,'','')
			)
		);
	}


	/**
	 * Build short rating value
	 *
	 * @param string $statname
	 * @param array $data_or Alternative data to use
	 * @return string
	 */
	function rating_short( $statname , $data_or=false )
	{
		if( $data_or == false )
		{
			$data = $this->data;
		}
		else
		{
			$data = $data_or;
		}

		$base = $data[$statname];
		$current = $data[$statname . '_c'];
		$buff = $data[$statname . '_b'];
		$debuff = -$data[$statname . '_d'];

		if( $buff > 0 && $debuff > 0 )
		{
			$color = 'purple';
		}
		elseif( $buff > 0 )
		{
			$color = 'green';
		}
		elseif( $debuff > 0 )
		{
			$color = 'red';
		}
		else
		{
			$color = 'white';
		}

		return '<strong class="' . $color . '">' . $current . '</strong>';
	}


	/**
	 * Build long rating value
	 *
	 * @param string $statname
	 * @param array $data_or Alternative data to use
	 * @return string
	 */
	function rating_long( $statname , $data_or=false )
	{
		if( $data_or == false )
		{
			$data = $this->data;
		}
		else
		{
			$data = $data_or;
		}

		$base = $data[$statname];
		$current = $data[$statname . '_c'];
		$buff = $data[$statname . '_b'];
		$debuff = -$data[$statname . '_d'];

		$tooltipheader = $current;

		if( $base != $current)
		{
			$tooltipheader .= " ($base";
			if( $buff > 0 )
			{
				$tooltipheader .= ' <span class="green">+ ' . $buff . '</span>';
			}
			if( $debuff > 0 )
			{
				$tooltipheader .= ' <span class="red">- ' . $debuff . '</span>';
			}
			$tooltipheader .= ')';
		}

		return $tooltipheader;
	}


	/**
	 * Build a status box
	 *
	 * @param string $cat
	 * @param string $side
	 * @param bool $visible
	 */
	function status_box( $cat , $side , $visible=false )
	{
		global $roster;

		$roster->tpl->assign_block_vars('box_stats',array(
			'ID'   => $cat . $side,
			'SHOW' => $visible
			)
		);

		switch( $cat )
		{
			case 'stats':
				$this->box_stat_line('stat_str');
				$this->box_stat_line('stat_agl');
				$this->box_stat_line('stat_sta');
				$this->box_stat_line('stat_int');
				$this->box_stat_line('stat_spr');
				$this->box_stat_line('stat_armor');
				break;

			case 'melee':
				$this->wdamage('melee');
				$this->wspeed('melee');
				$this->box_stat_line('melee_power');
				$this->box_stat_line('melee_hit');
				$this->box_stat_line('melee_crit');
				$this->box_stat_line('melee_expertise');
				break;

			case 'ranged':
				$this->wskill('ranged');
				$this->wdamage('ranged');
				$this->wspeed('ranged');
				$this->box_stat_line('ranged_power');
				$this->box_stat_line('ranged_hit');
				$this->box_stat_line('ranged_crit');
				break;

			case 'spell':
				$this->spell_damage();
				$this->status_value('spell_healing');
				$this->box_stat_line('spell_hit');
				$this->spell_crit();
				$this->status_value('spell_penetration');
				$this->status_value('mana_regen');
				break;

			case 'defense':
				$this->box_stat_line('stat_armor');
				$this->defense_rating();
				$this->defense_line('dodge');
				$this->defense_line('parry');
				$this->defense_line('block');
				$this->resilience();
				break;
		}
	}


	/**
	 * Build a status line
	 *
	 * @param string $statname
	 * @return string
	 */
	function box_stat_line( $statname )
	{
		global $roster;

		switch( $statname )
		{
			case 'stat_str':
				$name = $roster->locale->act['strength'];
				$tooltip = $roster->locale->act['strength_tooltip'];
				break;

			case 'stat_int':
				$name = $roster->locale->act['intellect'];
				$tooltip = $roster->locale->act['intellect_tooltip'];
				break;

			case 'stat_sta':
				$name = $roster->locale->act['stamina'];
				$tooltip = $roster->locale->act['stamina_tooltip'];
				break;

			case 'stat_spr':
				$name = $roster->locale->act['spirit'];
				$tooltip = $roster->locale->act['spirit_tooltip'];
				break;

			case 'stat_agl':
				$name = $roster->locale->act['agility'];
				$tooltip = $roster->locale->act['agility_tooltip'];
				break;

			case 'stat_armor':
				$name = $roster->locale->act['armor'];
				$tooltip = sprintf($roster->locale->act['armor_tooltip'],$this->data['mitigation']);
				break;

			case 'melee_power':
				$lname = $roster->locale->act['melee_att_power'];
				$name = $roster->locale->act['power'];
				$tooltip = sprintf($roster->locale->act['melee_att_power_tooltip'], $this->data['melee_power_dps']);
				break;

			case 'melee_hit':
				$name = $roster->locale->act['weapon_hit_rating'];
				$tooltip = $roster->locale->act['weapon_hit_rating_tooltip'];
				break;

			case 'melee_expertise':
				$name = $roster->locale->act['weapon_expertise'];
				$tooltip = $roster->locale->act['weapon_expertise_tooltip'];
				break;

			case 'melee_crit':
				$name = $roster->locale->act['weapon_crit_rating'];
				$tooltip = sprintf($roster->locale->act['weapon_crit_rating_tooltip'], $this->data['melee_crit_chance']);
				break;

			case 'ranged_power':
				$lname = $roster->locale->act['ranged_att_power'];
				$name = $roster->locale->act['power'];
				$tooltip = sprintf($roster->locale->act['ranged_att_power_tooltip'], $this->data['ranged_power_dps']);
				break;

			case 'ranged_hit':
				$name = $roster->locale->act['weapon_hit_rating'];
				$tooltip = $roster->locale->act['weapon_hit_rating_tooltip'];
				break;

			case 'ranged_crit':
				$name = $roster->locale->act['weapon_crit_rating'];
				$tooltip = sprintf($roster->locale->act['weapon_crit_rating_tooltip'], $this->data['ranged_crit_chance']);
				break;

			case 'spell_hit':
				$name = $roster->locale->act['spell_hit_rating'];
				$tooltip = $roster->locale->act['spell_hit_rating_tooltip'];
				break;
		}

		if( isset($lname) )
		{
			$tooltipheader = $lname . ' ' . $this->rating_long($statname);
		}
		else
		{
			$tooltipheader = $name . ' ' . $this->rating_long($statname);
		}

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->stat_line($name, $this->rating_short($statname), $line);
	}


	/**
	 * Build a special status line
	 *
	 * @param string $statname
	 * @return unknown
	 */
	function status_value( $statname )
	{
		global $roster;

		$value = $this->data[$statname];
		switch( $statname )
		{
			case 'spell_penetration':
				$name = $roster->locale->act['spell_penetration'];
				$tooltip = $roster->locale->act['spell_penetration_tooltip'];
				break;

			case 'mana_regen':
				$name = $roster->locale->act['mana_regen'];
				$tooltip = sprintf($roster->locale->act['mana_regen_tooltip'],$this->data['mana_regen'],$this->data['mana_regen_cast']);
				break;

			case 'spell_healing':
				$name = $roster->locale->act['spell_healing'];
				$tooltip = sprintf($roster->locale->act['spell_healing_tooltip'],$this->data['spell_healing']);
				break;
		}

		$tooltipheader = (isset($name) ? $name : '');

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->stat_line($name, '<strong class="white">' . $value . '</strong>', $line);
	}


	/**
	 * Build weapon skill
	 *
	 * @param string $location
	 * @return string
	 */
	function wskill( $location )
	{
		global $roster;

		if( $location == 'ranged' )
		{
			$value = '<strong class="white">' . $this->data['ranged_skill'] . '</strong>';
			$name = $roster->locale->act['weapon_skill'];
			$tooltipheader = $roster->locale->act['ranged'];
			$tooltip = sprintf($roster->locale->act['weapon_skill_tooltip'], $this->data['ranged_skill'], $this->data['ranged_rating']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
				  . '<div style="color:#DFB801;">' . $tooltip . '</div>';
		}
		else
		{
			$value = '<strong class="white">' . $this->data['melee_mhand_skill'] . '</strong>';
			$name = $roster->locale->act['weapon_skill'];
			$tooltipheader = $roster->locale->act['mainhand'];
			$tooltip = sprintf($roster->locale->act['weapon_skill_tooltip'], $this->data['melee_mhand_skill'], $this->data['melee_mhand_rating']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
				  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

			if( $this->data['melee_ohand_dps'] > 0 )
			{
				$value .= '/<strong class="white">' . $this->data['melee_ohand_skill'] . '</strong>';
				$tooltipheader = $roster->locale->act['offhand'];
				$tooltip = sprintf($roster->locale->act['weapon_skill_tooltip'], $this->data['melee_ohand_skill'], $this->data['melee_ohand_rating']);

				$line .= '<br /><span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
					   . '<div style="color:#DFB801;">' . $tooltip . '</div>';
			}
		}

		$this->stat_line($name, $value, $line);
	}


	/**
	 * Build weapon damage
	 *
	 * @param string $location
	 * @return string
	 */
	function wdamage( $location )
	{
		global $roster;

		if( $location == 'ranged' )
		{
			$value = '<strong class="white">' . $this->data['ranged_mindam'] . '</strong>' . '-' . '<strong class="white">' . $this->data['ranged_maxdam'] . '</strong>';
			$name = $roster->locale->act['damage'];
			$tooltipheader = $roster->locale->act['ranged'];
			$tooltip = sprintf($roster->locale->act['damage_tooltip'], $this->data['ranged_speed'], $this->data['ranged_mindam'], $this->data['ranged_maxdam'], $this->data['ranged_dps']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
				  . '<div style="color:#DFB801;">' . $tooltip . '</div>';
		}
		else
		{
			$value = '<strong class="white">' . $this->data['melee_mhand_mindam'] . '</strong>-<strong class="white">' . $this->data['melee_mhand_maxdam'] . '</strong>';
			$name = $roster->locale->act['damage'];
			$tooltipheader = $roster->locale->act['mainhand'];
			$tooltip = sprintf($roster->locale->act['damage_tooltip'], $this->data['melee_mhand_speed'], $this->data['melee_mhand_mindam'], $this->data['melee_mhand_maxdam'], $this->data['melee_mhand_dps']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
				  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

			if( $this->data['melee_ohand_dps'] > 0 )
			{
				// This will only print then there is no main hand data because printing both stats is too long for the box
				if( empty($this->data['melee_mhand_mindam']) )
				{
					$value .= '<strong class="white">' . $this->data['melee_ohand_mindam'] . '</strong>-<strong class="white">' . $this->data['melee_ohand_maxdam'] . '</strong>';
				}
				$tooltipheader = $roster->locale->act['offhand'];
				$tooltip = sprintf($roster->locale->act['damage_tooltip'], $this->data['melee_ohand_speed'], $this->data['melee_ohand_mindam'], $this->data['melee_ohand_maxdam'], $this->data['melee_ohand_dps']);

				$line .= '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
					   . '<div style="color:#DFB801;">' . $tooltip . '</div>';
			}
		}

		$this->stat_line($name, $value, $line);
	}


	/**
	 * Build weapon speed
	 *
	 * @param string $location
	 * @return string
	 */
	function wspeed( $location )
	{
		global $roster;

		if( $location == 'ranged' )
		{
			$value = '<strong class="white">' . $this->data['ranged_speed'] . '</strong>';
			$name = $roster->locale->act['speed'];
			$tooltipheader = $roster->locale->act['atk_speed'] . ' ' . $value;
			$tooltip = $roster->locale->act['haste_tooltip'] . $this->rating_long('ranged_haste');

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
				  . '<span style="color:#DFB801;">' . $tooltip . '</span>';
		}
		else
		{
			$value = '<strong class="white">' . $this->data['melee_mhand_speed'] . '</strong>';
			$name = $roster->locale->act['speed'];

			if( $this->data['melee_ohand_dps'] > 0 )
			{
				$value .= '/<strong class="white">' . $this->data['melee_ohand_speed'] . '</strong>';
			}

			$tooltipheader = $roster->locale->act['atk_speed'] . ' ' . $value;
			$tooltip = $roster->locale->act['haste_tooltip'] . $this->rating_long('melee_haste');

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
				  . '<span style="color:#DFB801;">' . $tooltip . '</span>';
		}

		$this->stat_line($name, $value, $line);
	}


	/**
	 * Build spell damage
	 *
	 * @return string
	 */
	function spell_damage()
	{
		global $roster, $addon;

		$name = $roster->locale->act['spell_damage'];
		$value = '<strong class="white">' . $this->data['spell_damage'] . '</strong>';

		$tooltipheader = $name . ' ' . $value;

		$tooltip  = '<div><span style="float:right;">' . $this->data['spell_damage_holy'] . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-holy.gif" alt="" />' . $roster->locale->act['holy'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . $this->data['spell_damage_fire'] . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-fire.gif" alt="" />' . $roster->locale->act['fire'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . $this->data['spell_damage_nature'] . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-nature.gif" alt="" />' . $roster->locale->act['nature'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . $this->data['spell_damage_frost'] . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-frost.gif" alt="" />' . $roster->locale->act['frost'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . $this->data['spell_damage_shadow'] . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-shadow.gif" alt="" />' . $roster->locale->act['shadow'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . $this->data['spell_damage_arcane'] . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-arcane.gif" alt="" />' . $roster->locale->act['arcane'] . '</div>';

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->stat_line($name, $value, $line);
	}


	/**
	 * Build spell crit chance
	 *
	 * @return string
	 */
	function spell_crit()
	{
		global $roster, $addon;

		$name = $roster->locale->act['spell_crit_chance'];
		$value = '<strong class="white">' . $this->data['spell_crit_chance'] . '</strong>';

		$tooltipheader = $roster->locale->act['spell_crit_rating'] . ' ' . $this->rating_long('spell_crit');

		$tooltip = '<div><span style="float:right;">' . sprintf('%.2f%%',$this->data['spell_crit_chance_holy']) . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-holy.gif" alt="" />' . $roster->locale->act['holy'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . sprintf('%.2f%%',$this->data['spell_crit_chance_fire']) . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-fire.gif" alt="" />' . $roster->locale->act['fire'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . sprintf('%.2f%%',$this->data['spell_crit_chance_nature']) . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-nature.gif" alt="" />' . $roster->locale->act['nature'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . sprintf('%.2f%%',$this->data['spell_crit_chance_frost']) . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-frost.gif" alt="" />' . $roster->locale->act['frost'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . sprintf('%.2f%%',$this->data['spell_crit_chance_shadow']) . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-shadow.gif" alt="" />' . $roster->locale->act['shadow'] . '</div>';
		$tooltip .= '<div><span style="float:right;">' . sprintf('%.2f%%',$this->data['spell_crit_chance_arcane']) . '</span><img src="' . $addon['tpl_image_path'] . 'resist/icon-arcane.gif" alt="" />' . $roster->locale->act['arcane'] . '</div>';

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->stat_line($name, $value, $line);
	}


	/**
	 * Build defense rating value
	 *
	 * @return string
	 */
	function defense_rating()
	{
		global $roster;

		$qry = "SELECT `skill_level` FROM `" . $roster->db->table('skills') . "` WHERE `member_id` = " . $this->data['member_id'] . " AND `skill_name` = '" . $this->locale['defense'] . "';";
		$result = $roster->db->query($qry);

		if( !$result )
		{
			$value = 'N/A';
		}
		else
		{
			$row = $roster->db->fetch($result,SQL_NUM);
			$value = explode(':',$row[0]);
			$value = $value[0];
			$roster->db->free_result($result);
			unset($row);
		}

		$name = $roster->locale->act['defense'];
		$tooltipheader = $name . ' ' . $value;

		$tooltip = $roster->locale->act['defense_rating'] . $this->rating_long('stat_defr');

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->stat_line($name, '<strong class="white">' . $value . '</strong>', $line);
	}


	/**
	 * Build a defense value
	 *
	 * @param string $statname
	 *
	 * @return string
	 */
	function defense_line( $statname )
	{
		global $roster;

		$name = $roster->locale->act[$statname];
		$value = $this->data[$statname];

		$tooltipheader = $name . ' ' . $this->rating_long('stat_' . $statname);
		$tooltip = sprintf($roster->locale->act['def_tooltip'],$name);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->stat_line($name, '<strong class="white">' . $value . '%</strong>', $line);
	}


	/**
	 * Build resiliance value
	 *
	 * @return string
	 */
	function resilience()
	{
		global $roster;

		$name = $roster->locale->act['resilience'];
		$value = min($this->data['stat_res_melee'],$this->data['stat_res_ranged'],$this->data['stat_res_spell']);

		$tooltipheader = $name;
		$tooltip = '<div><span style="float:right;">' . $this->data['stat_res_melee'] . '</span>' . $roster->locale->act['melee'] . '</div>'
				 . '<div><span style="float:right;">' . $this->data['stat_res_ranged'] . '</span>' . $roster->locale->act['ranged'] . '</div>'
				 . '<div><span style="float:right;">' . $this->data['stat_res_spell'] . '</span>' . $roster->locale->act['spell'] . '</div>';

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">' . $tooltipheader . '</span><br />'
			  . '<span style="color:#DFB801;">' . $tooltip . '</span>';

		$this->stat_line($name, '<strong class="white">' . $value . '</strong>', $line);
	}


	/**
	 * Build a resistance value
	 *
	 * @param string $resname
	 * @return string
	 */
	function resist_value( $resname )
	{
		global $roster;

		switch( $resname )
		{
			case 'fire':
				$name = $roster->locale->act['res_fire'];
				$tooltip = $roster->locale->act['res_fire_tooltip'];
				$color = 'red';
				break;

			case 'nature':
				$name = $roster->locale->act['res_nature'];
				$tooltip = $roster->locale->act['res_nature_tooltip'];
				$color = 'green';
				break;

			case 'arcane':
				$name = $roster->locale->act['res_arcane'];
				$tooltip = $roster->locale->act['res_arcane_tooltip'];
				$color = 'yellow';
				break;

			case 'frost':
				$name = $roster->locale->act['res_frost'];
				$tooltip = $roster->locale->act['res_frost_tooltip'];
				$color = 'blue';
				break;

			case 'shadow':
				$name = $roster->locale->act['res_shadow'];
				$tooltip = $roster->locale->act['res_shadow_tooltip'];
				$color = 'purple';
				break;
		}

		$tooltip = '<span style="color:' . $color . ';font-size:11px;font-weight:bold;">' . $name . '</span> ' . $this->rating_long('res_'.$resname) . '<br />'
				 . '<span style="color:#DFB801;text-align:left;">' . $tooltip . '</span>';

		$roster->tpl->assign_block_vars('resist',array(
			'NAME'  => $name,
			'CLASS' => $resname,
			'COLOR' => $color,
			'VALUE' => $this->data['res_' . $resname . '_c'],
			'TOOLTIP' => makeOverlib($tooltip,'','',2,'',''),
			)
		);
	}


	/**
	 * Build a equiped item slot
	 *
	 * @param string $slot
	 * @return string
	 */
	function equip_slot( $slot )
	{
		global $roster;

		if( isset($this->equip[$slot]) )
		{
			$roster->tpl->assign_block_vars('equipment',array(
				'SLOT'     => $slot,
				'ICON'     => $this->equip[$slot]->tpl_get_icon(),
				'TOOLTIP'  => $this->equip[$slot]->tpl_get_tooltip(),
				'ITEMLINK' => $this->equip[$slot]->tpl_get_itemlink(),
				'QTY'      => $this->equip[$slot]->quantity,
				'S_AMMO'   => $slot == 'Ammo'
				)
			);
		}
		else
		{
			$roster->tpl->assign_block_vars('equipment',array(
				'SLOT'     => $slot,
				'ICON'     => $roster->config['img_url'] . 'pixel.gif',
				'TOOLTIP'  => makeOverlib($roster->locale->act['empty_equip'],$roster->locale->act[$slot],'',2,'',',WRAP'),
				'ITEMLINK' => '',
				'QTY'      => 0,
				'S_AMMO'   => $slot == 'Ammo'
				)
			);
		}
	}


	/**
	 * Build Talents
	 *
	 * @return string
	 */
	function show_talents()
	{
		global $roster, $addon;

		$sqlquery = "SELECT * FROM `" . $roster->db->table('talenttree') . "` WHERE `member_id` = '" . $this->data['member_id'] . "' ORDER BY `order`;";
		$trees = $roster->db->query($sqlquery);

		$tree_rows = $roster->db->num_rows($trees);

		if( $tree_rows > 0 )
		{
			// Set vars for talent specialization
			$talent_spec = $spec_points_temp = 0;
			$spec_points = array();

			for( $j=0; $j < $tree_rows; $j++)
			{
				$treedata = $roster->db->fetch($trees,SQL_ASSOC);

				// does this tree have the most points?
				if( $treedata['pointsspent'] > $spec_points_temp )
				{
/*					if( ($treedata['pointsspent'] - $spec_points_temp) < 5 )
					{
						$talent_spec = 0;
						$talent_spec_name = $roster->locale->act['hybrid'];
						$talent_spec_icon = 'hybrid';
					}
					else
					{*/
						$talent_spec = $j;
						$talent_spec_name = $treedata['tree'];
						$talent_spec_icon = $treedata['background'];
//					}
					$spec_points_temp = $treedata['pointsspent'];
				}

				// store our talent points
				$spec_points[] = $treedata['pointsspent'];

				$treelayer[$j]['name'] = $treedata['tree'];
				$treelayer[$j]['image'] = $treedata['background'];
				$treelayer[$j]['points'] = $treedata['pointsspent'];
				$treelayer[$j]['talents'] = $this->_talent_layer($treedata['tree']);
			}

			foreach( $treelayer as $treeindex => $tree )
			{
				$roster->tpl->assign_block_vars('talent_tree',array(
					'L_POINTS_SPENT' => sprintf($roster->locale->act['pointsspent'],$tree['name']),
					'NAME'     => $tree['name'],
					'ID'       => $treeindex,
					'POINTS'   => $tree['points'],
					'ICON'     => $tree['image'],
					'SELECTED' => ( $talent_spec == $treeindex ? true : false )
					)
				);

				foreach( $tree['talents'] as $row )
				{
					$roster->tpl->assign_block_vars('talent_tree.row',array());

					foreach( $row as $cell )
					{
						$roster->tpl->assign_block_vars('talent_tree.row.cell',array(
							'NAME'    => $cell['name'],
							'RANK'    => ( isset($cell['rank']) ? $cell['rank'] : 0 ),
							'MAXRANK' => ( isset($cell['maxrank']) ? $cell['maxrank'] : 0 ),
							'MAX'     => ( isset($cell['rank']) ? $cell['maxrank'] : 0 ),
							'TOOLTIP' => ( isset($cell['tooltip']) ? $cell['tooltip'] : '' ),
							'ICON'    => ( isset($cell['image']) ? $cell['image'] : '' )
							)
						);
					}
				}
			}

			$roster->tpl->assign_vars(array(
				'U_TALENT_EXPORT' => $roster->locale->act['export_url'] . strtolower($this->data['classEn']) . '/talents.html?' . $this->talent_build_url,
				'SPEC_POINTS'     => implode(' / ',$spec_points),
				'SPEC_NAME'       => $talent_spec_name,
				'SPEC_ICON'       => $talent_spec_icon,
				)
			);

			return true;
		}

		return false;
	}


	/**
	 * Build a talent tree
	 *
	 * @param string $treename
	 * @return array
	 */
	function _talent_layer( $treename )
	{
		global $roster;

		$sqlquery = "SELECT * FROM `" . $roster->db->table('talents') . "` WHERE `member_id` = '" . $this->data['member_id'] . "' AND `tree` = '" . $treename . "' ORDER BY `row` ASC , `column` ASC";

		$result = $roster->db->query($sqlquery);

		$returndata = array();
		if( $roster->db->num_rows($result) > 0 )
		{
			// initialize the rows and cells
			for( $r=1; $r < 11; $r++ )
			{
				for( $c=1; $c < 5; $c++ )
				{
					$returndata[$r][$c]['name'] = '';
				}
			}

			while( $talentdata = $roster->db->fetch($result,SQL_ASSOC) )
			{
				$r = $talentdata['row'];
				$c = $talentdata['column'];

				$this->talent_build_url .= $talentdata['rank'];

				$returndata[$r][$c]['name'] = $talentdata['name'];
				$returndata[$r][$c]['rank'] = $talentdata['rank'];
				$returndata[$r][$c]['maxrank'] = $talentdata['maxrank'];
				$returndata[$r][$c]['row'] = $r;
				$returndata[$r][$c]['column'] = $c;
				$returndata[$r][$c]['image'] = $talentdata['texture'].'.'.$roster->config['img_suffix'];
				$returndata[$r][$c]['tooltip'] = makeOverlib($talentdata['tooltip'],'','',0,$this->data['clientLocale']);
			}
		}
		return $returndata;
	}


	/**
	 * Build character skills
	 *
	 * @return string
	 */
	function show_skills()
	{
		global $roster, $addon;

		$skillData = $this->_skill_tab_values();

		if( count($skillData) > 0 )
		{
			foreach( $skillData as $sindex => $skill )
			{
				$roster->tpl->assign_block_vars('skill',array(
					'ID'      => $sindex,
					'NAME'    => $skill['name'],
					'NAME_ID' => $this->locale['skill_to_id'][$skill['name']]
					)
				);

				foreach( $skill['bars'] as $skillbar )
				{
					$roster->tpl->assign_block_vars('skill.bar',array(
						'NAME'     => $skillbar['name'],
						'WIDTH'    => $skillbar['barwidth'],
						'VALUE'    => $skillbar['value'],
						'MAXVALUE' => $skillbar['maxvalue']
						)
					);

					if( $skill['name'] == $this->locale['professions'] )
					{
						$roster->tpl->assign_block_vars('professions',array(
							'NAME'     => $skillbar['name'],
							'WIDTH'    => $skillbar['barwidth'],
							'VALUE'    => $skillbar['value'],
							'MAXVALUE' => $skillbar['maxvalue'],
							'ICON'     => $this->locale['ts_iconArray'][$skillbar['name']]
							)
						);
					}
				}
			}
			return true;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Build a skill bars data
	 *
	 * @param array $skilldata
	 * @return array
	 */
	function _skill_bar_values( $skilldata )
	{
		list($level, $max) = explode( ':', $skilldata['skill_level'] );

		$returnData['maxvalue'] = $max;
		$returnData['value'] = $level;
		$returnData['name'] = $skilldata['skill_name'];
		$returnData['barwidth'] = ceil($level/$max*100);

		return $returnData;
	}


	/**
	 * Build skill values
	 *
	 * @return mixed Array on success, false on fail
	 */
	function _skill_tab_values()
	{
		global $roster;

		$query = "SELECT * FROM `".$roster->db->table('skills')."` WHERE `member_id` = '".$this->data['member_id']."' ORDER BY `skill_order` ASC, `skill_name` ASC;";
		$result = $roster->db->query( $query );

		$skill_rows = $roster->db->num_rows($result);

		$i=0;
		$j=0;
		if ( $skill_rows > 0 )
		{
			$data = $roster->db->fetch($result,SQL_ASSOC);
			$skillInfo[$i]['name'] = $data['skill_type'];

			for( $r=0; $r < $skill_rows; $r++ )
			{
				if( $skillInfo[$i]['name'] != $data['skill_type'] )
				{
					$i++;
					$j=0;
					$skillInfo[$i]['name'] = $data['skill_type'];
				}
				$skillInfo[$i]['bars'][$j] = $this->_skill_bar_values($data);
				$j++;
				$data = $roster->db->fetch($result,SQL_ASSOC);
			}
			return $skillInfo;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Build character reputation
	 *
	 * @return mixed Array on success, false on fail
	 */
	function show_reputation()
	{
		global $roster, $addon;

		$repData = $this->_rep_tab_values();

		if( is_array($repData) )
		{
			foreach( $repData as $findex => $faction )
			{
				$roster->tpl->assign_block_vars('rep',array(
					'ID'      => $findex,
					'NAME'    => $faction['name'],
					'NAME_ID' => $this->locale['faction_to_id'][$faction['name']]
					)
				);

				foreach( $faction['bars'] as $repbar )
				{
					$roster->tpl->assign_block_vars('rep.bar',array(
						'ID'       => $repbar['barid'],
						'NAME'     => $repbar['name'],
						'WIDTH'    => $repbar['barwidth'],
						'IMAGE'    => $repbar['image'],
						'STANDING' => $repbar['standing'],
						'VALUE'    => $repbar['value'],
						'MAXVALUE' => $repbar['maxvalue'],
						'ATWAR'    => $repbar['atwar']
						)
					);
				}
			}
			return true;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Build a reputation bars data
	 *
	 * @return array
	 */
	function _rep_tab_values()
	{
		global $roster;

		$query= "SELECT * FROM `".$roster->db->table('reputation')."` WHERE `member_id` = '".$this->data['member_id']."' ORDER BY `faction` ASC, `name` ASC;";
		$result = $roster->db->query( $query );

		$rep_rows = $roster->db->num_rows($result);

		$i=0;
		$j=0;
		if ( $rep_rows > 0 )
		{
			$data = $roster->db->fetch($result,SQL_ASSOC);
			$repInfo[$i]['name'] = $data['faction'];

			for( $r=0; $r < $rep_rows; $r++ )
			{
				if( $repInfo[$i]['name'] != $data['faction'] )
				{
					$i++;
					$j=0;
					$repInfo[$i]['name'] = $data['faction'];
				}
				$repInfo[$i]['bars'][$j] = $this->_rep_bar_values($data);
				$j++;
				$data = $roster->db->fetch($result,SQL_ASSOC);
			}
			return $repInfo;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Build reputation values
	 *
	 * @param array $repdata
	 * @return array
	 */
	function _rep_bar_values( $repdata )
	{
		static $repnum = 0;

		global $roster, $addon;

		$level = $repdata['curr_rep'];
		$max = $repdata['max_rep'];

		$img = array(
			$this->locale['exalted'] => 'exalted',
			$this->locale['revered'] => 'revered',
			$this->locale['honored'] => 'honored',
			$this->locale['friendly'] => 'friendly',
			$this->locale['neutral'] => 'neutral',
			$this->locale['unfriendly'] => 'unfriendly',
			$this->locale['hostile'] => 'hostile',
			$this->locale['hated'] => 'hated'
		);

		$returnData['name'] = $repdata['name'];
		$returnData['barwidth'] = ceil($level / $max * 100);
		$returnData['image'] = $img[$repdata['Standing']];
		$returnData['barid'] = $repnum;
		$returnData['standing'] = $repdata['Standing'];
		$returnData['value'] = $level;
		$returnData['maxvalue'] = $max;
		$returnData['atwar'] = $repdata['AtWar'];

		$repnum++;

		return $returnData;
	}


	/**
	 * Build pvp stats
	 *
	 * @return string
	 */
	function show_pvp()
	{
		global $roster;

		$roster->tpl->assign_vars(array(
			'HONOR_POINTS' => $this->data['honorpoints'],
			'ARENA_POINTS' => $this->data['arenapoints'],
			'SESSION_HK'   => $this->data['sessionHK'],
			'YEST_HK'      => $this->data['yesterdayHK'],
			'LIFE_HK'      => $this->data['lifetimeHK'],
			'SESSION_CP'   => $this->data['sessionCP'],
			'YEST_CP'      => $this->data['yesterdayContribution']
			)
		);
	}

	function _alt_name_hover()
	{
		global $roster;

		$alt_hover = '';
		if( active_addon('memberslist') )
		{
			$sql = "SELECT `main_id` FROM `"
				 . $roster->db->table('alts', 'memberslist')
				 . "` WHERE `member_id` = " . $this->data['member_id'] . ";";

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
							$caption = '<a href="' . makelink('char-info&amp;a=c:' . $alt['member_id']) . '">'
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
					$alt_hover = 'style="cursor:pointer;" onmouseover="return overlib(overlib_alt_html,CAPTION,overlib_alt_cap);" '
									 . 'onclick="return overlib(overlib_alt_html,CAPTION,overlib_alt_cap,STICKY,OFFSETX,-5,OFFSETY,-5,NOCLOSE);" '
									 . 'onmouseout="return nd();"';
				}
			}
		}
		$roster->tpl->assign_var('ALT_TOOLTIP',$alt_hover);
	}

	function _mini_members_list()
	{
		global $roster;

		// Get the scope select data
		$query = 'SELECT `members`.`member_id`, `members`.`name`, `members`.`class`, `members`.`classid`, `members`.`level`, `members`.`guild_title`, `members`.`guild_rank`, `players`.`race`, `players`.`raceid`, `players`.`sex`, `players`.`sexid` '
			   . 'FROM `' . $roster->db->table('members') . '` AS members '
			   . 'LEFT JOIN `' . $roster->db->table('players') . '` AS players ON `members`.`member_id` = `players`.`member_id` '
			   . 'WHERE `members`.`guild_id` = "' . $this->data['guild_id'] . '" '
			   . 'ORDER BY `members`.`level` DESC, `members`.`name` ASC';

		$result = $roster->db->query($query);

		if( !$result )
		{
			trigger_error($roster->db->error());
			return false;
		}

		while( $data = $roster->db->fetch($result,SQL_ASSOC) )
		{
			$roster->tpl->assign_block_vars('mini_memberslist', array(
				'ID'         => $data['member_id'],
				'NAME'       => $data['name'],
				'CLASS'      => $data['class'],
				'CLASS_ID'   => $data['classid'],
				'CLASS_EN'   => strtolower(str_replace(' ','',$roster->locale->wordings['enUS']['id_to_class'][$data['classid']])),
				'LEVEL'      => $data['level'],
				'TITLE'      => $data['guild_title'],
				'RANK'       => $data['guild_rank'],
				'RACE'       => $data['race'],
				'RACE_ID'    => $data['raceid'],
				'RACE_EN'    => ( $data['race'] != '' ? strtolower(str_replace(' ','',$this->locale['race_to_en'][$data['race']])) : '' ),
				'SEX'        => $data['sex'],
				'SEX_ID'     => $data['sexid'],
				'U_LINK'     => ( $data['race'] != '' ? makelink('&amp;a=c:' . $data['member_id'],true) : false ),
				'S_SELECTED' => ( $data['member_id'] == $this->data['member_id'] ? true : false )
				)
			);
		}

        $roster->tpl->assign_var('S_MINI_MEMBERSLIST',( $roster->db->num_rows() > 1 ? true : false ));

		$roster->db->free_result($result);

		return true;
	}

	/**
	 * Main output function
	 */
	function out()
	{
		global $roster, $addon;

		$this->fetchEquip();
		$this->_alt_name_hover();

		// Equipment
		$this->equip_slot('Head');
		$this->equip_slot('Neck');
		$this->equip_slot('Shoulder');
		$this->equip_slot('Back');
		$this->equip_slot('Chest');
		$this->equip_slot('Shirt');
		$this->equip_slot('Tabard');
		$this->equip_slot('Wrist');

		$this->equip_slot('MainHand');
		$this->equip_slot('SecondaryHand');
		$this->equip_slot('Ranged');
		$this->equip_slot('Ammo');

		$this->equip_slot('Hands');
		$this->equip_slot('Waist');
		$this->equip_slot('Legs');
		$this->equip_slot('Feet');
		$this->equip_slot('Finger0');
		$this->equip_slot('Finger1');
		$this->equip_slot('Trinket0');
		$this->equip_slot('Trinket1');

		// Resists
		$this->resist_value('arcane');
		$this->resist_value('fire');
		$this->resist_value('nature');
		$this->resist_value('frost');
		$this->resist_value('shadow');

		if( $roster->auth->getAuthorized($addon['config']['show_played']) )
		{
			$TimeLevelPlayedConverted = seconds_to_time($this->data['timelevelplayed']);
			$TimePlayedConverted = seconds_to_time($this->data['timeplayed']);

			$roster->tpl->assign_block_vars('info_stats',array(
				'NAME'  => $roster->locale->act['timeplayed'],
				'VALUE' => $TimePlayedConverted['days'] . $TimePlayedConverted['hours'] . $TimePlayedConverted['minutes'] . $TimePlayedConverted['seconds']
				)
			);

			$roster->tpl->assign_block_vars('info_stats',array(
				'NAME'  => $roster->locale->act['timelevelplayed'],
				'VALUE' => $TimeLevelPlayedConverted['days'] . $TimeLevelPlayedConverted['hours'] . $TimeLevelPlayedConverted['minutes'] . $TimeLevelPlayedConverted['seconds']
				)
			);
		}

		if( $roster->auth->getAuthorized($addon['config']['show_talents']) && $this->data['talent_points'] )
		{
			$roster->tpl->assign_block_vars('info_stats',array(
				'NAME'  => $roster->locale->act['unusedtalentpoints'],
				'VALUE' => $this->data['talent_points']
				)
			);
		}

		// Code to write a "Max Exp bar" just like in SigGen
		$expbar_amount = $expbar_max = $expbar_rest = '';
		if( $this->data['level'] == ROSTER_MAXCHARLEVEL )
		{
			$exp_percent = 100;
			$expbar_amount = $roster->locale->act['max_exp'];
			$expbar_type = 'max';
		}
		elseif( $this->data['exp'] == '0' )
		{
			$exp_percent = 0;
			$expbar_type = 'normal';
		}
		else
		{
			$xp = explode(':',$this->data['exp']);
			if( isset($xp) && $xp[1] != '0' && $xp[1] != '' )
			{
				$exp_percent = ( $xp[1] > 0 ? floor($xp[0] / $xp[1] * 100) : 0);

				$expbar_amount = $xp[0];
				$expbar_max = $xp[1];

				$expbar_rest = ( $xp[2] > 0 ? $xp[2] : '' );
				$expbar_type = ( $xp[2] > 0 ? 'rested' : 'normal' );
			}
		}

		$roster->tpl->assign_vars(array(
			'EXP_AMOUNT' => $expbar_amount,
			'EXP_MAX'    => $expbar_max,
			'EXP_REST'   => $expbar_rest,
			'EXP_PERC'   => $exp_percent,
			'EXP_TYPE'   => $expbar_type,
			)
		);

		switch( $this->data['classid'] )
		{
			case ROSTER_CLASS_WARRIOR:
			case ROSTER_CLASS_PALADIN:
			case ROSTER_CLASS_ROGUE:
				$rightbox = 'melee';
				break;

			case ROSTER_CLASS_HUNTER:
				$rightbox = 'ranged';
				break;

			case ROSTER_CLASS_SHAMAN:
			case ROSTER_CLASS_DRUID:
			case ROSTER_CLASS_MAGE:
			case ROSTER_CLASS_WARLOCK:
			case ROSTER_CLASS_PRIEST:
				$rightbox = 'spell';
				break;
		}

		$roster->tpl->assign_var('RIGHTBOX', $rightbox);

		// Print stat boxes
		$this->status_box('stats','left',true);
		$this->status_box('melee','left');
		$this->status_box('ranged','left');
		$this->status_box('spell','left');
		$this->status_box('defense','left');
		$this->status_box('stats','right');
		$this->status_box('melee','right',$rightbox=='melee');
		$this->status_box('ranged','right',$rightbox=='ranged');
		$this->status_box('spell','right',$rightbox=='spell');
		$this->status_box('defense','right');

		// Buffs
		$this->show_buffs();

		// PvP
		$this->show_pvp();

		// Mini Memberslist
		$this->_mini_members_list();

		// Item bonuses
		if( $roster->auth->getAuthorized($addon['config']['show_item_bonuses']) )
		{
			require_once($addon['inc_dir'] . 'charbonus.lib.php');
			$char_bonus = new CharBonus($this);
			$char_bonus->dumpBonus();
			unset($char_bonus);
		}

		// Print tabs
		$roster->tpl->assign_block_vars('tabs',array(
			'NAME'     => $roster->locale->act['tab1'],
			'VALUE'    => 'tab1',
			'SELECTED' => true
			)
		);

		// Pet Tab
		if( $roster->auth->getAuthorized($addon['config']['show_tab2']) && ($this->show_pets() || $this->show_companions()) )
		{
			$roster->tpl->assign_block_vars('tabs',array(
				'NAME'     => $roster->locale->act['tab2'],
				'VALUE'    => 'tab2',
				'SELECTED' => false
				)
			);
		}
		else
		{
			$roster->tpl->assign_var('S_PET_TAB',false);
		}

		// Reputation Tab
		if( $roster->auth->getAuthorized($addon['config']['show_tab3']) && $this->show_reputation() )
		{
			$roster->tpl->assign_block_vars('tabs',array(
				'NAME'     => $roster->locale->act['tab3'],
				'VALUE'    => 'tab3',
				'SELECTED' => false
				)
			);
		}
		else
		{
			$roster->tpl->assign_var('S_REP_TAB',false);
		}

		// Skills Tab
		if( $roster->auth->getAuthorized($addon['config']['show_tab4']) && $this->show_skills() )
		{
			$roster->tpl->assign_block_vars('tabs',array(
				'NAME'     => $roster->locale->act['tab4'],
				'VALUE'    => 'tab4',
				'SELECTED' => false
				)
			);
		}
		else
		{
			$roster->tpl->assign_var('S_SKILL_TAB',false);
		}

		// Talents Tab
		if( $roster->auth->getAuthorized($addon['config']['show_talents']) && $this->show_talents() )
		{
			$roster->tpl->assign_block_vars('tabs',array(
				'NAME'     => $roster->locale->act['talents'],
				'VALUE'    => 'tab5',
				'SELECTED' => false
				)
			);
		}
		else
		{
			$roster->tpl->assign_var('S_TALENT_TAB',false);
		}

		// Spell Book Tab
		if( $roster->auth->getAuthorized($addon['config']['show_spellbook']) && $this->show_spellbook() )
		{
			$roster->tpl->assign_block_vars('tabs',array(
				'NAME'     => $roster->locale->act['spellbook'],
				'VALUE'    => 'tab6',
				'SELECTED' => false
				)
			);
		}
		else
		{
			$roster->tpl->assign_var('S_SPELL_TAB',false);
		}

		$roster->tpl->set_filenames(array('char' => $addon['basename'] . '/char.html'));
		return $roster->tpl->fetch('char');
	}
}


/**
 * Gets one characters data using a member id
 *
 * @param int $member_id
 * @return mixed False on failure
 */
function char_get_one_by_id( $member_id )
{
	global $roster;

	$query = "SELECT a.*, b.*, `c`.`guild_name`, DATE_FORMAT(  DATE_ADD(`a`.`dateupdatedutc`, INTERVAL ".$roster->config['localtimeoffset']." HOUR ), '".$roster->locale->act['timeformat']."' ) AS 'update_format' ".
		"FROM `".$roster->db->table('players')."` a, `".$roster->db->table('members')."` b, `".$roster->db->table('guild')."` c " .
		"WHERE `a`.`member_id` = `b`.`member_id` AND `a`.`member_id` = '$member_id' AND `a`.`guild_id` = `c`.`guild_id`;";
	$result = $roster->db->query($query);
	if( $roster->db->num_rows($result) > 0 )
	{
		$data = $roster->db->fetch($result);
		return new char($data);
	}
	else
	{
		return false;
	}
}


/**
 * Gets one characters data using name, server
 *
 * @param string $name
 * @param string $server
 * @return mixed False on failure
 */
function char_get_one( $name, $server )
{
	global $roster;

	$name = $roster->db->escape( $name );
	$server = $roster->db->escape( $server );
	$query = "SELECT `a`.*, `b`.*, `c`.`guild_name`, DATE_FORMAT(  DATE_ADD(`a`.`dateupdatedutc`, INTERVAL ".$roster->config['localtimeoffset']." HOUR ), '".$roster->locale->act['timeformat']."' ) AS 'update_format' ".
		"FROM `".$roster->db->table('players')."` a, `".$roster->db->table('members')."` b, `".$roster->db->table('guild')."` c " .
		"WHERE `a`.`member_id` = `b`.`member_id` AND `a`.`name` = '$name' AND `a`.`server` = '$server' AND `a`.`guild_id` = `c`.`guild_id`;";
	$result = $roster->db->query($query);
	if( $roster->db->num_rows($result) > 0 )
	{
		$data = $roster->db->fetch($result);
		return new char($data);
	}
	else
	{
		return false;
	}
}
