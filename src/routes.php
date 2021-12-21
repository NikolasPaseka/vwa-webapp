<?php

// include 'utils.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/', function (Request $request, Response $response, $args) {
    // Render index view
    if ($_SESSION['logged_colonist']) {
        return $this->view->render($response, 'index.latte');
    } else {
        return $response->withHeader('Location', $this->router->pathFor('login'));
    }
})->setName('index');

// login page render
$app->get('/login', function (Request $request, Response $response, $args) {
    return $this->view->render($response, 'login.latte');
})->setName('login');

// login process and authorization
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

// logout
$app->get('/logout', function (Request $request, Response $response, $args) {
    session_destroy();
    return $response->withHeader('Location', $this->router->pathFor('index'));
})->setName('logout');

// profile page of user
$app->get('/colonist/{id}/profile', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT * FROM colonist C LEFT JOIN habitat H ON C.id_habitat = H.id_habitat 
    WHERE id_colonist = :idc');
    $stmt->bindValue(':idc', $args['id']);
    $stmt->execute();

    // data 2D array, habitat 1D array
    $data['colonist'] = $stmt->fetch();

    return $this->view->render($response, 'colonist_profile.latte', $data);
})->setName('colonist_profile');

// list of all colonists
$app->get('/colonist/list', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT * FROM colonist');
    $stmt->execute();
    $data['colonists'] = $stmt->fetchall();

    return $this->view->render($response, 'colonist_list.latte', $data);
})->setName('colonist_list');

// list of colonists set filter
$app->post('/colonist/list', function (Request $request, Response $response, $args) {
    $formData = $request->getParsedBody();
    $stmt = $this->db->prepare('SELECT * FROM colonist');

    if ($formData['list'] == 'same habitat') {
        $stmt = $this->db->prepare('SELECT * FROM colonist WHERE id_habitat = :loggidhab');
        $logged_colonist = $_SESSION['logged_colonist'];
        $stmt->bindValue(':loggidhab', $logged_colonist['id_habitat']);
    }
    $stmt->execute();
    $data['colonists'] = $stmt->fetchall();

    return $this->view->render($response, 'colonist_list.latte', $data);
})->setName('colonist_list_filter');

// droid series list
$app->get('/droid/list', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT DISTINCT model FROM droid');
    $stmt->execute();
    $data['droids'] = $stmt->fetchall();

    return $this->view->render($response, 'droid_list.latte', $data);
})->setName('droid_list');

$app->post('/droid/list', function (Request $request, Response $response, $args) {
    $formData = $request->getParsedBody();

    $stmt = $this->db->prepare('SELECT * FROM droid WHERE model = :model
                                AND (SELECT count(*) FROM colonist WHERE colonist.id_droid = droid.id_droid) = 0');
    $stmt->bindValue(':model', $formData['model']);
    $stmt->execute();
    $_SESSION['droids'] = $stmt->fetchall();

    return $response->withHeader('Location', $this->router->pathFor('droid_shop'));
});

// model droid list shop
$app->get('/droid/shop', function (Request $request, Response $response, $args) {
    $data['droids'] = $_SESSION['droids'];
    $_SESSION = null;

    return $this->view->render($response, 'droid_shop.latte', $data);
})->setName('droid_shop');