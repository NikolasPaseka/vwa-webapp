<?php

include 'utils.php';

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
    $stmt = $this->db->prepare('SELECT * FROM colonist C 
                                LEFT JOIN habitat H ON C.id_habitat = H.id_habitat
                                LEFT JOIN droid D on C.id_droid = D.id_droid
                                WHERE id_colonist = :idc');
    $stmt->bindValue(':idc', $args['id']);
    $stmt->execute();
    $data['colonist'] = $stmt->fetch();

    return $this->view->render($response, 'colonist_profile.latte', $data);
})->setName('colonist_profile');

// add credits
$app->post('/colonist/{id}/profile', function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    if (!empty($params) and $params['credits'] > 0) {
        $stmt = $this->db->prepare('UPDATE colonist SET credits = credits + :credits WHERE id_colonist = :idc');
        $stmt->bindValue(':credits', $params['credits']);
        $stmt->bindValue(':idc', $args['id']);
        $stmt->execute();
    }

    return $response->withRedirect($this->router->pathFor('colonist_profile', $args));
})->setName('colonist_profile_addCredits');

// edit colonist profile
$app->get('/colonist/{id}/profile/edit', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT * FROM colonist C 
                                LEFT JOIN habitat H ON C.id_habitat = H.id_habitat
                                LEFT JOIN droid D on C.id_droid = D.id_droid
                                WHERE id_colonist = :idc');
    $stmt->bindValue(':idc', $args['id']);
    $stmt->execute();

    // data 2D array, habitat 1D array
    $data['colonist'] = $stmt->fetch();

    $stmt = $this->db->prepare('SELECT colonist.id_habitat, name, size, count(*) AS number_of_colonists FROM colonist 
                                LEFT JOIN habitat ON colonist.id_habitat = habitat.id_habitat 
                                GROUP BY colonist.id_habitat');
    $stmt->execute();
    $data['habitats'] = $stmt->fetchall();

    if ($_SESSION['message']) {
        $data['message'] = $_SESSION['message'];
        $_SESSION['message'] = NULL;
    }

    return $this->view->render($response, 'colonist_profile_edit.latte', $data);
})->setName('colonist_profile_edit');

$app->post('/colonist/{id}/profile/edit', function (Request $request, Response $response, $args) {
    $formData = $request->getParsedBody();
    if (checkHabitatCapacity($this, $formData['habitat'])) {
        try {
            $stmt = $this->db->prepare('UPDATE colonist SET firstname = :fn, lastname = :ln, gender = :ge,
                                        username = :un, id_habitat = :idh
                                        WHERE id_colonist = :idc');
            $stmt->bindValue(':fn', $formData['firstname']);
            $stmt->bindValue(':ln', $formData['lastname']);
            $stmt->bindValue(':ge', $formData['gender']);
            $stmt->bindValue(':un', $formData['username']);
            $stmt->bindValue(':idh', $formData['habitat']);
            $stmt->bindValue(':idc', $args['id']);
            $stmt->execute();

            $data['message'] = "Data succesfuly updated";
            
            // overeni jestli je upravovany profil lognuty -> refresh
            $logged_colonist = $_SESSION['logged_colonist'];
            if ($args['id'] == $logged_colonist['id_colonist']) {
                $_SESSION['logged_colonist'] = reloadLoggedColonist($this);
            }
        } catch (Exception $e) {
            $data['message'] = $e;
        }
    } else {
        $data['message'] = 'No space in selected habitat, try to select another one';
    }
    $_SESSION['message'] = $data['message'];

    return $response->withRedirect($this->router->pathFor('colonist_profile_edit', $args));
});

// create new colonist
$app->get('/colonist/create', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT colonist.id_habitat, name, size, count(*) AS number_of_colonists FROM colonist 
                                LEFT JOIN habitat ON colonist.id_habitat = habitat.id_habitat 
                                GROUP BY colonist.id_habitat');
    $stmt->execute();
    $data['habitats'] = $stmt->fetchall();

    if ($_SESSION['message']) {
        $data['message'] = $_SESSION['message'];
        $_SESSION['message'] = NULL;
    }

    return $this->view->render($response, 'colonist_new.latte', $data);
})->setName('colonist_new');

$app->post('/colonist/create', function (Request $request, Response $response, $args) {
    $formData = $request->getParsedBody();
    if (checkHabitatCapacity($this, $formData['habitat'])) {
        try {
            $stmt = $this->db->prepare('INSERT INTO colonist (firstname, lastname, username, gender, authorization, id_habitat)
                                        VALUES (:fn, :ln, :un, :ge, :auth, :idh)');
            $stmt->bindValue(':fn', $formData['firstname']);
            $stmt->bindValue(':ln', $formData['lastname']);
            $stmt->bindValue(':un', $formData['username']);
            $stmt->bindValue(':ge', $formData['gender']);
            $stmt->bindValue(':auth', "colonist");
            $stmt->bindValue(':idh', $formData['habitat']);
            $stmt->execute();
            $data['message'] = 'New colonist sucessfuly created';
        } catch (Exception $e) {
            $data['message'] = $e;
        }
    } else {
        $data['message'] = 'No space in selected habitat, try to select another one';
    }
    $_SESSION['message'] = $data['message'];
    return $response->withRedirect($this->router->pathFor('colonist_new'));
});

// profile page of user - sell droid
$app->get('/colonist/profile/sell_droid', function (Request $request, Response $response, $args) {
    $logged_colonist = $_SESSION['logged_colonist'];
    if ($logged_colonist['id_droid']) {
        // sell dorid
        $stmt = $this->db->prepare('UPDATE colonist SET 
                                    id_droid = NULL, 
                                    credits = credits + (SELECT price FROM droid where droid.id_droid = colonist.id_droid) 
                                    WHERE id_colonist = :id');
        $stmt->bindValue(':id', $logged_colonist['id_colonist']);
        $stmt->execute();

        // reload logged user from database
        $_SESSION['logged_colonist'] = reloadLoggedColonist($this);
    }

    return $response->withRedirect($this->router->pathFor('colonist_profile', ['id' => $logged_colonist['id_colonist']]));
})->setName('colonist_profile_sell_droid');

// list of all colonists
$app->get('/colonist/list/all', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT * FROM colonist');
    $stmt->execute();
    $data['colonists'] = $stmt->fetchall();

    $data['filter'] = "All colonists";

    return $this->view->render($response, 'colonist_list.latte', $data);
})->setName('colonist_list_all');

// list of colonists set filter
$app->get('/colonist/list/habitat', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT * FROM colonist WHERE id_habitat = :loggidhab');
    $logged_colonist = $_SESSION['logged_colonist'];
    $stmt->bindValue(':loggidhab', $logged_colonist['id_habitat']);
    $stmt->execute();
    $data['colonists'] = $stmt->fetchall();

    $data['filter'] = "Colonists in same habitat";

    return $this->view->render($response, 'colonist_list.latte', $data);
})->setName('colonist_list_habitat');

// list habitats
$app->get('/habitat/list', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT *, count(*) AS actual_capacity FROM colonist 
                                LEFT JOIN habitat ON habitat.id_habitat = colonist.id_habitat
                                LEFT JOIN (SELECT username AS majordom, id_habitat AS id_h FROM colonist WHERE authorization = :auth) c 
                                    ON habitat.id_habitat = c.id_h
                                GROUP BY colonist.id_habitat');
    $stmt->bindValue(':auth', "majordom");
    $stmt->execute();
    $data['habitats'] = $stmt->fetchall();

    return $this->view->render($response, 'habitat_list.latte', $data);
})->setName('habitats_list');

// habitat info page
$app->get('/habitat/{id}/info', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT *, count(*) AS actual_capacity FROM colonist 
                                LEFT JOIN habitat ON habitat.id_habitat = colonist.id_habitat
                                LEFT JOIN (SELECT username AS majordom, id_habitat AS id_h FROM colonist WHERE authorization = :auth) c 
                                    ON habitat.id_habitat = c.id_h
                                WHERE habitat.id_habitat = :idh
                                GROUP BY colonist.id_habitat');
    $stmt->bindValue(':auth', "majordom");
    $stmt->bindValue(":idh", $args['id']);
    $stmt->execute();
    $data['habitat'] = $stmt->fetch();

    $stmt = $this->db->prepare('SELECT * FROM colonist WHERE id_habitat = :idh');
    $stmt->bindValue(":idh", $args['id']);
    $stmt->execute();
    $data['colonists'] = $stmt->fetchAll();

    return $this->view->render($response, 'habitat_info.latte', $data);
})->setName('habitat_info');

// move out from habitat
$app->get('/habitat/{id}/move_out/{idc}', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('UPDATE colonist SET id_habitat = NULL
                                WHERE id_colonist = :idc');
    $stmt->bindValue(":idc", $args['idc']);
    $stmt->execute();

    return $response->withRedirect($this->router->pathFor('habitat_info', $args));
})->setName('habitat_move_out');

// edit habitat info
$app->get('/habitat/{id}/info/edit', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT * FROM habitat WHERE id_habitat = :idh');
    $stmt->bindValue(':idh', $args['id']);
    $stmt->execute();

    // data 2D array, habitat 1D array
    $data['habitat'] = $stmt->fetch();

    if ($_SESSION['message']) {
        $data['message'] = $_SESSION['message'];
        $_SESSION['message'] = NULL;
    }

    return $this->view->render($response, 'habitat_info_edit.latte', $data);
})->setName('habitat_info_edit');

$app->post('/habitat/{id}/info/edit', function (Request $request, Response $response, $args) {
    $formData = $request->getParsedBody();
    if (getHabitatCapacity($this, $args['id']) <= $formData['size']) {
        try {
            $stmt = $this->db->prepare('UPDATE habitat SET name = :name, coordinate_x = :coorx, coordinate_y = :coory,
                                        size = :size
                                        WHERE id_habitat = :idh');
            $stmt->bindValue(':name', $formData['name']);
            $stmt->bindValue(':coorx', $formData['coordinate_x']);
            $stmt->bindValue(':coory', $formData['coordinate_y']);
            $stmt->bindValue(':size', $formData['size']);
            $stmt->bindValue(':idh', $args['id']);
            $stmt->execute();

            $data['message'] = "Data succesfuly updated";
        } catch (Exception $e) {
            $data['message'] = $e;
        }
    } else {
        $data['message'] = 'Size is smaller then actual capacity of habitat';
    }
    $_SESSION['message'] = $data['message'];

    return $response->withRedirect($this->router->pathFor('habitat_info_edit', $args));
});

// create new habitat
$app->get('/habitat/create', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT * FROM colonist WHERE authorization = :auth');
    $stmt->bindValue(':auth', "colonist");
    $stmt->execute();
    $data['colonists'] = $stmt->fetchAll();

    if ($_SESSION['message']) {
        $data['message'] = $_SESSION['message'];
        $_SESSION['message'] = NULL;
    }

    return $this->view->render($response, 'habitat_new.latte', $data);
})->setName('habitat_new');

$app->post('/habitat/create', function (Request $request, Response $response, $args) {
    $formData = $request->getParsedBody();
    try {
        $stmt = $this->db->prepare('INSERT INTO habitat (name, coordinate_x, coordinate_y, size)
                                    VALUES (:name, :coorx, :coory, :size)');
        $stmt->bindValue(':name', $formData['name']);
        $stmt->bindValue(':coorx', $formData['coordinate_x']);
        $stmt->bindValue(':coory', $formData['coordinate_y']);
        $stmt->bindValue(':size', $formData['size']);
        $stmt->execute();

        // get ID of created habitat
        $stmt = $this->db->prepare('SELECT id_habitat FROM habitat WHERE name = :name');
        $stmt->bindValue(':name', $formData['name']);
        $stmt->execute();
        $id_habitat = $stmt->fetch();
        
        // make colonist majordom
        $stmt = $this->db->prepare('UPDATE colonist SET id_habitat = :idh, authorization = :auth
                                    WHERE id_colonist = :idc');
        $stmt->bindValue(':idh', $id_habitat['id_habitat']);
        $stmt->bindValue(':auth', "majordom");
        $stmt->bindValue(':idc', $formData['colonist']);
        $stmt->execute();

        $data['message'] = 'New habitat sucessfuly created';
    } catch (Exception $e) {
        $data['message'] = $e;
    }

    $_SESSION['message'] = $data['message'];
    return $response->withRedirect($this->router->pathFor('habitat_new'));
});

// droid series list
$app->get('/droid/list', function (Request $request, Response $response, $args) {
    $stmt = $this->db->prepare('SELECT DISTINCT model FROM droid');
    $stmt->execute();
    $data['droids'] = $stmt->fetchall();

    return $this->view->render($response, 'droid_list.latte', $data);
})->setName('droid_list');

// model droid list shop
$app->get('/droid/shop/{model}', function (Request $request, Response $response, $args) {
    //$data['droids'] = $_SESSION['droids'];
    // $_SESSION['droids'] = null;

    $stmt = $this->db->prepare('SELECT * FROM droid WHERE model = :model
                                AND (SELECT count(*) FROM colonist WHERE colonist.id_droid = droid.id_droid) = 0');
    $stmt->bindValue(':model', $args['model']);
    $stmt->execute();
    $data['droids'] = $stmt->fetchall();

    if ($_SESSION['message']) {
        $data['message'] = $_SESSION['message'];
        $_SESSION['message'] = NULL;
    }

    return $this->view->render($response, 'droid_shop.latte', $data);
})->setName('droid_shop');

// buy droid
$app->get('/droid/shop/{model}/{id}/buy', function (Request $request, Response $response, $args) {
    $logged_colonist = $_SESSION['logged_colonist'];

    if (!$logged_colonist['id_droid']) {
        $stmt = $this->db->prepare('SELECT * FROM droid WHERE id_droid = :id');
        $stmt->bindValue(':id', $args['id']);
        $stmt->execute();
        $droid = $stmt->fetch();

        if ($logged_colonist['credits'] >= $droid['price']) {
            $remaining_credits = $logged_colonist['credits'] - $droid['price'];

            $stmt = $this->db->prepare('UPDATE colonist SET id_droid = :idd, credits = :cred
                                        WHERE id_colonist = :idc');
            $stmt->bindValue(':idd', $args['id']);
            $stmt->bindValue(':cred', $remaining_credits);
            $stmt->bindValue(':idc', $logged_colonist['id_colonist']);
            $stmt->execute();
            $message = "You succesfully bought a new droid";

            // reload logged user from database
            $_SESSION['logged_colonist'] = reloadLoggedColonist($this);
        } else {
            $message = "You dont have enough credits to buy this droid";
        }
    } else {
        $message = "You already have a droid";
    }
    $_SESSION['message'] = $message;

    return $response->withRedirect($this->router->pathFor('droid_shop', $args));
})->setName('droid_shop_buy');