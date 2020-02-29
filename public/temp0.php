<?php

header("PRAGMA:NO-CACHE"); //страница не кешируется!!!
header("Access-Control-Allow-Origin: *"); //можно вызывать с любого домена

ini_set("display_errors","1");

require_once __DIR__ . '/../lib/bootstrap.php';