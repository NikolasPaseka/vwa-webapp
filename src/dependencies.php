<?php

use Latte\Engine;
use Latte\MacroNode;
use Latte\PhpWriter;
use Latte\Loaders\FileLoader;
use Ujpef\LatteView;

// DIC configuration

$container = $app->getContainer();

// monolog
$container['logger'] = function ($c) {
    $settings = $c['settings']['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

//database
$container['db'] = function ($c) {
    // $db = $c['settings']['db'];
    // $pdo = new PDO('sqlite:../database/persons.db');
    // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // $pdo->query("SET NAMES 'utf8'");
    return $pdo;
};

$container['view'] = function ($container) use ($settings) {
    $engine = new Engine();
    $engine->setLoader(new FileLoader($settings['settings']['renderer']['template_path']));
    $engine->setTempDirectory(__DIR__ . '/../cache');

    $latteView = new LatteView($engine);
    $latteView->addParam('router', $container->router);
    $latteView->addMacro('link', function (MacroNode $node, PhpWriter $writer) use ($container) {
        if (strpos($node->args, ' ') !== false) {
            return $writer->write("echo \$router->pathFor(%node.word, %node.args);");
        } else {
            return $writer->write("echo \$router->pathFor(%node.word);");
        }
    });
    return $latteView;
};
