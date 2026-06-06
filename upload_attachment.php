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
define('HESK_PATH', './');
define('HESK_NO_ROBOTS',1);

require_once(HESK_PATH . 'hesk_settings.inc.php');
require_once(HESK_PATH . 'inc/common.inc.php');

// Demo mode?
if ( defined('HESK_DEMO') ) {
    http_response_code(400);
    exit();
}

hesk_load_database_functions();
hesk_dbConnect();
hesk_session_start('CUSTOMER');

if ($hesk_settings['customer_accounts'] && $hesk_settings['customer_accounts_required']) {
    require_once(HESK_PATH . 'inc/customer_accounts.inc.php');

    if ( ! hesk_isCustomerLoggedIn(false)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        print json_encode(array(
            'status' => 'failure',
            'status_code' => 401,
            'message' => $hesklang['customer_must_be_logged_in_to_view']
        ));
        exit();
    }
}

require_once(HESK_PATH . 'inc/upload_attachment.inc.php');
