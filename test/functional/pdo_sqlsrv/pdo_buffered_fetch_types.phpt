--TEST--
Prepare with cursor buffered and fetch a variety of types converted to different types
--DESCRIPTION--
Test various conversion functionalites for buffered queries with PDO_SQLSRV.
--SKIPIF--
<?php require_once('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

$tableName = 'pdoFetchingClientBuffer';
$violation = 'Restricted data type attribute violation';
$outOfRange = 'Numeric value out of range';
$truncation = 'Fractional truncation';
$epsilon = 0.00001;

function fetchAsChar($conn, $tableName, $inputs)
{
    $query = "SELECT c1, c2, c3, c4, c5, c6 FROM $tableName";
    try {
        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        $stmt->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_SYSTEM);
        
        // Fetch all fields as strings - no conversion
        for ($i = 0; $i < count($inputs) - 1; $i++) {
            $stmt->execute();
            $f = $stmt->fetchColumn($i);
            
            if ($i == 2) {
                if (!compareFloats(floatval($inputs[$i]), floatval($f))) {
                    echo "In fetchAsChar ($i): expected $inputs[$i]\n";
                    var_dump($f);
                }
            } elseif ($f !== $inputs[$i]) {
                echo "In fetchAsChar ($i): expected $inputs[$i]\n";
                var_dump($f);
            }
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchAsChar:\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

function fetchAsUTF8($conn, $tableName, $inputs)
{
    $query = "SELECT * FROM $tableName";
    try {
        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));

        // Fetch all fields as UTF-8 strings
        for ($i = 0; $i < count($inputs); $i++) {
            $stmt->execute();
            $f = $stmt->fetchColumn($i);
            
            if ($i == 2) {
                if (!compareFloats(floatval($inputs[$i]), floatval($f))) {
                    echo "In fetchAsUTF8 ($i): expected $inputs[$i]\n";
                    var_dump($f);
                }
            } elseif ($f !== $inputs[$i]) {
                echo "In fetchAsUTF8 ($i): expected $inputs[$i]\n";
                var_dump($f);
            }
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchAsUTF8:\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

function fetchArray($conn, $tableName, $inputs)
{
    $query = "SELECT * FROM $tableName";
    try {
        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        $stmt->execute();
        
        // By default, even numeric or datetime fields are fetched as strings
        $result = $stmt->fetch(PDO::FETCH_NUM);
        for ($i = 0; $i < count($inputs); $i++) {
            if ($i == 2) {
                $expected = floatval($inputs[$i]);
                if (!compareFloats($expected, floatval($result[$i]))) {
                    echo "in fetchArray: for column $i expected $expected but got: ";
                    var_dump($result[$i]);
                }
            }
            elseif ($result[$i] !== $inputs[$i]) {
                echo "in fetchArray: for column $i expected $inputs[$i] but got: ";
                var_dump($result[$i]);
            }
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchArray:\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

function fetchBinaryAsNumber($conn, $tableName, $inputs)
{
    global $violation;

    $query = "SELECT c1 FROM $tableName";

    try {
        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED, PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE=>true));
        $stmt->execute();
        
        $stmt->bindColumn('c1', $binaryValue, PDO::PARAM_INT);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
        echo "in fetchBinaryAsNumber: exception should have been thrown!\n";
    } catch (PdoException $e) {
        // The varbinary field - expect the violation error
        if (strpos($e->getMessage(), $violation) === false) {
            echo "in fetchBinaryAsNumber: expected '$violation' but caught this:\n";
            echo $e->getMessage() . PHP_EOL;
        }
    }
}

function fetchBinaryAsBinary($conn, $tableName, $inputs)
{
    try {
        $query = "SELECT c1 FROM $tableName";
        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        $stmt->execute();
        
        $stmt->bindColumn('c1', $binaryValue, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
    
        if (PHP_VERSION_ID < 80100) {
            if ($binaryValue !== $inputs[0]) {
                echo "Fetched binary value unexpected: $binaryValue\n";
            }
        } else {
            if (!compareResourceToInput($binaryValue, $inputs[0])) {
                echo "Fetched binary value unexpected\n";
            }
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchBinaryAsBinary:\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

function fetchFloatAsInt($conn, $tableName)
{
    global $truncation;

    try {
        $query = "SELECT c3 FROM $tableName";
        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        $stmt->execute();

        $stmt->bindColumn('c3', $floatValue, PDO::PARAM_INT);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
        
        // This should return SQL_SUCCESS_WITH_INFO with the truncation error
        $info = $stmt->errorInfo();
        if ($info[0] != '01S07' || $info[2] !== $truncation) {
            print_r($stmt->errorInfo());
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchFloatAsInt:\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

function fetchCharAsInt($conn, $tableName, $column)
{
    global $outOfRange;

    try {
        $query = "SELECT $column FROM $tableName";
        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        $stmt->execute();

        $stmt->bindColumn($column, $value, PDO::PARAM_INT);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
        
        echo "in fetchCharAsInt: exception should have been thrown!\n";
    } catch (PdoException $e) {
        // The (n)varchar field - expect the outOfRange error
        if (strpos($e->getMessage(), $outOfRange) === false) {
            echo "in fetchCharAsInt ($column): expected '$outOfRange' but caught this:\n";
            echo $e->getMessage() . PHP_EOL;
        }
    }
}

function fetchAsNumerics($conn, $tableName, $inputs)
{
    // The following calls expect different errors
    fetchFloatAsInt($conn, $tableName);
    fetchCharAsInt($conn, $tableName, 'c6');
    fetchCharAsInt($conn, $tableName, 'c7');
    
    // The following should work
    try {
        $query = "SELECT c2, c4 FROM $tableName";
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        $stmt->execute();
            
        $stmt->bindColumn('c2', $intValue, PDO::PARAM_INT);
        $stmt->bindColumn('c4', $decValue, PDO::PARAM_INT);
            
        $row = $stmt->fetch(PDO::FETCH_BOUND);
        
        if ($intValue !== intval($inputs[1])) {
            var_dump($intValue);
        }
        if ($decValue !== intval($inputs[3])) {
            var_dump($decValue);
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchAsNumerics:\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

function fetchNumbers($conn, $tableName, $inputs)
{
    // Fetch integers and floats as numbers, not strings
    try {
        $query = "SELECT c2, c3, c4 FROM $tableName";
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

        $stmt = $conn->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
        $stmt->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, true);
        $stmt->execute();
                     
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row[0] !== intval($inputs[1])) {
            var_dump($row[0]);
        }
        $expected = floatval($inputs[2]);
        if (!compareFloats($expected, $row[1])) {
            echo "in fetchNumbers: expected $expected but got: ";
            var_dump($row[1]);
        }
        if ($row[2] !== $inputs[3]) {
            var_dump($row[2]);
        }
    } catch (PdoException $e) {
        echo "Caught exception in fetchAsNumerics:\n";
        echo $e->getMessage() . PHP_EOL;
    }
}

try {
    $conn = connect();
    $conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    
    $columns = array('c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c7');
    $types = array('varbinary(10)', 'int', 'float(53)', 'decimal(16, 6)', 'datetime2', 'varchar(50)', 'nvarchar(50)');
    $inputs = array('abcdefghij', '34567', '9876.5432', '123456789.012340', '2020-02-02 20:20:20.2220000', 'This is a test', 'Şơмė śäოрŀề');

    // Create table
    $colMeta = array(new ColumnMeta($types[0], $columns[0]),
                     new ColumnMeta($types[1], $columns[1]),
                     new ColumnMeta($types[2], $columns[2]),
                     new ColumnMeta($types[3], $columns[3]),
                     new ColumnMeta($types[4], $columns[4]),
                     new ColumnMeta($types[5], $columns[5]),
                     new ColumnMeta($types[6], $columns[6]));
    createTable($conn, $tableName, $colMeta);

    // Prepare the input values and insert one row
    $query = "INSERT INTO $tableName VALUES(?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    for ($i = 0; $i < count($columns); $i++) {
        if ($i == 0) {
            $stmt->bindParam($i+1, $inputs[$i], PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
        } else {
            $stmt->bindParam($i+1, $inputs[$i]);
        }
    }
    $stmt->execute();
    unset($stmt);

    // Starting fetching using client buffers
    fetchAsChar($conn, $tableName, $inputs);
    fetchAsUTF8($conn, $tableName, $inputs);
    fetchArray($conn, $tableName, $inputs);
    fetchBinaryAsNumber($conn, $tableName, $inputs);
    fetchBinaryAsBinary($conn, $tableName, $inputs);
    fetchAsNumerics($conn, $tableName, $inputs);
    fetchNumbers($conn, $tableName, $inputs);
    
    // dropTable($conn, $tableName);
    echo "Done\n";
    unset($conn);
} catch (PdoException $e) {
    echo $e->getMessage() . PHP_EOL;
}
?>
--EXPECT--
Done