<?php
/**
 * WoWRoster.net WoWRoster
 *
 * LICENSE: Licensed under the Creative Commons
 *          "Attribution-NonCommercial-ShareAlike 2.5" license
 *
 * @copyright  2002-2007 WoWRoster.net
 * @license    http://creativecommons.org/licenses/by-nc-sa/2.5   Creative Commons "Attribution-NonCommercial-ShareAlike 2.5"
 * @version    SVN: $Id$
 * @link       http://www.wowroster.net
 * @package    News
*/

include( $addon['dir'] . 'template' . DIR_SEP . 'template.php' );

// Display news
$query = "SELECT * "
		. "FROM `" . $roster->db->table('comments','news') . "` news "
		. "WHERE `comment_id` = '" . $_GET['id'] . "';";

$result = $roster->db->query($query);

$comment = $roster->db->fetch($result);

include_template( 'comment_edit.tpl', $comment );