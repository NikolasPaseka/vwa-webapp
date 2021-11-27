<?php
// Application middleware

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->add(function(Request $request, Response $response, $next) {
    $basePath = $request->getUri()->getBasePath();
    $this->view->addParam('logged_colonist', $_SESSION['logged_colonist']);
    $this->view->addParam('basePath', $basePath);
    return $next($request, $response);
});