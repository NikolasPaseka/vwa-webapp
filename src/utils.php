<?php

function reloadLoggedColonist($app) {
    $logged_colonist = $_SESSION['logged_colonist'];

    $stmt = $app->db->prepare('SELECT * FROM colonist WHERE id_colonist = :id');
    $stmt->bindValue(':id', $logged_colonist['id_colonist']);
    $stmt->execute();
    $logged_colonist = $stmt->fetch();
    
    return $logged_colonist;
}

function checkHabitatCapacity($app, $idh) {
    $stmt = $app->db->prepare('SELECT size, name, count(*) AS actual_capacity FROM colonist 
                                JOIN habitat ON habitat.id_habitat = colonist.id_habitat 
                                WHERE colonist.id_habitat = :idh');
    $stmt->bindValue(":idh", $idh);
    $stmt->execute();
    $data = $stmt->fetch();

    if ($data['actual_capacity'] < $data['size'])
        return true;
    else
        return false;
}

function getHabitatCapacity($app, $idh) {
    $stmt = $app->db->prepare('SELECT size, name, count(*) AS actual_capacity FROM colonist 
                                JOIN habitat ON habitat.id_habitat = colonist.id_habitat 
                                WHERE colonist.id_habitat = :idh');
    $stmt->bindValue(":idh", $idh);
    $stmt->execute();
    $data = $stmt->fetch();

    return $data['actual_capacity'];
}

?>