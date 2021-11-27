<?php

// include 'utils.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/', function (Request $request, Response $response, $args) {
    // Render index view
    return $this->view->render($response, 'index.latte');
})->setName('index');

$app->post('/test', function (Request $request, Response $response, $args) {
    //read POST data
    $input = $request->getParsedBody();

    //log
    $this->logger->info('Your name: ' . $input['person']);

    return $response->withHeader('Location', $this->router->pathFor('index'));
})->setName('redir');


$app->get('/login', function (Request $request, Response $response, $args) {
    return $this->view->render($response, 'login.latte');
})->setName('login');

$app->post('/login', function (Request $request, Response $response, $args) {
    $formData = $request->getParsedBody();

    // get hash of password
    $password_hash['pass'] = hash('sha256', $formData['password']);

    // check if username and password is in database
    $stmt = $this->db->prepare('SELECT * FROM colonist WHERE
    username = :un AND password = :pw');
    $stmt->bindValue(':un', $formData['username']);
    $stmt->bindValue(':pw', $password_hash['pass']);
    $stmt->execute();
    $logged_colonist = $stmt->fetch();
    if ($logged_colonist) {
        $_SESSION['logged_colonist'] = $logged_colonist;
        return $response->withHeader('Location', $this->router->pathFor('index'));
    }
    // else return to same page with message
    $data['message'] = "Wrong username or password";
    return $this->view->render($response, 'login.latte', $data);
});