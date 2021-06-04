<?php
require('../config.php');
require_login();
include('config.php');
$today = date("Y-m-d");
$due_at = $today.'T00:00:00-06:00';


//Get Moodle Course ID, Canvas ID and Course Name from the selected button.
if (isset($_REQUEST['CID'])) {$CID=$_REQUEST['CID'];}
if (isset($_REQUEST['CvID'])) {$canvasID=$_REQUEST['CvID'];}
if (isset($_REQUEST['Course'])) {$coursetitle=$_REQUEST['Course'];}


/////////////////////////////////
// UPDATING ASSIGNMENTS SECTION
/////////////////////////////////

//Make array from Canvas with Canvas $sectionid-> Moodle $groupname (ex. hermon8)
$sectiondata = json_decode(@file_get_contents($canvasAddress.'/api/v1/courses/'.$canvasID.'/sections?per_page=100&access_token='.$canvasToken));
$mysections = array();
foreach($sectiondata as $section){
$pos = strcspn( $section->name , '0123456789'); //strip position of number out of canvas section name
$mysections[$section->id]=$USER->username.$section->name[$pos];
}


//Create an array from Canvas with username as the key and Canvas student_id as the value to match those from Moodle. This is because sometimes the names in Canvas don't match the names in Moodle
$myCstudents = array();
foreach($mysections as $SID => $groupname){
$studentdata = json_decode(@file_get_contents($canvasAddress.'/api/v1/sections/'.$SID.'/enrollments?type=StudentEnrollment&grouped=true&per_page=100&access_token='.$canvasToken));
foreach($studentdata as $canvas_student)
{$explode_email = explode("@", $canvas_student->user->login_id);
  $username = $explode_email[0];
  $myCstudents[$username]=$canvas_student->user_id;}
}

//Get assignment group (category) IDs from this Canvas Course.
$assignment_groups = json_decode(@file_get_contents($canvasAddress.'/api/v1/courses/'.$canvasID.'/assignment_groups?published=true&per_page=10000&access_token='.$canvasToken));
$agIDs = array();
foreach($assignment_groups as $ag)
{$agIDs[$ag->name]=$ag->id;}

//Get assignment list by ID from Canvas.
$canvas_assignments= json_decode(@file_get_contents($canvasAddress.'/api/v1/courses/'.$canvasID.'/assignments?published=true&per_page=10000&access_token='.$canvasToken));
$c_ass_IDS = array();
foreach($canvas_assignments as $ca)
{$c_ass_IDS[$ca->name]=$ca->id;}


// Cycle through every Moodle Assignment to upload to Canvas.
$queryh="SELECT
  gi.id AS itemid,
  gi.idnumber AS aidnumber,
  gi.itemname AS itemname,
  gi.itemmodule AS module,
  gi.grademax AS itemgrademax,
  cm.id AS id,
  c.id AS cid,
  c.shortname AS coursename,
  gc.fullname AS category,
  gi.hidden AS turnedon
  FROM mdl_grade_items gi
  JOIN mdl_course c ON c.id = gi.courseid
  JOIN mdl_course_modules cm ON cm.instance = gi.iteminstance AND cm.course = gi.courseid
  JOIN mdl_grade_categories gc ON gc.id = gi.categoryid
  WHERE gi.idnumber!='' AND c.id='$CID' AND gi.hidden = 0 ORDER BY aidnumber";

$resulth=mysqli_query($conn,$queryh);
$myrowh=mysqli_fetch_array($resulth);
$c=0;


$url = $canvasAddress.'/api/v1/courses/'.$canvasID.'/assignments';
$curl = curl_init(); //initialize curl call before the loop

do
{
  $id=$myrowh["id"];
  $module=$myrowh["module"];
  $name=$myrowh["itemname"];
  $category=$myrowh["category"];
  $coursename=$myrowh["coursename"];
  $points_possible=$myrowh["itemgrademax"];
  $published=$myrowh["turnedon"];
  if($published==0){$publish=true;}else{$publish=false;}

  $description = "<a href='".$moodleAddress."/mod/".$module."/view.php?id=".$id."' target='_blank' class='btn btn-success'><b>Moodle:</b> ".$name."</a>";

if (!array_key_exists($name, $c_ass_IDS)) {

    $assignment = array("assignment"=>array("points_possible"=>$points_possible, "assignment_group_id"=>$agIDs[$category], "name"=>$name, "published"=>$publish,"due_at"=>$due_at,"post_to_sis"=>true, "description"=>"$description"));

    $data_json = json_encode($assignment);


    /////////////////////////////////////////
    // Post Assignments to Canvas
    /////////////////////////////////////////

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
       'Authorization: Bearer ' . $canvasToken,
       'Content-Type: application/json',
       'Content-Length: ' . strlen($data_json)
    ));
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    if(!$response){
       die("Connection Failure");
    }
$output = json_decode($response,true);
$c_ass_IDS[$output["name"]]=$output["id"]; //Append added assignments to lists
}
$c++;
  }while($myrowh=mysqli_fetch_array($resulth));

  curl_close($curl); //close curl call after the loop


/////////////////////////////////
// UPDATING SCORES SECTION
/////////////////////////////////


//Make an array of all Moodle assignment scores
//g.rawgrade gives the raw grade but g.finalgrade would give a modified grade
$query="SELECT
  u.id AS userid,
  u.idnumber AS idnumber,
  u.username AS username,
  u.email AS email,
  u.firstname AS firstname,
  u.lastname AS lastname,
  gi.id AS itemid,
  gi.idnumber AS aidnumber,
  gi.itemname AS itemname,
  gi.hidden AS turnedon,
  c.shortname AS courseshortname,
  c.idnumber AS cidnumber,
  c.id AS cid,
  gi.grademax AS itemgrademax,
  g.rawgrade AS finalgrade
  FROM mdl_user u
  JOIN mdl_grade_grades g ON g.userid = u.id
  JOIN mdl_grade_items gi ON g.itemid =  gi.id
  JOIN mdl_user_enrolments ue ON ue.userid = u.id
  JOIN mdl_role_assignments ra ON ra.userid = u.id
  JOIN mdl_role r ON r.id = ra.roleid
  JOIN mdl_context cxt ON cxt.id = ra.contextid
  JOIN mdl_enrol e ON e.id = ue.enrolid
  JOIN mdl_course c ON c.id = gi.courseid AND e.courseid = c.id AND c.id = cxt.instanceid AND gi.hidden != 1

  WHERE gi.idnumber!='' AND c.id='$CID' AND cxt.contextlevel = 50 AND ra.roleid = 5";

$result=mysqli_query($conn,$query);
$myrow=mysqli_fetch_array($result);
$rowcountass=mysqli_num_rows($result);
$Moodle_scores=array();
$m_ass_IDS=array();
if($rowcountass){ //Begin if there are grades in moodle
do
{
  $itemgrademax=$myrow["itemgrademax"];
  $itemname=$myrow["itemname"];
  $username=$myrow["username"];
  $finalgrade=$myrow["finalgrade"];
  $item_ID=$myrow["aidnumber"];
  $m_ass_IDS[$item_ID]=$c_ass_IDS[$itemname];

  if (array_key_exists($itemname, $c_ass_IDS)) {//Don't do any of below unless this assignment is in Canvas.

//if the grade is <50% set the grade to half of the point total if turned on for this assignment.
  if($finalgrade/$itemgrademax <.50 && $finalgrade!=NULL && substr($item_ID, -1)=='*'){$finalgrade=$itemgrademax/2;}
  $finalgrade = number_format((float)$finalgrade, 1);


$Moodle_scores[$item_ID.'|'.$myCstudents[$username]] = $finalgrade;

}
  }while($myrow=mysqli_fetch_array($result));

  ksort($Moodle_scores);
  ksort($m_ass_IDS);





  /////////Create JSON for Grade Upload//////////////////////////////////////////////////////
  $AID="0";
  $all_assignments=array();
  foreach($Moodle_scores as $key=>$value) {
  ///////////////fix for Hermon Physics gradesheet only//////////////////////////////////////
    if($CID==2){
    $ID = explode("*", $key);
    $ekey='E'.$ID[0].$ID[1];
    if($Moodle_scores[$key]>=9 && $Moodle_scores[$ekey]>99){$Moodle_scores[$key]+=.5;}}
  ////////////////////////////////////////////////////////////////////////////////////////////

$key_explode = explode("|", $key);
$ass_ID = $m_ass_IDS[$key_explode[0]];
$student_ID = $key_explode[1];

if($AID!=$ass_ID)
{if(count($assignment_array)>0){$all_assignments[$AID]=$assignment_array;}
$assignment_array = array(); $AID=$ass_ID;}
$assignment_array[$student_ID]=array("posted_grade"=>$Moodle_scores[$key]);

}
$all_assignments[$AID]=$assignment_array; //push last asssignment

$upload_grades_array=array("grade_data"=>$all_assignments);
$data_json = json_encode($upload_grades_array);
//print_r($data_json);

/////////////////////////////////////////
// Post Grades to Canvas
/////////////////////////////////////////

$url = $canvasAddress.'/api/v1/courses/'.$canvasID.'/submissions/update_grades';
$curl = curl_init(); //initialize curl call before the loop
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
   'Authorization: Bearer ' . $canvasToken,
   'Content-Type: application/json',
   'Content-Length: ' . strlen($data_json)
));
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($curl, CURLOPT_POSTFIELDS, $data_json);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response2 = curl_exec($curl);
if(!$response2){
   die("Connection Failure");
}
curl_close($curl);

if($response2){
header('Location: '.$canvasAddress.'/courses/'.$canvasID.'/gradebook');
}




}  //End if there are grades in Moodle
mysqli_close($conn);
 ?>
