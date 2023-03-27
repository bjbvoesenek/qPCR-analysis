<?php
/*******************************************************************************
 *
 * Web wrapper for Bas Voesenek's qPCR analysis script.
 *
 * Created     : 2023-03-22
 * Modified    : 2023-03-27
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
}
?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
</body>
</html>
