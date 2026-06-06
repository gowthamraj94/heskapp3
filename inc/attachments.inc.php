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

/***************************
Function hesk_uploadFiles()
***************************/
function hesk_uploadFile($i)
{
	global $hesk_settings, $hesklang, $trackingID, $hesk_error_buffer;

	/* Return if name is empty */
	if (empty($_FILES['attachment']['name'][$i])) {return '';}

    /* Parse the name */
	$file_realname = hesk_cleanFileName($_FILES['attachment']['name'][$i]);

	/* Check file extension */
	$ext = strtolower(strrchr($file_realname, "."));
	if ( ! in_array($ext,$hesk_settings['attachments']['allowed_types']))
	{
        return hesk_fileError(sprintf($hesklang['type_not_allowed'], $ext, $file_realname));
	}

	/* Check file size */
	if ($_FILES['attachment']['size'][$i] > $hesk_settings['attachments']['max_size'])
	{
	    return hesk_fileError(sprintf($hesklang['file_too_large'], $file_realname));
	}
	else
	{
	    $file_size = $_FILES['attachment']['size'][$i];
	}

	/* Generate a random file name */
    $file_name = hesk_generateAttachmentName($file_realname, $ext, $trackingID);

    // Does the temporary file exist? If not, probably server-side configuration limits have been reached
    // Uncomment this for debugging purposes
    /*
    if ( ! file_exists($_FILES['attachment']['tmp_name'][$i]) )
    {
		return hesk_fileError($hesklang['fnuscphp']);
    }
    */

	/* If upload was successful let's create the headers */
	if ( ! move_uploaded_file($_FILES['attachment']['tmp_name'][$i], dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/'.$file_name))
	{
	    return hesk_fileError($hesklang['cannot_move_tmp']);
	}

	$info = array(
	    'saved_name'=> $file_name,
	    'real_name' => $file_realname,
	    'size'      => $file_size
	);

	return $info;
} // End hesk_uploadFile()

function hesk_generateAttachmentName($file_realname, $ext, $tracking_id = '') {
    /* Generate a random file name */
    $useChars='AEUYBDGHJLMNPQRSTVWXZ123456789';
    $tmp = uniqid();
    for($j=1;$j<10;$j++) {
        $tmp .= $useChars[mt_rand(0,29)];
    }

    if (defined('KB') || $tracking_id === '') {
        return substr(md5($tmp . $file_realname), 0, 200) . $ext;
    }

    return substr($tracking_id . '_' . md5($tmp . $file_realname), 0, 200) . $ext;
}

function hesk_generateTempAttachmentRandomHex($bytes = 32)
{
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Exception $e) {
            // Fall through to older generators below.
        }
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $random = openssl_random_pseudo_bytes($bytes, $strong);
        if ($random !== false && $strong) {
            return bin2hex($random);
        }
    }

    if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
        $random = mcrypt_create_iv($bytes, MCRYPT_DEV_URANDOM);
        if ($random !== false && strlen($random) === $bytes) {
            return bin2hex($random);
        }
    }

    $random = '';
    while (strlen($random) < $bytes) {
        $seed = uniqid('', true) . microtime(true) . mt_rand() . mt_rand();
        if (function_exists('memory_get_usage')) {
            $seed .= memory_get_usage();
        }
        $random .= hash('sha256', $seed . $random, true);
    }

    return bin2hex(substr($random, 0, $bytes));
} // End hesk_generateTempAttachmentRandomHex()

function hesk_getTempAttachmentSigningSecret()
{
    if ( ! isset($_SESSION) || ! is_array($_SESSION)) {
        return false;
    }

    if (empty($_SESSION['temp_attachment_secret']) || ! preg_match('/^[a-f0-9]{64}$/', $_SESSION['temp_attachment_secret'])) {
        $_SESSION['temp_attachment_secret'] = hesk_generateTempAttachmentRandomHex(32);
    }

    return $_SESSION['temp_attachment_secret'];
} // End hesk_getTempAttachmentSigningSecret()

function hesk_getTempAttachmentSignature($unique_id)
{
    $secret = hesk_getTempAttachmentSigningSecret();

    return $secret === false ? false : hash_hmac('sha256', 'hesk-temp-attachment-v1|' . (string) $unique_id, $secret);
} // End hesk_getTempAttachmentSignature()

function hesk_signTempAttachmentKey($unique_id)
{
    $signature = hesk_getTempAttachmentSignature($unique_id);

    return $signature === false ? false : (string) $unique_id . ':' . $signature;
} // End hesk_signTempAttachmentKey()

function hesk_validateTempAttachmentKey($file_key)
{
    $parts = explode(':', (string) $file_key, 2);

    if (count($parts) !== 2) {
        return false;
    }

    list($unique_id, $signature) = $parts;

    if ($unique_id === '' || strlen($unique_id) > 255 || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
        return false;
    }

    $expected_signature = hesk_getTempAttachmentSignature($unique_id);

    if ($expected_signature === false || ! hash_equals($expected_signature, $signature)) {
        return false;
    }

    return $unique_id;
} // End hesk_validateTempAttachmentKey()

function hesk_uploadTempFile() {
    global $hesk_settings, $hesklang;

    /* Return if name is empty */
    if (empty($_FILES['attachment']['name'])) {
        return null;
    }

    /* Parse the name */
    $file_realname = hesk_cleanFileName($_FILES['attachment']['name']);

    /* Check file extension */
    $ext = strtolower(strrchr($file_realname, "."));
    if (!in_array($ext,$hesk_settings['attachments']['allowed_types'])) {
        return array(
            'status' => 'failure',
            'status_code' => 400,
            'message' => sprintf($hesklang['type_not_allowed'], $ext, $file_realname)
        );
    }

    /* Check file size */
    if ($_FILES['attachment']['size'] > $hesk_settings['attachments']['max_size']) {
        return array(
            'status' => 'failure',
            'status_code' => 400,
            'message' => sprintf($hesklang['file_too_large'], $file_realname)
        );
    } else {
        $file_size = $_FILES['attachment']['size'];
    }

    /* Check for potential attachment flooding */
    $ip = hesk_getClientIP();
    if (hesk_attachmentFloodingDetected($ip)) {
        return array(
            'status' => 'failure',
            'status_code' => 429,
            'message' => $hesklang['attachment_too_many_uploads']
        );
    }

    $file_name = hesk_generateAttachmentName($file_realname, $ext);

    // Does the temporary file exist? If not, probably server-side configuration limits have been reached
    // Uncomment this for debugging purposes
    /*
    if ( ! file_exists($_FILES['attachment']['tmp_name']) )
    {
		return hesk_fileError($hesklang['fnuscphp']);
    }
    */

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';
    if (!is_dir($hesk_settings['server_path'])) {
        @mkdir($hesk_settings['server_path']);
        @file_put_contents($hesk_settings['server_path'].'index.htm', '');
    }

    /* If upload was successful let's create the headers */
    ob_start();
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $hesk_settings['server_path'].$file_name))
    {
        ob_end_clean();
        return array(
            'status' => 'failure',
            'status_code' => 500,
            'message' => $hesklang['error'] . ': ' . $hesklang['cannot_move_tmp']
        );
    }
    ob_end_clean();

    // Generate a random ID to use when deleting temporary attachments
    $unique_id = hesk_generateTempAttachmentRandomHex(32);

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments` (`saved_name`, `unique_id`, `real_name`, `expires_at`, `size`)
    VALUES ('".hesk_dbEscape($file_name)."', '".hesk_dbEscape($unique_id)."', '".hesk_dbEscape($file_realname)."', NOW() + INTERVAL 3 HOUR, ".intval($file_size).")");

    // Increment limits used for IP
    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` (`ip`,`upload_count`)
        VALUES ('".hesk_dbEscape(hesk_getClientIP())."', 1) ON DUPLICATE KEY UPDATE `upload_count` = `upload_count` + 1");

    $file_key = hesk_signTempAttachmentKey($unique_id);
    if ($file_key === false) {
        hesk_deleteTempAttachmentByUniqueId($unique_id, true);
        return array(
            'status' => 'failure',
            'status_code' => 500,
            'message' => $hesklang['error'] . ': ' . $hesklang['cannot_move_tmp']
        );
    }

    $info = array(
        'status' => 'success',
        'status_code' => 200,
        'file_key'=> $file_key
    );

    return $info;
} // End hesk_uploadTempFile

function hesk_attachmentFloodingDetected($ip) {
    global $hesk_settings;

    // Reset counters for any IPs that haven't uploaded in an hour
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` WHERE `last_upload_at` < (NOW() - INTERVAL 1 HOUR)");

    $res = hesk_dbQuery("SELECT `upload_count` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` WHERE `ip` = '".hesk_dbEscape($ip)."'");
    if (hesk_dbNumRows($res) < 1) {
        return false;
    }
    $row = hesk_dbFetchAssoc($res);

    // Change "100" to whatever max amount is appropriate.
    return $row['upload_count'] > 100;
}

function hesk_fileError($error)
{
	global $hesk_settings, $hesklang, $trackingID;
    global $hesk_error_buffer;

	$hesk_error_buffer['attachments'] = $error;

	return false;
} // End hesk_fileError()


function hesk_removeAttachments($attachments)
{
	global $hesk_settings, $hesklang;

	$hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/';

	foreach ($attachments as $myatt)
	{
		hesk_unlink($hesk_settings['server_path'].$myatt['saved_name']);
	}

	return true;
} // End hesk_removeAttachments()

function hesk_removeExpiredTempAttachments() {
    global $hesk_settings;

    // 1. Grab temp attachments that are expired
    $res = hesk_dbQuery("SELECT `att_id`, `saved_name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments`
        WHERE `expires_at` < NOW()");

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';

    while ($row = hesk_dbFetchAssoc($res)) {
        hesk_unlink($hesk_settings['server_path'].$row['saved_name']);
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments` WHERE `att_id` = ".intval($row['att_id']));
    }
}

function hesk_migrateTempAttachments($attachments, $tracking_id = '') {
    global $hesk_settings;

    $moved_attachments = array();
    foreach ($attachments as $myatt) {
        hesk_deleteTempAttachment($myatt['file_key']);

        $old_name = $myatt['saved_name'];
        $myatt['saved_name'] = ($tracking_id !== '') ? "{$tracking_id}_{$old_name}" : $old_name;
        hesk_moveAttachment($old_name, $myatt['saved_name']);

        $moved_attachments[] = $myatt;
    }

    // Reset limits for the IP
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments_limits` WHERE `ip` = '".hesk_dbEscape(hesk_getClientIP())."'");

    return $moved_attachments;
}

function hesk_moveAttachment($old_name, $new_name) {
    global $hesk_settings;

    $hesk_settings['temp_server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';
    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/';

    hesk_rename($hesk_settings['temp_server_path'].$old_name, $hesk_settings['server_path'].$new_name);
}

function hesk_deleteTempAttachmentByUniqueId($unique_id, $delete_file = false) {
    global $hesk_settings;

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';

    $res = hesk_dbQuery("SELECT `att_id`, `saved_name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments`
        WHERE `unique_id` = '".hesk_dbEscape($unique_id)."'");

    if ($row = hesk_dbFetchAssoc($res)) {
        if ($delete_file) {
            hesk_unlink($hesk_settings['server_path'].$row['saved_name']);
        }
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."temp_attachments` WHERE `att_id` = ".intval($row['att_id']));
    }
}

function hesk_deleteTempAttachment($file_key, $delete_file = false) {
    $unique_id = hesk_validateTempAttachmentKey($file_key);

    if ($unique_id === false) {
        return;
    }

    hesk_deleteTempAttachmentByUniqueId($unique_id, $delete_file);
}

function hesk_getTemporaryAttachment($file_key) {
    global $hesk_settings;

    $unique_id = hesk_validateTempAttachmentKey($file_key);

    if ($unique_id === false) {
        return NULL;
    }

    $rs = hesk_dbQuery("SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "temp_attachments` WHERE `unique_id` = '" . hesk_dbEscape($unique_id) . "'");
    if (hesk_dbNumRows($rs) == 0) {
        return NULL;
    }
    $row = hesk_dbFetchAssoc($rs);

    $hesk_settings['server_path'] = dirname(dirname(__FILE__)).'/'.$hesk_settings['attach_dir'].'/temp/';
    if (!file_exists($hesk_settings['server_path'].$row['saved_name'])) {
        // Not deleting the file itself because it, well, doesn't exist.
        hesk_deleteTempAttachmentByUniqueId($unique_id);
        return null;
    }

    $info = array(
        'saved_name' => $row['saved_name'],
        'real_name' => $row['real_name'],
        'size' => $row['size'],
        'file_key' => hesk_signTempAttachmentKey($row['unique_id'])
    );

    return $info;
}

//region Dropzone
function build_dropzone_markup($admin = false, $id = 'filedrop', $startingId = 1, $show_file_limits = true) {
    global $hesklang, $hesk_settings;

    $directory_separator = $admin ? '../' : '';
    echo '<div class="dropzone dz-click-'.$id.'" id="' . $id . '">
        <div class="fallback">
            <input type="hidden" name="use-legacy-attachments" value="1">';
    for ($i = $startingId; $i <= $hesk_settings['attachments']['max_number']; $i++) {
        $cls = ($i == 1 && isset($_SESSION['iserror']) && in_array('attachments', $_SESSION['iserror'])) ? ' class="isError" ' : '';
        echo '<input type="file" name="attachment[' . $i . ']" size="50" ' . $cls . ' /><br />';
    }
    echo '</div>
    </div>
    <div class="btn btn-full fileinput-button filedropbutton-' . $id . ' dz-click-'.$id.'">' . $hesklang['attachment_add_files'] . '</div>';
    if ($show_file_limits) {
        echo '<a class="link" href="' . $directory_separator . 'file_limits.php" target="_blank"
       onclick="Javascript:hesk_window(\'' . $directory_separator . 'file_limits.php\',250,500);return false;">'. $hesklang['ful'] . '</a>';
    }

    output_attachment_id_holder_container($id);
}

function display_dropzone_field($url, $is_admin, $id = 'filedrop', $max_files_override = -1) {
    global $hesk_settings, $hesklang;

    // Built-in admin pages use their own staff-authenticated upload endpoint.
    // Customer/public pages keep using the public customer upload endpoint passed by the caller.
    if ($is_admin && $url === HESK_PATH . 'upload_attachment.php') {
        $url = 'admin_upload_attachment.php';
    }

    output_dropzone_window();

    $acceptedFiles = implode(',', $hesk_settings['attachments']['allowed_types']);
    $size = round($hesk_settings['attachments']['max_size'] / 1048576, 8, PHP_ROUND_HALF_UP);
    $max_files = $max_files_override > -1 ? $max_files_override : $hesk_settings['attachments']['max_number'];
    $attachment_token = hesk_token_echo(0);

    // Let's define this function we may need if it's not defined already
    if ( ! isset($hesk_settings['HeskWithout_defined'])) {
        echo "<script>const HeskWithout = (list, rejectedItem) => list.filter((item) => item !== rejectedItem).map((item) => item);</script>";
        $hesk_settings['HeskWithout_defined'] = true;
    }

    // Dropzone auto-discovery is being removed in v6.  As such, autodiscovery is disabled here should we want to
    // upgrade in the future.
    echo "
    <script>
    var pleaseWaitMessage = ".json_encode($hesklang['please_wait']).";
    Dropzone.autoDiscover = false;
    var dropzone{$id} = new Dropzone('#{$id}', {
        paramName: 'attachment',
        url: '{$url}',
        parallelUploads: {$max_files},
        maxFiles: {$max_files},
        acceptedFiles: ".json_encode($acceptedFiles).",
        maxFilesize: {$size}, // MB
        dictDefaultMessage: ".json_encode($hesklang['attachment_viewer_message']).",
        dictFallbackMessage: '',
        dictInvalidFileType: ".json_encode($hesklang['attachment_invalid_type_message']).",
        dictResponseError: ".json_encode(defined('HESK_DEMO') ? $hesklang['ddemo'] : $hesklang['attachment_upload_error']).",
        dictFileTooBig: ".json_encode($hesklang['attachment_too_large']).",
        dictCancelUpload: ".json_encode($hesklang['attachment_cancel']).",
        dictCancelUploadConfirmation: ".json_encode($hesklang['attachment_confirm_cancel']).",
        dictRemoveFile: ".json_encode($hesklang['attachment_remove']).",
        dictMaxFilesExceeded: ".json_encode($hesklang['attachment_max_exceeded']).",
        previewTemplate: $('#previews').html(),
        clickable: '.dz-click-".$id."',
        uploadMultiple: false,
        params: {
            token: ".json_encode($attachment_token)."
        }
    });
    
    dropzone{$id}.on('success', function(file, response) {
        var jsonResponse = response;

        if (typeof response === 'string') {
            try {
                jsonResponse = JSON.parse(response);
            } catch (e) {
                dropzone{$id}.emit('uploadprogress', file, 0);
                dropzone{$id}.files = HeskWithout(dropzone{$id}.files, file);
                dropzone{$id}.emit('error', file, ".json_encode($hesklang['attachment_upload_error']).");
                dropzone{$id}.emit('complete', file);
                return;
            }
        }

        // console.log(JSON.stringify(jsonResponse, null, 4));

        if(jsonResponse && jsonResponse.hasOwnProperty('status') && jsonResponse['status'] == 'failure'){
            // Upload request was completed, but something failed on the server-side
            dropzone{$id}.emit('uploadprogress', file, 0);
            dropzone{$id}.files = HeskWithout(dropzone{$id}.files, file);
            dropzone{$id}.emit('error', file, jsonResponse['message']);
            dropzone{$id}.emit('complete', file);
        } else if (jsonResponse && jsonResponse.hasOwnProperty('file_key')) {
            // The response will only be a JSON object holding the saved and real name
            outputAttachmentIdHolder(jsonResponse['file_key'], '".$id."');

            // Add the database id to the file
            file['databaseResponse'] = jsonResponse['file_key'];
        } else {
            dropzone{$id}.emit('uploadprogress', file, 0);
            dropzone{$id}.files = HeskWithout(dropzone{$id}.files, file);
            dropzone{$id}.emit('error', file, ".json_encode($hesklang['attachment_upload_error']).");
            dropzone{$id}.emit('complete', file);
        }
    });
    dropzone{$id}.on('addedfile', function() {
        var numberOfFiles = $('#" . $id . " .file-row').length;

        var disabled = false;
        if (numberOfFiles >= " . $max_files . ") {
            disabled = true;
        }

        $('." . $id . "button-" . $id . "').attr('disabled', disabled);
    });
    dropzone{$id}.on('removedfile', function(file) {
        if (file.beingRetried) {
            return;
        }
    
        // Remove the attachment from the database and the filesystem.
        removeAttachment(".$id.", file['databaseResponse'], ".($is_admin ? "true" : "false").", ".json_encode($attachment_token).");

        var numberOfFiles = $('#" . $id . " .file-row').length;

        var disabled = false;
        if (numberOfFiles >= " . $max_files . ") {
            disabled = true;
        }
        $('." . $id . "button-" . $id . "').attr('disabled', disabled);
        
        dropzone{$id}.getRejectedFiles().forEach(function(file) {
            file.beingRetried = true;
            dropzone{$id}.removeFile(file);
            file.status = undefined;
            file.accepted = undefined;
            file.beingRetried = false;
            dropzone{$id}.addFile(file);
        });
    });
    dropzone{$id}.on('queuecomplete', function() {
        $('input[type=\"submit\"]').attr('disabled', false);
        if(typeof attachmentQueueComplete === 'function') {
            attachmentQueueComplete();
        }
    });
    dropzone{$id}.on('processing', function() {
        $('input[type=\"submit\"]').attr('disabled', true);
        if(typeof attachmentQueueProcessing === 'function') {
            attachmentQueueProcessing();
        }
    });
    dropzone{$id}.on('uploadprogress', function(file, percentage) {
        $(file.previewTemplate).find('#percentage').text(percentage + '%');
    });
    dropzone{$id}.on('error', function(file, message) {
        $(file.previewTemplate).addClass('alert-danger');
        
        var actualMessage = message.title + ': ' + message.message;
        if (!message.message) {
            actualMessage = message;
        }
        
        $(file.previewElement).addClass('dz-error').find('[data-dz-errormessage]').text(actualMessage);
        if(typeof attachmentError === 'function') {
            attachmentError();
        }
    });
    </script>
    ";

}

function dropzone_display_existing_files($files, $dropzone_id = 'filedrop') {
    foreach ($files as $file) {
        dropzone_display_existing_file($file['real_name'], $file['size'], $file['file_key'], $dropzone_id);
    }
}

function dropzone_display_existing_file($name, $size, $file_key, $dropzone_id = 'filedrop') {
    $uniqid = uniqid();
    $successPayload = json_encode(array(
        'file_key' => $file_key
    ));
    echo "
    <script>
    var tempFile{$uniqid} = { 
        name: ".json_encode($name).", 
        size: ".$size."
    };
    tempFile{$uniqid}.accepted = true;
    tempFile{$uniqid}.status = Dropzone.SUCCESS;
    dropzone{$dropzone_id}.files.push(tempFile{$uniqid});
    dropzone{$dropzone_id}._updateMaxFilesReachedClass();
    dropzone{$dropzone_id}.emit('addedfile', tempFile{$uniqid});
    dropzone{$dropzone_id}.emit('complete', tempFile{$uniqid});
    dropzone{$dropzone_id}.emit('success', tempFile{$uniqid}, '{$successPayload}');
    dropzone{$dropzone_id}.emit('uploadprogress', tempFile{$uniqid}, 100);
    </script>
    ";
}

function output_dropzone_window() {
    echo '
    <div id="previews" style="display:none">
        <div id="template" class="file-row">
            <!-- This is used as the file preview template -->
            <div class="attachment-row">
                <div class="name-size-delete">
                    <div class="name-size">
                        <div class="name">
                            <p class="name" data-dz-name></p>
                        </div>
                        <div class="size">
                            (<span data-dz-size></span>)
                        </div>
                    </div>
                    <div class="delete-button">
                        <svg class="icon icon-delete" data-dz-remove>
                            <use xlink:href="'.HESK_PATH.'img/sprite.svg#icon-delete"></use>
                        </svg>
                    </div>
                </div>
                <div class="upload-progress">
                    <div style="border: 1px solid #d4d6e3; width: 100%; height: 19px">
                        <div style="font-size: 1px; height: 17px; width: 0px; border: none; background-color: green" data-dz-uploadprogress>
                        </div>
                    </div>
                </div>
            </div>
            <div class="error">
                <strong class="error text-danger" data-dz-errormessage></strong>
            </div>
        </div>
    </div>';
}

function output_attachment_id_holder_container($id) {
    echo '<div id="attachment-holder-' . $id . '" class="hide"></div>';
}

//endregion
