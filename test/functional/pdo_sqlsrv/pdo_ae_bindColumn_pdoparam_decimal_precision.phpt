--TEST--
Test for retrieving encrypted data from decimal types columns using PDO::bindColumn
--DESCRIPTION--
Test conversion from decimal types to output of PDO::PARAM types 
With or without Always Encrypted, conversion should work for all PDO::PARAM 
types unless for cases when mapping large decimal / numeric values to 
integers (values out of range)
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("AEData.inc");

$dataTypes = array("decimal", "numeric");
$precisions = array(1 => array(0, 1), 
                    4 => array(0, 1, 4), 
                    16 => array(0, 1, 4, 16), 
                    38 => array(0, 1, 4, 16, 38));
$inputValuesInit = array(92233720368547758089223372036854775808, -92233720368547758089223372036854775808);
$inputPrecision = 38;

// function checkNULLs() returns false when at least one of fetched 
// values is not null 
function checkNULLs($pdoParamType, $typeFull, $det, $rand)
{
    if (!is_null($det) || !is_null($rand)) {
        echo "Retrieving $typeFull data as $pdoParamType should return NULL\n";
        return false;
    } 
    return true;
}

// function compareIntegers() returns false when the fetched values 
// are different from the expected inputs 
function compareIntegers($pdoParamType, $det, $rand, $inputValues)
{
    // Assuming $pdoParamType is PDO::PARAM_BOOL or PDO::PARAM_INT
    $input0 = floor($inputValues[0]); // the positive float
    $input1 = ceil($inputValues[1]); // the negative float
        
    $matched = ($det == $input0 && $rand == $input1);
    if (!$matched) {
        echo "****Binding as $pdoParamType failed:****\n";
        echo "input 0: "; var_dump($inputValues[0]);
        echo "fetched: "; var_dump($det);
        echo "input 1: "; var_dump($inputValues[1]);
        echo "fetched: "; var_dump($rand);
    }
    
    return $matched;
}

try {
    $conn = connect("", array(), PDO::ERRMODE_SILENT);
    foreach ($dataTypes as $dataType) {
        foreach ($precisions as $m1 => $scales) {
            foreach ($scales as $m2) {
                // change the number of integers in the input values to be $m1 - $m2
                $precDiff = $inputPrecision - ($m1 - $m2);
                $inputValues = $inputValuesInit;
                foreach ($inputValues as &$inputValue) {
                    $inputValue = $inputValue / pow(10, $precDiff);
                }
                
                // compute the epsilon for comparing doubles
                // float in PHP only has a precision of roughly 14 digits: http://php.net/manual/en/language.types.float.php
                $epsilon;
                if ($m1 < 14) {
                    $epsilon = pow(10, $m2 * -1);
                } else {
                    $numint = $m1 - $m2;
                    if ($numint < 14) {
                        $epsilon = pow(10, (14 - $numint) * -1);
                    } else {
                        $epsilon = pow(10, $numint - 14);
                    }
                }
                
                $typeFull = "$dataType($m1, $m2)";
                
                // create and populate table containing decimal(m1, m2) 
                // or numeric(m1, m2) columns
                $tbname = "test_" . $dataType . $m1 . $m2;
                $colMetaArr = array(new ColumnMeta($typeFull, "c_det"), new ColumnMeta($typeFull, "c_rand", null, "randomized"));
                createTable($conn, $tbname, $colMetaArr);
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
                    
                    // With AE or not, the behavior is the same
                    $succeeded = false;
                    if ($pdoParamType == "PDO::PARAM_NULL") {
                        $succeeded = checkNULLs($pdoParamType, $typeFull, $det, $rand);
                    } elseif ($pdoParamType == "PDO::PARAM_BOOL" || $pdoParamType == "PDO::PARAM_INT") {
                        if ($m1 >= 16 && ($m1 != $m2)) {
                            // When the precision is more than 16 (unless the 
                            // precision = scale), the returned values are
                            // out of range as integers, so expect NULL
                            // (the data retrieval should have caused 
                            // an exception but was silenced)
                            $succeeded = checkNULLs($pdoParamType, $typeFull, $det, $rand);
                        } else {
                            $succeeded = compareIntegers($pdoParamType, $det, $rand, $inputValues, $m1, $m2);
                        }
                    } else {
                        if (PHP_VERSION_ID >= 80100 && $pdoParamType == "PDO::PARAM_LOB") {
                            // Starting with PHP 8.1 fetching as PDO::PARAM_LOB will return a resource obj
                            $det = fread($det, 8192);
                            $rand = fread($rand, 8192);
                        }
                        if (abs($det - $inputValues[0]) < $epsilon &&
                            abs($rand - $inputValues[1]) < $epsilon) {
                            $succeeded = true;
                        } 
                    }
                        
                    if (!$succeeded) {
                        echo "Retrieving $typeFull as $pdoParamType fails\n";
                    }
                }
                // cleanup
                dropTable($conn, $tbname);
            }
        }
    }
    unset($stmt);
    unset($conn);
    
    echo "Test successfully done\n";
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
--EXPECT--
Test successfully done