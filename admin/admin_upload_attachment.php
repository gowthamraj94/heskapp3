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
define('IN_SCRIPT', 1);
define('HESK_PATH', '../');
require_once(HESK_PATH . 'hesk_settings.inc.php');
require_once(HESK_PATH . 'inc/common.inc.php');
require_once(HESK_PATH . 'inc/admin_functions.inc.php');

// Demo mode?
if ( defined('HESK_DEMO') ) {
    http_response_code(400);
    exit();
}

hesk_load_database_functions();
hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

require_once(HESK_PATH . 'inc/upload_attachment.inc.php');
