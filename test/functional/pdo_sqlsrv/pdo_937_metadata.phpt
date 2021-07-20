--TEST--
GitHub issue 937 - getting metadata will not fail after an UPDATE / DELETE statement
--DESCRIPTION--
Verifies that getColumnMeta will not fail after processing an UPDATE / DELETE query that returns no fields. Instead, it should simply return FALSE because no result set exists.
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsSetup.inc");
require_once("MsCommon_mid-refactor.inc");

$tableName = 'pdoTestTable_938';
$procName = 'pdoTestProc_938';

function checkMetaData($stmt)
{
    $metadata = $stmt->getColumnMeta(0);
    if ($metadata !== FALSE) {
        echo "Expects FALSE because no result set exists!\n";
    }
}

try {
    $conn = connect();
    
    dropTable($conn, $tableName);
    dropProc($conn, $procName);

    $tsql = "CREATE TABLE $tableName([id] [int] NOT NULL, [name] [varchar](10) NOT NULL)";
    $conn->query($tsql);
    
    $id = 3;
    $tsql = "INSERT INTO $tableName VALUES ($id, 'abcde')";
    $conn->query($tsql);
    
    $tsql = "UPDATE $tableName SET name = 'updated' WHERE id = $id";
    $stmt = $conn->prepare($tsql);
    $stmt->execute();
    $numCol = $metadata = $stmt->columnCount();
    echo "Number of columns after UPDATE: $numCol\n";
    checkMetaData($stmt);

    $tsql = "SELECT * FROM $tableName";
    $stmt = $conn->query($tsql);
    $numCol = $metadata = $stmt->columnCount();
    for ($i = 0; $i < $numCol; $i++) {
        $metadata = $stmt->getColumnMeta($i);
        var_dump($metadata);
    }
    
    createProc($conn, $procName, "@id int, @val varchar(10) OUTPUT", "SELECT @val = name FROM $tableName WHERE id = @id");

    $value = '';
    $tsql = "{CALL [$procName] (?, ?)}";
    $stmt = $conn->prepare($tsql);
    $stmt->bindParam(1, $id, PDO::PARAM_INT);
    $stmt->bindParam(2, $value, PDO::PARAM_STR, 10);
    $stmt->execute();
    $numCol = $metadata = $stmt->columnCount();
    echo "Number of columns after PROCEDURE: $numCol\n";
    echo "Value returned: $value\n";
    checkMetaData($stmt);
    
    $query = "DELETE FROM $tableName WHERE name = 'updated'";
    $stmt = $conn->query($query);  
    $numCol = $metadata = $stmt->columnCount();
    echo "Number of columns after DELETE: $numCol\n";
    checkMetaData($stmt);
} catch (PDOException $e) {
    echo $e->getMessage() . PHP_EOL;
}

dropTable($conn, $tableName);
dropProc($conn, $procName);

unset($stmt);
unset($conn);

?>
--EXPECTF--
Number of columns after UPDATE: 0
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
  string(2) "id"
  ["len"]=>
  int(10)
  ["precision"]=>
  int(0)
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
  string(4) "name"
  ["len"]=>
  int(10)
  ["precision"]=>
  int(0)
}
Number of columns after PROCEDURE: 0
Value returned: updated
Number of columns after DELETE: 0
