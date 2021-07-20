--TEST--
PDOStatement::BindParam for predefined constants and buffered query.
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php

//*************************************************************************************

// Test binding with different predefined constants and using buffered query to update and

// select data.

//*************************************************************************************

require_once("MsCommon_mid-refactor.inc");
require_once("MsData_PDO_AllTypes.inc");

function insert($db, $tbname)
{
    $bin = fopen('php://memory', 'a');
    fwrite($bin, '00');
    rewind($bin);

    $inputs = array("BigIntCol" => 0,
                    "BitCol" => '0',
                    "IntCol" => 0,
                    "SmallIntCol" => 1,
                    "TinyIntCol" => 1,
                    "DecimalCol" => 111,
                    "NumCol" => 1,
                    "MoneyCol" => 111.1110,
                    "SmallMoneyCol" => 111.1110,
                    "FloatCol" => 111.111,
                    "RealCol" => 111.111,
                    "CharCol" => 'STRINGCOL2',
                    "VarcharCol" => 'STRINGCOL2',
                    "TextCol" => '1 This is a really large string used to test certain large data types like xml data type. The length of this string is greater than 256 to correctly test a large data type. This is currently used by atleast varchar type and by xml type. The fetch tests are the primary consumer of this string to validate that fetch on large types work fine. The length of this string as counted in terms of number of characters is 417.',
                    "NCharCol" => 'STRINGCOL2',
                    "NVarcharCol" => 'STRINGCOL2',
                    "ImageCol" => new BindParamOp(17, $bin, "PDO::PARAM_LOB", 0, "PDO::SQLSRV_ENCODING_BINARY"),
                    "BinaryCol" => new BindParamOp(18, $bin, "PDO::PARAM_LOB", 0, "PDO::SQLSRV_ENCODING_BINARY"),
                    "VarbinaryCol" => new BindParamOp(19, $bin, "PDO::PARAM_LOB", 0, "PDO::SQLSRV_ENCODING_BINARY"),
                    "SmallDTCol" => '2000-11-11 11:11:00',
                    "DateTimeCol" => '2000-11-11 11:11:11.110',
                    "DTOffsetCol" => '2000-11-11 11:11:11.1110000 +00:00',
                    "TimeCol" => '11:11:11.1110000',
                    "Guidcol" => 'AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',
                    "VarbinaryMaxCol" => new BindParamOp(25, $bin, "PDO::PARAM_LOB", 0, "PDO::SQLSRV_ENCODING_BINARY"),
                    "VarcharMaxCol" => '1 This is a really large string used to test certain large data types like xml data type. The length of this string is greater than 256 to correctly test a large data type. This is currently used by atleast varchar type and by xml type. The fetch tests are the primary consumer of this string to validate that fetch on large types work fine. The length of this string as counted in terms of number of characters is 417.',
                    "XmlCol" => '<xml> 1 This is a really large string used to test certain large data types like xml data type. The length of this string is greater than 256 to correctly test a large data type. This is currently used by atleast varchar type and by xml type. The fetch tests are the primary consumer of this string to validate that fetch on large types work fine. The length of this string as counted in terms of number of characters is 417.</xml>',
                    "NTextCol" => '1 This is a really large string used to test certain large data types like xml data type. The length of this string is greater than 256 to correctly test a large data type. This is currently used by atleast varchar type and by xml type. The fetch tests are the primary consumer of this string to validate that fetch on large types work fine. The length of this string as counted in terms of number of characters is 417.',
                    "NVarCharMaxCol" => '1 This is a really large string used to test certain large data types like xml data type. The length of this string is greater than 256 to correctly test a large data type. This is currently used by atleast varchar type and by xml type. The fetch tests are the primary consumer of this string to validate that fetch on large types work fine. The length of this string as counted in terms of number of characters is 417.');
    $stmt = insertRow($db, $tbname, $inputs, "prepareBindParam");
    unset($stmt);
    echo "Insert complete!\n";
}

function bindPARAM_LOB($db, $tbname)
{
    // Binding LOB with buffered queries activated.
    $noteID = fopen('php://memory', 'a');
    $data = '0';
    $query = "UPDATE $tbname SET [VarbinaryCol]=:Name WHERE [BitCol]=:value";
    $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    fwrite($noteID, '00');
    rewind($noteID);
    $stmt->bindParam(':Name', $noteID, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
    $stmt->bindParam(':value', $data);
    $result = $stmt->execute();

    $select = "SELECT * FROM $tbname WHERE [BitCol]=:value";
    $stmt = $db->prepare($select, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->bindParam(':value', $data);
    $stmt->execute();
    $result = $stmt->fetchColumn(26);    // Retrieve VarbinaryCol
    unset($stmt);
    print("$result\n");
}

function bindPARAM_STR($db, $tbname)
{
    // Binding STR with buffered queries activated.
    // fopen returns a resource, if using PDO::PARAM_STR, the PDO side converts it to a zend_string "Resource id #*"
    // thus a resource is not meant to bind parameter using PDO::PARAM_STR

    $noteID = '1';
    $data = 'STRINGCOL2';
    $query = "UPDATE $tbname SET [BitCol]=:Name WHERE [CharCol]=:value";
    $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->bindParam(':Name', $noteID, PDO::PARAM_STR);
    $stmt->bindParam(':value', $data);
    $stmt->execute();

    $select = "SELECT * FROM $tbname WHERE [CharCol]=:value";
    $stmt = $db->prepare($select, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->bindParam(':value', $data);
    $stmt->execute();
    $result = $stmt->fetchColumn(2);    // Retrieve BitCol
    unset($stmt);
    $result = str_replace("\0", "", $result);
    print("$result\n");
}

function bindPARAM_NULL($db, $tbname)
{

    // Binding NULL with buffered queries activated.
    $noteID = fopen('php://memory', 'a');
    $data = 'STRINGCOL2';
    $query = "UPDATE PDO_AllTypes SET [BitCol]=:Name WHERE [VarcharCol]=:value";

    $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    // PHP Deprecated:  fwrite(): Passing null to parameter #2 ($data) of type string is deprecated
    // Also, it is not necessary for this test
    // fwrite($noteID, null);
    // rewind($noteID);
    $stmt->bindParam(':Name', $noteID, PDO::PARAM_NULL);
    $stmt->bindParam(':value', $data);
    $stmt->execute();

    $data = '0';
    $select = "SELECT * FROM PDO_AllTypes WHERE [BitCol]=:value";
    $stmt = $db->prepare($select, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->bindParam(':value', $data);
    $stmt->execute();
    $result = $stmt->fetchColumn(2);    // Retrieve BitCol
    unset($stmt);
    $result = str_replace("\0", "", $result);
    print("$result\n");
}

function bindPARAM_INT($db, $tbname)
{
    // Binding INT with buffered queries activated.
    $noteID = fopen('php://memory', 'a');
    $data = 0;
    $query = "UPDATE $tbname SET [IntCol]=:Name WHERE [IntCol]=:value";
    $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    fwrite($noteID, '1');
    rewind($noteID);
    $stmt->bindParam(':Name', $noteID, PDO::PARAM_INT);
    $stmt->bindParam(':value', $data);
    $stmt->execute();

    $select = "SELECT * FROM $tbname WHERE [BigIntCol]=:value";
    $stmt = $db->prepare($select, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->bindParam(':value', $data, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn(11);    // Retrieve IntCol
    unset($stmt);
    $result = str_replace("\0", "", $result);
    print("$result\n");
}

function bindPARAM_BOOL($db, $tbname)
{
    // Binding BOOL with buffered queries activated.
    $noteID = fopen('php://memory', 'a');
    $data = '0';
    $query = "UPDATE $tbname SET [BitCol]=:Name WHERE [BigIntCol]=:value";
    $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    fwrite($noteID, '1');
    rewind($noteID);
    $stmt->bindParam(':Name', $noteID, PDO::PARAM_BOOL);
    $stmt->bindParam(':value', $data);
    $stmt->execute();

    $query = "SELECT * FROM $tbname WHERE [BigIntCol]=:value";
    $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->bindParam(':value', $data, PDO::PARAM_BOOL);
    $stmt->execute();
    $result = $stmt->fetchColumn(2);    // Retrieve BitCol
    unset($stmt);
    $result = str_replace("\0", "", $result);
    print("$result\n");
}

function delete($db, $tbname)
{
    $data = "STRINGCOL2";
    $query = "DELETE FROM $tbname WHERE [CharCol]=:value";
    $stmt = $db->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE=>PDO::SQLSRV_CURSOR_BUFFERED));
    $stmt->bindParam(':value', $data);
    $stmt->execute();
    unset($stmt);
}

try {
    $db = connect();
    $tbname = "PDO_AllTypes";
    createAndInsertTableAllTypes($db, $tbname);
    insert($db, $tbname);
    bindPARAM_LOB($db, $tbname);
    bindPARAM_STR($db, $tbname);
    bindPARAM_NULL($db, $tbname);
    bindPARAM_INT($db, $tbname);
    bindPARAM_BOOL($db, $tbname);
    delete($db, $tbname);

    dropTable($db, $tbname);
    unset($db);
    echo "Test Completed";
} catch (PDOException $e) {
    var_dump($e);
    exit;
}

?>

--EXPECT--
Insert complete!
00
1
0
1
1
Test Completed
