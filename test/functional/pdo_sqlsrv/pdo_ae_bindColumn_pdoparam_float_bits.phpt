--TEST--
Test for retrieving encrypted data from float types columns using PDO::bindColumn
--DESCRIPTION--
Test conversion from float types column to output of PDO::PARAM types
With or without Always Encrypted, conversion works if:
1. From any float type column to PDO::PARAM_STR
2. From any float type column to PDO::PARAM_LOB
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("AEData.inc");

$dataType = "float";
$bits = array(1, 12, 24, 36, 53);
$inputValues = array(9223372036854775808.9223372036854775808, -9223372036854775808.9223372036854775808);
$numint = 19;

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);
    foreach ($bits as $m) {
        // compute the epsilon for comparing doubles
        // when $m <= 24, the precision is 7 digits
        // when $m > 24, the precision is 15 digits, but PHP float only supports up to 14 digits
        $epsilon;
        if ($m <= 24) {
            $epsilon = pow(10, $numint - 7);
        } else {
            $epsilon = pow(10, $numint - 14);
        }
        
        $typeFull = "$dataType($m)";
        echo "\nTesting $typeFull:\n";
        
        //create and populate table containing float(m) columns
        $tbname = "test_" . $dataType . $m;
        $colMetaArr = array(new ColumnMeta($typeFull, "c_det"), new ColumnMeta($typeFull, "c_rand", null, "randomized"));
        createTable($conn, $tbname, $colMetaArr);
        insertRow($conn, $tbname, array("c_det" => $inputValues[0], "c_rand" => $inputValues[1]));
        
        // fetchby specifying PDO::PARAM_ types with PDO::bindColumn
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
                    echo "Retriving $typeFull data as $pdoParamType should return NULL\n";
                }
            } else {
                if (PHP_VERSION_ID >= 80100 && $pdoParamType == "PDO::PARAM_LOB") {
                    // Starting with PHP 8.1 fetching as PDO::PARAM_LOB will return a resource obj
                    $det = fread($det, 8192);
                    $rand = fread($rand, 8192);
                }

                if (abs($det - $inputValues[0]) < $epsilon && abs($rand - $inputValues[1]) < $epsilon) {
                    echo "****Retrieving $typeFull as $pdoParamType is supported****\n";
                } else {
                    echo "Retrieving $typeFull as $pdoParamType fails\n";
                }
            }
        }
        dropTable($conn, $tbname);
    }
    unset($stmt);
    unset($conn);
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
--EXPECT--
Testing float(1):
****Retrieving float(1) as PDO::PARAM_STR is supported****
****Retrieving float(1) as PDO::PARAM_LOB is supported****

Testing float(12):
****Retrieving float(12) as PDO::PARAM_STR is supported****
****Retrieving float(12) as PDO::PARAM_LOB is supported****

Testing float(24):
****Retrieving float(24) as PDO::PARAM_STR is supported****
****Retrieving float(24) as PDO::PARAM_LOB is supported****

Testing float(36):
****Retrieving float(36) as PDO::PARAM_STR is supported****
****Retrieving float(36) as PDO::PARAM_LOB is supported****

Testing float(53):
****Retrieving float(53) as PDO::PARAM_STR is supported****
****Retrieving float(53) as PDO::PARAM_LOB is supported****