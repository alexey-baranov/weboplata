<?php
header("PRAGMA:NO-CACHE"); //страница не кешируется!!!
header("Access-Control-Allow-Origin: *"); //можно вызывать с любого домена

ini_set("display_errors","1");

require_once __DIR__ . '/../lib/bootstrap.php';

Logger::getRootLogger()->debug($_REQUEST);

class Wrapper extends \Weboplata\Weboplata2{

}

$server= new Zend_Rest_Server();
$server->setClass(new Wrapper);
echo $server->handle();