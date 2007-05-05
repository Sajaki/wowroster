<?php
/**
 * WoWRoster.net WoWRoster
 *
 * Character class
 *
 * LICENSE: Licensed under the Creative Commons
 *          "Attribution-NonCommercial-ShareAlike 2.5" license
 *
 * @copyright  2002-2007 WoWRoster.net
 * @license    http://creativecommons.org/licenses/by-nc-sa/2.5   Creative Commons "Attribution-NonCommercial-ShareAlike 2.5"
 * @version    SVN: $Id$
 * @link       http://www.wowroster.net
 * @since      File available since Release 1.03
*/

if( !defined('ROSTER_INSTALLED') )
{
    exit('Detected invalid access to this file!');
}

require_once (ROSTER_LIB.'item.php');
require_once (ROSTER_LIB.'bag.php');
require_once (ROSTER_LIB.'skill.php');
require_once (ROSTER_LIB.'quest.php');
require_once (ROSTER_LIB.'recipes.php');
require_once (ROSTER_LIB.'pvp3.php');

class char
{
	var $data;
	var $equip;
	var $talent_build;

	function char( $data )
	{
		$this->data = $data;
	}


	function show_pvp2( $type , $url , $sort , $start )
	{
		$pvps = pvp_get_many3( $this->data['member_id'],$type, $sort, -1);
		$returnstring = '<div align="center">';

		if( is_array($pvps) )
		{
			$returnstring .= output_pvp_summary($pvps,$type);

			if( isset( $pvps[0] ) )
			{
				switch ($type)
				{
					case 'BG':
						$returnstring .= output_bglog($this->data['member_id']);
						break;

					case 'PvP':
						$returnstring .= output_pvplog($this->data['member_id']);
						break;

					case 'Duel':
						$returnstring .= output_duellog($this->data['member_id']);
						break;

					default:
						break;
				}
			}

			$returnstring .= '<br />';
			$returnstring .= '<br />';

			$max = sizeof($pvps);
			$sort_part = $sort ? "&amp;s=$sort" : '';

			if ($start > 0)
				$prev = '<a href="'.makelink($url.'&amp;start=0'.$sort_part).'">&lt;&lt;</a>&nbsp;&nbsp;'.'<a href="'.makelink($url.'&amp;start='.($start-50).$sort_part).'">&lt;</a> ';

			if (($start+50) < $max)
			{
				$listing = '<small>['.$start.' - '.($start+50).'] of '.$max.'</small>';
				$next = ' <a href="'.makelink($url.'&amp;start='.($start+50).$sort_part).'">&gt;</a>&nbsp;&nbsp;'.'<a href="'.makelink($url.'&amp;start='.($max-50).$sort_part).'">&gt;&gt;</a>';
			}
			else
				$listing = '<small>['.$start.' - '.($max).'] of '.$max.'</small>';

			$pvps = pvp_get_many3( $this->data['member_id'],$type, $sort, $start);

			if( isset( $pvps[0] ) )
			{
				$returnstring .= border('sgray','start',$prev.'Log '.$listing.$next);
				$returnstring .= output_pvp2($pvps, $url."&amp;start=".$start,$type);
				$returnstring .= border('sgray','end');
			}

			$returnstring .= '<br />';

			if ($start > 0)
				$returnstring .= $prev;

			if (($start+50) < $max)
			{
				$returnstring .= '['.$start.' - '.($start+50).'] of '.$max;
				$returnstring .= $next;
			}
			else
				$returnstring .= '['.$start.' - '.($max).'] of '.$max;

			$returnstring .= '</div><br />';

			return $returnstring;
		}
		else
		{
			return '';
		}
	}


	function show_quests( )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$quests = quest_get_many( $this->data['member_id'],'');

		$returnstring = '';
		if( isset( $quests[0] ) )
		{
			$zone = '';
			$returnstring = border('sgray','start',$roster->locale[$lang]['questlog'] . ' (' . count($quests) . '/25)').
				'<table class="bodyline" cellspacing="0" cellpadding="0">';

			foreach ($quests as $quest)
			{
				if ($zone != $quest->data['zone'])
				{
					$zone = $quest->data['zone'];
					$returnstring .= '<tr><th colspan="10" class="membersHeaderRight">' . $zone . '</th></tr>';
				}
				$quest_level = $quest->data['quest_level'];
				$char_level = $this->data['level'];

				if( $quest_level + 9 < $char_level )
				{
					$font = 'grey';
				}
				elseif( $quest_level + 2 < $char_level )
				{
					$font = 'green';
				}
				elseif( $quest_level < $char_level+3 )
				{
					$font = 'yellow';
				}
				else
				{
					$font = 'red';
				}

				$name = $quest->data['quest_name'];
				if( $name{0} == '[' )
				{
					$name = trim(strstr($name, ' '));
				}

				$returnstring .= '        <tr>
          <td class="membersRow1">';

				$returnstring .= '<span class="' . $font . '">[' . $quest_level . '] ' . $name . '</span>';

				$quest_tags = '';

				if( $quest->data['quest_tag'] )
				{
					$quest_tags[] = $quest->data['quest_tag'];
				}

				if( $quest->data['is_complete'] == 1 )
				{
					$quest_tags[] = $roster->locale[$lang]['complete'];
				}
				elseif( $quest->data['is_complete'] == -1 )
				{
					$quest_tags[] = $roster->locale[$lang]['failed'];
				}

				if( is_array($quest_tags) )
				{
						$returnstring .= ' (' . implode(', ',$quest_tags) . ')';
				}

				$returnstring .= "</td>\n";

				$returnstring .= '<td class="membersRowRight1 quest_link">';

				foreach( $roster->locale[$lang]['questlinks'] as $link )
				{
					$returnstring .= '<a href="' . $link['url1'] . urlencode(utf8_decode($name)) . (isset($link['url2']) ? $link['url2'] . $quest_level : '') . (isset($link['url3']) ? $link['url3'] . $quest_level : '') . '" target="_blank">' . $link['name'] . "</a>\n";
				}

				$returnstring .= '</td></tr>';
			}
			$returnstring .= '      </table>' . border('sgray','end');
		}
		return $returnstring;
	}

	function show_recipes( )
	{
		global $roster, $url, $sort, $wowdb, $addon;

		$lang = $this->data['clientLocale'];
		$returnstring = '';

		$recipes = recipe_get_many( $this->data['member_id'],'', $sort );
		if( isset( $recipes[0] ) )
		{
			$skill_name = '';
			$returnstring = '';

			// Get char professions for quick links
			$query = "SELECT `skill_name` FROM `".ROSTER_RECIPESTABLE."` WHERE `member_id` = '" . $this->data['member_id'] . "' GROUP BY `skill_name` ORDER BY `skill_name`";
			$result = $wowdb->query( $query );

			// Set a ank for link to top of page
			$returnstring .= "<a name=\"top\">&nbsp;</a>\n";
			$returnstring .= '<div align="center">';
			$skill_name_divider = '';
			while( $data = $wowdb->fetch_assoc( $result ) )
			{
				$skill_name_header = $data['skill_name'];
				$returnstring .= $skill_name_divider .'<a href="#' . strtolower(str_replace(' ','',$skill_name_header)) . '">' . $skill_name_header . '</a>';
				$skill_name_divider = '&nbsp;-&nbsp;';
			}
			$returnstring .= "</div>\n<br />\n";

			$rc = 0;
			$first_run = 1;

			foreach ($recipes as $recipe)
			{
				if ($skill_name != $recipe->data['skill_name'])
				{
					$skill_name = $recipe->data['skill_name'];
					if ( !$first_run )
						$returnstring .= '</table>'.border('sgray','end')."<br />\n";
					$first_run = 0;

					// Set an link to the top behind the profession image
					$skill_image = 'Interface/Icons/'.$roster->locale[$this->data['clientLocale']]['ts_iconArray'][$skill_name];
					$skill_image = "<img style=\"float:left;\" width=\"17\" height=\"17\" src=\"".$roster->config['interface_url'].$skill_image.'.'.$roster->config['img_suffix']."\" alt=\"\" />\n";

					$header = '<div style="cursor:pointer;width:600px;" onclick="showHide(\'table_'.$rc.'\',\'img_'.$rc.'\',\''.$roster->config['img_url'].'minus.gif\',\''.$roster->config['img_url'].'plus.gif\');">
	'.$skill_image.'
	<div style="display:inline;float:right;"><img id="img_'.$rc.'" src="'.$roster->config['img_url'].'plus.gif" alt="" /></div>
<a name="'.strtolower(str_replace(' ','',$skill_name)).'"></a>'.$skill_name.'</div>';


					$returnstring .= border('sgray','start',$header)."\n<table width=\"100%\" ".($addon['config']['recipe_disp'] == '0' ? 'style="display:none;"' : '').";\" class=\"bodyline\" cellspacing=\"0\" id=\"table_$rc\">\n";

$returnstring .= '  <tr>
    <th class="membersHeader"><a href="'.makelink('char-recipes&amp;s=item').'">'.$roster->locale[$lang]['item'].'</a></th>
    <th class="membersHeader"><a href="'.makelink('char-recipes&amp;s=name').'">'.$roster->locale[$lang]['name'].'</a></th>
    <th class="membersHeader"><a href="'.makelink('char-recipes&amp;s=difficulty').'">'.$roster->locale[$lang]['difficulty'].'</a></th>
    <th class="membersHeader"><a href="'.makelink('char-recipes&amp;s=type').'">'.$roster->locale[$lang]['type'].'</a></th>
    <th class="membersHeader"><a href="'.makelink('char-recipes&amp;s=level').'">'.$roster->locale[$lang]['level'].'</a></th>
    <th class="membersHeaderRight"><a href="'.makelink('char-recipes&amp;s=reagents').'">'.$roster->locale[$lang]['reagents'].'</a></th>
  </tr>
';
				}

				if( $recipe->data['difficulty'] == '4' )
					$difficultycolor = 'FF9900';
				elseif( $recipe->data['difficulty'] == '3' )
					$difficultycolor = 'FFFF66';
				elseif( $recipe->data['difficulty'] == '2' )
					$difficultycolor = '339900';
				elseif( $recipe->data['difficulty'] == '1' )
					$difficultycolor = 'CCCCCC';
				else
					$difficultycolor = 'FFFF80';

				// Dont' set an CSS class for the image cell - center it
				$stripe = (($rc%2)+1);
				$returnstring .= '  <tr>
    <td class="membersRow'.$stripe.' equip">';

				$returnstring .= $recipe->out();
				$returnstring .= '</td>
    <td class="membersRow'.$stripe.'"><span style="color:#'.substr( $recipe->data['item_color'], 2, 6 ).'">&nbsp;'.$recipe->data['recipe_name'].'</span></td>
    <td class="membersRow'.$stripe.'"><span style="color:#'.$difficultycolor.'">&nbsp;'.$roster->locale[$lang]['recipe_'.$recipe->data['difficulty']].'</span></td>
    <td class="membersRow'.$stripe.'">&nbsp;'.$recipe->data['recipe_type'].'&nbsp;</td>
    <td class="membersRow'.$stripe.'">&nbsp;'.$recipe->data['level'].'&nbsp;</td>
    <td class="membersRowRight'.$stripe.'">&nbsp;'.str_replace('<br>','&nbsp;<br />&nbsp;',$recipe->data['reagents']).'</td>
  </tr>
';
			$rc++;
			}
			$returnstring .= "</table>".border('sgray','end');
		}
		return $returnstring;
	}

	function show_mailbox( )
	{
		global $roster, $wowdb, $tooltips, $addon;

		$lang = $this->data['clientLocale'];

		$sqlquery = "SELECT * FROM `".ROSTER_MAILBOXTABLE."` ".
			"WHERE `member_id` = '".$this->data['member_id']."' ".
			"ORDER BY `mailbox_days`;";

		$result = $wowdb->query($sqlquery);

		if( !$result )
		{
			return '<span class="headline_1">'.sprintf($roster->locale[$lang]['no_mail'],$this->data['name']).'</span>';
		}

		$content = '';

		if( $wowdb->num_rows($result) > 0 )
		{
			//begin generation of mailbox's output
			$content .= border('sgray','start',$roster->locale[$lang]['mailbox']).
				'<table cellpadding="0" cellspacing="0" class="bodyline">'."\n";
			$content .= "<tr>\n";
			$content .= '<th class="membersHeader">'.$roster->locale[$lang]['mail_item'].'</th>'."\n";
			$content .= '<th class="membersHeader">'.$roster->locale[$lang]['mail_sender'].'</th>'."\n";
			$content .= '<th class="membersHeader">'.$roster->locale[$lang]['mail_subject'].'</th>'."\n";
			$content .= '<th class="membersHeaderRight">'.$roster->locale[$lang]['mail_expires'].'</th>'."\n";
			$content .= "</tr>\n";

			$cur_row = 1;
			while( $row = $wowdb->fetch_assoc($result) )
			{
				$maildateutc = strtotime($this->data['maildateutc']);

				$content .= "<tr>\n";
				$content .= '<td class="membersRow'.$cur_row.'">'."\n";

				// Get money in mail
				$money_included = '';
				if( $row['mailbox_coin'] > 0 && $addon['config']['show_money'] )
				{
					$db_money = $row['mailbox_coin'];

					$mail_money['c'] = substr($db_money,-2,2);
					$db_money = substr($db_money,0,-2);
					$money_included = $mail_money['c'].'<img src="'.$roster->config['img_url'].'coin_copper.gif" alt="c" />';

					if( !empty($db_money) )
					{
						$mail_money['s'] = substr($db_money,-2,2);
						$db_money = substr($db_money,0,-2);
						$money_included = $mail_money['s'].'<img src="'.$roster->config['img_url'].'coin_silver.gif" alt="s" /> '.$money_included;
					}
					if( !empty($db_money) )
					{
						$mail_money['g'] = $db_money;
						$money_included = $mail_money['g'].'<img src="'.$roster->config['img_url'].'coin_gold.gif" alt="g" /> '.$money_included;
					}
				}

				// Fix icon texture
				if( !empty($row['item_icon']) )
				{
					$item_icon = $roster->config['interface_url'].'Interface/Icons/'.$row['item_icon'].'.'.$roster->config['img_suffix'];
				}
				elseif( !empty($money_included) )
				{
					$item_icon = $roster->config['interface_url'].'Interface/Icons/'.$row['mailbox_coin_icon'].'.'.$roster->config['img_suffix'];
				}
				else
				{
					$item_icon = $roster->config['interface_url'].'Interface/Icons/INV_Misc_Note_02.'.$roster->config['img_suffix'];
				}


				// Start the tooltips
				$tooltip_h = $row['mailbox_subject'];

				// first line is sender
				$tooltip = $roster->locale[$this->data['clientLocale']]['mail_sender'].
					': '.$row['mailbox_sender'].'<br />';

				$expires_line = date($roster->locale[$this->data['clientLocale']]['phptimeformat'],((($row['mailbox_days']*24 + $roster->config['localtimeoffset'])*3600)+$maildateutc)).' '.$roster->config['timezone'];
				if( (($row['mailbox_days']*24*3600)+$maildateutc) - time() < (3*24*3600) )
					$color = 'ff0000;';
				else
					$color = 'ffffff;';

				$tooltip .= $roster->locale[$this->data['clientLocale']]['mail_expires'].": <span style=\"color:#$color\">$expires_line</span><br />";

				// Join money with main tooltip
				if( !empty($money_included) )
				{
					$tooltip .= $roster->locale[$this->data['clientLocale']]['mail_money'].': '.$money_included;
				}


				// Get item tooltip
				$item_tooltip = colorTooltip($row['item_tooltip'],$row['item_color'],$this->data['clientLocale']);


				// If the tip has no info, at least get the item name in there
				if( $item_tooltip != '<br />' )
					$item_tooltip = '<hr />'.$item_tooltip;


				// Join item tooltip with main tooltip
				$tooltip .= $item_tooltip;

				if ($tooltip == '')
				{
					if ($row['item_name'] != '')
					{
						$tooltip = $row['item_name'];
					}
					else
					{
						$tooltip = $roster->locale[$lang]['no_info'];
					}
				}

				$tooltip = makeOverlib($tooltip,$tooltip_h,'',2,$this->data['clientLocale']);

				// Item links
				$num_of_tips = (count($tooltips)+1);
				$linktip = '';
				foreach( $roster->locale[$this->data['clientLocale']]['itemlinks'] as $ikey => $ilink )
				{
					$linktip .= '<a href="'.$ilink.urlencode(utf8_decode($row['item_name'])).'" target="_blank">'.$ikey.'</a><br />';
				}
				setTooltip($num_of_tips,$linktip);
				setTooltip('itemlink',$roster->locale[$this->data['clientLocale']]['itemlink']);

				$linktip = ' onclick="return overlib(overlib_'.$num_of_tips.',CAPTION,overlib_itemlink,STICKY,NOCLOSE,WRAP,OFFSETX,5,OFFSETY,5);"';


				$content .= '<div class="item" style="cursor:pointer;" '.$tooltip.$linktip.'>';

				$content .= '<img src="'.$item_icon.'"'." alt=\"\" />\n";

				if( ($row['item_quantity'] > 1) )
					$content .= '<span class="quant">'.$row['item_quantity'].'</span>';
				$content .= "</div>\n</td>\n";

				$content .= '<td class="membersRow'.$cur_row.'">'.$row['mailbox_sender'].'</td>'."\n";
				$content .= '<td class="membersRow'.$cur_row.'">'.$row['mailbox_subject'].'</td>'."\n";
				$content .= '<td class="membersRowRight'.$cur_row.'">'.$expires_line.'</td>'."\n";

				$content .= "</tr>\n";

				$cur_row = (($cur_row%2)+1);
			}

			$content .= "</table>\n".border('sgray','end');

			return $content;
		}
		else
		{
			return '<span class="headline_1">'.sprintf($roster->locale[$lang]['no_mail'],$this->data['name']).'</span>';
		}
	}

	function show_spellbook( )
	{
		global $roster, $wowdb;

		$lang = $this->data['clientLocale'];

		$query = "SELECT `spelltree`.*, `talenttree`.`order`
			FROM `".ROSTER_SPELLTREETABLE."` AS spelltree
			LEFT JOIN `".ROSTER_TALENTTREETABLE."` AS talenttree
				ON `spelltree`.`member_id` = `talenttree`.`member_id`
				AND `spelltree`.`spell_type` = `talenttree`.`tree`
			WHERE `spelltree`.`member_id` = ".$this->data['member_id']."
			ORDER BY `talenttree`.`order` ASC";

		$result = $wowdb->query($query);

		if( !$result )
		{
			return sprintf($roster->locale[$lang]['no_spellbook'],$this->data['name']);
		}

		$num_trees = $wowdb->num_rows($result);

		if( $num_trees == 0 )
		{
			return sprintf($roster->locale[$lang]['no_spellbook'],$this->data['name']);
		}

		for( $t=0; $t < $num_trees; $t++)
		{
			$treedata = $wowdb->fetch_assoc($result);

			$spelltree[$t]['name'] = $treedata['spell_type'];
			$spelltree[$t]['icon'] = 'Interface/Icons/'.$treedata['spell_texture'];
			$spelltree[$t]['id'] = $t;

			$name_id[$treedata['spell_type']] = $t;
		}

		$wowdb->free_result($result);

		// Get the spell data
		$query = "SELECT * FROM `".ROSTER_SPELLTABLE."` WHERE `member_id` = '".$this->data['member_id']."' ORDER BY `spell_name`";

		$result = $wowdb->query($query);

		while ($row = $wowdb->fetch_assoc($result))
		{
			$spelltree[$name_id[$row['spell_type']]]['rawspells'][] = $row;
		}

		foreach ($spelltree as $t => $tree)
		{
			$i=0;
			$p=0;
			foreach ($spelltree[$t]['rawspells'] as $r => $spell)
			{
				if( $i >= 14 )
				{
					$i=0;
					$p++;
				}
				$spelltree[$t]['spells'][$p][$i]['name'] = $spell['spell_name'];
				$spelltree[$t]['spells'][$p][$i]['type'] = $spell['spell_type'];
				$spelltree[$t]['spells'][$p][$i]['icon'] = 'Interface/Icons/'.$spell['spell_texture'];
				$spelltree[$t]['spells'][$p][$i]['rank'] = $spell['spell_rank'];

				// Parse the tooltip
				$spelltree[$t]['spells'][$p][$i]['tooltip'] = makeOverlib($spell['spell_tooltip'],'','',0,$this->data['clientLocale'],',RIGHT');

				$i++;
			}
		}
		$wowdb->free_result($result);


		// Get the PET spell data
		$query = "SELECT `spell`.*, `pet`.`name`
			FROM `".ROSTER_PETSPELLTABLE."` as spell
			LEFT JOIN `".ROSTER_PETSTABLE."` AS pet
			ON `spell`.`pet_id` = `pet`.`pet_id`
			WHERE `spell`.`member_id` = '".$this->data['member_id']."' ORDER BY `spell`.`spell_name`;";

		$result = $wowdb->query($query);

		$petspells = array();
		while( $row = $wowdb->fetch_assoc($result) )
		{
			$petid = $row['pet_id'];
			$petspells[$petid]['name'] = $row['name'];
			$petspells[$petid][$i]['name'] = $row['spell_name'];
			$petspells[$petid][$i]['icon'] = 'Interface/Icons/'.$row['spell_texture'];
			$petspells[$petid][$i]['rank'] = $row['spell_rank'];

			// Parse the tooltip
			$petspells[$petid][$i]['tooltip'] = makeOverlib($row['spell_tooltip'],'','',0,$this->data['clientLocale'],',RIGHT');
			$i++;
		}
		$wowdb->free_result($result);



		$return_string = '
<div class="char_panel spell_panel">
	<img class="panel_icon" src="'.$roster->config['img_url'].'char/menubar/icon_spellbook.gif" alt=""/>
	<div class="panel_title">'.$roster->locale[$lang]['spellbook'].'</div>
	<div class="background">&nbsp;</div>

	<div id="main_spells">
		<div class="skill_types">
			<ul>
';

		foreach( $spelltree as $tree )
		{
			$treetip = makeOverlib($tree['name'],'','',2,'',',WRAP,RIGHT');
			$return_string .= '				<li onclick="return showSpell(\''.$tree['id'].'\');"><img class="icon" src="'.$roster->config['interface_url'].$tree['icon'].'.'.$roster->config['img_suffix'].'" '.$treetip.' alt="" /></li>'."\n";
		}
		$return_string .= "			</ul>\n		</div>\n";


		foreach( $spelltree as $tree )
		{
			if( $tree['id'] == 0 )
			{
				$return_string .= '		<div id="spelltree_'.$tree['id'].'">'."\n";
			}
			else
			{
				$return_string .= '		<div id="spelltree_'.$tree['id'].'" style="display:none;">'."\n";
			}

			$num_pages = count($tree['spells']);
			$first_page = true;
			$page = 0;
			foreach( $tree['spells'] as $spellpage )
			{
				if( $first_page )
				{
					if( ($num_pages-1) == $page )
					{
						$return_string .= '			<div id="page_'.$page.'_'.$tree['id'].'">'."\n";
						$return_string .= '				<div class="page_back_off"><img src="'.$roster->config['img_url'].'char/spellbook/pageback_off.gif" class="navicon" alt="" /> '.$roster->locale[$lang]['prev'].'</div>'."\n";
						$return_string .= '				<div class="page_forward_off">'.$roster->locale[$lang]['next'].' <img src="'.$roster->config['img_url'].'char/spellbook/pageforward_off.gif" class="navicon" alt="" /></div>'."\n";
						$first_page = false;
					}
					else
					{
						$return_string .= '			<div id="page_'.$page.'_'.$tree['id'].'">'."\n";
						$return_string .= '				<div class="page_back_off"><img src="'.$roster->config['img_url'].'char/spellbook/pageback_off.gif" class="navicon" alt="" /> '.$roster->locale[$lang]['prev'].'</div>'."\n";
						$return_string .= '				<div class="page_forward" onclick="swapShow(\'page_'.($page+1).'_'.$tree['id'].'\',\'page_'.$page.'_'.$tree['id'].'\');">'.$roster->locale[$lang]['next'].' <img src="'.$roster->config['img_url'].'char/spellbook/pageforward.gif" class="navicon" alt="" /></div>'."\n";
						$first_page = false;
					}
				}
				elseif( ($num_pages-1) == $page )
				{
					$return_string .= '			<div id="page_'.$page.'_'.$tree['id'].'" style="display:none;">'."\n";
					$return_string .= '				<div class="page_back" onclick="swapShow(\'page_'.($page-1).'_'.$tree['id'].'\',\'page_'.$page.'_'.$tree['id'].'\');"><img src="'.$roster->config['img_url'].'char/spellbook/pageback.gif" class="navicon" alt="" /> '.$roster->locale[$lang]['prev'].'</div>'."\n";
					$return_string .= '				<div class="page_forward_off">'.$roster->locale[$lang]['next'].' <img src="'.$roster->config['img_url'].'char/spellbook/pageforward_off.gif" class="navicon" alt="" /></div>'."\n";
				}
				else
				{
					$return_string .= '			<div id="page_'.$page.'_'.$tree['id'].'" style="display:none;">'."\n";
					$return_string .= '				<div class="page_back" onclick="swapShow(\'page_'.($page-1).'_'.$tree['id'].'\',\'page_'.$page.'_'.$tree['id'].'\');"><img src="'.$roster->config['img_url'].'char/spellbook/pageback.gif" class="navicon" alt="" /> '.$roster->locale[$lang]['prev'].'</div>'."\n";
					$return_string .= '				<div class="page_forward" onclick="swapShow(\'page_'.($page+1).'_'.$tree['id'].'\',\'page_'.$page.'_'.$tree['id'].'\');">'.$roster->locale[$lang]['next'].' <img src="'.$roster->config['img_url'].'char/spellbook/pageforward.gif" class="navicon" alt="" /></div>'."\n";
				}
				$return_string .= '				<div class="pagenumber">'.$roster->locale[$lang]['page'].' '.($page+1).'</div>'."\n";


				$icon_num = 0;
				foreach( $spellpage as $spellicons )
				{
					if( $icon_num == 0 )
					{
						$return_string .= '				<div class="container_1">'."\n";
					}
					elseif( $icon_num == 7 )
					{
						$return_string .= "				</div>\n				<div class=\"container_2\">\n";
					}
					$return_string .= '
				<div class="info_container">
					<img src="'.$roster->config['interface_url'].$spellicons['icon'].'.'.$roster->config['img_suffix'].'" class="icon" '.$spellicons['tooltip'].' alt="" />
					<span class="text"><span class="yellowB">'.$spellicons['name'].'</span>';
					if( $spellicons['rank'] != '' )
					{
						$return_string .= '<br /><span class="brownB">'.$spellicons['rank'].'</span>';
					}
					$return_string .= "</span>\n					</div>\n";
					$icon_num++;
				}
				$return_string .= "				</div>\n			</div>\n";

				$page++;
			}
			$return_string .= "		</div>\n";
		}
		$return_string .= "	</div>\n";

		// PET SPELLS
		$pet_tabs = '';
		foreach( $petspells as $petid => $pet )
		{
			$pet_tabs .= '			<li onclick="return displaypage(\'petspell_'.$petid.'\',this);"><div class="text">'.$pet['name']."</div></li>\n";

			$return_string .= '		<div id="petspell_'.$petid.'" style="display:none;">'."\n";

			$icon_num = 0;
			foreach( $pet as $arrayname => $spellicons )
			{
				if( $arrayname != 'name' )
				{
					if( $icon_num == 0 )
					{
						$return_string .= '			<div class="container_1">'."\n";
					}
					elseif( $icon_num == 7 )
					{
						$return_string .= "			</div>\n			<div class=\"container_2\">\n";
					}
					$return_string .= '
			<div class="info_container">
				<img src="'.$roster->config['interface_url'].$spellicons['icon'].'.'.$roster->config['img_suffix'].'" class="icon" '.$spellicons['tooltip'].' alt="" />
				<span class="text"><span class="yellowB">'.$spellicons['name'].'</span>';
					if( $spellicons['rank'] != '' )
					{
						$return_string .= '<br /><span class="brownB">'.$spellicons['rank'].'</span>';
					}
					$return_string .= "</span>\n				</div>\n";
					$icon_num++;
				}
			}
			$return_string .= "			</div>\n		</div>\n";
		}

		//$return_string .= "	</div>\n";


		$return_string .= '
<!-- Begin Navagation Tabs -->
	<div id="spell_set" class="tab_navagation">
		<ul>
			<li onclick="return displaypage(\'main_spells\',this);"><div class="text">'.$this->data['name'].'</div></li>
'.$pet_tabs.'
		</ul>
	</div>
</div>

<script type="text/javascript">
	//Set tab to intially be selected when page loads:
	//[which tab (1=first tab), ID of tab content to display]:
	window.onload=tab_nav_onload(\'spell_set\',[1, \'main_spells\'])
</script>'."\n";

		return $return_string;
	}

	function get( $field )
	{
		return $this->data[$field];
	}


	function printPet( )
	{
		global $roster, $wowdb;

		$lang = $this->data['clientLocale'];

		$query = "SELECT * FROM `".ROSTER_PETSTABLE."` WHERE `member_id` = '".$this->data['member_id']."' ORDER BY `level` DESC";
		$result = $wowdb->query( $query );

		$output = $icons = '';

		$petNum = 0;
		if( $wowdb->num_rows($result) > 0 )
		{
			while ($row = $wowdb->fetch_assoc($result))
			{
				$xpbarshow = true;

				if( $row['level'] == ROSTER_MAXCHARLEVEL )
				{
					$expbar_width = '216';
					$expbar_text = $roster->locale[$lang]['max_exp'];
				}
				else
				{
					list($xp, $xplevel) = explode(':',$row['xp']);
					if ($xplevel != '0' && $xplevel != '')
					{
						$expbar_width = ( $xplevel > 0 ? floor($xp / $xplevel * 216) : 0);

						$exp_percent = ( $xplevel > 0 ? floor($xp / $xplevel * 100) : 0);

						$expbar_text = $xp.'/'.$xplevel.' ('.$exp_percent.'%)';
					}
					else
					{
						$xpbarshow = false;
					}
				}

				$unusedtp = $row['totaltp'] - $row['usedtp'];

				if( $row['level'] == ROSTER_MAXCHARLEVEL )
					$showxpBar = false;

				$left = 35+(($petNum)*50);
				$top = 285;

				// Start Warlock Pet Icon Fix
				if( $row['type'] == $roster->locale[$lang]['Imp'] )
				{
					$row['icon'] = 'spell_shadow_summonimp';
				}
				elseif( $row['type'] == $roster->locale[$lang]['Voidwalker'] )
				{
					$row['icon'] = 'spell_shadow_summonvoidwalker';
				}
				elseif( $row['type'] == $roster->locale[$lang]['Succubus'] )
				{
					$row['icon'] = 'spell_shadow_summonsuccubus';
				}
				elseif( $row['type'] == $roster->locale[$lang]['Felhunter'] )
				{
					$row['icon'] = 'spell_shadow_summonfelhunter';
				}
				elseif( $row['type'] == $roster->locale[$lang]['Felguard'] )
				{
					$row['icon'] = 'spell_shadow_summonfelguard';
				}
				elseif( $row['type'] == $roster->locale[$lang]['Infernal'] )
				{
					$row['icon'] = 'spell_shadow_summoninfernal';
				}
				// End Warlock Pet Icon Fix

				if( $row['icon'] == '' || !isset($row['icon']) )
				{
					$row['icon'] = 'inv_misc_questionmark';
				}

				$icons .= '			<li onclick="return showPet(\''. $petNum .'\');" '.makeOverlib($row['name'],$row['type'],'',2,'',',WRAP').'>
				<div class="text"><img src="'.$roster->config['interface_url'].'Interface/Icons/'.$row['icon'].'.'.$roster->config['img_suffix'].'" alt="" /></div></li>
';

				$output .= '
		<div id="pet_'.$petNum.'"'. ($petNum == 0 ? '' : ' style="display:none;"') .'>
			<div class="name">'. stripslashes($row['name']) .'</div>
			<div class="info">'. $roster->locale[$lang]['level'] .' '. $row['level'] .' '. stripslashes($row['type']) .'</div>

			<div class="loyalty">'. $row['loyalty'] .'</div>

			<img class="icon" src="'. $roster->config['interface_url'] .'Interface/Icons/'. $row['icon'] .'.'. $roster->config['img_suffix'] .'" alt="" />

			<div class="health"><span class="yellowB">'. $roster->locale[$lang]['health'] .':</span> '. (isset($row['health']) ? $row['health'] : '0') .'</div>
			<div class="mana"><span class="yellowB">'. $row['power'] .':</span> '. (isset($row['mana']) ? $row['mana'] : '0') .'</div>

			<div class="resist">
				'. $this->printPetResist('arcane',$row) .'
				'. $this->printPetResist('fire',$row) .'
				'. $this->printPetResist('nature',$row) .'
				'. $this->printPetResist('frost',$row) .'
				'. $this->printPetResist('shadow',$row) .'
			</div>
';
				if( $xpbarshow )
				{
					$output .= '
			<img src="'. $roster->config['img_url'] .'char/expbar_empty.gif" class="xpbar_empty" alt="" />
			<div class="xpbar" style="clip:rect(0px '. $expbar_width .'px 12px 0px);"><img src="'. $roster->config['img_url'].'char/expbar_full.gif' .'" alt="" /></div>
			<div class="xpbar_text">'. $expbar_text .'</div>';
				}

				$output .= '
			<div class="padding">
				<div class="stats">
					'. $this->printPetStat('stat_str',$row).'
					'. $this->printPetStat('stat_agl',$row).'
					'. $this->printPetStat('stat_sta',$row).'
					'. $this->printPetStat('stat_int',$row).'
					'. $this->printPetStat('stat_spr',$row).'
					'. $this->printPetStat('stat_armor',$row).'
				</div>
				<div class="stats">
					'. $this->printPetWSkill($row).'
					'. $this->printPetWDamage($row).'
					'. $this->printPetStat('melee_power',$row).'
					'. $this->printPetStat('melee_hit',$row).'
					'. $this->printPetStat('melee_crit',$row).'
					'. $this->printPetResilience($row).'
				</div>
			</div>
';
				if( $row['totaltp'] != 0 )
				{
					$output .= '
			<div class="trainingpts">'.$roster->locale[$lang]['unusedtrainingpoints'].': '. $unusedtp .' / '. $row['totaltp'] .'</div>';
				}
				$output .= '
		</div>
';

				$petNum++;
			}
			$output .= '
<!-- Begin Navagation Tabs -->
	<div class="pet_tabs">
		<ul>
'. $icons .'
		</ul>
	</div>';
		}

		return $output;
	}

	function printPetStat( $statname , $data )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$base = $data[$statname];
		$current = $data[$statname.'_c'];
		$buff = $data[$statname.'_b'];
		$debuff = -$data[$statname.'_d'];

		switch( $statname )
		{
			case 'stat_str':
				$name = $roster->locale[$lang]['strength'];
				$tooltip = $roster->locale[$lang]['strength_tooltip'];
				break;
			case 'stat_int':
				$name = $roster->locale[$lang]['intellect'];
				$tooltip = $roster->locale[$lang]['intellect_tooltip'];
				break;
			case 'stat_sta':
				$name = $roster->locale[$lang]['stamina'];
				$tooltip = $roster->locale[$lang]['stamina_tooltip'];
				break;
			case 'stat_spr':
				$name = $roster->locale[$lang]['spirit'];
				$tooltip = $roster->locale[$lang]['spirit_tooltip'];
				break;
			case 'stat_agl':
				$name = $roster->locale[$lang]['agility'];
				$tooltip = $roster->locale[$lang]['agility_tooltip'];
				break;
			case 'stat_armor':
				$name = $roster->locale[$lang]['armor'];
				$tooltip = $roster->locale[$lang]['armor_tooltip'];
				if( !empty($data['mitigation']) )
					$tooltip .= '<br /><span class="red">'.$roster->locale[$lang]['tooltip_damage_reduction'].': '.$data['mitigation'].'%</span>';
				break;
			case 'melee_power':
				$lname = $roster->locale[$lang]['melee_att_power'];
				$name = $roster->locale[$lang]['power'];
				$tooltip = sprintf($roster->locale[$lang]['melee_att_power_tooltip'], $data['melee_power_dps']);
				break;
			case 'melee_hit':
				$name = $roster->locale[$lang]['weapon_hit_rating'];
				$tooltip = $roster->locale[$lang]['weapon_hit_rating_tooltip'];
				break;
			case 'melee_crit':
				$name = $roster->locale[$lang]['weapon_crit_rating'];
				$tooltip = sprintf($roster->locale[$lang]['weapon_crit_rating_tooltip'], $data['melee_crit_chance']);
				break;
		}

		if( isset($lname) )
			$tooltipheader = $lname.' '.$this->printRatingLong($statname,$data);
		else
			$tooltipheader = $name.' '.$this->printRatingLong($statname,$data);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, $this->printRatingShort($statname,$data), $line);
	}

	function printPetWSkill ( $data )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$value = '<strong class="white">'.$data['melee_mhand_skill'].'</strong>';
		$name = $roster->locale[$lang]['weapon_skill'];
		$tooltipheader = $roster->locale[$lang]['mainhand'];
		$tooltip = sprintf($roster->locale[$lang]['weapon_skill_tooltip'], $data['melee_mhand_skill'], $data['melee_mhand_rating']);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, $value, $line);
	}

	function printPetWDamage ( $data )
	{
		global $roster;

		$lang = $this->data['clientLocale'];


		$value = '<strong class="white">'.$data['melee_mhand_mindam'].'</strong>'.'-'.'<strong class="white">'.$data['melee_mhand_maxdam'].'</strong>';
		$name = $roster->locale[$lang]['damage'];
		$tooltipheader = $roster->locale[$lang]['mainhand'];
		$tooltip = sprintf($roster->locale[$lang]['damage_tooltip'], $data['melee_mhand_speed'], $data['melee_mhand_mindam'], $data['melee_mhand_maxdam'], $data['melee_mhand_dps']);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, $value, $line);
	}

	function printPetResist( $resname , $data )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		switch($resname)
		{
		case 'fire':
			$name = $roster->locale[$lang]['res_fire'];
			$tooltip = $roster->locale[$lang]['res_fire_tooltip'];
			$color = 'red';
			break;
		case 'nature':
			$name = $roster->locale[$lang]['res_nature'];
			$tooltip = $roster->locale[$lang]['res_nature_tooltip'];
			$color = 'green';
			break;
		case 'arcane':
			$name = $roster->locale[$lang]['res_arcane'];
			$tooltip = $roster->locale[$lang]['res_arcane_tooltip'];
			$color = 'yellow';
			break;
		case 'frost':
			$name = $roster->locale[$lang]['res_frost'];
			$tooltip = $roster->locale[$lang]['res_frost_tooltip'];
			$color = 'blue';
			break;
		case 'shadow':
			$name = $roster->locale[$lang]['res_shadow'];
			$tooltip = $roster->locale[$lang]['res_shadow_tooltip'];
			$color = 'purple';
			break;
		}

		$line = '<span style="color:'.$color.';font-size:11px;font-weight:bold;">'.$name.'</span> '.$this->printRatingLong('res_'.$resname,$data).'<br />';
		$line .= '<span style="color:#DFB801;text-align:left;">'.$tooltip.'</span>';

		$output = '<div style="background:url('.$roster->config['img_url'].'char/resist/'.$resname.'.gif);" class="resist_'.$resname.'" '.makeOverlib($line,'','',2,'','').'>'. $data['res_'.$resname.'_c'] ."</div>\n";
		$output = '<div style="background-image:url('.$roster->config['img_url'].'char/resist/'.$resname.'.gif);" class="'.$resname.'" '.makeOverlib($line,'','',2,'','').'><b>'. $data['res_'.$resname.'_c'] .'</b><span>'. $data['res_'.$resname.'_c'] ."</span></div>\n";

		return $output;
	}

	function printPetResilience( $data )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$name = $roster->locale[$lang]['resilience'];
		$value = min($data['stat_res_melee'],$data['stat_res_ranged'],$data['stat_res_spell']);

		$tooltipheader = $name;
		$tooltip  = '<div><span style="float:right;">'.$data['stat_res_melee'].'</span>'.$roster->locale[$lang]['melee'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$data['stat_res_ranged'].'</span>'.$roster->locale[$lang]['ranged'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$data['stat_res_spell'].'</span>'.$roster->locale[$lang]['spell'].'</div>';


		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, '<strong class="white">'.$value.'%</strong>', $line);
	}


	function printStatLine( $label , $value , $tooltip )
	{
		$output  = '  <div class="statline" '.makeOverlib($tooltip,'','',2,'','').'>'."\n";
		$output .= '    <span class="value">'.$value.'</span>'."\n";
		$output .= '    <span class="label">'.$label.':</span>'."\n";
		$output .= '  </div>'."\n";

		return $output;
	}

	function printRatingShort( $statname , $data_or=false )
	{
		if( $data_or == false )
			$data = $this->data;
		else
			$data = $data_or;

		$base = $data[$statname];
		$current = $data[$statname.'_c'];
		$buff = $data[$statname.'_b'];
		$debuff = -$data[$statname.'_d'];

		if( $buff>0 && $debuff>0 )
		{
			$color = "purple";
		}
		elseif( $buff>0 )
		{
			$color = "green";
		}
		elseif( $debuff>0 )
		{
			$color = "red";
		}
		else
		{
			$color = "white";
		}

		return '<strong class="'.$color.'">'.$current.'</strong>';
	}

	function printRatingLong( $statname , $data_or=false )
	{
		if( $data_or == false )
			$data = $this->data;
		else
			$data = $data_or;

		$base = $data[$statname];
		$current = $data[$statname.'_c'];
		$buff = $data[$statname.'_b'];
		$debuff = -$data[$statname.'_d'];

		$tooltipheader = $current;

		if( $base != $current)
		{
			$tooltipheader .= " ($base";
			if( $buff > 0 )
			{
				$tooltipheader .= " <span class=\"green\">+ $buff</span>";
			}
			if( $debuff > 0 )
			{
				$tooltipheader .= " <span class=\"red\">- $debuff</span>";
			}
			$tooltipheader .= ")";
		}

		return $tooltipheader;
	}

	function printBox( $cat , $side , $visible )
	{
		print '<div class="stats" id="'.$cat.$side.'" style="display:'.($visible?'block':'none').'">'."\n";
		switch($cat)
		{
			case 'stats':
				print $this->printStat('stat_str');
				print $this->printStat('stat_agl');
				print $this->printStat('stat_sta');
				print $this->printStat('stat_int');
				print $this->printStat('stat_spr');
				print $this->printStat('stat_armor');
				break;
			case 'melee':
				print $this->printWSkill('melee');
				print $this->printWDamage('melee');
				print $this->printWSpeed('melee');
				print $this->printStat('melee_power');
				print $this->printStat('melee_hit');
				print $this->printStat('melee_crit');
				break;
			case 'ranged':
				print $this->printWSkill('ranged');
				print $this->printWDamage('ranged');
				print $this->printWSpeed('ranged');
				print $this->printStat('ranged_power');
				print $this->printStat('ranged_hit');
				print $this->printStat('ranged_crit');
				break;
			case 'spell':
				print $this->printSpellDamage();
				print $this->printValue('spell_healing');
				print $this->printStat('spell_hit');
				print $this->printSpellCrit();
				print $this->printValue('spell_penetration');
				print $this->printValue('mana_regen_value');
				break;
			case 'defense':
				print $this->printStat('stat_armor');
				print $this->printDefense();
				print $this->printDef('dodge');
				print $this->printDef('parry');
				print $this->printDef('block');
				print $this->printResilience();
				break;
		}
		print '</div>'."\n";
	}

	function printStat( $statname )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$base = $this->data[$statname];
		$current = $this->data[$statname.'_c'];
		$buff = $this->data[$statname.'_b'];
		$debuff = -$this->data[$statname.'_d'];

		switch( $statname )
		{
			case 'stat_str':
				$name = $roster->locale[$lang]['strength'];
				$tooltip = $roster->locale[$lang]['strength_tooltip'];
				break;
			case 'stat_int':
				$name = $roster->locale[$lang]['intellect'];
				$tooltip = $roster->locale[$lang]['intellect_tooltip'];
				break;
			case 'stat_sta':
				$name = $roster->locale[$lang]['stamina'];
				$tooltip = $roster->locale[$lang]['stamina_tooltip'];
				break;
			case 'stat_spr':
				$name = $roster->locale[$lang]['spirit'];
				$tooltip = $roster->locale[$lang]['spirit_tooltip'];
				break;
			case 'stat_agl':
				$name = $roster->locale[$lang]['agility'];
				$tooltip = $roster->locale[$lang]['agility_tooltip'];
				break;
			case 'stat_armor':
				$name = $roster->locale[$lang]['armor'];
				$tooltip = $roster->locale[$lang]['armor_tooltip'];
				if( !empty($this->data['mitigation']) )
					$tooltip .= '<br /><span class="red">'.$roster->locale[$lang]['tooltip_damage_reduction'].': '.$this->data['mitigation'].'%</span>';
				break;
			case 'melee_power':
				$lname = $roster->locale[$lang]['melee_att_power'];
				$name = $roster->locale[$lang]['power'];
				$tooltip = sprintf($roster->locale[$lang]['melee_att_power_tooltip'], $this->data['melee_power_dps']);
				break;
			case 'melee_hit':
				$name = $roster->locale[$lang]['weapon_hit_rating'];
				$tooltip = $roster->locale[$lang]['weapon_hit_rating_tooltip'];
				break;
			case 'melee_crit':
				$name = $roster->locale[$lang]['weapon_crit_rating'];
				$tooltip = sprintf($roster->locale[$lang]['weapon_crit_rating_tooltip'], $this->data['melee_crit_chance']);
				break;
			case 'ranged_power':
				$lname = $roster->locale[$lang]['ranged_att_power'];
				$name = $roster->locale[$lang]['power'];
				$tooltip = sprintf($roster->locale[$lang]['ranged_att_power_tooltip'], $this->data['ranged_power_dps']);
				break;
			case 'ranged_hit':
				$name = $roster->locale[$lang]['weapon_hit_rating'];
				$tooltip = $roster->locale[$lang]['weapon_hit_rating_tooltip'];
				break;
			case 'ranged_crit':
				$name = $roster->locale[$lang]['weapon_crit_rating'];
				$tooltip = sprintf($roster->locale[$lang]['weapon_crit_rating_tooltip'], $this->data['ranged_crit_chance']);
				break;
			case 'spell_hit':
				$name = $roster->locale[$lang]['spell_hit_rating'];
				$tooltip = $roster->locale[$lang]['spell_hit_rating_tooltip'];
				break;
		}

		if( isset($lname) )
			$tooltipheader = $lname.' '.$this->printRatingLong($statname);
		else
			$tooltipheader = $name.' '.$this->printRatingLong($statname);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, $this->printRatingShort($statname), $line);
	}

	function printValue( $statname )
	{
		global $roster;

		$lang = $this->data['clientLocale'];
		$value = $this->data[$statname];
		switch( $statname )
		{
			case 'spell_penetration':
				$name = $roster->locale[$lang]['spell_penetration'];
				$tooltip = $roster->locale[$lang]['spell_penetration_tooltip'];
				break;
			case 'mana_regen_value':
				$name = $roster->locale[$lang]['mana_regen'];
				$tooltip = sprintf($roster->locale[$lang]['mana_regen_tooltip'],$this->data['mana_regen_value'],$this->data['mana_regen_time']);
				break;
			case 'spell_healing':
				$name = $roster->locale[$lang]['spell_healing'];
				$tooltip = sprintf($roster->locale[$lang]['spell_healing_tooltip'],$this->data['spell_healing']);
				break;
		}

		$tooltipheader = (isset($name) ? $name : '');

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, '<strong class="white">'.$value.'</strong>', $line);
	}

	function printWSkill ( $location )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		if( $location == 'ranged' )
		{
			$value = '<strong class="white">'.$this->data['ranged_skill'].'</strong>';
			$name = $roster->locale[$lang]['weapon_skill'];
			$tooltipheader = $roster->locale[$lang]['ranged'];
			$tooltip = sprintf($roster->locale[$lang]['weapon_skill_tooltip'], $this->data['ranged_skill'], $this->data['ranged_rating']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
			$line = '<span style="color:#DFB801;">'.$tooltip.'</span>';
		}
		else
		{
			$value = '<strong class="white">'.$this->data['melee_mhand_skill'].'</strong>';
			$name = $roster->locale[$lang]['weapon_skill'];
			$tooltipheader = $roster->locale[$lang]['mainhand'];
			$tooltip = sprintf($roster->locale[$lang]['weapon_skill_tooltip'], $this->data['melee_mhand_skill'], $this->data['melee_mhand_rating']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
			$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

			if( $this->data['melee_ohand_dps'] > 0 )
			{
				$value .= '/'.'<strong class="white">'.$this->data['melee_ohand_skill'].'</strong>';
				$tooltipheader = $roster->locale[$lang]['offhand'];
				$tooltip = sprintf($roster->locale[$lang]['weapon_skill_tooltip'], $this->data['melee_ohand_skill'], $this->data['melee_ohand_rating']);

				$line .= '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
				$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';
			}
		}

		return $this->printStatLine($name, $value, $line);
	}

	function printWDamage ( $location )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		if( $location == 'ranged' )
		{
			$value = '<strong class="white">'.$this->data['ranged_mindam'].'</strong>'.'-'.'<strong class="white">'.$this->data['ranged_maxdam'].'</strong>';
			$name = $roster->locale[$lang]['damage'];
			$tooltipheader = $roster->locale[$lang]['ranged'];
			$tooltip = sprintf($roster->locale[$lang]['damage_tooltip'], $this->data['ranged_speed'], $this->data['ranged_mindam'], $this->data['ranged_maxdam'], $this->data['ranged_dps']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
			$line = '<span style="color:#DFB801;">'.$tooltip.'</span>';
		}
		else
		{
			$value = '<strong class="white">'.$this->data['melee_mhand_mindam'].'</strong>'.'-'.'<strong class="white">'.$this->data['melee_mhand_maxdam'].'</strong>';
			$name = $roster->locale[$lang]['damage'];
			$tooltipheader = $roster->locale[$lang]['mainhand'];
			$tooltip = sprintf($roster->locale[$lang]['damage_tooltip'], $this->data['melee_mhand_speed'], $this->data['melee_mhand_mindam'], $this->data['melee_mhand_maxdam'], $this->data['melee_mhand_dps']);

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
			$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

			if( $this->data['melee_ohand_dps'] > 0 )
			{
				$value .= '/'.'<strong class="white">'.$this->data['melee_ohand_mindam'].'</strong>'.'-'.'<strong class="white">'.$this->data['melee_ohand_maxdam'].'</strong>';
				$tooltipheader = $roster->locale[$lang]['offhand'];
				$tooltip = sprintf($roster->locale[$lang]['damage_tooltip'], $this->data['melee_ohand_speed'], $this->data['melee_ohand_mindam'], $this->data['melee_ohand_maxdam'], $this->data['melee_ohand_dps']);

				$line .= '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
				$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';
			}
		}


		return $this->printStatLine($name, $value, $line);
	}

	function printWSpeed ( $location )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		if( $location == 'ranged' )
		{
			$value = '<strong class="white">'.$this->data['ranged_speed'].'</strong>';
			$name = $roster->locale[$lang]['speed'];
			$tooltipheader = $roster->locale[$lang]['atk_speed'].' '.$value;
			$tooltip = $roster->locale[$lang]['haste_tooltip'].$this->printRatingLong('ranged_haste');

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
			$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';
		}
		else
		{
			$value = '<strong class="white">'.$this->data['melee_mhand_speed'].'</strong>';
			$name = $roster->locale[$lang]['speed'];

			if( $this->data['melee_ohand_dps'] > 0 )
			{
				$value .= '/'.'<strong class="white">'.$this->data['melee_ohand_speed'].'</strong>';
			}

			$tooltipheader = $roster->locale[$lang]['atk_speed'].' '.$value;
			$tooltip = $roster->locale[$lang]['haste_tooltip'].$this->printRatingLong('melee_haste');

			$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
			$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';
		}

		return $this->printStatLine($name, $value, $line);
	}

	function printSpellDamage( )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$name = $roster->locale[$lang]['spell_damage'];
		$value = '<strong class="white">'.$this->data['spell_damage'].'</strong>';

		$tooltipheader = $name.' '.$value;
		//$tooltip  = '<div><span style="float:right;">'.$this->data['spell_damage_holy'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-fire.gif" alt="" />'.$roster->locale[$lang]['holy'].'</div>';
		$tooltip  = '<div><span style="float:right;">'.$this->data['spell_damage_fire'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-fire.gif" alt="" />'.$roster->locale[$lang]['fire'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_nature'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-nature.gif" alt="" />'.$roster->locale[$lang]['nature'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_frost'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-frost.gif" alt="" />'.$roster->locale[$lang]['frost'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_shadow'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-shadow.gif" alt="" />'.$roster->locale[$lang]['shadow'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_arcane'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-arcane.gif" alt="" />'.$roster->locale[$lang]['arcane'].'</div>';


		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, $value, $line);
	}

	function printSpellCrit()
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$name = $roster->locale[$lang]['spell_crit_rating'];
		$value = $this->printRatingShort('spell_crit');

		$tooltipheader = $name.' '.$this->printRatingLong('spell_crit');
		$tooltip = $roster->locale[$lang]['spell_crit_chance'].' '.$this->data['spell_crit_chance'];
/*
		//$tooltip  = '<div><span style="float:right;">'.$this->data['spell_damage_holy'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-fire.gif" alt="" />'.$roster->locale[$lang]['holy'].'</div>';
		$tooltip  = '<div><span style="float:right;">'.$this->data['spell_damage_fire'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-fire.gif" alt="" />'.$roster->locale[$lang]['fire'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_nature'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-nature.gif" alt="" />'.$roster->locale[$lang]['nature'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_frost'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-frost.gif" alt="" />'.$roster->locale[$lang]['frost'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_shadow'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-shadow.gif" alt="" />'.$roster->locale[$lang]['shadow'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['spell_damage_arcane'].'</span><img src="'.$roster->config['img_url'].'char/resist/icon-arcane.gif" alt="" />'.$roster->locale[$lang]['arcane'].'</div>';
*/
		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, $value, $line);
	}

	function printDefense( )
	{
		global $roster, $wowdb;

		$lang = $this->data['clientLocale'];

		$qry = "SELECT `skill_level` FROM `roster_skills` WHERE `member_id` = ".$this->data['member_id']." AND `skill_name` = '".$roster->locale[$lang]['defense']."'";
		$result = $wowdb->query($qry);
		if( !$result )
		{
			$value = 'N/A';
		}
		else
		{
			$row = $wowdb->fetch_row($result);
			$value = explode(':',$row[0]);
			$value = $value[0];
			$wowdb->free_result($result);
			unset($row);
		}

		$name = $roster->locale[$lang]['defense'];
		$tooltipheader = $name.' '.$value;

		$tooltip = $roster->locale[$lang]['defense_rating'].$this->printRatingLong('stat_defr');

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, '<strong class="white">'.$value.'</strong>', $line);
	}

	function printDef( $statname )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$base = $this->data['stat_'.$statname];
		$current = $this->data['stat_'.$statname.'_c'];
		$buff = $this->data['stat_'.$statname.'_b'];
		$debuff = -$this->data['stat_'.$statname.'_d'];

		$name = $roster->locale[$lang][$statname];
		$value = $this->data[$statname];

		$tooltipheader = $name.' '.$this->printRatingLong('stat_'.$statname);
		$tooltip = sprintf($roster->locale[$lang]['def_tooltip'],$name);

		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, '<strong class="white">'.$value.'%</strong>', $line);
	}

	function printResilience( )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		$name = $roster->locale[$lang]['resilience'];
		$value = min($this->data['stat_res_melee'],$this->data['stat_res_ranged'],$this->data['stat_res_spell']);

		$tooltipheader = $name;
		$tooltip  = '<div><span style="float:right;">'.$this->data['stat_res_melee'].'</span>'.$roster->locale[$lang]['melee'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['stat_res_ranged'].'</span>'.$roster->locale[$lang]['ranged'].'</div>';
		$tooltip .= '<div><span style="float:right;">'.$this->data['stat_res_spell'].'</span>'.$roster->locale[$lang]['spell'].'</div>';


		$line = '<span style="color:#ffffff;font-size:11px;font-weight:bold;">'.$tooltipheader.'</span><br />';
		$line .= '<span style="color:#DFB801;">'.$tooltip.'</span>';

		return $this->printStatLine($name, '<strong class="white">'.$value.'%</strong>', $line);
	}

	function printResist( $resname )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		switch($resname)
		{
		case 'fire':
			$name = $roster->locale[$lang]['res_fire'];
			$tooltip = $roster->locale[$lang]['res_fire_tooltip'];
			$color = 'red';
			break;
		case 'nature':
			$name = $roster->locale[$lang]['res_nature'];
			$tooltip = $roster->locale[$lang]['res_nature_tooltip'];
			$color = 'green';
			break;
		case 'arcane':
			$name = $roster->locale[$lang]['res_arcane'];
			$tooltip = $roster->locale[$lang]['res_arcane_tooltip'];
			$color = 'yellow';
			break;
		case 'frost':
			$name = $roster->locale[$lang]['res_frost'];
			$tooltip = $roster->locale[$lang]['res_frost_tooltip'];
			$color = 'blue';
			break;
		case 'shadow':
			$name = $roster->locale[$lang]['res_shadow'];
			$tooltip = $roster->locale[$lang]['res_shadow_tooltip'];
			$color = 'purple';
			break;
		}

		$line = '<span style="color:'.$color.';font-size:11px;font-weight:bold;">'.$name.'</span> '.$this->printRatingLong('res_'.$resname).'<br />';
		$line .= '<span style="color:#DFB801;text-align:left;">'.$tooltip.'</span>';

		$output = '<div style="background-image:url('.$roster->config['img_url'].'char/resist/'.$resname.'.gif);" class="'.$resname.'" '.makeOverlib($line,'','',2,'','').'><b>'. $this->data['res_'.$resname.'_c'] .'</b><span>'. $this->data['res_'.$resname.'_c'] ."</span></div>\n";

		return $output;
	}


	function fetchEquip()
	{
		if (!is_array($this->equip))
		{
			$this->equip = item_get_many($this->data['member_id'], 'equip');
		}
	}

	function printEquip( $slot )
	{
		global $roster;

		$lang = $this->data['clientLocale'];

		if( isset($this->equip[$slot]) )
		{
			$item = $this->equip[$slot];
			$output = $item->out();
		}
		else
		{
			$output = '<div class="item" '.makeOverlib($roster->locale[$lang]['empty_equip'],$slot,'',2,'',',WRAP').'>'."\n";
			if ($slot == 'Ammo')
				$output .= '<img src="'.$roster->config['img_url'].'pixel.gif" class="iconsmall"'." alt=\"\" />\n";
			else
				$output .= '<img src="'.$roster->config['img_url'].'pixel.gif" class="icon"'." alt=\"\" />\n";
			$output .= "</div>\n";
		}
		return '<div class="equip_'.$slot.'">'.$output.'</div>';
	}


	function printTalents( )
	{
		global $roster, $wowdb;

		$lang = $this->data['clientLocale'];

		$sqlquery = "SELECT * FROM `".ROSTER_TALENTTREETABLE."` WHERE `member_id` = '".$this->data['member_id']."' ORDER BY `order`;";
		$trees = $wowdb->query( $sqlquery );

		if( $wowdb->num_rows($trees) > 0 )
		{
			for( $j=0; $j < $wowdb->num_rows($trees); $j++)
			{
				$treedata = $wowdb->fetch_assoc($trees);

				$treelayer[$j]['name'] = $treedata['tree'];
				$treelayer[$j]['image'] = $treedata['background'].'.'.$roster->config['img_suffix'];
				$treelayer[$j]['points'] = $treedata['pointsspent'];
				$treelayer[$j]['talents'] = $this->talentLayer($treedata['tree']);
			}

			$returndata = '
<div class="char_panel talent_panel">

	<img class="panel_icon" src="'.$roster->config['img_url'].'char/menubar/icon_talents.gif" alt="" />
	<div class="panel_title">'.$roster->locale[$lang]['talents'].'</div>
	<img class="top_bar" src="'.$roster->config['img_url'].'char/talent/bar_top.gif" alt="" />
	<img class="bot_bar" src="'.$roster->config['img_url'].'char/talent/bar_bottom.gif" alt="" />

	<div class="link"><a href="';

			switch($this->data['clientLocale'])
			{
				case 'enUS':
					$returndata .= 'http://www.worldofwarcraft.com/info/classes/';
					break;

				case 'frFR':
					$returndata .= 'http://www.wow-europe.com/fr/info/basics/talents/';
					break;

				case 'deDE':
					$returndata .= 'http://www.wow-europe.com/de/info/basics/talents/';
					break;

				case 'esES':
					$returndata .= 'http://www.wow-europe.com/es/info/basics/talents/';
					break;

				default:
					$returndata .= 'http://www.worldofwarcraft.com/info/classes/';
					break;
			}

			$returndata .= strtolower($this->data['classEn']).'/talents.html?'.$this->talent_build.'" target="_blank">'.$roster->locale[$this->data['clientLocale']]['talentexport'].'</a></div>
	<div class="points_unused"><span class="label">'.$roster->locale[$this->data['clientLocale']]['unusedtalentpoints'].':</span> '.$this->data['talent_points'].'</div>'."\n";

			foreach( $treelayer as $treeindex => $tree )
			{
				$returndata .= '	<div id="treetab'.$treeindex.'" class="char_tab" style="display:none;" >

		<div class="points"><span style="color:#ffdd00">'.$roster->locale[$this->data['clientLocale']]['pointsspent'].' '.$tree['name'].' Talents:</span> '.$tree['points'].'</div>
		<img class="background" src="'.$roster->config['interface_url'].'Interface/TalentFrame/'.$tree['image'].'" alt="" />

		<div class="container">'."\n";

				foreach( $tree['talents'] as $row )
				{
					$returndata .= '			<div class="row">'."\n";
					foreach( $row as $cell )
					{
						if( $cell['name'] != '' )
						{
							if( $cell['rank'] != 0 )
							{
								$returndata .= '				<div class="cell" '.$cell['tooltipid'].'>
					<img class="rank_icon" src="'.$roster->config['img_url'].'char/talent/rank.gif" alt="" />
					<div class="rank_text" style="font-weight:bold;color:#'.$cell['numcolor'].';">'.$cell['rank'].'</div>
					<img src="'.$roster->config['interface_url'].'Interface/Icons/'.$cell['image'].'" alt="" /></div>'."\n";
							}
							else
							{
								$returndata .= '				<div class="cell" '.$cell['tooltipid'].'>
					<img class="icon_grey" src="'.$roster->config['interface_url'].'Interface/Icons/'.$cell['image'].'" alt="" /></div>'."\n";
							}
						}
						else
						{
							$returndata .= '				<div class="cell">&nbsp;</div>'."\n";
						}
					}
					$returndata .= '			</div>'."\n";
				}

				$returndata .= "		</div>\n	</div>\n";
			}
			$returndata .= '
	<div id="talent_navagation" class="tab_navagation">
		<ul>
			<li onclick="return displaypage(\'treetab0\',this);"><div class="text">'.$treelayer[0]['name'].'</div></li>
			<li onclick="return displaypage(\'treetab1\',this);"><div class="text">'.$treelayer[1]['name'].'</div></li>
			<li onclick="return displaypage(\'treetab2\',this);"><div class="text">'.$treelayer[2]['name'].'</div></li>
		</ul>
	</div>

</div>

<script type="text/javascript">
	//Set tab to intially be selected when page loads:
	//[which tab (1=first tab), ID of tab content to display]:
	window.onload=tab_nav_onload(\'talent_navagation\',[1, \'treetab0\'])
</script>';
			return $returndata;
		}
		else
		{
			return '<span class="headline_1">No Talents for '.$this->data['name'].'</span>';
		}
	}

	function talentLayer( $treename )
	{
		global $wowdb, $roster;

		$sqlquery = "SELECT * FROM `".ROSTER_TALENTSTABLE."` WHERE `member_id` = '".$this->data['member_id']."' AND `tree` = '".$treename."' ORDER BY `row` ASC , `column` ASC";

		$result = $wowdb->query($sqlquery);

		$returndata = array();
		if( $wowdb->num_rows($result) > 0 )
		{
			// initialize the rows and cells
			for($r=1; $r < 10; $r++)
			{
				for($c=1; $c < 5; $c++)
				{
					$returndata[$r][$c]['name'] = '';
				}
			}

			while( $talentdata = $wowdb->fetch_assoc( $result ) )
			{
				$r = $talentdata['row'];
				$c = $talentdata['column'];

				$this->talent_build .= $talentdata['rank'];

				$returndata[$r][$c]['name'] = $talentdata['name'];
				$returndata[$r][$c]['rank'] = $talentdata['rank'];
				$returndata[$r][$c]['maxrank'] = $talentdata['maxrank'];
				$returndata[$r][$c]['row'] = $r;
				$returndata[$r][$c]['column'] = $c;
				$returndata[$r][$c]['image'] = $talentdata['texture'].'.'.$roster->config['img_suffix'];
				$returndata[$r][$c]['tooltipid'] = makeOverlib($talentdata['tooltip'],'','',0,$this->data['clientLocale']);

				if( $talentdata['rank'] == $talentdata['maxrank'] )
				{
					$returndata[$r][$c]['numcolor'] = 'ffdd00';
				}
				else
				{
					$returndata[$r][$c]['numcolor'] = '00dd00';
				}
			}
		}
		return $returndata;
	}


	function printSkills( )
	{
		global $roster;

		$skillData = $this->getSkillTabValues();

		$output = '';
		foreach( $skillData as $sindex => $skill )
		{
			$output .= '
		<div class="header"><img src="'.$roster->config['img_url'].'minus.gif" id="skill'.$sindex.'_img" class="minus_plus" alt="" onclick="showHide(\'skill'.$sindex.'\',\'skill'.$sindex.'_img\',\''.$roster->config['img_url'].'minus.gif\',\''.$roster->config['img_url'].'plus.gif\');" />'.$skill['name'].'</div>
		<div id="skill'.$sindex.'">
';
			foreach( $skill['bars'] as $skillbar )
			{
				$output .= '
			<div class="skill_bar">';
				if( $skillbar['maxvalue'] == '1' )
				{
					$output .= '
				<div style="position:absolute;"><img src="'.$roster->config['img_url'].'char/skill/bar_grey.gif" alt="" /></div>
				<div class="text">'.$skillbar['name'].'</div>';
				}
				else
				{
					$output .= '
				<div style="position:absolute;clip:rect(0px '.$skillbar['barwidth'].'px 15px 0px);"><img src="'.$roster->config['img_url'].'char/skill/bar.gif" alt="" /></div>
				<div class="text">'.$skillbar['name'].'<span class="text_num">'.$skillbar['value'].' / '.$skillbar['maxvalue'].'</span></div>';
				}
				$output .= "\n			</div>\n";
			}
			$output .= "		</div>\n";
		}

		return $output;
	}

	function getSkillBarValues( $skilldata )
	{
		list($level, $max) = explode( ':', $skilldata['skill_level'] );

		$returnData['maxvalue'] = $max;
		$returnData['value'] = $level;
		$returnData['name'] = $skilldata['skill_name'];
		$returnData['barwidth'] = ceil($level/$max*273);

		return $returnData;
	}

	function getSkillTabValues( )
	{
		global $wowdb;

		$query = "SELECT * FROM `".ROSTER_SKILLSTABLE."` WHERE `member_id` = '".$this->data['member_id']."' ORDER BY `skill_order` ASC, `skill_name` ASC;";
		$result = $wowdb->query( $query );

		$skill_rows = $wowdb->num_rows($result);

		$i=0;
		$j=0;
		if ( $skill_rows > 0 )
		{
			$data = $wowdb->fetch_assoc( $result );
			$skillInfo[$i]['name'] = $data['skill_type'];

			for( $r=0; $r < $skill_rows; $r++ )
			{
				if( $skillInfo[$i]['name'] != $data['skill_type'] )
				{
					$i++;
					$j=0;
					$skillInfo[$i]['name'] = $data['skill_type'];
				}
				$skillInfo[$i]['bars'][$j] = $this->getSkillBarValues($data);
				$j++;
				$data = $wowdb->fetch_assoc( $result );
			}
			return $skillInfo;
		}
	}


	function printReputation( )
	{
		global $roster;

		$repData = $this->getRepTabValues();

		$output = '';
		foreach( $repData as $findex => $faction )
		{
			$output .= '
		<div class="header"><img src="'.$roster->config['img_url'].'minus.gif" id="rep'.$findex.'_img" class="minus_plus" alt="" onclick="showHide(\'rep'.$findex.'\',\'rep'.$findex.'_img\',\''.$roster->config['img_url'].'minus.gif\',\''.$roster->config['img_url'].'plus.gif\');" />'.$faction['name'].'</div>
		<div id="rep'.$findex.'">
';
			foreach( $faction['bars'] as $repbar )
			{
				$output .= '
			<div class="rep_bar">
				<div class="rep_title">'.$repbar['name'].'</div>
				<div class="rep_bar_field" style="clip:rect(0px '.$repbar['barwidth'].'px 13px 0px);"><img class="rep_bar_image" src="'.$repbar['image'].'" alt="" /></div>
				<div id="rb_'.$repbar['barid'].'" class="rep_bar_text">'.$repbar['standing'].'</div>
				<div id="rbn_'.$repbar['barid'].'" class="rep_bar_text" style="display:none">'.$repbar['value'].' / '.$repbar['maxvalue'].'</div>
				<div class="rep_bar_field"><img class="rep_bar_image" src="'.$roster->config['img_url'].'pixel.gif" onmouseout="swapShow(\'rb_'.$repbar['barid'].'\',\'rbn_'.$repbar['barid'].'\');" onmouseover="swapShow(\'rb_'.$repbar['barid'].'\',\'rbn_'.$repbar['barid'].'\');" alt="" /></div>'."\n";
				if( $repbar['atwar'] == 1 )
				{
					$output .= '				<img src="'.$roster->config['img_url'].'/char/rep/atwar.gif" style="float:right;" alt="" />'."\n";
				}
				$output .= "			</div>\n";
			}
			$output .= "		</div>\n";
		}

		return $output;
	}

	function getRepTabValues( )
	{
		global $wowdb;

		$query= "SELECT * FROM `".ROSTER_REPUTATIONTABLE."` WHERE `member_id` = '".$this->data['member_id']."' ORDER BY `faction` ASC, `name` ASC;";
		$result = $wowdb->query( $query );

		$rep_rows = $wowdb->num_rows($result);

		$i=0;
		$j=0;
		if ( $rep_rows > 0 )
		{
			$data = $wowdb->fetch_assoc( $result );
			$repInfo[$i]['name'] = $data['faction'];

			for( $r=0; $r < $rep_rows; $r++ )
			{
				if( $repInfo[$i]['name'] != $data['faction'] )
				{
					$i++;
					$j=0;
					$repInfo[$i]['name'] = $data['faction'];
				}
				$repInfo[$i]['bars'][$j] = $this->getRepBarValues($data);
				$j++;
				$data = $wowdb->fetch_assoc( $result );
			}
			return $repInfo;
		}
	}

	function getRepBarValues( $repdata )
	{
		static $repnum = 0;

		global $roster, $char;

		$lang = $char->data['clientLocale'];

		$level = $repdata['curr_rep'];
		$max = $repdata['max_rep'];

		$img = array(
			$roster->locale[$lang]['exalted'] => $roster->config['img_url'].'char/rep/green.gif',
			$roster->locale[$lang]['revered'] => $roster->config['img_url'].'char/rep/green.gif',
			$roster->locale[$lang]['honored'] => $roster->config['img_url'].'char/rep/green.gif',
			$roster->locale[$lang]['friendly'] => $roster->config['img_url'].'char/rep/green.gif',
			$roster->locale[$lang]['neutral'] => $roster->config['img_url'].'char/rep/yellow.gif',
			$roster->locale[$lang]['unfriendly'] => $roster->config['img_url'].'char/rep/orange.gif',
			$roster->locale[$lang]['hostile'] => $roster->config['img_url'].'char/rep/red.gif',
			$roster->locale[$lang]['hated'] => $roster->config['img_url'].'char/rep/red.gif'
		);

		$returnData['name'] = $repdata['name'];
		$returnData['barwidth'] = ceil($level / $max * 139);
		$returnData['image'] = $img[$repdata['Standing']];
		$returnData['barid'] = $repnum;
		$returnData['standing'] = $repdata['Standing'];
		$returnData['value'] = $level;
		$returnData['maxvalue'] = $max;
		$returnData['atwar'] = $repdata['AtWar'];

		$repnum++;

		return $returnData;
	}


	function printHonor()
	{
		global $roster, $wowdb;

		$lang = $this->data['clientLocale'];

		$icon = '';
		switch( substr($roster->data['faction'],0,1) )
		{
			case 'A':
				$icon = '<img src="'.$roster->config['img_url'].'battleground-alliance.png" alt="" />';
				break;
			case 'H':
				$icon = '<img src="'.$roster->config['img_url'].'battleground-horde.png" alt="" />';
				break;
		}

		$output = '
		<div class="honortext">'.$roster->locale[$lang]['honor'].':<span>'.$this->data['honorpoints'].'</span>'.$icon.'</div>

		<div class="today">'.$roster->locale[$lang]['today'].'</div>
		<div class="yesterday">'.$roster->locale[$lang]['yesterday'].'</div>
		<div class="lifetime">'.$roster->locale[$lang]['lifetime'].'</div>

		<div class="divider"></div>

		<div class="killsline">'.$roster->locale[$lang]['kills'].'</div>
		<div class="killsline1">'.$this->data['sessionHK'].'</div>
		<div class="killsline2">'.$this->data['yesterdayHK'].'</div>
		<div class="killsline3">'.$this->data['lifetimeHK'].'</div>

		<div class="honorline">'.$roster->locale[$lang]['honor'].'</div>
		<div class="honorline1">~'.$this->data['sessionCP'].'</div>
		<div class="honorline2">'.$this->data['yesterdayContribution'].'</div>
		<div class="honorline3">-</div>

		<div class="arenatext">'.$roster->locale[$lang]['arena'].':<span>'.$this->data['arenapoints'].'</span><img src="'.$roster->config['img_url'].'arenapointsicon.png" alt="" /></div>'."\n";

		return $output;
	}

	function out( )
	{
		global $roster, $addon;

		$lang = $this->data['clientLocale'];

		if ($this->data['name'] != '')
		{
			$this->fetchEquip();
			$petTab = $this->printPet();

?>

<div class="char_panel">
	<img src="<?php print $this->data['char_icon']; ?>.gif" class="panel_icon" alt="" />
	<div class="panel_title"><?php print $this->data['name']; ?></div>
	<div class="infoline_1"><?php print sprintf($roster->locale[$lang]['char_level_race_class'],$this->data['level'],$this->data['race'],$this->data['class']); ?></div>
<?php

if( isset( $this->data['guild_name'] ) )
	echo '	<div class="infoline_2">'.sprintf($roster->locale[$lang]['char_guildline'],$this->data['guild_title'],$this->data['guild_name'])."</div>\n";

?>

<!-- Begin tab1 -->
	<div id="tab1" class="tab1" style="display:none;">
		<div class="background">&nbsp;</div>

	<!-- Begin Equipment Items -->
		<div class="equip">
			<?php print $this->printEquip('Head'); ?>
			<?php print $this->printEquip('Neck'); ?>
			<?php print $this->printEquip('Shoulder'); ?>
			<?php print $this->printEquip('Back'); ?>
			<?php print $this->printEquip('Chest'); ?>
			<?php print $this->printEquip('Shirt'); ?>
			<?php print $this->printEquip('Tabard'); ?>
			<?php print $this->printEquip('Wrist'); ?>

			<?php print $this->printEquip('MainHand'); ?>
			<?php print $this->printEquip('SecondaryHand'); ?>
			<?php print $this->printEquip('Ranged'); ?>
			<?php print $this->printEquip('Ammo'); ?>

			<?php print $this->printEquip('Hands'); ?>
			<?php print $this->printEquip('Waist'); ?>
			<?php print $this->printEquip('Legs'); ?>
			<?php print $this->printEquip('Feet'); ?>
			<?php print $this->printEquip('Finger0'); ?>
			<?php print $this->printEquip('Finger1'); ?>
			<?php print $this->printEquip('Trinket0'); ?>
			<?php print $this->printEquip('Trinket1'); ?>
		</div>
	<!-- End Equipment Items -->

	<!-- Begin Resists -->
		<div class="resist">
			<?php print $this->printResist('arcane'); ?>
			<?php print $this->printResist('fire'); ?>
			<?php print $this->printResist('nature'); ?>
			<?php print $this->printResist('frost'); ?>
			<?php print $this->printResist('shadow'); ?>
		</div>
	<!-- End Resists -->

	<!-- Begin Advanced Stats -->
		<img src="<?php print $roster->config['img_url']; ?>char/percentframe.gif" class="percent_frame" alt="" />

		<div class="health"><span class="yellowB"><?php print $roster->locale[$lang]['health']; ?>:</span> <?php print $this->data['health']; ?></div>
		<div class="mana"><span class="yellowB"><?php print $this->data['power']; ?>:</span> <?php print $this->data['mana']; ?></div>

		<div class="info_desc">
<?php

if($this->data['talent_points'])
	print '			'.$roster->locale[$lang]['unusedtalentpoints']."<br />\n";

print '			'.$roster->locale[$lang]['timeplayed']."<br />\n";
print '			'.$roster->locale[$lang]['timelevelplayed']."<br />\n";

?>
		</div>
		<div class="info_values">
<?php

if($this->data['talent_points'])
	print '			'.$this->data['talent_points']."<br />\n";

$TimeLevelPlayedConverted = seconds_to_time($this->data['timelevelplayed']);
$TimePlayedConverted = seconds_to_time($this->data['timeplayed']);
print '			'.$TimePlayedConverted['days'].$TimePlayedConverted['hours'].$TimePlayedConverted['minutes'].$TimePlayedConverted['seconds']."<br />\n";
print '			'.$TimeLevelPlayedConverted['days'].$TimeLevelPlayedConverted['hours'].$TimeLevelPlayedConverted['minutes'].$TimeLevelPlayedConverted['seconds']."<br />\n";
?>
		</div>
<?php

if( $addon['config']['show_money'] )
{
	print '
		<!-- Money Display -->
		<div class="money_disp">'."\n";
	if( $this->data['money_g'] != '0' )
		print '			'.$this->data['money_g'].'<img src="'.$roster->config['img_url'].'coin_gold.gif" class="coin" alt="g" />'."\n";
	if( $this->data['money_s'] != '0' )
		print '			'.$this->data['money_s'].'<img src="'.$roster->config['img_url'].'coin_silver.gif" class="coin" alt="s" />'."\n";
	if( $this->data['money_c'] != '0' )
		print '			'.$this->data['money_c'].'<img src="'.$roster->config['img_url'].'coin_copper.gif" class="coin" alt="c" />'."\n";
print '
		</div>
';
}

// Code to write a "Max Exp bar" just like in SigGen
if( $this->data['level'] == ROSTER_MAXCHARLEVEL )
{
	$expbar_width = '216';
	$expbar_text = $roster->locale[$lang]['max_exp'];
	$expbar_type = 'expbar_full';
}
else
{
	list($xp, $xplevel, $xprest) = explode(':',$this->data['exp']);
	if ($xplevel != '0' && $xplevel != '')
	{
		$expbar_width = ( $xplevel > 0 ? floor($xp / $xplevel * 216) : 0);

		$exp_percent = ( $xplevel > 0 ? floor($xp / $xplevel * 100) : 0);

		if( $xprest > 0 )
		{
			$expbar_text = $xp.'/'.$xplevel.' : '.$xprest.' ('.$exp_percent.'%)';
			$expbar_type = 'expbar_full_rested';
		}
		else
		{
			$expbar_text = $xp.'/'.$xplevel.' ('.$exp_percent.'%)';
			$expbar_type = 'expbar_full';
		}
	}
}

?>
	<!-- Begin EXP Bar -->
		<img src="<?php print $roster->config['img_url']; ?>char/expbar_empty.gif" class="xpbar_empty" alt="" />
		<div class="xpbar" style="clip:rect(0px <?php print $expbar_width; ?>px 12px 0px);"><img src="<?php print $roster->config['img_url'].'char/'.$expbar_type.'.gif'; ?>" alt="" /></div>
		<div class="xpbar_text"><?php print $expbar_text; ?></div>
	<!-- End EXP Bar -->


<?php
	switch( $this->data['class'] )
	{
		case $roster->locale[$this->data['clientLocale']]['Warrior']:
		case $roster->locale[$this->data['clientLocale']]['Paladin']:
		case $roster->locale[$this->data['clientLocale']]['Rogue']:
			$rightbox = 'melee';
			break;

		case $roster->locale[$this->data['clientLocale']]['Hunter']:
			$rightbox = 'ranged';
			break;

		case $roster->locale[$this->data['clientLocale']]['Shaman']:
		case $roster->locale[$this->data['clientLocale']]['Druid']:
		case $roster->locale[$this->data['clientLocale']]['Mage']:
		case $roster->locale[$this->data['clientLocale']]['Warlock']:
		case $roster->locale[$this->data['clientLocale']]['Priest']:
			$rightbox = 'spell';
			break;
	}
?>
<script type="text/javascript">
<!--
	addLpage('statsleft');
	addLpage('meleeleft');
	addLpage('rangedleft');
	addLpage('spellleft');
	addLpage('defenseleft');
	addRpage('statsright');
	addRpage('meleeright');
	addRpage('rangedright');
	addRpage('spellright');
	addRpage('defenseright');
//-->
</script>
		<form action="<?php print makelink(); ?>">
			<select class="statselect_l" name="statbox_left" onchange="doLpage(this.value);">
				<option value="statsleft" selected="selected"><?php print $roster->locale[$lang]['menustats']; ?></option>
				<option value="meleeleft"><?php print $roster->locale[$lang]['melee']; ?></option>
				<option value="rangedleft"><?php print $roster->locale[$lang]['ranged']; ?></option>
				<option value="spellleft"><?php print $roster->locale[$lang]['spell']; ?></option>
				<option value="defenseleft"><?php print $roster->locale[$lang]['defense']; ?></option>
			</select>
				<select class="statselect_r" name="statbox_right" onchange="doRpage(this.value);">
				<option value="statsright"><?php print $roster->locale[$lang]['menustats']; ?></option>
				<option value="meleeright"<?php echo ($rightbox == 'melee'?' selected="selected"':'');?>><?php print $roster->locale[$lang]['melee']; ?></option>
				<option value="rangedright"<?php echo ($rightbox == 'ranged'?' selected="selected"':'');?>><?php print $roster->locale[$lang]['ranged']; ?></option>
				<option value="spellright"<?php echo ($rightbox == 'spell'?' selected="selected"':'');?>><?php print $roster->locale[$lang]['spell']; ?></option>
				<option value="defenseright"><?php print $roster->locale[$lang]['defense']; ?></option>
			</select>
		</form>
		<div class="padding">
			<?php print $this->printBox('stats','left',true); ?>
			<?php print $this->printBox('melee','left',false); ?>
			<?php print $this->printBox('ranged','left',false); ?>
			<?php print $this->printBox('spell','left',false); ?>
			<?php print $this->printBox('defense','left',false); ?>
			<?php print $this->printBox('stats','right',false); ?>
			<?php print $this->printBox('melee','right',$rightbox=='melee'); ?>
			<?php print $this->printBox('ranged','right',$rightbox=='ranged'); ?>
			<?php print $this->printBox('spell','right',$rightbox=='spell'); ?>
			<?php print $this->printBox('defense','right',false); ?>
		</div>
	</div>
<!-- Begin tab2 -->
	<div id="tab2" class="tab2" style="display:none;">
		<div class="background">&nbsp;</div>
		<?php print $petTab; ?>
	</div>

<!-- Begin tab3 -->
	<div id="tab3" class="tab3" style="display:none;">
		<div class="faction"><?php print $roster->locale[$lang]['faction']; ?></div>
		<div class="standing"><?php print $roster->locale[$lang]['standing']; ?></div>
		<div class="atwar"><?php print $roster->locale[$lang]['atwar']; ?></div>

		<div class="container">
<?php print $this->printReputation(); ?>
		</div>
	</div>

<!-- Begin tab4 -->
	<div id="tab4" class="tab4" style="display:none;">
		<div class="container">
<?php print $this->printSkills(); ?>
		</div>
	</div>

<!-- Begin tab5 -->
	<div id="tab5" class="tab5" style="display:none;">
		<div class="background">&nbsp;</div>
<?php print $this->printHonor(); ?>
	</div>

<!-- Begin Navagation Tabs -->
	<div id="char_navagation" class="tab_navagation">
		<ul>
			<li onclick="return displaypage('tab1',this);"><div class="text"><?php print $roster->locale[$lang]['tab1']; ?></div></li>
<?php
if( $petTab != '' )
	print '			<li onclick="return displaypage(\'tab2\',this);"><div class="text">'.$roster->locale[$lang]['tab2'].'</div></li>'."\n";
?>
			<li onclick="return displaypage('tab3',this);"><div class="text"><?php print $roster->locale[$lang]['tab3']; ?></div></li>
			<li onclick="return displaypage('tab4',this);"><div class="text"><?php print $roster->locale[$lang]['tab4']; ?></div></li>
			<li onclick="return displaypage('tab5',this);"><div class="text"><?php print $roster->locale[$lang]['tab5']; ?></div></li>
		</ul>
	</div>

</div>


<script type="text/javascript">
	//Set tab to intially be selected when page loads:
	//[which tab (1=first tab), ID of tab content to display]:
	window.onload=tab_nav_onload('char_navagation',[1, 'tab1'])
</script>

<?php

		}
		else
		{
			roster_die('Sorry no data in database for '.$_GET['name'].' of '.$_GET['server'],'Character Not Found');
		}
	}
}


function char_get_one_by_id( $member_id )
{
	global $wowdb, $roster;

	$query = "SELECT a.*, b.*, `c`.`guild_name`, DATE_FORMAT(  DATE_ADD(`a`.`dateupdatedutc`, INTERVAL ".$roster->config['localtimeoffset']." HOUR ), '".$roster->locale->act['timeformat']."' ) AS 'update_format' ".
		"FROM `".ROSTER_PLAYERSTABLE."` a, `".ROSTER_MEMBERSTABLE."` b, `".ROSTER_GUILDTABLE."` c " .
		"WHERE `a`.`member_id` = `b`.`member_id` AND `a`.`member_id` = '$member_id' AND `a`.`guild_id` = `c`.`guild_id`;";
	$result = $wowdb->query( $query );
	if( $wowdb->num_rows($result) > 0 )
	{
		$data = $wowdb->fetch_assoc( $result );
		return new char( $data );
	}
	else
	{
		return false;
	}
}


function char_get_one( $name, $server )
{
	global $wowdb, $roster;

	$name = $wowdb->escape( $name );
	$server = $wowdb->escape( $server );
	$query = "SELECT `a`.*, `b`.*, `c`.`guild_name`, DATE_FORMAT(  DATE_ADD(`a`.`dateupdatedutc`, INTERVAL ".$roster->config['localtimeoffset']." HOUR ), '".$roster->locale->act['timeformat']."' ) AS 'update_format' ".
		"FROM `".ROSTER_PLAYERSTABLE."` a, `".ROSTER_MEMBERSTABLE."` b, `".ROSTER_GUILDTABLE."` c " .
		"WHERE `a`.`member_id` = `b`.`member_id` AND `a`.`name` = '$name' AND `a`.`server` = '$server' AND `a`.`guild_id` = `c`.`guild_id`;";
	$result = $wowdb->query( $query );
	if( $wowdb->num_rows($result) > 0 )
	{
		$data = $wowdb->fetch_assoc( $result );
		return new char( $data );
	}
	else
	{
		return false;
	}
}


function DateCharDataUpdated($id)
{
	global $wowdb, $roster;

	$query = "SELECT `dateupdatedutc`, `clientLocale` FROM `".ROSTER_PLAYERSTABLE."` WHERE `member_id` = '$id'";
	$result = $wowdb->query($query);
	$data = $wowdb->fetch_assoc($result);
	$wowdb->free_result($result);

	list($year,$month,$day,$hour,$minute,$second) = sscanf($data['dateupdatedutc'],"%d-%d-%d %d:%d:%d");
	$localtime = mktime($hour+$roster->config['localtimeoffset'] ,$minute, $second, $month, $day, $year, -1);
	return date($roster->locale[$data['clientLocale']]['phptimeformat'], $localtime);
}