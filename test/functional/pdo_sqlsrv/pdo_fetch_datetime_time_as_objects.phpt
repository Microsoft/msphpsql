--TEST--
Test attribute PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE for date, time and datetime columns 
--DESCRIPTION--
Test attribute PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE for datetime, datetime2,
smalldatetime, datetimeoffset and time columns. The input values are based on current  
timestamp and they are retrieved either as strings or date time objects. Note that the
existing attributes ATTR_STRINGIFY_FETCHES and SQLSRV_ATTR_FETCHES_NUMERIC_TYPE
should have no effect on data retrieval.
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

function checkStringValues($obj, $columns, $values)
{
    $size = count($values);
    for ($i = 0; $i < $size; $i++) {
        $col = $columns[$i];
        switch ($i) {
            case 0:
                $val = $obj->c1; break;
            case 1:
                $val = $obj->c2; break;
            case 2:
                $val = $obj->c3; break;
            case 3:
                $val = $obj->c4; break;
            case 4:
                $val = $obj->c5; break;
            case 5:
                $val = $obj->c6; break;
            default:
                echo "Something went wrong!\n";
        }

        if ($val != $values[$i]) {
            echo "Expected $values[$i] for column $col but got: ";
            var_dump($val);
        } 
    }
}

function checkDTObjectValues($row, $columns, $values, $fetchStyle) 
{
    $size = count($values);
    
    for ($i = 0; $i < $size; $i++) {
        $col = $columns[$i];
        if ($fetchStyle == PDO::FETCH_ASSOC) {
            $dtObj = $row[$col];
        } else {
            // assume PDO::FETCH_BOTH
            $dtObj = $row[$i];
        }
        checkColumnDTValue($i, $col, $values, $dtObj);
    }
}

function checkColumnDTValue($index, $column, $values, $dtObj)
{
    // expected datetime value as a string
    $dtime = date_create($values[$index]);
    $dtExpected = $dtime->format('Y-m-d H:i:s.u');

    // actual datetime value from date time object to string
    $dtActual = date_format($dtObj, 'Y-m-d H:i:s.u');
    if ($dtActual != $dtExpected) {
        echo "Expected $dtExpected for column $column but the actual value was $dtActual\n";
    } 
}

function randomColumns($conn, $query, $columns, $values)
{
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $stmt = $conn->prepare($query);
    
    // Fetch a random column to trigger caching
    $lastCol = count($columns) - 1;
    $col = rand(0, $lastCol);
    $stmt->execute();
    $dtObj = $stmt->fetchColumn($col);
    checkColumnDTValue($col, $columns[$col], $values, $dtObj);

    // Similarly, fetch another column
    $col = (++$col) % count($columns);
    $stmt->execute();
    $dtObj = $stmt->fetchColumn($col);
    checkColumnDTValue($col, $columns[$col], $values, $dtObj);

    // Now fetch all columns in a backward order
    $i = $lastCol;
    do {
        $stmt->execute();
        $dtObj = $stmt->fetchColumn($i);
        checkColumnDTValue($i, $columns[$i], $values, $dtObj);
    } while (--$i >= 0);
}

function runTest($conn, $query, $columns, $values, $useBuffer = false)
{
    // fetch the date time values as strings or date time objects
    // prepare with or without buffered cursor 
    $options = array();
    if ($useBuffer) {
        $options = array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL, 
                         PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE => PDO::SQLSRV_CURSOR_BUFFERED);
    }
    
    // fetch_numeric off, fetch_datetime off 
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, false);
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, false);
    $stmt = $conn->prepare($query, $options);
    $stmt->execute();
    $obj = $stmt->fetch(PDO::FETCH_OBJ);
    checkStringValues($obj, $columns, $values);

    // fetch_numeric off, fetch_datetime on
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, false);
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $stmt = $conn->prepare($query, $options);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    checkDTObjectValues($row, $columns, $values, PDO::FETCH_ASSOC);

    // fetch_numeric on, fetch_datetime on
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, true);
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $stmt = $conn->prepare($query, $options);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_BOTH);
    checkDTObjectValues($row, $columns, $values, PDO::FETCH_BOTH);
   
    // ATTR_STRINGIFY_FETCHES should have no effect when fetching date time objects 
    // Setting it to true only converts numeric values to strings when fetching
    // See http://www.php.net/manual/en/pdo.setattribute.php for details
    // stringify on, fetch_numeric off, fetch_datetime on
    if (PHP_VERSION_ID < 80100) {
        // TODO: starting in PHP 8.1 with ATTR_STRINGIFY_FETCHES set to true
        // this fails with this error from PHP:
        // Fatal error: Uncaught Error: Object of class DateTime could not be converted to string
        $conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    }
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, false);
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $stmt = $conn->prepare($query, $options);
    $i = 0;
    do {
        $stmt->execute();
        $dtObj = $stmt->fetchColumn($i);
        checkColumnDTValue($i, $columns[$i], $values, $dtObj);
    } while (++$i < count($columns));
    
    // reset stringify to off
    // fetch_numeric off, fetch_datetime off
    $conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, false);
    $stmt = $conn->prepare($query, $options);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_OBJ);
    checkStringValues($obj, $columns, $values);
    
    // conn attribute fetch_datetime on, but statement attribute fetch_datetime off --
    // expected strings to be returned because statement attribute overrides the 
    // connection attribute
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $stmt = $conn->prepare($query, $options);
    $stmt->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, false);
    $stmt->execute();
    $obj = $stmt->fetch(PDO::FETCH_OBJ);
    checkStringValues($obj, $columns, $values);
    
    // conn attribute fetch_datetime unchanged, but statement attribute fetch_datetime on --
    // expected datetime objects to be returned (this time no need to prepare the statement)
    $stmt->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    checkDTObjectValues($row, $columns, $values, PDO::FETCH_ASSOC);

    // likewise, conn attribute fetch_datetime off, but statement attribute 
    // fetch_datetime on -- expected datetime objects to be returned
    $conn->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, false);
    $stmt = $conn->prepare($query, $options);
    $stmt->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_BOTH);
    checkDTObjectValues($row, $columns, $values, PDO::FETCH_BOTH);
    
    // conn attribute fetch_datetime unchanged, but statement attribute fetch_datetime off --
    // expected strings to be returned (again no need to prepare the statement)
    $stmt->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, false);
    $stmt->execute();
    $obj = $stmt->fetch(PDO::FETCH_LAZY);
    checkStringValues($obj, $columns, $values);
    
    // last test: set statement attribute fetch_datetime on with no change to 
    // prepared statement -- expected datetime objects to be returned
    $stmt->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, true);
    $i = 0;
    do {
        $stmt->execute();
        $dtObj = $stmt->fetchColumn($i);
        checkColumnDTValue($i, $columns[$i], $values, $dtObj);
    } while (++$i < count($columns));
    
    // keep the same settings but test with FETCH_BOUND
    for ($i = 0; $i < count($columns); $i++) {
        $dateObj = null;
        $stmt->execute();
        $stmt->bindColumn($i + 1, $dateObj, PDO::PARAM_LOB);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
        checkColumnDTValue($i, $columns[$i], $values, $dateObj);
    }
    
    // redo the test but with fetch_datetime off
    // expected strings to be returned
    $stmt->setAttribute(PDO::SQLSRV_ATTR_FETCHES_DATETIME_TYPE, false);
    for ($i = 0; $i < count($columns); $i++) {
        $dateStr = null;
        $stmt->execute();
        $stmt->bindColumn($i + 1, $dateStr);
        $row = $stmt->fetch(PDO::FETCH_BOUND);
        if ($dateStr != $values[$i]) {
            $col = $columns[$i];
            echo "Expected $values[$i] for column $col but the bound value was: ";
            var_dump($dateStr);
        } 
    }
}

try {
    date_default_timezone_set('America/Los_Angeles');

    $conn = connect();
    
    // Generate input values for the test table 
    $query = 'SELECT CONVERT(date, SYSDATETIME()), SYSDATETIME(), 
                     CONVERT(smalldatetime, SYSDATETIME()),
                     CONVERT(datetime, SYSDATETIME()), 
                     SYSDATETIMEOFFSET(), 
                     CONVERT(time, SYSDATETIME())';

    $stmt = $conn->query($query);
    $values = $stmt->fetch(PDO::FETCH_NUM);

    // create a test table with the above input date time values
    $tableName = "TestDateTimeOffset";
    $columns = array('c1', 'c2', 'c3', 'c4', 'c5', 'c6');
    $dataTypes = array('date', 'datetime2', 'smalldatetime', 'datetime', 'datetimeoffset', 'time');

    $colMeta = array(new ColumnMeta($dataTypes[0], $columns[0]),
                     new ColumnMeta($dataTypes[1], $columns[1]),
                     new ColumnMeta($dataTypes[2], $columns[2]),
                     new ColumnMeta($dataTypes[3], $columns[3]),
                     new ColumnMeta($dataTypes[4], $columns[4]),
                     new ColumnMeta($dataTypes[5], $columns[5]));
    createTable($conn, $tableName, $colMeta);

    $query = "INSERT INTO $tableName VALUES(?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    for ($i = 0; $i < count($columns); $i++) {
        $stmt->bindParam($i+1, $values[$i], PDO::PARAM_LOB);
    }
    $stmt->execute();

    $query = "SELECT * FROM $tableName";
    
    runtest($conn, $query, $columns, $values);
    runtest($conn, $query, $columns, $values, true);
    randomColumns($conn, $query, $columns, $values);
    
    dropTable($conn, $tableName);
    
    echo "Done\n";
    
    unset($stmt); 
    unset($conn);
} catch (PDOException $e) {
    var_dump($e->errorInfo);
}
?>
--EXPECT--
Done
