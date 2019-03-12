<?php

namespace Garden\Db\Database\Query;

use Garden\Db\Database;

/**
 * Database query builder
 */
abstract class Builder extends Database\Query
{
    /**
     * Reset the current builder status.
     *
     * @return $this
     */
    abstract public function reset();

    /**
     * Compiles an array of JOIN statements into an SQL partial.
     *
     * @param object $db Database instance
     * @param array $joins join statements
     * @return string
     */
    protected function _compile_join(Database $db, array $joins)
    {
        $statements = [];

        foreach ($joins as $join) {
            // Compile each of the join statements
            $statements[] = $join->compile($db);
        }

        return implode(' ', $statements);
    }

    /**
     * Compiles an array of conditions into an SQL partial. Used for WHERE
     * and HAVING.
     *
     * @param object $db Database instance
     * @param array $conditions condition statements
     * @return string
     */
    protected function _compile_conditions(Database $db, array $conditions)
    {
        $last_condition = null;

        $sql = '';
        foreach ($conditions as $group) {
            // Process groups of conditions
            foreach ($group as $logic => $condition) {
                if ($condition === '(') {
                    if (!empty($sql) && $last_condition !== '(') {
                        // Include logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    $sql .= ')';
                } else {
                    if (!empty($sql) && $last_condition !== '(') {
                        // Add the logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    // Split the condition
                    list($column, $op, $value) = $condition;

                    if ($value === null) {
                        if ($op === '=') {
                            // Convert "val = null" to "val IS null"
                            $op = 'IS';
                        } elseif ($op === '!=' || $op === '<>') {
                            // Convert "val != null" to "valu IS NOT null"
                            $op = 'IS NOT';
                        }
                    }

                    // Database operators are always uppercase
                    $op = strtoupper($op);

                    if ($op === 'BETWEEN' && is_array($value)) {
                        // BETWEEN always has exactly two arguments
                        list($min, $max) = $value;

                        if ((is_string($min) && array_key_exists($min, $this->_parameters)) === false) {
                            // Quote the value, it is not a parameter
                            $min = $db->quote($min);
                        }

                        if ((is_string($max) && array_key_exists($max, $this->_parameters)) === false) {
                            // Quote the value, it is not a parameter
                            $max = $db->quote($max);
                        }

                        // Quote the min and max value
                        $value = $min . ' AND ' . $max;
                    } elseif ((is_string($value) && array_key_exists($value, $this->_parameters)) === false) {
                        // Quote the value, it is not a parameter
                        $value = $db->quote($value);
                    }

                    if ($column) {
                        if (is_array($column)) {
                            if ($op === 'BETWEEN DATE') {
                                $op = 'BETWEEN';
                                $values = [];
                                foreach ($column as $col) {
                                    $values[] = "STR_TO_DATE({$db->quoteColumn($col)}, '%Y-%m-%d %H:%i:%s')";
                                }
                                $column = "STR_TO_DATE($value, '%Y-%m-%d %H:%i:%s')";
                                $value = implode(' AND ', $values);
                            } else {
                                // Use the column name
                                $column = $db->quoteIdentifier(reset($column));
                            }
                        } else {
                            // Apply proper quoting to the column
                            $column = $db->quoteColumn($column);
                        }
                    }

                    // Append the statement to the query
                    $sql .= trim($column . ' ' . $op . ' ' . $value);
                }

                $last_condition = $condition;
            }
        }

        return $sql;
    }

    /**
     * Compiles an array of set values into an SQL partial. Used for UPDATE.
     *
     * @param object $db Database instance
     * @param array $values updated values
     * @return string
     */
    protected function _compile_set(Database $db, array $values)
    {
        $set = [];
        foreach ($values as list ($column, $value)) {
            // Quote the column name
            $column = $db->quoteColumn($column);

            if ((is_string($value) && array_key_exists($value, $this->_parameters)) === false) {
                // Quote the value, it is not a parameter
                $value = $db->quote($value);
            }

            $set[$column] = $column . ' = ' . $value;
        }

        return implode(', ', $set);
    }

    /**
     * Compiles an array of GROUP BY columns into an SQL partial.
     *
     * @param object $db Database instance
     * @param array $columns
     * @return string
     */
    protected function _compile_group_by(Database $db, array $columns)
    {
        $group = [];

        foreach ($columns as $column) {
            if (is_array($column)) {
                // Use the column alias
                $column = $db->quoteIdentifier(end($column));
            } else {
                // Apply proper quoting to the column
                $column = $db->quoteColumn($column);
            }

            $group[] = $column;
        }

        return 'GROUP BY ' . implode(', ', $group);
    }

    /**
     * Compiles an array of ORDER BY statements into an SQL partial.
     *
     * @param object $db Database instance
     * @param array $columns sorting columns
     * @return string
     */
    protected function _compile_order_by(Database $db, array $columns)
    {
        $sort = [];
        foreach ($columns as list ($column, $direction)) {
            if (is_array($column)) {
                // Use the column alias
                $column = $db->quoteIdentifier(end($column));
            } else {
                // Apply proper quoting to the column
                $column = $db->quoteColumn($column);
            }

            if ($direction) {
                // Make the direction uppercase
                $direction = ' ' . strtoupper($direction);
            }

            $sort[] = $column . $direction;
        }

        return 'ORDER BY ' . implode(', ', $sort);
    }
}