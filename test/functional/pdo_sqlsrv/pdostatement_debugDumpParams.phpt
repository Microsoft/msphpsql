--TEST--
Test debugDumpParams method.
--SKIPIF--
<?php require("skipif_mid-refactor.inc"); ?>
--FILE--
<?php

require_once('MsCommon_mid-refactor.inc');
require_once("MsData_PDO_AllTypes.inc");

try {
    $db = connect();
    $tbname = "PDO_AllTypes";
    createAndInsertTableAllTypes($db, $tbname);
    $bigint = 1;
    $string = "STRINGCOL1";
    $stmt = $db->prepare("SELECT IntCol FROM $tbname WHERE BigIntCol = :bigint AND CharCol = :string");
    $stmt->bindValue(':bigint', $bigint, PDO::PARAM_INT);
    $stmt->bindValue(':string', $string, PDO::PARAM_STR);
    $stmt->execute();

    $stmt->debugDumpParams();

    dropTable($db, $tbname);
    unset($stmt);
    unset($db);
} catch (PDOException $e) {
    var_dump($e);
}
?>
--EXPECTF--
SQL: [79] SELECT IntCol FROM PDO_AllTypes WHERE BigIntCol = :bigint AND CharCol = :string
Params:  2
Key: Name: [7] :bigint
paramno=0
name=[7] ":bigint"
is_param=1
param_type=%d
Key: Name: [7] :string
paramno=1
name=[7] ":string"
is_param=1
param_type=%d
