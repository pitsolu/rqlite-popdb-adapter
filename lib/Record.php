<?php

use Pop\Db\Db;

class Record extends \Pop\Db\Record{

	public static function findById(mixed $id, ?array $options = null, bool $toArray = false): array|static{

		$table = self::table();

		$sql = sprintf("SELECT %s.* FROM %s WHERE %s.id = %d LIMIT 1", $table, $table, $table, $id);

		$rqlite = self::db()->query(sprintf('["%s"]', $sql));
		$result = $rqlite->fetch();

		if($toArray)
			return $result;

		$class = get_called_class();

		return new $class($result);
	}
}