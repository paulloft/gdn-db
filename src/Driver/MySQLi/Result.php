<?php

namespace Garden\Db\Driver\MySQLi;
/**
 * MySQLi database result.
 */
class Result extends \Garden\Db\Database\Result
{

    protected $internalRow = 0;

    public function __construct($result, $sql, $asObject = false, array $params = null)
    {
        parent::__construct($result, $sql, $asObject, $params);

        // Find the number of rows in the result
        $this->_totalRows = $result->num_rows;
    }

    public function __destruct()
    {
        if (is_resource($this->_result)) {
            $this->_result->free();
        }
    }

    public function seek($offset)
    {
        if ($this->offsetExists($offset) && $this->_result->data_seek($offset)) {
            // Set the current row to the offset
            $this->_currentRow = $this->internalRow = $offset;

            return true;
        }

        return FALSE;
    }

    public function current()
    {
        if ($this->_currentRow !== $this->internalRow && !$this->seek($this->_currentRow)) {
            return null;
        }

        // Increment internal row for optimization assuming rows are fetched in order
        $this->internalRow++;

        if ($this->_asObject === true) {
            // Return an stdClass
            return $this->_result->fetch_object();
        }

        if (is_string($this->_asObject)) {
            // Return an object of given class name
            return $this->_result->fetch_object($this->_asObject, (array)$this->_objectParams);
        }
        // Return an array of the row
        return $this->_result->fetch_assoc();
    }

}
