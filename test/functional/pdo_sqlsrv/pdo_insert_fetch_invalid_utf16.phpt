--TEST--
Test fetching invalid UTF-16 from the server
--DESCRIPTION--
This is similar to sqlsrv 0079.phpt with checking for error conditions concerning encoding issues.
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

try {
    $conn = connect();
    
    // The following is required or else the insertion would have failed because the input
    // was invalid
    $conn->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_SYSTEM);
    
    // Create test table
    $tableName = 'pdoUTF16invalid';
    $columns = array(new ColumnMeta('int', 'id', 'identity'),
                     new ColumnMeta('nvarchar(100)', 'c1'));
    $stmt = createTable($conn, $tableName, $columns);
    
    // 0xdc00,0xdbff is an invalid surrogate pair
    $invalidUTF16 = pack("H*", '410042004300440000DCFFDB45004600');

    $insertSql = "INSERT INTO $tableName (c1) VALUES (?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bindParam(1, $invalidUTF16, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_BINARY);
    $stmt->execute();
    
    try {
        // Now fetch data with UTF-8 encoding
        $tsql = "SELECT * FROM $tableName";
        $stmt = $conn->prepare($tsql);
        $stmt->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8);
        $stmt->execute();
        $utf8 = $stmt->fetchColumn(1);    // Ignore the id column
        echo "fetchColumn should have failed with an error.\n";
    } catch (PDOException $e) {
        $error = '*An error occurred translating string for a field to UTF-8:*';
        if ($e->getCode() !== "IMSSP" || !fnmatch($error, $e->getMessage())) {
            var_dump($e->getMessage());
        }
    }
    
    dropProc($conn, 'Utf16InvalidOut');
    $createProc = <<<PROC
CREATE PROCEDURE Utf16InvalidOut
    @param nvarchar(25) OUTPUT
AS
BEGIN
    set @param = convert(nvarchar(25), 0x410042004300440000DCFFDB45004600);
END;
PROC;

    $conn->query($createProc);
    
    try {
        $invalidUTF16Out = '';
        $tsql = '{call Utf16InvalidOut(?)}';
        $stmt = $conn->prepare($tsql);
        $stmt->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8);
        $stmt->bindParam(1, $invalidUTF16Out, PDO::PARAM_STR, 25);
        $stmt->execute();
    } catch (PDOException $e) {
        $error = '*An error occurred translating string for an output param to UTF-8:*';
        if ($e->getCode() !== "IMSSP" || !fnmatch($error, $e->getMessage())) {
            var_dump($e->getMessage());
        }
    }
    
    echo "Done\n";
    
    // Done testing with the stored procedure and test table
    dropProc($conn, 'Utf16InvalidOut');
    dropTable($conn, $tableName);
    
    unset($stmt);
    unset($conn);
} catch (PDOException $e) {
    var_dump($e->errorInfo);
}
?>
--EXPECT--
Done