<?php
/**
 * Proposes new assignment for student and group
 * 
 * @author  Nikola Tankovic
 * @author  Enola Knežević
 * @package assignmentdistrib
 **/

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

    }
    
    if ($usehtmleditor = can_use_html_editor()) {
        $defaultformat = FORMAT_HTML;
        $editorfields = '';
    } else {
        $defaultformat = FORMAT_MOODLE;
    }
    
    print_header("", "", "", '', '', true, "", "");
    
    require_login($course->id);
    
    $isteacher = isteacher($course->id);
    $isstudent = isstudent($course->id);
    
    $form = new object();
    $form->coursemodule = $cm->id;
    $form->section      = $cm->section;
    $form->course       = $cm->course;
    $form->instance     = $cm->instance;
    $form->sesskey      = !empty($USER->id) ? $USER->sesskey : '';
    
    $mode = optional_param('mode', 'suggest', PARAM_ALPHANUM);
    
    $submit = optional_param('submit', NULL, PARAM_ALPHANUM);
    $copy = optional_param('copy', NULL, PARAM_ALPHANUM);
    $approve = optional_param('approve', NULL, PARAM_ALPHANUM);
    $reject = optional_param('reject', NULL, PARAM_ALPHANUM);
    
    $save = ($submit || $copy || $approve || $reject);
    
    if(!in_array( $mode, array('suggest', 'suggestedit') )) {
        $mode = 'suggest';
    }
    
    if ($save && confirm_sesskey())
    {
        $sesskey            = required_param('sesskey', PARAM_RAW);
        
        $sdata = new object();
        $sdata->name                   = trim(required_param('name', PARAM_RAW));
        $sdata->description            = required_param('description', PARAM_RAW);
        $sdata->descriptionformat      = required_param('descriptionformat', PARAM_INT);
        $sdata->assignmentdistribid    = required_param('instance', PARAM_INT);
        if ($isteacher) $sdata->available              = optional_param('available', 1, PARAM_INT);
        else $sdata->available = 0;
        if ($isteacher) $sdata->groupstudentmin        = optional_param('groupstudentmin', 1, PARAM_INT);
        if ($isteacher) $sdata->groupstudentmax        = optional_param('groupstudentmax', 1, PARAM_INT);
        if ($isteacher) $sdata->maxnumberofrepeats     = optional_param('maxnumberofrepeats', NULL, PARAM_INT);
        $sdata->approved               = 0;
        $sdata->createdbyuserid        = optional_param('createdbyuserid', $USER->id, PARAM_INT);
        
        if($cm->instance != $sdata->assignmentdistribid) {
            error('Instance error');
        }
    }
    
    if ($mode == 'suggest')
    {
        if ($submit && confirm_sesskey())
        {
            $inserted = insert_record('assignmentdistrib_assignments', $sdata, true);
            $message = get_string('course', 'assignmentdistrib') . ": $course->fullname\n" .
                                get_string('assignmentdistribution', 'assignmentdistrib') . ": $assignmentdistrib->name\n" .
                                get_string('suggestedassignment', 'assignmentdistrib');
        message_post_message($USER, get_teacher($course->id), $message, FORMAT_PLAIN, 'direct');
            
//            notify(get_string('saved', 'assignmentdistrib'));
//            close_window_button();


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
            die();
            //redirect("suggest.php?id=$cm->id&mode=suggestedit&aid=$inserted", get_string('saved', 'assignmentdistrib'), 10);
        }
    }
    else if ($mode == 'suggestedit')
    {
//        $isteacher = isteacher($course->id);
//        $isstudent = isstudent($course->id);
        $aid = required_param('aid', PARAM_INT);
        //print_object($aid);
        $data = get_record('assignmentdistrib_assignments', 'id', $aid);
        //print_object($data);
        //print_object($USER);
        if ($data->createdbyuserid != $USER->id && !$isteacher)
        {
            error('Access violation');
        }

        if (isteacher())
        {
            $createdbyuser = get_complete_user_data('id', $data->createdbyuserid);
            echo ("Assignment created by: ");
            p($createdbyuser->firstname.' '.$createdbyuser->lastname);
          //  if (!$approved)
            //    echo ("<br>Assignment has not been approved yet.");
        }

        if ($copy && confirm_sesskey()) {
            
            $sdata->id = $aid;
            $sdata->approved = 1;
//            print_object($sdata);
//            die();
            
            insert_record('assignmentdistrib_assignments', $sdata);
            
//            notify(get_string('saved', 'assignmentdistrib'));
//            close_window_button();

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

            die();
            //redirect("suggest.php?id=$cm->id&mode=suggestedit&aid=$aid", get_string('saved', 'assignmentdistrib'), 10);
        }
        else if ($approve && confirm_sesskey()) {
            
            $sdata->id = $aid;
           
            // ASSIGN STUDENT
            if($assignmentdistrib->worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
                $newrecord = new object();
                $newrecord->assignmentdistrib_assignments_id = $aid;
                $newrecord->userid = $sdata->createdbyuserid;
                $newrecord->timecreated = time();
                
                if(!insert_record('assignmentdistrib_submissions', $newrecord, false)) {
                    error('Unable to create record in database');
                }
            }
            // if its group just approve it
            $sdata->approved = 1;
//            print_object($sdata);
//            die();
            update_record('assignmentdistrib_assignments', $sdata);
            
//            notify(get_string('saved', 'assignmentdistrib'));
//            close_window_button();

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
            die();
            //redirect("suggest.php?id=$cm->id&mode=suggestedit&aid=$aid", get_string('saved', 'assignmentdistrib'), 10);
        }
        else if ($submit && confirm_sesskey()) {
            $sdata->id = $aid;
            
            update_record('assignmentdistrib_assignments', $sdata);
            
//            notify(get_string('saved', 'assignmentdistrib'));
//            close_window_button();

        ?>
            <p align="center">
                <br>
                Update successful.
                <br><br>
                <input type="button" value="Close" onclick="window.close();">
                <br>
                <br>
<!--                <input type="button" value="Close and refresh parent window" onclick="window.opener.location.reload(); window.close();">-->
            </p>
        <?php

            die();
            //redirect("suggest.php?id=$cm->id&mode=suggestedit&aid=$aid", get_string('saved', 'assignmentdistrib'), 10);
        }
        else if ($reject && confirm_sesskey()){

            $sdata->id = $aid;
//            print_object($sdata);
//            die();
            delete_records('assignmentdistrib_assignments', 'id', $sdata->id);

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

            die();

        }
        
        $description = $data->description;
        $name = $data->name;
        $createdbyuserid = $data->createdbyuserid;
        $groupstudentmin = $data->groupstudentmin;
        $groupstudentmax = $data->groupstudentmax;
        $maxnumberofrepeats = $data->maxnumberofrepeats;
        $available = $data->available;
    }
    
    include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_assignments_add.html");
    
    if (isset($usehtmleditor) && $usehtmleditor) {
        use_html_editor($editorfields);
    }
    
?>