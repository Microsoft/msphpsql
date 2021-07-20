--TEST--
Test for retrieving encrypted data from binary types columns using PDO::bindColumn
--DESCRIPTION--
Test conversion from binary types column to output of PDO::PARAM types
With or without AE, conversion works if:
1. From any binary type column to PDO::PARAM_STR
2. From any binary type column to PDO::PARAM_LOB
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("AEData.inc");

$dataTypes = array("binary", "varbinary", "varbinary(max)");
$lengths = array(1, 8, 64, 512, 4000);

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);
    foreach ($dataTypes as $dataType) {
        $maxcol = strpos($dataType, "(max)");
        foreach ($lengths as $m) {
            if ($maxcol !== false) {
                $typeFull = $dataType;
            } else {
                $typeFull = "$dataType($m)";
            }
            echo "\nTesting $typeFull:\n";
                
            //create and populate table containing binary(m) or varbinary(m) columns
            $tbname = "test_" . str_replace(array('(', ')'), '', $dataType) . $m;
            $colMetaArr = array(new ColumnMeta($typeFull, "c_det"), new ColumnMeta($typeFull, "c_rand", null, "randomized"));
            createTable($conn, $tbname, $colMetaArr);
            $inputValues = array(str_repeat("d", $m), str_repeat("r", $m));
            insertRow($conn, $tbname, array("c_det" => new BindParamOp(1, $inputValues[0], "PDO::PARAM_LOB", 0, "PDO::SQLSRV_ENCODING_BINARY"),
                                            "c_rand" => new BindParamOp(2, $inputValues[1], "PDO::PARAM_LOB", 0, "PDO::SQLSRV_ENCODING_BINARY")), "prepareBindParam");
                
            // fetch by specifying PDO::PARAM_ types with PDO::bindColumn
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
                // check the case when fetching as PDO::PARAM_STR
                // with or without AE: should work
                } else {
                    if (PHP_VERSION_ID >= 80100 && $pdoParamType == "PDO::PARAM_LOB") {
                        // Starting with PHP 8.1 fetching as PDO::PARAM_LOB will return a resource obj
                        $det = fread($det, 8192);
                        $rand = fread($rand, 8192);
                    }
                    
                    if (trim($det) == $inputValues[0] && trim($rand) == $inputValues[1]) {
                        echo "****Retrieving $typeFull data as $pdoParamType is supported****\n";
                    } else {
                        echo "Retrieving $typeFull data as $pdoParamType fails\n";
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
Testing binary(1):
****Retrieving binary(1) data as PDO::PARAM_STR is supported****
****Retrieving binary(1) data as PDO::PARAM_LOB is supported****

Testing binary(8):
****Retrieving binary(8) data as PDO::PARAM_STR is supported****
****Retrieving binary(8) data as PDO::PARAM_LOB is supported****

Testing binary(64):
****Retrieving binary(64) data as PDO::PARAM_STR is supported****
****Retrieving binary(64) data as PDO::PARAM_LOB is supported****

Testing binary(512):
****Retrieving binary(512) data as PDO::PARAM_STR is supported****
****Retrieving binary(512) data as PDO::PARAM_LOB is supported****

Testing binary(4000):
****Retrieving binary(4000) data as PDO::PARAM_STR is supported****
****Retrieving binary(4000) data as PDO::PARAM_LOB is supported****

Testing varbinary(1):
****Retrieving varbinary(1) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(1) data as PDO::PARAM_LOB is supported****

Testing varbinary(8):
****Retrieving varbinary(8) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(8) data as PDO::PARAM_LOB is supported****

Testing varbinary(64):
****Retrieving varbinary(64) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(64) data as PDO::PARAM_LOB is supported****

Testing varbinary(512):
****Retrieving varbinary(512) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(512) data as PDO::PARAM_LOB is supported****

Testing varbinary(4000):
****Retrieving varbinary(4000) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(4000) data as PDO::PARAM_LOB is supported****

Testing varbinary(max):
****Retrieving varbinary(max) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(max) data as PDO::PARAM_LOB is supported****

Testing varbinary(max):
****Retrieving varbinary(max) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(max) data as PDO::PARAM_LOB is supported****

Testing varbinary(max):
****Retrieving varbinary(max) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(max) data as PDO::PARAM_LOB is supported****

Testing varbinary(max):
****Retrieving varbinary(max) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(max) data as PDO::PARAM_LOB is supported****

Testing varbinary(max):
****Retrieving varbinary(max) data as PDO::PARAM_STR is supported****
****Retrieving varbinary(max) data as PDO::PARAM_LOB is supported****