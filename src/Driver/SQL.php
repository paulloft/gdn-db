<?php

namespace Garden\Db\Driver;

use Garden\Db\Database;

abstract class SQL extends Database
{

    public function datatype($type): array
    {
        static $types = [
            'blob' => ['type' => 'string', 'binary' => true, 'maxLength' => '65535'],
            'bool' => ['type' => 'bool'],
            'bigint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '18446744073709551615'],
            'datetime' => ['type' => 'string'],
            'decimal unsigned' => ['type' => 'float', 'exact' => true, 'min' => '0'],
            'double' => ['type' => 'float'],
            'double precision unsigned' => ['type' => 'float', 'min' => '0'],
            'double unsigned' => ['type' => 'float', 'min' => '0'],
            'enum' => ['type' => 'string'],
            'fixed' => ['type' => 'float', 'exact' => true],
            'fixed unsigned' => ['type' => 'float', 'exact' => true, 'min' => '0'],
            'float unsigned' => ['type' => 'float', 'min' => '0'],
            'geometry' => ['type' => 'string', 'binary' => true],
            'int unsigned' => ['type' => 'int', 'min' => '0', 'max' => '4294967295'],
            'integer unsigned' => ['type' => 'int', 'min' => '0', 'max' => '4294967295'],
            'longblob' => ['type' => 'string', 'binary' => true, 'maxLength' => '4294967295'],
            'longtext' => ['type' => 'string', 'maxLength' => '4294967295'],
            'mediumblob' => ['type' => 'string', 'binary' => true, 'maxLength' => '16777215'],
            'mediumint' => ['type' => 'int', 'min' => '-8388608', 'max' => '8388607'],
            'mediumint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '16777215'],
            'mediumtext' => ['type' => 'string', 'maxLength' => '16777215'],
            'national varchar' => ['type' => 'string'],
            'numeric unsigned' => ['type' => 'float', 'exact' => true, 'min' => '0'],
            'nvarchar' => ['type' => 'string'],
            'point' => ['type' => 'string', 'binary' => true],
            'real unsigned' => ['type' => 'float', 'min' => '0'],
            'set' => ['type' => 'string'],
            'smallint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '65535'],
            'text' => ['type' => 'string', 'maxLength' => '65535'],
            'tinyblob' => ['type' => 'string', 'binary' => true, 'maxLength' => '255'],
            'tinyint' => ['type' => 'int', 'min' => '-128', 'max' => '127'],
            'tinyint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '255'],
            'tinytext' => ['type' => 'string', 'maxLength' => '255'],
            'year' => ['type' => 'string'],
        ];

        $type = str_replace(' zerofill', '', $type);

        return $types[$type] ?? parent::datatype($type);
    }

    public function listTables($like = null): array
    {
        if (is_string($like)) {
            // Search for table names
            $result = $this->query(Database::SELECT, 'SHOW TABLES LIKE ' . $this->quote($like));
        } else {
            // Find all table names
            $result = $this->query(Database::SELECT, 'SHOW TABLES');
        }

        $tables = [];
        foreach ($result as $row) {
            $tables[] = reset($row);
        }

        return $tables;
    }

    public function listColumns($table, $like = null, $addPrefix = true): array
    {
        // Quote the table name
        $table = ($addPrefix === true) ? $this->quoteTable($table) : $table;

        if (is_string($like)) {
            // Search for column names
            $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM ' . $table . ' LIKE ' . $this->quote($like));
        } else {
            // Find all column names
            $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM ' . $table);
        }

        $count = 0;
        $columns = [];
        foreach ($result as $row) {
            list($type, $length) = $this->parseType($row['Type']);

            $column = (object)$this->datatype($type);

            $column->name = $row['Field'];
            $column->default = $row['Default'];
            $column->dataType = $type;
            $column->allownull = ($row['null'] === 'YES');
            $column->position = ++$count;
            $column->length = $length;

            switch ($column->type) {
                case 'float':
                    if (isset($length)) {
                        list($column->numPrecision, $column->numScale) = explode(',', $length);
                    }
                    break;
                case 'int':
                    if (isset($length)) {
                        // MySQL attribute
                        $column->length = $length;
                    }
                    break;
                case 'string':
                    switch ($column->dataType) {
                        case 'binary':
                        case 'varbinary':
                            $column->maxLength = $length;
                            break;
                        case 'char':
                        case 'varchar':
                            $column->maxLength = $length;
                            break;
                        case 'text':
                        case 'tinytext':
                        case 'mediumtext':
                        case 'longtext':
                            $column->collation = $row['Collation'];
                            break;
                        case 'enum':
                        case 'set':
                            $column->collation = $row['Collation'];
                            $column->options = explode('\',\'', substr($length, 1, -1));
                            break;
                    }
                    break;
            }

            // MySQL attributes
            // TODO Обрабатывать не только auto_increment, а ещё 'ON UPDATE CURRENT_TIMESTAMP', ''
            $column->autoIncrement = strpos($row['Extra'], 'auto_increment') !== false;
            $column->key = $row['Key'];
            $column->privileges = $row['Privileges'];

            $columns[$row['Field']] = $column;
        }

        return $columns;
    }

}