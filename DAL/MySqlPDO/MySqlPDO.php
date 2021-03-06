<?php

    /**
     * PDO (MySQL) wrapper
     *
     * @filesource
     */

    /* First check and make sure php module pdo_mysql is installed */

    if ( !extension_loaded('pdo_mysql') ) {
        die('MySqlPDO requires mysql_pdo module installed!');
    }


    /**
     * MySqlPDO: A wrapper and utility class using PDO (MySQL)
     *
     * @author Anjan Bhowmik
     */
    class MySqlPDO
    {

        /**
         * Private static copy for singleton system
         *
         * @var MySqlPDO $_oDb
         */

        private static $_oDb = NULL;

        /**
         * Last executed statement
         *
         * @var PDOStatement $_oLastStatement
         */

        private $_oLastStatement = NULL;

        /**
         * Underlying PDO object
         *
         * @var PDO $_oPdo
         */

        private $_oPdo = NULL;

        /**
         * The name of the database passed to Connect() method
         *
         * @var string
         */

        private $_sDatabaseName = NULL;

        /**
         * Protected constructor
         */

        protected function __construct() { }

        /**
         * Prepares a SELECT sql query from given parameters
         *
         * @param string    $tableName      A single table name or a coma separated list of table names for join
         * @param string    $fields         A coma separated list of valid field names. [Default : '*']
         * @param string    $filterClause   A string presenting the where clause. Supports parameters for prepared statements
         * @param array     $sortBy         An array of sortable columns in key/value pairs. E.g. array('name' => 'asc','email => 'desc')
         * @param int       $offset         Row offset for paging. Passing a negative value will disable paging
         * @param int       $limit          Number of rows to return in each page. Ignored if offset is negative [Default : 10]
         *
         * @return string
         */

        public function __PrepareSqlQueryForSelect($tableName, $fields = '*', $filterClause = '', $sortBy = array(),$offset = -1, $limit = 10) {

            $tableName = trim($tableName);

            if($tableName == '') {
                return array();
            }

            $fields = trim($fields);

            if($fields == '') {
                $fields = '*';
            }

            $sql = "select {$fields} from `$tableName`";

            if($filterClause != '') {

                $sql .= " where {$filterClause}";

            }

            if(is_array($sortBy) && count($sortBy) > 0) {

                $temp = array();

                foreach ( $sortBy as $k => $v ) {
                    $temp[] = "{$k} {$v}";
                }

                $sql .= " order by ".join(',',$temp);

            }

            $offset = (int)$offset;

            if($offset >= 0) {

                $limit = (int)$limit;

                if($limit < 1) {
                    $limit = 1;
                }

                $sql .= " limit $offset,$limit";

            }

            return $sql;

        }

        /**
         * Connect to database with given info
         *
         * @param string    $database       Database Name
         * @param string    $user           User Name
         * @param string    $password       The Password
         * @param string    $host           MySQL Server name [default: localhost]
         * @param int       $port           Port number [default: 3306]
         * @param array     $pdoOptions     PDO Driver options, if required
         */

        //public function Connect($database = DB_NAME, $user = DB_USER, $password = DB_PASSWORD, $host = DB_URL , $port = 3306, $pdoOptions = array())
        public function Connect($database = '', $user = 'root', $password = '', $host = 'localhost', $port = 3306, $pdoOptions = array())
        {
            if ( !is_array($pdoOptions) ) {
                $pdoOptions = array();
            }

            // Use UTF-8 encoding

            $pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'UTF8'";

            $this->_oPdo = new PDO("mysql:host={$host};dbname={$database};port={$port};charset=UTF8", $user, $password, $pdoOptions);

            // Store the database name for later use

            $this->_sDatabaseName = $database;

            // set fetch mode to associated array

            $this->SetArrayFetchMode('assoc');

            // Set error mode to exception. Silent mode is default though

            $this->SetErrorMode(PDO::ERRMODE_EXCEPTION);

        }

        /**
         * Create a new instance. for situations when u need a different connection
         *
         * @return MySqlPDO
         */

        public static function CreateNewInstance()
        {
            return new MySqlPDO();
        }

        /**
         * Delete row(s) of data from given table
         *
         * @param string    $tableName      The table name
         * @param string    $whereClause    The where clause with parameters
         * @param array     $whereBind      Parameter values for where clause
         *
         * @return int
         */

        public function DeleteData($tableName, $whereClause = '', $whereBind = array()) {

            $tableName = trim($tableName);

            $whereClause = trim($whereClause);

            if($tableName == '') {
                return null;
            }

            if($whereClause == '') {
                return null;
            }

            $sql = "delete from `$tableName` where ".$whereClause;

            return $this->ExecuteNonQuery($sql,$whereBind);

        }

        /**
         * Instructs MySql to disable/enable foreign key check
         *
         * <p>
         * If foreign key check is disabled, records from parent table can be deleted,
         * even if dependent rows in child tables exist. But this will create orphaned
         * records.
         * </p>
         *
         * @param bool  $disabled
         *
         * @return int
         */

        public function DisableForeignKeyCheck($disabled = true) {

            $disabled = $disabled ? 0 : 1;

            $sql = "SET FOREIGN_KEY_CHECKS = $disabled";

            return $this->ExecuteNonQuery($sql);

        }

        /**
         * Check for the existence of the given value in the table column
         *
         * <p>
         * The search is performed for an exact match using = operator.
         * </p>
         *
         * @param string    $tableName The table name
         * @param string    $columnName The column name
         * @param string    $columnValue The column value
         *
         * @return bool|null    Null is returned if table or column name is not provided. Else bool is returned.
         */

        public function DoesValueExist($tableName,$columnName,$columnValue) {

            $tableName = trim($tableName);
            $columnName = trim($columnName);

            if($tableName == '' || $columnName == '') {
                return null;
            }

            $sql = "select count(*) from `$tableName` where `$columnName` = ?";

            $count = (int) $this->GetScaler($sql,array($columnValue));

            return $count > 0;

        }



        /**
         * Execute a DDL statement (E.g. insert, update,delete,truncate) and returns
         * affected row number
         *
         * @param string    $sql            The sql query
         * @param array     $bind           Parameter values
         * @param array     $pdoOptions     PDO driver options
         *
         * @return int
         */

        public function ExecuteNonQuery($sql,$bind = array(),$pdoOptions = array())
        {

            $this->_oLastStatement = $this->_oPdo->prepare($sql,$pdoOptions);

            $res = $this->_oLastStatement->execute($bind);

            if($res) {
                return $this->_oLastStatement->rowCount();
            }

            return false;

        }

        /**
         * Executes the provided sql query as it is.
         *
         * <p>
         * This function is similar to ExecuteNonQuery(), except it will execute the sql query immediately without
         * further processing. This function will come handy, when u need to prepare a query, that does not fit nicely
         * into prepared query system.
         * </p>
         *
         * <p>
         * The DDL queries executed through this function does not make use of prepared query system. So, any data
         * passed within the query must be properly quoted by yourself using quote()
         * </p>
         *
         * @see MySqlPDO::ExecuteNonQuery() ExecuteNonQuery()
         *
         * @param string $sql The sql query
         *
         * @return int
         */

        public function ExecuteNonQueryDirect($sql) {

            return $this->_oPdo->exec($sql);

        }

        /**
         * Executes a SELECT query and gets first row of result as array
         *
         * <p>
         * This function works similarly as <b>GetArrayList</b> taking sql query
         * and bound parameter values (if any). The difference is it only returns
         * the first row. Use this when you are looking for just one record.
         * </p>
         *
         * <p>
         * The array fetch mode can be set using <b>SetArrayFetchMode()</b> to be
         * associative, numeric or both.
         * </p>
         *
         * @example phpdoc-examples/get_array.php
         *
         * @see MySqlPDO::GetArrayList()        GetArrayList()
         * @see MySqlPDO::SetArrayFetchMode()   SetArrayFetchMode()
         *
         * @param string    $sql            The sql statement. It will be prepared
         * @param array     $bind           The parameters to be bound (if any)
         * @param array     $pdoOptions     Extra PDO driver options
         *
         * @return array
         */

        public function GetArray($sql, $bind = array(), $pdoOptions = array())
        {

            $rows = $this->GetArrayList($sql, $bind, $pdoOptions);

            if ( empty($rows) ) {

                return array();

            }

            return current($rows);

        }


        /**
         * Executes a SELECT query and gets result as list of array
         *
         * <p>
         * Use this function to execute select queries and get result data as list
         * of array. The sql statement is executed as prepared statement. the <b>$bind</b>
         * param lets you to pass the values for parameters.
         * </p>
         *
         * <p>
         * If you only need the first row from result set as array, use <b>GetArray()</b> instead.
         * </p>
         *
         * <p>
         * The array fetch mode can be set using <b>SetArrayFetchMode()</b> to be
         * associative, numeric or both.
         * </p>
         *
         * @example phpdoc-examples/get_array_list.php
         *
         * @see MySqlPDO::GetArray()            GetArray()
         * @see MySqlPDO::SetArrayFetchMode()   SetArrayFetchMode()
         *
         * @param string    $sql            The sql statement. It will be prepared
         * @param array     $bind           The parameters to be bound (if any)
         * @param array     $pdoOptions     Extra PDO driver options
         *
         * @return array
         */

        public function GetArrayList($sql, $bind = array(), $pdoOptions = array())
        {

            $this->_oLastStatement = $this->_oPdo->prepare($sql, $pdoOptions);

            $this->_oLastStatement->execute($bind);

            $rows = $this->_oLastStatement->fetchAll($this->_oPdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));

            $this->_oLastStatement->closeCursor();

            return $rows;
        }

        /**
         * Gets the avg value for a column
         *
         * @param $table
         * @param $column
         *
         * @return bool|null|string
         */

        public function GetAvgColumnValue($table,$column) {

            $table = trim($table);
            $column = trim($column);

            if($table == '' || $column == '') {
                return false;
            }

            $sql = "select avg(`$column`) from `{$table}`";

            return $this->GetScaler($sql);

        }

        /**
         * Execute the query and get a column of data
         *
         * <p>
         * The function executes the query and instead of returning rows of data set, it simply
         * returns the data for a particular column as a simple php array with numeric index.
         * </p>
         *
         * @param string    $sql            The SQL query
         * @param array     $bind           Values for query parameters
         * @param int       $colIndex       The index of column [Default: 0]
         * @param array     $pdoOptions     PDO driver options
         *
         * @return array
         */

        public function GetColumn($sql, $bind = array(), $colIndex = 0, $pdoOptions = array())
        {

            $colIndex = (int) $colIndex;

            if ( $colIndex < 0 ) {
                $colIndex = 0;
            }

            $this->_oLastStatement = $this->_oPdo->prepare($sql, $pdoOptions);

            $this->_oLastStatement->execute($bind);

            $rows = $this->_oLastStatement->fetchAll(PDO::FETCH_COLUMN, $colIndex);

            $this->_oLastStatement->closeCursor();

            return $rows;
        }

        /**
         * Returns the underlying PDO object
         *
         * @return PDO
         */

        public function GetConnection()
        {
            return $this->_oPdo;
        }

        /**
         * Get instance in singleton style
         *
         * @return MySqlPDO
         */

        public static function GetInstance()
        {

            if ( self::$_oDb === NULL ) {
                self::$_oDb = new MySqlPDO();
            }

            return self::$_oDb;

        }

        /**
         * Execute query and get key/value pairs
         *
         * <p>
         * This function executes the and creates an associative array with key being
         * the values from first column and value being the values from second column.
         * If there are multiple different values for a single value in first column,
         * only the last value from second column will be available.
         * </p>
         *
         * <p>
         * The SQL query passed to this function, MUST have EXACTLY 2 columns.
         * </p>
         *
         * @param string    $sql            The sql query
         * @param array     $bind           Query parameter values
         * @param array     $pdoOptions     PDO Driver options
         *
         * @return array
         */

        public function GetKeyValuePairs($sql, $bind = array(), $pdoOptions = array())
        {

            $this->_oLastStatement = $this->_oPdo->prepare($sql, $pdoOptions);

            $this->_oLastStatement->execute($bind);

            $rows = $this->_oLastStatement->fetchAll(PDO::FETCH_KEY_PAIR);

            $this->_oLastStatement->closeCursor();

            return $rows;

        }

        /**
         * Get last statement object executed
         *
         * @return PDOStatement
         */

        public function GetLastStatement()
        {
            return $this->_oLastStatement;
        }

        /**
         * Gets auto increment id generated in last insert query
         *
         * @return string
         */

        public function GetLastInsertId() {

            return $this->_oPdo->lastInsertId();

        }

        /**
         * Gets the max value for a column
         *
         * @param $table
         * @param $column
         *
         * @return bool|null|string
         */

        public function GetMaxColumnValue($table,$column) {

            $table = trim($table);
            $column = trim($column);

            if($table == '' || $column == '') {
                return false;
            }

            $sql = "select max(`$column`) from `{$table}`";

            return $this->GetScaler($sql);

        }

        /**
         * Gets the minimum value for column
         *
         * @param $table
         * @param $column
         *
         * @return bool|null|string
         */

        public function GetMinColumnValue($table,$column) {

            $table = trim($table);
            $column = trim($column);

            if($table == '' || $column == '') {
                return false;
            }

            $sql = "select min(`$column`) from `{$table}`";

            return $this->GetScaler($sql);

        }

        /**
         * Executes the sql query and get the first row as object
         *
         * <p>
         * It is similar to <b>GetObjectList()</b>, the difference is that it only returns
         * the first row as object.
         * </p>
         *
         * @see MySqlPDO::GetObjectList() GetObjectList()
         *
         * @param string    $sql            The sql query to be used in prepared statement.
         * @param array     $bind           The parameter values for the query.
         * @param string    $className      The PHP class name. The class must be defined.
         * @param array     $ctorArgs       If the class constructor requires parameters, pass here.
         * @param array     $pdoOptions     Extra PDO driver options.
         *
         * @return object|null Returns null if record set is empty.
         */

        public function GetObject($sql, $bind = array(), $className = 'stdClass', $ctorArgs = array(), $pdoOptions = array())
        {

            $list = $this->GetObjectList($sql, $bind, $className, $ctorArgs, $pdoOptions);

            if ( empty($list) ) {
                return NULL;
            }

            return current($list);

        }

        /**
         * Executes the sql query and get data as list of objects
         *
         * <p>
         * Like GetArrayList() this function performs the select query, but instead of array, it
         * returns a list of objects. If no class name is provided, <b>stdClass</b> is assumed.
         * </p>
         *
         * <p>
         * PDO sets the class variables BEFORE calling the constructor. You can use this to further
         * process the data returned if you need
         * </p>
         *
         * @example phpdoc-examples/get_object_list.php
         *
         * @param string    $sql            The sql query to be used in prepared statement.
         * @param array     $bind           The parameter values for the query.
         * @param string    $className      The PHP class name. The class must be defined.
         * @param array     $ctorArgs       If the class constructor requires parameters, pass here.
         * @param array     $pdoOptions     Extra PDO driver options.
         *
         * @return array
         */

        public function GetObjectList($sql, $bind = array(), $className = 'stdClass', $ctorArgs = array(), $pdoOptions = array())
        {

            $this->_oLastStatement = $this->_oPdo->prepare($sql, $pdoOptions);

            $this->_oLastStatement->execute($bind);

            $rows = $this->_oLastStatement->fetchAll(PDO::FETCH_CLASS, $className, $ctorArgs);

            $this->_oLastStatement->closeCursor();

            return $rows;

        }

        /**
         * Executes the query and gets a single string value
         *
         * <p>
         * This function executes the query. Then if the result set is empty returns null, else it will
         * return the value of first column of the row. In other words, you get the value of first column
         * of the first row as string.
         * </p>
         *
         * @param string    $sql            The sql query to be used in prepared statement.
         * @param array     $bind           The parameter values for the query.
         * @param array     $pdoOptions     Extra PDO driver options.
         *
         * @return string|null
         */

        public function GetScaler($sql,$bind = array(), $pdoOptions = array()) {

            $oldFetchMode = $this->_oPdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

            $this->SetArrayFetchMode('num');

            $row = $this->GetArray($sql,$bind,$pdoOptions);

            if(empty($row)) {
                return NULL;
            }

            $this->_oPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $oldFetchMode);

            return $row[0];

        }

        /**
         * Gets the sum for column
         *
         * @param $table
         * @param $column
         *
         * @return bool|null|string
         */

        public function GetTotalColumnValue($table,$column) {

            $table = trim($table);
            $column = trim($column);

            if($table == '' || $column == '') {
                return false;
            }

            $sql = "select sum(`$column`) from `{$table}`";

            return $this->GetScaler($sql);

        }

        /**
         * Gets detailed column info for the given table
         *
         * <p>
         * This function fetches column info like: name, type, size, etc. for the given table. This is similar to
         * GetTableColumnNames() except it returns much more info than just names. The column data list is sorted by
         * ordinal position or the position in which they are defined in the table.
         * </p>
         *
         * @see MySqlPDO::GetTableColumnNames()  GetTableColumnNames()
         *
         * @param string    $tableName      Name of table.
         * @param string    $databaseName   Name of database containing the table. if empty, current database is used
         *
         * @return array
         */

        public function GetTableColumnList($tableName, $databaseName = null) {

            $tableName = trim($tableName);
            $databaseName = trim($databaseName);

            if($tableName == '') {
                return array();
            }

            if($databaseName == '') {
                $databaseName = $this->_sDatabaseName;
            }


            $sql = "select * from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ? order by ORDINAL_POSITION";

            return $this->GetArrayList($sql,array($databaseName,$tableName));

        }

        /**
         * Gets list of column names from a table
         *
         * <p>
         * Using this function you can retrieve a list of column names from any table in any database the connecting
         * user has permission on.
         * </p>
         *
         * @param string    $tableName      Name of table.
         * @param string    $databaseName   Name of database containing the table. if empty, current database is used
         * @param string    $sortBy         Sort column names by any of ["name","position"] (Default: "position")
         *
         * @return array
         */

        public function GetTableColumnNames($tableName, $databaseName = null,$sortBy = 'position') {

            $tableName = trim($tableName);
            $databaseName = trim($databaseName);

            if($tableName == '') {
                return array();
            }

            if($databaseName == '') {
                $databaseName = $this->_sDatabaseName;
            }


            $sql = "select COLUMN_NAME from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ? order by ";

            switch($sortBy) {

                case 'name':
                    $sql .= "COLUMN_NAME";
                    break;
                case 'position':
                default:
                    $sql .= "ORDINAL_POSITION";
                    break;

            }

            return $this->GetColumn($sql,array($databaseName,$tableName),0);

        }

        /**
         * Returns list of table names in the given database.
         *
         * <p>
         * This function returns a list of available tables in the given database. the list is sorted by
         * table name. If no database name is provided, the current open database name is used.
         * </p>
         *
         * @param string    $databaseName   The name of database. If empty, uses the current database name.
         *
         * @return array
         */

        public function GetTableNames($databaseName = null) {

            $databaseName = trim($databaseName);

            if($databaseName == '') {
                $databaseName = $this->_sDatabaseName;
            }

            $sql = "select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = ? and TABLE_TYPE = 'BASE TABLE' order by TABLE_NAME";

            return $this->GetColumn($sql,array($databaseName),0);

        }

        /**
         * Inserts a row of data into a table
         *
         * <p>
         * Inserts a single row of data into a table. After insert if required use MySqlPDO::GetLastInsertId() to fetch
         * the generated auto inc. column value.
         * </p>
         *
         * @param string    $tableName  The name of the table to insert data
         * @param array     $data       The data as key/value pairs. E.g. array('column1' => 'data1','column2' => 'data2', ...)
         *
         * @return bool|int Returns false if no table name or data specified. Returns 0 if insert fails. Else returns 1
         */

        public function InsertData($tableName, $data = array()) {

            $tableName = trim($tableName);

            if($tableName == '') {
                return false;
            }

            if(is_array($data) && count($data) > 0) {

                $fields = array();
                $params = array();
                $values = array();

                foreach ( $data as $k => $v ) {

                    $fields[] = "`$k`";
                    $params[] = '?';
                    $values[] = $v;

                }

                $sql = "insert into `{$tableName}` (".join(', ',$fields).") values (".join(', ',$params).")";

                return $this->ExecuteNonQuery($sql,$values);

            }

            return false;
        }

        /**
         * Generates an actual sql query by resolving bound params
         *
         * <p>
         * Replaces any parameter placeholders in a query with the value of that
         * parameter. Useful for debugging. Assumes anonymous parameters from
         * $params are are in the same order as specified in $query. <b>This is a
         * slightly modified version of original one</b>
         * </p>
         *
         * @param   string  $query      The sql query with parameter placeholders
         * @param   array   $params     The array of substitution parameters
         *
         * @return  string The interpolated query
         *
         * @link http://stackoverflow.com/questions/210564/getting-raw-sql-query-string-from-pdo-prepared-statements The original article in stackoverflow about InterpolateQuery()
         */

        public static function InterpolateQuery($query, $params)
        {
            $_pdo = self::GetInstance()->_oPdo;

            $keys = array();

            # build a regular expression for each parameter
            foreach ( $params as $key => &$value ) {

                if ( is_string($key) ) {
                    $keys[] = '/:' . $key . '/';
                } else {
                    $keys[] = '/[?]/';
                }

                $value = $_pdo->quote($value, PDO::PARAM_STR);
            }

            $query = preg_replace($keys, $params, $query, 1, $count);

            return $query;
        }

        /**
         * Prepares a PDOStatement, calls it's execute() method and returns it.
         *
         * <p>
         * This function takes your sql query and parameter values, executes that and
         * instead of returning the results itself, it provides you with the PDOStatement
         * object.
         *</p>
         *
         * <p>
         * This will be quite useful in situations, where the resul tset is quite
         * big and can take up a lot of memory. In this case you can use the PDOStatement
         * and loop through the result set using PDOStatement::fetch() method.
         * </p>
         *
         * @param string    $query          The sql query
         * @param array     $bind           Query parameter values
         * @param array     $pdoOptions     PDO driver options
         *
         * @return PDOStatement
         */

        public function Query($query, $bind = array(), $pdoOptions = array())
        {

            $this->_oLastStatement = $this->_oPdo->prepare($query, $pdoOptions);

            $this->_oLastStatement->execute($bind);

            return $this->_oLastStatement;

        }

        /**
         * Sets array fetch mode
         *
         * <p>
         * While fetching results as array, the fetch mode can be set to one of these three
         * values <b>["assoc", "both", "num"]</b>. the default value is <b>"assoc"</b>. This only
         * works with <b>GetArray()</b> and <b>GetArrayList()</b> functions.
         * </p>
         *
         * @see MySqlPDO::GetArray()        GetArray()
         * @see MySqlPDO::GetArrayList()    GetArrayList()
         *
         * @param string $mode
         */

        public function SetArrayFetchMode($mode = 'assoc')
        {

            if ( $this->_oPdo ) {

                switch ( $mode ) {
                    case 'num':
                        $this->_oPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);
                        break;
                    case 'both':
                        $this->_oPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_BOTH);
                        break;
                    case 'assoc':
                    default:
                        $this->_oPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                        break;

                }

            }

        }

        /**
         * Selects data as a list of array from the given table(s)
         *
         * <p>
         * This function builds upon GetArrayList() and provides a shorter way to fetch data from a single table
         * or a list of tables using implicit join. Sing it uses GetArrayList() you can use SetArrayFetchMode()
         * to set the array index type to one of ['assoc','num','both']
         * </p>
         *
         * @see MySqlPDO::GetArrayList()            GetArrayList()
         * @see MySqlPDO::SetArrayFetchMode()       SetArrayFetchMode()
         *
         * @param string    $tableName      A single table name or a coma separated list of table names for join
         * @param string    $fields         A coma separated list of valid field names. [Default : '*']
         * @param string    $filterClause   A string presenting the where clause. Supports parameters for prepared statements
         * @param array     $filterBind     Values for bound query parameters
         * @param array     $sortBy         An array of sortable columns in key/value pairs. E.g. array('name' => 'asc','email => 'desc')
         * @param int       $offset         Row offset for paging. Passing a negative value will disable paging
         * @param int       $limit          Number of rows to return in each page. Ignored if offset is negative [Default : 10]
         * @param array     $pdoOptions     Extra PDO driver options
         *
         * @return array
         */

        public function SelectArrayListFromTable($tableName, $fields = '*', $filterClause = '', $filterBind = array()  , $sortBy = array(),$offset = -1, $limit = 10, $pdoOptions = array()) {

            $sql = $this->__PrepareSqlQueryForSelect($tableName,$fields,$filterClause,$sortBy,$offset,$limit);

            return $this->GetArrayList($sql,$filterBind,$pdoOptions);

        }

        /**
         * Selects data as a list of objects from the given table(s)
         *
         * <p>
         * This functions works same as SelectArrayListFromTable(), except it returns list of object instead of
         * array.
         * </p>
         *
         * @see MySqlPDO::SelectArrayListFromTable()    SelectArrayListFromTable()
         *
         * @param string    $tableName A single table name or a coma separated list of table names for join
         * @param string    $fields A coma separated list of valid field names. [Default : '*']
         * @param string    $filterClause A string presenting the where clause. Supports parameters for prepared statements
         * @param array     $filterBind Values for bound query parameters
         * @param array     $sortBy An array of sortable columns in key/value pairs. E.g. array('name' => 'asc','email => 'desc')
         * @param int       $offset Row offset for paging. Passing a negative value will disable paging
         * @param int       $limit Number of rows to return in each page. Ignored if offset is negative [Default : 10]
         * @param string    $className The name of teh class. the class must exist. [Default: 'stdClass']
         * @param array     $ctorArgs The class __construct() parameters
         * @param array     $pdoOptions Extra PDO driver options
         *
         * @return array
         */


        public function SelectObjectListFromTable($tableName, $fields = '*', $filterClause = '', $filterBind = array()  , $sortBy = array(),$offset = -1, $limit = 10, $className = 'stdClass', $ctorArgs = array() ,$pdoOptions = array()) {

            $sql = $this->__PrepareSqlQueryForSelect($tableName,$fields,$filterClause,$sortBy,$offset,$limit);

            return $this->GetObjectList($sql,$filterBind,$className,$ctorArgs,$pdoOptions);
        }


        /**
         * Set an underlying mysql PDO object
         *
         * @param PDO $pdo
         */

        public function SetConnection($pdo)
        {
            $this->_oPdo = $pdo;
        }

        /**
         * Sets error mode. based on this settings you can control whether to display error messages for debugging or
         * just silently ignore the error and pretend no data was returned.
         *
         * @param int $mode
         */

        public function SetErrorMode($mode = PDO::ERRMODE_SILENT)
        {
            $this->_oPdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
        }


        /**
         * Truncate a table or empties the table
         *
         * <p>
         * This will will attempt to empty the table and reset auto inc. counter to 0. but
         * if any foreign key check fails, the truncate would stop at that row. If you need
         * to bypass this foreign key check, you can use MySqlPDO::DisableForeignKeyCheck()
         * </p>
         *
         * @see MySqlPDO::DisableForeignKeyCheck() DisableForeignKeyCheck()
         *
         * @param string    $tableName     The table name
         *
         * @return bool
         */

        public function TruncateTable($tableName) {

            $tableName = trim($tableName);

            if($tableName == '') {
                return false;
            }

            return $this->ExecuteNonQuery("truncate $tableName");

        }

        /**
         * Updates row(s) of data in given table
         *
         * @param string    $tableName      The table name
         * @param array     $updateData     The key/value pairs of data.
         * @param string    $whereClause    The where clause with parameters
         * @param array     $whereBind      Parameter values for where clause
         *
         * @return int
         */

        public function UpdateData($tableName,$updateData = array(),$whereClause = '', $whereBind = array()) {

            $tableName = trim($tableName);
            $whereClause = trim($whereClause);

            if($tableName == '') {
                return null;
            }

            if(!is_array($updateData) || count($updateData) == 0) {
                return null;
            }

            if($whereClause == '') {
                return null;
            }

            $sql = "update `$tableName` set ";

            $bind = array();

            # update data

            $temp = array();

            foreach ( $updateData as $k => $v ) {

                $temp[] = "`$k` = ?";
                $bind[] = $v;

            }

            $sql .= join(', ',$temp);

            $sql .= " where $whereClause";

            foreach ( $whereBind as $val ) {
                $bind[] = $val;
            }

            return $this->ExecuteNonQuery($sql,$bind);

        }

    }