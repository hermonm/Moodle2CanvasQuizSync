<?php
require('../config.php');
require_login();
session_start();
include('config.php');


// Get list of all moodle course ids, CanvasIDs, Course Names and number of assignments for this teacher.
$query="SELECT c.id AS CID, c.idnumber AS canvasID, c.shortname AS coursename,u.lastname,r.name

FROM mdl_course c
JOIN mdl_context ct ON c.id = ct.instanceid
JOIN mdl_role_assignments ra ON ra.contextid = ct.id
JOIN mdl_user u ON u.id = ra.userid
JOIN mdl_role r ON r.id = ra.roleid
WHERE r.name = 'Teacher' AND u.id = '$USER->id' AND c.idnumber!=''";

$result=mysqli_query($conn,$query);
$myrow=mysqli_fetch_array($result);
$rowcount=mysqli_num_rows($result);
$courselist=array();
do
{ //Get Moodle IDs, Canvas IDs and Course Names
  $course=array();
  $CID=$myrow['CID'];
  $canvasID=$myrow['canvasID'];
  $coursename=$myrow['coursename'];
  array_push($course, $CID);
  array_push($course, $canvasID);
  array_push($course, $coursename);

//Determine number of assignments for each course.
  $querycheck="SELECT * FROM mdl_grade_items gi JOIN mdl_course c ON c.id = gi.courseid
  WHERE gi.idnumber!='' AND c.id='$CID' ORDER BY gi.idnumber";
  $resultcheck=mysqli_query($conn,$querycheck);
  $checkcount=mysqli_num_rows($resultcheck);

  if($checkcount>0){array_push($courselist, $course);}//add the course if at least one IDed assignment.

}while($myrow=mysqli_fetch_array($result));
  //Close Database connection
  mysqli_close($conn);

?>
<html>
<head>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<script type="text/javascript">

      function insertText(course)
      {
        var elem = document.getElementById("theDiv");
        elem.innerHTML += "<img src='loading.gif'><br>Syncing "+course+" Grades to Canvas ...";
      }
    </script>

<style>
#mybutton{
    display: block;
    background-color: #255279;
    border: 1px solid black;
    width: 200px;
    height: 20;
    color: white;
    padding: 2px 2px;
    text-decoration: none;
    margin: 0px 0px;
    cursor: pointer;
    font-size: 12px;
    border-radius: 4px;
    outline: none;
    text-align: center;
}
#mybutton:hover {background-color: #3384ff;}

#mybutton:active {
  background-color: #3384ff;
  transform: translateY(2px);
}
h1 {font-size: 25px; text-decoration: underline; color: #255279; font-family: Papyrus;}
li {font-size: 14px;  color: #255279; font-family: Papyrus;}

img {width: 160px; height: 120px;}

</style>

</head>
<body>

<h1>Moodle to Canvas Grade Sync</h1>
<div style="float:left;">
  <!--Downloading Buttons for each Moodle Course-->
  <?php foreach ($courselist as $Course) {?>
  <form action="M2CD.php">
      <input type="hidden" name="CID" value="<?=$Course[0]?>">
      <input type="hidden" name="CvID" value="<?=$Course[1]?>">
      <input type="hidden" name="Course" value="<?=$Course[2]?>">
      <input type="submit" onclick="insertText('<?=$Course[2]?>');" class="mybutton" id="mybutton" value="<?=$Course[2]?>" />
</form>
<?php }

if(count($courselist)==0){?>

  <ul>
    <li>No courses set up yet. Please follow Setup Directions below and come back.</li>
  </ul>

  <?php }?>

</div>
<div style="float:left; text-align: center; color: red; font-size: 18px; font-family: Arial; padding-left: 20px; vertical-align: top;" id="theDiv"></div>





<div style="clear: left;">
<h1>Setup Directions</h1>
<ol>
  <li>Align your Canvas and Moodle courses by entering the Canvas Course ID into the Availability & Canvas Sync section of your Moodle Course Settings.</li>
  <li>Enter IDs into the Canvas ID section of each moodle assignment in your moodle course that you want to sync. (ID can be anything.)</li>
  <li>Setup Grade Categories and weights in PowerSchool and then import them into Canvas. Make sure the weights are the same.</li>
  <li>In Moodle Gradebook Setup, add the same gradebook categories and weights.</li>
</ol>
<h1>Sync Directions</h1>
<ol>
  <li>If you see no course buttons at the top of this page, you need to follow the Setup Directions above.</li>
  <li>Click on the appropriate course button above to sync grades for that course.</li>
  <li>If successful, you will be redirected to the Canvas Gradebook</li>
  <li>You may have to refresh a few times before all grades display.</li>
  <li>You can now sync to PowerSchool (SIS) manually or wait until 1:00 a.m. for the automatic sync.</li>
  <li>Repeat this process whenever you want to sync your Moodle grades to Canvas.</li>
</ol>
</div>
</body>
</html>
