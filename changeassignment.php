<?php
/**
 * Changes assignment for a student or a group
 * 
 * @author  Sonja Milicic
 * @author  Enola Knežević
 * @package assignmentdistrib
 **/



//echo "<pre>";
//
//print_r($_GET);
//print_r($_POST);
//die();
 
 
    require_once("../../config.php");
    require_once("lib.php");
    require_once("../../message/lib.php");
    
    $id = optional_param('id', 0, PARAM_INT); 
    if ($id) {
        if (! $cm = get_record("course_modules", "id", $id)) {
            error("Course Module ID was incorrect");
        }
    
        if (! $course = get_record("course", "id", $cm->course)) {
            error("Course is misconfigured");
        }
    
        if (! $assignmentdistrib = get_record("assignmentdistrib", "id", $cm->instance)) {
            error("Course module is incorrect");
        }

    } else {
        if (! $assignmentdistrib = get_record("assignmentdistrib", "id", $a)) {
            error("Course module is incorrect");
        }
        if (! $course = get_record("course", "id", $assignmentdistrib->course)) {
            error("Course is misconfigured");
        }
        if (! $cm = get_coursemodule_from_instance("assignmentdistrib", $assignmentdistrib->id, $course->id)) {
            error("Course Module ID was incorrect");
        }
    }
    print_header("", "", "", '', '', true, "", "");
    
    require_login($course->id);
    
    $groupid = optional_param('groupid', -1, PARAM_INT);
    $studentid = optional_param('studentid', -1, PARAM_INT);
    
    if ($studentid != -1) {
        $worktype = 0;
        if(!isstudent($course->id, $studentid) ) {
            error('User is not student in selected course');
        }
    }
    else if ($groupid != -1){
        $worktype = 1;
    }
    else {
        error('Insufficient parameters');
    }
    
    if(!isteacher($course->id, $USER->id, true, true)) {
        error('Access denied');
        die();
    }
    
    //get all assignments
    $assignments = get_records_sql ("SELECT *, 
                                        (SELECT COUNT(DISTINCT assignmentdistrib_groups_id) FROM {$CFG->prefix}assignmentdistrib_submissions
                                                WHERE assignmentdistrib_groups_id IS NOT NULL AND
                                                    {$CFG->prefix}assignmentdistrib_submissions.assignmentdistrib_assignments_id = {$CFG->prefix}assignmentdistrib_assignments.id

                                        )
                                            +
            
                                        (SELECT COUNT(*) FROM {$CFG->prefix}assignmentdistrib_submissions
                                                WHERE assignmentdistrib_groups_id IS NULL AND
                                                    {$CFG->prefix}assignmentdistrib_submissions.assignmentdistrib_assignments_id = {$CFG->prefix}assignmentdistrib_assignments.id
                                                    
                                        ) AS assigned_count

                                        FROM {$CFG->prefix}assignmentdistrib_assignments
                                        WHERE assignmentdistribid = $cm->instance");
//      print_object($assignments);


    
    //get student or group's old assignment
    if ($worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
        $aidq = get_records_sql("SELECT assignmentdistrib_assignments_id
                                    FROM {$CFG->prefix}assignmentdistrib_submissions
                                        JOIN {$CFG->prefix}assignmentdistrib_assignments
                                            ON assignmentdistrib_assignments_id = {$CFG->prefix}assignmentdistrib_assignments.id
                                    WHERE userid = $studentid
                                        AND assignmentdistribid = {$assignmentdistrib->id}");
    } else {
        $aidq = get_records_sql("SELECT DISTINCT assignmentdistrib_assignments_id
                                    FROM {$CFG->prefix}assignmentdistrib_submissions
                                        JOIN {$CFG->prefix}assignmentdistrib_assignments
                                            ON assignmentdistrib_assignments_id = {$CFG->prefix}assignmentdistrib_assignments.id
                                    WHERE assignmentdistrib_groups_id = $groupid
                                        AND assignmentdistribid = {$assignmentdistrib->id}");
    }
    if(count($aidq) == 0) {
        error('Assignment is not found');
        die();
    }
    if(count($aidq) > 1) {
        error('Not all students in the group have the same assignment assigned to them.');
    }
    foreach($aidq as $item) {
        $aid = $item->assignmentdistrib_assignments_id;
        break;
    }

    //save changes
    $change_assign = optional_param('changeassign', false, PARAM_BOOL);
    $delete_assign = optional_param('deleteassign', false, PARAM_BOOL);
    $done = false;

    if ($worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL){
        $graded = get_records_sql("SELECT * FROM {$CFG->prefix}assignmentdistrib_submissions
                                WHERE userid = $studentid AND assignmentdistrib_assignments_id=$aid AND grade!=-1");
    }
    else {
        $gradedgroup = get_records_sql("SELECT * FROM {$CFG->prefix}assignmentdistrib_groups
                                WHERE id = $groupid AND grade!=-1");
        $graded = get_records_sql("SELECT * FROM {$CFG->prefix}assignmentdistrib_submissions
                                WHERE assignmentdistrib_groups_id = $groupid AND assignmentdistrib_assignments_id=$aid AND grade!=-1");
    }
   
    //print_object($gradedgroup);

    if ($graded || (isset($gradedgroup)&&$gradedgroup))
    {
        ?>
        <p align="center">
            <br>
            The assignment cannot be changed because it has already been graded.
            <br><br>
            <input type="button" value="Close" onclick="window.close();">
            <br>
            <br>
             
        </p>
        <?php
        die();
    }
//    print_object($graded);
//    die();



    if ($change_assign){
        if ($worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
            $subid_all = get_records_sql("SELECT id FROM {$CFG->prefix}assignmentdistrib_submissions
                                WHERE userid = $studentid AND assignmentdistrib_assignments_id=$aid");
        } else {
            $subid_all = get_records_sql("SELECT id, userid FROM {$CFG->prefix}assignmentdistrib_submissions
                                WHERE assignmentdistrib_groups_id = $groupid");
        }
        
        foreach($subid_all as $subid) {
            if($worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
                $assign_id = required_param('assignment', PARAM_INT);
                $updaterecord = new object();
                $updaterecord->id = $subid->id;
                $updaterecord->userid = $studentid;
                $updaterecord->assignmentdistrib_assignments_id = $assign_id;
                if(!update_record('assignmentdistrib_submissions', $updaterecord)) {
                    error('Update failed');
                }
                else {
                    $done = true;
                    $message = get_string('course', 'assignmentdistrib') . ": $course->fullname\n" .
                                get_string('assignmentdistribution', 'assignmentdistrib') . ": $assignmentdistrib->name\n" .
                                get_string('changedassignmentmessage', 'assignmentdistrib');
                    message_post_message(get_teacher($course->id), get_complete_user_data('id', $studentid), $message, FORMAT_PLAIN, 'direct');
                }
                //break;
            } else {
                $assign_id = required_param('assignment', PARAM_INT);
                $updaterecord = new object();
                $updaterecord->id = $subid->id; 
                //$updaterecord->assignmentdistrib_groups_id = $groupid;
                $updaterecord->assignmentdistrib_assignments_id = $assign_id;
                if(!update_record('assignmentdistrib_submissions', $updaterecord)) {
                    error('Update failed');
                }
                else {
                    $done = true;
                    $message = get_string('course', 'assignmentdistrib') . ": $course->fullname\n" .
                                get_string('assignmentdistribution', 'assignmentdistrib') . ": $assignmentdistrib->name\n" .
                                get_string('changedassignmentmessage', 'assignmentdistrib');
                    message_post_message(get_teacher($course->id), get_complete_user_data('id', $subid->userid), $message, FORMAT_PLAIN, 'direct');
//                    message_post_message(
//                            get_teacher($course->id),
//                            get_complete_user_data('id', $subid->userid),
//                            $message,
//                            FORMAT_PLAIN,
//                            'direct'
//                    );
                }
                //break;
            }
        }        
    }
    else if ($delete_assign){
        
        if($worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
            //print_object($assignmentdistrib->name);
               
                //print_object(get_complete_user_data('id', $studentid));
                
            if(!delete_records('assignmentdistrib_submissions', 'userid', $studentid, 'assignmentdistrib_assignments_id', $aid)) {
                error('Unassignment failed');
            }
            else {
                $done = true;
                 $message = get_string('course', 'assignmentdistrib') . ": $course->fullname\n" .
                                get_string('assignmentdistribution', 'assignmentdistrib') . ": $assignmentdistrib->name\n" .
                                get_string('unassignedmessage', 'assignmentdistrib');
                    message_post_message(get_teacher($course->id), get_complete_user_data('id', $studentid), $message, FORMAT_PLAIN, 'direct');
            }
            
        }
        else
        {
            $subid_all = get_records_sql("SELECT id, userid FROM {$CFG->prefix}assignmentdistrib_submissions
                         WHERE assignmentdistrib_groups_id = $groupid");
            
            if(!delete_records('assignmentdistrib_submissions', 'assignmentdistrib_groups_id', $groupid)) {
                error('Unassignment failed');
            }


            else
            {
                if (!delete_records('assignmentdistrib_groups', 'id', $groupid)) {
                    error('Unassignment partially successful');
                }
                foreach($subid_all as $subid)
                {
                     $message = get_string('course', 'assignmentdistrib') . ": $course->fullname\n" .
                                get_string('assignmentdistribution', 'assignmentdistrib') . ": $assignmentdistrib->name\n" .
                                get_string('unassignedmessage', 'assignmentdistrib');
                    message_post_message(get_teacher($course->id), get_complete_user_data('id', $subid->userid), $message, FORMAT_PLAIN, 'direct');
//                    message_post_message(
//                            get_teacher($course->id),
//                            get_complete_user_data('id', $subid->userid),
//                            "Na tečaju $course->fullname, assignment distributionu $assignmentdistrib->name poništena vam je dodjela zadatka i rasformirana grupa.",
//                            FORMAT_PLAIN,
//                            'direct'
//                    );
                }
                $done = true;
            }
            
        }


    }
?>
<head>
<script language="javascript">
var current_assignment = <?php echo $aid; ?>;

function change() {
    if (document.getElementById("assignment") == null) {
        return;
    }
    var assign_id = document.getElementById("assignment").value;
    if(assign_id != current_assignment) {
        var assign_old = document.getElementById('text_'+current_assignment);
        var assign_new = document.getElementById('text_'+assign_id);
        
        assign_old.style.display = 'none';
        assign_new.style.display = 'block';
        
        current_assignment = assign_id;
    }
}

function check_submit() {
    var select = document.getElementById('assignment');
//    console.log(select);
    var option_all = document.getElementsByTagName('option');
    var i;
    for (i=0;i<option_all.length;i++)
    {
        var option = option_all[i];
//            console.log(option);
        var option_aid = parseInt(option.getAttribute('value'));
        if (option.selected){
            if (option_aid==<?php echo $aid; ?>){
//                    console.log(option.value);
                return true;
            }

            var count = parseInt(option.getAttribute('data-count'));
            var max = parseInt(option.getAttribute('data-max'));
            if (max!=0){
                if (count+1 > max){
                    var ans=confirm('This assignment will be assigned to more students than its maximum number of repeats allows. \n\nAre you sure?');
                    if (!ans){
                        return false;
                    }
                }

            }
        }
    }
  
    return true;
}

</script>
</head>
<body onload="change()" onclose="close()">
<?php
if (!$done){
    ?>
    <form method="post">
        <table cellspacing="0" cellpadding="0" border="0" class="generaltable" width="100%">
            <tr>
                <td colspan="2">
                    
                    <?php
                     if($worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
                        $student = get_complete_user_data('id', $studentid);
                       
                     }
                     else{
                         $groupleader = get_record_sql("SELECT leaderuserid FROM {$CFG->prefix}assignmentdistrib_groups
                                WHERE id = $groupid");
//                         print_object($groupleader);
                         $student = get_complete_user_data('id', $groupleader->leaderuserid);
                     }
                      p($student->firstname.' '.$student->lastname);

                    ?>
                    <br>&nbsp;
                </td>
                <td width="25%" style="padding:2px 2px 10px 2px" align="right">
                    <input type="submit" name="deleteassign" value="Unassign" />
                </td>
            </tr>
            <tr>
                <td width="25%" style="padding:2px 2px 10px 2px"><strong>Assignment:</strong></td>
                <td width="50%" style="padding:2px 2px 10px 2px">
                    <select id="assignment" name="assignment" style="width:200px" onChange="change()">";
                        <?php
                        foreach ($assignments as $assignment) {
                            if ($assignmentdistrib->distributiontype == ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSENOREPEAT){
                                $assignment->maxnumberofrepeats = 1;
                            }
                            if ($assignment->approved)
                            {
                                if($assignment->id == $aid) {
                                    echo "<option value=\"".$assignment->id."\" data-count=\"{$assignment->assigned_count}\" data-max=\"{$assignment->maxnumberofrepeats}\" selected=\"\">".$assignment->name." (current assignment)</option>";

                                } else {
                                    echo "<option value=\"".$assignment->id."\"  data-count=\"{$assignment->assigned_count}\" data-max=\"{$assignment->maxnumberofrepeats}\">".$assignment->name."</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                </td>
                <td width="25%" style="padding:2px 2px 10px 2px" align="right">
                    <input type="submit" name="changeassign" value="Change assignment" onclick="return check_submit();"/>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="padding:5px 2px 2px 2px; border-top:1px gray solid">
                    <strong>Assignment text:</strong><br>
                    <div style="padding:5px;" name="assign_text" id="assign_text">
                        <?php
                        foreach ($assignments as $assignment) {
                            $style = ($assignment->id == $aid) ? 'block' : 'none';
                            echo "<div  style='display: $style' id='text_{$assignment->id}' >";
                            echo "<div style='font-weight: bold; display: none;'>".s($assignment->name)."</div>";
                            echo $assignment->description;
                            echo "</div>";
                        }
                        ?>
                    </div>
                </td>
            </tr>
        </table>
    </form>
    <br>
    <input type="button" value="Cancel" onclick="window.close();">
    <?php
}
else {
    ?>
	<p align="center">
        <br>
        Update successful.
        <br><br>
        <input type="button" value="Close" onclick="window.close();">
        <br>
        <br>
         <input type="button" value="Close and refresh parent window" onclick="window.opener.location.reload(); window.close();">
    </p>
	<?php
}
?>
</body>
