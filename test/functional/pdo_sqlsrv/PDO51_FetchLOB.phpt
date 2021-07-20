--TEST--
PDO Fetch LOB Test
--DESCRIPTION--
Verification for LOB handling.
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require_once('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

try {
    $conn1 = connect();

    // Execute test
    $data = str_repeat('A', 255);
    $tableName = "pdo_test_table";
    fetchLob(1, $conn1, $tableName, "VARCHAR(512)", 1, $data);
    fetchLob(2, $conn1, $tableName, "NVARCHAR(512)", 2, $data);
    unset($data);

    $data = str_repeat('B', 4000);
    fetchLob(3, $conn1, $tableName, "VARCHAR(8000)", 3, $data);
    fetchLob(4, $conn1, $tableName, "NVARCHAR(4000)", 4, $data);
    unset($data);

    $data = str_repeat('C', 100000);
    fetchLob(5, $conn1, $tableName, "TEXT", 5, $data);
    fetchLob(6, $conn1, $tableName, "NTEXT", 6, $data);
    unset($data);

    // Cleanup
    dropTable($conn1, $tableName);
    unset($conn1);

    echo "Test 'PDO Statement - Fetch LOB' completed successfully.\n";
} catch (Exception $e) {
    echo $e->getMessage();
}

function fetchLob($offset, $conn, $table, $sqlType, $data1, $data2)
{
    $id = null;
    $label = null;

    createTable($conn, $table, array(new ColumnMeta("int", "id", "NOT NULL PRIMARY KEY"), "label" => $sqlType));
    insertRow($conn, $table, array("id" => $data1, "label" => $data2));

    // Check data fetched with PDO::FETCH_BOUND
    $stmt = $conn->query("SELECT * FROM [$table]");
    if (!$stmt->bindColumn(1, $id, PDO::PARAM_INT)) {
        logInfo($offset, "Cannot bind integer column");
    }
    if (!$stmt->bindColumn(2, $label, PDO::PARAM_LOB)) {
        logInfo($offset, "Cannot bind LOB column");
    }
    if (!$stmt->fetch(PDO::FETCH_BOUND)) {
        logInfo($offset, "Cannot fetch bound data");
    }
    if ($id != $data1) {
        logInfo($offset, "ID data corruption: [$id] instead of [$data1]");
    }
    if (PHP_VERSION_ID < 80100) {
        if ($label != $data2) {
            logInfo($offset, "Label data corruption: [$label] instead of [$data2]");
        }
    } else {
        if (!compareResourceToInput($label, $data2)) {
            logInfo($offset, "Label data corruption");
        }
    }
    unset($stmt);
    unset($label);

    // Check data fetched with PDO::FETCH_ASSOC
    $stmt = $conn->query("SELECT * FROM [$table]");
    $refData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($refData['id'] != $data1) {
        $id = $refData['id'];
        logInfo($offset, "ID data corruption: [$id] instead of [$data1]");
    }
    if ($refData['label'] != $data2) {
        $label = $refData['label'];
        logInfo($offset, "Label data corruption: [$label] instead of [$data2]");
    }
    unset($stmt);
    unset($refData);
}

function logInfo($offset, $msg)
{
    printf("[%03d] %s\n", $offset, $msg);
}

?>
--EXPECT--
Test 'PDO Statement - Fetch LOB' completed successfully.
