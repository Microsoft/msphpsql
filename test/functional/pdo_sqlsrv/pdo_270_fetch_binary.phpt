--TEST--
Test fetch from binary, varbinary, varbinary(max), image columns, without setting binary encoding.
--DESCRIPTION--
Verifies GitHub issue 270 is fixed, users could not retrieve the data as inserted in binary columns without setting the binary encoding either on stmt or using bindCoulmn encoding.
This test verifies that the data inserted in binary columns can be retrieved using fetch, fetchColumn, fetchObject, and fetchAll functions.
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

$tableName = 'test_binary'.rand();
$columns = array('col1', 'col2', 'col3');

try {
    // Connect
    $conn = connect();

    $colmeta_arr = array($columns[0] => "binary(50)", $columns[1] => "varbinary(50)", $columns[2] => "varbinary(max)");
    $icon = base64_decode("This is some text to test retrieving from binary type columns");
    $inputs = array($columns[0] => new BindParamOp(1, $icon, "PDO::PARAM_LOB", null, "PDO::SQLSRV_ENCODING_BINARY"),
                    $columns[1] => new BindParamOp(2, $icon, "PDO::PARAM_LOB", null, "PDO::SQLSRV_ENCODING_BINARY"),
                    $columns[2] => new BindParamOp(3, $icon, "PDO::PARAM_LOB", null, "PDO::SQLSRV_ENCODING_BINARY"));

    if (!isColEncrypted()) {
        // image is not supported for encryption
        array_push($columns, 'col4');
        $colmeta_arr += array($columns[3] => "image");
        $inputs += array( $columns[3] => new BindParamOp(4, $icon, "PDO::PARAM_LOB", null, "PDO::SQLSRV_ENCODING_BINARY"));
    }
    
    createTable($conn, $tableName, $colmeta_arr);

    // Insert data using bind parameters
    insertRow($conn, $tableName, $inputs, "prepareBindParam");

    // loop through each column in the table
    foreach ($columns as $col) {
        testFetch($conn, $tableName, $col, $icon);
    }
    // DROP table
    dropTable($conn, $tableName);

    //free statement and connection
    unset($stmt);
    unset($conn);

    print_r("Test finished successfully\n");
} catch (PDOException $e) {
    var_dump($e->errorInfo);
}

function verifyBinaryResult($result, $input, $len, $message)
{
    if (PHP_VERSION_ID < 80100) {
        if (strncmp($result, $input, $len) !== 0) {
            print_r($message);
        }
    } else {
        if (!feof($result)) {
            $str = fread($result, $len);
            if (strncmp($str, $input, $len) !== 0) {
                print_r($message);
            }
        }
    }
}

//calls various fetch methods
function testFetch($conn, $tableName, $columnName, $input)
{
    $len = strlen($input);
    $result = "";
    $sql = "SELECT $columnName from $tableName";
    $stmt = $conn->query($sql);
    $stmt->bindColumn(1, $result, PDO::PARAM_LOB);
    $stmt->fetch(PDO::FETCH_BOUND);
    //binary is fixed size, to evaluate output, compare it using strncmp
    verifyBinaryResult($result, $input, $len, "\nRetrieving using bindColumn failed");

    $result = "";
    $stmt = $conn->query($sql);
    $stmt->bindColumn(1, $result, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
    $stmt->fetch(PDO::FETCH_BOUND);
    verifyBinaryResult($result, $input, $len, "\nRetrieving using bindColumn with encoding set failed");

    $result = "";
    $stmt = $conn->query($sql);
    $result = $stmt->fetchColumn();
    if (strncmp($result, $input, $len) !== 0) {
        print_r("\nRetrieving using fetchColumn failed");
    }

    $result = "";
    $stmt = $conn->query($sql);
    $result = $stmt->fetchObject();
    if (strncmp($result->$columnName, $input, $len) !== 0) {
        print_r("\nRetrieving using fetchObject failed");
    }

    $result = "";
    $stmt = $conn->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (strncmp($result[0], $input, $len) !== 0) {
        print_r("\nRetrieving using fetchAll failed");
    }
    unset($stmt);
}
?>
--EXPECT--
Test finished successfully
