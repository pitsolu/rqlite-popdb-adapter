<?php

require_once 'vendor/autoload.php';

use Pop\Db\Db;
use Pop\Db\Record;

class User extends Record
{

}

Record::setDb(Db::sqliteConnect([
    'database' => 'popdb.sqlite',
]));

$user = new User([
    'username' => 'admin',
    'password'    => sha1('admin@example.com'),
]);
$user->save();

// $user = User::findById(1);
// print_r($user->toArray());