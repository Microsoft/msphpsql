--TEST--
Test for retrieving encrypted data from datetime types columns using PDO::bindColumn
--DESCRIPTION--
Test conversion from datetime types column to output of PDO::PARAM types
With or without Always Encrypted, conversion works if:
1. From any datetime type column to PDO::PARAM_STR
2. From any datetime type column to PDO::PARAM_LOB
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("AEData.inc");

$dataTypes = array("date", "datetime", "smalldatetime");

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);
    foreach ($dataTypes as $dataType) {
        echo "\nTesting $dataType:\n";

        // create and populate table containing date, datetime or smalldatetime columns
        $tbname = "test_" . $dataType;
        $colMetaArr = array(new ColumnMeta($dataType, "c_det"), new ColumnMeta($dataType, "c_rand", null, "randomized"));
        createTable($conn, $tbname, $colMetaArr);
        $inputValues = array_slice(${explode("(", $dataType)[0] . "_params"}, 1, 2);
        insertRow($conn, $tbname, array("c_det" => $inputValues[0], "c_rand" => $inputValues[1]));
        
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
                    echo "Retrieving $dataType data as $pdoParamType should not be supported\n";
                }
            // check the case when fetching as PDO::PARAM_STR or PDO::PARAM_LOB
            // only check if input values are part of fetched values because some input values do not contain any deicmal places, the value retrieved however has 3 decimal places if the type is a datetime
            // with or without AE: should work
            } else {
                if (PHP_VERSION_ID >= 80100 && $pdoParamType == "PDO::PARAM_LOB") {
                    // Starting with PHP 8.1 fetching as PDO::PARAM_LOB will return a resource obj
                    $det = fread($det, 8192);
                    $rand = fread($rand, 8192);
                }
                if (strpos($det, $inputValues[0]) !== false && strpos($rand, $inputValues[1]) !== false) {
                    echo "****Retrieving $dataType as $pdoParamType is supported****\n";
                } else {
                    echo "Retrieving $dataType as $pdoParamType fails\n";
                }
            }
        }
        // cleanup
        dropTable($conn, $tbname);
    }
    unset($stmt);
    unset($conn);
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
--EXPECT--
Testing date:
****Retrieving date as PDO::PARAM_STR is supported****
****Retrieving date as PDO::PARAM_LOB is supported****

Testing datetime:
****Retrieving datetime as PDO::PARAM_STR is supported****
****Retrieving datetime as PDO::PARAM_LOB is supported****

Testing smalldatetime:
****Retrieving smalldatetime as PDO::PARAM_STR is supported****
****Retrieving smalldatetime as PDO::PARAM_LOB is supported****