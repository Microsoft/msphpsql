--TEST--
GitHub issue 1018 - Test PDO::quote() with the extended string types 
--DESCRIPTION--
This test verifies the extended string types, PDO::ATTR_DEFAULT_STR_PARAM, PDO::PARAM_STR_NATL and 
PDO::PARAM_STR_CHAR will affect how PDO::quote() works.
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require('skipif_old_php.inc'); ?>
--FILE--
<?php
require_once("MsSetup.inc");
require_once("MsCommon_mid-refactor.inc");

function testErrorCase2($conn, $isChar)
{
    try {
        $stmt = $conn->query('select 1');
        $error = '*An invalid attribute was designated on the PDOStatement object.';
        $pdoParam = ($isChar) ? PDO::PARAM_STR_CHAR : PDO::PARAM_STR_NATL;
        
        // This will cause an exception because PDO::ATTR_DEFAULT_STR_PARAM is not a statement attribute
        $stmt->setAttribute(PDO::ATTR_DEFAULT_STR_PARAM, $pdoParam);
    } catch (PDOException $e) {
        if (!fnmatch($error, $e->getMessage())) {
            echo "Unexpected error returned setting PDO::ATTR_DEFAULT_STR_PARAM on statement\n";
            var_dump($e->getMessage());
        }
    }
}

function testErrorCase($attr)
{
    try {
        $conn = connect();
        $error = '*Invalid extended string type specified. PDO_ATTR_DEFAULT_STR_PARAM can be either PDO_PARAM_STR_CHAR or PDO_PARAM_STR_NATL.';

        // This will cause an exception because PDO::ATTR_DEFAULT_STR_PARAM expects either PDO_PARAM_STR_CHAR or PDO_PARAM_STR_NATL only
        $conn->setAttribute(PDO::ATTR_DEFAULT_STR_PARAM, $attr);
    } catch (PDOException $e) {
        if (!fnmatch($error, $e->getMessage())) {
            echo "Unexpected error returned setting PDO::ATTR_DEFAULT_STR_PARAM\n";
            var_dump($e->getMessage());
        }
    }
}

try {
    testErrorCase(true);
    testErrorCase('abc');
    testErrorCase(4);

    $conn = connect();
    testErrorCase2($conn, true);
    testErrorCase2($conn, false);
    
    // Start testing quote function
    $conn->setAttribute(PDO::ATTR_DEFAULT_STR_PARAM, PDO::PARAM_STR_CHAR);
    
    // Deprecated: PDO::quote(): Passing null to parameter #1 ($string) of type string is being deprecated
    if (PHP_VERSION_ID < 80100) {
        var_dump($conn->quote(null, PDO::PARAM_NULL));
    } else {
        var_dump($conn->quote('', PDO::PARAM_NULL));
    }
    var_dump($conn->quote('\'', PDO::PARAM_STR));
    var_dump($conn->quote('foo', PDO::PARAM_STR));
    var_dump($conn->quote('foo', PDO::PARAM_STR | PDO::PARAM_STR_CHAR));
    var_dump($conn->quote('über', PDO::PARAM_STR | PDO::PARAM_STR_NATL));
    
    var_dump($conn->getAttribute(PDO::ATTR_DEFAULT_STR_PARAM) === PDO::PARAM_STR_CHAR);
    $conn->setAttribute(PDO::ATTR_DEFAULT_STR_PARAM, PDO::PARAM_STR_NATL);
    var_dump($conn->getAttribute(PDO::ATTR_DEFAULT_STR_PARAM) === PDO::PARAM_STR_NATL);
    
    var_dump($conn->quote('foo', PDO::PARAM_STR | PDO::PARAM_STR_CHAR));
    var_dump($conn->quote('über', PDO::PARAM_STR));
    var_dump($conn->quote('über', PDO::PARAM_STR | PDO::PARAM_STR_NATL));

    unset($conn);
    
    echo "Done\n";
} catch (PDOException $e) {
    echo $e->getMessage() . PHP_EOL;
}

?>
--EXPECT--
string(2) "''"
string(4) "''''"
string(5) "'foo'"
string(5) "'foo'"
string(8) "N'über'"
bool(true)
bool(true)
string(5) "'foo'"
string(8) "N'über'"
string(8) "N'über'"
Done
