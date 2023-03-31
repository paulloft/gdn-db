<?php

namespace Garden\Db\Database;

use Garden\Db\Exceptions\Error;

/**
 * Database result wrapper
 */
abstract class Result implements \Countable, \Iterator, \SeekableIterator, \ArrayAccess
{
    // Executed SQL for this result
    protected $_query;

    // Raw result resource
    protected $_result;

    // Total number of rows and current row
    protected $_totalRows = 0;
    protected $_currentRow = 0;

    // Return rows as an object or associative array
    protected $_asObject;

    // Parameters for __construct when using object results
    protected $_objectParams;

    /**
     * Sets the total number of rows and stores the result locally.
     * @param mixed $result query result
     * @param string $sql SQL query
     * @param mixed $asObject
     * @param array $params
     * @return void
     */
    public function __construct($result, $sql, $asObject = false, array $params = null)
    {
        // Store the result locally
        $this->_result = $result;

        // Store the SQL locally
        $this->_query = $sql;

        if (is_object($asObject)) {
            // Get the object class name
            $asObject = get_class($asObject);
        }

        // Results as objects or associative arrays
        $this->_asObject = $asObject;

        if ($params) {
            // Object constructor params
            $this->_objectParams = $params;
        }
    }

    /**
     * Result destruction cleans up all open result sets.
     * @return void
     */
    abstract public function __destruct();

    /**
     * Get a cached database result from the current result iterator.
     * @return Result\Cached
     */
    public function cached()
    {
        return new Result\Cached($this->as_array(), $this->_query, $this->_asObject);
    }

    /**
     * Return all of the rows in the result as an array.
     * @param string $key column for associative keys
     * @param string $value column for values
     * @return array
     */
    public function as_array($key = null, $value = null)
    {
        $results = [];

        if ($key === null && $value === null) {
            // Indexed rows

            foreach ($this as $row) {
                $results[] = $row;
            }
        } elseif ($key === null) {
            // Indexed columns

            if ($this->_asObject) {
                foreach ($this as $row) {
                    $results[] = $row->$value;
                }
            } else {
                foreach ($this as $row) {
                    $results[] = $row[$value];
                }
            }
        } elseif ($value === null) {
            // Associative rows

            if ($this->_asObject) {
                foreach ($this as $row) {
                    $results[$row->$key] = $row;
                }
            } else {
                foreach ($this as $row) {
                    $results[$row[$key]] = $row;
                }
            }
        } elseif ($this->_asObject) {
            foreach ($this as $row) {
                $results[$row->$key] = $row->$value;
            }
        } else {
            foreach ($this as $row) {
                $results[$row[$key]] = $row[$value];
            }
        }

        $this->rewind();

        return $results;
    }

    /**
     * Return the named column from the current row.
     * @param string $name column to get
     * @param mixed $default default value if the column does not exist
     * @return mixed
     */
    public function get($name, $default = null)
    {
        $row = $this->current();

        if ($this->_asObject) {
            if (isset($row->$name)) {
                return $row->$name;
            }
        } elseif (isset($row[$name])) {
            return $row[$name];
        }

        return $default;
    }

    /**
     * Implements [Countable::count], returns the total number of rows.
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->_totalRows;
    }

    /**
     * Implements [ArrayAccess::offsetExists], determines if row exists.
     * @param int $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return ($offset >= 0 AND $offset < $this->_totalRows);
    }

    /**
     * Implements [ArrayAccess::offsetGet], gets a given row.
     * @param int $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        try {
            $this->seek($offset);
        } catch (\OutOfBoundsException $e) {
            return null;
        }

        return $this->current();
    }

    /**
     * Implements [ArrayAccess::offsetSet], throws an error.
     * @param int $offset
     * @param mixed $value
     * @return void
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    final public function offsetSet($offset, $value)
    {
        throw new Error('Database results are read-only');
    }

    /**
     * Implements [ArrayAccess::offsetUnset], throws an error.
     * @param int $offset
     * @return void
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    final public function offsetUnset($offset)
    {
        throw new Error('Database results are read-only');
    }

    /**
     * Implements [Iterator::key], returns the current row number.
     * @return int
     */
    public function key(): self
    {
        return $this->_currentRow;
    }

    /**
     * Implements [Iterator::next], moves to the next row.
     * @return $this
     */
    public function next(): void
    {
        ++$this->_currentRow;
    }

    /**
     * Implements [Iterator::prev], moves to the previous row.
     * @return $this
     */
    public function prev(): void
    {
        --$this->_currentRow;
    }

    /**
     * Implements [Iterator::rewind], sets the current row to zero.
     * @return $this
     */
    public function rewind(): void
    {
        $this->_currentRow = 0;
    }

    /**
     * Implements [Iterator::valid], checks if the current row exists.
     * @return bool
     */
    public function valid(): bool
    {
        return $this->offsetExists($this->_currentRow);
    }

}
