--TEST--
Test warnings on connection and statement levels
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php

$counter = 0;

// When testing with PHP 8.1-dev it throws different warning messages. Implement a custom 
// warning handler such that when testing with previous PHP versions, the warnings are 
// handled and verified differently.
function warningHandler($errno, $errstr) 
{ 
    global $counter;

    $warnings = array("An unsupported attribute was designated on the PDO object.",
                      "Driver does not support this function: driver does not support that attribute");

    if (PHP_VERSION_ID < 80100) {
        $str = strstr($errstr, $warnings[$counter++]);
    } else {
        $str = strstr($errstr, $warnings[1]);
    }
    if ($str == false) {
        echo "Unexpected warning message ($counter):";
        var_dump($errstr);
    }
}

try {
    require_once("MsCommon_mid-refactor.inc");

    set_error_handler("warningHandler", E_WARNING);
    $conn = connect("", array(), PDO::ERRMODE_WARNING);
    // raise a warning in connection
    $conn->getAttribute(PDO::ATTR_TIMEOUT);
    restore_error_handler();

    $tbname = "table1";
    dropTable($conn, $tbname);

    // raise a warning in statement
    $statement = $conn->prepare("CRATE TABLE table1(id INT NOT NULL PRIMARY KEY, val VARCHAR(10))");
    $statement->execute();

    unset($statement);
    unset($conn);
} catch (PDOException $e) {
    var_dump($e->errorInfo);
}
?>
--EXPECTREGEX--
Warning: PDOStatement::execute\(\): SQLSTATE\[42000\]: Syntax error or access violation: 156 \[Microsoft\]\[ODBC Driver 1[1-9] for SQL Server\]\[SQL Server\]Incorrect syntax near the keyword 'TABLE'\. in .+(\/|\\)pdo_warnings\.php on line [0-9]+
