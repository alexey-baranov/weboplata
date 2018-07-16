<?php
header("PRAGMA:NO-CACHE"); //страница не кешируется!!!
header("Access-Control-Allow-Origin: *"); //можно вызывать с любого домена

ini_set("display_errors","1");

require_once __DIR__ . '/../lib/bootstrap.php';

Logger::getRootLogger()->debug($_REQUEST);

class Wrapper extends \Weboplata\Weboplata{

}

$server= new Zend_Rest_Server();
$server->setClass(new Wrapper);
$server->returnResponse(true);
/* @var $RESPONSE string */
$RESPONSE= $server->handle();

//var_dump($RESPONSE);

$response= simplexml_load_string($RESPONSE);

if (!$response){
    Logger::getRootLogger()->fatal($RESPONSE); //fatal потому что даже нет ответа от сервера
}
else if ((string)$response->xpath('//status')[0]== "failed"){
    Logger::getRootLogger()->error((string)$response->xpath("//response/message")[0]);
}
foreach ($server->getHeaders() as $eachHeader) {
    header($eachHeader);
}
echo $RESPONSE;