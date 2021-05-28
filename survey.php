<?php
namespace Stanford\PublicSurveyDag;
/** @var \Stanford\PublicSurveyDag\PublicSurveyDag $module */


// GET THE DAG ID FROM THE URL
$dag_id = $module->getDagFromUrl();
if (empty($dag_id)) {
    die("Invalid URL");
}

// MAKE SURE IT IS STILL VALID (DAGS COULD HAVE BEEN REMOVED/DELETED
$dags = $module->getDags();
if (! array_key_exists($dag_id, $dags)) {
    die("Invalid DAG: $dag_id");
}

// CREATE THE NEW RECORD
$id = $module->createRecordFromDag($dag_id);
if (!$id) {
    die("Unable to create a new record in group $dag_id");
}

$params = $module->getAdditionalParamsFromUrl();
$query_string = http_build_query($params);

// REDIRECT TO THE FIRST SURVEY
$url = $module->getFirstSurveyUrl($id);
$url = $url . "&" . $query_string;

redirect($url);
