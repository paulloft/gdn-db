<?php

namespace Garden\Db;

use Garden\Db\Exceptions as Exception;

/**
 * Database connection wrapper/helper.
 *
 * You may get a database instance using `Database::instance('name')` where
 * name is the [config](database/config) group.
 *
 * This class provides connection instance management via Database Drivers, as
 * well as quoting, escaping and other related functions. Querys are done using
 * {Database\Query} and {Database\Query\Builder} objects, which can be easily
 * created using the [DB] helper class.
 */
abstract class Database {

    // Query types
    const SELECT = 1;
    const INSERT = 2;
    const UPDATE = 3;
    const DELETE = 4;

    /**
     * @var string $default default instance name
     */
    public static $default = 'default';

    /**
     * @var string $defaultConfigPath default path of config file
     */
    public static $defaultConfigPath = PATH_ROOT . '/config/database.php';

    /**
     * @var array $instances Database instances
     */
    public static $instances = [];

    /**
     * @var  string  the last query executed
     */
    public $lastQuery;

    // Character that is used to quote identifiers
    protected $identifier = '"';

    // Instance name
    protected $_instance;

    // Raw server connection
    protected $_connection;

    // Configuration array
    protected $_config;

    /**
     * Get a singleton Database instance. If configuration is not specified,
     * it will be loaded from the database configuration file using the same
     * group as the name.
     *
     * @example $db = Database::instance(); // Load the default database
     * @example $db = Database::instance('custom', $config); // Create a custom configured instance
     *
     * @param string $name instance name
     * @param array $config configuration parameters
     * @throws Exception\Error
     * @return self
     */
    public static function instance($name = null, array $config = null): self
    {
        if ($name === null) { // Use the default instance name
            $name = self::$default;
        }

        if (!isset(self::$instances[$name])) {
            if ($config === null) { // Load the configuration for this database
                $config = include self::$defaultConfigPath;
            }

            if (!$config['driver']) {
                throw new Exception\Error("Database driver not defined in $name configuration");
            }

            // Set the driver class name
            $driver = 'Garden\\Db\\Driver\\' . ucfirst($config['driver']);

            // Create the database connection instance
            $driver = new $driver($name, $config);

            // Store the database instance
            self::$instances[$name] = $driver;
        }

        return self::$instances[$name];
    }

    /**
     * Stores the database configuration locally and name the instance
     *
     * @return void
     */
    protected function __construct($name, array $config)
    {
        // Set the instance name
        $this->_instance = $name;

        // Store the config locally
        $this->_config = $config;
        $this->_connection = $config['connection'] ?? null;

        if (empty($this->_config['tablePrefix'])) {
            $this->_config['tablePrefix'] = '';
        }
    }

    /**
     * Set the connection character set. This is called automatically by [Database::connect].
     *
     * @throws Exception\Database
     * @param string $charset character set name
     * @return void
     */
    abstract public function setCharset($charset);

    /**
     * Perform an SQL query of the given type.
     *
     *     // Make a SELECT query and use objects for results
     *     $db->query(Database::SELECT, 'SELECT * FROM groups', true);
     *
     *     // Make a SELECT query and use "Model_User" for the results
     *     $db->query(Database::SELECT, 'SELECT * FROM users LIMIT 1', 'Model_User');
     *
     * @param integer $type Database::SELECT, Database::INSERT, etc
     * @param string $sql SQL query
     * @param mixed $asObject result object class string, true for stdClass, false for assoc array
     * @param array $params object construct parameters for result class
     * @return Database\Result|array|int
     */
    abstract public function query($type, $sql, $asObject = false, array $params = null);

    /**
     * Start a SQL transaction
     *
     * @param string $mode transaction mode
     * @return bool
     */
    abstract public function begin($mode = null): bool;

    /**
     * Commit the current transaction
     *
     * @return bool
     */
    abstract public function commit(): bool;

    /**
     * Abort the current transaction
     *
     * @return bool
     */
    abstract public function rollback(): bool;

    /**
     * Sanitize a string by escaping characters that could cause an SQL
     * injection attack.
     *
     * @param string $value value to quote
     * @return string
     */
    abstract public function escape($value): string;

    /**
     * Connect to the database. This is called automatically when the first
     * query is executed.
     *
     * @throws Exception\Database
     * @return void
     */
    abstract public function connect();

    /**
     * Disconnect from the database. This is called automatically by [Database::__destruct].
     * Clears the database instance from [Database::$instances].
     *
     * @return boolean
     */
    public function disconnect(): bool
    {
        unset(self::$instances[$this->_instance]);

        return true;
    }

    /**
     * Count the number of records in a table.
     *
     * @param mixed $table table name string or array(query, alias)
     * @return int
     */
    public function countRecords($table): int
    {
        // Quote the table name
        $table = $this->quoteTable($table);

        return $this->query(self::SELECT, "SELECT COUNT(*) AS total_row_count FROM $table")
            ->get('total_row_count');
    }

    /**
     * Returns a normalized array describing the SQL data type
     *
     * @param string $type SQL data type
     * @return array
     */
    public function datatype($type): array
    {
        static $types = [
            // SQL-92
            'bit' => ['type' => 'string', 'exact' => true],
            'bit varying' => ['type' => 'string'],
            'char' => ['type' => 'string', 'exact' => true],
            'char varying' => ['type' => 'string'],
            'character' => ['type' => 'string', 'exact' => true],
            'character varying' => ['type' => 'string'],
            'date' => ['type' => 'string'],
            'dec' => ['type' => 'float', 'exact' => true],
            'decimal' => ['type' => 'float', 'exact' => true],
            'double precision' => ['type' => 'float'],
            'float' => ['type' => 'float'],
            'int' => ['type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'],
            'integer' => ['type' => 'int', 'min' => '-2147483648', 'max' => '2147483647'],
            'interval' => ['type' => 'string'],
            'national char' => ['type' => 'string', 'exact' => true],
            'national char varying' => ['type' => 'string'],
            'national character' => ['type' => 'string', 'exact' => true],
            'national character varying' => ['type' => 'string'],
            'nchar' => ['type' => 'string', 'exact' => true],
            'nchar varying' => ['type' => 'string'],
            'numeric' => ['type' => 'float', 'exact' => true],
            'real' => ['type' => 'float'],
            'smallint' => ['type' => 'int', 'min' => '-32768', 'max' => '32767'],
            'time' => ['type' => 'string'],
            'time with time zone' => ['type' => 'string'],
            'timestamp' => ['type' => 'string'],
            'timestamp with time zone' => ['type' => 'string'],
            'varchar' => ['type' => 'string'],

            // SQL:1999
            'binary large object' => ['type' => 'string', 'binary' => true],
            'blob' => ['type' => 'string', 'binary' => true],
            'boolean' => ['type' => 'bool'],
            'char large object' => ['type' => 'string'],
            'character large object' => ['type' => 'string'],
            'clob' => ['type' => 'string'],
            'national character large object' => ['type' => 'string'],
            'nchar large object' => ['type' => 'string'],
            'nclob' => ['type' => 'string'],
            'time without time zone' => ['type' => 'string'],
            'timestamp without time zone' => ['type' => 'string'],

            // SQL:2003
            'bigint' => ['type' => 'int', 'min' => '-9223372036854775808', 'max' => '9223372036854775807'],

            // SQL:2008
            'binary' => ['type' => 'string', 'binary' => true, 'exact' => true],
            'binary varying' => ['type' => 'string', 'binary' => true],
            'varbinary' => ['type' => 'string', 'binary' => true],
        ];

        return $types[$type] ?? [];
    }

    /**
     * List all of the tables in the database. Optionally, a LIKE string can
     * be used to search for specific tables.
     *
     * @param string $like table to search for
     * @return array
     */
    abstract public function listTables($like = null): array;

    /**
     * Lists all of the columns in a table. Optionally, a LIKE string can be
     * used to search for specific fields.
     *
     * @param string $table table to get columns from
     * @param string $like column to search for
     * @param boolean $addPrefix whether to add the table prefix automatically or not
     * @return array
     */
    abstract public function listColumns($table, $like = null, $addPrefix = true): array;

    /**
     * Extracts the text between parentheses, if any.
     *
     * @param string $type
     * @return array list containing the type and length, if any
     */
    protected function parseType($type): array
    {
        if (($open = strpos($type, '(')) === false) {
            // No length specified
            return [$type, null];
        }

        // Closing parenthesis
        $close = strrpos($type, ')', $open);

        // Length without parentheses
        $length = substr($type, $open + 1, $close - 1 - $open);

        // Type without the length
        $type = substr($type, 0, $open) . substr($type, $close + 1);

        return [$type, $length];
    }

    /**
     * Return the table prefix defined in the current configuration.
     *
     * @return string
     */
    public function tablePrefix(): string
    {
        return $this->_config['tablePrefix'];
    }

    /**
     * Returns current encoding
     * @return string
     */
    public function encoding(): string
    {
        return $this->_config['charset'];
    }

    /**
     * Returns config value
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    public function configValue($key, $default = null)
    {
        return $this->_config[$key] ?? $default;
    }

    /**
     * Quote a value for an SQL query.
     *
     * @param mixed $value any value to quote
     * @return string
     * @uses Database::escape
     */
    public function quote($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($value === true) {
            return "'1'";
        }

        if ($value === false) {
            return "'0'";
        }

        if (is_object($value)) {
            if ($value instanceof Database\Query) {
                // Create a sub-query
                return '(' . $value->compile($this) . ')';
            }

            if ($value instanceof Database\Expression) {
                // Compile the expression
                return $value->compile($this);
            }
            // Convert the object to a string
            return $this->quote((string)$value);
        }

        if (is_array($value)) {
            return '(' . implode(', ', array_map([$this, __FUNCTION__], $value)) . ')';
        }

        if (is_int($value)) {
            return (int)$value;
        }

        if (is_float($value)) {
            // Convert to non-locale aware float to prevent possible commas
            return sprintf('%F', $value);
        }

        return $this->escape($value);
    }

    /**
     * Quote a database column name and add the table prefix if needed.
     *
     * Objects passed to this function will be converted to strings.
     * {Database\Expression} objects will be compiled.
     * {Database\Query} objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $column column name or array(column, alias)
     * @return string
     * @uses Database::quoteIdentifier
     * @uses Database::tablePrefix
     */
    public function quoteColumn($column): string
    {
        // Identifiers are escaped by repeating them
        $escaped_identifier = $this->identifier . $this->identifier;

        if (is_array($column)) {
            list($column, $alias) = $column;
            $alias = str_replace($this->identifier, $escaped_identifier, $alias);
        }

        if ($column instanceof Database\Query) {
            // Create a sub-query
            $column = '(' . $column->compile($this) . ')';
        } elseif ($column instanceof Database\Expression) {
            // Compile the expression
            $column = $column->compile($this);
        } else {
            // Convert to a string
            $column = (string)$column;

            $column = str_replace($this->identifier, $escaped_identifier, $column);

            if ($column === '*') {
                return $column;
            }

            if (strpos($column, '.') !== false) {
                $parts = explode('.', $column);
                $prefix = $this->tablePrefix();

                if ($prefix) {
                    // Get the offset of the table name, 2nd-to-last part
                    $offset = count($parts) - 2;

                    // Add the table prefix to the table name
                    $parts[$offset] = $prefix . $parts[$offset];
                }

                foreach ($parts as $key => $part) {
                    if ($part !== '*') {
                        // Quote each of the parts
                        $parts[$key] = $this->identifier . $part . $this->identifier;
                    }
                }

                $column = implode('.', $parts);
            } else {
                $column = $this->identifier . $column . $this->identifier;
            }
        }

        if (isset($alias)) {
            $column .= ' AS ' . $this->identifier . $alias . $this->identifier;
        }

        return $column;
    }

    /**
     * Quote a database table name and adds the table prefix if needed.
     *
     * Objects passed to this function will be converted to strings.
     * {Database\Expression} objects will be compiled.
     * {Database\Query} objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $table table name or array(table, alias)
     * @return string
     * @uses Database::quoteIdentifier
     * @uses Database::tablePrefix
     */
    public function quoteTable($table): string
    {
        // Identifiers are escaped by repeating them
        $escaped_identifier = $this->identifier . $this->identifier;

        if (is_array($table)) {
            list($table, $alias) = $table;
            $alias = str_replace($this->identifier, $escaped_identifier, $alias);
        }

        if ($table instanceof Database\Query) {
            // Create a sub-query
            $table = '(' . $table->compile($this) . ')';
        } elseif ($table instanceof Database\Expression) {
            // Compile the expression
            $table = $table->compile($this);
        } else {
            // Convert to a string
            $table = (string)$table;

            $table = str_replace($this->identifier, $escaped_identifier, $table);

            if (strpos($table, '.') !== false) {
                $parts = explode('.', $table);
                $prefix = $this->tablePrefix();

                if ($prefix) {
                    // Get the offset of the table name, last part
                    $offset = count($parts) - 1;

                    // Add the table prefix to the table name
                    $parts[$offset] = $prefix . $parts[$offset];
                }

                foreach ($parts as $key => $part) {
                    // Quote each of the parts
                    $parts[$key] = $this->identifier . $part . $this->identifier;
                }

                $table = implode('.', $parts);
            } else {
                // Add the table prefix
                $table = $this->identifier . $this->tablePrefix() . $table . $this->identifier;
            }
        }

        if (isset($alias)) {
            // Attach table prefix to alias
            $table .= ' AS ' . $this->identifier . $this->tablePrefix() . $alias . $this->identifier;
        }

        return $table;
    }

    /**
     * Quote a database identifier
     *
     * Objects passed to this function will be converted to strings.
     * {Database\Expression} objects will be compiled.
     * {Database\Query} objects will be compiled and converted to a sub-query.
     * All other objects will be converted using the `__toString` method.
     *
     * @param mixed $value any identifier
     * @return string
     */
    public function quoteIdentifier($value): string
    {
        // Identifiers are escaped by repeating them
        $escaped_identifier = $this->identifier . $this->identifier;

        if (is_array($value)) {
            list($value, $alias) = $value;
            $alias = str_replace($this->identifier, $escaped_identifier, $alias);
        }

        if ($value instanceof Database\Query) {
            // Create a sub-query
            $value = '(' . $value->compile($this) . ')';
        } elseif ($value instanceof Database\Expression) {
            // Compile the expression
            $value = $value->compile($this);
        } else {
            // Convert to a string
            $value = (string)$value;

            $value = str_replace($this->identifier, $escaped_identifier, $value);

            if (strpos($value, '.') !== false) {
                $parts = explode('.', $value);

                foreach ($parts as $key => $part) {
                    // Quote each of the parts
                    $parts[$key] = $this->identifier . $part . $this->identifier;
                }

                $value = implode('.', $parts);
            } else {
                $value = $this->identifier . $value . $this->identifier;
            }
        }

        if (isset($alias)) {
            $value .= ' AS ' . $this->identifier . $alias . $this->identifier;
        }

        return $value;
    }

    /**
     * Disconnect from the database when the object is destroyed.
     * [!!] Calling `unset($db)` is not enough to destroy the database, as it
     * will still be stored in `Database::$instances`.
     *
     * @example unset(Database::instances[(string) $db], $db); // Destroy the database instance
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Returns the database instance name.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->_instance;
    }
}
