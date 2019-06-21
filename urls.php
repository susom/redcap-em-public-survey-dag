<?php
namespace Stanford\PublicSurveyDag;
/** @var \Stanford\PublicSurveyDag\PublicSurveyDag $module */



include_once (APP_PATH_DOCROOT . "ProjectGeneral/header.php");


global $Proj;
if (! $Proj->project['surveys_enabled']) {
    die ("This only works if you have surveys enabled");
}

if (empty($module->getPublicSurveyUrl())) {
    die ("You must have a public survey url for this to work");
}

?>
    <h3>Public Survey Dag URLs</h3>

    <p>
        This module provides a url for each DAG in your project.  When users access these urls, a new record will be
        created and assigned to the corresponding DAG.  The user will then be redirected to the survey URL for this
        new record.
    </p>
    <p>
        Below is a table with public survey equivalent urls for each DAG
    </p>

    <div class="row">
        <div class="ml-3">
            <table id="dags">
                <thead>
                    <tr>
                        <th>DAG ID</th>
                        <th>DAG Name</th>
                        <th>Public Survey URL</th>
                    </tr>
                </thead>
                <tbody>
<?php
    // GET LIST OF DAGS
    $dags = $module->getDags();
    foreach ($dags as $dag_id => $dag_name) { ?>
                    <tr>
                        <td><?php echo $dag_id ?></td>
                        <td><?php echo $dag_name ?></td>
                        <td style="width:100%;"><input value="<?php echo $module->getPublicDagUrl($dag_id) ?>" onclick="this.select();"
                                   readonly="readonly" class="staticInput" style="float:left;width:600px;">
                        </td>
                    </tr>
<?php } ?>
                </tbody>
            </table>
        </div>
    </div>

<script>
    $(document).ready( function() {
        $('#dags').DataTable();
    });
</script>
