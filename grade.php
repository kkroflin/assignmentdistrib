<?php
/**
 * This page prints a particular instance of assignmentdistrib
 * 
 * @author  Krešimir Kroflin
 * @author  Enola Knežević
 * @package assignmentdistrib
 **/

    require_once("../../config.php");
    require_once("lib.php");
    require_once($CFG->libdir.'/gradelib.php');

    $id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
    $a  = optional_param('a', 0, PARAM_INT);  // assignmentdistrib ID

    if ($id) {
        if (! $cm = get_coursemodule_from_id('assignmentdistrib', $id)) {
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
    require_login($course->id);
    
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    $groupid = optional_param('groupid', -1, PARAM_INT);
    $studentid = optional_param('studentid', -1, PARAM_INT);
    $showcomment = optional_param('comment', false, PARAM_BOOL);
    $grading = optional_param('grading', false, PARAM_BOOL);
        
    $error_flag = false;
    if($groupid == -1 && $studentid == -1) {
        redirect("view.php?id=$id&tab=students", "", 10);
    } else if($studentid != -1) {
        $worktype = 0;
        
        /*
        if(!has_capability('mod/assignmentdistrib:submit', $context, $studentid)) {
        */
        
        if(!isstudent($course->id, $studentid) ) {
            error('User is not student in selected course');
        }
        
        $s = get_record_sql("SELECT submissions.*
                                FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                    JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignments
                                        ON assignments.id = submissions.assignmentdistrib_assignments_id
                                WHERE userid = $studentid
                                    AND assignments.assignmentdistribid = $cm->instance");
        //get grade from gradebook
        $grade_info = grade_get_grades($course->id, 'mod', 'assignmentdistrib', $cm->instance, array($s->userid));
        if(empty($grade_info->items)) {
            $s->grade = null;
            $s->grade_str = "-";
        } else {
            $s->grade = $grade_info->items[0]->grades[$s->userid]->grade;
            $s->grade_str = $grade_info->items[0]->grades[$s->userid]->str_grade;
        }
        
        $student_submission_id = $s->id;
        $log_url = "grade.php?id=$cm->id&studentid=$studentid";
    } else if($groupid != -1) {
        $worktype = 1;
        $groups = get_record_sql("SELECT COUNT(DISTINCT assignments.assignmentdistribid, assignments.id) AS count
                                    FROM {$CFG->prefix}assignmentdistrib_groups AS groups
                                        JOIN {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            ON groups.id = submissions.assignmentdistrib_groups_id
                                        JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignments
                                            ON assignments.id = submissions.assignmentdistrib_assignments_id
                                    WHERE groups.id = $groupid
                                    AND assignments.assignmentdistribid = $cm->instance");
        if($groups->count == 0) {
            error('Selected group is not assigned to this activity');
        }
        $log_url = "grade.php?id=$cm->id&groupid=$groupid";
    }

    
    $isteacher = isteacher($course->id, $USER->id, true, true);
    $isleader = false;
    if($worktype == 1) {
        $leader = get_record_sql("SELECT userid, userid FROM {$CFG->prefix}assignmentdistrib_groups AS groups
                                        JOIN {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            ON groups.id = submissions.assignmentdistrib_groups_id
                                                AND groups.leaderuserid = submissions.userid
                                    WHERE groups.id = $groupid");
        if($leader !== null) {
            if($USER->id == $leader->userid) {
                $isleader = true;
            }
            $leader = get_user_info_from_db('id', $leader->userid);
        }
    }
    
    if ($isleader && !$assignmentdistrib->leadercangrade)
    {
        error('Access denied');
        die();
    }
    if(!$isteacher && !$isleader) {
        error('Access denied');
        die();
    }
    
    $warnings = array();
    $notices = array();
    $errors = array();
    
    /* INSERT/UPDATE GRADE (start) */
    $savegrades = optional_param('savegrades', false, PARAM_BOOL);
    $savegradesclose = optional_param('savegradesclose', false, PARAM_BOOL);
    
    if(($savegrades || $savegradesclose) && confirm_sesskey())
    {
        if(required_param('course', PARAM_INT) != $cm->course
            || required_param('coursemodule', PARAM_INT) != $cm->id
            || required_param('section', PARAM_INT) != $cm->section
            || required_param('instance', PARAM_INT) != $cm->instance
        ) {
            error('Form error');
        }
        
        //print_object($_POST);
        
        /* array of objects to insert/modify */
        $todo_groups_update = array();
        $todo_students_insert = array();
        $todo_students_update = array();
        
        /* calculation variables */
        $group_average_grade = null;
        $calc_number_of_students = 0;
        $calc_sum_students_grade = 0;
        
        
        if($worktype == 1)
        {
            $group = get_record('assignmentdistrib_groups', 'id', $groupid);
            if($group === false) {
                error('Group is not defined');
            }
            $group_average_grade = $group->grade;
            
            $groupgrade = optional_param('groupgrade', null, PARAM_RAW);
            if($groupgrade != null && $isteacher)
            {
                if($groupid == -1) {
                    error('Grading non group');
                }
                if(!is_array($groupgrade) || count($groupgrade) != 1) {
                    error('Group grade');
                }
                if(!$isteacher) {
                    error('Only teacher can grade group');
                }
                
                $groupcomment = required_param('groupcomment', PARAM_RAW);
                $groupcommentformat = required_param('groupcommentformat', PARAM_RAW);
                if(assignmentdistrib_change_grade($group
                                                , assignmentdistrib_array_get_value($groupgrade, $groupid)
                                                , assignmentdistrib_array_get_value($groupcomment, $groupid)
                                                , assignmentdistrib_array_get_value($groupcommentformat, $groupid)
                                                ))
                {
                    $todo_groups_update[] = $group;
                    $group_average_grade = $group->grade;
                }
            }
            
            $student_submissions = get_records('assignmentdistrib_submissions', 'assignmentdistrib_groups_id', $groupid);
        }
        else {
            $studentgrade = required_param('studentgrade', PARAM_RAW);
            $student_submissions = get_records('assignmentdistrib_submissions', 'id', $student_submission_id);
        }
        
        $studentgrade = optional_param('studentgrade', null, PARAM_RAW);
        if($studentgrade === null && $worktype == 1) {      // used for final group grade control
            foreach($student_submissions as $submission) {
                $calc_number_of_students++;
                if($submission->grade > 0) {
                    $calc_sum_students_grade += $submission->grade;
                }
            }
        }
        else if($studentgrade !== null)
        {
            $studentcomment = required_param('studentcomment', PARAM_RAW);
            $studentcommentformat = required_param('studentcommentformat', PARAM_RAW);
            
            if(!is_array($studentgrade)) {
                error('Student grades must be in array');
            }
            foreach($student_submissions as $submission)
            {
                $current_grade          = assignmentdistrib_array_get_value($studentgrade, $submission->userid);
                $current_comment        = assignmentdistrib_array_get_value($studentcomment, $submission->userid);
                $current_commentformat  = assignmentdistrib_array_get_value($studentcommentformat, $submission->userid);
                $old_grade = $submission->grade;
                
                if(assignmentdistrib_change_grade($submission
                                        , $current_grade
                                        , $current_comment
                                        , $current_commentformat
                                        )) 
                {
                    if(!$isteacher && ($old_grade != -1 && $old_grade != "-" ) ) {
                        error('Team leader cannot change the existing grade');
                    }
                    
                    $todo_students_update[] = $submission;
                }
                
                if($submission->grade < -1 || $submission->grade > $assignmentdistrib->grade) {
                    error("Grade must be between 0 and $assignmentdistrib->grade");
                } else if($submission->grade >= 0) {
                    $calc_number_of_students++;
                    $calc_sum_students_grade += $submission->grade;
                }
            }
            
            if($calc_number_of_students == 0) {
                $calc_avg_students_grade = -1;
            } else {
                $calc_avg_students_grade = $calc_sum_students_grade / $calc_number_of_students;
            }
        }
        
        if($worktype == 1 && $calc_avg_students_grade > $group_average_grade) {
            if($group_average_grade == -1) {
                $warnings[] = "Group is not graded but some students are.";
            } else {
                $warnings[] = "Average group grade must be lower or equal to {$group_average_grade}. Average group grade is {$calc_avg_students_grade}.";
            }
            $error_flag = true;
        }
        
        foreach($todo_groups_update as $record) {
            add_to_log($course->id, 'assignmentdistrib', "grading group: {$record->id} ({$record->grade})", $log_url, $assignmentdistrib->id);
            
            update_record('assignmentdistrib_groups', $record);
        }
        
        if(empty($warnings) || $isteacher) {
            foreach($todo_students_update as $record) {
                $userinfo = get_user_info_from_db('id', $record->userid);
                add_to_log($course->id, 'assignmentdistrib', "grading student: {$userinfo->username}, {$userinfo->lastname}, {$userinfo->firstname}, ({$record->grade})", $log_url, $assignmentdistrib->id);
                update_record('assignmentdistrib_submissions', $record);
                if($record->grade < 0) {
                    $record->grade = null;
                }
                //update in gradebook
                $grade = array();
                $grade['course_id'] = $course->id;
                $grade['userid'] = $record->userid;
                $grade['rawgrade'] = $record->grade;
                $details = array();
                $details['itemname'] = $assignmentdistrib->name;
                
                assignmentdistrib_grade_item_update($assignmentdistrib, $grade);
                //grade_update('mod/assignmentdistrib', $course->id, 'mod', 'assignmentdistrib', $cm->instance, 0, $grade, $details);
                //grade_regrade_final_grades($course->id);
            }
            if(empty($warnings)) {
                $notices[] = 'Saved!';
            } else {
                $notices[] = 'Saved! (You are teacher and errors are ignored)';
            }
            $error_flag = false;
        }
    }
    /* INSERT/UPDATE GRADE (end) */
    
    $students = array();
    if($worktype == 0) {
        add_to_log($course->id, 'assignmentdistrib', 'grade', $log_url, $assignmentdistrib->id);
        $assignment = get_record_sql("SELECT assignments.id,
                                            assignments.name,
                                            description,
                                            descriptionformat,
                                            groupstudentmin,
                                            groupstudentmax
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignments
                                                ON submissions.assignmentdistrib_assignments_id = assignments.id
                                        WHERE submissions.id = $student_submission_id
                                            AND assignmentdistribid = $cm->instance", false, true);
        
        /*
        $student_assignment = get_record_sql("SELECT submissions.userid
                                            assignment.assignment_name AS assignment_name,
                                            assignment.assignment_description AS assignment_description,
                                            assignment.assignment_descriptionformat AS assignment_descriptionformat,
                                            assignment.assignment_groupstudentmin AS assignment_groupstudentmin,
                                            assignment.assignment_groupstudentmax AS assignment_groupstudentmax,
                                            submissions.assignmentdistrib_groups_id AS submissions_group,
                                            submissions.grade,
                                            submissions.timegraded,
                                            submissions.comment,
                                            submissions.commentformat,
                                            submissions.assignmenttype,
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignments
                                                ON submissions.assignmentdistrib_assignments_id = assignments.id
                                        WHERE submissions.userid = $studentid");
        */
        $students = get_records_sql("SELECT user.id AS id, firstname, lastname, email, picture, username,
                                            submissions.gradedbyuserid,
                                            submissions.comment,
                                            submissions.commentformat
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            RIGHT JOIN {$CFG->prefix}user AS user
                                                ON user.id = submissions.userid
                                    WHERE submissions.id = $student_submission_id
                                    ORDER BY lastname, firstname");
        
        $student = current($students);
        $grade_info = grade_get_grades($cm->course, 'mod', 'assignmentdistrib', $cm->instance, array($student->id));
        if(empty($grade_info->items)) {
            $student->grade = null;
        } else {
            $student->grade = $grade_info->items[0]->grades[$student->id]->str_grade;
        }
        
        $title = "Grade: $student->firstname $student->lastname";
    } else {
        add_to_log($course->id, 'assignmentdistrib', 'grade', $log_url, $assignmentdistrib->id);
        $assignment = get_record_sql("SELECT assignments.id,
                                            assignments.name,
                                            description,
                                            descriptionformat,
                                            groupstudentmin,
                                            groupstudentmax
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignments
                                                ON submissions.assignmentdistrib_assignments_id = assignments.id
                                        WHERE submissions.assignmentdistrib_groups_id = $groupid
                                            AND assignmentdistribid = $cm->instance
                                        LIMIT 1", false, true);
        
        
        /*
        $student_assignment = get_record_sql("SELECT submissions.userid
                                    assignment.assignment_name AS assignment_name,
                                    assignment.assignment_description AS assignment_description,
                                    assignment.assignment_descriptionformat AS assignment_descriptionformat,
                                    assignment.assignment_groupstudentmin AS assignment_groupstudentmin,
                                    assignment.assignment_groupstudentmax AS assignment_groupstudentmax,
                                    submissions.assignmentdistrib_groups_id AS submissions_group,
                                    submissions.grade,
                                    submissions.timegraded,
                                    submissions.comment,
                                    submissions.commentformat,
                                    submissions.assignmenttype,
                                FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                    JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignments
                                        ON submissions.assignmentdistrib_assignments_id = assignments.id
                                WHERE submissions.userid = $studentid");
        */
        
        
        if($leader === null) {
            $title = 'Grade: Group - No leader';
        } else {
            $title = "Grade: Group - $leader->firstname $leader->lastname";
        }
        
        $groupinfo = get_record('assignmentdistrib_groups', 'id', $groupid);
        $_temp = optional_param('_groupgrade', null, PARAM_RAW);
        $groupinfo->grade = ($_temp[$groupid])?$_temp[$groupid]:$groupinfo->grade;
        
        $students = get_records_sql("SELECT userid AS id, firstname, lastname, email, picture, username,
                                            submissions.grade,
                                            submissions.gradedbyuserid,
                                            submissions.comment,
                                            submissions.commentformat
                                        FROM {$CFG->prefix}assignmentdistrib_groups AS groups
                                        JOIN {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            ON groups.id = submissions.assignmentdistrib_groups_id
                                        JOIN {$CFG->prefix}user AS user
                                            ON submissions.userid = user.id
                                    WHERE groups.id = $groupid
                                    ORDER BY lastname, firstname");
    }

    print_header($title, $title, '', '', '', false, '', '');
    
    if($savegradesclose && empty($warnings) && !$error_flag) {
        ?>
        <center><input type="button" onclick="window.close();" value="Close" /></center>
        <script type="text/javascript">
            window.close();
        </script>
        <?php
    }
    else
    {
        $penalties = get_recordset_select("assignmentdistrib_penalties", "assignmentdistrib_id = {$assignmentdistrib->id}", 'time asc', '*');
        ?>
            <script type="text/javascript">
                var penalties = new Array();
                var pen_count = <?=$penalties->_numOfRows?>;
                <?php
                    $i=0;
                    foreach($penalties as $p) {
                        echo "penalties[$i]=".$p['penalty_grade'].";\n\t\t\t";
                        $i++;
                    }
                ?>
                function update_final_grade(studentid) {
                    grade = parseInt(document.getElementById('menu_studentgrade'+studentid).value);
                    
                    if (grade == -1) {
                        document.getElementById('assignmentdistrib_finalgrade').innerHTML = "No grade";
                        document.getElementById('studentgrade['+studentid+']').value = -1;
                        return false;
                    }
                    for (i=0; i<pen_count; i++) {
                        if (document.getElementById('hpen_'+i).value=="true") grade += parseInt(penalties[i]);
                    }
                    document.getElementById('assignmentdistrib_finalgrade').innerHTML = grade;
                    document.getElementById('studentgrade['+studentid+']').value = grade;
                }
                function update_final_group_grade(groupid) {
                    grade = parseInt(document.getElementById('menu_groupgrade'+groupid).value);
                    if (grade == -1) {
                        document.getElementById('assignmentdistrib_finalgrade').innerHTML = "No grade";
                        document.getElementById('groupgrade['+groupid+']').value = "-1";
                        return false;
                    }
                    for (i=0; i<pen_count; i++) {
                        if (document.getElementById('hpen_'+i).value=="true") grade += parseInt(penalties[i]);
                    }
                    document.getElementById('assignmentdistrib_finalgrade').innerHTML = grade;
                    document.getElementById('groupgrade['+groupid+']').value = grade;
                }
            </script>
            <div class="error">
                <?php
                foreach($warnings as $text) {
                    echo '<p>'.$text.'</p>';
                }
                ?>
            </div>
            <div class="notice">
                <?php
                foreach($notices as $text) {
                    echo '<p>'.$text.'</p>';
                }
                ?>
            </div>
            <div style="text-align: right">
                <?php
                $baseurl = "grade.php?id=$cm->id&amp;". ($worktype == 0 ? s("studentid=$studentid") : s("&groupid=$groupid") );
                if($showcomment) {
                    $currenturl = $baseurl .'&amp;comment=1';
                    $inverturl = $baseurl;
                    $invertllink = "<a href=\"$inverturl\">Hide grade comment</a>";
                } else {
                    $currenturl = $baseurl;
                    $inverturl = $baseurl .'&amp;comment=1';
                    $invertllink = "<a href=\"$inverturl\">Show grade comment</a>";
                }
                ?>
            </div>
        <?php
        
        if ($usehtmleditor = can_use_html_editor()) {
            $defaultformat = FORMAT_HTML;
            $editorfields = '';
        } else {
            $defaultformat = FORMAT_MOODLE;
        }
        // HTML
        
        $form = new object();
        $form->coursemodule = $cm->id;
        $form->section      = $cm->section;
        $form->course       = $cm->course;
        $form->instance     = $cm->instance;
        $form->sesskey      = !empty($USER->id) ? $USER->sesskey : '';
        ?>
        <br />
        <form name="grade" method="post" action="<?php echo $currenturl; ?>">
            
            <div style="text-align: right">
                <?php if ($notices) { ?><input type="button" value="Close and refresh parent window" onclick="window.opener.location.reload(); window.close();"><br><br><?php }?>
                <?php echo $invertllink; ?>&nbsp;&nbsp;&nbsp;
                
                <input type="submit" name="savegrades" value="Save grades" />
                <input type="submit" name="savegradesclose" value="Save grades and close" />
                <input type="button" value="Close" onclick="window.close();">
            </div>
            <br />
            <?php
            if($worktype == 1)          // grade group
            {
                ?>
                <table cellspacing="0" border="1" class="generaltable" width="100%">
                    <tr>
                        <td class="header c1" colspan="2">
                            <strong>Group grade</strong>
                        </td>
                    </tr>
                    <tr>
                        <td width="40" valign="top" class="picture user">
                            &nbsp;
                        </td>
                        <td valign="top">
                            <div style="float: right">Maximal average group grade:
                                <?php 
                                    if($isteacher) {
                                        /*
                                        choose_from_menu(make_grades_menu($assignmentdistrib->grade)
                                            , "groupgrade[$groupid]", $groupinfo->grade, get_string('nograde'), '', -1);
                                        */
                                        choose_from_menu(make_grades_menu($assignmentdistrib->grade)
                                            , "_groupgrade[$groupid]", $groupinfo->grade, get_string('nograde')
                                            , 'update_final_group_grade('.$groupid.')', -1);
                                        echo "<input type=\"hidden\" name=\"groupgrade[$groupid]\" id=\"groupgrade[$groupid]\" value=\"\" />";
                                        ?>

                                        <?php
                                    }
                                    else {
                                        echo $groupinfo->grade;
                                    }
                                ?>
                            </div>
                            <?php
                                if ($isteacher) {
                                    if($penalties->_numOfRows) {
                                        ?>
                                        <div style="clear: both; width: 100%">Suggested penalties:
                                        <br/><span style="font-style: italic; font-size: 0.8em">Select penalties you wish to enable<br/>Take care if you're updating the grade, the penalties may have already been considered in the first grading.</span>
                                        <br/><hr/><font style="font-size: 0.8em">
                                        <?php
                                        $time = time();
                                        $i=0;
                                        foreach($penalties as $p) {
                                            if ($p['time'] < $time) echo '<span style="color: red">';
                                            print_checkbox('penalty_'.$i, $i, ($p['time'] < $time), userdate($p['time'])."&nbsp;&nbsp;&nbsp;(<b>".$p['penalty_grade']."</b> pts.)", ''
                                                          , "document.getElementById('hpen_$i').value=this.checked; update_final_group_grade($groupid)", '');
                                            echo "<br/>";
                                            if ($p['time'] < $time) echo '</span>';
                                            echo "<input type='hidden' value='".(($p['time'] < $time)?"true":"false")."' id='hpen_$i' />";
                                            $i++;
                                        }
                                        ?>
                                        </font> <hr/>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                    <div style="clear:both; float:right; padding: 10px 0 10px 0">
                                        Final grade: <b> <span id="assignmentdistrib_finalgrade"> </span></b>
                                        <script>
                                            update_final_group_grade(<?=$groupid?>);
                                        </script>
                                    </div>
                                    <div style="clear: both"></div>
                                    <?php
                                }
                            ?>
                            <div style="clear: all" />
                            <?php
                                if($showcomment)
                                {
                                    if($isteacher) {
                                        print_textarea($usehtmleditor, 8, 40, 0, 0, "groupcomment[$groupid]", $groupinfo->comment, $course->id);
                                
                                        if ($usehtmleditor) { 
                                            echo '<input type="hidden" name="groupcommentformat['.$groupid.']" value="'.FORMAT_HTML.'" />';
                                        } else {
                                            echo '<div align="right" class="format">';
                                            choose_from_menu(format_text_menu(), "groupcommentformat[$groupid]", $groupinfo->commentformat, "");
                                            helpbutton("textformat", get_string("helpformatting"));
                                            echo '</div>';
                                        }
                                    }
                                    else {
                                        echo format_text($groupinfo->comment, $groupinfo->commentformat, null, $course->id);
                                    }
                                    echo '<div style="clear: all" />';
                                }
                                else
                                {
                                    echo "<input type=\"hidden\" name=\"groupcomment[$groupid]\" value=\"$groupinfo->comment\" />";
                                    echo "<input type=\"hidden\" name=\"groupcommentformat[$groupid]\" value=\"$groupinfo->commentformat\" />";
                                }
                            ?>
                        </td>
                    </tr>
                </table>
                <br />
                <hr />
                <br />
                <?php
            }
            ?>
            <table cellspacing="0" border="1" class="generaltable" width="100%">
                <?php
                foreach($students as $student)              // grade students
                {
                    if($error_flag == true
                        && isset($studentgrade) && isset($studentcomment) && isset($studentcommentformat)
                        && is_array($studentgrade) && is_array($studentcomment) && is_array($studentcommentformat) )
                    {
                        $old_grade          = assignmentdistrib_array_get_value($studentgrade, $student->id);
                        $old_comment        = assignmentdistrib_array_get_value($studentcomment, $student->id);
                        $old_commentformat  = assignmentdistrib_array_get_value($studentcommentformat, $student->id);
                    }
                    ?>
                    <tr>
                        <td colspan="2" class="header c1">
                            <strong><?php p($student->firstname.' '.$student->lastname)?></strong> <em>(<?php p($student->username) ?>)</em>
                        </td>
                    </tr>
                    <tr>
                        <td width="40" valign="top" class="picture user">
                            <?php
                                print_user_picture($student->id, $course->id, $student->picture);
                            ?>
                        </td>
                        <td>
                            <div style="float: right">Grade:
                                <?php
                                    if(!$isteacher && $student->grade != -1) {
                                        p($student->grade);
                                        echo "<input type=\"hidden\" name=\"studentgrade[$student->id]\" value=\"$student->grade\" />";
                                    } else {
                                        if($worktype == 0) {
                                            choose_from_menu(make_grades_menu($assignmentdistrib->grade)
                                                , "_studentgrade[$student->id]", $error_flag ? $old_grade : $student->grade, get_string('nograde')
                                                , 'update_final_grade('.$student->id.')', -1);
                                            echo "<input type=\"hidden\" name=\"studentgrade[$student->id]\" id=\"studentgrade[$student->id]\" value=\"\" />";
                                        }
                                        else
                                            choose_from_menu(make_grades_menu($assignmentdistrib->grade)
                                                , "studentgrade[$student->id]", $error_flag ? $old_grade : $student->grade, get_string('nograde'), '', -1);
                                    }
                                ?>
                            </div>
                            
                            <?php
                            if($worktype == 0) { 
                                if($penalties->_numOfRows) {
                                ?>
                                <div style="clear: both; width: 100%">Suggested penalties:
                                <br/><span style="font-style: italic; font-size: 0.8em">Select penalties you wish to enable<br/>Take care if you're updating the grade, the penalties may have already been considered in the first grading.</span>
                                <br/><hr/><font style="font-size: 0.8em">
                                <?php
                                $time = time();
                                $i=0;
                                foreach($penalties as $p) {
                                    if ($p['time'] < $time) echo '<span style="color: red">';
                                    print_checkbox('penalty_'.$i, $i, ($p['time'] < $time), userdate($p['time'])."&nbsp;&nbsp;&nbsp;(<b>".$p['penalty_grade']."</b> pts.)", ''
                                                  , "document.getElementById('hpen_$i').value=this.checked; update_final_grade($student->id)", '');
                                    echo "<br/>";
                                    if ($p['time'] < $time) echo '</span>';
                                    echo "<input type='hidden' value='".(($p['time'] < $time)?"true":"false")."' id='hpen_$i' />";
                                    $i++;
                                }
                                ?>
                                </font> <hr/>
                                </div>
                                <?php
                                }
                                ?>
                                <div style="clear:both; float:right; padding: 10px 0 10px 0">
                                    Final grade: <b> <span id="assignmentdistrib_finalgrade"> </span></b>
                                    <script>
                                        update_final_grade(<?=$student->id?>);
                                    </script>
                                </div>
                                <div style="clear: both"></div>
                            <?php } ?>
                            <div style="clear: all" />
                            <?php
                                if($showcomment && ($isteacher ||$student->grade == -1))
                                {
                                    echo '<br />';
                                    print_textarea($usehtmleditor, 8, 40, 0, 0, "studentcomment[$student->id]", $error_flag ? $old_comment : $student->comment, $course->id);
                            
                                    if ($usehtmleditor) { 
                                        echo '<input type="hidden" name="studentcommentformat['.$student->id.']" value="'.FORMAT_HTML.'" />';
                                    } else {
                                        echo '<div align="right" class="format">';
                                        choose_from_menu(format_text_menu(), "studentcommentformat[$student->id]", $error_flag ? $old_commentformat : $student->commentformat, "");
                                        helpbutton("textformat", get_string("helpformatting"));
                                        echo '</div>';
                                    }
                                    echo '<div style="clear: all" />';
                                }
                                else
                                {
                                    if($showcomment && !$isteacher && $student->grade != -1) {
                                        echo '<br />';
                                        echo format_text($student->comment, $student->commentformat, null, $course->id);
                                    }
                                    
                                    echo "<input type=\"hidden\" name=\"studentcomment[$student->id]\" value=\"$student->comment\" />";
                                    echo "<input type=\"hidden\" name=\"studentcommentformat[$student->id]\" value=\"$student->commentformat\" />";
                                }
                            ?>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
    
            <input type="hidden" name="grading"       value="1" />
            <input type="hidden" name="course"        value="<?php  p($form->course) ?>" />
            <input type="hidden" name="sesskey"       value="<?php  p($form->sesskey) ?>" />
            <input type="hidden" name="coursemodule"  value="<?php  p($form->coursemodule) ?>" />
            <input type="hidden" name="section"       value="<?php  p($form->section) ?>" />
            <input type="hidden" name="instance"      value="<?php  p($form->instance) ?>" />
            
            <div style="text-align: center">
                <input type="submit" name="savegrades" value="Save grades" />
                <input type="submit" name="savegradesclose" value="Save grades and close" />
                <input type="button" value="Close" onclick="window.close();">

            </div>
        </form>
        <?php
    }
    if (isset($usehtmleditor) && $usehtmleditor && empty($nohtmleditorneeded)) {
        use_html_editor($editorfields);
    }
    
    print_footer('none');
?>