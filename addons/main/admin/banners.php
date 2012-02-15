<?php
/**
 * Project: SigGen - Signature and Avatar Generator for WoWRoster
 * File: /admin/index.php
 *
 * @link http://www.wowroster.net
 * @license    http://www.gnu.org/licenses/gpl.html   Licensed under the GNU General Public License v3.
 * @author Joshua Clark
 * @version $Id$
 * @copyright 2005-2011 Joshua Clark
 * @package SigGen
 * @filesource
 */

// Bad monkey! You can view this directly. And you are stupid for trying. HA HA, take that!
if ( !defined('IN_ROSTER') )
{
    exit('Detected invalid access to this file!');
}


if( isset( $_POST['op'] ) && $_POST['op'] == 'process' )
{
	switch ( $_POST['type'] )
	{
		case 'activate':
			$query = "UPDATE `" . $roster->db->table('banners',$addon['basename']) . "` SET `b_active` = '1' WHERE `id` = '".$_POST['id']."';";
			$roster->db->query($query);
			break;

		case 'deactivate':
			$query = "UPDATE `" . $roster->db->table('banners',$addon['basename']) . "` SET `b_active` = '0' WHERE `id` = '".$_POST['id']."';";
			$roster->db->query($query);
			break;
		case 'delete':
		
			$dir = $addon['image_path'];
			$filename = $_POST['image'];
			$delete = $_POST['id'];
			if( file_exists($dir.$filename) )
			{
				if( unlink($dir.$filename))
				{
					$roster->set_message( '<span class="green">'.$filename.'</span>: <span class="red">Deleted</span>' );
					$roster->db->query("DELETE FROM `".$roster->db->table('banners',$addon['basename'])."` WHERE id='".$delete."' ");
				}
				else
				{
					$roster->set_message( '<span class="red">'.$filename.': Could not be deleted</span>' );
				}
			}
			else
			{
				$roster->set_message( '<span class="red">File not found!</span><br>--['.$dir.$filename.']--<br>Removing SQL entry' );
				$roster->db->query("DELETE FROM `".$roster->db->table('banners',$addon['basename'])."` WHERE id='".$delete."' ");
			}
			
			break;

		default:
		break;
	}
}

/*
// ----[ Include export settings box ]-----------------------
ob_start();
include_once( SIGGEN_DIR . 'templates/sc_export.tpl' );
$body .= ob_get_contents();
ob_end_clean();
*/
//$body = 'banner upload';
$query = "SELECT * FROM `" . $roster->db->table('banners',$addon['basename']) . "` "
	. "ORDER BY `id` ASC;";

$result = $roster->db->query($query);

while( $row = $roster->db->fetch($result) )
{
	$roster->tpl->assign_block_vars('banner_row',array(
		'ROW_CLASS' => $roster->switch_row_class(),
		'B_TITLE' 	=> $row['b_title'],
		'B_DESC' 	=> $row['b_desc'],
		'B_ACTIVE' 	=> $row['b_active'],
		'B_ID' 		=> $row['id'],
		'B_IMG'		=> $row['b_image'],
		'B_ACTIVEI' => ( $row['b_active'] == 1 ? 'green' : 'red'),
		'B_ACTIVET'	=> ( $row['b_active'] == 1 ? 'Active' : 'Inactive'),
		'B_ACTIVEOP'=> ( $row['b_active'] == 1 ? 'deactivate' : 'activate'),
		'B_IMAGE' 	=> $addon['url_path'].'images/'.$row['b_image'],
		)
	);
}

$roster->tpl->set_handle('banner',$addon['basename'] . '/banners.html');

$body .= $roster->tpl->fetch('banner');


/**
 * Make our menu from the config api
 */
// ----[ Set the tablename and create the config class ]----
include(ROSTER_LIB . 'config.lib.php');
$config = new roster_config( $roster->db->table('addon_config'), '`addon_id` = "' . $addon['addon_id'] . '"' );

// ----[ Get configuration data ]---------------------------
$config->getConfigData();

// ----[ Build the page items using lib functions ]---------
$menu .= $config->buildConfigMenu('rostercp-addon-' . $addon['basename']);