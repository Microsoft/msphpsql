--TEST--
Test fetching varbinary, varchar, nvarchar max fields with client buffer
--DESCRIPTION--
Similar to sqlsrv_fetch_large_stream test but fetching varbinary, varchar, nvarchar max fields as strings with or without client buffer
--SKIPIF--
<?php require_once('skipif_mid-refactor.inc'); ?>
--ENV--
PHPT_EXEC=true
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

$tableName = 'pdoFetchLobTest';
$binaryColumn = 'varbinary_max';
$strColumn = 'varchar_max';
$nstrColumn = 'nvarchar_max';

$bin = 'abcdefghijklmnopqrstuvwxyz';
$binaryValue = str_repeat($bin, 100);
$hexValue = str_repeat(strtoupper(bin2hex($bin)), 100);
$strValue = str_repeat("stuvwxyz", 400);
$nstrValue = str_repeat("ÃÜðßZZýA©", 200);

function checkData($actual, $expected)
{
    if (PHP_VERSION_ID >= 80100 && is_resource($actual)) {
        $actual = fread($actual, 8192);
    }
    trace("Actual:\n$actual\n");

    $success = true;
    $pos = strpos($actual, $expected);
    if (($pos === false) || ($pos > 1)) {
        $success = false;
    }
    
    return ($success);
}

function fetchBinary($conn, $buffered)
{
    global $tableName, $binaryColumn, $binaryValue, $hexValue;
    
    try {
        $query = "SELECT $binaryColumn FROM $tableName";
        if ($buffered) {
            $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        } else {
            $stmt = $conn->prepare($query);
        }
        $stmt->bindColumn($binaryColumn, $value, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_BOUND);
    
        if (!checkData($value, $binaryValue)) {
            echo "Fetched binary value unexpected ($buffered): $value\n";
        }

        $stmt->bindColumn($binaryColumn, $value, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_SYSTEM);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_BOUND);

        if (!checkData($value, $hexValue)) {
            echo "Fetched binary value a char string ($buffered): $value\n";
        }

        $stmt->bindColumn($binaryColumn, $value, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_UTF8);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_BOUND);
    
        if (!checkData($value, $hexValue)) {
            echo "Fetched binary value as UTF-8 string ($buffered): $value\n";
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchBinary ($buffered):\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

function fetchAsString($conn, $buffered)
{
    global $tableName, $strColumn, $strValue;
    global $nstrColumn, $nstrValue;
    
    try {
        $query = "SELECT $strColumn, $nstrColumn FROM $tableName";
        if ($buffered) {
            $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        } else {
            $stmt = $conn->prepare($query);
        }
        $stmt->execute();
        
        $stmt->bindColumn($strColumn, $value1, PDO::PARAM_STR);
        $stmt->bindColumn($nstrColumn, $value2, PDO::PARAM_STR);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
    
        if (!checkData($value1, $strValue)) {
            echo "Fetched string value ($buffered): $value1\n";
        }
        
        if (!checkData($value2, $nstrValue)) {
            echo "Fetched string value ($buffered): $value2\n";
        }
        $stmt->execute();
        
        $stmt->bindColumn($strColumn, $value, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_SYSTEM);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
    
        if (!checkData($value, $strValue)) {
            echo "Fetched string value: $value\n";
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchBinary ($buffered):\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

try {
    $conn = connect();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create table of one max column
    $colMeta = array(new ColumnMeta('varbinary(max)', $binaryColumn),
                     new ColumnMeta('varchar(max)', $strColumn),
                     new ColumnMeta('nvarchar(max)', $nstrColumn));
    createTable($conn, $tableName, $colMeta);

    // Insert one row
    $query = "INSERT INTO $tableName ($binaryColumn, $strColumn, $nstrColumn) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $binaryValue, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
    $stmt->bindParam(2, $strValue, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_SYSTEM);
    $stmt->bindParam(3, $nstrValue, PDO::PARAM_STR);
    $stmt->execute();
    unset($stmt);

    // Starting fetching with or without client buffer
    fetchBinary($conn, false);
    fetchBinary($conn, true);
    
    fetchAsString($conn, false);
    fetchAsString($conn, true);
   
    dropTable($conn, $tableName);
    echo "Done\n";
    unset($conn);
} catch (PdoException $e) {
    echo $e->getMessage() . PHP_EOL;
}
?>
--EXPECT--
Done
