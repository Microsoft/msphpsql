--TEST--
PDO - Insert Nulls
--DESCRIPTION--
Test inserting nulls into nullable columns
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require_once('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

function insertNullsTest($bindType)
{
    $outvar = null;
    $failed = false;
    $conn = connect();

    $tableName = "pdo_test_table";
    $dataTypes = array("c1_int" => "int",
                      "c2_tinyint" => "tinyint",
                      "c3_smallint" => "smallint",
                      "c4_bigint" => "bigint",
                      "c5_bit" => "bit",
                      "c6_float" => "float",
                      "c7_real" => "real",
                      "c8_decimal" => "decimal(28,4)",
                      "c9_numeric" => "numeric(32,4)",
                      "c10_money" => "money",
                      "c11_smallmoney" => "smallmoney",
                      "c12_char" => "char(512)",
                      "c13_varchar" => "varchar(512)",
                      "c14_varchar_max" => "varchar(max)",
                      "c15_nchar" => "nchar(512)",
                      "c16_nvarchar" => "nvarchar(512)",
                      "c17_nvarchar_max" => "nvarchar(max)",
                      "c18_text" => "text",
                      "c19_ntext" => "ntext",
                      "c20_binary" => "binary(512)",
                      "c21_varbinary" => "varbinary(512)",
                      "c22_varbinary_max" => "varbinary(max)",
                      "c23_image" => "image",
                      "c24_uniqueidentifier" => "uniqueidentifier",
                      "c25_datetime" => "datetime",
                      "c26_smalldatetime" => "smalldatetime",
                      "c27_timestamp" => "timestamp",
                      "c28_xml" => "xml");
    createTable($conn, $tableName, $dataTypes);

    $stmt = $conn->query("SELECT [TABLE_NAME],[COLUMN_NAME],[IS_NULLABLE] FROM [INFORMATION_SCHEMA].[COLUMNS] WHERE [TABLE_NAME] = '$tableName'");

    if ($stmt === false) {
        fatalError("Could not query for column information on table $tableName");
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmt2 = $conn->prepare("INSERT INTO [$tableName] ([" . $row['COLUMN_NAME'] . "]) VALUES (:p1)");

        if (strpos($row['COLUMN_NAME'], "timestamp") !== false) {
            continue;
        }

        if (($row['IS_NULLABLE'] == 'YES') && (strpos($row['COLUMN_NAME'], "binary") !== false)) {
            if ($bindType == PDO::PARAM_LOB) {
                $stmt2->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_BINARY);
                $stmt2->bindValue(":p1", null, $bindType);
            } elseif ($bindType == PDO::PARAM_STR) {
                $stmt2->bindParam(":p1", $outvar, $bindType, 0, PDO::SQLSRV_ENCODING_BINARY);
            }
        } else {
            $stmt2->bindParam(":p1", $outvar);
        }

        $stmt2->execute();

        if ($stmt2->errorCode() !== '00000') {
            print_r($stmt2->errorInfo());

            $failed = true;
        }
    }

    dropTable($conn, $tableName);
    return($failed);
}



//--------------------------------------------------------------------
// Repro
//
//--------------------------------------------------------------------
try {
    $failed = false;
    $failed |= insertNullsTest(PDO::PARAM_LOB);
    $failed |= insertNullsTest(PDO::PARAM_STR);
} catch (Exception $e) {
    echo $e->getMessage();
}
if ($failed) {
    fatalError("Possible Regression: Could not insert NULL");
} else {
    echo "Test 'PDO - Insert Nulls' completed successfully.\n";
}

?>
--EXPECT--
Test 'PDO - Insert Nulls' completed successfully.
