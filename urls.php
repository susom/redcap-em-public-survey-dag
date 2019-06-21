<?php
namespace Stanford\PublicSurveyDag;
/** @var \Stanford\PublicSurveyDag\PublicSurveyDag $module */


use REDCap;

// GET LIST OF DAGS

$dags = REDCap::getGroupNames();


$module->emDebug($dags);

?>
