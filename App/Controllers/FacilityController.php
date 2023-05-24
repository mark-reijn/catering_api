<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\plugins\Db\Db;
use Exception;

/**
 * @property mixed|void $db
 */
class FacilityController extends BaseController {

    /**
     * Getting all facilities, or facilities matching the filter options.
     * Can filter on facility name, location and tag, this needs to be given as body in the HTTP request.
     * @return void
     */
    public function getFacilities() {
        $entityBody = file_get_contents('php://input');
        if ($entityBody) {
            $filterData = json_decode($entityBody, true);
            $this->getFacilitiesByFilter($filterData);
            die;
        }

        $facilityQuery = "SELECT * FROM Facility";
        $results = $this->db->fetchQuery($facilityQuery);

        if (count($results) < 1) {
            (new Status\NoContent())->send();
            die;
        }

        $results = $this->getTagsForFacilities($results);

        (new Status\Ok($results))->send();
    }

    /**
     * Getting a facility by its name.
     * @param $facilityName
     * @return void
     */
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

    /**
     * Creating a new facility.
     * @return void
     */
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

        try {
            $createQueryResult = $this->db->executeQuery($createQuery, [$name, $creationDate, $location]);
        } catch (Exception $e) {
            (new Status\InternalServerError($e))->send();
            die;
        }

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

    /**
     * Updating a facility.
     * @param $facilityName
     * @return void
     */
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
            $updateQueryResult = $this->db->executeQuery($updateQuery, [$name, $creationDate, $location, $name]);
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

    /**
     * Deleting a facility by name.
     * @param $facilityName
     * @return void
     */
    public function deleteFacility($facilityName) {
        $deleteQuery = "DELETE FROM facility WHERE name = ?";
        $deleteQueryResult = $this->db->executeQuery($deleteQuery, [$facilityName]);

        if (!$deleteQueryResult) {
            (new Status\InternalServerError("Something went wrong while trying to delete the facility."))->send();
            die;
        }

        (new Status\Ok())->send();
    }

    /**
     * Getting facilities matching the filters.
     * @param $filterData
     * @return void
     */
    private function getFacilitiesByFilter($filterData) {
        $name = array_key_exists('name', $filterData) ? $filterData['name'] : '';
        $location = array_key_exists('location', $filterData) ? $filterData['location'] : '';
        $tag = array_key_exists('tag', $filterData) ? $filterData['tag'] : '';

        $filterQuery = "SELECT DISTINCT F.name, F.creation_date, F.location FROM facility AS F INNER JOIN facilitytag AS FT on F.name = FT.facility WHERE F.name LIKE ? AND F.location LIKE ? AND FT.tag LIKE ?;";
        $filterQueryResult = $this->db->fetchQuery($filterQuery, [
            "%".$name."%",
            "%".$location."%",
            "%".$tag."%"
            ]);

        $filterQueryResult = $this->getTagsForFacilities($filterQueryResult);
        (new Status\Ok($filterQueryResult))->send();
    }

    /**
     * Creating tags for facilities
     * @param $tags
     * @param $facilityName
     * @return bool
     */
    private function createTagsFromFacility($tags, $facilityName) : bool {
        foreach ($tags as $tag) {
            if (!array_key_exists('tag', $tag)) {
                return false;
            }

            $tagQuery = "INSERT INTO tag (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name";

            try {
                $createTagQueryResult = $this->db->executeQuery($tagQuery, [$tag['tag']]);
            } catch (Exception $e) {
                (new Status\InternalServerError($e))->send();
                die;
            }

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

    /**
     * Getting the tags for the facilities
     * @param $facilities
     * @return array
     */
    private function getTagsForFacilities($facilities) : array {
        for ($i = 0; $i < count($facilities); $i++) {
            $facility = $facilities[$i];

            $tagQuery = "SELECT tag FROM facilitytag WHERE facility = ?";
            $tagQueryResult = $this->db->fetchQuery($tagQuery, [$facility['name']]);
            $facility['tags'] = $tagQueryResult;
            $facilities[$i] = $facility;
        }

        return $facilities;
    }
}