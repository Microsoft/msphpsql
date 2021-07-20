--TEST--
Test the PDOStatement::getColumnMeta() method
--DESCRIPTION--
there could be an issue about using a non-existent column index --- doesn't give any error/output/warning
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");
require_once("MsData_PDO_AllTypes.inc");

function fetchBoth($conn, $tbname)
{
    $stmt = $conn->query("Select * from $tbname");

    // 1
    $meta = $stmt->getColumnMeta(0);
    var_dump($meta);

    // 2
    $meta = $stmt->getColumnMeta(1);
    var_dump($meta);

    // 3
    $meta = $stmt->getColumnMeta(2);
    var_dump($meta);

    // 4
    $meta = $stmt->getColumnMeta(3);
    var_dump($meta);

    // 5
    $meta = $stmt->getColumnMeta(4);
    var_dump($meta);

    // 6
    $meta = $stmt->getColumnMeta(5);
    var_dump($meta);

    // 7
    $meta = $stmt->getColumnMeta(6);
    var_dump($meta);

    // 8
    $meta = $stmt->getColumnMeta(7);
    if ($meta["sqlsrv:decl_type"] != "xml") {
        echo "Wrong column metadata was retrieved for a xml column.\n";
    }
    unset($meta["sqlsrv:decl_type"]);
    var_dump($meta);

    // Test invalid arguments, set error mode to silent to reduce the number of error messages generated
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

    // Test negative column number
    try {
        $meta = $stmt->getColumnMeta(-1);
        echo "Expect getColumnMeta to fail with -1\n";
    } catch (Error $e) {
        if (PHP_MAJOR_VERSION == 8) {
            $error = '*PDOStatement::getColumnMeta(): Argument #1 ($column) must be greater than or equal to 0*';
        } else {
            $error = '*Invalid column reference: column number must be non-negative*';
        }
        if (!fnmatch($error, $e->getMessage())) {
            echo "Unexpected error:";
            var_dump($e->getMessage());
        }
    }

    // Test non-existent column number
    $meta = $stmt->getColumnMeta(10);
    var_dump($meta);
}

// When testing with PHP 8.0 the negative test case throws an Error instead of a warning. 
// Implement a custom warning handler such that with PHP 7.x the warning would be handled 
// to throw an Error.
function warningHandler($errno, $errstr) 
{ 
    throw new Error($errstr);
}

try {
    $db = connect();
    $tbname = "PDO_MainTypes";
    createAndInsertTableMainTypes($db, $tbname);
    set_error_handler("warningHandler", E_WARNING);
    fetchBoth($db, $tbname);
} catch (PDOException $e) {
    var_dump($e);
    exit;
}


?>
--EXPECTF--

array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(3) "int"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(6) "IntCol"
  ["len"]=>
  int(10)
  ["precision"]=>
  int(0)
}
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(4) "char"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(7) "CharCol"
  ["len"]=>
  int(10)
  ["precision"]=>
  int(0)
}
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(5) "nchar"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(8) "NCharCol"
  ["len"]=>
  int(10)
  ["precision"]=>
  int(0)
}
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(8) "datetime"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(11) "DateTimeCol"
  ["len"]=>
  int(23)
  ["precision"]=>
  int(3)
}
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(7) "varchar"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(10) "VarcharCol"
  ["len"]=>
  int(50)
  ["precision"]=>
  int(0)
}
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(8) "nvarchar"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(11) "NVarCharCol"
  ["len"]=>
  int(50)
  ["precision"]=>
  int(0)
}
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(5) "float"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(8) "FloatCol"
  ["len"]=>
  int(53)
  ["precision"]=>
  int(0)
}
array(7) {
  ["flags"]=>
  int(0)
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(6) "XmlCol"
  ["len"]=>
  int(0)
  ["precision"]=>
  int(0)
}
bool(false)
