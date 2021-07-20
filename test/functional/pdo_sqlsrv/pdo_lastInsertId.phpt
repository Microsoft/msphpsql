--TEST--
Test the PDO::lastInsertId() method.
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

try {
    $conn = connect();

    // create temporary tables
    createTable($conn, "table1", array(new columnMeta("int", "id", "IDENTITY(100,2)"), "val" => "int"));
    createTable($conn, "table2", array(new columnMeta("int", "id", "IDENTITY(200,2)"), "val" => "int"));
    createTable($conn, "table3", array("id" => "int", "val" => "int"));

    insertRow($conn, "table1", array("val" => 1), "exec");
    insertRow($conn, "table2", array("val" => 2), "exec");
    $id = $conn->lastInsertId();
    var_dump($id);

    insertRow($conn, "table2", array("val" => 3), "exec");
    insertRow($conn, "table1", array("val" => 4), "exec");
    $id = $conn->lastInsertId();
    var_dump($id);

    // Should return empty string as the table does not have an IDENTITY column.
    insertRow($conn, "table3", array("id" => 1, "val" => 1), "exec");
    $id = $conn->lastInsertId();
    var_dump($id);

    dropTable($conn, "table1");
    dropTable($conn, "table2");
    dropTable($conn, "table3");
    unset($conn);
} catch (PDOException $e) {
    var_dump($e);
    exit;
}


?>
--EXPECTREGEX--
string\(3\) "200"
string\(3\) "102"
(string\(0\) ""|bool\(false\))
