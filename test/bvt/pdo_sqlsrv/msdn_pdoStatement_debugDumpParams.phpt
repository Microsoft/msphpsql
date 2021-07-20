--TEST--
displays a prepared statement
--SKIPIF--
<?php require('skipif.inc'); ?>
--FILE--
<?php
require('connect.inc');
$conn = new PDO( "sqlsrv:server=$server ; Database = $databaseName", "$uid", "$pwd");

$param = "Owner";

$stmt = $conn->prepare("select * from Person.ContactType where name = :param");
$stmt->execute(array($param));
$stmt->debugDumpParams();

echo "\n\n";

$stmt = $conn->prepare("select * from Person.ContactType where name = ?");
$stmt->execute(array($param));
$stmt->debugDumpParams();

//free the statement and connection 
$stmt=null;
$conn=null;
?>
--EXPECTREGEX--
SQL: \[52\] select \* from Person.ContactType where name = \:param
Params:  1
Key: Name: \[6\] :param
paramno=0
name=\[6\] ":param"
is_param=1
param_type=(2|3)


SQL: \[47\] select \* from Person.ContactType where name = \?
Params:  1
Key: Position #0:
paramno=0
name=\[0\] ""
is_param=1
param_type=(2|3)