<?php
/**
 * This page prints a particular instance of assignmentdistrib
 * 
 * @author Krešimir Kroflin
 * @author Nikola Tanković
 * @author Sonja Miličić
 * @author Enola Knežević
 * @package assignmentdistrib
 **/
    require_once("../../config.php");
    require_once("lib.php");
    require_once($CFG->libdir.'/gradelib.php');
    require_once("../../grade/querylib.php");
    require_once("../../message/lib.php");
    require_once("../../lib/questionlib.php");
    
    $id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
    $a  = optional_param('a', 0, PARAM_INT);  // assignmentdistrib ID

    $notdeleted = false;
    $notmoved = false;

//
//
//    $coursemodule = get_coursemodule_from_id('assignmentdistrib', $id);
//    $thiscontext = get_context_instance(CONTEXT_MODULE, $id);
//    print_object($coursemodule);
//    print_object($thiscontext);
//    print_object(get_filesdir_from_context($thiscontext));
//    die();
    
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
    add_to_log($course->id, "assignmentdistrib", "view", "view.php?id=$cm->id", "$assignmentdistrib->id");

/// Print the page header
    /*
    if ($course->category) {
        $header_navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> -> ";
    } else {
        $header_navigation = '';
    }
    */

    $strassignmentdistribs = get_string("modulenameplural", "assignmentdistrib");
    $strassignmentdistrib  = get_string("modulename", "assignmentdistrib");

    $header_title = "$course->shortname: $assignmentdistrib->name";
    $header_heading = "$course->fullname";
    //$header_navigation .= "<a href=index.php?id=$course->id>$strassignmentdistribs</a>";
    $header_update_module_button =  update_module_button($cm->id, $course->id, $strassignmentdistrib);
    $header_navmenu = navmenu($course, $cm);
    
    /*
    *array(
      array('name' => $linktext1, 'link' => $url1, 'type' => $linktype1),
      array('name' => $linktext2, 'link' => $url2, 'type' => $linktype2)
  )*/
    
    $isteacher = isteacher($course->id); //TODO: uloge
    $isstudent = isstudent($course->id);

    
    $form = new object();
    $form->coursemodule = $cm->id;
    $form->section      = $cm->section;
    $form->course       = $cm->course;
    $form->instance     = $cm->instance;
    $form->sesskey      = !empty($USER->id) ? $USER->sesskey : '';
    
//    if(optional_param('gpss', false, PARAM_BOOL))
//    {
//        include('insert_gpss.php');
//    }
//    if(optional_param('atlas', false, PARAM_BOOL))
//    {
//        include('insert_atlas.php');
//    }

    $course_students = get_course_students($cm->course, 'u.lastname, u.firstname, u.username'
                                                                , '', 0, 99999, '', '', null, '', 'u.id, firstname, lastname, email, username', '');
    if($isstudent)
    {
        $studentid = $USER->id;
        //$header_navigation .= " -> $assignmentdistrib->name";
        $nav = build_navigation(array(), $cm);
        //print_header($header_title, $header_heading, $header_navigation, '', '', true, $header_update_module_button, $header_navmenu);
        print_header($header_title, $header_heading, $nav, '', '', true, $header_update_module_button, $header_navmenu);
        
        $record = get_record('assignmentdistrib', 'id', $cm->instance);
        $time = time();

        $isavailable = ($record->timeavailable < $time) ? true : false;
        $islate = ($record->timedue != 0 && $record->timedue < $time) ? true : false;
        
        $assignmentsubmission = get_record_sql("SELECT *, dbas.id AS submissionid
                                                FROM {$CFG->prefix}assignmentdistrib_submissions AS dbas
                                                    , {$CFG->prefix}assignmentdistrib_assignments AS dbaa
                                                WHERE  dbas.assignmentdistrib_assignments_id = dbaa.id
                                                    AND dbas.userid={$USER->id}
                                                    AND dbaa.assignmentdistribid={$cm->instance}
                                                ");


        $assignmentproposed = get_record_sql("SELECT *
                                                FROM {$CFG->prefix}assignmentdistrib_assignments
                                                WHERE  approved=0
                                                    AND createdbyuserid={$USER->id}
                                                    AND assignmentdistribid={$cm->instance}
                                                ");
//        print_object($assignmentproposed);
        //die();

        $mode = optional_param('mode', '', PARAM_ALPHA);
        $view_student = true;
        
        if(!$assignmentsubmission && !$assignmentproposed && $mode == 'get' && confirm_sesskey())
        {
            $cancel = optional_param('cancel', false, PARAM_BOOL);
            if($cancel) {
                redirect("view.php?id=$cm->id", '', 0);
            }
            $chooseassignment = optional_param('chooseassignment', null, PARAM_RAW);
            $chooseassignment_ind = optional_param('chooseassignment_ind', null, PARAM_RAW);
            $joingroupassignment = optional_param('joingroup', null, PARAM_RAW);
            
            $back = optional_param('back', false, PARAM_BOOL);
            if($chooseassignment !== null && !$back)
            {
                $assignmentdistribtype = required_param('assignmentdistribtype', PARAM_INT);
                $view_student = false;
                // Assignment chosen -> proceed with group creation
                if(is_array($chooseassignment) && count($chooseassignment) == 1)
                {
                    foreach($chooseassignment as $assignmentid=>$value) { break; }
                    $assignment = get_record('assignmentdistrib_assignments', 'id', $assignmentid, 'assignmentdistribid', $cm->instance);
                    if($assignment === false) {
                        error('Assignment is missing');
                    }
                    
                    // assigned students
                    $assignedstudents = get_records_sql("SELECT dbas.userid AS userid, 0 AS moodle_bug
                                                FROM {$CFG->prefix}assignmentdistrib_submissions AS dbas
                                                    , {$CFG->prefix}assignmentdistrib_assignments AS dbaa
                                                WHERE  dbas.assignmentdistrib_assignments_id = dbaa.id
                                                    AND dbaa.assignmentdistribid = {$cm->instance}
                                                ");

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
                    
                    if(!in_array($USER->id, $unasignedstudentsid)) {
                        redirect("view.php?id=$cm->id", get_string('alreadyassinged', 'assignmentdistrib'), 360);
                    }
                    
                    $groupmembers = optional_param('groupmembers', array(), PARAM_RAW);
                    if(!is_array($groupmembers)) {
                        error('Not array');
                    }
                    
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
                    else if(optional_param('remove', false, PARAM_BOOL)) {
                        $groupmembers_remove = optional_param('groupmembers_remove', array(), PARAM_RAW);
                        foreach($groupmembers_remove as $key=>$value) {
                            if(!is_numeric($value)) {
                                error('Not numeric');
                            }
                        }
                        $groupmembers = array_diff($groupmembers, $groupmembers_remove);
                    }
                                           
                    
                    
                    $groupmembers[] = $USER->id;
                    $groupmembers = array_unique($groupmembers);
                    
                    // check if all members are unassigned
                    $groupmemberscheck = array_diff($groupmembers, $unasignedstudentsid);
                    if(count($groupmemberscheck) != 0) {
                        $error = true;
                        $errormembercheck = true;
                    }
                    
                    
                    // check min/max students in a group
                    $groupmemberscount = count($groupmembers);
                    if($groupmemberscount < $assignment->groupstudentmin) {
                        $errormin = true;
                        $error = true;
                    }
                    else if($groupmemberscount > $assignment->groupstudentmax) {
                        $errormax = true;
                        $error = true;
                    }
                    
                    $market_enabled = optional_param('allowother', 0, PARAM_INT);
                    
                    if ((!isset($error) || !$error || ($market_enabled &&(!isset($errormax) || !$errormax))) && optional_param('getfinal', false, PARAM_BOOL))
                    {
                        // insert: submissions, groups

                        $rollback = false;
                        $newrecord_group = new object();
                        $newrecord_group->name = '';
                        $newrecord_group->leaderuserid = $USER->id;
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
                            $newrecord_submissions->timecreated = $time;
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
                        
                        redirect("view.php?id=$cm->id", get_string('assigned', 'assignmentdistrib'), 10);
                    }
                    
                    include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose_group.html");
                }
                else
                {
                    error('Assignment choosing failed');
                }
            }
            else if($chooseassignment_ind !== null && !$back)
            {
                //INDIVIDUAL CHOOSE ASSIGNMENT
                
                foreach($chooseassignment_ind as $assignmentid=>$value) { break; }
                $newrecord = new object();
                $newrecord->assignmentdistrib_assignments_id = $assignmentid;
                $newrecord->userid = $USER->id;
                $newrecord->timecreated = time();
                
                if(!insert_record('assignmentdistrib_submissions', $newrecord, false)) {
                    error('Unable to create record in database');
                }
                
                redirect("view.php?id=$cm->id", get_string('assigned', 'assignmentdistrib'), 10);
                
                $noassignment = false;
            }
            else if($joingroupassignment !== null && !$back) {            
                $assignmentdistribtype = required_param('assignmentdistribtype', PARAM_INT);
                $view_student = false;
                
                // Assignment choosen -> add user to group and assignment
                if(is_array($joingroupassignment) && count($joingroupassignment) == 1)
                {
                    foreach($joingroupassignment as $groupid=>$value) { break; }
                    $assignmentid = get_field_sql("SELECT assignmentdistrib_assignments_id FROM {$CFG->prefix}assignmentdistrib_submissions WHERE assignmentdistrib_groups_id = $groupid");
                    
                    // gore DISTINCT

                    $assignment = get_record('assignmentdistrib_assignments', 'id', $assignmentid, 'assignmentdistribid', $cm->instance);
                    if($assignment === false) {
                        error('Assignment is missing');
                    }
                    
                    $newrecord_submissions = new object();
                    $newrecord_submissions->assignmentdistrib_assignments_id = $assignment->id;
                    $newrecord_submissions->userid = $USER->id;
                    $newrecord_submissions->assignmentdistrib_groups_id = $groupid;
                    $newrecord_submissions->timecreated = $time;
                    $insertedid = insert_record('assignmentdistrib_submissions', $newrecord_submissions, true);
                    
                    redirect("view.php?id=$cm->id", get_string('assigned', 'assignmentdistrib'), 10);
                }
            } else {
                $view_student = false;
                $assignmentdistribtype = null;
                if($back) {
                    $assignmentdistribtype = required_param('assignmentdistribtype', PARAM_INT);
                }
                else
                {
                    $submit = required_param('submit', PARAM_RAW);
                    if(is_array($submit) && count($submit) == 1) {
                        foreach($submit as $assignmentdistribtype=>$value) { break; }
                    } else {
                        error('Assignment choosing failed');
                    }
                }
                
                if($assignmentdistribtype == ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_RANDOM)
                {
                    if($record->distributiontype != ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_RANDOM) {
                        error('Distribution Type Mismatch');
                    }
                    else
                    {
                        if($record->worktype != ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
                            error('Sorry, this distribution is allowed only for individual assignments (for now).');
                        }
                        $assignmentids = get_records_sql("SELECT ada.id,
                                                                (
                                                                    SELECT COUNT(*) FROM {$CFG->prefix}assignmentdistrib_submissions
                                                                        WHERE assignmentdistrib_assignments_id=ada.id
                                                                ) AS count
                                                            FROM {$CFG->prefix}assignmentdistrib_assignments AS ada
                                                            WHERE assignmentdistribid={$cm->instance} AND available = 1
                                                            ORDER BY 2");
//                        print_object($assignmentids);
//                        die();
                        if($assignmentids)
                        {
                            $ids = array();
                            $count_min = null;
                            foreach ($assignmentids as $value){
                                if($count_min === null || $count_min > $value->count) {
                                    $count_min = $value->count;

                                } 
                            }
                            //print_object($count_min);
                            foreach($assignmentids as $value)
                            {
                                if($count_min === null || $count_min == $value->count) {
                                    $ids[] = $value->id;
                                    
                                }
                            }
                            
                            $selected = $ids[mt_rand(0, count($ids)-1)];
                            
                            $newrecord = new object();
                            $newrecord->assignmentdistrib_assignments_id = $selected;
                            $newrecord->userid = $USER->id;
                            $newrecord->timecreated = time();
                            
                            if(!insert_record('assignmentdistrib_submissions', $newrecord, false)) {
                                error('Unable to create record in database');
                            }
                            
                            redirect("view.php?id=$cm->id", get_string('assigned', 'assignmentdistrib'), 10);
                            
                            $noassignment = false;
                        }
                        else
                        {
                            print_simple_box_start('center', '', '', 5, 'generalbox', 'description');
                            echo "<center>";
                            print_string('no_available_assignments', 'assignmentdistrib');
                            //print_object(get_teacher($course->id));
                            $message = get_string('course', 'assignmentdistrib') . ": $course->fullname\n" .
                                get_string('assignmentdistribution', 'assignmentdistrib') . ": $assignmentdistrib->name\n" .
                                get_string('noavailableassignments', 'assignmentdistrib');
                            message_post_message(get_complete_user_data('id', $studentid), get_teacher($course->id), $message, FORMAT_PLAIN, 'direct');
//                            message_post_message($USER,
//                                get_teacher($course->id),
//                                //get_complete_user_data('id', $subid->userid),
//                                "Na tečaju $course->fullname, assignment distributionu $assignmentdistrib->name nema dostupnih zadataka za podjelu.",
//                                FORMAT_PLAIN,
//                                'direct'
//                             );
                            echo "</center>";
                            redirect("view.php?id=$cm->id", get_string('continue', 'assignmentdistrib'), 3600);
                            print_simple_box_end();
                            $noassignment = true;
                        }
                        
                    }
                }
                else if($assignmentdistribtype == ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSENOREPEAT)
                {
                    if($record->distributiontype != ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSENOREPEAT) {
                        error('Distribution Type Mismatch');
                    }
                    else
                    {
                        if($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
                            // TODO provjera koji su dostupni je u htmlu
                            //$assignments = get_records();
                            include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose.html");
                        }
                        else if ($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_TEAM) {
                            // dodatni za market
                            if($record->allowgroupmarket) {
                                $available_group_assignments = get_records_sql("
                                                                    SELECT ada.id as assignmentd_id, adg.id as group_id, ads.id as submission_id, count(*) as member_count
                                                                    FROM {$CFG->prefix}assignmentdistrib_groups as adg
                                                                        INNER JOIN {$CFG->prefix}assignmentdistrib_submissions as ads
                                                                            ON adg.id = ads.assignmentdistrib_groups_id
                                                                        INNER JOIN {$CFG->prefix}assignmentdistrib_assignments as ada
                                                                            ON ada.id = ads.assignmentdistrib_assignments_id
                                                                    WHERE ada.assignmentdistribid = {$assignmentdistrib->id} AND adg.allowjoin = 1
                                                                    GROUP BY 1, 2
                                                                ");
//                                print_object($available_group_assignments);
                                $assignments2 = array();
                                
                                if($available_group_assignments){
                                
                                    foreach($available_group_assignments as $as) {
    //                                    $temp = get_record_select('assignmentdistrib_assignments', "assignmentdistribid = {$cm->instance} AND groupstudentmax > {$as->member_count}", '*');
    //                                    if ($temp) {
    //
    //                                        $temp->member_count = $as->member_count;
    //                                        $temp->group_id = $as->group_id;
    //                                        print_object($temp);
    //
    //                                        $assignments2[] = $temp;
    //                                      print_object($as->member_count);
                                          $temp = get_records_select('assignmentdistrib_assignments', "assignmentdistribid = {$cm->instance} AND groupstudentmax > {$as->member_count}", null , '*' ,null, null);
                                          if ($temp) {

                                            foreach ($temp as $opengroup){

                                                if ($opengroup->id ==$as->assignmentd_id){
                                                    $opengroup->member_count = $as->member_count;
                                                    $opengroup->group_id = $as->group_id;
        //                                                   print_object($opengroup);
                                                    $assignments2[] = $opengroup;

                                                 }
                                             }
                                          }
                                       }

                                   }
                                }

                            }
                            include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose.html");
                    }
                    
                }
                else if($assignmentdistribtype == ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSEREPEAT)
                {
                    if($record->distributiontype != ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSEREPEAT) {
                        error('Distribution Type Mismatch');
                    }
                    else
                    {
                        if($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
                            include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose.html");
                        }

                        else if ($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_TEAM) {
//                            print_object($assignmentdistrib);
                            if($record->allowgroupmarket) {
                                $available_group_assignments = get_records_sql("
                                                                    SELECT ada.id as assignmentd_id, adg.id as group_id,  ads.id as submission_id, count(*) AS member_count
                                                                    FROM {$CFG->prefix}assignmentdistrib_groups as adg
                                                                        INNER JOIN {$CFG->prefix}assignmentdistrib_submissions as ads
                                                                            ON adg.id = ads.assignmentdistrib_groups_id
                                                                        INNER JOIN {$CFG->prefix}assignmentdistrib_assignments as ada
                                                                            ON ada.id = ads.assignmentdistrib_assignments_id
                                                                    WHERE ada.assignmentdistribid = {$assignmentdistrib->id} AND adg.allowjoin = 1
                                                                    GROUP BY 1, 2
                                                                ");
                                $assignments2 = array();
//                                print_object($available_group_assignments);
                                
                                if ($available_group_assignments){
                                    
                                    

                                    foreach($available_group_assignments as $as) {
                                        
                                        $temp = get_records_select('assignmentdistrib_assignments', "assignmentdistribid = {$cm->instance} AND groupstudentmax > {$as->member_count}", null , '*' ,null, null);
                                        if ($temp) {
                                           
                                            foreach ($temp as $opengroup){
                                               
                                                if ($opengroup->id ==$as->assignmentd_id){
                                                    $opengroup->member_count = $as->member_count;
                                                    $opengroup->group_id = $as->group_id;
//                                                    print_object($opengroup);
                                                    $assignments2[] = $opengroup;

                                                }
                                            }

                                        }
                                    }
                                
                                }
                            }
                            include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose.html");
                        }
                    }
                }
                else if($assignmentdistribtype == ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSEREPEATMAX)
                {
                    if($record->distributiontype != ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSEREPEATMAX) {
                        error('Distribution Type Mismatch');
                    }
                    else
                    {
                        if($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL) {
                            include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose.html");
                        }
                        else if ($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_TEAM) {
                            // dodatni za market
                            if($record->allowgroupmarket) {
                                $available_group_assignments = get_records_sql("
                                                                    SELECT ada.id as assignmentd_id, adg.id as group_id, ads.id as submission_id, count(*) as member_count
                                                                    FROM {$CFG->prefix}assignmentdistrib_groups as adg
                                                                        INNER JOIN {$CFG->prefix}assignmentdistrib_submissions as ads
                                                                            ON adg.id = ads.assignmentdistrib_groups_id
                                                                        INNER JOIN {$CFG->prefix}assignmentdistrib_assignments as ada
                                                                            ON ada.id = ads.assignmentdistrib_assignments_id
                                                                    WHERE ada.assignmentdistribid = {$assignmentdistrib->id} AND adg.allowjoin = 1
                                                                    GROUP BY 1, 2
                                                                ");
                                $assignments2 = array();
                                if ($available_group_assignments){
                                    foreach($available_group_assignments as $as) {
                                        $temp = get_records_select('assignmentdistrib_assignments', "assignmentdistribid = {$cm->instance} AND groupstudentmax > {$as->member_count}", null , '*' ,null, null);
                                        //$temp = get_record_select('assignmentdistrib_assignments', "assignmentdistribid = {$cm->instance} AND groupstudentmax > {$as->member_count}", '*');
                                        foreach ($temp as $opengroup){

                                            if ($opengroup->id ==$as->assignmentd_id){
                                                $opengroup->member_count = $as->member_count;
                                                $opengroup->group_id = $as->group_id;
//                                                        print_object($opengroup);
                                                $assignments2[] = $opengroup;

                                                    }
                                                }
    //                                    if (!$temp) continue;
    //                                    $temp->member_count = $as->member_count;
    //                                    $temp->group_id = $as->group_id;
    //                                    $assignments2[] = $temp;
                                    }
                                }
                            }
                            include("$CFG->dirroot/mod/assignmentdistrib/view_student_choose.html");
                        }
                    }
                }
            }
        }
        else if($assignmentsubmission)
        {
            $submitted = $assignmentsubmission->timemodified == 0 ? false : true;
            if($mode == 'submitonline' && confirm_sesskey())
            {
                if($submitted) {
                    error('resubmission is not allowed');
                }
                
                $updaterecord = new object();
                
                $updaterecord->id              = required_param('submissionid', PARAM_INT);
                if($updaterecord->id != $assignmentsubmission->submissionid) {
                    error('submissionid mismatch');
                }
                
                
                $updaterecord->timemodified    = time();
                $updaterecord->assignmenttype  = ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_ONLINETEXT;
                $updaterecord->var1            = required_param('onlinetext', PARAM_RAW);
                $updaterecord->var2            = required_param('onlinetextformat', PARAM_INT);

                if($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_TEAM)
                {
                    $assignmentsubmission_groupids = get_records('assignmentdistrib_submissions'
                                                            , 'assignmentdistrib_groups_id'
                                                            , $assignmentsubmission->assignmentdistrib_groups_id
                                                            , ''
                                                            , 'id');
                    foreach($assignmentsubmission_groupids as $row) {
                        $updaterecord->id = $row->id;
                        if(!update_record('assignmentdistrib_submissions', $updaterecord)) {
                            error('Submission failed');
                        }
                        
                        if($updaterecord->id == $assignmentsubmission->submissionid) {
                            add_to_log($course->id, 'assignmentdistrib', 'submit: online', "view.php?id=$cm->id");
                        }
                    }
                } else {
                    if(!update_record('assignmentdistrib_submissions', $updaterecord)) {
                        error('Submission failed');
                    }
                    add_to_log($course->id, 'assignmentdistrib', 'submit: online', "view.php?id=$cm->id");
                }
                redirect("view.php?id=$cm->id", get_string('saved', 'assignmentdistrib'), 10);
            }
            
            if($mode == 'submitupload' && confirm_sesskey())
            {
                if($submitted) {
                    error('resubmission is not allowed');
                }
                
                $updaterecord = new object();
                
                $updaterecord->id              = required_param('submissionid', PARAM_INT);
                if($updaterecord->id != $assignmentsubmission->submissionid) {
                    error('submissionid mismatch');
                }
                
                require_once($CFG->dirroot.'/lib/uploadlib.php');
                
                //  /var/www/moodle/moodledata/2/moddata/assignmentdistrib/submitted/
                //$dirname = '/'.$course->id.'/'.$CFG->moddata.'/assignmentdistrib/'.$cm->id.'/submitted/';
                //$dirnamebase = '/'.$cm->id.'/submitted/';
                $dirnamebase = '/' . $assignmentdistrib->id . '/submissions/';
                $dirname = '/'.$course->id.'/'.$CFG->moddata.'/assignmentdistrib' . $dirnamebase;

                $dirlocal = $CFG->dataroot.$dirname;
                if(!is_dir($dirlocal)) {
                    if(!mkdir(str_replace('/', DIRECTORY_SEPARATOR, $dirlocal), 0777, true)) {
                        error('Error while uploading file (dir)', "view.php?id=$cm->id");
                    }
                }
                $um = new upload_manager('submitedfile', false, true, $course, false, 0);
                if ($um->process_file_uploads($dirlocal)) {
                    $newfile_name = $um->get_new_filename();
                    $filename = $dirnamebase.$newfile_name;
                    
                    $updaterecord->timemodified    = time();
                    $updaterecord->assignmenttype  = ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_UPLOADSINGLE;
                    $updaterecord->var1            = $filename;
                    
                    if($record->worktype == ASSIGNMENTDISTRIB_WORKTYPE_TEAM)
                    {
                        $assignmentsubmission_groupids = get_records('assignmentdistrib_submissions'
                                                                , 'assignmentdistrib_groups_id'
                                                                , $assignmentsubmission->assignmentdistrib_groups_id
                                                                , ''
                                                                , 'id');
                        foreach($assignmentsubmission_groupids as $row) {
                            $updaterecord->id = $row->id;
                            if(!update_record('assignmentdistrib_submissions', $updaterecord)) {
                                error('Submission failed');
                            }
                            
                            if($updaterecord->id == $assignmentsubmission->submissionid) {
                                add_to_log($course->id, 'assignmentdistrib', 'submit: upload', "view.php?id=$cm->id");
                            }
                        }
                    } else {
                        if(!update_record('assignmentdistrib_submissions', $updaterecord)) {
                            error('Submission failed');
                        }
                        add_to_log($course->id, 'assignmentdistrib', 'submit: upload', "view.php?id=$cm->id");
                    }
                    redirect("view.php?id=$cm->id", get_string('saved', 'assignmentdistrib'), 10);
                } else {
                    error('Error while uploading file', "view.php?id=$cm->id");
                }
            }
            
            if(!$submitted && $record->assignmenttype == ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_ONLINETEXT)
            {
                if ($usehtmleditor = can_use_html_editor()) {
                    $defaultformat = FORMAT_HTML;
                    $editorfields = '';
                } else {
                    $defaultformat = FORMAT_MOODLE;
                }
            }
        }
//        else if ($assignmentproposed)
//        {
//            $cantassign = get_string('cantassign', 'assignmentdistrib');
//            redirect("view.php?id=$cm->id&tab=assignments&mode=view&aid=$cm->instance", $cantassign, 10);
//        }
        
        if($view_student == true) {
            include("$CFG->dirroot/mod/assignmentdistrib/view_student.html");
        }
    }
    else if($isteacher)
    {
        $currenttab = optional_param('tab', 'properties', PARAM_ALPHA);
        if(!in_array($currenttab, array('properties', 'assignments', 'students'))) {
            $currenttab = 'properties';
        }
        $inactive = array();
        $tabs = array(
            array(
                new tabobject('properties'  , "view.php?id=$cm->id"  , get_string('tab_properties', 'assignmentdistrib')),
                new tabobject('assignments' , "view.php?id=$cm->id&tab=assignments" , get_string('tab_assignments', 'assignmentdistrib')),
                new tabobject('students'    , "view.php?id=$cm->id&tab=students"    , get_string('tab_students', 'assignmentdistrib')),
            )
        );
        //$header_navigation .= " -> $assignmentdistrib->name";
        $nav = build_navigation(array(), $cm);
        print_header($header_title, $header_heading, $nav, '', '', false, $header_update_module_button, $header_navmenu);
        //print_header($header_title, $header_heading, $header_navigation, '', '', false, $header_update_module_button, $header_navmenu);
        print_tabs($tabs, $currenttab, $inactive);

        if($currenttab == 'properties')
        {
            include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_properties.html");
        }
        else if($currenttab == 'assignments')
        {
            $mode = optional_param('mode', 'list', PARAM_ALPHANUM);
            if(!in_array( $mode, array('list', 'add', 'edit', 'view', 'instances') )) {
                $mode = 'list';
            }
            $cancel = optional_param('cancel', false, PARAM_BOOL);
            if($cancel)
            {
                
                $return = optional_param('return', '', PARAM_ALPHA);
                $continue = get_string('continue', 'assignmentdistrib');
                if($mode == 'add') {
                    redirect("view.php?id=$cm->id&tab=assignments", '', 0);
                }
                else if($mode == 'edit') {
                    if($return == 'view') {
                        $aid = required_param('aid', PARAM_INT);
                        redirect("view.php?id=$cm->id&tab=assignments&mode=view&aid=$aid", '', 0);
                    }
                    redirect("view.php?id=$cm->id&tab=assignments", '', 0);
                }
                else if($mode == 'view')
                {
                    redirect("view.php?id=$cm->id&tab=assignments", '', 0);
                }
               
            }
            else if(optional_param('edit', false, PARAM_BOOL))
            {
                $aid = required_param('aid', PARAM_INT);
                redirect("view.php?id=$cm->id&tab=assignments&mode=edit&aid=$aid&return=view", '', 0);
            }
            
            
            if($mode == 'list')
            {
                $destinations = get_records_sql("SELECT {$CFG->prefix}assignmentdistrib.id, name, {$CFG->prefix}course.fullname 
                    FROM {$CFG->prefix}assignmentdistrib
                    JOIN {$CFG->prefix}course ON course = {$CFG->prefix}course.id");
                if(optional_param('delete', null, PARAM_RAW) && confirm_sesskey())
                {
                    $checkassigment = optional_param('checkassigment', array(), PARAM_RAW);
                    if($cm->instance != required_param('instance', PARAM_INT) ) {
                        error('Instance error');
                    }
                    $deleted = assignmentdistrib_delete_assignment($checkassigment);
                    
                    if($deleted) {
                        redirect("view.php?id=$cm->id&tab=assignments", get_string('deleted', 'assignmentdistrib'), 10);
                    } else {
                        redirect("view.php?id=$cm->id&tab=assignments", get_string('deletedsome', 'assignmentdistrib'), 60);
                    }
                }
                if(optional_param('movecopy', null, PARAM_RAW) && confirm_sesskey()) {

                    $action = required_param('action', PARAM_RAW);
                    if ($action != 'move' && $action != 'copy'){
                        error("Wrong parameters");
                    }

                    $id = required_param('id', PARAM_INT);
                    $courseid = required_param('course', PARAM_INT);
                    $destination = optional_param('destination', -1, PARAM_INT);
                    $checkassignment = optional_param('checkassigment', array(), PARAM_RAW);
                    if($cm->instance != required_param('instance', PARAM_INT) ) {
                        error('Instance error');
                    }

                    $assignments = array_keys($checkassignment);
                    $notmoved = false;
                    foreach ($assignments as $assignment) {
                        if(!count_records('assignmentdistrib_assignments', 'id', $assignment, 'assignmentdistribid', $cm->instance))    // Security check
                        {
                            error('Assignment error');
                        }
                        $count = count_records('assignmentdistrib_submissions', 'assignmentdistrib_assignments_id', $assignment);
                        if ($action == 'move' && $count)
                        {
                            $notmoved = true;
                            continue;
                        }
//                        $update = new Object();
//                        $update->id = $assignment;
//                        $update->assignmentdistribid = $destination;

                        //if destination is in different course, copy files and change URLs
                        $query = "SELECT {$CFG->prefix}assignmentdistrib_assignments.*
                                    FROM {$CFG->prefix}assignmentdistrib_assignments
                                        JOIN {$CFG->prefix}assignmentdistrib ON assignmentdistribid = {$CFG->prefix}assignmentdistrib.id
                                    WHERE {$CFG->prefix}assignmentdistrib_assignments.id = {$assignment}";
                        $assignmentdata = get_record_sql($query);
//                        update_record('assignmentdistrib_assignments', $assignmentdata);
//                        print_object($assignmentdata);
//                        die();
                        $destinationcourse = get_record_select('assignmentdistrib', 'id = '.$destination, 'course');


                        $assignmentdata->assignmentdistribid = $destination;
                        if ($cm->course != $destinationcourse) {
                            $assignmentdata->course = $destinationcourse;
                            $desc = $assignmentdata->description;
//                            print_object($desc);
                            $newdesc = "";
                            $p = strpos($desc, "file.php/");   
                            while ($p){
                                $newdesc = $newdesc.substr($desc, 0, $p)."file.php/";
//                                print_object($newdesc);
                                $desc = substr($desc, $p + 9);
//                                print_object($desc);
                                $end = strpos($desc, "\"");
//                                echo "end:";
//                                print_object($end);
                                $end2 = strpos($desc, "'");
//                                echo "end2:";
//                                print_object($end2);
                                if ($end !== false && $end2 !== false) {
                                    $end=($end < $end2) ? $end : $end2;
                                }
                                else if ($end === false){
                                    $end = $end2;
                                }

                                if ($end === false){
                                    error("Assignment is misconfigured");
                                }
//                                echo "end:";
//                                print_object($end);
                                
//                                die();


                                $slashpos = strpos($desc, "/");
                                
                                $courseidtemp = substr($desc, 0, $slashpos);
                                                       // TODO: provjeriti odgovara li trenutnom courseid
                                $file = substr($desc, $slashpos + 1, $end - $slashpos - 1);
                                $desc = substr($desc, $end);

//                                $newdesc = $newdesc.$destinationcourse->course."/".$file;
                                //copy the file
//                                echo $file;
                                $from_file = $CFG->dataroot."/".$courseid."/".$file;
                                $to_file = $CFG->dataroot."/".$destinationcourse->course."/".$file;
                                $path_parts = pathinfo($to_file);
                                while(file_exists($to_file)){
                                    
//                                    echo $path_parts['dirname'], "<br>";
//                                    echo $path_parts['basename'], "<br>";
//                                    echo $path_parts['extension'], "<br>";
//                                    echo $path_parts['filename'], "<br>";
//                                    echo $to_file, "<br>";
//                                    echo $path_parts['dirname'] . "/" . $path_parts['filename'] . "1" . "." . $path_parts['extension'];

                                    $to_file = $path_parts['dirname'] . "/" . $path_parts['filename'] . "1" . "." . $path_parts['extension'];
                                    $path_parts = pathinfo($to_file);
//                                    echo $to_file, "<br>";
                                    
                                }
                               
                                $newdesc = $newdesc . $destinationcourse->course . "/" . $path_parts['basename'];
//                                print_object( $newdesc);
                                copy($from_file, $to_file);
                                if(!file_exists($to_file)){
                                    error("Unsuccessful file copying!");
                                }
                              
                                $p = strpos($desc, "file.php/");
                            }
                            
                            $newdesc .= $desc;
                            $newdesc = addslashes($newdesc);        //FIXME: kad moodle updejt bude radio eskejpanje, maknuti
//                            $newdesc = str_replace("'", "\"", $newdesc);
//                            print_object($newdesc);
                           
                            $assignmentdata->description = $newdesc;
                            $assignmentdata->name = addslashes($assignmentdata->name);
                            
                        }
                        if ($action == 'move'){
//                            print_object($assignmentdata);
                            $updated = update_record('assignmentdistrib_assignments', $assignmentdata);
//                            if ($notmoved)
//                            {
//                                redirect("view.php?id=$cm->id&tab=assignments", get_string('movedsome', 'assignmentdistrib'), 10);
//                            }
//                            else
//                            {
//                                redirect("view.php?id=$cm->id&tab=assignments", get_string('moved', 'assignmentdistrib'), 10);
//                            }
                        }
                        else if ($action == 'copy') {
                            unset($assignmentdata->id);
                            $updated = insert_record('assignmentdistrib_assignments', $assignmentdata);
                            //redirect("view.php?id=$cm->id&tab=assignments", get_string('copied', 'assignmentdistrib'), 10);
                        }
                        
                    }
                    if ($action == 'copy') {
                        redirect("view.php?id=$cm->id&tab=assignments", get_string('copied', 'assignmentdistrib'), 10);
                    }
                    else if ($notmoved)
                    {
                        redirect("view.php?id=$cm->id&tab=assignments", get_string('movedsome', 'assignmentdistrib'), 10);
                    }
                    else if ($action == 'move')
                    {
                        redirect("view.php?id=$cm->id&tab=assignments", get_string('moved', 'assignmentdistrib'), 10);
                    }

                    //redirect("view.php?id=$cm->id&tab=assignments", $copied, 10);

                   // redirect("view.php?id=$cm->id&tab=assignments", get_string('moved', 'assignmentdistrib'), 10);
                }
                
                $assignment_list = get_records('assignmentdistrib_assignments', 'assignmentdistribid', $cm->instance, 'name');
                if(empty($assignment_list)) {
                    $assignment_list = array();
                }
                include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_assignments.html");
            }
            
            else if ($mode == 'instances'){
                $id =  required_param('id', PARAM_INT);
                $aid =  required_param('aid', PARAM_INT);
                include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_assignments_instances.html");
            }
            
            else if($mode == 'add' || $mode == 'edit')
            {
                $return = optional_param('return', '', PARAM_ALPHA);
                
                if ($usehtmleditor = can_use_html_editor()) {
                    $defaultformat = FORMAT_HTML;
                    $editorfields = '';
                } else {
                    $defaultformat = FORMAT_MOODLE;
                }
                
                if($mode == 'add')
                {
                    $name               = optional_param('name', '', PARAM_RAW);
                    $description        = optional_param('description', '', PARAM_RAW);
                    $descriptionformat  = optional_param('descriptionformat', $defaultformat, PARAM_INT);
                    $groupstudentmin    = optional_param('groupstudentmin', 1, PARAM_INT);
                    $groupstudentmax    = optional_param('groupstudentmax', 1, PARAM_INT);
                    $maxnumberofrepeats = optional_param('maxnumberofrepeats', 0, PARAM_INT);
                    $available          = optional_param('available', 1, PARAM_INT);
                }
                else
                {
                    $aid =  required_param('aid', PARAM_INT);
                    $record = get_record('assignmentdistrib_assignments', 'id', $aid, 'assignmentdistribid', $cm->instance);
                    if(empty($record)) {
                        error(get_string('assignment_missing', 'assignmentdistrib'));
                    }
                    
                    $name                   = $record->name;
                    $description            = $record->description;
                    $descriptionformat      = $record->descriptionformat;
                    $groupstudentmin        = $record->groupstudentmin;
                    $groupstudentmax        = $record->groupstudentmax;
                    $maxnumberofrepeats     = $record->maxnumberofrepeats;
                    $available              = $record->available;
                    $createdbyuserid        = $record->createdbyuserid;
                    $approved               = $record->approved;
                    
                    if ($createdbyuserid&&!($USER->id==$createdbyuserid))
                    {
                        //print_object($createdbyuserid);
                        $createdbyuser = get_complete_user_data('id', $createdbyuserid);
                        echo ("Assignment created by: ");
                        p($createdbyuser->firstname.' '.$createdbyuser->lastname);
                        if (!$approved)
                            echo ("<br>Assignment has not been approved yet.");
                    }
                }
                $q = get_record_sql("SELECT worktype, distributiontype FROM {$CFG->prefix}assignmentdistrib WHERE id = ".$cm->instance);

                $worktype = $q->worktype;
                $distributiontype = $q->distributiontype;
                

                if($mode == 'edit'){
                    if ($worktype == ASSIGNMENTDISTRIB_WORKTYPE_TEAM){
                        $groupminmax = get_record_sql ("select assignmentdistrib_assignments_id, min(count) as mingroup, max(count) as maxgroup
                                                        from
                                                        (
                                                            select assignmentdistrib_assignments_id, assignmentdistrib_groups_id, count(*) as count
                                                            from {$CFG->prefix}assignmentdistrib_submissions
                                                            group by assignmentdistrib_groups_id, assignmentdistrib_assignments_id
                                                        ) as lalala
                                                        where assignmentdistrib_groups_id is not null
                                                        and assignmentdistrib_assignments_id={$aid}
                                                        group by assignmentdistrib_assignments_id");
                        if ($groupminmax){
                            $groupminstatus = $groupminmax->mingroup;
                            $groupmaxstatus = $groupminmax->maxgroup;
                        }
//                        print_object($groupminstatus);
//                        print_object($groupmaxstatus);


                        $count_inst = get_record_sql ("SELECT COUNT(DISTINCT assignmentdistrib_groups_id) AS count_instances
                                                        FROM {$CFG->prefix}assignmentdistrib_submissions
                                                        WHERE assignmentdistrib_groups_id IS NOT NULL AND
                                                        {$CFG->prefix}assignmentdistrib_submissions.assignmentdistrib_assignments_id = {$aid}");

                    }
                    else{
                        $count_inst = get_record_sql ("SELECT COUNT(*) AS count_instances FROM {$CFG->prefix}assignmentdistrib_submissions
                                                        WHERE assignmentdistrib_groups_id IS NULL AND
                                                        {$CFG->prefix}assignmentdistrib_submissions.assignmentdistrib_assignments_id = {$aid}");
                    }
                    
                    $count_instances = $count_inst->count_instances;
             //       print_object($count_instances);

                    }

                $inserted = false; 
                $errornameempty = false;
                $edit_submit_mode = null;
                if(optional_param('submit', null, PARAM_RAW) != '') {
                    $edit_submit_mode = 'edit';
                } else if(optional_param('copy', null, PARAM_RAW) != '') {
                    $edit_submit_mode = 'copy';
                }
                if($edit_submit_mode !== null && confirm_sesskey())
                {
                    $sesskey            = required_param('sesskey', PARAM_RAW);
                    $data = new object();
                    $data->name                 = trim(required_param('name', PARAM_RAW));
                    $data->description          = required_param('description', PARAM_RAW);
                    $data->descriptionformat    = required_param('descriptionformat', PARAM_INT);
                    $data->groupstudentmin      = optional_param('groupstudentmin', 1, PARAM_INT);
                    $data->groupstudentmax      = optional_param('groupstudentmax', 1, PARAM_INT);
                    $data->maxnumberofrepeats   = optional_param('maxnumberofrepeats', 0, PARAM_INT);
                    $data->assignmentdistribid  = required_param('instance', PARAM_INT);
                    $data->available            = optional_param('available', 1, PARAM_INT);
                    if($cm->instance != $data->assignmentdistribid) {
                        error('Instance error');
                    }
                 
                    $error=false;

                    if(empty($data->name)) {
                        $error=true;
                        $errornameempty = true;
                        $name           = stripslashes_safe($name);
                        $description    = stripslashes_safe($description);
                    }
                    
                    if (!ctype_digit(required_param('groupstudentmin', PARAM_RAW)))
                    {
                        $error=true;
                        $errorinteger=true;
                        $groupstudentmin=required_param('groupstudentmin', PARAM_RAW);
                    }

                    if (!ctype_digit(required_param('groupstudentmax', PARAM_RAW)))
                    {
                        $error=true;
                        $errorinteger=true;
                        $groupstudentmax=required_param('groupstudentmax', PARAM_RAW);
                    }
                    if (!ctype_digit(required_param('maxnumberofrepeats', PARAM_RAW)))
                    {
                        $error=true;
                        $errorinteger=true;
                        $maxnumberofrepeats=required_param('maxnumberofrepeats', PARAM_RAW);
                    }

                    if (required_param('groupstudentmax', PARAM_INT) < required_param('groupstudentmin', PARAM_INT)){
                        $error=true;
                        $errorminmax=true;
                    }

                    if (!$error)
                    {
                        if ($mode == 'add') {
                            $inserted = insert_record('assignmentdistrib_assignments', $data, false);
                        }
                        else if ($mode == 'edit'){
                            if ($edit_submit_mode == 'edit') { //update
                                $data->id = $aid;
                                $updated = update_record('assignmentdistrib_assignments', $data);

                                $olddescription = required_param('olddescription', PARAM_RAW);
                                $instances = required_param('instances', PARAM_INT);
                                //print_object ($olddescription);
                                //print_object ($aid);

                                if ($data->description != $olddescription){
                                    //echo "Description changed";

                                    if ($instances){
                                        
                                        $students_assigned = get_records_sql ("SELECT userid
                                                    FROM {$CFG->prefix}assignmentdistrib_submissions
                                                    WHERE assignmentdistrib_assignments_id = {$aid}");
                                        foreach($students_assigned as $student_assigned){

                                            $student_data = get_complete_user_data('id', $student_assigned->userid);
//                                                print_object($student_data);
                                            $message = get_string('course', 'assignmentdistrib') . ": $course->fullname\n" .
                                                        get_string('assignmentdistribution', 'assignmentdistrib') . ": $assignmentdistrib->name\n" .
                                                        get_string('assignment', 'assignmentdistrib') . ": $data->name\n" .
                                                        get_string('changedassignmentdescriptionmessage', 'assignmentdistrib');
                                            message_post_message($USER, $student_data, $message, FORMAT_PLAIN, 'direct');

                                            }

                                            //print_object($instances);

                                    }
                                }

                                //die();


                                $saved = get_string('saved', 'assignmentdistrib');
                                if($return == 'view') {
                                    $aid = required_param('aid', PARAM_INT);
                                }
                            }
                            else {
//                                print_object($data);
//                                die();
                                $inserted = insert_record('assignmentdistrib_assignments', $data, true);
//                                print_object($inserted);
//                                die();
                                $saved = get_string('savedasnew', 'assignmentdistrib');
                                $aid=$inserted;

                            }
                            if($return == 'view') {
                                redirect("view.php?id=$cm->id&tab=assignments&mode=view&aid=$aid", $saved, 10);
                            }
                            redirect("view.php?id=$cm->id&tab=assignments", $saved, 10);
                        }
                        
                        $name = '';
                        $description = '';
                        $descriptionformat = $defaultformat;
                    }
                }

                include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_assignments_add.html");
            }
            else if($mode == 'view')
            {
                $aid =  required_param('aid', PARAM_INT);
                $record = get_record('assignmentdistrib_assignments', 'id', $aid, 'assignmentdistribid', $cm->instance);
                if(empty($record)) {
                    error(get_string('assignment_missing', 'assignmentdistrib'));
                }
                
                if(optional_param('delete', null, PARAM_RAW) && confirm_sesskey())
                {
                    $checkassigment = array($aid => 'ok');
                    if($cm->instance != required_param('instance', PARAM_INT) ) {
                        error('Instance error');
                    }
                    
                    if(assignmentdistrib_delete_assignment($checkassigment)) {
                        redirect("view.php?id=$cm->id&tab=assignments", get_string('deleted', 'assignmentdistrib'), 10);
                    } else {
                        $notdeleted = true;
                    }
                }
                
                $name                   = $record->name;
                $description            = $record->description;
                $descriptionformat      = $record->descriptionformat;
                $groupstudentmin        = $record->groupstudentmin;
                $groupstudentmax        = $record->groupstudentmax;
                $maxnumberofrepeats     = $record->maxnumberofrepeats;
                $available              = $record->available;
                include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_assignments_view.html");
            }
        }
        else if($currenttab == 'students')
        {
            if($assignmentdistrib->worktype == ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL)
            {
                //mydump($cm->instance, false);
                //mydump($CFG->prefix, false);
                $submissions_list = get_records_sql("SELECT user.id,
                                            user.id AS userid,
                                            user.firstname,
                                            user.lastname,
                                            user.email,
                                            user.username,
                                            submissions.timecreated,
                                            submissions.timemodified,
                                            submissions.gradedbyuserid,
                                            submissions.timegraded,
                                            submissions.comment,
                                            submissions.commentformat,
                                            submissions.assignmenttype,
                                            submissions.var1,
                                            submissions.var2,
                                            submissions.assignmentdistrib_assignments_id AS assignment_id,
                                            assignment.name AS assignment_name
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignment
                                                ON assignment.id = submissions.assignmentdistrib_assignments_id
                                            JOIN {$CFG->prefix}user AS user
                                                ON submissions.userid = user.id
                                        WHERE assignment.assignmentdistribid={$cm->instance}
                                        ORDER BY user.lastname, user.firstname, user.username
                                        ");
                if($submissions_list) {
                    foreach ($submissions_list as &$sub){
                        
                        $grade_info = grade_get_grades($cm->course, 'mod', 'assignmentdistrib', $cm->instance, array($sub->userid));
                        if(!empty($grade_info->items)) {
                            $sub->grade = $grade_info->items[0]->grades[$sub->userid]->str_grade;
                        } else {
                            $sub->grade = null;
                        }
                    }
                }
                if(!is_array($course_students)) {
                    $course_students = array();
                }
                if(!is_array($submissions_list)) {
                    $submissions_list = array();
                }
                
                $unassigned_students = array_diff_key($course_students, $submissions_list);
                
                include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_students_individual.html");
            }
            else
            {
                $submissions_list = get_records_sql("SELECT user.id,
                                            user.id AS userid,
                                            user.firstname,
                                            user.lastname,
                                            user.email,
                                            user.username,
                                            submissions.timecreated,
                                            submissions.timemodified,
                                            submissions.grade,
                                            submissions.gradedbyuserid,
                                            submissions.timegraded,
                                            submissions.comment,
                                            submissions.commentformat,
                                            submissions.assignmenttype,
                                            submissions.var1,
                                            submissions.var2,
                                            groups.id AS group_id,
                                            groups.name AS group_name,
                                            groups.leaderuserid AS group_leaderuserid,
                                            groups.grade AS group_grade,
                                            groups.timegraded AS group_timegraded,
                                            groups.comment AS group_comment,
                                            groups.commentformat AS group_commentformat,
                                            submissions.assignmentdistrib_assignments_id AS assignment_id,
                                            assignment.name AS assignment_name,
                                            leader.firstname AS leader_firstname,
                                            leader.lastname AS leader_lastname,
                                            leader.email AS leader_email
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            LEFT JOIN {$CFG->prefix}assignmentdistrib_groups AS groups
                                                ON submissions.assignmentdistrib_groups_id = groups.id
                                            LEFT JOIN {$CFG->prefix}user AS leader
                                                ON groups.leaderuserid = leader.id
                                            JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignment ON assignment.id = submissions.assignmentdistrib_assignments_id
                                            JOIN {$CFG->prefix}user AS user ON submissions.userid = user.id
                                        WHERE assignment.assignmentdistribid={$cm->instance}
                                        ORDER BY leader.lastname, leader.firstname, user.lastname, user.firstname
                                        ");
                $groups_stats = get_records_sql("SELECT groups.id, groups.grade,
                                                    COUNT(*) AS count,
                                                    SUM(CASE submissions.grade WHEN -1 THEN 0 ELSE submissions.grade END) AS grade_sum,
                                                    AVG(CASE submissions.grade WHEN -1 THEN 0 ELSE submissions.grade END) AS grade_avg,
                                                    MAX(CASE submissions.grade WHEN -1 THEN 0 ELSE submissions.grade END) AS grade_max,
                                                    MIN(CASE submissions.grade WHEN -1 THEN 0 ELSE submissions.grade END) AS grade_min,
                                                    MAX(submissions.timemodified) AS timemodified_max,
                                                    MAX(submissions.timegraded) AS timegraded_max,
                                                    MIN(submissions.timecreated) AS timecreated_min
                                        FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                            LEFT JOIN {$CFG->prefix}assignmentdistrib_groups AS groups
                                                ON submissions.assignmentdistrib_groups_id = groups.id
                                            JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignment ON assignment.id = submissions.assignmentdistrib_assignments_id
                                        WHERE assignment.assignmentdistribid={$cm->instance}
                                        GROUP BY groups.id, groups.grade
                                        ");
                if(!is_array($course_students)) {
                    $course_students = array();
                }
                if(!is_array($submissions_list)) {
                    $submissions_list = array();
                }
                $unassigned_students = array_diff_key($course_students, $submissions_list);
                include("$CFG->dirroot/mod/assignmentdistrib/view_teacher_students_groups.html");
            }
        }
        else
        {
            error("Unknown tab");
        }
    }
    if (isset($usehtmleditor) && $usehtmleditor && empty($nohtmleditorneeded)) {
        use_html_editor($editorfields);
    }

    
/// Finish the page
    print_footer($course);

?>