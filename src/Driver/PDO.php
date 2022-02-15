<?php

namespace Garden\Db\Driver;

use Garden\Db\Exceptions as Exception;
use Garden\Db\Database;

/**
 * PDO database connection.
 */
class PDO extends SQL {

    // PDO uses no quoting for identifiers
    protected $identifier = '`';

    /**
     * @var \PDO
     */
    protected $_connection;

    public function __construct($name, array $config)
    {
        parent::__construct($name, $config);

        if (isset($this->_config['identifier'])) {
            // Allow the identifier to be overloaded per-connection
            $this->identifier = (string)$this->_config['identifier'];
        }
    }

    /**
     * @throws Exception\Database
     */
    public function connect()
    {
        if ($this->_connection) {
            return;
        }

        // Extract the connection parameters, adding required variabels
        $host = $this->_config['host'] ?? 'localhost';
        $type = $this->_config['type'] ?? 'mysql';
        $dsn = $this->_config['dsn'] ?? null;
        $database = $this->_config['database'] ?? null;
        $username = $this->_config['username'] ?? null;
        $password = $this->_config['password'] ?? null;
        $persistent = $this->_config['persistent'] ?? null;
        $options = $this->_config['options'] ?? [];

        if (!$dsn) {
            $dsn = "$type:host=$host;dbname=$database";
        }

        // Force PDO to use exceptions for all errors
        $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;

        if (!empty($persistent)) {
            // Make the connection persistent
            $options[\PDO::ATTR_PERSISTENT] = true;
        }

        try {
            // Create a new PDO connection
            $this->_connection = new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $e) {
            throw new Exception\Database($e->getMessage(), $e->getCode());
        }

        if (!empty($this->_config['charset'])) {
            // Set the character set
            $this->setCharset($this->_config['charset']);
        }
    }

    public function disconnect(): bool
    {
        // Destroy the PDO object
        $this->_connection = null;

        return parent::disconnect();
    }

    public function setCharset($charset)
    {
        // Make sure the database is connected
        $this->_connection OR $this->connect();

        // This SQL-92 syntax is not supported by all drivers
        $this->_connection->exec('SET NAMES ' . $this->quote($charset));
    }

    /**
     * @param int $type
     * @param string $sql
     * @param mixed $asObject
     * @param array|null $params
     * @return array|Database\Result\Cached|object|int
     * @throws Exception\Database
     */
    public function query($type, $sql, $asObject = false, array $params = null)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        try {
            $result = $this->_connection->query($sql);
        } catch (\Exception $e) {
            // Convert the exception in a database exception
            throw new Exception\SyntaxError("{$e->getMessage()} \n[$sql ]");
        }

        // Set the last query
        $this->lastQuery = $sql;

        if ($type === Database::SELECT) {
            // Convert the result into an array, as PDOStatement::rowCount is not reliable
            if ($asObject === false) {
                $result->setFetchMode(\PDO::FETCH_ASSOC);
            } elseif (is_string($asObject)) {
                $result->setFetchMode(\PDO::FETCH_CLASS, $asObject, $params);
            } else {
                $result->setFetchMode(\PDO::FETCH_CLASS, 'stdClass');
            }

            $result = $result->fetchAll();

            // Return an iterator of results
            return new Database\Result\Cached($result, $sql, $asObject);
        }

        if ($type === Database::INSERT) {
            // Return a list of insert id and rows created
            return [
                $this->_connection->lastInsertId(),
                $result->rowCount()
            ];
        }

        // Return the number of rows affected
        return $result->rowCount();
    }

    /**
     * @param null $mode
     * @return bool
     * @throws Exception\Database
     */
    public function begin($mode = null): bool
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return $this->_connection->beginTransaction();
    }

    public function commit(): bool
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return $this->_connection->commit();
    }

    public function rollback(): bool
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return $this->_connection->rollBack();
    }

    /**
     * @param string $value
     * @return string
     * @throws Exception\Database
     */
    public function escape($value): string
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        return $this->_connection->quote($value);
    }

}
