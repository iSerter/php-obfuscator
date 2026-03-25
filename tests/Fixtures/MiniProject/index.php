<?php
use MiniProject\Services\DatabaseService;
use MiniProject\Controllers\UserController;
use ExternalLib\Logger;

$db = new DatabaseService();
$controller = new UserController($db);
$logger = new Logger();

$logger->log("Starting execution...");
$controller->execute();
