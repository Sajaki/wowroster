<?php
/******************************
 * WoWRoster.net  Roster
 * Copyright 2002-2006
 * Licensed under the Creative Commons
 * "Attribution-NonCommercial-ShareAlike 2.5" license
 *
 * Short summary
 *  http://creativecommons.org/licenses/by-nc-sa/2.5/
 *
 * Full license information
 *  http://creativecommons.org/licenses/by-nc-sa/2.5/legalcode
 * -----------------------------
 *
 * $Id$
 *
 ******************************/

if ( !defined('ROSTER_INSTALLED') )
{
    exit('Detected invalid access to this file!');
}

class memberslist
{
	// Update messages
	var $messages = '';

	// Addon data object, recieved in constructor
	var $data;

	// LUA upload files accepted. We don't use any.
	var $files = array();

	// Character data cache
	var $chars = array();

	/**
	 * Constructor
	 *
	 * @param array $data
	 *		Addon data object
	 */
	function memberslist($data)
	{
		global $wowdb;

		$this->data = $data;
		$addon = $this->data;

		include_once($this->data['conf_file']);
	}

	/**
	 * Resets addon messages
	 */
	function reset_messages()
	{
		$this->messages = 'SortMember';
	}

	/**
	 * Guild trigger, the regex-based alt detection
	 *
	 * @param array $char
	 *		CP.lua guild member data
	 * @param int $member_id
	 * 		Member ID
	 */
	function guild($char, $member_id)
	{
		global $wowdb, $roster;

		// --[ Check if this update type is enabled ]--
		if( !( $this->data['config']['update_type'] & 1 ) )
		{
			return true;
		}

		// --[ Fetch full member data ]--
		$query =
			"SELECT `alt`.*, `member`.`name` ".
			"FROM `".ROSTER_MEMBERSTABLE."` member ".
				"LEFT JOIN `".ROSTER_ALT_TABLE."` alt ".
					"ON `alt`.`member_id` = `member`.`member_id` ".
			"WHERE `member`.`member_id` = '".$member_id."';";

		$result = $wowdb->query( $query );

		if ( !$result )
		{
			$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
			return false;
		}

		if ( $row = $wowdb->fetch_array( $result )) {
			// Check manual record
			if ( $row['alt_type'] & 0x8 ) {
				$wowdb->free_result( $result );
				$this->messages .= " - <span style='color:yellow;'>Manual entry</span><br/>\n";
				return true;
			}
			else
			{
				$wowdb->free_result( $result );
				$member_name = $row['name'];
			}
		}
		else
		{
			$wowdb->free_result( $result );
			$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
			return false;
		}

		// --[ Use regex to parse the main name ]--
		$matchfield = isset($char[$this->data['config']['getmain_field']])?$char[$this->data['config']['getmain_field']]:'';
		if(preg_match($this->data['config']['getmain_regex'], $matchfield, $regs))
		{
			$main_name = $regs[$this->data['config']['getmain_match']]; // We have a regex result.
			$this->messages .= " - <span style='color:green;'>Main: $main_name</span>\n";
		}
		else if($this->data['config']['defmain'])
		{
			$main_name = $member_name;			// No regex result; assume the character is a main
			$this->messages .= " - <span style='color:yellow;'>No main match</span>\n";
		}
		else
		{
			$main_name = '';				// No regex result; assume the character is mainless alt
			$this->messages .= " - <span style='color:yellow;'>No main match</span>\n";
		}

		// If the main name is equal to this config field then this char is a main, and we should set the $main_name accordingly
		if($main_name == $this->data['config']['getmain_main'])
		{
			$main_name = $member_name;
		}


		// --[ Get the main's member ID. We handle the 2 easy cases (Main and mainless alt) first ]--
		if ( $main_name == $member_name ) {
			$this->messages .= " - <span style='color:green;'>Main</span>\n";
			$main_id = $member_id;

			// --[ Look up if there are alts for this main ]--
			$query =
				"SELECT COUNT(member_id) ".
				" FROM `".ROSTER_ALT_TABLE."` as `alts`".
				" WHERE `alts`.`main_id`=".$member_id;

			$result = $wowdb->query( $query );

			if ( !$result )
			{
				$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
				return false;
			}

			$row = mysql_fetch_array( $result );

			if ($row[0] == 1) {
				$alt_type = ALTMONITOR_MAIN_NO_ALTS;
				$this->messages .= " - <span style='color:green;'>No alts</span>\n";
			}
			else {
				$alt_type = ALTMONITOR_MAIN_ALTS;
				$this->messages .= " - <span style='color:green;'>With alts</span>\n";
			}
		}
		elseif ( $main_name == '' ) {
			$this->messages .= " - <span style='color:red;'>Mainless alt</span>\n";
			$main_id = 0;
			$alt_type = ALTMONITOR_ALT_NO_MAIN;
		}
		else {
			$this->messages .= " - <span style='color:green;'>Alt of $main_name</span>\n";
			// --[ Get the main's member ID ]--
			$query =
				"SELECT `members`.`member_id`, `members`.`name`".
				" FROM `".ROSTER_MEMBERSTABLE."` as `members`".
				" WHERE `members`.`name`='".$main_name."'";

			$result = $wowdb->query( $query );

			if ( !$result )
			{
				$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
				return false;
			}

			if ( $row = $wowdb->fetch_array( $result )) {
				$main_id = $row['member_id'];
				$wowdb->free_result( $result );

				// --[ Alt of alt check ]--
				if ( $this->data['config']['altofalt'] == 'leave' ) {
					$alt_type = ALTMONITOR_ALT_WITH_MAIN;	// Don't check if we're allowing alt of alt in the database
				}
				else {
					$query =			// Lookup main's alt_type
						"SELECT `member_id`, `main_id`, `alt_type`".
						" FROM `".ROSTER_ALT_TABLE."`".
						" WHERE `member_id`=".$main_id;

					$result = $wowdb->query( $query );

					if ( !$result )
					{
						$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
						return false;
					}

					if ( $row = $wowdb->fetch_array( $result )) {
						if ( ( $row['alt_type'] & 2 ) == 0 ) { // Alt of main
							$alt_type = ALTMONITOR_ALT_WITH_MAIN;
							$this->messages .= " - <span style='color:green;'>Alt of Main</span>\n";
						}
						elseif ( $this->data['config']['altofalt'] == 'main' ) {
							// The main is an alt so the member is being made a main
							$this->messages .= " - <span style='color:red;'>Alt of Alt</span>\n";

							$main_id = $member_id;

							// --[ Look up if there are alts for this main ]--
							$query =
								"SELECT COUNT(member_id) ".
								" FROM `".ROSTER_ALT_TABLE."` as `alts`".
								" WHERE `alts`.`main_id`=".$member_id;

							$result = $wowdb->query( $query );

							if ( !$result )
							{
								$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
								return false;
							}

							$row = $wowdb->fetch_array( $result );

							if ($row[0] == 1) {
								$alt_type = ALTMONITOR_MAIN_NO_ALTS;
								$this->messages .= " - <span style='color:red;'>Main without alts</span>\n";
							}
							else {
								$alt_type = ALTMONITOR_MAIN_ALTS;
								$this->messages .= " - <span style='color:red;'>Main with alts </span>\n";
							}
						}
						elseif ( $this->data['config']['altofalt'] == 'alt' ) {
							$main_id = 0;
							$alt_type = ALTMONITOR_ALT_NO_MAIN;
							$this->messages .= " - <span style='color:red;'>Alt of Alt</span>\n";
							$this->messages .= " - <span style='color:red;'>Mainless alt</span>\n";
						}
						else {
							$alt_type = $row['alt_type'] & 3; // don't accidentically set this to manual
							$main_id = $row['main_id'];
						}
					}
					else {
						$this->messages .= " - <span style='color:red;'>Mainless alt</span>\n";
						$alt_type = ALTMONITOR_ALT_NO_MAIN;
					}
				}
			}
			else
			{
				$this->messages .= " - <span style='color:red;'>Invalid main</span>\n";
				if($this->data['config']['invmain'])
				{
					$this->messages .= " - <span style='color:green;'>Main</span>\n";

					// --[ Main name invalid, so we're making this a main ]--
					$main_id = $member_id;

					// --[ Look up if there are alts for this main ]--
					$query =
						"SELECT COUNT(member_id) ".
						" FROM `".ROSTER_ALT_TABLE."` as `alts`".
						" WHERE `alts`.`main_id`=".$member_id;

					$result = $wowdb->query( $query );

					if ( !$result )
					{
						$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
						return false;
					}

					$row = $wowdb->fetch_array( $result );

					if ($row[0] == 1) {
						$alt_type = ALTMONITOR_MAIN_NO_ALTS;
						$this->messages .= " - <span style='color:green;'>Main without alts</span>\n";
					}
					else {
						$alt_type = ALTMONITOR_MAIN_ALTS;
						$this->messages .= "<span style='color:green;'>Main with alts</span>\n";
					}
				}
				else
				{
					$main_id = 0;			// Invalid regex result; assume the character is mainless alt
					$alt_type = ALTMONITOR_ALT_NO_MAIN;
					$this->messages .= " - <span style='color:red;'>Mainless alt</span>\n";
				}

				$wowdb->free_result( $result );
			}
		}


		// -[ Start DB update code ]-
		$query = "SELECT `member_id` FROM `".ROSTER_ALT_TABLE."` WHERE `member_id`='$member_id'";

		$result = $wowdb->query( $query );

		if ( !$result )
		{
			$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
			return false;
		}

		$update = $wowdb->num_rows( $result ) == 1;
		$wowdb->free_result($result);

		$wowdb->reset_values();

		$wowdb->add_value( 'member_id', $member_id );

		$wowdb->add_value( 'main_id', $main_id );

		$wowdb->add_value( 'alt_type', $alt_type );

		if( $update )
			$querystr = "UPDATE `".ROSTER_ALT_TABLE."` SET ".$wowdb->assignstr." WHERE `member_id` = '$member_id'";
		else
			$querystr = "INSERT INTO `".ROSTER_ALT_TABLE."` SET ".$wowdb->assignstr;

		$result = $wowdb->query( $querystr );

		if ( !$result )
		{
			$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
			return false;
		}

		$this->messages .= '<br/>';
		return true;
	}

	/**
	 * Guild_post trigger: throwing away the old records
	 *
	 * @param array $guild
	 *		CP.lua guild data
	 */
	function guild_post($guild)
	{
		global $wowdb, $roster;

		// --[ Check if this update type is enables ]--
		if(( $this->data['config']['update_type'] & 1 ) == 0 )
		{
			return true;
		}

		$query = "DELETE `".ROSTER_ALT_TABLE."` ".
			"FROM `".ROSTER_ALT_TABLE."` ".
			"LEFT JOIN `".ROSTER_MEMBERSTABLE."` USING (`member_id`) ".
			"WHERE `".ROSTER_MEMBERSTABLE."`.`member_id` IS NULL ";

		if( $wowdb->query($query) )
		{
			$this->messages .= ' - '.$wowdb->affected_rows().' records without matching member records deleted';
		}
		else
		{
			$this->messages .= ' - <span style="color:red;">Old records not deleted. MySQL said: '.$wowdb->error().'</span><br/>'."\n";
			return false;
		}

		return true;
	}

	/**
	 * Char trigger: add the member record to the local data array
	 *
	 * @param array $char
	 *		CP.lua character data
	 * @param int $member_id
	 *		Member ID
	 */
	function char($char, $member_id)
	{
		global $wowdb, $roster;

		// --[ Check if this update type is enables ]--
		if(( $this->data['config']['update_type'] & 2 ) == 0 )
		{
			return true;
		}

		// --[ Fetch full member data ]--
		$query =
			"SELECT `alt`.*, `member`.`name` ".
			"FROM `".ROSTER_MEMBERSTABLE."` member ".
				"LEFT JOIN `".ROSTER_ALT_TABLE."` alt ".
					"ON `alt`.`member_id` = `member`.`member_id` ".
			"WHERE `member`.`member_id` = '".$member_id."';";

		$result = $wowdb->query( $query );

		if ( !$result )
		{
			$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'. MySQL said:<br/>'.$wowdb->error().'</span><br/>'."\n";
			return false;
		}

		if ( $row = $wowdb->fetch_array( $result )) {
			// Check manual record
			if ( $row['alt_type'] & 0x8 ) {
				$wowdb->free_result( $result );
				$this->messages .= " - <span style='color:yellow;'>Manual entry</span><br/>\n";
				return true;
			}
			else
			{
				$wowdb->free_result( $result );
				$member_name = $row['name'];
			}
		}
		else
		{
			$wowdb->free_result( $result );
			$this->messages .= ' - <span style="color:red;">'.$member_name.' not updated, failed at line '.__LINE__.'</span><br/>'."\n";
			return false;
		}

		// --[ Add record to the cache of chars we'll be updating ]--
		$this->chars[$member_id] = $char;
		$this->chars[$member_id]['main_id'] = $row['main_id'];
		$this->chars[$member_id]['alt_type'] = $row['alt_type'];

		$this->messages = substr($this->messages,0,-10);
		return true;
	}

	/**
	 * Char_post trigger: does the actual update.
	 *
	 * @param array $chars
	 *		CP.lua characters data
	 */
	function char_post($chars)
	{
		global $wowdb, $roster;

		// --[ Check if this update type is enables ]--
		if(( $this->data['config']['update_type'] & 2 ) == 0 )
		{
			return true;
		}

		if( empty($this->chars) ) { return true; }

		// Decide upon a main: Highest leveled among those with highest guild rank
		$maxrank = 11;
		$maxlevel = 0;

		foreach($this->chars as $char)
		{
			if( $char['Guild']['Rank'] < $maxrank )
			{
				$maxrank = $char['Guild']['Rank'];
			}
		}

		foreach($this->chars as $member_id => $char)
		{
			if( $char['Guild']['Rank'] == $maxrank && $char['Level'] > $maxlevel )
			{
				$maxlevel = $char['Level'];
				$mainid = $member_id;
			}
		}

		// And the update code
		$inclause = implode(',',array_diff(array_keys($this->chars),array($mainid)));

		if( empty($inclause) )
		{
			$query = "UPDATE `".ROSTER_ALT_TABLE."` SET `main_id` = '".$mainid."', `alt_type` = '".ALTMONITOR_MAIN_MANUAL_NO_ALTS."' WHERE `member_id` = '".$mainid."'";
			if( $wowdb->query($query) )
			{
				$this->messages .= ' - '.$this->chars[$mainid]['name'].' written as main without alts<br/>'."\n";
			}
			else
			{
				$this->messages .= ' - <span style="color:red;">Main not written. MySQL said: '.$wowdb->error().'</span><br/>'."\n";
				return false;
			}
		}
		else
		{
			$query = "UPDATE `".ROSTER_ALT_TABLE."` SET `main_id` = '".$mainid."', `alt_type` = '".ALTMONITOR_ALT_MANUAL_WITH_MAIN."' WHERE `member_id` IN (".$inclause.")";
			if( $wowdb->query($query) )
			{
				$this->messages .= ' - '.$wowdb->affected_rows().' alts added to main '.$this->chars[$mainid]['Name'];
			}
			else
			{
				$this->messages .= ' - <span style="color:red;">Alts not written. MySQL said: '.$wowdb->error().'</span><br/>'."\n";
				return false;
			}

			$query = "UPDATE `".ROSTER_ALT_TABLE."` SET `main_id` = '".$mainid."', `alt_type` = '".ALTMONITOR_MAIN_MANUAL_WITH_ALTS."' WHERE `member_id` = '".$mainid."'";
			if( $wowdb->query($query) )
			{
				$this->messages .= ' - Main written<br/>'."\n";
			}
			else
			{
				$this->messages .= ' - <span style="color:red;">Main not written. MySQL said: '.$wowdb->error().'</span><br/>'."\n";
				return false;
			}
		}

		return true;
	}
}