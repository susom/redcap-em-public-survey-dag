<?php
namespace Stanford\PublicSurveyDag;
/** @var \Stanford\PublicSurveyDag\PublicSurveyDag $module */



include_once (APP_PATH_DOCROOT . "ProjectGeneral/header.php");


global $Proj;
$survey_enabled = $Proj->project['surveys_enabled'];
if (! $survey_enabled) {
    die ("This only works if you have surveys enabled");
}

$url_exists = $module->getPublicSurveyUrl();
if (empty($url_exists)) {
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
    $csvOutput = [];
    foreach ($dags as $dag_id => $dag_name) {
        $dagUrl = $module->getPublicDagUrl($dag_id);
        $csvOutput[] = [
            "dag_id" => $dag_id,
            "dag_name" => $dag_name,
            "dag_url" => $dag_url
        ];

        ?>
                    <tr>
                        <td><?php echo $dag_id ?></td>
                        <td><?php echo $dag_name ?></td>
                        <td style="width:100%;"><input value="<?php echo $dagUrl ?>" onclick="this.select();"
                                   readonly="readonly" class="staticInput" style="float:left;width:600px;">
                        </td>
                    </tr>
<?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <pre>
            <?php
                foreach ($csvOutput as $row) {
                    echo $row['dag_id'] . ", " . $row['dag_name'] . ", " . $row['dag_url'] . "\n";
                }
            ?>
        </pre>
    </div>
<script>
    $(document).ready( function() {
        $('#dags').DataTable();
    });
</script>
