<?php
/*
    Microsoft SQL Server Driver for PHP - Unit Test Framework
    Copyright (c) Microsoft Corporation.  All rights reserved.

    Description:
        Common functions (shared by all tests).

*/

$tvpIncPath = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR;

require_once($tvpIncPath. 'test_tvp_data.php');

//
// looks like an additional file (in addition to pdo_test_base.inc) may be needed for these PHPTs
// to be runnable from the MSSQL teams' internal proprietary test running system
//

function isAEQualified($conn)
{
    $msodbcsql_ver = $conn->getAttribute(PDO::ATTR_CLIENT_VERSION)["DriverVer"];
    $msodbcsql_maj = explode(".", $msodbcsql_ver)[0];
    if ($msodbcsql_maj < 17) {
        return false;
    }
    require 'MsSetup.inc';
    if ($daasMode) {
        // running against Azure
        return true;
    }
    // if not Azure, check the server version
    $server_ver = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
    if (explode('.', $server_ver)[0] < 13)
        return false;
    return true;
}


/**
 * Connect to the database specified in MsSetup.inc; Column Encryption keywords automatically added when $keystore is not none
 * @param  string  $keywords : string to append to the dsn string in PDO::_construct
 * @param  array  $options : attributes to pass to PDO::_construct
 * @param  int $errmode : specifies how the driver reports failures: one of PDO::ERRMODE_EXCEPTION, PDO::ERRMODE_WARNING, or PDO::ERRMODE_SILENT; default is PDO::ERRMODE_EXCEPTION
 * @param  bool  $disableCE : flag for disabling column encryption even when keystore is NOT none
 *                          for testing fetching encrypted data when connection column encryption is off
 * @return PDO connection object
 */
function connect($keywords = '', $options=array(), $errmode = PDO::ERRMODE_EXCEPTION, $disableCE = false)
{
    try {
        // simply use $databaseName from MsSetup.inc to facilitate testing in Azure,
        // which does not support switching databases
        require("MsSetup.inc");
        $dsn = getDSN($server, $databaseName, $driver, $keywords, $disableCE);
        $conn = new PDO($dsn, $uid, $pwd, $options);
        if ($errmode == PDO::ERRMODE_EXCEPTION || $errmode == PDO::ERRMODE_WARNING || $errmode == PDO::ERRMODE_SILENT) {
            $conn->setAttribute(PDO::ATTR_ERRMODE, $errmode);
        } else {
            printf("connect: The errmode provided must be one of exception, warning, or silent.\n");
        }
        return $conn;
    } catch (PDOException $e) {
        var_dump($e->errorInfo);
    } catch (Exception $e) {
        var_dump($e->errorInfo);
        exit;
    }
}


/**
 * @param  string  $sqlsrvserver : server name
 * @param  string  $database : database name
 * @param  string  $keywords : string to append to the dsn string in PDO::_construct
 * @param  bool  $disableCE : flag for disabling column encryption even when keystore is NOT none
 * @return string dsn string used for PDO constructor
 */
function getDSN($sqlsrvserver, $database, $driver = null, $keywords = '', $disableCE = false)
{
    require("MsSetup.inc");
    $dsn = "";
    if ($sqlsrvserver) {
        $dsn .= "sqlsrv:Server=$sqlsrvserver;";
    } else {
        printf("getDSN: the sqlsrvserver provided must not be null.\n");
        exit;
    }
    if ($database) {
        $dsn .= "database=$database;";
    }
    if (!is_null($driver)) {
        $dsn .= "driver=$driver;";
    }
    if ($keystore != "none" && !$disableCE) {
        $dsn .= "ColumnEncryption=Enabled;";
    }
    if ($keystore == "akv" && !$disableCE) {
        if ($AKVKeyStoreAuthentication == "KeyVaultPassword") {
            $dsn .= "KeyStoreAuthentication=$AKVKeyStoreAuthentication;KeyStorePrincipalId=$AKVPrincipalName;KeyStoreSecret=$AKVPassword;";
        } else if ($AKVKeyStoreAuthentication == "KeyVaultClientSecret") {
            $dsn .= "KeyStoreAuthentication=$AKVKeyStoreAuthentication;KeyStorePrincipalId=$AKVClientID;KeyStoreSecret=$AKVSecret;";
        }
    }
    if ($keystore == "ksp" && !$disableCE) {
        $ksp_path = getKSPPath();
        $ksp_name = KSP_NAME;
        $encrypt_key = ENCRYPT_KEY;
        $dsn .= "CEKeystoreProvider=$ksp_path;CEKeystoreName=$ksp_name;CEKeystoreEncryptKey=$encrypt_key;";
    }
    if ($keywords) {
        $dsn .= $keywords;
    }
    return $dsn;
}


/**
 * @return string CEK name depending on the connection keywords
 */
function getCekName()
{
    require  'MsSetup.inc';
    $cekName = '';
    switch ($keystore) {
        case "none":
            $cekName = '';
            break;
        case "win":
            $cekName = 'AEColumnKey';
            break;
        case "ksp":
            $cekName = 'CustomCEK';
            break;
        case "akv":
            $cekName = 'AKVColumnKey';
            break;
        default:
            echo "getCekName: Invalid keystore name.\n";
    }
    return $cekName;
}

/**
 * @return the path to the KSP dll/so file
 */
function getKSPpath() 
{
    $name = 'myKSP';

    $dir_name = realpath(dirname(__FILE__));
    $ksp = $dir_name . DIRECTORY_SEPARATOR . $name;
    if ( strtoupper( substr( php_uname( 's' ), 0, 3 ) ) == 'WIN' ) {
        $arch = 'x64';
        if ( PHP_INT_SIZE == 4 ) // running 32 bit
            $arch = '';
        $ksp .= $arch . '.dll';
    }
    else
        $ksp .= '.so';
        
    return $ksp;
}  


/**
 * class for encapsulating column metadata needed for creating a table
 */
class ColumnMeta
{
    public $dataType;   //a string that includes the size of the type if necessary (e.g., decimal(10,5))
    public $colName;    //column name
    public $options;    //a string that is null by default (e.g. NOT NULL Identity (1,1) )
    public $encType;    //randomized, deterministic, or none; default is null
    public $forceEncrypt; //force encryption on a datatype not supported by Column Encrypton

    public function __construct($dataType, $colName = null, $options = null, $encType = null, $forceEncrypt = false)
    {
        if (is_null($colName)) {
            $this->colName = getDefaultColName($dataType);
        } else {
            $this->colName = $colName;
        }
        $this->dataType = $dataType;
        $this->options = $options;
        if (is_null($encType)) {
            if (isColEncrypted()) {
                $this->encType = "deterministic";
            } else {
                $this->encType = "none";
            }
        } else {
            $this->encType = $encType;
        }
        $this->forceEncrypt = $forceEncrypt;
    }
    /**
     * @return string column definition for creating a table
     */
    public function getColDef()
    {
        $append = " ";

        // an identity column is not encrypted because a select query with identity column as the where clause is often run and the user want to have to bind parameter every time
        if (isColEncrypted() && ($this->encType == "deterministic" || $this->encType == "randomized") && $this->isEncryptableType() 
            && stripos($this->options, "identity") === false && stripos($this->options, "rowguidcol") === false) {
            $cekName = getCekName();
            if (stripos($this->dataType, "char") !== false) {
                $append .= "COLLATE Latin1_General_BIN2 ";
            }
            $append .= sprintf("ENCRYPTED WITH (ENCRYPTION_TYPE = %s, ALGORITHM = 'AEAD_AES_256_CBC_HMAC_SHA_256', COLUMN_ENCRYPTION_KEY = $cekName) ", $this->encType);
        }
        $append .= $this->options;
        $colDef = "[" . $this->colName . "] " . $this->dataType . $append;
        return $colDef;
    }

    /**
     * @return bool if the datatype for this column is encryptable
     */
    public function isEncryptableType()
    {
        $unsupportedTypes = array("money", "smallmoney", "image", "ntext", "text", "xml", "sql_variant", "timestamp", "geography", "geometry", "hierarchyid");
        if (!$this->forceEncrypt && in_array(strtolower($this->dataType), $unsupportedTypes)) {
            return false;
        } else {
            return true;
        }
    }
}


/**
 * @return string default column name when a name is not provided in the ColumnMeta class
 */
function getDefaultColName($dataType)
{
    $colName = "c_" . str_replace(",", "_", str_replace("(", "_", $dataType));
    $colName = rtrim($colName, ")");
    return $colName;
}


/**
 * Create a table
 * @param  object  $conn : PDO connection object
 * @param  string  $tbname : name of the table to be created
 * @param  array  $columnMetaArr : array of key value pair with column name as key and datatype as value, or array of columnMeta objects, which contain metadata for one column
 * @param  array  $inputs : an associative array column name and its value; value may be a literal value or a ColumnMeta object
 * @param  bool  $disableCE : flag for disabling column encryption even when keystore is NOT none
 *                          for creating table with datatypes not support for encryption
 * @param  string  $tableOption : table options appended to the end of a create table statement
 * @return int reporting the number of rows affected (should always be 0 for creating table)
 */
function createTable($conn, $tbname, $columnMetaArr, $tableOption = '', $disableCE = false)
{
    try {
        dropTable($conn, $tbname);
        $colDef = "";
        foreach ($columnMetaArr as $key => $value) {
            if (!is_object($value)) {
                $cm = new ColumnMeta($value, $key);
                $colDef = $colDef . $cm->getColDef() . ", ";
            } elseif (get_class($value) == "ColumnMeta") {
                $colDef = $colDef . $value->getColDef() . ", ";
            } else {
                printf("createTable: The input provided must be an associative array of literal values or ColumnMeta objects.\n");
                exit;
            }
        }
        $colDef = rtrim($colDef, ", ");
        $createSql = "CREATE TABLE $tbname ($colDef) $tableOption";
        $numRows = $conn->exec($createSql);
        return $numRows;
    } catch (PDOException $e) {
        var_dump($e->errorInfo);
    } catch (Exception $e) {
        var_dump($e->errorInfo);
        exit;
    }
}


/**
 * class for encapsulating optional parameters for PDOStatement::bindParam
 */
class BindParamOp
{
    public $parameter;  //parameter identifier. Parameter name or 1-based index of the parameter
    public $variable;   //name of the PHP variable to bind
    public $pdoType;    //PDO_PARAM_ type specified for PDOStatement::bindParam
    public $length;     //length specified for PDOStatement::bindParam
    public $options;    //options specified for PDOStatement::bindParam

    public function __construct($parameter, $variable, $pdoType = null, $length = null, $options = null)
    {
        $this->parameter = $parameter;
        $this->variable = $variable;

        $pdoParams = array("PDO::PARAM_BOOL", "PDO::PARAM_NULL", "PDO::PARAM_INT", "PDO::PARAM_STR", "PDO::PARAM_LOB");
        if (in_array($pdoType, $pdoParams) || is_null($pdoType)) {
            $this->pdoType = $pdoType;
        } else {
            printf("BindParamOp construct: The pdoType provided must be one of PDO::PARAM_BOOL, PDO::PARAM_NULL, PDO::PARAM_INT, PDO::PARAM_STR, or PDO::PARAM_LOB.\n");
            exit;
        }

        if ($length >= 0 || is_null($length)) {
            $this->length = is_null($length) ? 0 : $length;
        } else {
            printf("BindParamOp construct: The length provided must be greater or equal to 0.\n");
            exit;
        }

        $encodingAttrs = array("PDO::SQLSRV_ENCODING_BINARY", "PDO::SQLSRV_ENCODING_SYSTEM", "PDO::SQLSRV_ENCODING_UTF8", "PDO::SQLSRV_ENCODING_DEFAULT");
        if (in_array($options, $encodingAttrs) || is_null($options)) {
            $this->options = $options;
        } else {
            printf("BindParamOp construct: The option provided must be one of PDO::SQLSRV_ENCODING_BINARY, PDO::SQLSRV_ENCODING_SYSTEM, PDO::SQLSRV_ENCODING_UTF8, PDO::SQLSRV_ENCODING_DEFAULT");
        }
    }
    /**
     * @param  object  $stmt : PDO Statement object
     */
    public function bindWithOp($stmt)
    {
        // get the constant values of pdoType, and option
        $pdoType = null;
        $options = null;
        if (!is_null($this->pdoType)) {
            $pdoType = constant($this->pdoType);
        }
        if (!is_null($this->options)) {
            $options = constant($this->options);
        }
        $stmt->bindParam($this->parameter, $this->variable, $pdoType, $this->length, $options);
    }
}


/**
 * Insert a row into a table
 * @param  object  $conn : PDO connection object
 * @param  string  $tbname : name of the table for the row to be inserted
 * @param  array  $inputs : an associative array column name and its value; value may be a literal value or a BindParamOp object
 * @param  string  $api : PDO_SQLSRV API used for executing the insert query
 *                      accepted values: "exec", "query", "prepareDirect", "prepareExecuteBind", "prepareBindParam"
 * @param  bool  $r : true if the row was successfully inserted, otherwise false. Default value is null to make this parameter optional.
 * @return object PDOStatement object of the insert statement
 */
function insertRow($conn, $tbname, $inputs, $api = null, &$r = null)
{
    try {
        // set the default API being called depending on if column is encrypted
        if (is_null($api)) {
            if (!isColEncrypted()) {
                $api = "query";
            } else {
                $api = "prepareBindParam";
            }
        }
        $stmt = null;
        if (!isColEncrypted() && $api != "prepareExecuteBind" && $api != "prepareBindParam") {
            $insertSql = getInsertSqlComplete($tbname, $inputs);
            switch ($api) {
                case "exec":
                    $conn->exec($insertSql);
                    break;
                case "query":
                    $stmt = $conn->query($insertSql);
                    break;
                case "prepareDirect":
                    $stmt = $conn->prepare($insertSql);
                    $r = $stmt->execute();
                    break;
            }
        } else {
            // if AE is on, must bind param
            $insertSql = getInsertSqlPlaceholders($tbname, $inputs);
            $stmt = $conn->prepare($insertSql);
            if ($api == "prepareExecuteBind") {
                $params = array_values($inputs);
                $r = $stmt->execute($params);
            } else {
                $i = 1;
                foreach ($inputs as $key => $value) {
                    if (!is_object($value)) {
                        $stmt->bindParam($i, $inputs[$key]);
                    } elseif (get_class($value) == "BindParamOp") {
                        $value->bindWithOp($stmt);
                    } else {
                        printf("insertRow: The inputs provided must be a literal value or a BindParamOp object.\n");
                        exit;
                    }
                    $i++;
                }
                $r = $stmt->execute();
            }
        }
        return $stmt;
    } catch (PDOException $e) {
        var_dump($e->errorInfo);
    } catch (Exception $e) {
        var_dump($e->errorInfo);
        exit;
    }
}


/**
 * @param  string  $tbname : name of the table for an insert sql
 * @param  array  $inputs : associative array containing a key value pair of column name and data to put into an insert sql string
 * @return string a complete insert sql string
 */
function getInsertSqlComplete($tbname, $inputs)
{
    $colStr = "INSERT INTO $tbname (";
    $valStr = "VALUES (";
    if (empty($inputs)) {
        echo "getInsertSqlComplete: inputs for inserting a row cannot be empty.\n";
        return;
    }
    foreach ($inputs as $key => $value) {
        $colStr .= $key . ", ";
        if (is_null($value)) {
            $valStr .= "null, ";
        } elseif (is_string($value)) {
            $valStr .= "'" . $value . "', ";
        } else {
            $valStr .= $value . ", ";
        }
    }
    $colStr = rtrim($colStr, ", ") . ") ";
    $valStr = rtrim($valStr, ", ") . ") ";
    $insertSql = $colStr . $valStr;
    return $insertSql;
}


/**
 * @param  string  $tbname : name of the table for an insert sql
 * @param  array  $input : associative array containing a key value pair of column name and data to put into an insert sql string
 * @return string an insert sql string with "?" placeholders for all values
 */
function getInsertSqlPlaceholders($tbname, $inputs)
{
    if (empty($inputs)) {
        echo "getInsertSqlPlaceholders: inputs for inserting a row cannot be empty.\n";
        return "";
    }
    $colStr = "INSERT INTO $tbname (";
    $valStr = "VALUES (";
    foreach ($inputs as $key => $value) {
        $colStr .= $key . ", ";
    }
    $colStr = rtrim($colStr, ", ") . ") ";
    $valStr .= getSeqPlaceholders(count($inputs)) . ") ";
    $insertSql = $colStr . $valStr;
    return $insertSql;
}


/**
 * @param  string  $spname : name of the stored procedure
 * @param  int $num  : number of parameters needed for the stored procedure
 * @return string a call stored procedure sql string with "?" placeholders for all parameters
 */
function getCallProcSqlPlaceholders($spname, $num)
{
    $callStr = "{CALL $spname (";
    $callStr .= getSeqPlaceholders($num) . ")} ";
    return $callStr;
}


/**
 * @param  int  $num : number of placeholders needed
 * @return string a string containing $num number of repeated "?" placeholders delimited by ", "
 */
function getSeqPlaceholders($num)
{
    if ($num <= 0) {
        echo "getSeqPlaceholders: num provided for creating a sequence of placeholders cannot be less than 0.\n";
        return;
    }
    $placeholderStr = str_repeat("?, ", $num);
    $placeholderStr = rtrim($placeholderStr, ", ");
    return $placeholderStr;
}

/**
 * Compare two floating point numbers and return true if the difference is minimal
 * @param  float  $expected  : expected value
 * @param  float  $actual : actual value
 */
function compareFloats($expected, $actual)
{
    $epsilon = 0.00001;
    $diff = abs(($actual - $expected) / $expected);
    return ($diff < $epsilon);
}

/**
 * Fetch all rows and all columns given a table name, and print them
 * @param  object  $conn : PDO connection object
 * @param  string  $tbname : name of the table to fetch from
 */
function fetchAll($conn, $tbname)
{
    try {
        $sql = "SELECT * FROM $tbname";
        $stmt = $conn->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($row as $key => $value) {
                print("$key: $value\n");
            }
        }
    } catch (PDOException $e) {
        var_dump($e->errorInfo);
    } catch (Exception $e) {
        var_dump($e->errorInfo);
        exit;
    }
}


/**
 * Use PDOStatement::fetchAll to fetch all rows given a table name
 * @param  object  $conn : PDO connection object
 * @param  string  $tbname : name of the table to fetch from
 * @param  string  $fetchStyle : fetch_style argument passed to PDOStatement::fetchAll
 * @return array rows in a result set
 */
function selectAll($conn, $tbname, $fetchStyle = PDO::FETCH_BOTH)
{
    try {
        $sql = "SELECT * FROM $tbname";
        $stmt = $conn->query($sql);
        $data = $stmt->fetchAll($fetchStyle);
        return $data;
    } catch (PDOException $e) {
        var_dump($e->errorInfo);
    } catch (Exception $e) {
        var_dump($e->errorInfo);
        exit;
    }
}


/**
 * Use PDOStatement::fetch to fetch a row given a table name
 * @param  object  $conn : PDO connection object
 * @param  string  $tbname : name of the table to fetch from
 * @param  string  $fetchStyle : fetch_style argument passed to PDOStatement::fetch
 * @return array a row from the result set
 */
function selectRow($conn, $tbname, $fetchStyle = null)
{
    try {
        $sql = "SELECT * FROM $tbname";
        $stmt = $conn->query($sql);
        $row = $stmt->fetch(constant($fetchStyle));
        return $row;
    } catch (PDOException $e) {
        var_dump($e->errorInfo);
    } catch (Exception $e) {
        var_dump($e->errorInfo);
        exit;
    }
}


/**
 * @return bool false if $keystore specified in MsSetup.inc is none, otherwise return true
 */
function isColEncrypted()
{
    require  'MsSetup.inc';
    if ($keystore == "none" || !$dataEncrypted) {
        return false;
    } else {
        return true;
    }
}


function isAEConnected()
{
    require('MsSetup.inc');
    return $keystore != "none";
}

function teardown()
{
    // TBD
}


function my_print_r($to_print)
{
    global $verbose;
    if ($verbose) {
        print_r($to_print);
    }
}


function TestMode()
{
    $testMode = getenv('PHPT_EXEC');
    return ($testMode ? true : false);
}

function IsPdoMode()
{
    require  'MsSetup.inc';
    return (($driverType === true) ? true : false);
}


function TraceMode()
{
    require  'MsSetup.inc';
    return ((!TestMode() && $traceEnabled) ? true : false);
}

function IsMarsSupported()
{
    require  'MsSetup.inc';
    return ($marsMode ? true : false);
}

function IsDaasMode()
{
    require  'MsSetup.inc';
    return ($daasMode ? true : false);
}

function isSQLAzure()
{
    // 'SQL Azure' indicates SQL Database or SQL Data Warehouse
    // For details, https://docs.microsoft.com/sql/t-sql/functions/serverproperty-transact-sql
    try {
        $conn = connect();
        $tsql = "SELECT SERVERPROPERTY ('edition')";
        $stmt = $conn->query($tsql);

        $result = $stmt->fetch(PDO::FETCH_NUM);
        $edition = $result[0];
        
        if ($edition === "SQL Azure") {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        echo $e->getMessage();
        die("Could not fetch server property.");
    }
}

function isAzureDW()
{
    // Check if running Azure Data Warehouse
    // For details, https://docs.microsoft.com/sql/t-sql/functions/serverproperty-transact-sql
    try {
        $conn = connect();

        // Check if running Azure Data Warehouse
        // For details, https://docs.microsoft.com/sql/t-sql/functions/serverproperty-transact-sql
        $tsql = "SELECT SERVERPROPERTY ('edition'), SERVERPROPERTY ('EngineEdition')";
        $stmt = $conn->query($tsql);

        $result = $stmt->fetch(PDO::FETCH_NUM);
        $edition = $result[0];
        $engEd = intval($result[1]);

        if ($edition == "SQL Azure" && $engEd == 6) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        echo $e->getMessage();
        die("skip Failed to connect or could not fetch edition info.");
    }
}

function FatalError($errorMsg)
{
    if (!IsPdoMode()) {
        handle_errors();
    }
    die("$errorMsg\n");
}

function Trace($msg)
{
    if (TraceMode()) {
        echo $msg;
    }
}

function TraceEx($msg, $exitMode)
{
    if ($exitMode) {
        FatalError($msg);
    } else {
        Trace("$msg\n");
    }
}

function TraceData($sqlType, $data)
{
    if (TraceMode()) {
        $msg = strtoupper(" $sqlType:");
        echo "$msg\t";
        if (strlen($msg) <= 7) {
            echo "\t";
        }
        if (strlen($msg) <= 15) {
            echo "\t";
        }
        echo "$data\n"
;
    }
}

function StartTest($testName)
{
    require  'MsSetup.inc';

    if (TraceMode()) {
        echo "$PhpDriver: starting \"$testName\" test...\n\n";
    }

    if (!extension_loaded(IsPdoMode() ? "pdo" : "sqlsrv")) {
        die("$PhpDriver cannot be loaded.");
    }

    // Set timezone
    $tz = ini_get('date.timezone');
    if (strcmp($tz, "") == 0) {
        date_default_timezone_set('America/Los_Angeles');
        $tz = date_default_timezone_get();
    }
    Trace("Timezone: $tz.\n");
}

function EndTest($testName)
{
    require  'MsSetup.inc';

    if (TraceMode()) {
        echo "\n$PhpDriver: "
;
    }
    echo "Test \"$testName\" completed successfully.\n"
;
}


function Setup()
{
    set_time_limit(0);

    if (IsPdoMode()) {   // PDO setup
    } else {   // PHP setup
        sqlsrv_configure('LogSubsystems', SQLSRV_LOG_SYSTEM_ALL);
        sqlsrv_configure('LogSeverity', SQLSRV_LOG_SEVERITY_ALL);
        sqlsrv_configure('WarningsReturnAsErrors', 1);
    }
}

function Configure($param, $expected)
{
    if (!IsPdoMode()) {
        sqlsrv_configure($param, $expected);
        $actual = sqlsrv_get_config($param);

        if ($actual == $expected) {
            Trace("Set configuration parameter $param = $actual.\n");
        } else {
            die("Failed to set configuration parameter $param = $expected.");
        }
    }
}

function getTableName($table = '')
{
    $timestamp = round(microtime(true)*1000);

    if (strlen($table) == 0) {
        $table = 'php_test_table';
    }

    return $table . '_' . $timestamp;
}

function getProcName($proc = '')
{
    // A temporary stored procedure name with the '#' prefix will be
    // automatically dropped once the connection is closed. Otherwise,
    // the caller should take care of dropping the temp procedure afterwards.

    $timestamp = round(microtime(true)*1000);

    if (strlen($proc) == 0) {
        $proc = 'php_test_proc';
    }

    return $proc . '_' . $timestamp;
}

function PDOConnect($className, $serverName, $user, $pwd, $exitMode)
{
    require  'MsSetup.inc';
    $conn = null;

    try {
        // simply use $databaseName from MsSetup.inc to facilitate testing in Azure,
        // which does not support switching databases
        $conn = new $className("sqlsrv:Server=$serverName;Database=$databaseName", $user, $pwd, $connectionOptions);
        $conn->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_SYSTEM);
    } catch (PDOException $e) {
        $conn = null;
        TraceEx("\nFailed to connect to $serverName: ".$e->getMessage(), $exitMode);
    }

    return ($conn);
}


function ExecuteQuery($conn, $tsql)
{
    $stmt = null;

    if (IsPdoMode()) {   // PDO
        try {
            $stmt = $conn->query($tsql);
        } catch (PDOException $e) {
            $stmt = null;
            FatalError("Query execution failed for $tsql: ".$e->getMessage());
        }
    } else {   // PHP
        $stmt = sqlsrv_query($conn, $tsql);
        if ($stmt === false) {
            FatalError("Query execution failed for $tsql");
        }
    }
    return ($stmt);
}


function PrepareQuery($conn, $tsql)
{
    $stmt = null;

    if (IsPdoMode()) {   // PDO
        try {
            $stmt = $conn->prepare($tsql);
        } catch (PDOException $e) {
            $stmt = null;
            FatalError("Query preparation failed for $tsql: ".$e->getMessage());
        }
    } else {   // PHP
        $stmt = sqlsrv_prepare($conn, $tsql);
        if ($stmt === false) {
            FatalError("Query preparation failed for $tsql");
        }
    }

    return ($stmt);
}


function ExecuteQueryEx($conn, $tsql, $modeDirect)
{
    $stmt = null;

    if ($modeDirect) {   // direct execution
        if (IsPdoMode()) {   // PDO
            try {
                $stmt = $conn->query($tsql);
            } catch (PDOException $e) {
                $stmt = null;
            }
        } else {   // PHP
            $stmt = sqlsrv_query($conn, $tsql);
        }
    } else {
        $stmt = PrepareQuery($conn, $tsql);
        if (IsPdoMode()) {
            $stmt->execute();
        } else {
            sqlsrv_execute($stmt);
        }
    }

    return ($stmt);
}


function GetSqlType($k)
{
    switch ($k) {
        case 1:     return ("int");
        case 2:     return ("tinyint");
        case 3:     return ("smallint");
        case 4:     return ("bigint");
        case 5:     return ("bit");
        case 6:     return ("float");
        case 7:     return ("real");
        case 8:     return ("decimal(28,4)");
        case 9:     return ("numeric(32,4)");
        case 10:    return ("money");
        case 11:    return ("smallmoney");
        case 12:    return ("char(512)");
        case 13:    return ("varchar(512)");
        case 14:    return ("varchar(max)");
        case 15:    return ("nchar(512)");
        case 16:    return ("nvarchar(512)");
        case 17:    return ("nvarchar(max)");
        case 18:    return ("text");
        case 19:    return ("ntext");
        case 20:    return ("binary(512)");
        case 21:    return ("varbinary(512)");
        case 22:    return ("varbinary(max)");
        case 23:    return ("image");
        case 24:    return ("uniqueidentifier");
        case 25:    return ("datetime");
        case 26:    return ("smalldatetime");
        case 27:    return ("timestamp");
        case 28:    return ("xml");
        default:    break;
    }
    return ("udt");
}

function GetDriverType($k, $dataSize)
{
    switch ($k) {
        case 1:     return (SQLSRV_SQLTYPE_INT);
        case 2:     return (SQLSRV_SQLTYPE_TINYINT);
        case 3:     return (SQLSRV_SQLTYPE_SMALLINT);
        case 4:     return (SQLSRV_SQLTYPE_BIGINT);
        case 5:     return (SQLSRV_SQLTYPE_BIT);
        case 6:     return (SQLSRV_SQLTYPE_FLOAT);
        case 7:     return (SQLSRV_SQLTYPE_REAL);
        case 8:     return (SQLSRV_SQLTYPE_DECIMAL(28, 4));
        case 9:     return (SQLSRV_SQLTYPE_NUMERIC(32, 4));
        case 10:    return (SQLSRV_SQLTYPE_MONEY);
        case 11:    return (SQLSRV_SQLTYPE_SMALLMONEY);
        case 12:    return (SQLSRV_SQLTYPE_CHAR($dataSize));
        case 13:    return (SQLSRV_SQLTYPE_VARCHAR($dataSize));
        case 14:    return (SQLSRV_SQLTYPE_VARCHAR('max'));
        case 15:    return (SQLSRV_SQLTYPE_NCHAR($dataSize));
        case 16:    return (SQLSRV_SQLTYPE_NVARCHAR($dataSize));
        case 17:    return (SQLSRV_SQLTYPE_NVARCHAR('max'));
        case 18:    return (SQLSRV_SQLTYPE_TEXT);
        case 19:    return (SQLSRV_SQLTYPE_NTEXT);
        case 20:    return (SQLSRV_SQLTYPE_BINARY($dataSize));
        case 21:    return (SQLSRV_SQLTYPE_VARBINARY($dataSize));
        case 22:    return (SQLSRV_SQLTYPE_VARBINARY('max'));
        case 23:    return (SQLSRV_SQLTYPE_IMAGE);
        case 24:    return (SQLSRV_SQLTYPE_UNIQUEIDENTIFIER);
        case 25:    return (SQLSRV_SQLTYPE_DATETIME);
        case 26:    return (SQLSRV_SQLTYPE_SMALLDATETIME);
        case 27:    return (SQLSRV_SQLTYPE_TIMESTAMP);
        case 28:    return (SQLSRV_SQLTYPE_XML);
        default:    break;
    }
    return (SQLSRV_SQLTYPE_UDT);
}

function IsStreamable($k)
{
    switch ($k) {
        case 12:    return (true);  // nchar(512)
        case 13:    return (true);  // varchar(512)
        case 14:    return (true);  // varchar(max)
        case 15:    return (true);  // nchar(512)
        case 16:    return (true);  // nvarchar(512)
        case 17:    return (true);  // nvarchar(max)
        case 18:    return (true);  // text
        case 19:    return (true);  // ntext
        case 20:    return (true);  // binary
        case 21:    return (true);  // varbinary(512)
        case 22:    return (true);  // varbinary(max)
        case 23:    return (true);  // image
        case 28:    return (true);  // xml
        default:    break;
    }
    return (false);
}

function IsNumeric($k)
{
    switch ($k) {
        case 1:     return (true);  // int
        case 2:     return (true);  // tinyint
        case 3:     return (true);  // smallint
        case 4:     return (true);  // bigint
        case 5:     return (true);  // bit
        case 6:     return (true);  // float
        case 7:     return (true);  // real
        case 8:     return (true);  // decimal(28,4)
        case 9:     return (true);  // numeric(32,4)
        case 10:    return (true);  // money
        case 11:    return (true);  // smallmoney
        default:    break;
    }
    return (false);
}

function IsChar($k)
{
    switch ($k) {
        case 12:    return (true);  // nchar(512)
        case 13:    return (true);  // varchar(512)
        case 14:    return (true);  // varchar(max)
        case 15:    return (true);  // nchar(512)
        case 16:    return (true);  // nvarchar(512)
        case 17:    return (true);  // nvarchar(max)
        case 18:    return (true);  // text
        case 19:    return (true);  // ntext
        case 28:    return (true);  // xml
        default:    break;
    }
    return (false);
}

function IsBinary($k)
{
    switch ($k) {
        case 20:    return (true);  // binary
        case 21:    return (true);  // varbinary(512)
        case 22:    return (true);  // varbinary(max)
        case 23:    return (true);  // image
        default:    break;
    }
    return (false);
}

function IsDateTime($k)
{
    switch ($k) {
        case 25:    return (true);  // datetime
        case 26:    return (true);  // smalldatetime
        case 27:    return (true);  // timestamp
        default:    break;
    }
    return (false);
}

function IsUnicode($k)
{
    switch ($k) {
        case 15:    return (true);  // nchar(512)
        case 16:    return (true);  // nvarchar(512)
        case 17:    return (true);  // nvarchar(max)
        case 19:    return (true);  // ntext
        default:    break;
    }
    return (false);
}

function IsUpdatable($k)
{
    switch ($k) {
        case 27:    return (false); // timestamp
        default:    break;
    }
    return (true);
}

function IsLiteral($k)
{
    switch ($k) {
        case 12:    return (true);  // nchar(512)
        case 13:    return (true);  // varchar(512)
        case 14:    return (true);  // varchar(max)
        case 15:    return (true);  // nchar(512)
        case 16:    return (true);  // nvarchar(512)
        case 17:    return (true);  // nvarchar(max)
        case 18:    return (true);  // text
        case 19:    return (true);  // ntext
        case 24:    return (true);  // uniqueidentifier
        case 25:    return (true);  // datetime
        case 26:    return (true);  // smalldatetime
        case 28:    return (true);  // xml
        default:    break;
    }
    return (false);
}

function GetMetadata($k, $info)
{
    if (strcasecmp($info, 'Name') == 0) {
        return (getColName($k));
    }
    if (strcasecmp($info, 'Size') == 0) {
        return (getColSize($k));
    }
    if (strcasecmp($info, 'Precision') == 0) {
        return (getColPrecision($k));
    }
    if (strcasecmp($info, 'Scale') == 0) {
        return (getColScale($k));
    }
    if (strcasecmp($info, 'Nullable') == 0) {
        return (getColNullable($k));
    }
    return ("");
}

function GetColName($k)
{
    switch ($k) {
        case 1:     return ("c1_int");
        case 2:     return ("c2_tinyint");
        case 3:     return ("c3_smallint");
        case 4:     return ("c4_bigint");
        case 5:     return ("c5_bit");
        case 6:     return ("c6_float");
        case 7:     return ("c7_real");
        case 8:     return ("c8_decimal");
        case 9:     return ("c9_numeric");
        case 10:    return ("c10_money");
        case 11:    return ("c11_smallmoney");
        case 12:    return ("c12_char");
        case 13:    return ("c13_varchar");
        case 14:    return ("c14_varchar_max");
        case 15:    return ("c15_nchar");
        case 16:    return ("c16_nvarchar");
        case 17:    return ("c17_nvarchar_max");
        case 18:    return ("c18_text");
        case 19:    return ("c19_ntext");
        case 20:    return ("c20_binary");
        case 21:    return ("c21_varbinary");
        case 22:    return ("c22_varbinary_max");
        case 23:    return ("c23_image");
        case 24:    return ("c24_uniqueidentifier");
        case 25:    return ("c25_datetime");
        case 26:    return ("c26_smalldatetime");
        case 27:    return ("c27_timestamp");
        case 28:    return ("c28_xml");
        default:    break;
    }
    return ("");
}


function GetColSize($k)
{
    switch ($k) {
        case 12:    return ("512");
        case 13:    return ("512");
        case 14:    return ("0");
        case 15:    return ("512");
        case 16:    return ("512");
        case 17:    return ("0");
        case 18:    return ("2147483647");
        case 19:    return ("1073741823");
        case 20:    return ("512");
        case 21:    return ("512");
        case 22:    return ("0)");
        case 23:    return ("2147483647");
        case 24:    return ("36");
        //case 25:  return ("23");
        //case 26:  return ("16");
        case 27:    return ("8");
        case 28:    return ("0");
        default:    break;
    }
    return ("");
}

function GetColPrecision($k)
{
    switch ($k) {
        case 1:     return ("10");
        case 2:     return ("3");
        case 3:     return ("5");
        case 4:     return ("19");
        case 5:     return ("1");
        case 6:     return ("53");
        case 7:     return ("24");
        case 8:     return ("28");
        case 9:     return ("32");
        case 10:    return ("19");
        case 11:    return ("10");
        case 25:    return ("23");
        case 26:    return ("16");
        default:    break;
    }
    return ("");
}

function GetColScale($k)
{
    switch ($k) {
        case 8:     return ("4");
        case 9:     return ("4");
        case 10:    return ("4");
        case 11:    return ("4");
        case 25:    return ("3");
        case 26:    return ("0");
        default:    break;
    }
    return ("");
}

function GetColNullable($k)
{
    return (IsUpdatable($k) ? "1" : "0");
}

function GetSampleData($k)
{
    switch ($k) {
        case 1: // int
            return ("123456789");

        case 2: // tinyint
            return ("234");

        case 3: // smallint
            return ("5678");

        case 4: // bigint
            return ("123456789987654321");


        case 5: // bit
            return ("1");

        case 6: // float
            return ("123.456");

        case 7: // real
            return ("789.012");

        case 8: // decimal
            return ("12.34");

        case 9: // numeric
            return ("567.89");

        case 10:// money
            return ("321.54");

        case 11:// smallmoney
            return ("67.89");

        case 12:// char
        case 15:// nchar
            return ("The quick brown fox jumps over the lazy dog");

        case 13:// varchar
        case 16:// nvarchar
            return ("The quick brown fox jumps over the lazy dog 9876543210");

        case 14:// varchar(max)
        case 17:// nvarchar(max)
            return ("The quick brown fox jumps over the lazy dog 0123456789");

        case 18:// text
        case 19:// ntext
            return ("0123456789 The quick brown fox jumps over the lazy dog");

        case 20:// binary
            return ("0123456789");

        case 21:// varbinary
            return ("01234567899876543210");

        case 22:// varbinary(max)
            return ("98765432100123456789");

        case 23:// image
            return ("01234567899876543210");

        case 24:// uniqueidentifier
            return ("12345678-9012-3456-7890-123456789012");

        case 25:// datetime
        case 26:// smalldatetime
            return (date("Y-m-d"));

        case 27:// timestamp
            return (null);

        case 28:// xml
            return ("<XmlTestData><Letters1>The quick brown fox jumps over the lazy dog</Letters1><Digits1>0123456789</Digits1></XmlTestData>");

        default:
            break;
    }

    return (null);
}


function CreateDB($conn, $dbName)
{
    Trace("Creating database $dbName ...");
    $tsql = "CREATE DATABASE [$dbName]";
    DropDB($conn, $dbName);

    if (IsPdoMode()) {
        $outcome = $conn->exec($tsql);
        if ($outcome === false) {
            FatalError("Failed to create test database: $dbName");
        }
    } else {
        $stmt = sqlsrv_query($conn, $tsql);
        if ($stmt === false) {
            FatalError("Failed to create test database: $dbName");
        }
        sqlsrv_free_stmt($stmt);
    }
    Trace(" completed successfully.\n");
}

function DropDB($conn, $dbName)
{
    $tsql = "DROP DATABASE [$dbName]";
    if (IsPdoMode()) {
        $mode = $conn->getAttribute(PDO::ATTR_ERRMODE);

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $outcome = $conn->exec($tsql);
        $conn->setAttribute(PDO::ATTR_ERRMODE, $mode);
    } else {
        $stmt = sqlsrv_query($conn, $tsql);
        if ($stmt === false) {
        } else {
            sqlsrv_free_stmt($stmt);
        }
    }
}

/*
function CreateTable($conn, $tableName)
{
    $dataType = "[c1_int] int, [c2_tinyint] tinyint, [c3_smallint] smallint, [c4_bigint] bigint, [c5_bit] bit, [c6_float] float, [c7_real] real, [c8_decimal] decimal(28,4), [c9_numeric] numeric(32,4), [c10_money] money, [c11_smallmoney] smallmoney, [c12_char] char(512), [c13_varchar] varchar(512), [c14_varchar_max] varchar(max), [c15_nchar] nchar(512), [c16_nvarchar] nvarchar(512), [c17_nvarchar_max] nvarchar(max), [c18_text] text, [c19_ntext] ntext, [c20_binary] binary(512), [c21_varbinary] varbinary(512), [c22_varbinary_max] varbinary(max), [c23_image] image, [c24_uniqueidentifier] uniqueidentifier, [c25_datetime] datetime, [c26_smalldatetime] smalldatetime, [c27_timestamp] timestamp, [c28_xml] xml";
    CreateTableEx($conn, $tableName, $dataType);
    if (IsDaasMode())
    {
        $colIndex = "[c1_int], [c2_tinyint], [c3_smallint], [c4_bigint], [c5_bit], [c6_float], [c7_real], [c8_decimal], [c9_numeric]";
        CreateTableIndex($conn, $tableName, $colIndex);
    }
}
*/
function CreateTableEx($conn, $tableName, $dataType)
{
    Trace("Creating table $tableName ...");
    $tsql = "CREATE TABLE [$tableName] ($dataType)";
    dropTable($conn, $tableName);

    if (IsPdoMode()) {
        $outcome = $conn->exec($tsql);
        if ($outcome === false) {
            FatalError("Failed to create test table: $tsql");
        }
    } else {
        $stmt = sqlsrv_query($conn, $tsql);
        if ($stmt === false) {
            FatalError("Failed to create test table: $tsql");
        }
        sqlsrv_free_stmt($stmt);
    }
    Trace(" completed successfully.\n");
}


function CreateTableIndex($conn, $tableName, $colIndex)
{
    require  'MsSetup.inc';
    CreateTableIndexEx($conn, $tableName, $tableIndex, $colIndex);
}


function CreateTableIndexEx($conn, $tableName, $tableIndex, $colIndex)
{
    Trace("Creating table index for $tableName ...");
    $sqlIndex = "CREATE CLUSTERED INDEX [$tableIndex] ON [$tableName]($colIndex)";
    if (IsPdoMode()) {
        $outcome = $conn->exec($sqlIndex);
        if ($outcome === false) {
            FatalError("Failed to create clustered index for test table: $sqlIndex");
        }
    } else {
        $stmt = sqlsrv_query($conn, $sqlIndex);
        if ($stmt === false) {
            FatalError("Failed to create clustered index for test table: $sqlIndex");
        }
        sqlsrv_free_stmt($stmt);
    }
    Trace(" completed successfully.\n");
}

function CreateUniqueIndex($conn, $tableName, $colIndex)
{
    require  'MsSetup.inc';
    CreateUniqueIndexEx($conn, $tableName, $tableIndex, $colIndex);
}

function CreateUniqueIndexEx($conn, $tableName, $tableIndex, $colIndex)
{
    Trace("Creating unique table index for $tableName ...");
    $sqlIndex = "CREATE UNIQUE INDEX [$tableIndex] ON [$tableName]($colIndex)";
    if (IsPdoMode()) {
        $outcome = $conn->exec($sqlIndex);
        if ($outcome === false) {
            FatalError("Failed to create unique index for test table: $sqlIndex");
        }
    } else {
        $stmt = sqlsrv_query($conn, $sqlIndex);
        if ($stmt === false) {
            FatalError("Failed to create unique index for test table: $sqlIndex");
        }
        sqlsrv_free_stmt($stmt);
    }
    Trace(" completed successfully.\n");
}

function dropTable($conn, $tableName)
{
    $tsql = "IF  EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'" . $tableName . "') AND type in (N'U')) DROP TABLE $tableName";
    if (IsPdoMode()) {
        $mode = $conn->getAttribute(PDO::ATTR_ERRMODE);

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $outcome = $conn->exec($tsql);
        $conn->setAttribute(PDO::ATTR_ERRMODE, $mode);
    } else {
        $stmt = sqlsrv_query($conn, $tsql);
        if ($stmt === false) {
        } else {
            sqlsrv_free_stmt($stmt);
        }
    }
}


function SelectFromTable($conn, $tableName)
{
    return (SelectFromTableEx($conn, $tableName, null));
}

function SelectFromTableEx($conn, $tableName, $cond)
{
    if (($cond != null) && (strlen($cond) > 0)) {
        return (SelectQuery($conn, "SELECT * FROM [$tableName] WHERE $cond"));
    } else {
        return (SelectQuery($conn, "SELECT * FROM [$tableName]"));
    }
}

function SelectQuery($conn, $query)
{
    return (SelectQueryEx($conn, $query, null));
}

function SelectQueryEx($conn, $query, $options)
{
    $numFields = 0;
    if (IsPDOMode()) {
        $stmt = ExecuteQuery($conn, $query);
        $numFields = $stmt->columnCount();
    } else {
        if ($options != null) {
            $stmt = sqlsrv_query($conn, $query, null, $options);
        } else {
            $stmt = sqlsrv_query($conn, $query);
        }
        if ($stmt === false) {
            FatalError("Failed to query test table");
        }

        $numFields = sqlsrv_num_fields($stmt);
    }
    if ($numFields <= 0) {
        die("Unexpected number of fields: .$numFields");
    }
    return ($stmt);
}

function RowCount($stmt)
{
    $rowCount = 0;
    if (IsPdoMode()) {
        while ($stmt->fetchColumn()) {
            $rowCount++;
        }
    } else {
        while (sqlsrv_fetch($stmt)) {
            $rowCount++;
        }
    }
    return ($rowCount);
}


function NumRows($conn, $tableName)
{
    $stmt = SelectFromTable($conn, $tableName);
    $rowCount = RowCount($stmt);
    if (IsPdoMode()) {
        $stmt = null;
    } else {
        sqlsrv_free_stmt($stmt);
    }

    return ($rowCount);
}


function InsertQuery($tableName)
{
    include_once 'MsData_UTF8.inc';
    return (InsertQueryExUTF8($tableName, rand(1, 20)));
}

function InsertRows($conn, $tableName, $rowCount)
{
    Trace("Inserting $rowCount rows into $tableName ...");
    $count = 0;
    for ($i = 0; $i < $rowCount; $i++) {
        if (InsertRow($conn, $tableName)) {
            $count++;
        }
    }
    Trace(" completed successfully.\n");
    if ($count != $rowCount) {
        die("$count rows inserted instead of $rowCount\n");
    }
    return ($count);
}

function InsertRowsByRange($conn, $tableName, $minIndex, $maxIndex)
{
    $rowCount = $maxIndex - $minIndex + 1;
    if ($rowCount > 0) {
        Trace("Inserting $rowCount rows into $tableName ...");
        for ($i = $minIndex; $i <= $maxIndex; $i++) {
            InsertRowByIndex($conn, $tableName, $i);
        }
        Trace(" completed successfully.\n");
    }
}
/*
function InsertRow($conn, $tableName)
{
    $tsql = InsertQuery($tableName);
    $stmt = null;

    if (IsPdoMode())
    {
        $stmt = $conn->exec($tsql);
    }
    else
    {
        $stmt = sqlsrv_query($conn, $tsql);
    }
    return (InsertCheck($stmt));
}
*/
function InsertRowEx($conn, $tableName, $dataCols, $dataValues, $dataOptions)
{
    $tsql = "INSERT INTO [$tableName] ($dataCols) VALUES ($dataValues)";
    $stmt = null;

    if (IsPdoMode()) {
        $stmt = $conn->exec($tsql);
    } else {
        $stmt = sqlsrv_query($conn, $tsql, $dataOptions);
    }
    return (InsertCheck($stmt));
}

function InsertRowByIndex($conn, $tableName, $index)
{
    $tsql = InsertQueryEx($tableName, $index);
    $stmt = null;

    if (IsPdoMode()) {
        $stmt = $conn->exec($tsql);
    } else {
        $stmt = sqlsrv_query($conn, $tsql);
    }
    return (InsertCheck($stmt));
}

function InsertStream($conn, $tableName, $dataCols, $dataValues, $dataOptions, $atExec)
{
    $tsql = "INSERT INTO [$tableName] ($dataCols) VALUES ($dataValues)";
    $stmt = null;

    if (IsPdoMode()) {
        $stmt = $conn->exec($tsql);
    } else {
        if ($atExec) {
            $stmt = sqlsrv_query($conn, $tsql, $dataOptions, array('SendStreamParamsAtExec' => 1));
        } else {
            $stmt = sqlsrv_query($conn, $tsql, $dataOptions);
            if ($stmt) {
                while (sqlsrv_send_stream_data($stmt)) {
                }
            }
        }
    }

    return (InsertCheck($stmt));
}


function InsertCheck($stmt)
{
    $numRows = 0;

    if ($stmt === false) {
        FatalError("Failed to insert row into test table");
    }
    if (IsPdoMode()) {
        $numRows = $stmt;
    } else {
        $numRows = sqlsrv_rows_affected($stmt);
        sqlsrv_free_stmt($stmt);
    }
    if ($numRows != 1) {
        die("Unexpected row count at insert: ".$numRows);
    }
    return (true);
}

function GetInsertData($rowIndex, $colIndex, $skip)
{
    $query = InsertQueryEx("TestTable", $rowIndex);
    $data = strstr($query, "((");
    $pos = 1;
    if ($data === false) {
        die("Failed to retrieve data on row $rowIndex");
    }
    $data = substr($data, 2);

    while ($pos < ($colIndex - $skip)) {
        $data = strstr($data, ", (");
        $pos++;
        if ($data === false) {
            die("Failed to retrieve data on row $rowIndex, column $pos");
        }
        $data = substr($data, 3);
    }
    $pos = strpos($data, ")");
    if ($pos === false) {
        die("Failed to isolate data on row $rowIndex, column $pos");
    }
    $data = substr($data, 0, $pos);
    if (strcasecmp($data, "null") == 0) {
        $data = "";
    }
    if (IsUnicode($colIndex)) {   // N'data'
        $data = substr($data, 2, strlen($data) - 3);
    } elseif (IsLiteral($colIndex)) {   // 'data'
        $data = substr($data, 1, strlen($data) - 2);
    } elseif (IsBinary($colIndex)) {   // 0xdata
        $data = substr($data, 2);
    }
    return (trim($data));
}

function CreateProc($conn, $procName, $procArgs, $procCode)
{
    DropProc($conn, $procName);

    if (!IsPdoMode()) {
        $stmt = sqlsrv_query($conn, "CREATE PROC [$procName] ($procArgs) AS BEGIN $procCode END");
        if ($stmt === false) {
            FatalError("Failed to create test procedure");
        }
        sqlsrv_free_stmt($stmt);
    } else {
        $stmt = $conn->query("CREATE PROC [$procName] ($procArgs) AS BEGIN $procCode END");
    }
}

function dropProc($conn, $procName)
{
    if (!IsPdoMode()) {
        $stmt = sqlsrv_query($conn, "DROP PROC [$procName]");
        sqlsrv_free_stmt($stmt);
    } else {
        $query = "IF OBJECT_ID('[$procName]', 'P') IS NOT NULL DROP PROCEDURE [$procName]";
        $stmt = $conn->query($query);
    }
}

function CallProc($conn, $procName, $procArgs, $procValues)
{
    $stmt = CallProcEx($conn, $procName, "", $procArgs, $procValues);
    sqlsrv_free_stmt($stmt);
}

function CallProcEx($conn, $procName, $procPrefix, $procArgs, $procValues)
{
    $stmt = sqlsrv_query($conn, "{ $procPrefix CALL [$procName] ($procArgs)}", $procValues);
    if ($stmt === false) {
        FatalError("Failed to call test procedure");
    }
    return ($stmt);
}


function CreateFunc($conn, $funcName, $funcArgs, $retType, $funcCode)
{
    DropFunc($conn, $funcName);
    try {
        $stmt = $conn->query("CREATE FUNCTION [$funcName] ($funcArgs) RETURNS $retType AS BEGIN $funcCode END");
    } catch (PDOException $e) {
        echo "Failed to create test function\n";
        var_dump($e);
    }
    unset($stmt);
}

function DropFunc($conn, $funcName)
{
    try {
        $conn->query("DROP FUNCTION [$funcName]");
    } catch (PDOException $e) {
        ; // do nothing
    }
}


function CallFunc($conn, $funcName, $funcArgs, $funcValues)
{
    $stmt = sqlsrv_query($conn, "{ ? = CALL [$funcName]($funcArgs)}", $funcValues);
    if ($stmt === false) {
        FatalError("Failed to call test function");
    }
    sqlsrv_free_stmt($stmt);
}

function handle_errors()
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $count = count($errors);
    if ($count == 0) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $count = count($errors);
    }
    if ($count > 0) {
        for ($i = 0; $i < $count; $i++) {
            Trace($errors[$i]['message']."\n");
        }
    }
}

function str2hex($dataIn)
{
    $dataOut = "";
    $len = strlen($dataIn);
    for ($i = 0; $i < $len; $i++) {
        $ch = strtoupper(substr($dataIn, $i, 1));
        if ($ch == "A") {
            $dataOut = $dataOut."41";
        } elseif ($ch == "B") {
            $dataOut = $dataOut."42";
        } elseif ($ch == "C") {
            $dataOut = $dataOut."43";
        } elseif ($ch == "D") {
            $dataOut = $dataOut."44";
        } elseif ($ch == "E") {
            $dataOut = $dataOut."45";
        } elseif ($ch == "F") {
            $dataOut = $dataOut."46";
        } else {
            $dataOut = $dataOut."3".$ch;
        }
    }
    return ($dataOut);
}

function PhpVersionComponents(&$major, &$minor, &$sub)
{
    $str_version = phpversion();

    $major = strtok($str_version, ".");
    $minor = strtok(".");
    $sub = strtok(".");
}

function getTodayDateAsString($conn)
{
    $tsql = 'SELECT CONVERT (VARCHAR(20), GETDATE())';
    $stmt = $conn->query($tsql);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    return $row[0];
}

function compareResourceToInput($actual, $expected)
{
    $size = 8192;
    $pos = 0;
    $matched = true;
    while (!feof($actual)) {
        $original = fread($actual, $size);
        $str = substr($expected, $pos, $size);
        
        if ($original !== $str) {
            $matched = false;
            break;
        }
        $pos += $size;
    }
    
    return $matched;
}
