--TEST--
Test for retrieving encrypted data from numeric types columns using PDO::bindColumn
--DESCRIPTION--
Test conversion from numeric types column to output of PDO::PARAM types
With or without Always Encrypted, conversion works if:
1. From any numeric type except for bigint column to PDO::PARAM_BOOL
2. From any numeric type except for bigint column to PDO::PARAM_INT
3. From any numeric type column to PDO::PARAM_STR
4. From any numeric type column to PDO::PARAM_LOB
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("AEData.inc");

$dataTypes = array( "bit", "tinyint", "smallint", "int", "bigint", "real");
$epsilon = 1;

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);
    foreach ($dataTypes as $dataType) {
        echo "\nTesting $dataType:\n";

        // create and populate table containing bit, tinyint, smallint, int, bigint, or real columns
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
            
            // check the case when fetching as PDO::PARAM_NULL
            // with or without AE: should not work
            if ($pdoParamType == "PDO::PARAM_NULL") {
                if (!is_null($det) || !is_null($rand)) {
                    echo "Retrieving $dataType data as $pdoParamType should not be supported\n";
                }
            // check the case when fetching as PDO::PARAM_BOOL or PDO::PARAM_INT
            // with or without AE: should only not work with bigint
            } elseif ($pdoParamType == "PDO::PARAM_BOOL" || $pdoParamType == "PDO::PARAM_INT") {
                if ($dataType == "bigint") {
                    if (!is_null($det) || !is_null($rand)) {
                        echo "Retrieving $dataType data as $pdoParamType should not be supported\n";
                    }
                } elseif (PHP_VERSION_ID >= 80100 && $pdoParamType == "PDO::PARAM_BOOL") {
                    if ($det == boolval($inputValues[0]) && $rand == boolval($inputValues[1])) {
                        echo "****Retrieving $dataType as $pdoParamType is supported****\n";
                    } else {
                        echo "Retrieving $dataType as $pdoParamType fails\n";
                    }
                } else {
                    if ($dataType == "real") {
                        if (abs($det - $inputValues[0]) < $epsilon && abs($rand - $inputValues[1]) < $epsilon) {
                            echo "****Retrieving $dataType as $pdoParamType is supported****\n";
                        } else {
                            echo "Retrieving $dataType as $pdoParamType fails\n";
                        }
                    } else {
                        if ($det == $inputValues[0] && $rand == $inputValues[1]) {
                            echo "****Retrieving $dataType as $pdoParamType is supported****\n";
                        } else {
                            echo "Retrieving $dataType as $pdoParamType fails\n";
                        }
                    }
                }
            // check the case when fetching as PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_STR or PDO::PARAM_LOB
            // with or without AE: should work
            } else {
                if ($pdoParamType == "PDO::PARAM_LOB") {
                    if (PHP_VERSION_ID >= 80100) {
                        $det = fread($det, 8192);
                        $rand = fread($rand, 8192);
                    }
                }
                if ($dataType == "real") {
                    if (abs($det - $inputValues[0]) < $epsilon && abs($rand - $inputValues[1]) < $epsilon) {
                        echo "****Retrieving $dataType as $pdoParamType is supported****\n";
                    } else {
                        echo "Retrieving $dataType as $pdoParamType fails\n";
                    }
                } else {
                    if ($det == $inputValues[0] && $rand == $inputValues[1]) {
                        echo "****Retrieving $dataType as $pdoParamType is supported****\n";
                    } else {
                        echo "Retrieving $dataType as $pdoParamType fails\n";
                    }
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
Testing bit:
****Retrieving bit as PDO::PARAM_BOOL is supported****
****Retrieving bit as PDO::PARAM_INT is supported****
****Retrieving bit as PDO::PARAM_STR is supported****
****Retrieving bit as PDO::PARAM_LOB is supported****

Testing tinyint:
****Retrieving tinyint as PDO::PARAM_BOOL is supported****
****Retrieving tinyint as PDO::PARAM_INT is supported****
****Retrieving tinyint as PDO::PARAM_STR is supported****
****Retrieving tinyint as PDO::PARAM_LOB is supported****

Testing smallint:
****Retrieving smallint as PDO::PARAM_BOOL is supported****
****Retrieving smallint as PDO::PARAM_INT is supported****
****Retrieving smallint as PDO::PARAM_STR is supported****
****Retrieving smallint as PDO::PARAM_LOB is supported****

Testing int:
****Retrieving int as PDO::PARAM_BOOL is supported****
****Retrieving int as PDO::PARAM_INT is supported****
****Retrieving int as PDO::PARAM_STR is supported****
****Retrieving int as PDO::PARAM_LOB is supported****

Testing bigint:
****Retrieving bigint as PDO::PARAM_STR is supported****
****Retrieving bigint as PDO::PARAM_LOB is supported****

Testing real:
****Retrieving real as PDO::PARAM_BOOL is supported****
****Retrieving real as PDO::PARAM_INT is supported****
****Retrieving real as PDO::PARAM_STR is supported****
****Retrieving real as PDO::PARAM_LOB is supported****