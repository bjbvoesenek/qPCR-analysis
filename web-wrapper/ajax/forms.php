<?php
/*******************************************************************************
 *
 * Web wrapper for Bas Voesenek's qPCR analysis script.
 *
 * Created     : 2023-03-23
 * Modified    : 2023-03-28
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
        // Handle https://bugs.php.net/bug.php?id=77784.
        // If the mimetype is a copy of itself, de-duplicate it.
        $lType = strlen($sType);
        if ($lType > 100 && !($lType % 2) && substr($sType, 0, $lType / 2) == substr($sType, -($lType / 2))) {
            $sType = substr($sType, 0, $lType/2);
        }
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
        oModal.handleUpdate();
        obModal.hide();
<?php
        exit;
    }

    // If we get here, no errors were encountered with the input. Process the file.
    $sID = str_pad(microtime(true), 15, '0');
    $b = (@mkdir(DATA_PATH . $sID) && @move_uploaded_file($_FILES['file']['tmp_name'], DATA_PATH . $sID . '/input.xlsx'));
    if (!$b) {
?>
        oModal.find(".modal-title").html("Error");
        oModal.find(".modal-content").addClass(["border-danger", "bg-danger", "text-white"]);
        oModal.find(".modal-body").html("Sorry, I couldn't store your input file after I received it. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help.");
        oModal.handleUpdate();
<?php
        exit;
    }

    // OK, ready for the next step.
    @file_put_contents(DATA_PATH . $sID . '/arguments.txt', 'input.xlsx ' . $_POST['housekeeping1'] . ' ' . $_POST['housekeeping2']);
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    oModal.find(".modal-title").html("Data successfully received, running the analysis...");
    $.post({
        url: $("form").attr("action") + "?process",
        data: {
            "jobID": "<?php echo $sID; ?>",
            "csrf_token": "<?php echo $_SESSION['csrf_tokens']['upload'][$sID]; ?>"
        },
        error: function ()
        {
            alert("Failed to process the data. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help and notify him of the job ID: <?php echo $sID; ?>.");
        }
    });
<?php
    exit;
}





elseif (ACTION == 'process') {
    // Actually process the data.
    $sID = ($_POST['jobID'] ?? '');
    $sCSRF = ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_tokens']['upload'][$sID])
        || $_SESSION['csrf_tokens']['upload'][$sID] != $sCSRF) {
?>
        oModal.find(".modal-title").html("Error");
        oModal.find(".modal-content").addClass(["border-danger", "bg-danger", "text-white"]);
        oModal.find(".modal-body").html("Sorry, there was an error verifying the data. Try reloading the page, and submitting the file again.");
        oModal.handleUpdate();
<?php
        exit;
    }

    // OK, run the script.
    @chdir(DATA_PATH . $sID);
    // Chose not to complicate this and not to add more tests. The program will fail if we fail here.
    $sArguments = implode(
        ' ',
        array_map(
            'escapeshellarg',
            explode(
                ' ',
                (string) @file_get_contents('arguments.txt')
            )
        )
    );
    $aOut = array();
    @exec(
        'python3 ../../../qpcr_analysis.py ' . $sArguments . ' 2>&1',
        $aOut,
        $nReturnCode
    );
    $sOut = implode("\n", $aOut);

    // To check if it worked, we will check the return code.
    $sError = '';
    if ($nReturnCode !== 0) {
        $sError = 'The analysis program reported an error.';
        if ($aOut) {
            $sError .= "<BR>" . implode("<BR>", array_map('htmlspecialchars', $aOut));
        }
?>
        oModal.find(".modal-title").html("Error");
        oModal.find(".modal-content").addClass(["border-danger", "bg-danger", "text-white"]);
        oModal.find(".modal-body").html("Sorry, there was an error processing the data.<BR><?php echo $sError; ?>");
        oModal.handleUpdate();
<?php
        exit;
    }

    // OK, ready for the next step.
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    oModal.find(".modal-title").html("Data successfully processed, preparing your download...");
    oModal.find(".modal-content").addClass(["border-success", "bg-success", "text-white"]);
    $.post({
        url: $("form").attr("action") + "?download",
        data: {
            "jobID": "<?php echo $sID; ?>",
            "csrf_token": "<?php echo $_SESSION['csrf_tokens']['upload'][$sID]; ?>"
        },
        error: function ()
        {
            alert("Failed to process the data. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help and notify him of the job ID: <?php echo $sID; ?>.");
        }
    });
<?php
    exit;
}
?>
