--TEST--
Test for retrieving encrypted data from nchar types columns using PDO::bindColumn
--DESCRIPTION--
Test conversion from nchar types column to output of PDO::PARAM types
With or without Always Encrypted, conversion works if:
1. From any nchar type column to PDO::PARAM_STR
2. From any nchar type column to PDO::PARAM_LOB
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("AEData.inc");

$dataTypes = array("nchar", "nvarchar", "nvarchar(max)");
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
                
            //create and populate table containing nchar(m) or nvarchar(m) columns
            $tbname = "test_" . str_replace(array('(', ')'), '', $dataType) . $m;
            $colMetaArr = array(new ColumnMeta($typeFull, "c1"));
            createTable($conn, $tbname, $colMetaArr);
            $inputValue = str_repeat("d", $m);
            insertRow($conn, $tbname, array("c1" => $inputValue));
                    
            // fetch by specifying PDO::PARAM_ types with PDO::bindColumn
            $query = "SELECT c1 FROM $tbname";
            foreach ($pdoParamTypes as $pdoParamType) {
                $det = "";
                $rand = "";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $stmt->bindColumn('c1', $c1, constant($pdoParamType));
                $row = $stmt->fetch(PDO::FETCH_BOUND);
                
                // check the case when fetching as PDO::PARAM_BOOL, PDO::PARAM_NULL or PDO::PARAM_INT
                // with or without AE: should not work
                if ($pdoParamType == "PDO::PARAM_BOOL" || $pdoParamType == "PDO::PARAM_NULL" || $pdoParamType == "PDO::PARAM_INT") {
                    if (!empty($det) || !empty($rand)) {
                        echo "Retrieving $typeFull data as $pdoParamType should not be supported\n";
                    }
                // check the case when fetching as PDO::PARAM_STR or PDO::PARAM_LOB
                // with or without AE: should work
                } else {
                    if (PHP_VERSION_ID >= 80100 && is_resource($c1)) {
                        // Starting with PHP 8.1 fetching as PDO::PARAM_LOB will return a resource obj
                        $c1 = fread($c1, 8192);
                    }
                    if (strlen($c1) == $m) {
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
Testing nchar(1):
****Retrieving nchar(1) as PDO::PARAM_STR is supported****
****Retrieving nchar(1) as PDO::PARAM_LOB is supported****

Testing nchar(8):
****Retrieving nchar(8) as PDO::PARAM_STR is supported****
****Retrieving nchar(8) as PDO::PARAM_LOB is supported****

Testing nchar(64):
****Retrieving nchar(64) as PDO::PARAM_STR is supported****
****Retrieving nchar(64) as PDO::PARAM_LOB is supported****

Testing nchar(512):
****Retrieving nchar(512) as PDO::PARAM_STR is supported****
****Retrieving nchar(512) as PDO::PARAM_LOB is supported****

Testing nchar(4000):
****Retrieving nchar(4000) as PDO::PARAM_STR is supported****
****Retrieving nchar(4000) as PDO::PARAM_LOB is supported****

Testing nvarchar(1):
****Retrieving nvarchar(1) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(1) as PDO::PARAM_LOB is supported****

Testing nvarchar(8):
****Retrieving nvarchar(8) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(8) as PDO::PARAM_LOB is supported****

Testing nvarchar(64):
****Retrieving nvarchar(64) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(64) as PDO::PARAM_LOB is supported****

Testing nvarchar(512):
****Retrieving nvarchar(512) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(512) as PDO::PARAM_LOB is supported****

Testing nvarchar(4000):
****Retrieving nvarchar(4000) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(4000) as PDO::PARAM_LOB is supported****

Testing nvarchar(max):
****Retrieving nvarchar(max) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(max) as PDO::PARAM_LOB is supported****

Testing nvarchar(max):
****Retrieving nvarchar(max) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(max) as PDO::PARAM_LOB is supported****

Testing nvarchar(max):
****Retrieving nvarchar(max) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(max) as PDO::PARAM_LOB is supported****

Testing nvarchar(max):
****Retrieving nvarchar(max) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(max) as PDO::PARAM_LOB is supported****

Testing nvarchar(max):
****Retrieving nvarchar(max) as PDO::PARAM_STR is supported****
****Retrieving nvarchar(max) as PDO::PARAM_LOB is supported****