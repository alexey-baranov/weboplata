<?php

header("PRAGMA:NO-CACHE"); //страница не кешируется!!!
header("Access-Control-Allow-Origin: *"); //можно вызывать с любого домена

ini_set("display_errors","1");

require_once __DIR__ . '/../lib/bootstrap.php';

$object = new \Zend_Rest_Client("https://iptv.tele-plus.ru/weboplata/public/1.0.php");
$object = new \Zend_Rest_Client("https://10.1.12.114:9443/weboplata/main");

$result = $object->getMetadata()
  ->AccountNumber("99999-И")
  ->PSID("dev")
  ->get();

echo $result;