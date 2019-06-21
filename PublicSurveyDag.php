<?php

namespace Stanford\PublicSurveyDag;

include_once "emLoggerTrait.php";

use REDCap;

class PublicSurveyDag extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public $dags;

    /**
     * returns array of [ id => name, ... ]
     */
    public function getDags() {
        $this->dags = REDCap::getGroupNames();
    }


    public function getPublicSurveyUrl() {
        // \Survey::getSur
        // REDCap::getSurveyLink()
    }


}