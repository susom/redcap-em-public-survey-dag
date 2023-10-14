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

    public function getAdditionalParamsFromUrl() {
        $exclude_keys = ["s", "hash", "page", "event_id", "pid", "pnid", "preview", "id", "sq", "prefix", "NOAUTH", "dag"];
        return array_diff_key($_GET, array_flip($exclude_keys));
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
     * @throws \Exception
     */
    public function createRecordFromDag($dag_id) {
        global $Proj;

        // Find the next new record id according to EM settings
        $id = $this->getNextDagId($dag_id);
        $this->emDebug("New ID", $id);

        $data = array(
            REDCap::getRecordIdField() => $id,
        );

        if ($Proj->longitudinal) {
            $event_name = REDCap::getEventNames(true,true,$Proj->firstEventId);
            $data['redcap_event_name'] = $event_name;
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
        return REDCap::getSurveyLink($id, $Proj->firstForm, $Proj->firstEventId);
    }


    /**
     * Like next ID but prefixes id with the DAG name (e.g. stanford-1) or DAG ID (e.g. 15-1)
     * @param $dag_id
     * @return string
     * @throws \Exception
     */
    function getNextDagId($dag_id) {

        // Determine Prefix
        $prefixDag = $this->getProjectSetting('prefix-record-with-dag');

        $prefix = '';
        $next_id = 0;

        // Load ALL record IDs (Across all arms?)
        global $Proj;
        $first_event_id = $Proj->firstEventId;
        $records = REDCap::getData('array', null, array(REDCap::getRecordIdField()), $first_event_id);

        if ($prefixDag) {
            // We need to first determine the starting point for records with this prefix
            // e.g. Stanford-123

            // See what kind of prefix is configured (either text or dag_id)
            $use_id_instead = $this->getProjectSetting('use-dag-id-instead');
            $prefix = trim( $use_id_instead ? $dag_id : REDCap::getGroupNames(true,$dag_id) ) . "-";

            // Get the length of the dag prefix so we can increment the suffix
            $prefix_len = strlen($prefix);

            $this->emDebug("Prefix $prefix is $prefix_len chars long", REDCap::getGroupNames(true));

            // Load current records to find the current max suffix
            // TODO: add filter to database query for prefix
            foreach ($records as $k => $v) {
                $k_prefix = substr($k, 0, $prefix_len);
                if( strcasecmp($k_prefix, $prefix) == 0 ) {
                    # Case-insensitive match
                    $suffix = substr($k,$prefix_len);
                    if (is_numeric($suffix) && $suffix >= $max_id) {
                        $max_id = intval($suffix);
                    }
                }
            }
        } else {
            // Get current max ID
            foreach ($records as $k => $v) {
                if (is_numeric($k) && $k >= $max_id) {
                    $max_id = intval($k);
                }
            }
        }

        // Reserve the Next_ID
        $loop_count = 1;
        do {
            $max_id++;
            $next_id = $prefix . $max_id;
            $result = REDCap::reserveNewRecordId($this->getProjectId(), $next_id);
            $this->emDebug("Try $loop_count with max of $max_id - reserving $next_id: " . ($result ? "true": "false"));
            // $result = self::reserveNewRecordId($this->getProjectId(), $next_id);
            if ($loop_count++ > 1000) {
                throw new \Exception("Overrun in " . __METHOD__ . " - $loop_count failed attempts");
            }
        } while ($result === false);
        $this->emDebug("Reserved $next_id in $loop_count tries");

        return $next_id;
    }


    /**
     * Cycle through each project and call the autoDelete method
     * @param $cron
     */
    function autoDeleteCron($cron) {
        $enabledProjects = $this->getProjectsWithModuleEnabled();
        //$this->emDebug('EnabledProjects', $enabledProjects);
        foreach($enabledProjects as $pid) {
            $deleteUrl = $this->getUrl("autoDelete.php", true, false) . "&pid=$pid";
            http_get($deleteUrl);
        }
    }


    // /**
    //  * This is a function to reserve a record and ensure it is unique.
    //  * If it returns true, it will 'hold' the reserved new record for 1 hour to be saved to the REDCap data
    //  * table.  If it is 'false' you must try a different record ID.
    //  * @param      $project_id
    //  * @param      $record
    //  * @param null $event_id
    //  * @param null $arm_id
    //  * @return bool
    //  * @throws \Exception
    //  */
    // public static function reserveNewRecordId($project_id, $record, $event_id = null, $arm_id = null)
    // {
    //     // SET $P AS CURRENT PROJECT
    //     global $Proj;
    //     /** @var \Project $P */
    //     $P = (empty($Proj) || $Proj->project_id !== $project_id) ? new \Project($Proj) : $Proj;
    //
    //
    //     // GET ARM_ID AND EVENT_ID IF NOT SUPPLIED
    //     if (empty($arm_id)) {
    //         if (empty($event_id)) {
    //             // Missing both event_id and arm_id -- assume first arm_id
    //             $arm_id = $P->firstArmId;
    //             $event_id = $P->firstEventId;
    //         } else {
    //             // We have an event_id, but not the arm.  Let's retrieve it
    //             foreach ($P->eventInfo as $p_event_id => $p_armDetail) {
    //                 if ($p_event_id == $event_id) {
    //                     $arm_id = $p_armDetail['arm_id'];
    //                     break;
    //                 }
    //             }
    //         }
    //     } else {
    //         // We have the arm
    //         if (empty($event_id)) {
    //             // Get event from arm
    //             $event_id = $P->getFirstEventIdArmId($arm_id);
    //         }
    //     }
    //
    //     if (empty($event_id) || empty($arm_id) || empty($record)) {
    //         throw new \Exception ("Missing required inputs for reserveRecord");
    //     }
    //
    //
    //     // STEP 1: CHECK THE NEW_RECORD_CACHE FIRST FOR HIGH-HIT SCENARIOS
    //     // Is the record in the redcap_new_record_cache
    //     $sql = sprintf("select 1 from redcap_new_record_cache
    //         where project_id = %d and arm_id = %d and record = '%s'",
    //         intval($project_id),
    //         intval($arm_id),
    //         db_escape($record)
    //     );
    //     $q   = db_query($sql);
    //     if (!$q) {
    //         throw new Exception("Unable to query redcap_new_record_cache - check your database connectivity");
    //     }
    //     if (db_num_rows($q) > 0) {
    //         // Already used
    //         return false;
    //     }
    //
    //
    //     // STEP 2: SINCE THE NEW_RECORD_CACHE DOESNT INCLUDE OLDER RECORDS, LETS CHECK THERE TOO:
    //     // Is the record used in the record list or redcap_data
    //     $recordListCacheStatus = \Records::getRecordListCacheStatus($project_id);
    //     ## USE RECORD LIST CACHE (if completed) (requires ARM)
    //     if ($recordListCacheStatus == 'COMPLETE') {
    //         $sql = sprintf("select 1 from redcap_record_list
    //             where project_id = %d and record = '%s' limit 1",
    //             intval($project_id),
    //             db_escape($record)
    //         );
    //     }
    //     ## USE DATA TABLE
    //     else {
    //         $sql = sprintf("select 1 from redcap_data
    //             where project_id = %d and field_name = '%s'
    //             and record regexp '%s' limit 1",
    //             intval($project_id),
    //             db_escape($P->table_pk),
    //             db_escape($record)
    //         );
    //     }
    //     $q = db_query($sql);
    //     if (!$q) {
    //         throw new \Exception("Unable to query redcap_data for $record in project $project_id - check your database connectivity and system logs");
    //     }
    //
    //     if (db_num_rows($q) > 0) {
    //         // Record is used
    //         return false;
    //     }
    //
    //
    //     // STEP 3: LETS TRY TO ADD IT TO THE NEW RECORD CACHE TO ENSURE IT IS STILL UNIQUE
    //     $sql = sprintf("insert into redcap_new_record_cache
    //         (project_id, event_id, arm_id, record, creation_time)
    //         values (%d, %d, %d, '%s', '%s')",
    //         intval($project_id),
    //         intval($event_id),
    //         intval($arm_id),
    //         db_escape($record),
    //         db_escape(NOW)
    //     );
    //     if (db_query($sql)) {
    //         // Success
    //         return true;
    //     } else {
    //         // Duplicate or other error
    //         // TODO: look at error code to differentiate from a lock error vs. another db error
    //         return false;
    //     }
    // }



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
        $this->emDebug("Public Dag auto-delete is enabled for $pid with delay of $delay");

        // Get the name of the logging table
        $log_event_table = method_exists('\REDCap', 'getLogEventTable') ? \REDCap::getLogEventTable($pid) : "redcap_log_event";

        // Query to get all records where there are only group_id or record_id fields for that record in redcap_data
        // along with the time they were last modified.
        $result = $this->query(
            "select
                rd.record,
                sum(
                    if(
                        rd.field_name = '__GROUPID__' OR
                        rd.field_name = ?, 1, 0
                    )
                ) as defaultFields,
                timestampdiff(HOUR, timestamp(max(rle.ts)), ?) as HoursSinceModified,
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
                date('Y-m-d H:i:s'),
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
