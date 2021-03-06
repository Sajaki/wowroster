<?php
/**
 * WoWRoster.net WoWRoster
 *
 * Roster diagnostics and info
 *
 *
 * @copyright  2002-2011 WoWRoster.net
 * @license    http://www.gnu.org/licenses/gpl.html   Licensed under the GNU General Public License v3.
 * @version    SVN: $Id$
 * @link       http://www.wowroster.net
 * @since      File available since Release 1.7.0
 * @package    WoWRoster
 * @subpackage RosterDiag
*/

if( !defined('IN_ROSTER') )
{
	exit('Detected invalid access to this file!');
}

//error_reporting(E_ALL);

//!!!!!!!!!!!!!// Developer Config //!!!!!!!!!!!!!//
// As a NON-Developer, please do not modify any content of this file, or the version check might fail!!!

// Ignored Directories
$ignored_dirs = array('.', '..', 'SVN', '.svn', 'Interface', 'cache');

// Files to check with extension:
$extensions = array('php', 'inc', 'css', 'js', 'tpl', 'htm', 'html', 'jpg', 'gif', 'png', 'sql', 'txt');

// Files to ignore
$ignored_files = array('conf.php', '.htaccess', 'roster_addons_go_here.txt');

// Do we want to check the SubDirs ?? I think we do :)
$subdirectories = 1;

// Set the severity information
$problemsev['description'] = 9;
$problemsev['revisiongreater'] = 4;
$problemsev['revisionsmaller'] = 9;
$problemsev['dateolder'] = 9;
$problemsev['dateyounger'] = 4;
$problemsev['author'] = 8;
$problemsev['MD5'] = 0;
$problemsev['MD5binary'] = 7;
$problemsev['nosvn'] = 2;
$problemsev['nolocal'] = 3;
$problemsev['unknown'] = 1;

$severity[0] = array('style' => 'sgreen',  'color' => '#12C312', 'weight' => 0,  'severityname' => 'No Issues');
$severity[1] = array('style' => 'sgray',   'color' => '#AFAFAF', 'weight' => 0,  'severityname' => 'Unknown');
$severity[2] = array('style' => 'sgray',   'color' => '#AFAFAF', 'weight' => 0,  'severityname' => 'Not in SVN');
$severity[3] = array('style' => 'sgray',   'color' => '#AFAFAF', 'weight' => 0,  'severityname' => 'Not in Local');
$severity[4] = array('style' => 'sblue',   'color' => '#312CF8', 'weight' => 0,  'severityname' => 'Newer Files');
$severity[5] = array('style' => 'sblue',   'color' => '#312CF8', 'weight' => 1,  'severityname' => 'Initial');
$severity[6] = array('style' => 'spurple', 'color' => '#E920CF', 'weight' => 1,  'severityname' => 'Strange');
$severity[7] = array('style' => 'syellow', 'color' => '#F1B10E', 'weight' => 3,  'severityname' => 'Minor');
$severity[8] = array('style' => 'sorange', 'color' => '#EE870D', 'weight' => 7,  'severityname' => 'Major');
$severity[9] = array('style' => 'sred',    'color' => '#FF0000', 'weight' => 15, 'severityname' => 'Critical');


$rollups[] = array('rollup' => 2,  'severity' => 7);
$rollups[] = array('rollup' => 5,  'severity' => 8);
$rollups[] = array('rollup' => 14, 'severity' => 9);

$newerfilesev = 4;

$totalseverity = 0;


//!!!!!!!!!!!!!// Do NOT edit anything below //!!!!!!!!!!!!!//
// Set some characters we will use for exploding the data streams
$explode = '*|*';
$break = "\n";

// Make an array to hold the direcory information
$directories = array('.' => array('localfiles' => 0, 'remotefiles' => 0, 'severity' => 0));

// Make an array to hold the local and, if applicable, remote file versioning information
$files = array();

// Get the $directories and fill the array $directories
if ($subdirectories)
{
	GrabAllLocalDirectories('.');
}

// Get the $files / versioning info for each $directories and fill the array $files
foreach ($directories as $directory => $filecount)
{
	// Grab all local $files and store the information into the array $files
	GrabLocalVersions($directory);
}

// Get the REMOTE $files / versioning info for each REMOTE $directories and fill the array $files
//GrabRemoteVersions();


foreach ($files as $directory => $filedata)
{
	$directories[$directory] = count($filedata);
}

//DisplayTheStuffTemp();

/**
 * Grab all directories and subdirectories for directory $dir and shove them into the global array $directories
 *
 * @param string $dir
 */
function GrabAllLocalDirectories($dir)
{
	global $directories;

	if ($handle = @opendir($dir)) {
		while ($filename = readdir($handle))
		{
			$directory = $dir . '/' . $filename;
			if(is_dir($directory) && CheckDirectory($filename))
			{
				$directories[$directory] = array('localfiles' => 0, 'remotefiles' => 0, 'severity' => 0);
				GrabAllLocalDirectories($directory);
			}
		}
		closedir($handle);
	}
}

/**
 * This function will determine all version of all files in the current directory
 * and will fill the $versions array with this data
 *
 * @param string $directory
 */
function GrabLocalVersions($directory)
{
	global $directories;

	if ($handle = @opendir($directory))
	{
		while ($filename = readdir($handle))
		{
			if(isset($filename) && !is_dir($directory . '/' . $filename) && CheckExtension($filename))
			{
				// Increase the filecounter for this directory
				$directories[$directory]['localfiles']++;
				// Get the file versioning info and store it into the array
				GetFileVersionInfo($directory, $filename);
			}
		}
		closedir($handle);
	}
}

/**
 * Check the file against the $extension array
 *
 * @param string $filename
 * @return int
 */
function CheckExtension($filename)
{
	global $extensions, $ignored_files;

	$returnvalue = 0;
	if ($filename == '.' || $filename == '..' || $filename == 'version_match.php')
	{
		$returnvalue = 0;
	}
	else
	{
		// Check the extension
		$fileextension = pathinfo($filename, PATHINFO_EXTENSION);


		if (in_array($fileextension, $extensions) && !in_array($filename, $ignored_files))
		{
			$returnvalue = 1;
		}
		else
		{
			$returnvalue = 0;
		}
	}
	return $returnvalue;
}

/**
 * Check the file against the $extension array
 *
 * @param string $dirname
 * @return int
 */
function CheckDirectory($dirname)
{
	global $ignored_dirs;

	$returnvalue = 0;

	if (in_array($dirname, $ignored_dirs))
	{
		$returnvalue = 0;
	}
	else
	{
		$returnvalue = 1;
	}
	return $returnvalue;
}

/**
 * Grab all the versioning data regarding the $file inside the $directory
 *
 * @param string $directory
 * @param string $file
 */
function GetFileVersionInfo($directory, $file)
{
	global $files;

	$filefullpath = $directory . '/' . $file;
	// Read the first 2k of the file, which should be enough to grab the $fileheader
	$fp = @fopen($directory . '/' . $file, 'rb');
	if($fp) {
		$fileheader = fread($fp, 2048);
		fclose($fp);
	}

	$files[$directory][$file]['local']['versionFile'] = $file;
	if (!$files[$directory][$file]['local']['versionMD5'] = md5_file($filefullpath)) {
		$files[$directory][$file]['local']['versionMD5'] = 0;
	}
	// Example of the SVN $Id string:
	//   * $Id$
	//         ~|Descr            |Ver |Date                |Author|~
	if (check_if_image($file))
	{
		$files[$directory][$file]['local']['versionDesc'] = 'N/A';
		$files[$directory][$file]['local']['versionRev'] = 'N/A';
		$files[$directory][$file]['local']['versionAuthor'] = 'N/A';
		if (!$files[$directory][$file]['local']['versionDate'] = filemtime($filefullpath)) {
			$files[$directory][$file]['local']['versionDate'] = 0;
		}
	}
	else
	{
		// String to match in SVN: $Id$
		if ((preg_match('~\s\$Id\:\s(.+?)\s(.+?)\s(.+?)\s(.+?)\s(.+?)\s\$~', $fileheader, $local_version) > 0) || (preg_match('~\s\$Id\:\s(.+?)\,v\s(.+?)\s(.+?)\s(.+?)\s(.+?)\sExp\s\$~', $fileheader, $local_version) > 0) )
		{
			$files[$directory][$file]['local']['versionDesc'] = $local_version[1];
			$files[$directory][$file]['local']['versionRev'] = $local_version[2];

			$files[$directory][$file]['local']['versionDate'] = check_date_time($local_version[3], $local_version[4]);

			$tmpdate = explode("/", $local_version[3]);
			$tmptime = explode(":", $local_version[4]);

//			$files[$directory][$file]['local']['versionDate'] = gmmktime($tmptime[0], $tmptime[1], $tmptime[2], $tmpdate[1], $tmpdate[2], $tmpdate[0]);
			$files[$directory][$file]['local']['versionAuthor'] = $local_version[5];
		} else {
			// Check if we have a version entry for the Date string and also capture the brief indication of which addon this is
			if (preg_match('~\$versions\[\'versionDate\'\]\[\'(.+?)\'\]\s\=\s\'\$Date\:\s(.+?)\s\$\'\;~', $fileheader, $local_version) == 1) {
				$files[$directory][$file]['local']['versionDesc'] = $local_version[1];


				$tmpdatetime = explode(" ", $local_version[2]);
				$tmpdate = explode("/", $tmpdatetime[0]);
				if (isset($tmpdatetime[1]))
				{
					$tmptime = explode(":", $tmpdatetime[1]);
					if (is_int($tmptime[0]))
					{
						$files[$directory][$file]['local']['versionDate'] = gmmktime($tmptime[0], $tmptime[1], $tmptime[2], $tmpdate[1], $tmpdate[2], $tmpdate[0]);
					}
				}

			} else {
				$files[$directory][$file]['local']['versionDesc'] = 0;
				$files[$directory][$file]['local']['versionDate'] = 0;
			}

			// Check if we have a version entry for the Revision string
			if (preg_match('~\$versions\[\'versionRev\'\]\[\'(.+?)\'\]\s\=\s\'\$Revision\:\s(.+?)\s\$\'\;~', $fileheader, $local_version) == 1)	{
				$files[$directory][$file]['local']['versionRev'] = $local_version[2];
			} else {
				$files[$directory][$file]['local']['versionRev'] = 0;
			}

			// Check if we have a version entry for the Author string
			if (preg_match('~\$versions\[\'versionAuthor\'\]\[\'(.+?)\'\]\s\=\s\'\$Author\:\s(.+?)\$\'\;~', $fileheader, $local_version) == 1) {
				$files[$directory][$file]['local']['versionAuthor'] = $local_version[2];
			} else {
				$files[$directory][$file]['local']['versionAuthor'] = 0;
			}
		}
	}
}

/**
 * Grab all the remote versioning data
 *
 * @return bool False on failure
 */
function GrabRemoteVersions()
{
	global $directories, $files, $break, $explode;

	// Execute the addon_versioncheck.php script in the SVN remote site
	$contents = urlgrabber(ROSTER_SVNREMOTE);

	if( $contents !== false )
	{
		// Break the header into lines
		$remoteversions = explode($break, $contents);
		foreach ($remoteversions as $remoteversion)
		{
			// Break the line into strings
			$remoteversion = explode($explode, $remoteversion);

			// Insert the file info into the $files array
			if (isset($remoteversion[1]))
			{
				$directory = $remoteversion[0];
				// Check if the directory existed on the local system. If not, declare the directory inside the $directories array.
				if (!isset($directories[$directory]))
				{
					$directories[$directory] = array('localfiles' => 0, 'remotefiles' => 0, 'severity' => 0);
				}
				$filename = $remoteversion[1];
				$files[$directory][$filename]['remote']['versionFile'] = $filename;
				$files[$directory][$filename]['remote']['versionDesc'] = $remoteversion[2];
				$files[$directory][$filename]['remote']['versionRev'] = $remoteversion[3];
				$files[$directory][$filename]['remote']['versionDate'] = $remoteversion[4];
				$files[$directory][$filename]['remote']['versionAuthor'] = $remoteversion[5];
				$files[$directory][$filename]['remote']['versionMD5'] = $remoteversion[6];
			}
		}
	}
	else
	{
		return false;
	}
}

/**
 * Verify version info
 *
 */
function VerifyVersions()
{
	global $files, $directories, $problemsev, $severity, $rollups, $totalrollup, $totalseverity, $newerfilesev;

	// Process verification for all directories, Local and SVN
	foreach ($files as $directory => $filedata)
	{
		// Initialize the Directory severity
		$files[$directory]['severity'] = 0;
		// Initialize the File tooltip
		$files[$directory]['tooltip'] = '';
		$files[$directory]['newer'] = 0;
		$nosvn = 0;
		$nolocal = 0;



		foreach ($filedata as $filename => $file)
		{
			// Initialize the File severity
			$files[$directory][$filename]['severity'] = 0;
			// Initialize the File tooltip
			$files[$directory][$filename]['tooltip'] = '';
			$files[$directory][$filename]['rogue'] = 0;
			$files[$directory][$filename]['update'] = 0;
			$files[$directory][$filename]['missing'] = 0;
			$files[$directory][$filename]['diff'] = 0;
			$files[$directory][$filename]['newer'] = 0;

			// Check if Both Local and SVN files are present
			if (isset($file['local']) && isset($file['remote']))
			{
				// Check if the local description matches the SVN description
				if (strcmp($file['local']['versionDesc'], $file['remote']['versionDesc']))
				{
					$files[$directory][$filename]['severity'] += $severity[$problemsev['description']]['weight'];
					$files[$directory][$filename]['tooltip'] .= 'Local Description does NOT match with SVN<br />';
				}
				// Check if the local version matches the SVN version
				if (version_compare($file['local']['versionRev'], $file['remote']['versionRev']) < 0)
				{
					$files[$directory][$filename]['severity'] += $severity[$problemsev['revisiongreater']]['weight'];
					$files[$directory][$filename]['newer'] = 1;
					$files[$directory][$filename]['tooltip'] .= 'Local Version: ' . $file['local']['versionRev'] . ' is HIGHER than SVN Version: ' . $file['remote']['versionRev'] . '<br />';
					$files[$directory][$filename]['rev'] = $file['local']['versionRev'] . ' < ' . $file['remote']['versionRev'];
					$files[$directory][$filename]['update'] = 1;
					$files[$directory][$filename]['diff'] = 1;
				}
				elseif (version_compare($file['local']['versionRev'], $file['remote']['versionRev']) > 0)
				{
					$files[$directory][$filename]['severity'] += $severity[$problemsev['revisiongreater']]['weight'];
					$files[$directory][$filename]['tooltip'] .= 'Local Version: ' . $file['local']['versionRev'] . ' is HIGHER than SVN Version: ' . $file['remote']['versionRev'] . '<br />';
					$files[$directory][$filename]['rev'] = $file['local']['versionRev'] . ' > ' . $file['remote']['versionRev'];
					$files[$directory][$filename]['diff'] = 1;
				}
				elseif (version_compare($file['local']['versionRev'], $file['remote']['versionRev']) == 0)
				{
					$files[$directory][$filename]['rev'] = $file['local']['versionRev'];
				}

				// Check if the local date matches the SVN date
				if (($file['local']['versionDate'] < $file['remote']['versionDate']) && !check_if_image($filename))
				{
					$files[$directory][$filename]['severity'] += $severity[$problemsev['dateolder']]['weight'];
					$files[$directory][$filename]['tooltip'] .= 'Local Date: ' . gmdate('Y/m/d H:i', $file['local']['versionDate']) . ' is OLDER than SVN Date: ' . gmdate('Y/m/d H:i', $file['remote']['versionDate']) . '<br />';
					$files[$directory][$filename]['date'] = gmdate('Y/m/d H:i', $file['local']['versionDate']) . ' < ' . gmdate('Y/m/d H:i', $file['remote']['versionDate']);
					$files[$directory][$filename]['update'] = 1;
					$files[$directory][$filename]['diff'] = 1;
				}
				elseif (($file['local']['versionDate'] > $file['remote']['versionDate']) && !check_if_image($filename))
				{
					$files[$directory][$filename]['severity'] += $severity[$problemsev['dateyounger']]['weight'];
					$files[$directory][$filename]['newer'] = 1;
					$files[$directory][$filename]['tooltip'] .= 'Local Date: ' . gmdate('Y/m/d H:i', $file['local']['versionDate']) . ' is NEWER than SVN Date: ' . gmdate('Y/m/d H:i', $file['remote']['versionDate']) . '<br />';
					$files[$directory][$filename]['date'] = gmdate('Y/m/d H:i', $file['local']['versionDate']) . ' > ' . gmdate('Y/m/d H:i', $file['remote']['versionDate']);
					$files[$directory][$filename]['diff'] = 1;
				}
				elseif (($file['local']['versionDate'] == $file['remote']['versionDate']) || check_if_image($filename))
				{
					$files[$directory][$filename]['date'] = gmdate('Y/m/d H:i', $file['local']['versionDate']);
				}
				// Check if the local author matches the SVN author
				if (strcmp($file['local']['versionAuthor'], $file['remote']['versionAuthor']))
				{
					$files[$directory][$filename]['severity'] += $severity[$problemsev['author']]['weight'];
					$files[$directory][$filename]['tooltip'] .= 'Local Author does NOT match with SVN<br />';
					$files[$directory][$filename]['author'] = preg_replace('/@(.+)/i', '', $file['local']['versionAuthor']) . ' != ' . preg_replace('/@(.+)/i', '', $file['remote']['versionAuthor']);
					$files[$directory][$filename]['diff'] = 1;
				}
				else
				{
					$files[$directory][$filename]['author'] = preg_replace('/@(.+)/i', '', $file['local']['versionAuthor']);
				}
				// Check if the local MD5 matches the SVN MD5
				if (strcmp($file['local']['versionMD5'], $file['remote']['versionMD5']))
				{
					if (check_if_image($filename))
					{
						$files[$directory][$filename]['severity'] += $severity[$problemsev['MD5binary']]['weight'];
						$files[$directory][$filename]['tooltip'] .= 'Local MD5 does not match with SVN<br />';
						$files[$directory][$filename]['update'] = 1;
					}
					else
					{
						$files[$directory][$filename]['severity'] += $severity[$problemsev['MD5']]['weight'];
						$files[$directory][$filename]['tooltip'] .= 'Local MD5 does not match with SVN<br />';
					}
					$files[$directory][$filename]['md5'] = 'MD5 String does NOT match';
					$files[$directory][$filename]['diff'] = 1;
				}
				else
				{
					$files[$directory][$filename]['md5'] = 'MD5 String Matches';
				}
			}
			elseif (isset($file['local']) && !isset($file['remote']))
			{
				$files[$directory][$filename]['severity'] += $severity[$problemsev['nosvn']]['weight'];
				$files[$directory][$filename]['tooltip'] .= 'Local file does not exist in SVN<br />';
				$files[$directory][$filename]['rogue'] = 1;
				$files[$directory][$filename]['rev'] = $file['local']['versionRev'];
				$files[$directory][$filename]['date'] = gmdate('Y/m/d H:i', $file['local']['versionDate']);
				$files[$directory][$filename]['author'] = preg_replace('/@(.+)/i', '', $file['local']['versionAuthor']);
			}
			elseif (!isset($file['local']) && isset($file['remote']))
			{
				$files[$directory][$filename]['severity'] += $severity[$problemsev['nolocal']]['weight'];
				$files[$directory][$filename]['tooltip'] .= 'Local file is missing but is present in SVN<br />';
				$files[$directory][$filename]['update'] = 1;
				$files[$directory][$filename]['missing'] = 1;
			}
			else
			{
				$files[$directory][$filename]['severity'] += $severity[$problemsev['unknown']]['weight'];
				$files[$directory][$filename]['tooltip'] .= 'Unknown Issue<br />';
			}
			$files[$directory][$filename]['rollup'] = 0;
			foreach ($rollups as $rollupkey => $rollup)
			{
				if ($files[$directory][$filename]['severity'] > $rollup['rollup'])
				{
					$files[$directory][$filename]['rollup'] = $rollup['severity'];
				}
			}

			$files[$directory]['severity'] += $files[$directory][$filename]['severity'];

			if (!$files[$directory][$filename]['severity'] && $files[$directory][$filename]['newer'])
			{
				$files[$directory][$filename]['rollup'] = $newerfilesev;
				$files[$directory]['newer'] = 1;
			}

			if ($files[$directory][$filename]['tooltip'] == '' && !$files[$directory][$filename]['severity'] && !$files[$directory][$filename]['rogue'] && !$files[$directory][$filename]['diff'] && !$files[$directory][$filename]['newer'])
			{
				$files[$directory][$filename]['tooltip'] .= 'Local file same as SVN<br />';
			}

			if ($files[$directory][$filename]['rogue'])
			{
				$files[$directory][$filename]['rollup'] = $problemsev['nosvn'];
				$nosvn = 1;
			}

			if ($files[$directory][$filename]['missing'])
			{
				$files[$directory][$filename]['rollup'] = $problemsev['nolocal'];
				$nolocal = 1;
			}

			if ($files[$directory][$filename]['rollup'])
			{
				$files[$directory]['tooltip'] .= 'File: ' . $filename . ' - Severity: ' . $files[$directory][$filename]['rollup'] . '<br />';
			}
		}

		if ($files[$directory]['tooltip'] == '')
		{
			$files[$directory]['tooltip'] = 'No File Version Issues!';
		}

		$files[$directory]['rollup'] = 0;

		foreach ($rollups as $rollupkey => $rollup)
		{
			if ($files[$directory]['severity'] > $rollup['rollup'])
			{
				$files[$directory]['rollup'] = $rollup['severity'];
			}
		}

		if (!$files[$directory]['severity'])
		{
			if ($files[$directory]['newer'])
			{
				$files[$directory]['rollup'] = $newerfilesev;
			}

			if ($nosvn)
			{
				$files[$directory]['rollup'] = $problemsev['nosvn'];
			}

			if ($nolocal)
			{
				$files[$directory]['rollup'] = $problemsev['nolocal'];
			}
		}

		$totalseverity += $files[$directory]['severity'];
	}

	$totalrollup = 0;

	foreach ($rollups as $rollupkey => $rollup)
	{
		if ($files[$directory]['severity'] > $rollup['rollup'])
		{
			$totalrollup = $rollup['severity'];
		}
	}
}


/**
 * Gets the gd_info and formats the output
 *
 * @return string
 */
function describeGDdyn()
{
	$rowstripe = 1;

	if( function_exists('gd_info') )
	{
		$returnVal  = '<table class="border_frame" cellspacing="0" width="100%">' . "\n";
		$returnVal .= "\t<tr>\n\t\t<td class=\"membersRow" . (((++$rowstripe)%2)+1) . "\">GD Status</td>\n\t\t<td class=\"membersRowRight" . ((($rowstripe)%2)+1) . "\"><span class=\"green\">On</span></td>\n\t</tr>\n";

		$info = gd_info();
		$keys = array_keys($info);
		for($i=0; $i<count($keys); $i++)
		{
			if(is_bool($info[$keys[$i]]))
			{
				$returnVal .= "\t<tr>\n\t\t<td class=\"membersRow" . (((++$rowstripe)%2)+1) . "\">" . $keys[$i] . "</td>\n\t\t<td class=\"membersRowRight" . ((($rowstripe)%2)+1) . "\">" . yesNo($info[$keys[$i]]) . "</td>\n\t</tr>\n";
			}
			else
			{
				$returnVal .= "\t<tr>\n\t\t<td class=\"membersRow" . (((++$rowstripe)%2)+1) . "\">" . $keys[$i] . "</td>\n\t\t<td class=\"membersRowRight" . ((($rowstripe)%2)+1) . "\">" . $info[$keys[$i]] . "</td>\n\t</tr>\n";
			}
		}
		$returnVal .= "</table>\n";
	}
	else
	{
		$returnVal  = '<table cellspacing="0" width="100%">' . "\n";
		$returnVal .= "\t<tr>\n\t\t<td class=\"membersRow1\">GD Status</td>\n\t\t<td class=\"membersRowRight1\"><span class=\"red\">Off</span></td>\n\t</tr>\n";
		$returnVal .= "</table>\n";
	}

	return $returnVal;
}

/**
 * Show any config setup errors
 *
 * @return string
 */
function ConfigErrors()
{
	global $roster;

	// Get freetype installation status
	if ( function_exists('gd_info'))
	{
		$gdinfo = gd_info();
		$FreeType = $gdinfo['FreeType Support'];
	}
	else
	{
		$FreeType = 0;
	}

	// Start building error string
	$errors = '';

	// Check GD and Freetype status in PHP config if GD Realm Status option is set
	if ($roster->config['rs_display'] == 'image')
	{
		if( !function_exists('gd_info') )
		{
			$errors .= "Realm Status GD image mode enabled (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;Display Mode = Image) in RosterCP but GD library was not found.<br />Either load the GD extension in PHP or set (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;Display Mode = text) in RosterCP<br />\n";
		}
		if ($FreeType == 0)
		{
			$errors .= "Realm Status GD image mode enabled (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;Display Mode = Image) in RosterCP but FreeType support was not found.<br />Either load the Freetype extension in PHP or set (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;Display Mode = text) in RosterCP<br />\n";
		}
	}
	if ($roster->config['motd_display_mode'] == 1)
	{
		if( !function_exists('gd_info') )
		{
			$errors .= "MOTD GD image mode enabled (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;MOTD Display Mode = on) in RosterCP but GD library was not found.<br />Either load the GD extension in PHP or set (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;MOTD Display Mode = text) in RosterCP<br />\n";
		}
		if ($FreeType == 0)
		{
			$errors .= "MOTD GD image mode enabled (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;MOTD Display Mode = on) in RosterCP but FreeType support was not found.<br />Either load the Freetype extension in PHP or set (RosterCP-&gt;Main Settings-&gt;Realmstatus-&gt;MOTD Display Mode = text) in RosterCP<br />\n";
		}
	}

	if( !empty($errors) )
	{
		$errors = '<span class="red">' . $errors . "</span><br /><br />\n";
	}

	return $errors;
}

/**
 * Determine a on/off value from a bool. true/1=on false/0=off
 *
 * @param bool $bool
 * @return formatted on/off string
 */
function onOff($bool)
{
	if( $bool )
	{
		return '<span class="green">On</span>';
	}
	else
	{
		return '<span class="red">Off</span>';
	}
}

/**
 * Determine a on/off value from a bool. true/1=on false/0=off
 *
 * @param bool $bool
 * @return formatted on/off string
 */
function onOffRev($bool)
{
	if( $bool )
	{
		return '<span class="red">On</span>';
	}
	else
	{
		return '<span class="green">Off</span>';
	}
}

/**
 * Determine a yes/no value from a bool. true/1=yes false/0=no
 *
 * @param bool $bool
 * @return formatted yes/no string
 */
function yesNo($bool)
{
	if($bool)
	{
		return '<span class="green">Yes</span>';
	}
	else
	{
		return '<span class="red">No</span>';
	}
}


/**
 * Writes a file from the SVN copy
 * (Not currently used)
 *
 * @param string $filename
 * @return bool
 */
function downloadsvn($filename)
{
	$file_source = ROSTER_SVNREMOTE . '?getfile=' . $filename . '&mode=full';

	$file_source = str_replace("\r\n","\n",urlgrabber($file_source));
	if ($file_source===false)
	{
		roster_die("[ERROR] Cannot Read File");
	}
	file_writer($filename,$file_source);
	return true;
}


function difffile($old,$new)
{
	// Clean up multiple line breaks
	$old = str_replace("\r\n","\n",$old);
	$new = str_replace("\r\n","\n",$new);

	// Split the source text into arrays of lines
	$t1 = explode("\n",$old);
	$x = array_pop($t1);
	if ($x > '')
	{
		$t1[] = "$x\n\\ No newline at end of file";
	}
	$t2 = explode("\n",$new);
	$x = array_pop($t2);
	if ($x>'')
	{
		$t2[] = "$x\n\\ No newline at end of file";
	}

	// Build a reverse-index array using the line as key and line number as value
	// Don't store blank lines, so they won't be targets of the shortest distance search
	foreach($t1 as $i=>$x)
	{
		if ($x > '')
		{
			$r1[$x][] = $i;
		}
	}
	foreach($t2 as $i=>$x)
	{
		if ($x > '')
		{
			$r2[$x][] = $i;
		}
	}

	// Start at beginning of each list
	$a1 = 0;
	$a2 = 0;
	$actions = array();

	// Walk this loop until we reach the end of one of the lists
	while ($a1 < count($t1) && $a2 < count($t2))
	{
		// If we have a common element, save it and go to the next
		if ($t1[$a1]==$t2[$a2])
		{
			$actions[] = 4;
			$a1++;
			$a2++;
			continue;
		}

		// Otherwise, find the shortest move (Manhattan-distance) from the current location
		$best1 = count($t1);
		$best2 = count($t2);
		$s1=$a1;
		$s2=$a2;
		while (($s1+$s2-$a1-$a2) < ($best1+$best2-$a1-$a2))
		{
			$d = -1;

			if(isset($s2) && isset($t2) && isset($t2[$s2]) && isset($r1) && isset($r1[$t2[$s2]]))
			{
				foreach((array)@$r1[$t2[$s2]] as $n)
				{
					if ($n>=$s1)
					{
						$d=$n;
						break;
					}
				}
			}
			if ($d>=$s1 && ($d+$s2-$a1-$a2) < ($best1+$best2-$a1-$a2))
			{
				$best1=$d; $best2=$s2;
			}
			$d = -1;

			if(isset($s1) && isset($t1) && isset($t1[$s1]) && isset($r2) && isset($r2[$t1[$s1]]))
			{
				foreach ((array)@$r2[$t1[$s1]] as $n)
				{
					if ($n >= $s2)
					{
						$d = $n;
						break;
					}
				}
			}
			if ($d>=$s2 && ($s1+$d-$a1-$a2) < ($best1+$best2-$a1-$a2))
			{
				$best1 = $s1;
				$best2 = $d;
			}
			$s1++;
			$s2++;
		}
		while ($a1<$best1)
		{
			$actions[] = 1;
			$a1++;
		}  // Deleted elements
		while ($a2<$best2)
		{
			$actions[] = 2;
			$a2++;
		}  // Added elements
	}

	// We've reached the end of one list, now walk to the end of the other
	while ($a1<count($t1))
	{
		$actions[] = 1;
		$a1++;
	}  // Deleted elements
	while ($a2<count($t2))
	{
		$actions[] = 2;
		$a2++;
	}  // Added elements

	// And this marks our ending point
	$actions[] = 8;

	// Now, let's follow the path we just took and report the added/deleted elements into $out.
	$op = 0;
	$x0 = $x1 = 0;
	$y0 = $y1 = 0;
	$out = array();
	$outcount = 0;
//	print_r($actions);
	foreach ($actions as $act)
	{
		if ($act == 1)
		{
			$op |= $act;
			$x1++;
			continue;
		}
		if ($act == 2)
		{
			$op |= $act;
			$y1++;
			continue;
		}
		if ($op > 0)
		{
			$xstr = ($x1 == ($x0+1)) ? $x1 : ($x0+1) . "," . $x1;
			$ystr = ($y1 == ($y0+1)) ? $y1 : ($y0+1) . "," . $y1;
			if ($op == 1)
			{
				$out[$outcount]['rownr1'] = $xstr;
				$out[$outcount]['rownr2'] = $y1;
				$out[$outcount]['action'] = 'Deleted';
				$out[$outcount]['color'] = 'red';
			}
			elseif ($op == 3)
			{
				$out[$outcount]['rownr1'] = $xstr;
				$out[$outcount]['rownr2'] = $ystr;
				$out[$outcount]['action'] = 'Changed';
				$out[$outcount]['color'] = 'blue';
			}
			$tmpi = 0;
			while ($x0 < $x1)
			{
				$out[$outcount]['from'][$tmpi] = $t1[$x0];
				$x0++;
				$tmpi++;
			}   // Deleted elems
			if ($op == 2)
			{
				$out[$outcount]['rownr1'] = $x1;
				$out[$outcount]['rownr2'] = $ystr;
				$out[$outcount]['action'] = 'Added';
				$out[$outcount]['color'] = 'green';
			}
			elseif ($op == 3)
			{
			}
			$tmpi = 0;
			while ($y0 < $y1)
			{
				$out[$outcount]['to'][$tmpi] = $t2[$y0];
				$y0++;
				$tmpi++;
			}   // Added elems
		}
		$x1++;
		$x0 = $x1;
		$y1++;
		$y0 = $y1;
		$op = 0;
		$outcount++;
	}

	return $out;
}

/**
 * Highlight php code
 *
 * @param string $string
 * @param int $startline
 * @return string
 */
function highlight_php($string, $startline=1)
{
	$lines = explode("\n",$string);

	$returnstring = '<div style="white-space:nowrap;overflow:auto;"><table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-style:solid;border-width:1px;border-color:white black black white">';

	foreach( $lines as $key => $line )
	{
		if( !empty($line) )
		{
			$line = "<?php x\n" . $line;

			$linecoded = highlight_string($line,true);

			$linecoded = str_replace(array('<font ', '</font>','<code>','</code>'), array('<span ', '</span>','',''), $linecoded);
			$linecoded = preg_replace('/color="(.*?)"/', 'style="color:\\1;"', $linecoded);

			$linecoded = str_replace('&lt;?php&nbsp;x<br />', '', $linecoded);

			if( !empty($linecoded) )
			{
				$returnstring .= '<tr>';
				$returnstring .= '  <td width="1%" valign="top" style="background-color:#33ccff;border-style:solid;border-width:1px;border-color:white;"><code>' . $startline . '</code></td>';
				$returnstring .= '  <td width="99%" valign="top" style="background-color:white;"><code>' . $linecoded . '</code></td>';
				$returnstring .= '</tr>';
			}
		}
		else
		{
			$returnstring .= '<tr>';
			$returnstring .= '  <td width="1%" valign="top" style="background-color:#33ccff;border-style:solid;border-width:1px;border-color:white;"><code>' . $startline . '</code></td>';
			$returnstring .= '  <td width="99%" valign="top" style="background-color:white;">&nbsp;</td>';
			$returnstring .= '</tr>';
		}

		$startline++;
	}
	$returnstring .= '</table></div>';

	return $returnstring;
}

/**
 * Check the timestamp
 *
 * @param sting $date
 * @param sting $time
 * @return sting
 */
function check_date_time($date, $time)
{
	if (preg_match("~(\d\d\d\d)[- /.](0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])~", $date, $datepart))
	{
		$returndate = $datepart;
	}
	else
	{
		$returndate = array(0,1,1,1970);
	}


	if (preg_match("~([0-9]|0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])~", $time, $timepart))
	{
		$returntime = $timepart;
	}
	else
	{
		$returntime = array(0,0,0,0);
	}

	$returnunixdate = gmmktime($returntime[1], $returntime[2], $returntime[3], $returndate[2], $returndate[3], $returndate[1]);

	return $returnunixdate;
}
