--TEST--
Test ColumnEncryption values.
--DESCRIPTION--
This test checks that connection fails when ColumnEncryption is set to nonsense,
or when it is set to an incorrect protocol. Then it checks that connection succeeds when
the attestation URL is incorrect.
--SKIPIF--
<?php require("skipif_not_hgs.inc"); ?>
--FILE--
<?php
require_once("MsSetup.inc");
require_once("AE_v2_values.inc");
require_once("sqlsrv_AE_functions.inc");

// Test with random nonsense. Connection should fail.
$options = array('database'=>$database,
                 'uid'=>$userName,
                 'pwd'=>$userPassword,
                 'ColumnEncryption'=>"xyz",
                 );

$conn = sqlsrv_connect($server, $options);
if (!$conn) {
    $e = sqlsrv_errors();
    checkErrors($e, array('CE400', '0'));
} else {
    die("Connecting with nonsense should have failed!\n");
}

// Test with incorrect protocol and good attestation URL. Connection should fail.
// Insert a rogue 'x' into the protocol part of the attestation.
$comma = strpos($attestation, ',');
$badProtocol = substr_replace($attestation, 'x', $comma, 0);
$options = array('database'=>$database,
                 'uid'=>$userName,
                 'pwd'=>$userPassword,
                 'ColumnEncryption'=>$badProtocol,
                 );

$conn = sqlsrv_connect($server, $options);
if (!$conn) {
    $e = sqlsrv_errors();
    checkErrors($e, array('CE400', '0'));
} else {
    die("Connecting with a bad attestation protocol should have failed!\n");
}

// Test with good protocol and incorrect attestation URL. Connection should succeed
// because the URL is only checked when an enclave computation is attempted.
$badURL = substr_replace($attestation, 'x', $comma+1, 0);
$options = array('database'=>$database,
                 'uid'=>$userName,
                 'pwd'=>$userPassword,
                 'ColumnEncryption'=>$badURL,
                 );

$conn = sqlsrv_connect($server, $options);
if (!$conn) {
    print_r(sqlsrv_errors());
    die("Connecting with a bad attestation URL should have succeeded!\n");
}

echo "Done.\n";

?>
--EXPECT--
Done.
