<?php

namespace Garden\Db\Database\Query\Builder;

use Garden\Db\Database;

/**
 * Database query builder for WHERE statements
 */
abstract class Where extends Database\Query\Builder
{

    // WHERE ...
    protected $_where = [];

    // ORDER BY ...
    protected $_orderBy = [];

    // LIMIT ...
    protected $_limit;

    /**
     * Alias of and_where()
     * @param mixed $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed $value column value
     * @return $this
     */
    public function where($column, $op, $value)
    {
        return $this->and_where($column, $op, $value);
    }

    /**
     * Creates a new "AND WHERE" condition for the query.
     * @param mixed $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed $value column value
     * @return $this
     */
    public function and_where($column, $op, $value)
    {
        $this->_where[] = ['AND' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Creates a new "OR WHERE" condition for the query.
     * @param mixed $column column name or array($column, $alias) or object
     * @param string $op logic operator
     * @param mixed $value column value
     * @return $this
     */
    public function or_where($column, $op, $value)
    {
        $this->_where[] = ['OR' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Alias of and_where_open()
     * @return $this
     */
    public function where_open()
    {
        return $this->and_where_open();
    }

    /**
     * Opens a new "AND WHERE (...)" grouping.
     * @return $this
     */
    public function and_where_open()
    {
        $this->_where[] = ['AND' => '('];

        return $this;
    }

    /**
     * Opens a new "OR WHERE (...)" grouping.
     * @return $this
     */
    public function or_where_open()
    {
        $this->_where[] = ['OR' => '('];

        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     * @return $this
     */
    public function where_close()
    {
        return $this->and_where_close();
    }

    /**
     * Closes an open "WHERE (...)" grouping or removes the grouping when it is
     * empty.
     * @return $this
     */
    public function where_close_empty()
    {
        $group = end($this->_where);

        if ($group && reset($group) === '(') {
            array_pop($this->_where);

            return $this;
        }

        return $this->where_close();
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     * @return $this
     */
    public function and_where_close()
    {
        $this->_where[] = ['AND' => ')'];

        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return $this
     */
    public function or_where_close()
    {
        $this->_where[] = ['OR' => ')'];

        return $this;
    }

    /**
     * Applies sorting with "ORDER BY ..."
     * @param mixed $column column name or array($column, $alias) or object
     * @param string $direction direction of sorting
     * @return $this
     */
    public function order_by($column, $direction = null)
    {
        $this->_orderBy[] = [$column, $direction];

        return $this;
    }

    /**
     * Return up to "LIMIT ..." results
     *
     * @param integer $number maximum results to return or null to reset
     * @return $this
     */
    public function limit($number)
    {
        $this->_limit = ($number === null) ? null : (int)$number;

        return $this;
    }

    /**
     * Alias of and_between()
     * @param array $columns
     * @param string $date
     * @return $this
     */
    public function between_date($columns, $date)
    {
        return $this->and_between_date($columns, $date);
    }

    /**
     * Creates a new "and between date" condition for the query.
     * @param array $columns
     * @param string $date
     * @return $this
     */
    public function and_between_date($columns, $date)
    {
        $this->_where[] = ['AND' => [$columns, 'BETWEEN DATE', $date]];

        return $this;
    }

    /**
     * Creates a new "or between date" condition for the query.
     * @param array $columns
     * @param string $date
     * @return $this
     */
    public function or_between_date($columns, $date)
    {
        $this->_where[] = ['OR' => [$columns, 'BETWEEN DATE', $date]];

        return $this;
    }

}