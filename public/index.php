<?php

ini_set('display_errors', 1);   // для отладки

use Symfony\Component\Dotenv\Dotenv;
use App\DomainsChecker\Method\Whois;
use App\DomainsChecker\Method\Dns;
use App\DomainsChecker\Method\GoDaddy;
use App\DomainsChecker\DomainsChecker;
use App\DBLayer\DBLayer;
use App\Model\CheckingModel;

require __DIR__ . '/../vendor/autoload.php';  // подключаем автолоадер

// подключаем считывание переменных окружения из файла .env
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

$configuration = [
    'settings' => [
        'displayErrorDetails' => true,  // для отладки
        //'routerCacheFile' => __DIR__ . '/../var/cache/router' // кэш FastRoute, для отладки комментим
    ],
];

$container = new \Slim\Container($configuration);   // создаем экземпляр контейнера с учетом конфигурации

$app = new \Slim\App($container);   // создаем экземпляр приложения Slim, передаем конструктору контейнер

// регистрируем в контейнере наши сервисы
$container['method_whois'] = function (/** @noinspection PhpUnusedParameterInspection */ $container) {
    return new Whois();
};
$container['method_dns'] = function (/** @noinspection PhpUnusedParameterInspection */ $container) {
    return new Dns();
};
$container['method_godaddy'] = function (/** @noinspection PhpUnusedParameterInspection */ $container) {
    return new GoDaddy(getenv('GODADDY_SSO_KEY'));
};
$container['domains_checker'] = function ($container) {
    $checker = new DomainsChecker();
    $checker->addMethod("whois", $container['method_whois']);
    $checker->addMethod("dns", $container['method_dns']);
    $checker->addMethod("godaddy", $container['method_godaddy']);
    return $checker;
};
$container['database'] = function (/** @noinspection PhpUnusedParameterInspection */ $container) {
    return new DBLayer(
        getenv('MYSQL_HOST'),
        getenv('MYSQL_DATABASE'),
        getenv('MYSQL_USER'),
        getenv('MYSQL_PASSWORD')
    );
};
$container['checking_model'] = function ($container) {
    $checkingModel = new CheckingModel($container);
    $checkingModel->addRequestLengthForMethod('whois', 5);
    $checkingModel->addRequestLengthForMethod('dns', 10);
    $checkingModel->addRequestLengthForMethod('godaddy', 500);
    return $checkingModel;
};

// прописываем маршрутизацию
$app->post('/start_request', \App\Controller\MainController::class . ':startRequest')->setName('start_request');
$app->get('/get_next_part', \App\Controller\MainController::class . ':getNextPart')->setName('get_next_part');

/** @noinspection PhpUnhandledExceptionInspection */
$app->run();    // запускаем приложение Slim
