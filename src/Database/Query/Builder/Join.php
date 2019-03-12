<?php

namespace Garden\Db\Database\Query\Builder;

use Garden\Db\Database;
use Garden\Db\Exceptions as Exception;

/**
 * Database query builder for JOIN statements
 */
class Join extends Database\Query\Builder {

    // Type of JOIN
    protected $_type;

    // JOIN ...
    protected $_table;

    // ON ...
    protected $_on = [];

    // USING ...
    protected $_using = [];

    /**
     * Creates a new JOIN statement for a table. Optionally, the type of JOIN
     * can be specified as the second parameter.
     *
     * @param mixed $table column name or array($column, $alias) or object
     * @param string $alias table alias
     * @param string $type type of JOIN: INNER, RIGHT, LEFT, etc
     * @return void
     */
    public function __construct($table, $alias = null, $type = null)
    {
        // Set the table to JOIN on
        $this->_table = $alias ? [$table, $alias] : $table;

        if ($type !== null) {
            // Set the JOIN type
            $this->_type = (string)$type;
        }
    }

    /**
     * Adds a new condition for joining.
     *
     * @param mixed $c1 column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed $c2 column name or array($column, $alias) or object
     * @throws Exception\SyntaxError
     * @return $this
     */
    public function on($c1, $op, $c2)
    {
        if (!empty($this->_using)) {
            throw new Exception\SyntaxError('JOIN ... ON ... cannot be combined with JOIN ... USING ...');
        }

        $this->_on[] = [$c1, $op, $c2];

        return $this;
    }

    /**
     * Adds a new condition for joining.
     *
     * @param string $columns column name
     * @throws Exception\SyntaxError
     * @return $this
     */
    public function using(...$columns)
    {
        if (!empty($this->_on)) {
            throw new Exception\SyntaxError('JOIN ... ON ... cannot be combined with JOIN ... USING ...');
        }

        $this->_using = array_merge($this->_using, $columns);

        return $this;
    }

    /**
     * Compile the SQL partial for a JOIN statement and return it.
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

        if ($this->_type) {
            $sql = strtoupper($this->_type) . ' JOIN';
        } else {
            $sql = 'JOIN';
        }

        // Quote the table name that is being joined
        $sql .= ' ' . $db->quoteTable($this->_table);

        if (!empty($this->_using)) {
            // Quote and concat the columns
            $sql .= ' USING (' . implode(', ', array_map([$db, 'quoteColumn'], $this->_using)) . ')';
        } else {
            $conditions = [];
            foreach ($this->_on as list($c1, $op, $c2)) {
                if ($op) {
                    // Make the operator uppercase and spaced
                    $op = ' ' . strtoupper($op);
                }

                // Quote each of the columns used for the condition
                $conditions[] = $db->quoteColumn($c1) . $op . ' ' . $db->quoteColumn($c2);
            }

            // Concat the conditions "... AND ..."
            $sql .= ' ON (' . implode(' AND ', $conditions) . ')';
        }

        return $sql;
    }

    public function reset()
    {
        $this->_type =
        $this->_table = null;

        $this->_on = [];
    }

}