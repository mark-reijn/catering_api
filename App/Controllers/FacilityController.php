<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\plugins\Db\Db;

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
}