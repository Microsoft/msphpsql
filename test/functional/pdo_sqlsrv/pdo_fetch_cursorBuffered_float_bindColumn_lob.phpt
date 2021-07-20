--TEST--
prepare with cursor buffered and fetch a float column with the column bound and specified to type LOB
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
function FlatsAreEqual($a, $b, $epsilon = 3.9265E-6)
{
    return (abs($a - $b) < $epsilon);
}
require_once("MsCommon_mid-refactor.inc");

try {
    $conn = connect();
    $sample = 1234567890.1234;

    $tbname = "TESTTABLE";
    createTable($conn, $tbname, array("exist" => "float(53)"));

    $query = "INSERT INTO $tbname VALUES(:p0)";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':p0', $sample, PDO::PARAM_INT);
    $stmt->execute();

    $query = "SELECT exist FROM $tbname";

    //prepare with no buffered cursor
    print "no buffered cursor, stringify off, fetch_numeric off\n"; //stringify and fetch_numeric is off by default
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    if (PHP_VERSION_ID >= 80100) {
        $float_col = fread($float_col, 8192);
    }
    var_dump($float_col);

    print "\nno buffered cursor, stringify off, fetch_numeric on\n";
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, true);
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    if (PHP_VERSION_ID >= 80100) {
        $float_col = fread($float_col, 8192);
    }
    var_dump($float_col);

    print "\nno buffered cursor, stringify on, fetch_numeric on\n";
    $conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    var_dump($float_col);

    print "\nno buffered cursor, stringify on, fetch_numeric off\n";
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, false);
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    var_dump($float_col);

    //prepare with client buffered cursor
    print "\nbuffered cursor, stringify off, fetch_numeric off\n";
    $conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE => PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    if (PHP_VERSION_ID >= 80100) {
        $float_col = fread($float_col, 8192);
    }
    var_dump($float_col);

    print "\nbuffered cursor, stringify off, fetch_numeric on\n";
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, true);
    $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE => PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    if (PHP_VERSION_ID >= 80100) {
        $float_col = fread($float_col, 8192);
    }
    var_dump($float_col);

    print "\nbuffered cursor, stringify on, fetch_numeric on\n";
    $conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE => PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    var_dump($float_col);

    print "\nbuffered cursor, stringify on, fetch_numeric off\n";
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, false);
    $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE => PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->execute();
    $stmt->bindColumn('exist', $float_col, PDO::PARAM_LOB);
    $value = $stmt->fetch();
    var_dump($float_col);

    dropTable($conn, $tbname);
    unset($stmt);
    unset($conn);
} catch (PDOException $e) {
    var_dump($e->errorInfo);
}
?>
--EXPECT--
no buffered cursor, stringify off, fetch_numeric off
string(15) "1234567890.1234"

no buffered cursor, stringify off, fetch_numeric on
string(15) "1234567890.1234"

no buffered cursor, stringify on, fetch_numeric on
string(15) "1234567890.1234"

no buffered cursor, stringify on, fetch_numeric off
string(15) "1234567890.1234"

buffered cursor, stringify off, fetch_numeric off
string(15) "1234567890.1234"

buffered cursor, stringify off, fetch_numeric on
string(15) "1234567890.1234"

buffered cursor, stringify on, fetch_numeric on
string(15) "1234567890.1234"

buffered cursor, stringify on, fetch_numeric off
string(15) "1234567890.1234"
