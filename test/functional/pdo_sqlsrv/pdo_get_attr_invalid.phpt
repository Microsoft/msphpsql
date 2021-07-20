--TEST--
Test getting invalid attributes
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php

require_once("MsCommon_mid-refactor.inc");

// When testing with PHP 8.1-dev, pdo_sqlsrv handles unsupported attribute differently. 
// Implement a custom warning handler such that this test works with previous PHP versions as well.
function warningHandler($errno, $errstr) 
{
    $warning = "Driver does not support this function: driver does not support that attribute";
    $str = strstr($errstr, $warning);
    if ($str == false) {
        echo "Unexpected warning message:";
        var_dump($errstr);
    }
}

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);

    set_error_handler("warningHandler", E_WARNING);
    @$conn->getAttribute(PDO::ATTR_FETCH_TABLE_NAMES);
    
    // Starting with PHP 8.1-dev getting an unsupported attribute pdo_sqlsrv will no longer 
    // throw an exception. PHP PDO will handle the warning instead.
    if (PHP_VERSION_ID < 80100) {
        $errmsg = ($conn->errorInfo())[2];
        if ($errmsg !== "An unsupported attribute was designated on the PDO object.") {
            var_dump($conn->errorInfo());
        }
    }
    restore_error_handler();

    @$conn->getAttribute(PDO::ATTR_CURSOR);
    print_r(($conn->errorInfo())[2]);
    echo "\n";

    @$conn->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    print_r(($conn->errorInfo())[2]);
    echo "\n";
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
--EXPECT--
The given attribute is only supported on the PDOStatement object.
An invalid attribute was designated on the PDO object.
