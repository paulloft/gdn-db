<?php

namespace Garden\Db\Driver;

use Garden\Db\Exceptions as Exception;
use Garden\Db\Database;

/**
 * MySQLi database connection.
 */
class MySQLi extends SQL
{

    // Database in use by each connection
    protected static $currentDatabases = [];

    // Use SET NAMES to set the character set
    protected static $setNames;

    // Identifier for this connection within the PHP driver
    protected $connectionID;

    // MySQL uses a backtick for identifiers
    protected $identifier = '`';

    /**
     * Raw server connection
     * @var \mysqli
     */
    protected $_connection;

    public function connect()
    {
        if ($this->_connection) {
            return;
        }

        if (self::$setNames === null) {
            // Determine if we can use mysqli_set_charset(), which is only
            // available on PHP 5.2.3+ when compiled against MySQL 5.0+
            self::$setNames = !function_exists('mysqli_set_charset');
        }

        // Extract the connection parameters, adding required variabels
        $hostname = $this->_config['host'] ?? 'localhost';
        $database = $this->_config['database'] ?? null;
        $username = $this->_config['username'] ?? null;
        $password = $this->_config['password'] ?? null;
        $socket = $this->_config['socket'] ?? null;
        $port = $this->_config['port'] ?? 3306;
        $ssl = $this->_config['ssl'] ?? null;

        try {
            if (is_array($ssl)) {
                $this->_connection = mysqli_init();
                $this->_connection->ssl_set(
                    $ssl['client_key_path'] ?? null,
                    $ssl['client_cert_path'] ?? null,
                    $ssl['ca_cert_path'] ?? null,
                    $ssl['ca_dir_path'] ?? null,
                    $ssl['cipher'] ?? null
                );
                $this->_connection->real_connect($hostname, $username, $password, $database, $port, $socket, MYSQLI_CLIENT_SSL);
            } else {
                $this->_connection = new \mysqli($hostname, $username, $password, $database, $port, $socket);
            }
        } catch (\Exception $e) {
            // No connection exists
            $this->_connection = null;

            throw new Exception\Database($e->getMessage(), $e->getCode());
        }

        // \xFF is a better delimiter, but the PHP driver uses underscore
        $this->connectionID = sha1($hostname . '_' . $username . '_' . $password);

        if (!empty($this->_config['charset'])) {
            // Set the character set
            $this->setCharset($this->_config['charset']);
        }

        if (!empty($this->_config['variables'])) {
            // Set session variables
            $variables = [];

            foreach ($this->_config['variables'] as $var => $val) {
                $variables[] = 'SESSION ' . $var . ' = ' . $this->quote($val);
            }

            $this->_connection->query('SET ' . implode(', ', $variables));
        }
    }

    public function disconnect(): bool
    {
        try {
            // Database is assumed disconnected
            $status = true;

            if (is_resource($this->_connection)) {
                $status = $this->_connection->close();
                if ($status) {
                    // Clear the connection
                    $this->_connection = null;

                    // Clear the instance
                    parent::disconnect();
                }
            }
        } catch (\Exception $e) {
            // Database is probably not disconnected
            $status = !is_resource($this->_connection);
        }

        return $status;
    }

    /**
     * @param string $charset
     * @throws Exception\Error
     * @throws Exception\Database
     */
    public function setCharset($charset)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if (self::$setNames === true) {
            // PHP is compiled against MySQL 4.x
            $status = (bool)$this->_connection->query('SET NAMES ' . $this->quote($charset));
        } else {
            // PHP is compiled against MySQL 5.x
            $status = $this->_connection->set_charset($charset);
        }

        if ($status === false) {
            throw new Exception\Error($this->_connection->error, $this->_connection->errno);
        }
    }

    public function query($type, $sql, $asObject = false, array $params = null)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        // Execute the query
        if (($result = $this->_connection->query($sql)) === false) {
            throw new Exception\SyntaxError("{$this->_connection->error} [$sql]", $this->_connection->errno);
        }

        // Set the last query
        $this->lastQuery = $sql;

        if ($type === Database::SELECT) {
            // Return an iterator of results
            return new MySQLi\Result($result, $sql, $asObject, $params);
        }

        if ($type === Database::INSERT) {
            // Return a list of insert id and rows created
            return [
                $this->_connection->insert_id,
                $this->_connection->affected_rows,
            ];
        }

        // Return the number of rows affected
        return $this->_connection->affected_rows;
    }

    /**
     * Start a SQL transaction
     *
     * @link http://dev.mysql.com/doc/refman/5.0/en/set-transaction.html
     *
     * @param string $mode Isolation level
     * @return boolean
     */
    public function begin($mode = null): bool
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if ($mode && !$this->_connection->query("SET TRANSACTION ISOLATION LEVEL $mode")) {
            throw new Exception\Database($this->_connection->error, $this->_connection->errno);
        }

        return (bool)$this->_connection->query('START TRANSACTION');
    }

    /**
     * Commit a SQL transaction
     *
     * @return boolean
     */
    public function commit(): bool
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query('COMMIT');
    }

    /**
     * Rollback a SQL transaction
     *
     * @return boolean
     */
    public function rollback(): bool
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query('ROLLBACK');
    }

    public function escape($value): string
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if (($value = $this->_connection->real_escape_string((string)$value)) === false) {
            throw new Exception\Database($this->_connection->error, $this->_connection->errno);
        }

        // SQL standard is to use single-quotes for all values
        return "'$value'";
    }

} // End MySQLi
