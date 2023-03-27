<?php
/*******************************************************************************
 *
 * Web wrapper for Bas Voesenek's qPCR analysis script.
 *
 * Created     : 2023-03-23
 * Modified    : 2023-03-27
 *
 * Copyright   : 2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-lib.php';

@ini_set('session.cookie_httponly', 1); // Available from 5.2.0.
@ini_set('session.use_cookies', 1);
@ini_set('session.use_only_cookies', 1);
if (ini_get('session.cookie_path') == '/') {
    // Don't share cookies with other systems - set the cookie path!
    @ini_set('session.cookie_path', lovd_getInstallURL(false));
}
@session_start();

define('DATA_PATH', ROOT_PATH . 'data/');
header('Content-type: text/javascript; charset=UTF-8');

// Note that we'll have to do this in steps. The script will take quite some time to complete.
// We can't send asynchronous updates to the page.
define('ACTION', current(array_keys($_GET)));
if (!ACTION) {
    die('
    alert("Did not receive a command.");');
}





if (ACTION == 'upload') {
    // Check whether all required input was given.
    if (empty($_FILES) || empty($_POST['housekeeping1']) || empty($_POST['housekeeping2'])) {
        // Client-side checks have been bypassed.
        die('
        $("form input").removeClass(["is-valid", "is-invalid"]).filter(function() { return $(this).val() == ""; }).addClass("is-invalid");');
    } else {
        print('
        $("form input").removeClass("is-invalid").addClass("is-valid");');
    }


    // Handle the file uploads.
    // Determine max file upload size in bytes.
    $nMaxSizeLOVD = 5 * 1024 * 1024; // 5MB = our limit.
    $nMaxSize = min($nMaxSizeLOVD,
        lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
        lovd_convertIniValueToBytes(ini_get('post_max_size')));

    $_ERRORS = array();

    // If the file does not arrive (too big), it doesn't exist in $_FILES.
    if (empty($_FILES['file']) || ($_FILES['file']['error'] > 0 && $_FILES['file']['error'] < 4)) {
        $_ERRORS['file'][] = 'There was a problem with the file transfer. Please try again. The file cannot be larger than ' . round($nMaxSize / pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.';

    } elseif ($_FILES['file']['error'] == 4 || !$_FILES['file']['size']) {
        $_ERRORS['file'][] = 'Please select a non-empty file to upload.';

    } elseif ($_FILES['file']['size'] > $nMaxSize) {
        $_ERRORS['file'][] = 'The file cannot be larger than ' . round($nMaxSize / pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.';

    } elseif ($_FILES['file']['error']) {
        // Various errors available from 4.3.0 or later.
        $_ERRORS['file'][] = 'There was an unknown problem with receiving the file properly, possibly because of the current server settings. If the problem persists, please contact the database administrator.';
    }

    if (!$_ERRORS) {
        // Find out the MIME-type of the uploaded file. Sometimes mime_content_type() seems to return False.
        // Don't stop processing if that happens.
        // However, when it does report something different, mention what type was found, so we can debug it.
        $sType = mime_content_type($_FILES['file']['tmp_name']);
        if ($sType
            && !in_array(
                $sType,
                array(
                    'application/octet-stream',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ))) {
            $_ERRORS['file'][] = 'The uploaded file does not seem to be an Excel spreadsheet. It seems to be of type "' . htmlspecialchars($sType) . '".';
        }
    }

    if ($_ERRORS) {
        // Send the errors back to the form.
?>
        // There are errors.
        $("form input[type=file]").removeClass("is-valid").addClass("is-invalid");
        oInvalidFeedback = $("form input[type=file]").next(".invalid-feedback");
        oInvalidFeedback.data("ori-html", oInvalidFeedback.html());
        oInvalidFeedback.html("<?php echo addslashes(implode('<BR>', $_ERRORS['file'])) ?>");
        obModal.hide();
<?php
        exit;
    }
}
?>
