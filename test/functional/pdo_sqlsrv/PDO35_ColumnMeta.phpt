--TEST--
PDO Column Metadata Test
--DESCRIPTION--
Verification for "PDOStatenent::getColumnMetadata".
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require('skipif.inc'); ?>
--FILE--
<?php
include 'MsCommon.inc';

function Fetch()
{
    include 'MsSetup.inc';

    $testName = "PDO Statement - Column Metadata";
    StartTest($testName);

    $conn1 = Connect();

    // Prepare test table
    CreateTableEx($conn1, $tableName, "id int NOT NULL PRIMARY KEY, val VARCHAR(10), val2 VARCHAR(16)", null);
    $data = array(  array('10', 'Abc', 'zxy'),
            array('20', 'Def', 'wvu'),
            array('30', 'Ghi', 'tsr'),
            array('40', 'Jkl', 'qpo'),
                array('50', 'Mno', 'nml'),
            array('60', 'Pqr', 'kji'));

    // Insert using question mark placeholders
    $stmt1 = PrepareQuery($conn1, "INSERT INTO [$tableName] VALUES(?, ?, ?)");
    foreach ($data as $row)
    {
        $stmt1->execute($row);
    }
    unset($stmt1);

    // Retrieve column metadata via a SELECT query
    $stmt1 = ExecuteQuery($conn1, "SELECT id, val, val2 FROM [$tableName]");
    $md = $stmt1->getColumnMeta(0);
    var_dump($md);
    $md = $stmt1->getColumnMeta(1);
    var_dump($md);
    $md = $stmt1->getColumnMeta(2);
    var_dump($md);
    unset($stmt1);

    // Retrieve column metadata as returned by a COUNT query
    $stmt1 = ExecuteQuery($conn1, "SELECT COUNT(*) FROM [$tableName]");
    $md = $stmt1->getColumnMeta(0);
    var_dump($md);


    // Cleanup
    DropTable($conn1, $tableName);
    $stmt1 = null;
    $conn1 = null;

    EndTest($testName);
}


//--------------------------------------------------------------------
// Repro
//
//--------------------------------------------------------------------
function Repro()
{

    try
    {
        Fetch();
    }
    catch (Exception $e)
    {
        echo $e->getMessage();
    }
}

Repro();

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
  string(3) "val"
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
  string(4) "val2"
  ["len"]=>
  int(16)
  ["precision"]=>
  int(0)
}
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
  string(0) ""
  ["len"]=>
  int(10)
  ["precision"]=>
  int(0)
}
Test "PDO Statement - Column Metadata" completed successfully.