<?php
/*******************************************************************************
 *
 * Web wrapper for Bas Voesenek's qPCR analysis script.
 *
 * Created     : 2023-03-22
 * Modified    : 2023-04-14
 *
 * Copyright   : 2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

function getVersion ()
{
    static $sVersion = '';

    if ($sVersion) {
        return $sVersion;
    }

    if (!defined('ROOT_PATH')) {
        return false;
    }

    // Try executing the Python script to get the version.
    @exec(
        'python3 ' . ROOT_PATH . '../qpcr_analysis.py --version 2>&1',
        $aOut,
        $nReturnCode
    );
    if (!$nReturnCode && $aOut && count($aOut) == 1) {
        $sVersion = substr(strstr($aOut[0], ' '), 1);
    }
    return $sVersion;
}





function lovd_cleanDirName ($s)
{
    // Cleans a given path by resolving a relative path.
    if (!is_string($s)) {
        // No input.
        return false;
    }

    // Clean up the pwd; remove '\' (some PHP versions under Windows seem to escape the slashes with backslashes???)
    $s = stripslashes($s);
    // Clean up the pwd; remove '//'
    $s = preg_replace('/\/+/', '/', $s);
    // Clean up the pwd; remove '/./'
    $s = preg_replace('/\/\.\//', '/', $s);
    // Clean up the pwd; remove '/dir/../'
    $s = preg_replace('/\/[^\/]+\/\.\.\//', '/', $s);
    // Hackers may try to give us links that start with a parent dir. That would cause an infinite loop.
    $s = preg_replace('/^\/\.\.\//', '/', $s);

    if (preg_match('/\/(\.)?\.\//', $s)) {
        // Still not clean... Pff...
        $s = lovd_cleanDirName($s);
    }

    return $s;
}





function lovd_convertIniValueToBytes ($sValue)
{
    // This function takes output from PHP's ini_get() function like "128M" or
    // "256k" and converts it to an integer, measured in bytes.
    // Implementation taken from the example on php.net.
    // FIXME; Implement proper checks here? Regexp?

    $nValue = (int) $sValue;
    $sLast = strtolower(substr($sValue, -1));
    switch ($sLast) {
        case 'g':
            $nValue *= 1024;
        case 'm':
            $nValue *= 1024;
        case 'k':
            $nValue *= 1024;
    }

    return $nValue;
}





function lovd_getInstallURL ($bFull = true)
{
    // Returns URL that can be used in URLs or redirects.
    // ROOT_PATH can be relative or absolute.
    return (!$bFull? '' : PROTOCOL . $_SERVER['HTTP_HOST']) .
        lovd_cleanDirName(substr(ROOT_PATH, 0, 1) == '/'? ROOT_PATH : dirname($_SERVER['SCRIPT_NAME']) . '/' . ROOT_PATH);
}





function lovd_getProjectFile ()
{
    // Gets project file name (file name including possible project subdirectory).
    // 2015-03-05; 3.0-13; When running an import, this function is called very often, so let's cache this function's results.
    static $sProjectFile;
    if ($sProjectFile) {
        return $sProjectFile;
    }

    $sDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; // /LOVDv.3.0/install/ or /
    $sProjectDir = lovd_cleanDirName($sDir . ROOT_PATH);        // /LOVDv.3.0/         or /
    $sDir = substr($sDir, strlen($sProjectDir) - 1);            // /install/           or /
    // You need to use SCRIPT_FILENAME here, because SCRIPT_NAME can lose the .php extension.
    $sProjectFile = $sDir . basename($_SERVER['SCRIPT_FILENAME']); // /install/index.php  or /variants.php
    return $sProjectFile;
}
?>
