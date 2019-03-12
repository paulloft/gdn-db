<?php

namespace Garden\Db;

use Garden\Db\Database\Query;

class DB {

    /**
     * Create a new {Query} of the given type
     *
     * Specifying the type changes the returned result. When using
     * `Database::SELECT`, a Query\Result will be returned.
     * `Database::INSERT` queries will return the insert id and number of rows.
     * For all other queries, the number of affected rows is returned.
     *
     * @example $query = DB::query(Database::SELECT, 'SELECT * FROM users'); // Create a new SELECT query
     * @example $query = DB::query(Database::DELETE, 'DELETE FROM users WHERE id = 5'); // Create a new DELETE query
     *
     * @param int $type type: Database::SELECT, Database::UPDATE, etc
     * @param string $sql SQL statement
     * @return Query
     */
    public static function query($type, $sql): Query
    {
        return new Query($type, $sql);
    }

    /**
     * Create a new {Query\Builder\Select}. Each argument will be
     * treated as a column. To generate a `foo AS bar` alias, use an array
     *
     * @example $query = DB::select('id', 'username'); // SELECT id, username
     * @example $query = DB::select(['id', 'user_id']); // SELECT id AS user_id
     *
     * @param mixed $columns column name or [$column, $alias] or object
     * @return Query\Builder\Select
     */
    public static function select($column = null, $alias = null): Query\Builder\Select
    {
        return new Query\Builder\Select($column, $alias);
    }

    /**
     * Create a new {Query\Builder\Select} from an array of columns
     *
     * @example $query = DB::select_array(['id', 'username']); // SELECT id, username
     *
     * @param array $columns columns to select
     * @return Query\Builder\Select
     */
    public static function selectArray(array $columns = null): Query\Builder\Select
    {
        $sql = new Query\Builder\Select();
        return $sql->select_array($columns);
    }

    /**
     * Create a new {Query\Builder\Insert}
     *
     * @example $query = DB::insert('users', ['id', 'username']); // INSERT INTO users (id, username)
     *
     * @param string $table table to insert into
     * @param array $columns list of column names or [$column, $alias] or object
     * @return Query\Builder\Insert
     */
    public static function insert($table = null, array $columns = null): Query\Builder\Insert
    {
        return new Query\Builder\Insert($table, $columns);
    }

    /**
     * Create a new {Query\Builder\Update}
     *
     * @example $query = DB::update('users'); // UPDATE users
     *
     * @param string $table table to update
     * @return Query\Builder\Update
     */
    public static function update($table = null): Query\Builder\Update
    {
        return new Query\Builder\Update($table);
    }

    /**
     * Create a new {Query\Builder\Delete}
     *
     * @example $query = DB::delete('users'); // DELETE FROM users
     *
     * @param string $table table to delete from
     * @return Query\Builder\Delete
     */
    public static function delete($table = null): Query\Builder\Delete
    {
        return new Query\Builder\Delete($table);
    }

    /**
     * Create a new {Database\Expression} which is not escaped. An expression
     * is the only way to use SQL functions within query builders.
     *
     * @example
     * $expression = DB::expr('COUNT(users.id)');
     * $query = DB::update('users')->set(['login_count' => DB::expr('login_count + 1')])->where('id', '=', $id);
     *
     * @param string $string expression
     * @param array $parameters
     * @return Database\Expression
     */
    public static function expr($string, array $parameters = []): Database\Expression
    {
        return new Database\Expression($string, $parameters);
    }

}