<?php 
    //This php script contains all the stuff to backup/restore

//This function executes all the restore procedure about this mod
function assignmentdistrib_restore_mods($mod,$restore) {
    global $CFG;
    $status = true;
    //Get record from backup_ids
    $data = backup_getid($restore->backup_unique_code,$mod->modtype,$mod->id);

    if ($data) {
        //Now get completed xmlized object
        $info = $data->info;
        //if necessary, write to restorelog and adjust date/time fields
        if ($restore->course_startdateoffset) {
         //   restore_log_date_changes('Assignmentdistrib', $restore, $info['MOD']['#'], array('TIMEDUE', 'TIMEAVAILABLE'));
        }
        //traverse_xmlize($info);                                                                     //Debug
        //print_object ($GLOBALS['traverse_array']);                                                  //Debug
        //$GLOBALS['traverse_array']="";                                                              //Debug

        //Now, build the ASSIGNMENT record structure
        $assignmentdistrib->course = $restore->course_id;
        $assignmentdistrib->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
        $assignmentdistrib->description = backup_todb($info['MOD']['#']['DESCRIPTION']['0']['#']);
        $assignmentdistrib->descriptionformat = backup_todb($info['MOD']['#']['DESCRIPTIONFORMAT']['0']['#']);
        $assignmentdistrib->worktype = backup_todb($info['MOD']['#']['WORKTYPE']['0']['#']);
        $assignmentdistrib->distributiontype = backup_todb($info['MOD']['#']['DISTRIBUTIONTYPE']['0']['#']);
        $assignmentdistrib->assignmenttype = backup_todb($info['MOD']['#']['ASSIGNMENTTYPE']['0']['#']);
        $assignmentdistrib->allowgroupmarket = backup_todb($info['MOD']['#']['ALLOWGROUPMARKET']['0']['#']);
        $assignmentdistrib->allowstudentchange = backup_todb($info['MOD']['#']['ALLOWSTUDENTCHANGE']['0']['#']);
        $assignmentdistrib->preventlate = backup_todb($info['MOD']['#']['PREVENTLATE']['0']['#']);
        $assignmentdistrib->timedue = backup_todb($info['MOD']['#']['TIMEDUE']['0']['#']);
        $assignmentdistrib->timeavailable = backup_todb($info['MOD']['#']['TIMEAVAILABLE']['0']['#']);
        $assignmentdistrib->leadercangrade = backup_todb($info['MOD']['#']['LEADERCANGRADE']['0']['#']);
        $assignmentdistrib->suggestable = backup_todb($info['MOD']['#']['SUGGESTABLE']['0']['#']);
        $assignmentdistrib->grade = backup_todb($info['MOD']['#']['GRADE']['0']['#']);
        $assignmentdistrib->timemodified = backup_todb($info['MOD']['#']['TIMEMODIFIED']['0']['#']);

        //We have to recode the grade field if it is <0 (scale)
        if ($assignmentdistrib->grade < 0) {
            $scale = backup_getid($restore->backup_unique_code,"scale",abs($assignmentdistrib->grade));        
            if ($scale) {
                $assignmentdistrib->grade = -($scale->new_id);       
            }
        }

        //The structure is equal to the db, so insert the assignment
        $newid = insert_record ("assignmentdistrib",$assignmentdistrib);

        //Do some output     
        if (!defined('RESTORE_SILENTLY')) {
            echo "<li>".get_string("modulename","assignmentdistrib")." \"".format_string(stripslashes($assignmentdistrib->name),true)."\"</li>";
        }
        backup_flush(300);

        if ($newid) {
            //We have the newid, update backup_ids
            backup_putid($restore->backup_unique_code, $mod->modtype, $mod->id, $newid);

            //restore assignments
            $status = assignmentdistrib_assignments_restore_mods($mod->id, $newid, $info, $restore, $mod) && $status;

            //restore penalties
            $status = assignmentdistrib_penalties_restore_mods($mod->id, $newid, $info, $restore) && $status;
            
            //restore user data if necessary
            if (restore_userdata_selected($restore,'assignmentdistrib',$mod->id)) { 
                $status = assignmentdistrib_groups_restore_mods($mod->id, $newid, $info, $restore) && $status;
                $status = assignmentdistrib_submissions_restore_mods($mod->id, $newid, $info, $restore, $assignmentdistrib->assignmenttype) && $status;
                $status = assignmentdistrib_restore_files ($mod->id, $newid, $restore);
               
               
            }      
        } else {
            $status = false;
        }
    } else {
        $status = false;
    }
    return $status;
}


/**
 * Restore assignments
 */ 
function assignmentdistrib_assignments_restore_mods($oldid, $newid, $info, $restore, $mod){
    global $CFG;
    $status = true;
    if (isset($info['MOD']['#']['ASSIGNMENTS']['0']['#']['ASSIGNMENT'])) {
        $assignments = $info['MOD']['#']['ASSIGNMENTS']['0']['#']['ASSIGNMENT'];
    } else {
        $assignments = array();
    }
    
    foreach($assignments as $assignment_info) {
        $assignment_oldid = backup_todb($assignment_info['#']['ID']['0']['#']);
        //$old_createdbyuserid = backup_todb($assignment_info['#']['CREATEDBYUSERID']['0']['#']);
        
        $assignment = new Object();
        $assignment->assignmentdistribid = $newid;
        $assignment->createdbyuserid = backup_todb($assignment_info['#']['CREATEDBYUSERID']['0']['#']);
        $assignment->name = backup_todb($assignment_info['#']['NAME']['0']['#']);
        $assignment->description = backup_todb($assignment_info['#']['DESCRIPTION']['0']['#']);
        $assignment->descriptionformat = backup_todb($assignment_info['#']['DESCRIPTIONFORMAT']['0']['#']);
        $assignment->groupstudentmin = backup_todb($assignment_info['#']['GROUPSTUDENTMIN']['0']['#']);
        $assignment->groupstudentmax = backup_todb($assignment_info['#']['GROUPSTUDENTMAX']['0']['#']);
  //      $assignment->random = backup_todb($assignment_info['#']['RANDOM']['0']['#']);
  //      $assignment->minnumberofrepeats = backup_todb($assignment_info['#']['MINNUMBEROFREPEATS']['0']['#']);
       
        $assignment->available = backup_todb($assignment_info['#']['AVAILABLE']['0']['#']);
        $assignment->approved = backup_todb($assignment_info['#']['APPROVED']['0']['#']);
        $assignment->maxnumberofrepeats = backup_todb($assignment_info['#']['MAXNUMBEROFREPEATS']['0']['#']);

        $user = backup_getid($restore->backup_unique_code, "user", $assignment->createdbyuserid);        
        if ($user) {
            $assignment->createdbyuserid = $user->new_id;
        } else {
            $assignment->createdbyuserid = 0;   //FIXME: Can't be 0. Must be assigned to some user or null
        }
        $assignment_newid = insert_record ("assignmentdistrib_assignments", $assignment);
        if ($assignment_newid) {
            //We have the newid, update backup_ids
            backup_putid($restore->backup_unique_code, "assignmentdistrib_assignments", $assignment_oldid, $assignment_newid);
        } else {
            $status = false;
        }
    }
    return $status;
}


/**
 * Restore penalties
 */ 
function assignmentdistrib_penalties_restore_mods($oldid, $newid, $info, $restore){
    global $CFG;
    $status = true;
    if (isset($info['MOD']['#']['PENALTIES']['0']['#']['PENALTY'])) {
        $penalties = $info['MOD']['#']['PENALTIES']['0']['#']['PENALTY'];
    } else {
        $penalties = array();
    }
    
    foreach($penalties as $penalty_info) {
        $penalty_oldid = backup_todb($penalty_info['#']['ID']['0']['#']);
        
        $penalty = new Object();
        $penalty->assignmentdistrib_id = $newid;
        $penalty->time = backup_todb($penalty_info['#']['TIME']['0']['#']);
        $penalty->penalty_grade = backup_todb($penalty_info['#']['PENALTY_GRADE']['0']['#']);
        
        $penalty_newid = insert_record ("assignmentdistrib_penalties", $penalty);
        
        if ($penalty_newid) {
            //We have the newid, update backup_ids
            backup_putid($restore->backup_unique_code, "assignmentdistrib_penalties", $penalty_oldid, $penalty_newid);
        } else {
            $status = false;
        }
    }
    return $status;
}


//This function restores assignment submissions
function assignmentdistrib_submissions_restore_mods($oldid,$newid,$info,$restore, $assignmenttype) {
    global $CFG;
    $status = true;

    if (isset($info['MOD']['#']['SUBMISSIONS']['0']['#']['SUBMISSION'])) {
        $subs = $info['MOD']['#']['SUBMISSIONS']['0']['#']['SUBMISSION'];
    } else {
        $subs = array();
    }
    
    foreach($subs as $sub_info) {
        $sub_oldid = backup_todb($sub_info['#']['ID']['0']['#']);
        //$old_userid = backup_todb($sub_info['#']['USERID']['0']['#']);
        //$old_gradedbyuserid = backup_todb($sub_info['#']['GRADEDBYUSERID']['0']['#']);
        //$old_assid = backup_todb($sub_info['#']['ASSIGNMENTDISTRIB_ASSIGNMENTS_ID']['0']['#']);
        //$old_groupid = backup_todb($sub_info['#']['ASSIGNMENTDISTRIB_GROUPS_ID']['0']['#']);
        
        $sub = new Object();
        $sub->assignmentdistrib_assignments_id = backup_todb($sub_info['#']['ASSIGNMENTDISTRIB_ASSIGNMENTS_ID']['0']['#']);
        $sub->timecreated = backup_todb($sub_info['#']['TIMECREATED']['0']['#']);
        $sub->timemodified = backup_todb($sub_info['#']['TIMEMODIFIED']['0']['#']);
        $sub->userid = backup_todb($sub_info['#']['USERID']['0']['#']);
        $sub->assignmentdistrib_groups_id = backup_todb($sub_info['#']['ASSIGNMENTDISTRIB_GROUPS_ID']['0']['#']);
        $sub->grade = backup_todb($sub_info['#']['GRADE']['0']['#']);
        $sub->gradedbyuserid = backup_todb($sub_info['#']['GRADEDBYUSERID']['0']['#']);
        $sub->timegraded = backup_todb($sub_info['#']['TIMEGRADED']['0']['#']);
        $sub->comment = backup_todb($sub_info['#']['COMMENT']['0']['#']);
        $sub->commentformat = backup_todb($sub_info['#']['COMMENTFORMAT']['0']['#']);
        $sub->assignmenttype = backup_todb($sub_info['#']['ASSIGNMENTTYPE']['0']['#']);
        $sub->var1 = backup_todb($sub_info['#']['VAR1']['0']['#']);
        $sub->var2 = backup_todb($sub_info['#']['VAR2']['0']['#']);

        if ($assignmenttype == 2){
            print_object($sub->var1);
            print_object($oldid);
            print_object($newid);
            // kako izvuci course module id iz baze ako imam assignmentdistrib id
            // nije jednoznacno u bazi
            $sub->var1 = preg_replace("#^/$oldid/#", "/$newid/", $sub->var1);
            print_object($sub->var1);
        }

        //update userid
        $userid = backup_getid($restore->backup_unique_code, "user", $sub->userid);
        if ($userid) {
            $sub->userid = $userid->new_id;
        } else {
            $sub->userid = 0;
        }


        //update gradedbyuserid
        $gradedbyuserid = backup_getid($restore->backup_unique_code, "user", $sub->gradedbyuserid);
        if ($gradedbyuserid) {
            $sub->gradedbyuserid = $gradedbyuserid->new_id;
        } else {
            $sub->gradedbyuserid = 0;
        }
        
        //update assignment id
        $assid = backup_getid($restore->backup_unique_code, "assignmentdistrib_assignments", $sub->assignmentdistrib_assignments_id);
        if ($assid) {
            $sub->assignmentdistrib_assignments_id = $assid->new_id;
        } else {
            $sub->assignmentdistrib_assignments_id = 0;
        }
            
        //update group id
        if ($sub->assignmentdistrib_groups_id){
            $groupid = backup_getid($restore->backup_unique_code, "assignmentdistrib_groups", $sub->assignmentdistrib_groups_id);
            if ($groupid) {
                $sub->assignmentdistrib_groups_id = $groupid->new_id;
            } else {
                $sub->assignmentdistrib_groups_id = NULL;
            }
        }
        
        $sub_newid = insert_record("assignmentdistrib_submissions", $sub);

        if ($sub_newid) {
            //We have the newid, update backup_ids
            backup_putid($restore->backup_unique_code, "assignmentdistrib_submissions", $sub_oldid, $sub_newid);
        } else {
            $status = false;
        }

    }
    return $status;
}


//This function restores student groups
function assignmentdistrib_groups_restore_mods($oldid, $newid,$info,$restore) {
    global $CFG;

    $status = true;
    if (isset($info['MOD']['#']['GROUPS']['0']['#']['GROUP'])) {
        $groups = $info['MOD']['#']['GROUPS']['0']['#']['GROUP'];
    } else {
        $groups = array();
    }
    
    foreach($groups as $group_info){
       $group_oldid = backup_todb($group_info['#']['ID']['0']['#']);
        
       $group = new Object();
       $group->name = backup_todb($group_info['#']['NAME']['0']['#']);
       $group->leaderuserid = backup_todb($group_info['#']['LEADERUSERID']['0']['#']);
       $group->grade = backup_todb($group_info['#']['GRADE']['0']['#']);
       $group->gradedbyuserid = backup_todb($group_info['#']['GRADEDBYUSERID']['0']['#']);
       $group->timegraded = backup_todb($group_info['#']['TIMEGRADED']['0']['#']);
       $group->comment = backup_todb($group_info['#']['COMMENT']['0']['#']);
       $group->commentformat = backup_todb($group_info['#']['COMMENTFORMAT']['0']['#']);
       $group->allowjoin = backup_todb($group_info['#']['ALLOWJOIN']['0']['#']);
       
       //update leaderuserid
       $leaderuserid = backup_getid($restore->backup_unique_code, "user", $group->leaderuserid);
       if ($leaderuserid) {
           $group->leaderuserid = $leaderuserid->new_id;
       } else {
           $group->leaderuserid = 0;
       }
       
       //update gradedbyuserid
       $gradedbyuserid = backup_getid($restore->backup_unique_code, "user", $group->gradedbyuserid);
       if ($gradedbyuserid) {
           $group->gradedbyuserid = $gradedbyuserid->new_id;
       } else {
           $group->gradedbyuserid = 0;
       }
       
       $group_newid = insert_record ("assignmentdistrib_groups", $group);
        
       if ($group_newid) {
           //We have the newid, update backup_ids
           backup_putid($restore->backup_unique_code, "assignmentdistrib_groups", $group_oldid, $group_newid);
       } else {
           $status = false;
       }
    }
    
    return $status;
}


//This function copies the assignment related info from backup temp dir to course moddata folder,
//creating it if needed and recoding everything (assignment id and user id) 
function assignmentdistrib_restore_files ($oldassid, $newassid, $restore) {
    global $CFG;

    $status = true;
    $todo = false;
    $moddata_path = "";
    $assignmentdistrib_path = "";
    $temp_path = "";

    //First, we check to "course_id" exists and create is as necessary
    //in CFG->dataroot
    $dest_dir = $CFG->dataroot."/".$restore->course_id;

    $status = check_dir_exists($dest_dir,true);

    //Now, locate course's moddata directory
    $moddata_path = $CFG->dataroot."/".$restore->course_id."/".$CFG->moddata;

    //Check it exists and create it
    $status = check_dir_exists($moddata_path,true);

    //Now, locate assignment directory
    if ($status) {
        $assignmentdistrib_path = $moddata_path."/assignmentdistrib";
        //Check it exists and create it
        $status = check_dir_exists($assignmentdistrib_path,true);
    }

    //Now locate the temp dir we are going to restore
    if ($status) {
        $temp_path = $CFG->dataroot."/temp/backup/".$restore->backup_unique_code.
                     "/moddata/assignmentdistrib/".$oldassid;
        //Check it exists
        if (is_dir($temp_path)) {
            $todo = true;
        }
    }

    //If todo, we create the neccesary dirs in course moddata/assignment
    if ($status and $todo) {
        //First this assignment id
        $this_assignmentdistrib_path = $assignmentdistrib_path."/".$newassid;
        $status = check_dir_exists($this_assignmentdistrib_path,true);
//        print_object($temp_path);
//        print_object($this_assignmentdistrib_path);
        $status = backup_copy_file($temp_path, $this_assignmentdistrib_path);
    }
   
    return $status;
}

//Return a content decoded to support interactivities linking. Every module
//should have its own. They are called automatically from
//assignment_decode_content_links_caller() function in each module
//in the restore process
function assignmentdistrib_decode_content_links ($content,$restore) {
    global $CFG;
        
    $result = $content;
            
    //Link to the list of assignments
            
    $searchstring='/\$@(ASSIGNMENTDISTRIBINDEX)\*([0-9]+)@\$/';
    //We look for it
    preg_match_all($searchstring,$content,$foundset);
    //If found, then we are going to look for its new id (in backup tables)
    if ($foundset[0]) {
        //print_object($foundset);                                     //Debug
        //Iterate over foundset[2]. They are the old_ids
        foreach($foundset[2] as $old_id) {
            //We get the needed variables here (course id)
            $rec = backup_getid($restore->backup_unique_code,"course",$old_id);
            //Personalize the searchstring
            $searchstring='/\$@(ASSIGNMENTDISTRIBINDEX)\*('.$old_id.')@\$/';
            //If it is a link to this course, update the link to its new location
            if($rec->new_id) {
                //Now replace it
                $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/assignmentdistrib/index.php?id='.$rec->new_id,$result);
            } else { 
                //It's a foreign link so leave it as original
                $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/assignmentdistrib/index.php?id='.$old_id,$result);
            }
        }
    }

    //Link to assignment view by moduleid

    $searchstring='/\$@(ASSIGNMENTDISTRIBVIEWBYID)\*([0-9]+)@\$/';
    //We look for it
    preg_match_all($searchstring,$result,$foundset);
    //If found, then we are going to look for its new id (in backup tables)
    if ($foundset[0]) {
        //print_object($foundset);                                     //Debug
        //Iterate over foundset[2]. They are the old_ids
        foreach($foundset[2] as $old_id) {
            //We get the needed variables here (course_modules id)
            $rec = backup_getid($restore->backup_unique_code,"course_modules",$old_id);
            //Personalize the searchstring
            $searchstring='/\$@(ASSIGNMENTDISTRIBVIEWBYID)\*('.$old_id.')@\$/';
            //If it is a link to this course, update the link to its new location
            if($rec->new_id) {
                //Now replace it
                $result= preg_replace($searchstring,$CFG->wwwroot.'/mod/assignmentdistrib/view.php?id='.$rec->new_id,$result);
            } else {
                //It's a foreign link so leave it as original
                $result= preg_replace($searchstring,$restore->original_wwwroot.'/mod/assignmentdistrib/view.php?id='.$old_id,$result);
            }
        }
    }

    return $result;
}

//This function makes all the necessary calls to xxxx_decode_content_links()
//function in each module, passing them the desired contents to be decoded
//from backup format to destination site/course in order to mantain inter-activities
//working in the backup/restore process. It's called from restore_decode_content_links()
//function in restore process
function assignmentdistrib_decode_content_links_caller($restore) {
    global $CFG;
    $status = true;

    if ($assignments = get_records_sql ("SELECT a.id, a.description
                               FROM {$CFG->prefix}assignmentdistrib a
                               WHERE a.course = $restore->course_id")) {
        //Iterate over each assignment->description
        $i = 0;   //Counter to send some output to the browser to avoid timeouts
        foreach ($assignments as $assignment) {
            //Increment counter
            $i++;
            $content = $assignment->description;
            $result = restore_decode_content_links_worker($content,$restore);
            if ($result != $content) {
                //Update record
                $assignment->description = addslashes($result);
                $status = update_record("assignmentdistrib",$assignment);
                if (debugging()) {
                    if (!defined('RESTORE_SILENTLY')) {
                        echo '<br /><hr />'.s($content).'<br />changed to<br />'.s($result).'<hr /><br />';
                    }
                }
            }
            //Do some output
            if (($i+1) % 5 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 100 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }
        }
    }
    return $status;
}

//This function converts texts in FORMAT_WIKI to FORMAT_MARKDOWN for
//some texts in the module
function assignmentdistrib_restore_wiki2markdown ($restore) {
    global $CFG;

    $status = true;

    //Convert assignment->description
    if ($records = get_records_sql ("SELECT a.id, a.description, a.format
                                     FROM {$CFG->prefix}assignmentdistrib a,
                                          {$CFG->prefix}backup_ids b
                                     WHERE a.course = $restore->course_id AND
                                           a.format = ".FORMAT_WIKI. " AND
                                           b.backup_code = $restore->backup_unique_code AND
                                           b.table_name = 'assignmentdistrib' AND
                                           b.new_id = a.id")) {
        foreach ($records as $record) {
            //Rebuild wiki links
            $record->description = restore_decode_wiki_content($record->description, $restore);
            //Convert to Markdown
            $wtm = new WikiToMarkdown();
            $record->description = $wtm->convert($record->description, $restore->course_id);
            $record->format = FORMAT_MARKDOWN;
            $status = update_record('assignmentdistrib', addslashes_object($record));
            //Do some output
            $i++;
            if (($i+1) % 1 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 20 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }
        }

    }
    return $status;
}

//This function returns a log record with all the necessay transformations
//done. It's used by restore_log_module() to restore modules log.
function assignmentdistrib_restore_logs($restore,$log) {
    $status = false;
                
    //Depending of the action, we recode different things
    switch ($log->action) {
    case "add":
        if ($log->cmid) {
            //Get the new_id of the module (to recode the info field)
            $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
            if ($mod) {
                $log->url = "view.php?id=".$log->cmid;
                $log->info = $mod->new_id;
                $status = true;
            }
        }
        break;
    case "update":
        if ($log->cmid) {
            //Get the new_id of the module (to recode the info field)
            $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
            if ($mod) {
                $log->url = "view.php?id=".$log->cmid;
                $log->info = $mod->new_id;
                $status = true;
            }
        }
        break;
    case "view":
        if ($log->cmid) {
            //Get the new_id of the module (to recode the info field)
            $mod = backup_getid($restore->backup_unique_code,$log->module,$log->info);
            if ($mod) {
                $log->url = "view.php?id=".$log->cmid;
                $log->info = $mod->new_id;
                $status = true;
            }
        }
        break;
    
    default:
        if (!defined('RESTORE_SILENTLY')) {
            echo "action (".$log->module."-".$log->action.") unknown. Not restored<br />";                 //Debug
        }
        break;
    }

    if ($status) {
        $status = $log;
    }
    return $status;
}
?>
