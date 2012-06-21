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
    $save = optional_param('save', NULL, PARAM_ALPHANUM);
    $back = optional_param('back', NULL, PARAM_ALPHANUM);
    $chooseassignment = optional_param('chooseassignment', null, PARAM_RAW);
    
    if(!in_array( $mode, array('addgroup', 'editgroup') )) {
        $mode = 'addgroup';
    }
    
    if ($isstudent && !$assignmentdistrib->allowstudentchange)
    {
        error('Access violation');
    }
    if ($mode == "addgroup") {
        $record = get_record('assignmentdistrib', 'id', $cm->instance);
    }
    if ($isteacher || ($isstudent && $mode=="editgroup"))
    {
        $groupmembers = optional_param('groupmembers', array(), PARAM_RAW);
        $leaderid = optional_param('leaderid', '', PARAM_ALPHANUM);
        $market_enabled = optional_param('allowother', 0, PARAM_INT);
        
        if ($mode=="editgroup")
        {
            $aid = required_param('aid', PARAM_INT);
            $gid = required_param('gid', PARAM_INT);
        }
        if ($mode=="editgroup" && !is_array($chooseassignment))
        {   
            $chooseassignment = array();
            
            $group = get_record_select("assignmentdistrib_groups", "id=$gid");
            $groupmembers_records = get_records_select("assignmentdistrib_submissions",
                                                      "assignmentdistrib_groups_id = $gid AND assignmentdistrib_assignments_id = $aid");
            foreach($groupmembers_records as $gm)
            {
                $groupmembers[] = $gm->userid;
            }
            $chooseassignment[$aid] = true;
            $market_enabled = $group->allowjoin;
            $leaderid = $group->leaderuserid;
        }
        
        if(is_array($chooseassignment) && count($chooseassignment) == 1 && !$back)
        {
            foreach($chooseassignment as $assignmentid=>$value) { break; }
            $assignment = get_record('assignmentdistrib_assignments', 'id', $assignmentid, 'assignmentdistribid', $cm->instance);
            if($assignment === false) {
                error('Assignment is missing');
            }
            
            // assigned students
            $addition_sql = ($mode=="editgroup") ? " AND dbaa.id != $aid" : "";
            
            $assignedstudents = get_records_sql("SELECT dbas.userid AS userid
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS dbas
                                            , {$CFG->prefix}assignmentdistrib_assignments AS dbaa
                                        WHERE  dbas.assignmentdistrib_assignments_id = dbaa.id
                                            AND dbaa.assignmentdistribid = {$cm->instance}
                                        ".$addition_sql);
            $assignedstudentslist = '';
            if($assignedstudents !== false)
            {
                $first = true;
                foreach($assignedstudents as $value)
                {
                    if($first==false) {
                        $assignedstudentslist .= ', ';
                    } else {
                        $first = false;
                    }
                    $assignedstudentslist .= $value->userid;
                }
            }
            
            // unassigned students
            $unasignedstudents = get_course_students($cm->course, 'u.lastname, u.firstname, u.username'
                                                        , '', 0, 99999, '', '', null, '', 'u.id, firstname, lastname, username, email'
                                                        , $assignedstudentslist);
            $unasignedstudentsid = array();
            foreach($unasignedstudents as $value) {
                $unasignedstudentsid[] = $value->id;
            }
            
            if(!is_array($groupmembers)) {
                error('Not array');
            }
            
            // adding and removing
            if(optional_param('add', false, PARAM_BOOL))
            {
                $groupmembers_add = optional_param('groupmembers_add', array(), PARAM_RAW);
                foreach($groupmembers_add as $key=>$value) {
                    if(!is_numeric($value)) {
                        error('Not numeric');
                    }
                }
                $groupmembers = array_merge($groupmembers, $groupmembers_add);
            }
            else if(optional_param('remove', false, PARAM_BOOL))
            {
                $groupmembers_remove = optional_param('groupmembers_remove', array(), PARAM_RAW);
                foreach($groupmembers_remove as $key=>$value) {
                    if(!is_numeric($value)) {
                        error('Not numeric');
                    }
                }
                $groupmembers = array_diff($groupmembers, $groupmembers_remove);
            }
            
            // if removed current user bring it back
            if ($mode=="editgroup" && $isstudent)
            {
                $groupmembers[] = $USER->id;
            }
            
            $groupmembers = array_unique($groupmembers);
            
            if ($mode!="editgroup" && !$save) {
                // check if all members are unassigned
                $groupmemberscheck = array_diff($groupmembers, $unasignedstudentsid);
                if(count($groupmemberscheck) != 0) {
                    $error = true;
                    $errormembercheck = true;
                }
            }
            
            // check min/max students in a group
            $groupmemberscount = count($groupmembers);
            if($groupmemberscount < $assignment->groupstudentmin) {
                $errormin = true;
                if($assignmentdistrib->allowgroupmarket && $market_enabled) {
                    $error = false;
                } else {
                    $error = true;
                }
            }
            else if($groupmemberscount > $assignment->groupstudentmax) {
                $errormax = true;
                $error = true;
            }
            
            // check if leader exists
            if (!$leaderid) {
                $errorleader = true;
                $error = true;
            }
            
            if((!isset($error) || !$error) && optional_param('save', false, PARAM_BOOL) && confirm_sesskey())
            {
                // update
                $newgroupmembers = $groupmembers;
                
                $group = get_record_select("assignmentdistrib_groups", "id=$gid");
                $groupmembers_records = get_records_select("assignmentdistrib_submissions",
                                                          "assignmentdistrib_groups_id = $gid AND assignmentdistrib_assignments_id = $aid");
                
                // if members removed
                foreach($groupmembers_records as $gm) {
                    if (!in_array($gm->userid, $newgroupmembers))
                    {
                        // delete removed students
                        delete_records('assignmentdistrib_submissions', 'userid', $gm->userid
                                                                      , 'assignmentdistrib_assignments_id', $aid
                                                                      , 'assignmentdistrib_groups_id', $gid);
                    }
                    unset($newgroupmembers[array_search($gm->userid, $newgroupmembers)]);
                }
                foreach($newgroupmembers as $ngm) {
                    // insert new members
                    $newrecord_submissions = new object();
                    $newrecord_submissions->assignmentdistrib_assignments_id = $aid;
                    $newrecord_submissions->userid = $ngm;
                    $newrecord_submissions->assignmentdistrib_groups_id = $gid;
                    $newrecord_submissions->timecreated = time();
                    $insertedid = insert_record('assignmentdistrib_submissions', $newrecord_submissions, true);
                    if($insertedid) {
                        $insertedids[] = $insertedid;
                    } else {
                        $rollback = true;
                        break;
                    }
                }
                
                if(isset($rollback) && $rollback == true) {
                    foreach($insertedids as $insertedid) {
                        delete_records('assignmentdistrib_submissions', 'id', $insertedid);
                    }
                    delete_records('assignmentdistrib_groups', 'id', $groupid);
                    error('Unable to create all records in database (submissions)');
                }
                else {
                    $update_group = new object();
                    $update_group->id = $gid;
                    $update_group->name = '';
                    $update_group->leaderuserid = $leaderid;
                    $update_group->allowjoin = $market_enabled;
                    update_record('assignmentdistrib_groups', $update_group);
                }
                
                $changes_saved = true;
                
            }
            else if((!isset($error) || !$error) && optional_param('getfinal', false, PARAM_BOOL) && $isteacher && confirm_sesskey())
            {
                // insert: submissions, groups

                $rollback = false;
                $newrecord_group = new object();
                $newrecord_group->name = '';
                $newrecord_group->leaderuserid = $leaderid;
                $newrecord_group->allowjoin = $market_enabled;
                $groupid = insert_record('assignmentdistrib_groups', $newrecord_group, true);
                if($groupid === false) {
                    error('Unable to create record in database (group)');
                }
                
                $time = time();
                $insertedids = array();
                foreach($groupmembers as $groupmember)
                {
                    $newrecord_submissions = new object();
                    $newrecord_submissions->assignmentdistrib_assignments_id = $assignmentid;
                    $newrecord_submissions->userid = $groupmember;
                    $newrecord_submissions->assignmentdistrib_groups_id = $groupid;
                    $newrecord_submissions->timecreated = time();
                    $insertedid = insert_record('assignmentdistrib_submissions', $newrecord_submissions, true);
                    if($insertedid) {
                        $insertedids[] = $insertedid;
                    } else {
                        $rollback = true;
                        break;
                    }
                }
                if($rollback == true) {
                    foreach($insertedids as $insertedid) {
                        delete_records('assignmentdistrib_submissions', 'id', $insertedid);
                    }
                    delete_records('assignmentdistrib_groups', 'id', $groupid);
                    error('Unable to create all records in database (submissions)');
                }
                if ($isteacher) {
                    notify(get_string('assigned', 'assignmentdistrib'));
                    close_window_button();
                    ?><br><br><center><input type="button" value="Close and refresh parent window" onclick="window.opener.location.reload(); window.close();"></center><?php
                    die();
                }
                else redirect("view.php?id=$cm->id", get_string('assigned', 'assignmentdistrib'), 10);
            }
            
            include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose_group.html");    
        }
        else include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose.html");    
    }
?>