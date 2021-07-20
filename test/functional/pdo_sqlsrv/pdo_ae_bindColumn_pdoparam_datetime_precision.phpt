--TEST--
Test for retrieving encrypted data from datetime types columns with different precisions using PDO::bindColumn
--DESCRIPTION--
Test conversion from datetime types column to output of PDO::PARAM types
With or without Always Encrypted, conversion works if:
1. From any datetime type column to PDO::PARAM_STR
2. From any datetime type column to PDO::PARAM_LOB
TODO: cannot insert into a datetime2(0) using the PDO_SQLSRV driver
      returns operand type clash error between smalldatetime and datetime2(0)
      to see error, uncomment 0 from the $precision array
      documented in VSO 2693
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("AEData.inc");

function compareDate($dtout, $dtin, $dataType) {
    if ($dataType == "datetimeoffset") {
        $dtarr = explode(' ', $dtin);
        if (strpos($dtout, $dtarr[0]) !== false && strpos($dtout, $dtarr[1]) !== false && strpos($dtout, $dtarr[2]) !== false) {
            return true;
        }
    } else {
        if (strpos($dtout, $dtin) !== false) {
            return true;
        }
    }
    return false;
}

$dataTypes = array("datetime2", "datetimeoffset", "time");
$precisions = array(0, 1, 2, 4, 7);
$inputValuesInit = array("datetime2" => array("0001-01-01 00:00:00", "9999-12-31 23:59:59"),
                     "datetimeoffset" => array("0001-01-01 00:00:00 -14:00", "9999-12-31 23:59:59 +14:00"),
                     "time" => array("00:00:00", "23:59:59"));

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);
    foreach ($dataTypes as $dataType) {
        foreach ($precisions as $m) {
            // add $m number of decimal digits to the some input values
            $inputValues[0] = $inputValuesInit[$dataType][0];
            $inputValues[1] = $inputValuesInit[$dataType][1];
            if ($m != 0) {
                if ($dataType == "datetime2") {
                    $inputValues[1] .= "." . str_repeat("9", $m);
                } else if ($dataType == "datetimeoffset") {
                    $dtoffsetPieces = explode(" ", $inputValues[1]);
                    $inputValues[1] = $dtoffsetPieces[0] . " " . $dtoffsetPieces[1] . "." . str_repeat("9", $m) . " " . $dtoffsetPieces[2];
                } else if ($dataType == "time") {
                    $inputValues[0] .= "." . str_repeat("0", $m);
                    $inputValues[1] .= "." . str_repeat("9", $m);
                }
            }
            $typeFull = "$dataType($m)";
            echo "\nTesting $typeFull:\n";
        
            //create and populate table containing datetime2(m), datetimeoffset(m) or time(m) columns
            $tbname = "test_" . $dataType . $m;
            $colMetaArr = array(new ColumnMeta($typeFull, "c_det"), new ColumnMeta($typeFull, "c_rand", null, "randomized"));
            createTable($conn, $tbname, $colMetaArr);
            insertRow($conn, $tbname, array("c_det" => $inputValues[0], "c_rand" => $inputValues[1]));
            
            // fetch by specifying PDO::PARAM_ types with PDO:bindColumn
            $query = "SELECT c_det, c_rand FROM $tbname";
            foreach ($pdoParamTypes as $pdoParamType) {
                $det = "";
                $rand = "";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $stmt->bindColumn('c_det', $det, constant($pdoParamType));
                $stmt->bindColumn('c_rand', $rand, constant($pdoParamType));
                $row = $stmt->fetch(PDO::FETCH_BOUND);
                
                // check the case when fetching as PDO::PARAM_BOOL, PDO::PARAM_NULL or PDO::PARAM_INT
                // with or without AE: should not work
                if ($pdoParamType == "PDO::PARAM_BOOL" || $pdoParamType == "PDO::PARAM_NULL" || $pdoParamType == "PDO::PARAM_INT") {
                    if (!is_null($det) || !is_null($rand)) {
                        echo "Retrieving $typeFull data as $pdoParamType should not be supported\n";
                    }
                // check the case when fetching as PDO::PARAM_STR or PDO::PARAM_LOB
                // with or without AE: should work
                } else {
                    if (PHP_VERSION_ID >= 80100 && $pdoParamType == "PDO::PARAM_LOB") {
                        // Starting with PHP 8.1 fetching as PDO::PARAM_LOB will return a resource obj
                        $det = fread($det, 8192);
                        $rand = fread($rand, 8192);
                    }
                    if (compareDate($det, $inputValues[0], $dataType) && compareDate($rand, $inputValues[1], $dataType)) {
                        echo "****Retrieving $typeFull as $pdoParamType is supported****\n";
                    } else {
                        echo "Retrieving $typeFull as $pdoParamType fails\n";
                    }
                }
            }
            // cleanup
            dropTable($conn, $tbname);
        }
    }
    unset($stmt);
    unset($conn);
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
--EXPECT--
Testing datetime2(0):
****Retrieving datetime2(0) as PDO::PARAM_STR is supported****
****Retrieving datetime2(0) as PDO::PARAM_LOB is supported****

Testing datetime2(1):
****Retrieving datetime2(1) as PDO::PARAM_STR is supported****
****Retrieving datetime2(1) as PDO::PARAM_LOB is supported****

Testing datetime2(2):
****Retrieving datetime2(2) as PDO::PARAM_STR is supported****
****Retrieving datetime2(2) as PDO::PARAM_LOB is supported****

Testing datetime2(4):
****Retrieving datetime2(4) as PDO::PARAM_STR is supported****
****Retrieving datetime2(4) as PDO::PARAM_LOB is supported****

Testing datetime2(7):
****Retrieving datetime2(7) as PDO::PARAM_STR is supported****
****Retrieving datetime2(7) as PDO::PARAM_LOB is supported****

Testing datetimeoffset(0):
****Retrieving datetimeoffset(0) as PDO::PARAM_STR is supported****
****Retrieving datetimeoffset(0) as PDO::PARAM_LOB is supported****

Testing datetimeoffset(1):
****Retrieving datetimeoffset(1) as PDO::PARAM_STR is supported****
****Retrieving datetimeoffset(1) as PDO::PARAM_LOB is supported****

Testing datetimeoffset(2):
****Retrieving datetimeoffset(2) as PDO::PARAM_STR is supported****
****Retrieving datetimeoffset(2) as PDO::PARAM_LOB is supported****

Testing datetimeoffset(4):
****Retrieving datetimeoffset(4) as PDO::PARAM_STR is supported****
****Retrieving datetimeoffset(4) as PDO::PARAM_LOB is supported****

Testing datetimeoffset(7):
****Retrieving datetimeoffset(7) as PDO::PARAM_STR is supported****
****Retrieving datetimeoffset(7) as PDO::PARAM_LOB is supported****

Testing time(0):
****Retrieving time(0) as PDO::PARAM_STR is supported****
****Retrieving time(0) as PDO::PARAM_LOB is supported****

Testing time(1):
****Retrieving time(1) as PDO::PARAM_STR is supported****
****Retrieving time(1) as PDO::PARAM_LOB is supported****

Testing time(2):
****Retrieving time(2) as PDO::PARAM_STR is supported****
****Retrieving time(2) as PDO::PARAM_LOB is supported****

Testing time(4):
****Retrieving time(4) as PDO::PARAM_STR is supported****
****Retrieving time(4) as PDO::PARAM_LOB is supported****

Testing time(7):
****Retrieving time(7) as PDO::PARAM_STR is supported****
****Retrieving time(7) as PDO::PARAM_LOB is supported****