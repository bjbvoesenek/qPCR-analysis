<?php
/*******************************************************************************
 *
 * Web wrapper for Bas Voesenek's qPCR analysis script.
 *
 * Created     : 2023-03-22
 * Modified    : 2023-03-31
 *
 * Copyright   : 2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-lib.php';
define('DATA_PATH', ROOT_PATH . 'data/');

// Find out if we're using SSL.
if ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || !empty($_SERVER['SSL_PROTOCOL'])) {
    // We're using SSL!
    define('PROTOCOL', 'https://');
} else {
    define('PROTOCOL', 'http://');
}

define('LANG', 'en_US');
?>
<!DOCTYPE html>
<HTML lang="<?php echo LANG; ?>">
<head>
    <!-- Required meta tags -->
    <META http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <META name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <!-- Bootstrap Font Icon CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">

    <title>qPCR Analysis Web Interface</title>
    <BASE href="<?php echo lovd_getInstallURL(); ?>">

    <meta property="og:title" content="qPCR Analysis Web Interface">
    <meta property="og:description" content="This script can be used after LinRegPCR analysis to plot your data in a clear way and automate the relative gene expression calculations.">
    <meta property="og:locale" content="<?php echo LANG; ?>">
    <meta property="og:site_name" content="Human Genetics, Leiden University Medical Center">

    <style type="text/css">
    </style>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha384-tsQFqpEReu7ZLhBV2VZlAu7zcOV+rXbYlF2cqB8txI/8aZajjp4Bqd+V6D5IgvKT" crossorigin="anonymous"></script>
</head>
<body>

    <div class="container my-4">
        <h1>qPCR Analysis Web Interface</h1>

<?php
// Check the data directory.
$sError = '';
if (!file_exists(DATA_PATH) || !is_dir(DATA_PATH)) {
    $sError = '
            The data directory does not exist.
            The web interface can\'t process data until it does.
            Please create the data directory, and make sure it\'s readable and writable for the webserver.';
} elseif (!is_readable(DATA_PATH)) {
    $sError = '
            The data directory is not readable for the web server.
            The web interface can\'t process data until it is.
            Please make sure the data directory is readable and writable for the webserver.';
} elseif (!is_writable(DATA_PATH)) {
    $sError = '
            The data directory is not writable for the web server.
            The web interface can\'t process data until it is.
            Please make sure the data directory is writable for the webserver.';
}
if ($sError) {
    // Something went wrong - print error.
    print('
        <div class="alert alert-danger" role="alert">' . $sError . '
        </div>' . "\n\n");
    exit;



} else {
    // OK, print the form.
?>
        <div class="alert alert-info mb-3" role="alert">
            Please select your input file (.xlsx) and fill in your housekeeping genes.
            Then, submit the form to start the qPCR analysis.
            Note, this process will take a while, but should be finished within a minute.
        </div>
        <!-- Placeholder for errors. -->
        <div class="alert alert-danger mb-3 d-none" role="alert"></div>

        <!-- Add novalidate to stop the browser from doing any validation, we want the Bootstrap markup. -->
        <form action="ajax/forms.php" method="post" class="needs-validation" novalidate>
            <div class="row mb-2 g-2">
                <label for="inputFile" class="col-sm-3 col-form-label">Select the file to analyze</label>
                <div class="col-sm-9">
                    <input class="form-control" id="inputFile" type="file" name="file" required>
                    <div class="invalid-feedback">
                        Please provide a valid Excel sheet, containing the qPCR data.
                    </div>
                </div>
            </div>
            <div class="row mb-2 g-2">
                <label for="inputHouseKeeping1" class="col-sm-3 col-form-label">Housekeeping gene #1</label>
                <div class="col-sm-9">
                    <input class="form-control" id="inputHouseKeeping1" type="text" name="housekeeping1" placeholder="e.g., GAPDH" required>
                    <div class="invalid-feedback">
                        Please provide a valid housekeeping gene, like GAPDH.
                    </div>
                </div>
            </div>
            <div class="row mb-2 g-2">
                <label for="inputHouseKeeping2" class="col-sm-3 col-form-label">Housekeeping gene #2</label>
                <div class="col-sm-9">
                    <input class="form-control" id="inputHouseKeeping2" type="text" name="housekeeping2" placeholder="e.g., Bactin" required>
                    <div class="invalid-feedback">
                        Please provide a valid housekeeping gene, like Bactin.
                    </div>
                </div>
            </div>
            <div class="row mb-2 g-2">
                <div class="col-sm-9 offset-sm-3">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </div>
        </form>

        <!-- Javascript that handles everything -->
        <script type="text/javascript">
            var oModal; // Global scope.
            var obModal; // Global scope.
            $(function ()
            {
                $("form").submit(
                    function (e)
                    {
                        e.preventDefault();
                        $(this).addClass("was-validated"); // Enable the Bootstrap annotation of the form.

                        // Only submit if the form is valid (required fields are filled in, etc).
                        if (this.checkValidity()) {
                            // Do an additional file type check.
                            var sFileType = $(this).find("input[type=file]")[0].files[0].type;
                            if (sFileType != 'application/vnd.ms-excel'
                                && sFileType != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                                $(this).find("input[type=file]").removeClass("is-valid").addClass("is-invalid");
                                return false;
                            }

                            var formData = new FormData(this);
                            // The call can take a while, if the file is big.
                            // Better show the modal and run the ajax call only after the modal is shown.
                            oModal = $("div.modal");
                            // Fill in the header...
                            oModal.find(".modal-title").html("Please wait...");
                            // ... and the body.
                            oModal.find(".modal-body").html('<div class="text-center"><div class="spinner-border" style="width: 3rem; height: 3rem;" role="status"><span class="visually-hidden">Loading...</span></div></div>');

                            // Now, activate the modal and call the script.
                            oModal.on('shown.bs.modal', function (e)
                            {
                                $.post({
                                    url: $("form").attr("action") + "?upload",
                                    data: formData,
                                    contentType: false, // Required, fails otherwise.
                                    processData: false, // Required, fails otherwise.
                                    error: function ()
                                    {
                                        oModal.find(".modal-title").html("Error");
                                        oModal.find(".modal-content").addClass(["border-danger", "bg-danger", "text-white"]);
                                        oModal.find(".modal-body").html("Failed to submit the data. Please try again later or contact I.F.A.C.Fokkema@LUMC.nl for help.");
                                        oModal.handleUpdate();
                                    }
                                });
                            });
                            obModal = bootstrap.Modal.getOrCreateInstance(oModal[0]);
                            obModal.show();
                        }
                    }
                );

            });



            function lovd_updateModal (aOptions = {})
            {
                // Function that updates the modal.
                if (typeof oModal == "undefined") {
                    return false;
                }

                if ("size" in aOptions) {
                    // First, clear the class.
                    oModal.find(".modal-dialog").attr(
                        'class',
                        function(i, sClasses)
                        {
                            return sClasses.replace(/(^|\s)modal-[a-z]{2}(\s|$)/g, ' ');
                        }
                    );
                    if (["sm", "lg", "xl"].includes(aOptions.size)) {
                        oModal.find(".modal-dialog").addClass("modal-" + aOptions.size);
                    }
                }
                if ("title" in aOptions) {
                    oModal.find(".modal-title").html(aOptions.title);
                }
                if ("body" in aOptions) {
                    oModal.find(".modal-body").html(aOptions.body);
                }
                if ("buttons" in aOptions) {
                    if ($.isArray(aOptions.buttons) && aOptions.buttons.length) {
                        oModal.find(".modal-footer").html("").removeClass("d-none");
                        $.each(
                            aOptions.buttons,
                            function (i, aButton)
                            {
                                if ($.isArray(aButton) && aButton.length > 1) {
                                    oModal.find(".modal-footer").append('<button type="button" class="btn btn-' + aButton[0] + '">' + aButton[1] + '</button>');
                                }
                            }
                        );
                    } else if (oModal.find(".modal-footer").html().length) {
                        oModal.find(".modal-footer").html("").addClass("d-none");
                    }
                }
                if ("classes" in aOptions) {
                    // First, clear the classes.
                    oModal.find(".modal-content").attr(
                        'class',
                        function(i, sClasses)
                        {
                            return sClasses.replace(/(^|\s)(bg|border|text)-[a-z]+/g, '');
                        }
                    );
                    if ($.isArray(aOptions.classes) && aOptions.classes.length) {
                        oModal.find(".modal-content").addClass(aOptions.classes);
                    }
                }
            }
        </script>

<?php
}
?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
</body>
</html>
