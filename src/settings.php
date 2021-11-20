<?php

// $env = Dotenv\Dotenv::create(__DIR__ . DIRECTORY_SEPARATOR . '..');
// $env->load();

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // 'db' => [
        //     'dbtype' => getenv('DB_TYPE'),
        //     'dbhost' => getenv('DB_HOST'),
        //     'dbname' => getenv('DB_NAME'),
        //     'dbuser' => getenv('DB_USER'),
        //     'dbpass' => getenv('DB_PASS')
        // ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];
