<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');} 

/*** FUNCTIONS ***/

function hesk_SecondsToHHMMSS($in)
{
	// Default values for hours, minutes and seconds
    $h = 0;
    $m = 0;
    $s = intval(trim($in === null ? '' : $in));

	// If time is 0 seconds just return an empty string
	if ($s == 0)
	{
		return '';
	}

	// Convert seconds to minutes if 60 or more seconds
    if ($s > 59)
    {
    	$m = floor($s / 60) + $m;
        $s = intval($s % 60);
    }

	// Convert minutes to hours if 60 or more minutes
    if ($m > 59)
    {
    	$h = floor($m / 60) + $h;
        $m = intval($m % 60);
    }

	// That's it, let's send out formatted time string
    return str_pad($h, 2, "0", STR_PAD_LEFT) . ':' . str_pad($m, 2, "0", STR_PAD_LEFT) . ':' . str_pad($s, 2, "0", STR_PAD_LEFT);

} // END hesk_SecondsToHHMMSS()


function hesk_parseXML($msg)
{
	$from = array('/\<a href="mailto\:([^"]*)"\>([^\<]*)\<\/a\>/i', '/\<a href="([^"]*)" target="_blank"\>([^\<]*)\<\/a\>/i');
	$to   = array("$1", "$1");
	$msg = preg_replace($from,$to,$msg);
	$msg = preg_replace('/<br \/>\s*/',"\n",$msg);
	$msg = trim($msg);
	return $msg;
} // END hesk_parseXML()


function dateweek($weeknumber,$business=0)
{
    $today = time();
    $day_of_week = date('N', $today);

    // Start from Monday of the current week, then offset by requested week number
    $monday = mktime(0, 0, 0, date('m', $today), date('d', $today) - $day_of_week + 1 + (7 * $weeknumber), date('Y', $today));

	// DATE BEGINNING OF THE WEEK (Monday)
	$dt[0] = date('Y-m-d', $monday);

	if ($business)
    {
		// DATE END OF BUSINESS WEEK (Friday)
		$dt[1] = date('Y-m-d', strtotime('+4 days', $monday));
	}
    else
    {
		// DATE END OF THE WEEK (Sunday)
		$dt[1] = date('Y-m-d', strtotime('+6 days', $monday));
	}

	return $dt;
} // END dateweek()


function DateArray($s,$e)
{
	$start = strtotime($s);
	$end = strtotime($e);
	$da = array();
	$loop = 0;
	while ($loop < 10000 && $start <= $end)
	{
		$loop++;
		$da[] = date('Y-m-d', $start);
		$start = strtotime('+1 day', $start);
	}
	return $da;
} // END DateArray()


function MonthsArray($s,$e)
{
	$start = date('Y-m-01', strtotime($s));
	$end = date('Y-m-01', strtotime($e));
    $mt = array();
	while ($start <= $end)
	{
		$mt[] = $start;
		$start = date('Y-m-01',strtotime("+1 month", strtotime($start)));
	}
    return $mt;
} // END MonthsArray()


function hesk_getOldestDate()
{
	global $hesk_settings, $hesklang, $date_from, $date_to;

	$res = hesk_dbQuery("SELECT `dt` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` ORDER BY `dt` ASC LIMIT 1");

    if (hesk_dbNumRows($res) == 1)
    {
		$row = hesk_dbFetchAssoc($res);
        return date('Y-m-d', strtotime($row['dt']) );
    }
    else
    {
    	return date('Y-m-d');
    }

} // END hesk_getOldestDate()
