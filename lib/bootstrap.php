<?php

ini_set('display_errors','On');
date_default_timezone_set("Etc/GMT-5");

require __DIR__.'/../vendor/autoload.php';

\Logger::configure(__DIR__.'/log4php.xml');

Doctrine\Common\Annotations\AnnotationRegistry::registerFile(__DIR__.'/../vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');
Billing\Core::configureDefault();

$x= $_SERVER;