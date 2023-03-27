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
?>
