<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\plugins\Db\Db;
use Exception;

/**
 * @property mixed|void $db
 */
class FacilityController extends BaseController {

    public function getFacilities() {
        $facilityQuery = "SELECT * FROM Facility";
        $results = $this->db->fetchQuery($facilityQuery);

        if (count($results) < 1) {
            (new Status\NoContent())->send();
            die;
        }

        for ($i = 0; $i < count($results); $i++) {
            $facility = $results[$i];

            $tagQuery = "SELECT tag FROM facilitytag WHERE facility = ?";
            $tagQueryResult = $this->db->fetchQuery($tagQuery, [$facility['name']]);
            $facility['tags'] = $tagQueryResult;
            $results[$i] = $facility;
        }

        (new Status\Ok($results))->send();
    }

    public function getFacilityByName($facilityName) {
        $facilityQuery = "SELECT * FROM Facility WHERE name = ?";
        $facility = $this->db->fetchQuery($facilityQuery, [$facilityName]);

        if (count($facility) < 1) {
            (new Status\NoContent())->send();
            die;
        }

        $tagQuery = "SELECT tag FROM facilitytag WHERE facility = ?";
        $tagQueryResult = $this->db->fetchQuery($tagQuery, [$facility[0]['name']]);
        $facility[0]['tags'] = $tagQueryResult;

        (new Status\Ok($facility))->send();
    }

    public function createFacility() {
        $entityBody = file_get_contents('php://input');
        $facility = json_decode($entityBody, true);

        $name = $facility['name'];
        $creationDate = $facility['creation_date'];
        $location = $facility['location'];

        if ($name == null || $creationDate == null || $location == null) {
            (new Status\NoContent("Not all fields are provided."))->send();
            die;
        }

        $createQuery = "INSERT INTO facility (name, creation_date, location) VALUES (?, ?, ?)";
        $createQueryResult = $this->db->executeQuery($createQuery, [$name, $creationDate, $location]);

        if (array_key_exists('tags', $facility)) {
            $tagsToDatabase = $this->createTagsFromFacility($facility['tags'], $name);

            if (!$tagsToDatabase) {
                (new Status\InternalServerError("Something went wrong while trying to save the tags."))->send();
                die;
            }
        }

        if (!$createQueryResult) {
            (new Status\InternalServerError("Something went wrong while trying to save the facility."))->send();
            die;
        }

        (new Status\Ok($facility))->send();
    }

    public function updateFacility($facilityName) {
        $entityBody = file_get_contents('php://input');
        $facility = json_decode($entityBody, true);

        $name = $facility['name'];
        $creationDate = $facility['creation_date'];
        $location = $facility['location'];

        if ($facilityName != $name) {
            (new Status\BadRequest("Name of the facility does not match."))->send();
        }

        $updateQuery = "UPDATE facility SET name = ?, creation_date = ?, location = ? WHERE name = ?";

        try {
            $updateQueryResult =  $this->db->executeQuery($updateQuery, [$name, $creationDate, $location, $name]);
        } catch (Exception $e) {
            (new Status\InternalServerError($e))->send();
            die;
        }


        if (!$updateQueryResult) {
            (new Status\InternalServerError("Something went wrong while trying to update the facility."))->send();
            die;
        }

        $this->db->executeQuery("DELETE FROM facilitytag WHERE facility = ?", [$name]);

        if (array_key_exists('tags', $facility)) {
            $tagsToDatabase = $this->createTagsFromFacility($facility['tags'], $name);

            if (!$tagsToDatabase) {
                (new Status\InternalServerError("Something went wrong while trying to save the tags."))->send();
                die;
            }
        }

        (new Status\Ok($facility))->send();
    }

    private function createTagsFromFacility($tags, $facilityName) : bool {
        foreach ($tags as $tag) {
            if (!array_key_exists('tag', $tag)) {
                return false;
            }

            $tagQuery = "INSERT INTO tag (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name";

            $createTagQueryResult = $this->db->executeQuery($tagQuery, [$tag['tag']]);

            if (!$createTagQueryResult) {
                return false;
            }

            $tagFacQuery = "INSERT INTO facilityTag (facility, tag) VALUES (?, ?)";
            $tagFacQueryResult = $this->db->executeQuery($tagFacQuery, [$facilityName, $tag['tag']]);

            if (!$tagFacQueryResult) {
                return false;
            }
        }

        return true;
    }
}