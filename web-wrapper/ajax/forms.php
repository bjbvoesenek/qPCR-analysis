<?php
/*******************************************************************************
 *
 * Web wrapper for Bas Voesenek's qPCR analysis script.
 *
 * Created     : 2023-03-23
 * Modified    : 2023-04-04
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
define('CURRENT_PATH', substr(lovd_getProjectFile(), 1));
define('ACTION', current(array_keys($_GET)));
if (!ACTION) {
    die('
    alert("Did not receive a command.");');
}





if (ACTION == 'upload') {
    // Check whether all required input was given.
    if (empty($_FILES)) {
        // Client-side checks have been bypassed.
        die('
        $("form input").removeClass(["is-valid", "is-invalid"]).filter(function() { return $(this).val() == ""; }).addClass("is-invalid");');
    } else {
        print('
        $("form input").removeClass("is-invalid").addClass("is-valid");');
    }



    // Handle the file upload.
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
        obModal.hide();
<?php
        exit;
    }

    // If we get here, no errors were encountered with the input. Process the file.
    $sID = str_pad(microtime(true), 15, '0');
    $b = (@mkdir(DATA_PATH . $sID) && @move_uploaded_file($_FILES['file']['tmp_name'], DATA_PATH . $sID . '/input.xlsx'));
    if (!$b) {
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, I couldn't store your input file after I received it. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help."
        });
<?php
        exit;
    }

    // OK, ready for the next step.
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    lovd_updateModal({
        "title": "Data successfully received, extracting the data..."
    });
    $.post({
        url: "<?= CURRENT_PATH; ?>?get-genes",
        data: {
            "jobID": "<?php echo $sID; ?>",
            "csrf_token": "<?php echo $_SESSION['csrf_tokens']['upload'][$sID]; ?>"
        },
        error: function ()
        {
            lovd_updateModal({
                "title": "Error",
                "classes": ["border-danger", "bg-danger", "text-white"],
                "body": "Failed to request the extraction of the data. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help and notify him of the job ID: <?php echo $sID; ?>."
            });
        }
    });
<?php
    exit;
}





elseif (ACTION == 'get-genes') {
    // What genes were found in the input file? Let the user choose the housekeeping genes.
    $sID = ($_POST['jobID'] ?? '');
    $sCSRF = ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_tokens']['upload'][$sID])
        || $_SESSION['csrf_tokens']['upload'][$sID] != $sCSRF) {
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error verifying the data. Try reloading the page, and submitting the file again."
        });
<?php
        exit;
    }

    // OK, run the script to get the gene list.
    @chdir(DATA_PATH . $sID);
    $aOut = array();
    @exec(
        'python3 ../../../qpcr_analysis.py --input input.xlsx 2>&1',
        $aOut,
        $nReturnCode
    );
    $sOut = implode("\n", $aOut);
    $sFile = 'Genes.txt';

    // To check if it worked, we will check the return code and check for the file.
    $sError = '';
    if ($nReturnCode !== 0 || !file_exists($sFile)) {
        $sError = 'The analysis program reported an error.';
        if ($aOut) {
            $sError .= "<BR>" . implode("<BR>", array_map('htmlspecialchars', $aOut));
        }
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error extracting the data.<BR><?php echo $sError; ?>"
        });
<?php
        exit;
    }

    // Fetch the gene list.
    $aGenes = file($sFile, FILE_IGNORE_NEW_LINES);
    $sGenes = implode(
        ' ',
        array_map(
            function ($sGene)
            {
                // Not sure why Bootstrap doesn't allow us to place the checkbox inside the label.
                $sID = preg_replace('/[^a-z0-9-]/', '-', strtolower($sGene));
                return
                    '<input type="checkbox" class="btn-check" name="genes[]" id="btn-check-' . $sID . '" value="' . $sGene . '">' .
                    '<label class="btn btn-outline-primary mb-1" for="btn-check-' . $sID . '">' . $sGene . '</label>';
            },
            $aGenes
        )
    );

    // OK, ready for the next step.
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    lovd_updateModal({
        "size": "lg",
        "title": "Please select your housekeeping genes",
        "classes": [],
        "body": '<form><?php echo $sGenes; ?></form>',
        "buttons": [["primary", "Submit"]]
    });
    $(function ()
    {
        oModal.find("form").submit(
            function (e)
            {
                e.preventDefault();

                // Only submit if the form is valid (required fields are filled in, etc).
                var nCheckBoxes = $(this).find("input[type=checkbox]:checked").length;
                if (!nCheckBoxes) {
                    $(this).prepend('<div class="alert alert-danger mb-3" role="alert">Please select at least one housekeeping gene.</div>');
                    return false;
                }

                var formData = new FormData(this);
                formData.append("jobID", "<?php echo $sID; ?>");
                formData.append("csrf_token", "<?php echo $_SESSION['csrf_tokens']['upload'][$sID]; ?>");
                // Turn off the submit button, but keep the form. We might need to post errors there.
                oModal.find("button").prop("disabled", true).append(' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

                $.post({
                    url: "<?= CURRENT_PATH; ?>?get-cell-lines",
                    data: formData,
                    contentType: false, // Required, fails otherwise.
                    processData: false, // Required, fails otherwise.
                    error: function ()
                    {
                        lovd_updateModal({
                            "title": "Error",
                            "classes": ["border-danger", "bg-danger", "text-white"],
                            "body": "Failed to request the cell line listing. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help and notify him of the job ID: <?php echo $sID; ?>.",
                            "buttons": []
                        });
                    }
                });
            }
        );
    });
<?php
    exit;
}





if (ACTION == 'get-cell-lines') {
    // Process given housekeeping genes, and ask user for the control cell lines.
    // What genes were found in the input file? Let the user choose the housekeeping genes.
    $sID = ($_POST['jobID'] ?? '');
    $sCSRF = ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_tokens']['upload'][$sID])
        || $_SESSION['csrf_tokens']['upload'][$sID] != $sCSRF) {
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error verifying the data. Try reloading the page, and submitting the file again.",
            "buttons": []
        });
<?php
        exit;
    }



    // Fetch the gene list.
    @chdir(DATA_PATH . $sID);
    $sFile = 'Genes.txt';
    $aGenes = file($sFile, FILE_IGNORE_NEW_LINES);

    $_ERRORS = array();
    if (empty($_POST['genes']) || !count($_POST['genes'])) {
        $_ERRORS[''][] = 'Please select at least one housekeeping gene.';
    } else {
        $aNotFound = array_diff($_POST['genes'], $aGenes);
        if ($aNotFound) {
            // Some genes don't exist in our list.
            $_ERRORS[''][] = 'Unknown gene' . (count($aNotFound) == 1? '' : 's') . ': &quot;' . implode('&quot;, &quot;', $aNotFound) . '&quot;.';
        }
    }

    if ($_ERRORS) {
        // Send the errors back to the form.
?>
        // There are errors. Prepare the form.
        if (!oModal.find("form div.alert").length) {
            oModal.find("form").prepend('<div class="alert alert-danger mb-3" role="alert"></div>');
        } else {
            oModal.find("form div.alert").html("");
        }
        oModal.find("form div.alert").html("<?= addslashes(implode('<BR>', $_ERRORS[''])) ?>");
        // Re-enable the submit button.
        oModal.find("button").prop("disabled", false).html(oModal.find("button").text().trim());
<?php
        exit;
    }

    // If we get here, no errors were encountered with the input.
    // Fetch the cell line list.
    $sFile = 'Cell_lines.txt';
    $aCellLines = file($sFile, FILE_IGNORE_NEW_LINES);
    $sCellLines = implode(
        ' ',
        array_map(
            function ($sCellLine)
            {
                // Not sure why Bootstrap doesn't allow us to place the checkbox inside the label.
                $sID = preg_replace('/[^a-z0-9-]/', '-', strtolower($sCellLine));
                return
                    '<input type="checkbox" class="btn-check" name="controls[]" id="btn-check-' . $sID . '" value="' . $sCellLine . '">' .
                    '<label class="btn btn-outline-primary mb-1" for="btn-check-' . $sID . '">' . $sCellLine . '</label>';
            },
            $aCellLines
        )
    );

    // OK, ready for the next step.
    @file_put_contents('arguments.txt', '--input input.xlsx --genes ' . implode(' ', $_POST['genes']));
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    lovd_updateModal({
        "size": "lg",
        "title": "Please select your controls",
        "classes": [],
        "body": '<form><?= $sCellLines; ?></form>',
        "buttons": [["primary", "Submit"]]
    });
    $(function ()
    {
        oModal.find("form").submit(
            function (e)
            {
                e.preventDefault();

                // Only submit if the form is valid (required fields are filled in, etc).
                var nCheckBoxes = $(this).find("input[type=checkbox]:checked").length;
                if (!nCheckBoxes) {
                    $(this).prepend('<div class="alert alert-danger mb-3" role="alert">Please select at least one control cell line.</div>');
                    return false;
                }

                var formData = new FormData(this);
                formData.append("jobID", "<?= $sID; ?>");
                formData.append("csrf_token", "<?= $_SESSION['csrf_tokens']['upload'][$sID]; ?>");
                // Turn off the submit button, but keep the form. We might need to post errors there.
                oModal.find("button").prop("disabled", true).append(' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

                $.post({
                    url: "<?= CURRENT_PATH; ?>?store-all",
                    data: formData,
                    contentType: false, // Required, fails otherwise.
                    processData: false, // Required, fails otherwise.
                    error: function ()
                    {
                        lovd_updateModal({
                            "title": "Error",
                            "classes": ["border-danger", "bg-danger", "text-white"],
                            "body": "Failed to request the processing of the data. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help and notify him of the job ID: <?= $sID; ?>.",
                            "buttons": []
                        });
                    }
                });
            }
        );
    });
<?php
    exit;
}





elseif (ACTION == 'store-all') {
    // Process given control cell lines, and store all the information we have.
    $sID = ($_POST['jobID'] ?? '');
    $sCSRF = ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_tokens']['upload'][$sID])
        || $_SESSION['csrf_tokens']['upload'][$sID] != $sCSRF) {
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error verifying the data. Try reloading the page, and submitting the file again.",
            "buttons": []
        });
<?php
        exit;
    }



    // Fetch the gene list.
    @chdir(DATA_PATH . $sID);
    $sFile = 'Cell_lines.txt';
    $aCellLines = file($sFile, FILE_IGNORE_NEW_LINES);

    $_ERRORS = array();
    if (empty($_POST['controls']) || !count($_POST['controls'])) {
        $_ERRORS[''][] = 'Please select at least one control cell line.';
    } else {
        $aNotFound = array_diff($_POST['controls'], $aCellLines);
        if ($aNotFound) {
            // Some cell lines don't exist in our list.
            $_ERRORS[''][] = 'Unknown cell line' . (count($aNotFound) == 1? '' : 's') . ': &quot;' . implode('&quot;, &quot;', $aNotFound) . '&quot;.';
        }
    }

    if ($_ERRORS) {
        // Send the errors back to the form.
?>
        // There are errors. Prepare the form.
        if (!oModal.find("form div.alert").length) {
            oModal.find("form").prepend('<div class="alert alert-danger mb-3" role="alert"></div>');
        } else {
            oModal.find("form div.alert").html("");
        }
        oModal.find("form div.alert").html("<?= addslashes(implode('<BR>', $_ERRORS[''])) ?>");
        // Re-enable the submit button.
        oModal.find("button").prop("disabled", false).html(oModal.find("button").text().trim());
<?php
        exit;
    }

    // OK, ready for the next step.
    @file_put_contents('arguments.txt', ' --controls ' . implode(' ', $_POST['controls']), FILE_APPEND);
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    lovd_updateModal({
        "title": "Data successfully received, running the analysis.",
        "body": '<div class="text-center"><div class="spinner-border" style="width: 3rem; height: 3rem;" role="status"><span class="visually-hidden">Loading...</span></div></div>',
        "buttons": []
    });
    $.post({
        url: "<?= CURRENT_PATH; ?>?process",
        data: {
            "jobID": "<?php echo $sID; ?>",
            "csrf_token": "<?php echo $_SESSION['csrf_tokens']['upload'][$sID]; ?>"
        },
        error: function ()
        {
            lovd_updateModal({
                "title": "Error",
                "classes": ["border-danger", "bg-danger", "text-white"],
                "body": "Failed to request the processing of the data. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help and notify him of the job ID: <?php echo $sID; ?>.",
                "buttons": []
            });
        }
    });
    // Show some activity for the user.
    for (i = 1; i < 30; i ++) {
        setTimeout(
            function ()
            {
                oModal.find(".modal-title").append(".");
            },
            i * 2000
        );
    }
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
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error verifying the data. Try reloading the page, and submitting the file again."
        });
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
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error processing the data.<BR><?= $sError; ?>"
        });
<?php
        exit;
    }

    // OK, ready for the next step.
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    lovd_updateModal({
        "title": "Data successfully processed, preparing your download...",
        "classes": ["border-success", "bg-success", "text-white"]
    });
    $.post({
        url: "<?= CURRENT_PATH; ?>?download",
        data: {
            "jobID": "<?= $sID; ?>",
            "csrf_token": "<?= $_SESSION['csrf_tokens']['upload'][$sID]; ?>"
        },
        error: function ()
        {
            lovd_updateModal({
                "title": "Error",
                "classes": ["border-danger", "bg-danger", "text-white"],
                "body": "Failed to request the preparation of the download. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help and notify him of the job ID: <?= $sID; ?>."
            });
        }
    });
<?php
    exit;
}





elseif (ACTION == 'download') {
    // Compress the processed data and trigger the download.
    $sID = ($_POST['jobID'] ?? '');
    $sCSRF = ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_tokens']['upload'][$sID])
        || $_SESSION['csrf_tokens']['upload'][$sID] != $sCSRF) {
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error verifying the data. Try reloading the page, and submitting the file again."
        });
<?php
        exit;
    }

    // OK, compress the data.
    @chdir(DATA_PATH . $sID);
    $sFile = 'results.zip';
    @exec(
        'zip ' . $sFile . ' *',
        $aOut,
        $nReturnCode
    );

    // To check if it worked, we will check the return code and check for the file.
    $sError = '';
    if ($nReturnCode !== 0 || !file_exists($sFile)) {
        $sError = 'Could not create download.';
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error preparing the download.<BR><?= $sError; ?>"
        });
<?php
        exit;
    }

    // OK, ready for the next step.
    $_SESSION['csrf_tokens']['upload'][$sID] = md5(uniqid());
?>
    lovd_updateModal({
        "body": "Download ready."
    });
    // When we hide the body, it'll look weird, so we keep the body and style it, then hide the header instead.
    oModal.find(".modal-body").addClass("fs-5");
    oModal.find(".modal-header").hide();

    // Trigger a download of the current file.
    // This cannot be done directly, because JS is not allowed to download files.
    // So, here we'll trigger the browser to download the file through an IFRAME.
    $("body").append(
        '<iframe class="d-none" src="' + $("form").attr("action") + '?raw&jobID=<?= $sID . '&csrf_token=' . $_SESSION['csrf_tokens']['upload'][$sID]; ?>"></iframe>'
    );
<?php
    exit;
}





elseif (ACTION == 'raw') {
    // Send the raw data.
    $sID = ($_GET['jobID'] ?? '');
    $sCSRF = ($_GET['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_tokens']['upload'][$sID])
        || $_SESSION['csrf_tokens']['upload'][$sID] != $sCSRF) {
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error verifying the data. Try reloading the page, and submitting the file again."
        });
<?php
        exit;
    }

    @chdir(DATA_PATH . $sID);
    $sFile = 'results.zip';
    if (!file_exists($sFile)) {
        $sError = 'Could not fetch download.';
?>
        lovd_updateModal({
            "title": "Error",
            "classes": ["border-danger", "bg-danger", "text-white"],
            "body": "Sorry, there was an error sending the data.<BR><?= $sError; ?>"
        });
<?php
        exit;
    }

    // OK, send the file.
    // Overwrite the JS content type.
    header('Content-type: application/zip');
    header('Content-Length: ' . filesize($sFile));
    header('Content-Disposition: attachment; filename="' . basename($sFile) . '"');
    header('Pragma: public');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($sFile)) . ' GMT');
    readfile($sFile);
    exit;
}
?>
