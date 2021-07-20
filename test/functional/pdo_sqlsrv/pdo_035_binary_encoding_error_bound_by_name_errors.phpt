--TEST--
GitHub Issue #35 binary encoding error when binding by name
--DESCRIPTION--
Based on pdo_035_binary_encoding_error_bound_by_name.phpt but this includes error checking for various encoding errors
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php

function bindTypeNoEncoding($conn, $sql, $input)
{
    try {
        $value = 1;
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $value, PDO::PARAM_INT, 0, PDO::SQLSRV_ENCODING_DEFAULT);
        $stmt->setAttribute(constant('PDO::SQLSRV_ATTR_ENCODING'), PDO::SQLSRV_ENCODING_BINARY);
        $stmt->bindParam(2, $input, PDO::PARAM_LOB);
        $stmt->execute();
        echo "bindTypeNoEncoding: expected to fail!\n";
    } catch (PDOException $e) {
        $error = '*An encoding was specified for parameter 1.  Only PDO::PARAM_LOB and PDO::PARAM_STR can take an encoding option.';
        if (!fnmatch($error, $e->getMessage())) {
            echo "Error message unexpected in bindTypeNoEncoding\n";
            var_dump($e->getMessage());
        }
    }
}

function bindDefaultEncoding($conn, $sql, $input)
{
    try {
        $value = 1;
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $value, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_DEFAULT);
        $stmt->setAttribute(constant('PDO::SQLSRV_ATTR_ENCODING'), PDO::SQLSRV_ENCODING_BINARY);
        $stmt->bindParam(2, $input, PDO::PARAM_LOB);
        $stmt->execute();
        echo "bindDefaultEncoding: expected to fail!\n";
    } catch (PDOException $e) {
        $error = '*Invalid encoding specified for parameter 1.';
        if (!fnmatch($error, $e->getMessage())) {
            echo "Error message unexpected in bindDefaultEncoding\n";
            var_dump($e->getMessage());
        }
    }
}

function insertData($conn, $sql, $input)
{
    try {
        $value = 1;
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $value);
        // Specify binary encoding for the second parameter only such that the first
        // parameter is unaffected
        $stmt->bindParam(2, $input, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
        $stmt->execute();
    } catch (PDOException $e) {
        echo "Error unexpected in insertData\n";
        var_dump($e->getMessage());
    }
}

function invalidEncoding1($conn, $sql)
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindColumn(1, $id, PDO::PARAM_INT, 0, PDO::SQLSRV_ENCODING_UTF8);
        $stmt->execute();
        $stmt->fetch(PDO::FETCH_BOUND);
        echo "invalidEncoding1: expected to fail!\n";
    } catch (PDOException $e) {
        $error = '*An encoding was specified for column 1.  Only PDO::PARAM_LOB and PDO::PARAM_STR column types can take an encoding option.';
        if (!fnmatch($error, $e->getMessage())) {
            echo "Error message unexpected in invalidEncoding1\n";
            var_dump($e->getMessage());
        }
    }
}

function invalidEncoding2($conn, $sql)
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindColumn('Value', $val1, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_DEFAULT);
        $stmt->execute();
        $stmt->fetch(PDO::FETCH_BOUND);
        echo "invalidEncoding2: expected to fail!\n";
    } catch (PDOException $e) {
        $error = '*Invalid encoding specified for column 1.';
        if (!fnmatch($error, $e->getMessage())) {
            echo "Error message unexpected in invalidEncoding2\n";
            var_dump($e->getMessage());
        }
    }
}

function invalidEncoding3($conn, $sql)
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindColumn(1, $id, PDO::PARAM_STR, 0, "dummy");
        $stmt->execute();
        $stmt->fetch(PDO::FETCH_BOUND);
        echo "invalidEncoding3: expected to fail!\n";
    } catch (PDOException $e) {
        $error = '*An invalid type or value was given as bound column driver data for column 1.  Only encoding constants such as PDO::SQLSRV_ENCODING_UTF8 may be used as bound column driver data.';
        if (!fnmatch($error, $e->getMessage())) {
            echo "Error message unexpected in invalidEncoding3\n";
            var_dump($e->getMessage());
        }
    }
}

try {
    require_once( "MsCommon_mid-refactor.inc" );

    // Connect
    $conn = connect();

    // Create a table
    $tableName = "testTableIssue35";
    createTable($conn, $tableName, array("ID" => "int", "Value" => "varbinary(max)"));

    // Insert data using bind parameters
    $sql = "INSERT INTO $tableName VALUES (?, ?)";
    $message = "This is to test github issue 35.";
    $value = base64_encode($message);
    
    // Errors expected
    bindTypeNoEncoding($conn, $sql, $value);
    bindDefaultEncoding($conn, $sql, $value);
    
    // No error expected
    insertData($conn, $sql, $value);

    // Fetch data, but test several invalid encoding issues (errors expected)
    $sql = "SELECT * FROM $tableName";
    invalidEncoding1($conn, $sql);
    invalidEncoding2($conn, $sql);
    invalidEncoding3($conn, $sql);

    // Now fetch it back
    $stmt = $conn->prepare("SELECT Value FROM $tableName");
    $stmt->bindColumn('Value', $val1, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
    $stmt->execute();
    $stmt->fetch(PDO::FETCH_BOUND);

    if (PHP_VERSION_ID < 80100) {
        var_dump($val1 === $value);
    } else {
        // $val1 is a stream object
        if (!feof($val1)) {
            $str = fread($val1, 8192);
            var_dump($str === $value);
        }
    }

    // Close connection
    dropTable($conn, $tableName);
    unset($stmt);
    unset($conn);
    print "Done\n";
} catch (PDOException $e) {
    var_dump($e->errorInfo);
}
?>
--EXPECT--
bool(true)
Done