<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
//composer init
require 'vendor/autoload.php';

//autoload classes via composer
// spl_autoload_register(function ($classname) {
//     require ("classes/" . $classname . ".php");
// });

//db creds, debug settings
require 'settings.php';

$app = new \Slim\App(["settings"] => $config);
$container = $app->getContainer();

//monolog conf
$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('guide_logger');
    $file_handler = new \Monolog\Handler\StreamHandler("logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};

//db conf
$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],$db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

//file uploads
$container['upload_directory'] = __DIR__ . '/uploads';

//api routes
require 'routes/main.php';

$app->run();
