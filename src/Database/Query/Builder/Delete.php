<?php

namespace Garden\Db\Database\Query\Builder;

use Garden\Db\Database;

/**
 * Database query builder for DELETE statements
 */
class Delete extends Where {

    // DELETE FROM ...
    protected $_table;

    /**
     * Set the table for a delete.
     *
     * @param mixed $table table name or array($table, $alias) or object
     * @return void
     */
    public function __construct($table = null)
    {
        if ($table) {
            // Set the inital table name
            $this->_table = $table;
        }

        // Start the query with no SQL
        parent::__construct(Database::DELETE, '');
    }

    /**
     * Sets the table to delete from.
     *
     * @param mixed $table table name or array($table, $alias) or object
     * @return $this
     */
    public function table($table)
    {
        $this->_table = $table;

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

        // Start a deletion query
        $query = 'DELETE FROM ' . $db->quoteTable($this->_table);

        if (!empty($this->_where)) {
            // Add deletion conditions
            $query .= ' WHERE ' . $this->_compile_conditions($db, $this->_where);
        }

        if (!empty($this->_orderBy)) {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by($db, $this->_orderBy);
        }

        if ($this->_limit !== null) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        $this->_sql = $query;

        return parent::compile($db);
    }

    public function reset()
    {
        $this->_table = $this->_sql = null;
        $this->_where = $this->_parameters = [];

        return $this;
    }

}