<?php

namespace Garden\Db\Database\Query\Builder;

use Garden\Db\Database;
use Garden\Db\Exceptions as Exception;

/**
 * Database query builder for INSERT statements
 */
class Insert extends Database\Query\Builder {

    // INSERT INTO ...
    protected $_table;

    // (...)
    protected $_columns = [];

    // VALUES (...)
    protected $_values = [];

    /**
     * Set the table and columns for an insert.
     *
     * @param mixed $table table name or array($table, $alias) or object
     * @param array $columns column names
     * @return void
     */
    public function __construct($table = null, array $columns = null)
    {
        if ($table) {
            // Set the inital table name
            $this->table($table);
        }

        if ($columns) {
            // Set the column names
            $this->_columns = $columns;
        }

        // Start the query with no SQL
        parent::__construct(Database::INSERT, '');
    }

    /**
     * Sets the table to insert into.
     *
     * @param string $table table name
     * @throws Exception\SyntaxError
     * @return $this
     */
    public function table($table)
    {
        if (!is_string($table)) {
            throw new Exception\SyntaxError('INSERT INTO syntax does not allow table aliasing');
        }

        $this->_table = $table;

        return $this;
    }

    /**
     * Set the columns that will be inserted.
     *
     * @param array $columns column names
     * @return $this
     */
    public function columns(array $columns)
    {
        $this->_columns = $columns;

        return $this;
    }

    /**
     * Adds or overwrites values. Multiple value sets can be added.
     * @param array ...$values values list
     * @return $this
     * @throws Exception\SyntaxError
     */
    public function values(...$values)
    {
        if (!is_array($this->_values)) {
            throw new Exception\SyntaxError('INSERT INTO ... SELECT statements cannot be combined with INSERT INTO ... VALUES');
        }

        foreach ($values as $value) {
            $this->_values[] = $value;
        }

        return $this;
    }

    /**
     * Use a sub-query to for the inserted values.
     *
     * @param object $query Database_Query of SELECT type
     * @throws Exception\SyntaxError
     * @return $this
     */
    public function select(Database\Query $query)
    {
        if ($query->type() !== Database::SELECT) {
            throw new Exception\SyntaxError('Only SELECT queries can be combined with INSERT queries');
        }

        $this->_values = $query;

        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param mixed $db Database instance or name of instance
     * @return string
     */
    public function compile($db = null)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = Database::instance($db);
        }

        // Start an insertion query
        $query = 'INSERT INTO ' . $db->quoteTable($this->_table);

        // Add the column names
        $query .= ' (' . implode(', ', array_map([$db, 'quoteColumn'], $this->_columns)) . ') ';

        if (is_array($this->_values)) {
            $groups = [];
            foreach ($this->_values as $group) {
                foreach ($group as $offset => $value) {
                    if ((is_string($value) && array_key_exists($value, $this->_parameters)) === false) {
                        // Quote the value, it is not a parameter
                        $group[$offset] = $db->quote($value);
                    }
                }

                $groups[] = '(' . implode(', ', $group) . ')';
            }

            // Add the values
            $query .= 'VALUES ' . implode(', ', $groups);
        } else {
            // Add the sub-query
            $query .= $this->_values;
        }

        $this->_sql = $query;

        return parent::compile($db);
    }

    public function reset()
    {
        $this->_table = null;

        $this->_columns =
        $this->_values = [];

        $this->_parameters = [];

        $this->_sql = null;

        return $this;
    }

}