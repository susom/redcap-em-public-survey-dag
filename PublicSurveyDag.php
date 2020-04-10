<?php
namespace Stanford\PublicSurveyDag;

include_once "emLoggerTrait.php";

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
     * Like next ID but prefixes id with the DAG name (e.g. stanford-1) or DAG ID (e.g. 15-1)
     * @param $dag_id
     * @return string
     */
    function getNextDagId($dag_id) {

        $use_id_instead = $this->getProjectSetting('use-dag-id-instead');

        $dagName = ( $use_id_instead ? $dag_id : REDCap::getGroupNames(true,$dag_id) ) . "-";
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


    /**
     * Cycle through each project and call the autoDelete method
     * @param $cron
     */
    function autoDeleteCron($cron) {
        $enabledProjects = $this->framework->getProjectsWithModuleEnabled();
        //$this->emDebug('EnabledProjects', $enabledProjects);
        foreach($enabledProjects as $pid) {
            $deleteUrl = $this->getUrl("autoDelete.php", true, false) . "&pid=$pid";
            http_get($deleteUrl);
        }
    }


    /**
     * Check to see if this project has auto-delete enabled, if so, delete records
     * This can only be called in project context
     * @param $pid  Project ID
     * @throws \Exception
     */
    function autoDelete() {
        // Get PID
        $pid = $this->getProjectId();

        // Make sure we are in project context
        if (empty($pid)) {
            $this->emDebug("Method should only be called in project_context");
            return;
        }
        global $Proj;

        // Check if auto-delete is enabled for this PID
        $delay = $this->getProjectSetting('auto-delete-delay', $pid);

        if (empty($delay)) {
            // Do nothing for this project
            return;
        } elseif(intval($delay) != $delay) {
            $this->emDebug("Invalid auto-delete setting for project $pid:", $delay);
            return;
        }
        $this->emDebug("Public Dag autodelete is enabled for $pid with delay of $delay");

        // Get the name of the logging table
        $log_event_table = method_exists('\REDCap', 'getLogEventTable') ? \REDCap::getLogEventTable($pid) : "redcap_log_event";

        // Query to get all records where there are only group_id or record_id fields for that record in redcap_data
        // along with the time they were last modified.
        $result = $this->framework->query(
            "select
                rd.record,
                sum(
                    if(
                        rd.field_name = '__GROUPID__' OR
                        rd.field_name = ?, 1, 0
                    )
                ) as defaultFields,
                timestampdiff(HOUR, timestamp(max(rle.ts)), now()) as HoursSinceModified,
                count(*) as allFields
            from
                redcap_data rd
                join " . $log_event_table . " rle on rle.project_id = rd.project_id and rle.pk = rd.record
            where
                rd.project_id = ?
            group by
                rd.record
            having
                defaultFields = allFields
                and HoursSinceModified > ?
            ",
            [
                $Proj->table_pk,
                $pid,
                $delay
            ]
        );

        $records = [];
        while($row = $result->fetch_assoc()) {
            $this->emDebug("Row", $row);
            $record = $row['record'];
            if (empty($record)) continue;

            $records[]=$record;
            $this->emDebug("Deleting $record");
            \Records::deleteRecord(
                $record,
                $Proj->table_pk,
                $Proj->multiple_arms,
                $Proj->project_id['randomization'],
                $Proj->project['status'],
                $Proj->project['require_change_reason'],
                $Proj->firstArmId,
                " (" . $this->getModuleName() . ")"
            );
        }

        $count = count($records);
        if ($count > 0) {
            $msg = $this->getModuleName() . " auto-deleted $count incomplete record" . ($count > 1 ? "s" : "") .
                " from project $pid: " . implode(",", $records);
            \REDCap::logEvent($pid, $msg);
            $this->emDebug($msg);
        }
    }
}
