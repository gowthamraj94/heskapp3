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

require_once(HESK_PATH . 'inc/attachments.inc.php');
require_once(HESK_PATH . 'inc/posting_functions.inc.php');

$hesk_settings['db_failure_response'] = 'json';

function hesk_tempAttachmentJsonResponse($status_code, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);

    if ($message !== '') {
        print json_encode(array(
            'status' => 'failure',
            'status_code' => $status_code,
            'message' => $message
        ));
    }

    return '';
}

// Temporary attachment upload/delete requests must be POST requests protected by the session CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return hesk_tempAttachmentJsonResponse(405, $hesklang['error']);
}

if (!hesk_token_compare(hesk_POST('token'))) {
    return hesk_tempAttachmentJsonResponse(403, $hesklang['eto']);
}

// Remove any expired temp attachments after the request method and CSRF token have been verified.
hesk_removeExpiredTempAttachments();

// Check if we are deleting an attachment or if we have a file to upload
if (hesk_POST('action') === 'delete') {
    $file_key = hesk_POST('fileKey', 'undefined');

    if ($file_key === 'undefined') {
        //-- Failed dropzone uploads will return an undefined saved name when removing them
        return http_response_code(204);
    }

    hesk_deleteTempAttachment($file_key, true);
    return http_response_code(204);
} elseif (!empty($_FILES)) {
    $info = hesk_uploadTempFile();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($info['status_code']);
    print json_encode($info);
    return '';
}

return hesk_tempAttachmentJsonResponse(400, $hesklang['error']);
