<?php
/**
 * Backup of assignmentdistrib data
 * 
 * @author Sonja Milicic
 * @author Enola Knežević
 */


/**
 * Backs up all instances of assignmentdistrib
 */
function assignmentdistrib_backup_mods($bf,$preferences){
    $assignmentdistribs = get_records ("assignmentdistrib","course",$preferences->backup_course,"id");
    if ($assignmentdistribs) {
        foreach ($assignmentdistribs as $assignmentdistrib) {
            if (backup_mod_selected($preferences,'assignmentdistrib',$assignmentdistrib->id)) 
                $status = assignment_backup_one_mod($bf,$preferences,$assignmentdistrib);
        }
    }
    echo "All\n";
    
    return $status;
}


 /**
 * Backs up a single instance of assignmentdistrib
 */
function assignmentdistrib_backup_one_mod($bf,$preferences,$assignmentdistrib_id){
    global $CFG;

    $status = true;
    
    //get mod data
    if (is_numeric($assignmentdistrib_id)) {
        $assignmentdistrib = get_record('assignmentdistrib','id',$assignmentdistrib_id);
//        print_object($assignmentdistrib);
//        die();
    }
    
    //Start mod
    fwrite ($bf,start_tag("MOD",3,true));
    
    //write assignmentdistrib XML
    fwrite ($bf,full_tag("ID",4,false,$assignmentdistrib_id));
    fwrite ($bf,full_tag("MODTYPE",4,false,"assignmentdistrib"));
    fwrite ($bf,full_tag("COURSE",4,false,$assignmentdistrib->course));
    fwrite ($bf,full_tag("NAME",4,false,$assignmentdistrib->name));
    fwrite ($bf,full_tag("DESCRIPTION",4,false,$assignmentdistrib->description));
    fwrite ($bf,full_tag("DESCRIPTIONFORMAT",4,false,$assignmentdistrib->descriptionformat));
    fwrite ($bf,full_tag("WORKTYPE",4,false,$assignmentdistrib->worktype));
    fwrite ($bf,full_tag("DISTRIBUTIONTYPE",4,false,$assignmentdistrib->distributiontype));
    fwrite ($bf,full_tag("ASSIGNMENTTYPE",4,false,$assignmentdistrib->assignmenttype));
    fwrite ($bf,full_tag("ALLOWGROUPMARKET",4,false,$assignmentdistrib->allowgroupmarket));
    fwrite ($bf,full_tag("ALLOWSTUDENTCHANGE",4,false,$assignmentdistrib->allowstudentchange));
    fwrite ($bf,full_tag("PREVENTLATE",4,false,$assignmentdistrib->preventlate));
    fwrite ($bf,full_tag("TIMEDUE",4,false,$assignmentdistrib->timedue));
    fwrite ($bf,full_tag("TIMEAVAILABLE",4,false,$assignmentdistrib->timeavailable));
    fwrite ($bf,full_tag("LEADERCANGRADE",4,false,$assignmentdistrib->leadercangrade));
    fwrite ($bf,full_tag("SUGGESTABLE",4,false,$assignmentdistrib->suggestable));
    fwrite ($bf,full_tag("GRADE",4,false,$assignmentdistrib->grade));
    fwrite ($bf,full_tag("TIMEMODIFIED",4,false,$assignmentdistrib->timemodified));

    //write penalties
    fwrite ($bf,start_tag("PENALTIES",4,true));
    $status = $status && assignmentdistrib_backup_penalties($bf,$preferences,$assignmentdistrib_id);
    fwrite ($bf,end_tag("PENALTIES",4,true));

    if($status) {
        //write assignments
        fwrite ($bf,start_tag("ASSIGNMENTS",4,true));
        $status = $status && assignmentdistrib_backup_assignments($bf,$preferences,$assignmentdistrib_id);
        fwrite ($bf,end_tag("ASSIGNMENTS",4,true));

        if($status) {
            //backup user data (groups, submissions) if specified
            if ($preferences->mods['assignmentdistrib']->instances[$assignmentdistrib_id]->userinfo == '1') {
                fwrite ($bf,start_tag("SUBMISSIONS",4,true));
                $status = $status && assignmentdistrib_backup_submissions($bf,$preferences,$assignmentdistrib_id);
                fwrite ($bf,end_tag("SUBMISSIONS",4,true));
                if ($status){
                    fwrite ($bf,start_tag("GROUPS",4,true));
                    $status = $status && assignmentdistrib_backup_groups($bf,$preferences,$assignmentdistrib_id);
                    fwrite ($bf,end_tag("GROUPS",4,true));
                }
            }
        }
    }

    if (backup_userdata_selected($preferences,'assignmentdistrib',$assignmentdistrib_id)) {
            $status = assignmentdistrib_backup_files_instance($bf,$preferences,$assignmentdistrib_id);
    }

        //end mod
    $status = $status && fwrite ($bf,end_tag("MOD",3,true));
    
    return $status;
}


/**
 * Backs up assignments
 */
function assignmentdistrib_backup_assignments($bf,$preferences, $assignmentdistrib_id){
    global $CFG;
$query = "SELECT * FROM {$CFG->prefix}assignmentdistrib_assignments WHERE assignmentdistribid = {$assignmentdistrib_id}";
    $assignments = get_records_sql($query);
//            print_object($assignments);
//                die();
    $status = true;
    foreach ($assignments as $assignment) {
//        print_object($assignment);
        $status = $status && fwrite ($bf,start_tag("ASSIGNMENT",5,true));
        $status = $status && fwrite ($bf,full_tag("ID",6,false,$assignment->id));
        $status = $status && fwrite ($bf,full_tag("ASSIGNMENTDISTRIBID",6,false,$assignment->assignmentdistribid));
        $status = $status && fwrite ($bf,full_tag("CREATEDBYUSERID",6,false,$assignment->createdbyuserid));
        $status = $status && fwrite ($bf,full_tag("NAME",6,false,$assignment->name));
        $status = $status && fwrite ($bf,full_tag("DESCRIPTION",6,false,$assignment->description));
        $status = $status && fwrite ($bf,full_tag("DESCRIPTIONFORMAT",6,false,$assignment->descriptionformat));
        $status = $status && fwrite ($bf,full_tag("GROUPSTUDENTMIN",6,false,$assignment->groupstudentmin));
        $status = $status && fwrite ($bf,full_tag("GROUPSTUDENTMAX",6,false,$assignment->groupstudentmax));
//        $status = $status && fwrite ($bf,full_tag("RANDOM",6,false,$assignment->random));
//        $status = $status && fwrite ($bf,full_tag("MINNUMBEROFREPEATS",6,false,$assignment->minnumberofrepeats));
       
        $status = $status && fwrite ($bf,full_tag("AVAILABLE",6,false,$assignment->available));
        $status = $status && fwrite ($bf,full_tag("APPROVED",6,false,$assignment->approved));
        $status = $status && fwrite ($bf,full_tag("MAXNUMBEROFREPEATS",6,false,$assignment->maxnumberofrepeats));
        $status = $status && fwrite ($bf,end_tag("ASSIGNMENT",5,true));
        if (!$status)
            return $status;
    }

    return $status;
}


/**
 * Backs up groups
 */
function assignmentdistrib_backup_groups($bf,$preferences, $assignmentdistrib_id){
    global $CFG;
    
    $status = true;
    $groups = get_records_sql("SELECT *
                                FROM {$CFG->prefix}assignmentdistrib_groups
                                WHERE
                                    id IN (SELECT assignmentdistrib_groups_id
                                            FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                                JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignments
                                                    ON submissions.assignmentdistrib_assignments_id = assignments.id
                                            WHERE assignmentdistribid = {$assignmentdistrib_id}
                                            )"
    );
//        print_object($groups);

    if(is_array($groups)){
        foreach ($groups as $group) {
            $status = $status && fwrite ($bf,start_tag("GROUP",5,true));
            $status = $status && fwrite ($bf,full_tag("ID",6,false,$group->id));
            $status = $status && fwrite ($bf,full_tag("NAME",6,false,$group->name));
            $status = $status && fwrite ($bf,full_tag("LEADERUSERID",6,false,$group->leaderuserid));
            $status = $status && fwrite ($bf,full_tag("GRADE",6,false,$group->grade));
            $status = $status && fwrite ($bf,full_tag("GRADEDBYUSERID",6,false,$group->gradedbyuserid));
            $status = $status && fwrite ($bf,full_tag("TIMEGRADED",6,false,$group->timegraded));
            $status = $status && fwrite ($bf,full_tag("COMMENT",6,false,$group->comment));
            $status = $status && fwrite ($bf,full_tag("COMMENTFORMAT",6,false,$group->commentformat));
            $status = $status && fwrite ($bf,full_tag("ALLOWJOIN",6,false,$group->allowjoin));
            $status = $status && fwrite ($bf,end_tag("GROUP",5,true));
            if (!$status)
                return $status;
        }
    }
    return $status;
}


/**
 * Backs up submissions
 */
function assignmentdistrib_backup_submissions($bf,$preferences,$assignmentdistrib_id){
    global $CFG;
    $query= "SELECT * FROM {$CFG->prefix}assignmentdistrib_submissions WHERE assignmentdistrib_assignments_id 
        IN (SELECT id FROM {$CFG->prefix}assignmentdistrib_assignments WHERE assignmentdistribid={$assignmentdistrib_id})";
   
    $submissions = get_records_sql($query);
    $status = true;
    if($submissions){
        foreach ($submissions as $submission){
            $status = $status && fwrite ($bf,start_tag("SUBMISSION",5,true));
            $status = $status && fwrite ($bf,full_tag("ID",6,false,$submission->id));
            $status = $status && fwrite ($bf,full_tag("ASSIGNMENTDISTRIB_ASSIGNMENTS_ID",6,false,$submission->assignmentdistrib_assignments_id));
            $status = $status && fwrite ($bf,full_tag("USERID",6,false,$submission->userid));
            $status = $status && fwrite ($bf,full_tag("ASSIGNMENTDISTRIB_GROUPS_ID",6,false,$submission->assignmentdistrib_groups_id));
            $status = $status && fwrite ($bf,full_tag("TIMECREATED",6,false,$submission->timecreated));
            $status = $status && fwrite ($bf,full_tag("TIMEMODIFIED",6,false,$submission->timemodified));
            $status = $status && fwrite ($bf,full_tag("GRADE",6,false,$submission->grade));
            $status = $status && fwrite ($bf,full_tag("GRADEDBYUSERID",6,false,$submission->gradedbyuserid));
            $status = $status && fwrite ($bf,full_tag("TIMEGRADED",6,false,$submission->timegraded));
            $status = $status && fwrite ($bf,full_tag("COMMENT",6,false,$submission->comment));
            $status = $status && fwrite ($bf,full_tag("COMMENTFORMAT",6,false,$submission->commentformat));
            $status = $status && fwrite ($bf,full_tag("ASSIGNMENTTYPE",6,false,$submission->assignmenttype));
            $status = $status && fwrite ($bf,full_tag("VAR1",6,false,$submission->var1));
            $status = $status && fwrite ($bf,full_tag("VAR2",6,false,$submission->var2));
            $status = $status && fwrite ($bf,end_tag("SUBMISSION",5,true));
            if (!$status)
                return $status;
}
    }
    return $status;
}


/**
* Backs up penalties
*/
function assignmentdistrib_backup_penalties($bf,$preferences,$assignmentdistrib_id){
    global $CFG;
    $status = true;

    $penalties = get_records_sql ("SELECT * FROM {$CFG->prefix}assignmentdistrib_penalties 
        WHERE assignmentdistrib_id = {$assignmentdistrib_id}");
    if (is_array($penalties)){
        foreach ($penalties as $penalty) {
            $status = $status && fwrite ($bf,start_tag("PENALTY",5,true));
            $status = $status && fwrite ($bf,full_tag("ID",6,false,$penalty->id));
            $status = $status && fwrite ($bf,full_tag("ASSIGNMENTDISTRIB_ID",6,false,$penalty->assignmentdistrib_id));
            $status = $status && fwrite ($bf,full_tag("TIME",6,false,$penalty->time));
            $status = $status && fwrite ($bf,full_tag("PENALTY_GRADE",6,false,$penalty->penalty_grade));
            $status = $status && fwrite ($bf,end_tag("PENALTY",5,true));
            if (!$status) return $status;
        }
    }
    return $status;        
}

/**
 * Backs up files
 */
function assignmentdistrib_backup_files($bf,$preferences) {
    global $CFG;

    $status = true;
    $status = check_and_create_moddata_dir($preferences->backup_unique_code);
    if ($status) {
        if (is_dir($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/assignmentdistrib")) {
            $status = backup_copy_file($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/assignmentdistrib",
                                       $CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/assignmentdistrib");
        }
    }
    return $status;
} 


function assignmentdistrib_backup_files_instance($bf,$preferences,$instanceid) {
    global $CFG;

    $status = true;

    $status = check_and_create_moddata_dir($preferences->backup_unique_code);
    $status = $status && check_dir_exists($CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/assignmentdistrib/",true);
    if ($status) {
        if (is_dir($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/assignmentdistrib/".$instanceid)) {
            $status = backup_copy_file($CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/assignmentdistrib/".$instanceid,
                                       $CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/assignmentdistrib/".$instanceid);
        }
    }

    return $status;

}




/**
 * Generates an array of course and user data information used to select which instances to backup 
 *(and whether to include user data or not)
 */
function assignmentdistrib_check_backup_mods($course,$user_data=false,$backup_unique_code,$instances=null){
    if (!empty($instances) && is_array($instances) && count($instances)) {
        $info = array();
        foreach ($instances as $id => $instance) {
            $info += assignmentdistrib_check_backup_mods_instances($instance,$backup_unique_code);
        }
        return $info;
    }
    //course data
    $info[0][0] = get_string("modulenameplural","assignmentdistrib");
    if ($ids = assignmentdistrib_ids ($course)) {
        $info[0][1] = count($ids);
    } else {
        $info[0][1] = 0;
    }

    //userdata (submissions and groups)
    if ($user_data) {
        $info[1][0] = get_string("submissions","assignmentdistrib");
        $info[2][0] = get_string("groups","assignmentdistrib");

        if ($ids = assignmentdistrib_userdata_ids_by_course ($course)) { 
            $info[1][1] = count($ids[0]);
            $info[2][1] = count($ids[1]);
        } else {
            $info[1][1] = 0;
            $info[2][1] = 0;
        }
        
    }
    return $info;
}


/**
* Generates an array of course and user data information for a specific instance
*/
function assignmentdistrib_check_backup_mods_instances($instance,$backup_unique_code){
    $info[$instance->id.'0'][0] = '<b>'.$instance->name.'</b>';
    $info[$instance->id.'0'][1] = '';
    if (!empty($instance->userdata)) {
        $info[$instance->id.'1'][0] = get_string("submissions","assignmentdistrib");
        $info[$instance->id.'1'][1] = get_string("groups","assignmentdistrib");

        if ($ids = assignmentdistrib_userdata_ids_by_instance ($instance->id)) {
            $info[$instance->id.'1'][1] = count($ids[0]);
            $info[$instance->id.'1'][2] = count($ids[1]);

        } else {
            $info[$instance->id.'1'][1] = 0;
            $info[$instance->id.'1'][2] = 0;
        }
       
    }
    return $info;
}


/**
 * Returns an array of assignmentdistrib ids by course
 */ 
function assignmentdistrib_ids ($course_id) {
    global $CFG;

    return get_records_sql("SELECT id, course FROM {$CFG->prefix}assignmentdistrib
                             WHERE course = {$course_id}");
}


/**
 * Returns an array of submission and group ids by course 
 */
function assignmentdistrib_userdata_ids_by_course($course_id){
    global $CFG;
    
    $ids = array();
    //submissions
    $ids[0] = get_records_sql("SELECT {$CFG->prefix}assignmentdistrib_submissions.id, assignmentdistrib_assignments_id FROM {$CFG->prefix}assignmentdistrib_submissions
        JOIN {$CFG->prefix}assignmentdistrib_assignments ON {$CFG->prefix}assignmentdistrib_assignments.id = assignmentdistrib_assignments_id
        WHERE assignmentdistribid IN (SELECT id FROM {$CFG->prefix}assignmentdistribs WHERE course = {$course_id})");
    //groups
    $ids[1] = get_records_sql("SELECT {$CFG->prefix}assignmentdistrib_groups.id, assignmentdistrib_assignments_id FROM {$CFG->prefix}assignmentdistrib_groups
        JOIN {$CFG->prefix}assignmentdistrib_submissions ON {$CFG->prefix}assignmentdistrib_groups.id = assignmentdistrib_groups_id
        JOIN {$CFG->prefix}assignmentdistrib_assignments ON {$CFG->prefix}assignmentdistrib_assignments.id = assignmentdistrib_assignments_id
        WHERE assignmentdistribid IN (SELECT id FROM {$CFG->prefix}assignmentdistribs WHERE course = {$course_id})");
    
    return $ids;

}


/**
 * Returns an array of submission and group ids by instance
 */
function assignmentdistrib_userdata_ids_by_instance($instance_id){
    global $CFG;
    
    $ids = array();
    //submissions
    $ids[0] = get_records_sql("SELECT {$CFG->prefix}assignmentdistrib_submissions.id AS id, assignmentdistrib_assignments_id FROM {$CFG->prefix}assignmentdistrib_submissions
        JOIN {$CFG->prefix}assignmentdistrib_assignments ON {$CFG->prefix}assignmentdistrib_assignments.id = assignmentdistrib_assignments_id
        WHERE assignmentdistribid = {$instance_id}");
    //groups
    $ids[1] = get_records_sql("SELECT {$CFG->prefix}assignmentdistrib_groups.id, assignmentdistrib_assignments_id FROM {$CFG->prefix}assignmentdistrib_groups
        JOIN {$CFG->prefix}assignmentdistrib_submissions ON {$CFG->prefix}assignmentdistrib_groups.id = assignmentdistrib_groups_id
        JOIN {$CFG->prefix}assignmentdistrib_assignments ON {$CFG->prefix}assignmentdistrib_assignments.id = assignmentdistrib_assignments_id
        WHERE assignmentdistribid = {$instance_id}");
    
    return $ids;

}


/**
 * Recode links to ensure they work when reimported
 */
function assignmentdistrib_encode_content_links ($content,$preferences){
    global $CFG;

    $base = preg_quote($CFG->wwwroot,"/");

    //Link to the list of assignments
    $buscar="/(".$base."\/mod\/assignmentdistrib\/index.php\?id\=)([0-9]+)/";
    $result= preg_replace($buscar,'$@ASSIGNMENTDISTRIBINDEX*$2@$',$content);

    //Link to view by moduleid
    $buscar="/(".$base."\/mod\/assignmentdistrib\/view.php\?id\=)([0-9]+)/";
    $result= preg_replace($buscar,'$@ASSIGNMENTDISTRIBVIEWBYID*$2@$',$result);

    return $result;
}
?>
