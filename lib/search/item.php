<?php
/**
 * WoWRoster.net WoWRoster
 *
 *
 * @copyright  2002-2011 WoWRoster.net
 * @license    http://www.gnu.org/licenses/gpl.html   Licensed under the GNU General Public License v3.
 * @version    SVN: $Id$
 * @link       http://www.wowroster.net
 * @package    WoWRoster
 * @subpackage Search
 */

if( !defined('IN_ROSTER') )
{
	exit('Detected invalid access to this file!');
}

/**
 * Item Search
 *
 * @package    WoWRoster
 * @subpackage Search
 */
class roster_itemSearch
{
	var $options;
	var $result = array();
	var $result_count = 0;
	var $start_search;
	var $stop_search;
	var $time_search;
	var $open_table;
	var $close_table;
	var $search_url;
	var $data = array();    // Addon data

	var $minlvl;
	var $maxlvl;
	var $quality;
	var $quality_sql;

	// class constructor
	function roster_itemSearch()
	{
		global $roster;

		require_once (ROSTER_LIB . 'item.php');

		$this->open_table = '<tr><th class="membersHeader ts_string">' . $roster->locale->act['item'] . '</th>'
			. '<th class="membersHeader ts_string">' . $roster->locale->act['level'] . '</th>'
			. '<th class="membersHeader ts_string">' . $roster->locale->act['name'] . '</th>'
			. '<th class="membersHeaderRight ts_string">' . $roster->locale->act['character'] . '</th></tr>';

		$this->minlvl = isset($_POST['item_minle']) ? (int)$_POST['item_minle'] : ( isset($_GET['item_minle']) ? (int)$_GET['item_minle'] : '' );
		$this->maxlvl = isset($_POST['item_maxle']) ? (int)$_POST['item_maxle'] : ( isset($_GET['item_maxle']) ? (int)$_GET['item_maxle'] : '' );
		$this->quality = isset($_POST['item_quality']) ? $_POST['item_quality'] : ( isset($_GET['item_quality']) ? $_GET['item_quality'] : array() );

		// Set up next/prev search link
		$this->search_url  = ( $this->minlvl != '' ? '&amp;item_minle=' . $this->minlvl : '' );
		$this->search_url .= ( $this->maxlvl != '' ? '&amp;item_maxle=' . $this->maxlvl : '' );

		// Assemble sql for item quality
		if( count($this->quality) > 0 )
		{
			$i = 0;
			$this->quality_sql = array();
			foreach( $this->quality as $color )
			{
				$this->quality_sql[] = "`items`.`item_color` = '$color'";
				$this->search_url .= '&amp;item_quality[' . $i++ . ']=' . $color;
			}
			$this->quality_sql = ' AND (' . implode(' OR ',$this->quality_sql) . ')';
		}

		//advanced options for searching items
		$this->options = '
	<label for="item_minle">' . $roster->locale->act['level'] . ':</label>
	<input type="text" name="item_minle" id="item_minle" size="3" maxlength="3" value="' . $this->minlvl . '" /> -
	<input type="text" name="item_maxle" id="item_maxle" size="3" maxlength="3" value="' . $this->maxlvl . '" /><br />
	<label for="item_quality">' . $roster->locale->act['quality'] . ':</label>
	<select name="item_quality[]" id="item_quality" size="7" multiple="multiple" class="multiselect">
		<option value="9d9d9d"' . ( in_array('9d9d9d',$this->quality) ? ' selected="selected"' : '' ) . '>' . $roster->locale->act['poor'] . '</option>
		<option value="ffffff"' . ( in_array('ffffff',$this->quality) ? ' selected="selected"' : '' ) . '>' . $roster->locale->act['common'] . '</option>
		<option value="1eff00"' . ( in_array('1eff00',$this->quality) ? ' selected="selected"' : '' ) . '>' . $roster->locale->act['uncommon'] . '</option>
		<option value="0070dd"' . ( in_array('0070dd',$this->quality) ? ' selected="selected"' : '' ) . '>' . $roster->locale->act['rare'] . '</option>
		<option value="a335ee"' . ( in_array('a335ee',$this->quality) ? ' selected="selected"' : '' ) . '>' . $roster->locale->act['epic'] . '</option>
		<option value="ff8800"' . ( in_array('ff8800',$this->quality) ? ' selected="selected"' : '' ) . '>' . $roster->locale->act['legendary'] . '</option>
		<option value="e6cc80"' . ( in_array('ff8800',$this->quality) ? ' selected="selected"' : '' ) . '>' . $roster->locale->act['heirloom'] . '</option>
	</select>';
	}

	function search( $search , $limit=10 , $page=0 )
	{
		global $roster;

		$first = $page * $limit;

		$sql = "SELECT `players`.`name`, `players`.`member_id`, `players`.`server`, `players`.`region`, `items`.*"
			. " FROM `" . $roster->db->table('items') . "` AS items,`" . $roster->db->table('players') . "` AS players"
			. " WHERE `items`.`member_id` = `players`.`member_id`"
				. " AND (`items`.`item_name` LIKE '%$search%' OR `items`.`item_tooltip` LIKE '%$search%')"
				. ( $this->minlvl != '' ? " AND `items`.`level` >= '$this->minlvl'" : '' )
				. ( $this->maxlvl != '' ? " AND `items`.`level` <= '$this->maxlvl'" : '' )
				. $this->quality_sql
			. " ORDER BY `items`.`item_name` ASC"
			. ( $limit > 0 ? " LIMIT $first," . $limit : '' ) . ';';

		//calculating the search time
		$this->start_search = format_microtime();

		$result = $roster->db->query($sql);

		$this->stop_search = format_microtime();
		$this->time_search = $this->stop_search - $this->start_search;

		$nrows = $roster->db->num_rows($result);

		$x = ($limit > $nrows) ? $nrows : ($limit > 0 ? $limit : $nrows);
		if( $nrows > 0 && $limit > 0 )
		{
			while( $x > 0 )
			{
				$row = $roster->db->fetch($result);
				$icon = new item($row);

				$item['html'] = '<td class="SearchRowCell">' . $icon->out(true) . '</td>'
					. '<td class="SearchRowCell">' . $icon->requires_level . '</td>'
					. '<td class="SearchRowCell"><span style="color:#' . $icon->color . '">[' . $icon->name . ']</span></td>'
					. '<td class="SearchRowCellRight"><a href="' . makelink('char-info&amp;a=c:' . $row['member_id']) . '"><strong>' . $row['name'] . '</strong></a></td>';

				$this->add_result($item);
				unset($item);
				$x--;
			}
		}
		else
		{
			$this->result_count = $nrows;
		}
		$roster->db->free_result($result);
	}

	function add_result( $resultarray )
	{
		$this->result[$this->result_count++] = $resultarray;
	}
}
