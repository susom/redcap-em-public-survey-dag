<?php
namespace Stanford\PublicSurveyDag;

include_once "emLoggerTrait.php";

require_once APP_PATH_DOCROOT . "Surveys/survey_functions.php";

use REDCap;
use Survey;

class PublicSurveyDag extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public $dags;

    /**
     * returns array of [ id => name, ... ]
     */
    public function getDags() {
        $this->dags = REDCap::getGroupNames();
        return $this->dags;
    }

    public function getPublicDagUrl($dag_id) {
        $use_api_urls = $this->getSystemSetting("use-api-urls");
        $base = $this->getUrl("survey.php",true, $use_api_urls);
        $encoded = urlencode(base64_encode($dag_id));
        return $base . "&dag=" . $encoded;
    }


    public function getDagFromUrl() {
        $encoded = $_GET['dag'];
        $dag_id = urldecode(base64_decode($encoded));
        return $dag_id;
    }

    public function getPublicSurveyUrl() {
        global $Proj;
        $survey_id = $Proj->firstFormSurveyId;
        $event_id = $Proj->firstEventId;
        $hash = Survey::getSurveyHash($survey_id, $event_id);

        $public_url = APP_PATH_SURVEY_FULL . "?s=$hash";

        return $public_url;
    }


    /**
     * Make a new record and assign it to the DAG
     * @param $dag_id
     * @return bool|int|string
     */
    public function createRecordFromDag($dag_id) {
        global $Proj;

        // Create a new record
        $prefixDag = $this->getProjectSetting('prefix-record-with-dag');

        $id = $prefixDag ? $this->getNextDagId($dag_id) : $this->getNextId();

        $this->emDebug("New ID", $prefixDag, $id);

        $data = array(
            REDCap::getRecordIdField() => $id,
        );

        if ($Proj->longitudinal) {
            $data['redcap_event_name'] = $Proj->firstEventId;
        }

        $result = REDCap::saveData('json', json_encode(array($data)), 'normal', 'YMD', 'flat', $dag_id);

        if (empty($result['errors'])) {
            return $id;
        } else {
            $this->emError($result);
            return false;
        }
    }


    /**
     * Used to return the survey url
     * @param $id
     * @return string|null
     */
    public function getFirstSurveyUrl($id) {
        global $Proj;
        // $firstFormSurveyId = $Proj->firstFormSurveyId;
        return REDCap::getSurveyLink($id, $Proj->firstForm,$Proj->firstEventId);
    }


    /**
     * Like next ID but prefixes id with the DAG name (e.g. stanford-1)
     * @param $dag_id
     * @return string
     */
    function getNextDagId($dag_id) {
        $dagName = REDCap::getGroupNames(true,$dag_id) . "-";
        $dagLen = strlen($dagName);

        $records = REDCap::getData('array', null, array(REDCap::getRecordIdField()));
        $next_id = 1;
        foreach ($records as $k => $v) {
            if( substr($k, 0, $dagLen) === $dagName ) {
                $suffix = substr($k,$dagLen);
                if (is_numeric($suffix) && $suffix >= $next_id) {
                    $next_id = $suffix + 1;
                }
            }
        }
        return $dagName . $next_id;
    }


    /**
     * Get the next record id
     * @return int|string
     */
    function getNextId() {
        $records = REDCap::getData('array', null, array(REDCap::getRecordIdField()));
        $next_id = 1;
        foreach ($records as $k => $v) {
            if (is_numeric($k) && $k >= $next_id) {
                $next_id = $k + 1;
            }
        }
        return $next_id;
    }



}
