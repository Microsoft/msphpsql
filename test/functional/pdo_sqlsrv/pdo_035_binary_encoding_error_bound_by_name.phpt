--TEST--
GitHub Issue #35 binary encoding error when binding by name
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
try {
    require_once( "MsCommon_mid-refactor.inc" );

    // Connect
    $conn = connect();

    // Create a table
    $tableName = "testTableIssue35";
    createTable($conn, $tableName, array("Value" => "varbinary(max)"));

    // Insert data using bind parameters
    $sql = "INSERT INTO $tableName VALUES (?)";
    $stmt = $conn->prepare($sql);
    $message = "This is to test github issue 35.";
    $value = base64_encode($message);
    
    $stmt->setAttribute(constant('PDO::SQLSRV_ATTR_ENCODING'), PDO::SQLSRV_ENCODING_BINARY);
    $stmt->bindParam(1, $value, PDO::PARAM_LOB); 
    $result = $stmt->execute();

    // fetch it back
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