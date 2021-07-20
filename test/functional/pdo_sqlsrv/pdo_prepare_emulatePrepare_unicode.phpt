--TEST--
prepare with emulate prepare and binding uft8 characters
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once('MsCommon_mid-refactor.inc');

function prepareStmt($conn, $query, $prepareOptions = array(), $dataType = PDO::PARAM_STR, $length = 0, $driverOptions = null)
{
    $name = "가각";
    if (!isColEncrypted()) {
        $stmt = $conn->prepare($query, $prepareOptions);
        $stmt->bindParam(':name', $name, $dataType, $length, $driverOptions);
    } else {
        $status = 1;
        $stmt = $conn->prepare($query, $prepareOptions);
        $stmt->bindParam(':name', $name, $dataType, $length, $driverOptions);
        $stmt->bindParam(':status', $status);
    }
    $stmt->execute();
    return $stmt;
}

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);

    $tableName = "users";
    createTable($conn, $tableName, array("name" => "nvarchar(max)", "status" => "int", "age" => "int"));

    if (!isColEncrypted()) {
        $conn->exec("INSERT INTO [$tableName] (name, status, age) VALUES (N'Belle', 1, 34)");
        $conn->exec("INSERT INTO [$tableName] (name, status, age) VALUES (N'Абрам', 1, 40)");
        $conn->exec("INSERT INTO [$tableName] (name, status, age) VALUES (N'가각', 1, 30)");
        $query = "SELECT * FROM [$tableName] WHERE name = :name AND status = 1";
    } else {
        insertRow($conn, $tableName, array("name" => "Belle", "status" => 1, "age" => 34));
        insertRow($conn, $tableName, array("name" => "Абрам", "status" => 1, "age" => 40));
        insertRow($conn, $tableName, array("name" => "가각", "status" => 1, "age" => 30));
        $query = "SELECT * FROM [$tableName] WHERE name = :name AND status = :status";
    }

    //without emulate prepare
    print_r("Prepare without emulate prepare:\n");
    $stmt = prepareStmt($conn, $query, array(), PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_UTF8);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);

    //with emulate prepare and no bind param options
    print_r("Prepare with emulate prepare and no bindParam options:\n");
    if (!isAEConnected()) {
        $options = array(PDO::ATTR_EMULATE_PREPARES => true);
    } else {
        $options = array(PDO::ATTR_EMULATE_PREPARES => false);
    }
    $stmt = prepareStmt($conn, $query, $options);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);
    if ($stmt->rowCount() == 0) {
        print_r("No results for this query\n");
    }

    //with emulate prepare and SQLSRV_ENCODING_UTF8
    print_r("Prepare with emulate prepare and SQLSRV_ENCODING_UTF8:\n");
    $stmt = prepareStmt($conn, $query, $options, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_UTF8);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);

    //with emulate prepare and SQLSRV_ENCODING_SYSTEM
    print_r("Prepare with emulate prepare and and SQLSRV_ENCODING_SYSTEM:\n");
    $stmt = prepareStmt($conn, $query, $options, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_SYSTEM);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // The combination of Column Encryption and Unix platforms support SQLSRV_ENCODING_SYSTEM because:
    // With Column Encryption enabled, binding parameters uses exact datatypes as the column definition
    // the default encoding in Linux and Mac is UTF8
    $success = true;
    if (!(strtoupper( substr( php_uname( 's' ),0,3 ) ) === 'WIN') && isAEConnected()) {
        if ($row['name'] != "가각" || $row['status'] != 1 || $row['age'] != 30) {
            print_r("Incorrect results retrieved.\n");
            $success = false;
        }
    } else {
        // the default encoding in Windows is non-UTF8, thus binding UTF8 parameters does not work
        if ($stmt->rowCount() != 0) {
            print_r("Binding UTF8 data when encoding is SQLSRV_ENCODING_SYSTEM should not work.\n");
            $success = false;
        }
    }
    if ($success) {
        print_r("Binding UTF8 data with SQLSRV_ENCODING_SYSTEM is tested successfully.\n");
    }

    //with emulate prepare and encoding SQLSRV_ENCODING_BINARY
    print_r("Prepare with emulate prepare and encoding SQLSRV_ENCODING_BINARY:\n");
    $stmt = prepareStmt($conn, $query, $options, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_BINARY);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);
    if ($stmt->rowCount() == 0) {
        print_r("No results for this query\n");
    }

    dropTable($conn, $tableName);
    unset($stmt);
    unset($conn);
} catch (PDOException $e) {
    var_dump($e->errorInfo);
}
?>

--EXPECT--
Prepare without emulate prepare:
Array
(
    [name] => 가각
    [status] => 1
    [age] => 30
)
Prepare with emulate prepare and no bindParam options:
No results for this query
Prepare with emulate prepare and SQLSRV_ENCODING_UTF8:
Array
(
    [name] => 가각
    [status] => 1
    [age] => 30
)
Prepare with emulate prepare and and SQLSRV_ENCODING_SYSTEM:
Binding UTF8 data with SQLSRV_ENCODING_SYSTEM is tested successfully.
Prepare with emulate prepare and encoding SQLSRV_ENCODING_BINARY:
No results for this query
