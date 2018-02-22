<?php
/*
* Script designed to increment through old logs for export to LL
*
*
* author: David Kane, 10th November 2016
* dkane@wit.ie
*/



// INITIAL VARIABLES //
$logfile = "./LogstoreLegacyMigrate.log";  //stores only the last as JSON.
$moodlepath = "/var/www/moodle/";
$increment = 7;
$lastValue = 0; // the last value of the database
$count = 10;  // the number of queries that we want to execute in the loop (a value of zero means that there is no limit);
$output = array(
    "lastValue" => 0,
    "increment" => $increment,
    "loop" => 0
);
$sourceTable = "mdl_logstore_subset_log";
$destinationTable = "mdl_logstore_xapi_log";
$DEBUGGING = TRUE;
$host = 'localhost';
$user = 'yourMYSQLusername';
$password = 'yourMYSQLpassword';
$db = 'moodleDatabase';
if($DEBUGGING){
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

        <!-- jQuery library -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>

        <!-- Latest compiled JavaScript -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    </head>
    <body>
    <div class="container-fluid">
    <div class="page-header">
        <h1>Legacy xAPI Load <small>This is normally run at the command line.  Currently running in debug mode.</small></h1>
    </div>
    <div class="row">
        <div class="col-md-8">


            <div class="panel panel-primary">
                <div class="panel-heading">
                    About this script
                </div>
                <div class="panel-body">
                    <div class="alert alert-warning" role="alert"><strong>! NOTE:</strong> <br/>This HTML guff disappears when the debug variable is set to false.  However, the script works the same with or without the debug variable being set.  It is up to you to delete the records from the LRS yourself, maybe use a tool like <a href="https://robomongo.org/" target="_blank">Robomongo</a>, if you have exported them in error.  Otherwise you might like to modify this script.</div>
                    <p>New users of xAPI, who have just installed the Moodle Logstore xAPI plugin are faced with the problem of extracting old data and converting it to xAPI statements in the learning record store.</p>
                    <p>This script is designed to take existing log data from a moodle installation, and import it &mdash; via the Logstore xAPI Moodle plugin &mdash; to the learning record store.</p>
                    <p>It does this by paging the <em>logstore_standard_log</em>, transferring the records to a temporary database table (of the same structure) and running export commands on the data stored there.  This is what it does normally, except the <em>logstore_standard_log</em> is only parsed for the last <em>n</em> records, where <em>n</em> is set in the settngs.</p>
                    <h2>Command line tasks executed</h2>
                    <pre># in directory admin/tool/task/cli/

php schedule_task.php --execute=<span style="background-color: #BFA3BA">\\logstore_xapi\\task\\<span style="background-color: #F2CEEC">emit_task</span></span>

php schedule_task.php --execute=<span style="background-color: #A3BFBF">\\logstore_legacy\\task\\<span style="background-color: #CEF2F2">cleanup_task</span></span>

php schedule_task.php --execute=<span style="background-color: #A3BFA3">\\logstore_standard\\task\\<span style="background-color: #CEF2CE">cleanup_task</span></span>
</pre>
                    <p>The data is also logged to a file called<em><?php echo($logfile); ?></em>.  This file is overwritten with the data from each loop and is never very long.  It is also stored as JSON, which struck me as a good idea at the time.</p>
                </div>

            </div><!-- end panel -->
        </div><!-- end column -->

        <div class="col-md-4">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    INITIAL VARIABLES
                </div>

                <div class="panel-body">
                    <table class="table table-striped">
                        <tr><th>increment</th><td><?php echo($increment);?></td></tr>
                        <tr><th>lastValue</th><td><?php echo($lastValue);?></td></tr>
                        <tr><th>moodlepath</th><td><?php echo($moodlepath);?></td></tr>
                        <tr><th>logfile</th><td><?php echo($logfile);?></td></tr>
                        <tr><th>Source DB Table (normally <em>mdl_logstore_standard_log)</em> </th><td><?php echo($sourceTable);?></td></tr>
                        <tr><th>Source DB Table (normally <em>mdl_logstore_xapi_log)</em> </th><td><?php echo($destinationTable);?></td></tr>
                        <tr><th>Debug</th><td>TRUE</td></tr>
                        <tr><th>Author</th><td>David Kane, dkane@wit.ie, Nov 2016</td></tr>
                    </table>
                </div></div>
        </div><!-- end column -->
    </div><!-- end row -->
    <div class="row">
    <div class="col-md-12">
    <div class="panel panel-info">
    <div class="panel-heading">The Loop</div>
    <div class="panel-body">
    <table class="table table-striped">
    <tr>
        <th>Iteration (count)</th>
        <th>SQL Query</th>
        <th>Affected Rows</th>
        <th>Last Insert ID</th>
        <th>Task Outputs</th>
    </tr>
    <?php
}
for($i = 0; $i < $count; $i++){
    if($DEBUGGING){
        echo "\t\t\t<tr>\n";
        echo "\t\t\t\t<td>$i of $count</td>";
    }
    $conn = new mysqli($host, $user, $password, $db);
    if ($conn->connect_error) {
        trigger_error('Database connection failed: '  . $conn->connect_error, E_USER_ERROR);
    }
    $sql = "insert into mdl_logstore_xapi_log (select * from mdl_logstore_subset_log where id > " . $lastValue . " order by id limit " . $increment . ");";
    if($DEBUGGING){echo "\t\t\t\t<td>$sql</td>\n";}

    if($conn->query($sql) === false) {
        trigger_error('Wrong SQL: ' . $sql . ' Error: ' . $conn->error, E_USER_ERROR);
        break;
    } else {
        $lastValue = $conn->insert_id;
        $affected_rows = $conn->affected_rows;
        $output["lastValue"] = $lastValue;
        $output["loop"] = $i;
        if($DEBUGGING){
            echo "\t\t<td>$affected_rows</td>\n";
            print "\t\t<td>$lastValue</td>\n";
        }
        //echo "executing commands";
        exec("php ".$moodlepath."admin/tool/task/cli/schedule_task.php --execute=\\\\logstore_xapi\\\\task\\\\emit_task ", $output["emit"]);
        exec("php ".$moodlepath."admin/tool/task/cli/schedule_task.php --execute=\\\\logstore_legacy\\\\task\\\\cleanup_task", $output["cleanupLegacy"]);
        exec("php ".$moodlepath."admin/tool/task/cli/schedule_task.php --execute=\\\\logstore_standard\\\\task\\\\cleanup_task", $output["cleanupStandard"]);
        if($DEBUGGING){
            // PRINT OUT THE VARIABLES, IF DEBUGGING
            echo "<td><span style='background-color: #F2CEEC'>";
            foreach ($output["emit"] as $key => $value) {
                echo "$value <br/>";
            }
            echo "</span><span style='background-color: #CEF2F2'>";
            foreach ($output["cleanupLegacy"] as $key => $value) {
                echo "$value <br/>";
            }
            echo "</span><span style='background-color: #CEF2CE'>";
            foreach ($output["cleanupStandard"] as $key => $value) {
                echo "$value <br/>";
            }
            echo "</span></td>";
        }
        unset($output["emit"]);
        unset($output["cleanupLegacy"]);
        unset($output["cleanupStandard"]);

        $log = fopen($logfile, 'w');
        fwrite($log, json_encode($output));
        fwrite($log, "\n");
        fclose($log);

        if($affected_rows < $increment){
            // no more rows left - time to leave the loop.
            if($DEBUGGING){echo "\t</tr>\n\t<tr>\n\t\t<td colspan='5'><em>Breaking out of loop at \$count = $i.</em></td>\n\t</tr>\n";}
            break;
        }
    }
    if($DEBUGGING){echo "\t</tr>\n";}
}

if($DEBUGGING){
    ?>
    </table>
    </div><!-- end panel body -->
    <div class="panel-footer">End of Loop</div>
    </div><!-- end panel -->

    </div>
    </div>

    </div>
    </body>
    </html>
    <?php
}
exit;