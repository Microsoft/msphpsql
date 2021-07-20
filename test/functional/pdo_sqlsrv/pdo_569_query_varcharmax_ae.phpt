--TEST--
GitHub issue #569 - direct query on varchar max fields results in function sequence error (Always Encrypted)
--DESCRIPTION--
This is similar to pdo_569_query_varcharmax.phpt but is not limited to testing the Always Encrypted feature in Windows only.
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

try {
    $conn = connect();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableName = 'pdoTestTable_569_ae';
    createTable($conn, $tableName, array(new ColumnMeta("int", "id", "IDENTITY"), "c1" => "nvarchar(max)"));

    $input = array();

    $input[0] = 'some very large string';
    $input[1] = '1234567890.1234';
    $input[2] = 'über über';

    $numRows = 3;
    $tsql = "INSERT INTO $tableName (c1) VALUES (?)";

    $stmt = $conn->prepare($tsql);
    for ($i = 0; $i < $numRows; $i++) {
        $stmt->bindParam(1, $input[$i]);
        $stmt->execute();
    }
    
    $tsql = "SELECT id, c1 FROM $tableName ORDER BY id";
    $stmt = $conn->prepare($tsql);
    $stmt->execute();

    // Fetch one row each time with different pdo type and/or encoding
    $result = $stmt->fetch(PDO::FETCH_NUM);
    if ($result[1] !== $input[0]) {
        echo "Expected $input[0] but got: ";
        var_dump($result[0]);
    }

    $stmt->bindColumn(2, $value, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_SYSTEM);
    $result = $stmt->fetch(PDO::FETCH_BOUND);
    if (PHP_VERSION_ID < 80100) {
        if (!$result || $value !== $input[1]) {
            echo "Expected $input[1] but got: ";
            var_dump($value);
        }
    } else {
        if (!$result || !is_resource($value)) {
            echo "Expected a stream resource but got: ";
            var_dump($value);
        }
        if (!feof($value)) {
            $str = fread($value, strlen($input[1]));
            if ($str !== $input[1]) {
                echo "Expected $input[1] but got: ";
                var_dump($str);
            }
        }
    }

    $stmt->bindColumn(2, $value, PDO::PARAM_STR);
    $result = $stmt->fetch(PDO::FETCH_BOUND);
    if (!$result || $value !== $input[2]) {
        echo "Expected $input[2] but got: ";
        var_dump($value);
    }
    
    // Fetch again but all at once
    $stmt->execute();
    $rows = $stmt->fetchall(PDO::FETCH_ASSOC);
    for ($i = 0; $i < $numRows; $i++) {
        $i = $rows[$i]['id'] - 1;
        if ($rows[$i]['c1'] !== $input[$i]) {
            echo "Expected $input[$i] but got: ";
            var_dump($rows[$i]['c1']);
        }
    }

    unset($stmt);
    unset($conn);
} catch (PDOException $e) {
    echo $e->getMessage();
}

echo "Done\n";

?>
--EXPECT--
Done