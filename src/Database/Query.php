<?php

namespace Garden\Db\Database;

use Garden\Db\Cache;
use Garden\Db\Database;

/**
 * Database query wrapper
 */
class Query
{

    // Query type
    protected $_type;

    // Execute the query during a cache hit
    protected $_forceExecute = false;

    // Cache lifetime
    protected $_lifetime;


    // SQL statement
    protected $_sql;

    // Quoted query parameters
    protected $_parameters = [];

    // Return results as associative arrays or objects
    protected $_asObject = false;

    // Parameters for __construct when using object results
    protected $_objectParams = [];

    /**
     * Creates a new SQL query of the specified type.
     * @param int $type query type: Database::SELECT, Database::INSERT, etc
     * @param string $sql query string
     * @return void
     */
    public function __construct($type, $sql)
    {
        $this->_type = $type;
        $this->_sql = $sql;
    }

    /**
     * Return the SQL query string.
     * @return string
     */
    public function __toString()
    {
        try {
            // Return the SQL string
            return $this->compile(Database::instance());
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Get the type of the query.
     * @return int
     */
    public function type()
    {
        return $this->_type;
    }

    /**
     * Enables the query to be cached for a specified amount of time.
     * @param int $lifetime number of seconds to cache, 0 deletes it from the cache
     * @param boolean $force whether or not to execute the query during a cache hit
     * @return $this
     */
    public function cached($lifetime = null, $force = false)
    {
        if ($lifetime !== null) {
            $this->_lifetime = $lifetime;
        }

        $this->_forceExecute = $force;


        return $this;
    }

    /**
     * Returns results as associative arrays
     * @return $this
     */
    public function as_assoc()
    {
        $this->_asObject = false;

        $this->_objectParams = [];

        return $this;
    }

    /**
     * Returns results as objects
     * @param string $class classname or true for stdClass
     * @param array $params
     * @return $this
     */
    public function as_object($class = true, array $params = null)
    {
        $this->_asObject = $class;

        if ($params) {
            // Add object parameters
            $this->_objectParams = $params;
        }

        return $this;
    }

    /**
     * Set the value of a parameter in the query.
     * @param string $param parameter key to replace
     * @param mixed $value value to use
     * @return $this
     */
    public function param($param, $value)
    {
        // Add or overload a new parameter
        $this->_parameters[$param] = $value;

        return $this;
    }

    /**
     * Bind a variable to a parameter in the query.
     * @param string $param parameter key to replace
     * @param mixed $var variable to use
     * @return $this
     */
    public function bind($param, &$var)
    {
        // Bind a value to a variable
        $this->_parameters[$param] = &$var;

        return $this;
    }

    /**
     * Add multiple parameters to the query.
     * @param array $params list of parameters
     * @return $this
     */
    public function parameters(array $params)
    {
        // Merge the new parameters in
        $this->_parameters = array_merge($params, $this->_parameters);

        return $this;
    }

    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     * @param mixed $db Database instance or name of instance
     * @return string
     */
    public function compile($db = null)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = Database::instance($db);
        }

        // Import the SQL locally
        $sql = $this->_sql;

        if (!empty($this->_parameters)) {
            // Quote all of the values
            $values = array_map([$db, 'quote'], $this->_parameters);

            // Replace the values in the SQL
            $sql = strtr($sql, $values);
        }

        return $sql;
    }

    /**
     * Execute the current query on the given database.
     * @param mixed $db Database instance or name of instance
     * @param string result object classname, true for stdClass or false for array
     * @param array result object constructor arguments
     * @return Database\Result   Database_Result for SELECT queries
     * @return mixed the insert id for INSERT queries
     * @return int number of affected rows for all other queries
     */
    public function execute($db = null, $asObject = null, $objectParams = null)
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = Database::instance($db);
        }

        if ($asObject === null) {
            $asObject = $this->_asObject;
        }

        if ($objectParams === null) {
            $objectParams = $this->_objectParams;
        }

        // Compile the SQL query
        $sql = $this->compile($db);
        /**
         * @var Cache
         */
        $cachedClass = $db->configValue('cached_class', Cache\Simple::class);

        if ($cachedClass !== null && $this->_lifetime !== null && $this->_type === Database::SELECT) {
            // Set the cache key based on the database instance name and SQL
            $cacheKey = 'Database::query("' . $db . '", "' . $sql . '")';

            // Read the cache first to delete a possible hit with lifetime <= 0
            $result = $cachedClass::get($cacheKey);
            if ($result !== null && !$this->_forceExecute) {
                // Return a cached result
                return new Database\Result\Cached($result, $sql, $asObject, $objectParams);
            }
        }

        // Execute the query
        $result = $db->query($this->_type, $sql, $asObject, $objectParams);

        if (isset($cacheKey) && $this->_lifetime > 0) {
            // Cache the result array
            $cachedClass::set($cacheKey, $result->as_array(), $this->_lifetime);
        }

        return $result;
    }

}