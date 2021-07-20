--TEST--
Unicode XML message using bindValue()
--SKIPIF--
<?php require('skipif_mid-refactor.inc'); ?>
--FILE--
<?php
require_once("MsCommon_mid-refactor.inc");

// Connect
$conn = connect();

// Create a temporary table
$tableName = '#testXMLBindValue';
// XML encrypted is not supported, thus do not create table with encrypted columns
$sql = "CREATE TABLE $tableName (ID INT PRIMARY KEY NOT NULL IDENTITY, XMLMessage XML)";
$stmt = $conn->exec($sql);

// XML samples
$xml1 = '<?xml version="1.0" encoding="UTF-16"?>
<PTag>
  <CTag01>APP_PoP_银河</CTag01>
  <CTag02>Το Παρίσι (γαλλικά: Paris, ΔΦΑ [paˈʁi]), γνωστό και ως η Πόλη του φωτός (Ville lumière), από τότε που εφοδιάστηκαν οι κύριες λεωφόροι του με φανούς γκαζιού το 1828, είναι η πρωτεύουσα της Γαλλίας και της περιφέρειας Ιλ ντε Φρανς (Île-de-France) και μία από τις ιστορικότερες πόλεις της Ευρώπης.</CTag02>
</PTag>';

$xml2 = '<?xml version="1.0" encoding="utf-16"?>
<PTag>
  <CTag01>NULL</CTag01>
  <CTag02></CTag02>
</PTag>';

// Insert data
try {
    $stmt = $conn->prepare("INSERT INTO $tableName (XMLMessage) VALUES (:msg)");
    $stmt->bindValue(':msg', $xml1);
    $stmt->execute();

    $stmt = $conn->prepare("INSERT INTO $tableName (XMLMessage) VALUES (?)");
    $stmt->bindValue(1, $xml2);
    $stmt->execute();
} catch (PDOException $ex) {
    echo "Error: " . $ex->getMessage();
}

// Get data
$row = selectAll($conn, $tableName, PDO::FETCH_ASSOC);
var_dump($row);

// Close connection
unset($stmt);
unset($conn);

print "Done"
?>

--EXPECT--
array(2) {
  [0]=>
  array(2) {
    ["ID"]=>
    string(1) "1"
    ["XMLMessage"]=>
    string(553) "<PTag><CTag01>APP_PoP_银河</CTag01><CTag02>Το Παρίσι (γαλλικά: Paris, ΔΦΑ [paˈʁi]), γνωστό και ως η Πόλη του φωτός (Ville lumière), από τότε που εφοδιάστηκαν οι κύριες λεωφόροι του με φανούς γκαζιού το 1828, είναι η πρωτεύουσα της Γαλλίας και της περιφέρειας Ιλ ντε Φρανς (Île-de-France) και μία από τις ιστορικότερες πόλεις της Ευρώπης.</CTag02></PTag>"
  }
  [1]=>
  array(2) {
    ["ID"]=>
    string(1) "2"
    ["XMLMessage"]=>
    string(43) "<PTag><CTag01>NULL</CTag01><CTag02/></PTag>"
  }
}
Done
