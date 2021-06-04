<?php
///////////////////////////////--CONFIG SECTION--/////////////////////////////////////////

// Canvas Address
$canvasAddress = 'CANVAS INSTRUCTURE ADDRESS HERE';
// Canvas API Token
$canvasToken = 'CANVAS API TOKEN HERE';

//////////////////////////////////////////////////////////////////////////////////////////

// Moodle Address
$moodleAddress = $CFG->wwwroot;

// Connect to Moodle Database
$conn = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
if (!$conn) {
    echo "Error: Unable to connect to MySQL." . PHP_EOL;
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
    exit;
}
?>
