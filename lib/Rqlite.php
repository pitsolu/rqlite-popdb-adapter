<?php

use Pop\Http\Client;
use Pop\Http\Client\Handler\Curl;
use Pop\Http\Client\Request;

/**
 * Rqite database adapter class
 * 
 * @author     Pitsolu <pitsolu@gmail.com>
 * @license    MIT
 */
class Rqlite extends Pop\Db\Adapter\AbstractAdapter
{

    /**
     * SQLite flags
     * @var ?int
     */
    protected ?int $flags = null;

    /**
     * SQLite key
     * @var ?string
     */
    protected ?string $key = null;

    /**
     * Last SQL query
     * @var ?string
     */
    protected ?string $lastSql = null;

    /**
     * Last result
     * @var mixed
     */
    protected mixed $lastResult = null;

    /**
     * Enable transactions
     * @var bool
     */
    private bool $useTrx = false;

    /**
     * List of SQLs
     * @var array
     */
    private mixed $sql = [];

    /**
     * Bind parameters
     * @var array
     */
    private array $params = [];

    /**
     * Constructor
     *
     * Instantiate the SQLite database connection object using SQLite3
     *
     * @param  array $options
     */
    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->connect($options);
        }
    }

    /**
     * Connect to the database
     *
     * @param  array $options
     * @return Sqlite
     */
    public function connect(array $options = []): Rqlite
    {
        if (!empty($options))
            $this->setOptions($options);

        $this->connection = $this->getClient();

        return $this;
    }

    public function getClient(){

        $config = array(
            "host"=>$this->options["url"]??"http://localhost:4001",
            "qstring"=>"pretty&timings"
        );

        $options = [
            "method"=>"POST",
            "type"=>Request::JSON
        ];

        $toString = function(mixed $sql){

            if(is_string($sql)){

                $sql = sprintf("[%s]", $sql);
            }
            
            if(is_array($sql)){

                $sql = array_map(fn($v)=>sprintf("\"%s\"",$v),  $sql);
                $sql = sprintf("[[%s]]", implode(",", $sql));
            }

            return $sql;
        };

        $client = function(string $type, mixed $sql) use($config, $options, $toString){

            $uri = sprintf("%s/db/%s?%s", $config["host"], $type, $config["qstring"]);
            $options["data"] = $toString($sql);

            $client = new Client($uri, $options);
            $response = $client->send();

            return $response;
        };

        $format = function(array $result){

            if(!empty($result)){

                $result = current($result["results"]);
                $keys = @$result["columns"];
                $values = @$result["values"];

                $rows = [];
                if(!empty($values)){

                    if(count($values) == count($values, COUNT_RECURSIVE)) //not_multidimentional
                        return array_combine($keys, $values[0]);

                    foreach($values as $row)
                        $rows[] = array_combine($keys, $row);
                }

                return $rows;
            }

            return [];
        };

        return new class($client, $format){

            private $fn;

            public function __construct(callable $client, callable $format){

                $this->fn["client"] = $client;
                $this->fn["format"] = $format;
            }

            public function execute(mixed $sql){

                $response = $this->fn["client"]("execute", $sql);

                return $response->json();
            }

            public function query(mixed $sql){

                $response = $this->fn["client"]("query", $sql);

                return $this->fn["format"]($response->json());
            }
        };
    }

    /**
     * Set database connection options
     * @param  array $options
     * @return Sqlite
     */
    public function setOptions(array $options): Rqlite
    {
        $this->options = $options;

        if (!$this->hasOptions())
            $this->options["url"] = 'http://localhost:4001';

        return $this;
    }

    /**
     * Has database connection options
     *
     * @return bool
     */
    public function hasOptions(): bool
    {
        return (isset($this->options['url']));
    }

    /**
     * Does the database file exist
     *
     * @return bool
     */
    public function dbFileExists(): bool
    {
        return (isset($this->options['url']) && file_exists($this->options['url']));
    }

    /**
     * Begin a transaction
     *
     * @return Sqlite
     */
    public function beginTransaction(): Rqlite
    {
        $this->useTrx = true;

        return $this;
    }

    /**
     * Commit a transaction

     *
     * @return Sqlite
     */
    public function commit(): Rqlite
    {
        $this->useTrx = false;

        return $this;
    }

    /**
     * Rollback a transaction
     *
     * @return Sqlite
     */
    public function rollback(): Rqlite
    {
        $this->useTrx = false;

        return $this;
    }

    /**
     * Check if transaction is success
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return ((($this->result !== null) && ($this->result !== false)) && (!$this->hasError()));
    }

    /**
     * Execute a SQL query directly
     *
     * @param  mixed $sql
     * @return Sqlite
     */
    public function query(mixed $sql): Rqlite
    {
        $this->lastSql = (stripos($sql, 'select') !== false) ? $sql : null;

        $this->result = $this->connection->query($sql);
    
        return $this;
    }

    /**
     * Prepare a SQL query
     *
     * @param  mixed $sql
     * @return Sqlite
     */
    public function prepare(mixed $sql): Rqlite
    {
        $this->sql = $sql;

        return $this;
    }

    /**
     * Bind parameters to a prepared SQL query
     *
     * @param  array $params
     * @return Sqlite
     */
    public function bindParams(array $params): Rqlite
    {
        foreach($params as $param=>$value)
            $this->params[$param] = $value;

        return $this;
    }

    /**
     * Bind a parameter for a prepared SQL query
     *
     * @param  mixed $param
     * @param  mixed $value
     * @param  int   $type
     * @return Sqlite
     */
    public function bindParam(mixed $param, mixed $value, int $type): Rqlite
    {
        $this->params[$param] = $value;

        return $this;
    }

    /**
     * Bind a value for a prepared SQL query
     *
     * @param  mixed $param
     * @param  mixed $value
     * @param  int   $type
     * @return Sqlite
     */
    public function bindValue(mixed $param, mixed $value, int $type): Rqlite
    {
        $this->params[$param] = $value;

        return $this;
    }

    /**
     * Execute a prepared SQL query
     *
     * @return Sqlite
     */
    public function execute(): Rqlite
    {
        $this->lastSql = (stripos($this->sql, 'select') !== false) ? $this->sql : null;

        if((str_starts_with($this->sql, "UPDATE") 
            || str_starts_with($this->sql, "INSERT")
            || str_starts_with($this->sql, "DELETE"))){
            
            if(str_contains($this->sql, "NULL")){

                $temp = str_replace("NULL", "?", $this->sql);
                $this->sql = array_merge([$temp], array_values($this->params));
            }

            //if full assoc array enter into rqlite format
            if(count(array_filter(array_keys($this->params), 'is_string')) == count($this->params)){

                if(is_string($this->sql)){

                    $temp = str_replace("\"",'', $this->sql);
                    $this->sql = sprintf("[\"%s\", %s]", $temp, json_encode($this->params));
                }
            }

            $this->lastResult = $this->result = $this->connection->execute($this->sql);

            return $this;
        }

        if(str_starts_with($this->sql, "SELECT")){

            $this->sql = str_replace("\"",'', $this->sql);

            if(empty($this->params))
                $sql = sprintf("[\"%s\"]", $this->sql);

            if(!empty($this->params)){

                if(!is_array($this->sql))
                    foreach($this->params as $param=>$value)
                        if(str_contains($this->sql, $value))
                            $this->sql = str_replace($value, sprintf("'%s'", $value), $this->sql);
                        else
                            $this->sql = str_replace(sprintf(":%s", $param), "?", $this->sql);

                $temp = $this->sql;
                $params = array_map(fn($v)=>sprintf("\"%s\"", $v), $this->params);
                $this->params = [];
                
                $sql[] = sprintf("\"%s\"", $temp);
                $sql = array_merge($sql, $params);
                $sql = sprintf("[%s]", implode(",", $sql));
            }

            $this->lastResult = $this->result = $this->connection->query($sql);

            return $this;
        }
    }

    /**
     * Fetch and return a row from the result
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        if ($this->result === null) {
            $this->throwError('Error: The database result resource is not currently set.');
        }

        $row = array_pop($this->result);

        return $row;
    }

    /**
     * Fetch and return all rows from the result
     *
     * @return array
     */
    public function fetchAll(): array
    {
        return $this->result;
    }

    /**
     * Disconnect from the database
     *
     * @return void
     */
    public function disconnect(): void
    {
        //
    }

    /**
     * Escape the value
     *
     * @param  ?string $value
     * @return string
     */
    public function escape(?string $value = null): string
    {
        return sprintf("'%s'", $value);
    }

    /**
     * Return the last ID of the last query
     *
     * @return int
     */
    public function getLastId(): int
    {
        $table = null;
        $tokens = [];

        if(!is_null($this->lastSql)){

            $lastSql = str_replace("\"","", $this->lastSql);

            preg_match_all("/(INTO|FROM)\s+(\w+)[\s+|\(]/", $lastSql, $tokens);
            $tokens = end($tokens);
            $table = end($tokens);

            $sql = sprintf("SELECT seq FROM sqlite_sequence WHERE name='%s'", $table);
            $result = $this->connection->query(sprintf('["%s"]', $sql));
            $result = current($result);

            return $result["seq"];
        }

        return 0;
    }

    /**
     * Return the number of rows from the last query
     *
     * @throws Exception
     * @return int
     */
    public function getNumberOfRows(): int
    {
        $count = count($this->lastResult);

        return $count;
    }

    /**
     * Return the number of affected rows from the last query
     *
     * @return int
     */
    public function getNumberOfAffectedRows(): int
    {
        $sql = "PRAGMA count_changes;";

        $result = $this->connection->query(sprintf('["%s"]', $sql));
        $result = current($result);

        return $result['count_changes']??0;
    }

    /**
     * Return the database version
     *
     * @return string
     */
    public function getVersion(): string
    {

        $sql = "SELECT sqlite_version() as version;";

        $result = $this->connection->query(sprintf('["%s"]', $sql));
        $result = current($result);

        return $result["version"];
    }

    /**
     * Return the tables in the database
     *
     * @return array
     */
    public function getTables(): array
    {
        $tables = [];
        $sql    = "SELECT name FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' " .
            "UNION ALL SELECT name FROM sqlite_temp_master WHERE type IN ('table', 'view') ORDER BY 1";

        $this->query(sprintf('["%s"]', $sql));

        foreach($this->result as $row)
            $tables[] = $row["name"];


        return $tables;
    }

}