--TEST--
Test for inserting and retrieving encrypted data of datetime types
--DESCRIPTION--
Bind output/inout params using sqlsrv_prepare with all sql_type
--SKIPIF--
<?php require('skipif_versions_old.inc'); ?>
--FILE--
<?php
require_once('MsCommon.inc');
require_once('AEData.inc');

date_default_timezone_set("Canada/Pacific");
$dataTypes = array("date", "datetime", "datetime2", "smalldatetime", "time", "datetimeoffset");

$directions = array(SQLSRV_PARAM_OUT, SQLSRV_PARAM_INOUT);

// this is a list of implicit datatype conversion that SQL Server allows (https://docs.microsoft.com/en-us/sql/t-sql/data-types/data-type-conversion-database-engine)
$compatList = array("date" => array( "SQLSRV_SQLTYPE_CHAR", "SQLSRV_SQLTYPE_VARCHAR", "SQLSRV_SQLTYPE_NCHAR", "SQLSRV_SQLTYPE_NVARCHAR", "SQLSRV_SQLTYPE_DATETIME", "SQLSRV_SQLTYPE_SMALLDATETIME", "SQLSRV_SQLTYPE_DATE", "SQLSRV_SQLTYPE_DATETIMEOFFSET", "SQLSRV_SQLTYPE_DATETIME2"),
                    "datetime" => array( "SQLSRV_SQLTYPE_CHAR", "SQLSRV_SQLTYPE_VARCHAR", "SQLSRV_SQLTYPE_NCHAR", "SQLSRV_SQLTYPE_NVARCHAR", "SQLSRV_SQLTYPE_DATETIME", "SQLSRV_SQLTYPE_SMALLDATETIME", "SQLSRV_SQLTYPE_DATE", "SQLSRV_SQLTYPE_TIME", "SQLSRV_SQLTYPE_DATETIMEOFFSET", "SQLSRV_SQLTYPE_DATETIME2"),
                    "datetime2" => array( "SQLSRV_SQLTYPE_CHAR", "SQLSRV_SQLTYPE_VARCHAR", "SQLSRV_SQLTYPE_NCHAR", "SQLSRV_SQLTYPE_NVARCHAR", "SQLSRV_SQLTYPE_DATETIME", "SQLSRV_SQLTYPE_SMALLDATETIME", "SQLSRV_SQLTYPE_DATE", "SQLSRV_SQLTYPE_TIME", "SQLSRV_SQLTYPE_DATETIMEOFFSET", "SQLSRV_SQLTYPE_DATETIME2"),
                    "smalldatetime" => array( "SQLSRV_SQLTYPE_CHAR", "SQLSRV_SQLTYPE_VARCHAR", "SQLSRV_SQLTYPE_NCHAR", "SQLSRV_SQLTYPE_NVARCHAR", "SQLSRV_SQLTYPE_DATETIME", "SQLSRV_SQLTYPE_SMALLDATETIME", "SQLSRV_SQLTYPE_DATE", "SQLSRV_SQLTYPE_TIME", "SQLSRV_SQLTYPE_DATETIMEOFFSET", "SQLSRV_SQLTYPE_DATETIME2"),
                    "time" => array( "SQLSRV_SQLTYPE_CHAR", "SQLSRV_SQLTYPE_VARCHAR", "SQLSRV_SQLTYPE_NCHAR", "SQLSRV_SQLTYPE_NVARCHAR", "SQLSRV_SQLTYPE_DATETIME", "SQLSRV_SQLTYPE_SMALLDATETIME", "SQLSRV_SQLTYPE_TIME", "SQLSRV_SQLTYPE_DATETIMEOFFSET", "SQLSRV_SQLTYPE_DATETIME2"),
                    "datetimeoffset" => array("SQLSRV_SQLTYPE_CHAR", "SQLSRV_SQLTYPE_VARCHAR", "SQLSRV_SQLTYPE_NCHAR", "SQLSRV_SQLTYPE_NVARCHAR", "SQLSRV_SQLTYPE_DATETIMEOFFSET") );

function isCompatible($dataType, $sqlType)
{
    global $compatList;
    
    foreach ($compatList[$dataType] as $compatType) {
        if (stripos($compatType, $sqlType) !== false) {
            return true;
        }
    }

    return false;
}

function testOutputParam($conn, $spname, $direction, $dataType, $sqlType)
{
    // The driver does not support these types as output params, simply return
    if (isDateTimeType($sqlType) || isLOBType($sqlType)) {
        return;
    }
    
    $sqlTypeConstant = get_sqlType_constant($sqlType);
        
    // Call store procedure
    $outSql = AE\getCallProcSqlPlaceholders($spname, 2);
    
    // Set these to NULL such that the PHP type of each output parameter is inferred
    // from the SQLSRV_SQLTYPE_* constant
    $c_detOut = null;
    $c_randOut = null;
    $stmt = sqlsrv_prepare(
        $conn,
        $outSql,
        array(array( &$c_detOut, $direction, null, $sqlTypeConstant),
        array(&$c_randOut, $direction, null, $sqlTypeConstant ))
    );
    if (!$stmt) {
        die(print_r(sqlsrv_errors(), true));
    }
    sqlsrv_execute($stmt);
    
    $success = false;
    $errors = sqlsrv_errors();
    if (AE\IsDataEncrypted()) {
        // With data encrypted, errors are totally expected
        if (empty($errors)) {
            echo "Encrypted data: $dataType should NOT be compatible with $sqlType\n";
        } else {
            // All should return 22018, the SQLSTATE for any incompatible conversion,
            // except the XML type
            if ($errors[0]['SQLSTATE'] !== '22018') {
                if ($sqlType === 'SQLSRV_SQLTYPE_XML' && $errors[0]['SQLSTATE'] === '42000') {
                    $success = true;
                } else {
                    echo "Encrypted data: unexpected errors with SQL type: $sqlType\n";
                    var_dump($errors);
                }
            } else {
                $success = true;
            }
        }
    } else {
        $compatible = isCompatible($dataType, $sqlType);
        
        if ($compatible) {
            if (!empty($errors)) {
                echo "$dataType should be compatible with $sqlType.\n";
                var_dump($errors);
            }
        } else {
            $implicitConv = 'Implicit conversion from data type ';

            // 22018 is the SQLSTATE for any incompatible conversion errors
            if ($errors[0]['SQLSTATE'] === '22018') {
                $success = true;
            } elseif (strpos($errors[0]['message'], $implicitConv) !== false) {
                $success = true;
            } else {
                echo "Failed with SQL type: $sqlType\n";
                var_dump($errors);
            }
        }
    }
    
    return $success;
}

////////////////////////////////////////////////////////////////////////////////////////

$conn = AE\connect();

foreach ($dataTypes as $dataType) {
    echo "\nTesting $dataType:\n";
    $success = true;

    // create table
    $tbname = GetTempTableName("", false);
    $colMetaArr = array(new AE\ColumnMeta($dataType, "c_det"), new AE\ColumnMeta($dataType, "c_rand", null, false));
    AE\createTable($conn, $tbname, $colMetaArr);

    // Create a Store Procedure
    $spname = 'selectAllColumns';
    createProc($conn, $spname, "@c_det $dataType OUTPUT, @c_rand $dataType OUTPUT", "SELECT @c_det = c_det, @c_rand = c_rand FROM $tbname");

    // insert a row
    $inputValues = array_slice(${explode("(", $dataType)[0] . "_params"}, 1, 2);
    $r;
    $stmt = AE\insertRow($conn, $tbname, array( $colMetaArr[0]->colName => $inputValues[0], $colMetaArr[1]->colName => $inputValues[1] ), $r);
    if ($r === false) {
        is_incompatible_types_error($dataType, "default type");
    }

    foreach ($directions as $direction) {
        $dir = ($direction == SQLSRV_PARAM_OUT) ? 'SQLSRV_PARAM_OUT' : 'SQLSRV_PARAM_INOUT';
        echo "Testing as $dir:\n";
        
        // test each SQLSRV_SQLTYPE_* constants
        foreach ($sqlTypes as $sqlType) {
            $success = testOutputParam($conn, $spname, $direction, $dataType, $sqlType);
        }
    }
    
    // cleanup
    sqlsrv_free_stmt($stmt);
    sqlsrv_query($conn, "TRUNCATE TABLE $tbname");
    
    if ($success) {
        echo "Test successfully done.\n";
    }
    
    dropProc($conn, $spname);
    dropTable($conn, $tbname);
}

sqlsrv_close($conn);
?>
--EXPECT--

Testing date:
Testing as SQLSRV_PARAM_OUT:
Testing as SQLSRV_PARAM_INOUT:
Test successfully done.

Testing datetime:
Testing as SQLSRV_PARAM_OUT:
Testing as SQLSRV_PARAM_INOUT:
Test successfully done.

Testing datetime2:
Testing as SQLSRV_PARAM_OUT:
Testing as SQLSRV_PARAM_INOUT:
Test successfully done.

Testing smalldatetime:
Testing as SQLSRV_PARAM_OUT:
Testing as SQLSRV_PARAM_INOUT:
Test successfully done.

Testing time:
Testing as SQLSRV_PARAM_OUT:
Testing as SQLSRV_PARAM_INOUT:
Test successfully done.

Testing datetimeoffset:
Testing as SQLSRV_PARAM_OUT:
Testing as SQLSRV_PARAM_INOUT:
Test successfully done.
