--TEST--
GitHub issue 1079 - fetching sql_variant types using client buffers
--DESCRIPTION--
This test verifies that fetching sql_variant types using client buffers is supported.
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsSetup.inc");
require_once("MsCommon_mid-refactor.inc");

try {
    $conn = connect();

    $funcName = 'PDO1079';
    dropFunc($conn, $funcName);

    $tsql = "CREATE FUNCTION [$funcName](@OP1 sql_variant, @OP2 sql_variant) RETURNS sql_variant AS
    BEGIN DECLARE @Result sql_variant SET @Result = CASE WHEN @OP1 >= @OP2 THEN @OP1 ELSE @OP2 END RETURN @Result END";

    $conn->exec($tsql);
    
    $tsql = "SELECT [dbo].[$funcName](5, 6) AS RESULT";
    $stmt = $conn->prepare($tsql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE => PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->execute();

    $metadata = $stmt->getColumnMeta(0);
    var_dump($metadata);

    dropFunc($conn, $funcName);

    unset($stmt);
    unset($conn);
} catch (PdoException $e) {
    echo $e->getMessage() . PHP_EOL;
}

?>
--EXPECTF--
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(11) "sql_variant"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(6) "RESULT"
  ["len"]=>
  int(10)
  ["precision"]=>
  int(0)
}