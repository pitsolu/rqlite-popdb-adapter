<?php

// use Kami\Rqlite\Rqlite;
// use Kami\Rqlite\Adapters\Curl;

// use Pop\Db\Adapter\Rqlite;
use Pop\Db\Db;
use Pop\Db\Record;


require "vendor/autoload.php";

// Create a http adapter and point it to your rqlite server
// You can create your own adapter by implementing the Adapter interface
// $curlAdapter = new Curl('http://localhost:4001');

// Create a Rqlite client instance and pass the adapter as a constructor argument
// $rqlite = new Rqlite($curlAdapter);

$db = Db::connect("rqlite", ["url"=>"http://localhost:4001"], "");
// User::setDb($db);
Record::setDb($db);
// $rq = new Rqlite(["url"=>"http://localhost:4001"]);


