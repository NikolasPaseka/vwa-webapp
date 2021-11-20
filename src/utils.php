<?php

function newLocation($app, $formData) {
    if (!empty($formData['street_number']) or !empty($formData['street_name']) or !empty($formData['city']) 
                    or !empty($formData['zip'])) {
        $stmt = $app->db->prepare('
            INSERT INTO location 
            (street_name, street_number, zip, city) 
            VALUES (:sname, :snumber, :zip, :city)
        ');
        $stmt->bindValue(':sname', empty($formData['street_name']) ? null : $formData['street_name']);
        $stmt->bindValue(':snumber', empty($formData['street_number']) ? null : $formData['street_number']);
        $stmt->bindValue(':zip', empty($formData['zip']) ? null : $formData['zip']);
        $stmt->bindValue(':city', empty($formData['city']) ? null : $formData['city']);

        $stmt->execute();
        $id_location = $app->db->lastInsertId();
        return $id_location;
    } else {
        return null;
    }
}

?>