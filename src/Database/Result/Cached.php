<?php

namespace Garden\Db\Database\Result;

use Garden\Db\Database;

/**
 * Object used for caching the results of select queries
 */
class Cached extends Database\Result
{

    public function __construct(array $result, $sql, $asObject = null, $params = null)
    {
        parent::__construct($result, $sql, $asObject, $params);

        // Find the number of rows in the result
        $this->_totalRows = count($result);
    }

    public function __destruct()
    {
        // Cached results do not use resources
    }

    public function cached()
    {
        return $this;
    }

    public function seek($offset)
    {
        if ($this->offsetExists($offset)) {
            $this->_currentRow = $offset;

            return true;
        }

        return false;
    }

    public function current()
    {
        // Return an array of the row
        return $this->valid() ? $this->_result[$this->_currentRow] : null;
    }

}