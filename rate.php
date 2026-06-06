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

define('IN_SCRIPT',1);
define('HESK_PATH','./');

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/customer_accounts.inc.php');
hesk_load_database_functions();

// Start a customer session and verify the request token
hesk_session_start('CUSTOMER');
hesk_token_check('GET');

// Is rating enabled?
if ( ! $hesk_settings['rating'])
{
	die($hesklang['rdis']);
}

// Rating value
$rating = intval( hesk_GET('rating', 0) );

// Rating can only be 1 or 5
if ($rating != 1 && $rating != 5)
{
	die($hesklang['attempt']);
}

// Reply ID
$reply_id = intval( hesk_GET('id', 0) ) or die($hesklang['attempt']);

// Ticket tracking ID
$trackingID = hesk_cleanID() or die($hesklang['attempt']);

// Connect to database
hesk_dbConnect();

// Get reply and ticket info to verify they match and enforce customer access
$result = hesk_dbQuery("SELECT `replies`.`replyto`, `replies`.`rating`, `replies`.`staffid`, `tickets`.`trackid`
	FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` AS `replies`
	INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `tickets`
		ON `replies`.`replyto` = `tickets`.`id`
	WHERE `replies`.`id`='{$reply_id}'
	LIMIT 1");

// -> Reply and ticket found?
if (hesk_dbNumRows($result) != 1)
{
	die($hesklang['attempt']);
}

$reply = hesk_dbFetchAssoc($result);

// -> Does the tracking ID match?
if ($reply['trackid'] != $trackingID)
{
	die($hesklang['attempt']);
}

// -> Is this a staff reply?
if (empty($reply['staffid']))
{
	die($hesklang['attempt']);
}

// -> Does the current customer have access to this ticket?
$customers = hesk_get_customers_for_ticket($reply['replyto']);
$customer_emails = array_map(function($customer) { return $customer['email']; }, $customers);

if (hesk_verifyEmailMatch($trackingID, 0, $customer_emails, 0) !== true)
{
	die($hesklang['attempt']);
}

// OK, tracking ID matches. Now check if this reply has already been rated
if ( ! empty($reply['rating']))
{
	die($hesklang['ar']);
}

// Update reply rating
hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `rating`='{$rating}' WHERE `id`='{$reply_id}'");

// Also update staff rating
hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `rating`=((`rating`*(`ratingpos`+`ratingneg`))+{$rating})/(`ratingpos`+`ratingneg`+1), " .
			($rating == 5 ? '`ratingpos`=`ratingpos`+1 ' : '`ratingneg`=`ratingneg`+1 ') .
            "WHERE `id`='{$reply['staffid']}'");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header('Content-type: text/plain; charset=utf-8');
if ($rating == 5)
{
	echo $hesklang['rh'];
}
else
{
	echo $hesklang['rnh'];
}
exit();
?>
