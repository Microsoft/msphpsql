--TEST--
PDO Fetch Mode Test with emulate prepare
--DESCRIPTION--
Basic verification for PDOStatement::setFetchMode.
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require_once('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

try {
    $conn1 = connect();

    // Prepare test table
    $tableName = "pdo_test_table";
    createTable($conn1, $tableName, array(new ColumnMeta("int", "ID", "NOT NULL PRIMARY KEY"), "Policy" => "varchar(2)", "Label" => "varchar(10)", "Budget" => "money"));

    try {
        $res = $conn1->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        if ($res) {
            echo "setAttribute should have failed.\n\n";
        }
    } catch (Exception $e) {
        echo $e->getMessage() . "\n";
    }

    try {
        $query = "SELECT * FROM [$tableName]";
        $stmt = $conn1->query($query);
        $stmt->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    } catch (Exception $e) {
        echo $e->getMessage();
    }

    echo "\nStart inserting data...\n";
    $dataCols = "ID, Policy, Label";
    $query = "INSERT INTO [$tableName](ID, Policy, Label, Budget) VALUES (?, ?, ?, ?)";
    $stmtOptions = array(PDO::ATTR_EMULATE_PREPARES => false);
    $stmt = $conn1->prepare($query, $stmtOptions);
    for ($i = 1; $i <= 2; $i++) {
        $pol = chr(64+$i);
        $grp = "Group " . $i;
        $budget = $i * 1000 + $i * 15;
        $stmt->execute(array( $i, $pol, $grp, $budget ));
    }

    $query1 = "INSERT INTO [$tableName](ID, Policy, Label, Budget) VALUES (:col1, :col2, :col3, :col4)";
    if (!isAEConnected()) {
        $stmtOptions[PDO::ATTR_EMULATE_PREPARES] = true;
    }
    $stmt = $conn1->prepare($query1, $stmtOptions);
    for ($i = 3; $i <= 5; $i++) {
        $pol = chr(64+$i);
        $grp = "Group " . $i;
        $budget = $i * 1000 + $i * 15;
        $stmt->execute(array( ':col1' => $i, ':col2' => $pol, ':col3' => $grp, ':col4' => $budget ));
    }
    echo "....Done....\n";
    echo "Now selecting....\n";
    $tsql = "SELECT * FROM [$tableName]";
    $stmtOptions[PDO::ATTR_CURSOR] = PDO::CURSOR_SCROLL;

    $stmt1 = $conn1->prepare($tsql, $stmtOptions);
    $stmt1->execute();
    // The row order in the resultset when the column is encrypted (which is dependent on the encrytion key used)
    // is different from the order when the column is not enabled
    // To make this test work, if the column is encrypted, fetch all then find the corresponding row
    if (!isColEncrypted()) {
        var_dump($stmt1->fetch(PDO::FETCH_ASSOC));
        $row = $stmt1->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT);
        print "$row[1]\n";
        $row = $stmt1->fetch(PDO::FETCH_LAZY, PDO::FETCH_ORI_LAST);
        print "$row[3]\n";
        $row = $stmt1->fetch(PDO::FETCH_BOTH, PDO::FETCH_ORI_PRIOR);
        print_r($row);
    } else {
        $resultset = [];
        // fetch first two rows
        array_push($resultset, $stmt1->fetch(PDO::FETCH_BOTH));
        array_push($resultset, $stmt1->fetch(PDO::FETCH_BOTH, PDO::FETCH_ORI_NEXT));
        // fetch last three rows
        array_push($resultset, $stmt1->fetch(PDO::FETCH_BOTH, PDO::FETCH_ORI_LAST));
        array_push($resultset, $stmt1->fetch(PDO::FETCH_BOTH, PDO::FETCH_ORI_PRIOR));
        array_push($resultset, $stmt1->fetch(PDO::FETCH_BOTH, PDO::FETCH_ORI_PRIOR));
        // sort and print
        sort($resultset);
        $assocArr['ID'] = $resultset[0]['ID'];
        $assocArr['Policy'] = $resultset[0]['Policy'];
        $assocArr['Label'] = $resultset[0]['Label'];
        $assocArr['Budget'] = $resultset[0]['Budget'];
        var_dump($assocArr);
        print($resultset[1][1] . "\n");
        print($resultset[4][3] . "\n");
        print_r($resultset[3]);
    }

    echo "\nFirst two groups or Budget > 4000....\n";
    unset($stmtOptions[PDO::ATTR_CURSOR]);
    if (!isColEncrypted()) {
        $tsql = "SELECT * FROM [$tableName] WHERE ID <= :id OR Budget > :budget";
        $stmt2 = $conn1->prepare($tsql, $stmtOptions);
        $budget = 4000;
        $id = 2;
        $stmt2->bindParam(':id', $id);
        $stmt2->bindParam(':budget', $budget);
        $stmt2->execute();
        while ($result = $stmt2->fetchObject()) {
            print_r($result);
        }
    } else {
        // more and less than operators do not work for encrypted columns
        $tsql = "SELECT * FROM [$tableName] WHERE NOT ID = :id";
        $stmt2 = $conn1->prepare($tsql, $stmtOptions);
        $id = 3;
        $stmt2->bindParam(':id', $id);
        $stmt2->execute();
        // again need to fetch all, sort, then print
        $resultset = array();
        while ($result = $stmt2->fetchObject()) {
            array_push($resultset, $result);
        }
        sort($resultset);
        foreach ($resultset as $r) {
            print_r($r);
        }
    }

    echo "\nSelect Policy = 'A'....\n";
    $tsql = "SELECT * FROM [$tableName] WHERE Policy = ?";
    $stmt3 = $conn1->prepare($tsql, $stmtOptions);
    $pol = 'A';
    $stmt3->bindValue(1, $pol);
    $id = 'C';
    $stmt3->execute();
    while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
        echo "\n";
    }

    echo "\nSelect id > 2....\n";
    if (!isColEncrypted()) {
        $tsql = "SELECT Policy, Label, Budget FROM [$tableName] WHERE ID > 2";
        $stmt4 = $conn1->prepare($tsql, $stmtOptions);
    } else {
        $tsql = "SELECT Policy, Label, Budget FROM [$tableName] WHERE NOT ID = ? AND NOT ID = ?";
        $stmt4 = $conn1->prepare($tsql, $stmtOptions);
        $id1 = 1;
        $id2 = 2;
        $stmt4->bindParam(1, $id1);
        $stmt4->bindParam(2, $id2);
    }
    $stmt4->execute();
    $stmt4->bindColumn('Policy', $policy);
    $stmt4->bindColumn('Budget', $budget);
    $policyArr = array();
    $budgetArr = array();
    while ($row = $stmt4->fetch(PDO::FETCH_BOUND)) {
        //echo "Policy: $policy\tBudget: $budget\n";
        array_push($policyArr, $policy);
        array_push($budgetArr, $budget);
    }
    if (isColEncrypted()) {
        sort($policyArr);
        sort($budgetArr);
    }
    for ($i = 0; $i < 3; $i++) {
        echo "Policy: $policyArr[$i]\tBudget: $budgetArr[$i]\n";
    }

    echo "\nBudget Metadata....\n";
    $metadata = $stmt4->getColumnMeta(2);
    var_dump($metadata);

    // Cleanup
    dropTable($conn1, $tableName);
    unset($stmt1);
    unset($stmt2);
    unset($stmt3);
    unset($stmt4);
    unset($conn1);
} catch (Exception $e) {
    echo $e->getMessage();
}

?>
--EXPECTF--
SQLSTATE[IMSSP]: The given attribute is only supported on the PDOStatement object.
SQLSTATE[IMSSP]: An invalid attribute was designated on the PDOStatement object.
Start inserting data...
....Done....
Now selecting....
array(4) {
  ["ID"]=>
  string(1) "1"
  ["Policy"]=>
  string(1) "A"
  ["Label"]=>
  string(7) "Group 1"
  ["Budget"]=>
  string(9) "1015.0000"
}
B
5075.0000
Array
(
    [ID] => 4
    [0] => 4
    [Policy] => D
    [1] => D
    [Label] => Group 4
    [2] => Group 4
    [Budget] => 4060.0000
    [3] => 4060.0000
)

First two groups or Budget > 4000....
stdClass Object
(
    [ID] => 1
    [Policy] => A
    [Label] => Group 1
    [Budget] => 1015.0000
)
stdClass Object
(
    [ID] => 2
    [Policy] => B
    [Label] => Group 2
    [Budget] => 2030.0000
)
stdClass Object
(
    [ID] => 4
    [Policy] => D
    [Label] => Group 4
    [Budget] => 4060.0000
)
stdClass Object
(
    [ID] => 5
    [Policy] => E
    [Label] => Group 5
    [Budget] => 5075.0000
)

Select Policy = 'A'....
Array
(
    [ID] => 1
    [Policy] => A
    [Label] => Group 1
    [Budget] => 1015.0000
)


Select id > 2....
Policy: C	Budget: 3045.0000
Policy: D	Budget: 4060.0000
Policy: E	Budget: 5075.0000

Budget Metadata....
array(8) {
  ["flags"]=>
  int(0)
  ["sqlsrv:decl_type"]=>
  string(5) "money"
  ["native_type"]=>
  string(6) "string"
  ["table"]=>
  string(0) ""
  ["pdo_type"]=>
  int(%d)
  ["name"]=>
  string(6) "Budget"
  ["len"]=>
  int(19)
  ["precision"]=>
  int(4)
}
