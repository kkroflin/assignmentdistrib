<?php
/**
 * Library of functions and constants for module assignmentdistrib
 *
 * @author Krešimir Kroflin 
 * @package assignmentdistrib
 **/

define('ASSIGNMENTDISTRIB_WORKTYPE_INDIVIDUAL'  , 0);
define('ASSIGNMENTDISTRIB_WORKTYPE_TEAM'        , 1);

define('ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_OFFLINE'       , 0);
define('ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_ONLINETEXT'    , 1);
define('ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_UPLOADSINGLE'  , 2);

define('ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_RANDOM'          , 0);
define('ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSENOREPEAT'   , 1);
define('ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSEREPEAT'     , 2);
define('ASSIGNMENTDISTRIB_DISTRIBUTIONTYPE_CHOOSEREPEATMAX'       , 3);

define('ASSIGNMENTDISTRIB_MAXPENALTIES', 10);

/**
 * Given an object containing all the necessary data, 
 * (defined by the form in mod.html) this function 
 * will create a new instance and return the id number 
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted assignmentdistrib record
 **/
function assignmentdistrib_add_instance($assignmentdistrib) {
    $assignmentdistrib->timemodified = time();
    $assignmentdistrib->courseid = $assignment->course;
    
    if (empty($assignmentdistrib->dueenable))
    {
        $assignmentdistrib->timedue = 0;
        $assignmentdistrib->preventlate = 0;
    }
    else
    {
        $assignmentdistrib->timedue = make_timestamp(   $assignmentdistrib->dueyear
                                                    ,   $assignmentdistrib->duemonth
                                                    ,   $assignmentdistrib->dueday
                                                    ,   $assignmentdistrib->duehour
                                                    ,   $assignmentdistrib->dueminute
                                                    );
    }
    
    if (empty($assignmentdistrib->availableenable))
    {
        $assignmentdistrib->timeavailable = 0;
    }
    else
    {
        $assignmentdistrib->timeavailable = make_timestamp(     $assignmentdistrib->availableyear
                                                            ,   $assignmentdistrib->availablemonth
                                                            ,   $assignmentdistrib->availableday
                                                            ,   $assignmentdistrib->availablehour
                                                            ,   $assignmentdistrib->availableminute
                                                            );
    }
    if (empty($assignmentdistrib->leadercangrade)) $assignmentdistrib->leadercangrade = 0;
    if (empty($assignmentdistrib->allowgroupmarket)) $assignmentdistrib->allowgroupmarket = 0;
    if (empty($assignmentdistrib->allowstudentchange)) $assignmentdistrib->allowstudentchange = 0;
    
    for($i=0; $i<ASSIGNMENTDISTRIB_MAXPENALTIES; $i++)
    {
        $penalty = intval($assignmentdistrib->{"penalty_$i"});
        if ($penalty) {
            $record = new object;
            $record->assignmentdistrib_id = $assignmentdistrib->id;
            $record->time = make_timestamp( $assignmentdistrib->{"pen_dueyear_$i"}
                                        ,   $assignmentdistrib->{"pen_duemonth_$i"}
                                        ,   $assignmentdistrib->{"pen_dueday_$i"}
                                        ,   $assignmentdistrib->{"pen_duehour_$i"}
                                        ,   $assignmentdistrib->{"pen_dueminute_$i"}
                                        );
            $record->penalty_grade = $penalty;
            insert_record('assignmentdistrib_penalties', $record);
        }
    }
    
    $returnid = insert_record('assignmentdistrib', $assignmentdistrib);
    if($returnid) {
        $assignmentdistrib->id = $returnid;
        assignmentdistrib_grade_item_update($assignmentdistrib);
    }
    return $returnid;
}

/**
 * Given an object containing all the necessary data, 
 * (defined by the form in mod.html) this function 
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function assignmentdistrib_update_instance($assignmentdistrib) {

    $assignmentdistrib->timemodified = time();
    $assignmentdistrib->id = $assignmentdistrib->instance;
    $assignmentdistrib->courseid = $assignmentdistrib->course;

    if (empty($assignmentdistrib->dueenable))
    {
        $assignmentdistrib->timedue = 0;
        $assignmentdistrib->preventlate = 0;
    }
    else
    {
        $assignmentdistrib->timedue = make_timestamp(   $assignmentdistrib->dueyear
                                                    ,   $assignmentdistrib->duemonth
                                                    ,   $assignmentdistrib->dueday
                                                    ,   $assignmentdistrib->duehour
                                                    ,   $assignmentdistrib->dueminute
                                                    );
    }
    
    if (empty($assignmentdistrib->availableenable))
    {
        $assignmentdistrib->timeavailable = 0;
    }
    else
    {
        $assignmentdistrib->timeavailable = make_timestamp(     $assignmentdistrib->availableyear
                                                            ,   $assignmentdistrib->availablemonth
                                                            ,   $assignmentdistrib->availableday
                                                            ,   $assignmentdistrib->availablehour
                                                            ,   $assignmentdistrib->availableminute
                                                            );
    }
    if (empty($assignmentdistrib->leadercangrade)) $assignmentdistrib->leadercangrade = 0;
    if (empty($assignmentdistrib->allowgroupmarket)) $assignmentdistrib->allowgroupmarket = 0;
    if (empty($assignmentdistrib->allowstudentchange)) $assignmentdistrib->allowstudentchange = 0;
    
    delete_records('assignmentdistrib_penalties', 'assignmentdistrib_id', $assignmentdistrib->id);
    
    for($i=0; $i<ASSIGNMENTDISTRIB_MAXPENALTIES; $i++)
    {
        $penalty = intval($assignmentdistrib->{"penalty_$i"});
        if ($penalty) {
            $record = new object;
            $record->assignmentdistrib_id = $assignmentdistrib->id;
            $record->time = make_timestamp( $assignmentdistrib->{"pen_dueyear_$i"}
                                        ,   $assignmentdistrib->{"pen_duemonth_$i"}
                                        ,   $assignmentdistrib->{"pen_dueday_$i"}
                                        ,   $assignmentdistrib->{"pen_duehour_$i"}
                                        ,   $assignmentdistrib->{"pen_dueminute_$i"}
                                        );
            $record->penalty_grade = $penalty;
            insert_record('assignmentdistrib_penalties', $record);
        }
    }    

    $ret = update_record("assignmentdistrib", $assignmentdistrib);
    assignmentdistrib_grade_item_update($assignmentdistrib);
    return $ret;
}

/**
 * Given an ID of an instance of this module, 
 * this function will permanently delete the instance 
 * and any data that depends on it. 
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function assignmentdistrib_delete_instance($id) {

    if (! $assignmentdistrib = get_record("assignmentdistrib", "id", "$id")) {
        return false;
    }

    $result = true;

    # Delete any dependent records here #

    $assignmentdistrib_assignments = get_records('assignmentdistrib_assignments', 'assignmentdistribid', $assignmentdistrib->id);
    if($assignmentdistrib_assignments)
    {
        foreach($assignmentdistrib_assignments as $assignmentdistrib_assignment)
        {
            if (! delete_records('assignmentdistrib_submissions', 'assignmentdistrib_assignments_id', $assignmentdistrib_assignment->id)) {
                $result = false;
            }
                        
            if (! delete_records('assignmentdistrib_assignments', 'id', $assignmentdistrib_assignment->id)) {
                $result = false;
            }
        }
    }
    
    if (! delete_records('assignmentdistrib_penalties', 'id', $assignmentdistrib->id)) {
        $result = false;
    }
    
    if (! delete_records('assignmentdistrib', 'id', $assignmentdistrib->id)) {
        $result = false;
    }
    assignmentdistrib_grade_item_update($assignmentdistrib);
    return $result;
}

/**
 * Return a small object with summary information about what a 
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 **/
function assignmentdistrib_user_outline($course, $user, $mod, $assignmentdistrib) {
    return $return;
}

/**
 * Print a detailed representation of what a user has done with 
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function assignmentdistrib_user_complete($course, $user, $mod, $assignmentdistrib) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity 
 * that has occurred in assignmentdistrib activities and print it out. 
 * Return true if there was output, or false is there was none. 
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function assignmentdistrib_print_recent_activity($course, $isteacher, $timestart) {
    global $CFG;

    return false;  //  True if anything was printed, otherwise false 
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such 
 * as sending out mail, toggling flags etc ... 
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function assignmentdistrib_cron () {
    global $CFG;

    return true;
}

/**
 * old moodle
 * Must return an array of grades for a given instance of this module, 
 * indexed by user.  It also returns a maximum allowed grade.
 * 
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $assignmentdistribid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 **
function assignmentdistrib_grades($assignmentdistribid) {
    global $CFG;
    $ret = new object();
    //  AS rawgrade, submissions.timemarked AS dategraded,  submissions.timemodified  AS datesubmitted
    $grades = get_records_sql("SELECT userid AS id, submissions.userid, submissions.grade
                                FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                    JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignment
                                        ON assignment.id = submissions.assignmentdistrib_assignments_id
                                WHERE assignment.assignmentdistribid = $assignmentdistribid
                                    AND grade > -1 AND grade IS NOT NULL
                                ");
    if($grades == false) {
        $ret->grades = false;
    } else {
        $ret->grades = array();
        foreach($grades as $userid => $grade) {
            $ret->grades[$userid] = $grade->grade;
        }
    }
    $assignment = get_record('assignmentdistrib', 'id', $assignmentdistribid);
    $ret->maxgrade = $assignment->grade;
    
    return $ret;
}
*/

/**
 * Return grade for given user or all users.
 *
 * @param int $assignmentdistrib assignmentdistrib
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function assignmentdistrib_get_user_grades($assignmentdistrib, $userid=0) {
    global $CFG;

    $user_inject = $userid ? "AND u.id = $userid" : "";

    $sql = "SELECT userid AS id, submissions.userid, submissions.grade AS rawgrade, submissions.comment AS feedback, submissions.commentformat AS feedbackformat,
                submissions.gradedbyuserid AS usermodified, submissions.timegraded AS dategraded, submissions.timemodified AS datesubmitted
                                FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                    JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignment
                                        ON assignment.id = submissions.assignmentdistrib_assignments_id
                                WHERE assignment.assignmentdistribid = $assignmentdistrib->id
                                            $user_inject
                                    ";
    // AND grade > -1 AND grade IS NOT NULL
    
    return get_records_sql($sql);
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $assignment null means all assignments
 * @param int $userid specific user only, 0 mean all
 */
function assignmentdistrib_update_grades($assignmentdistrib=null, $userid=0, $nullifnone=true) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($assignmentdistrib != null) {
        if ($grades = assignmentdistrib_get_user_grades($assignmentdistrib, $userid)) {
            foreach($grades as $k=>$v) {
                if ($v->rawgrade == -1) {
                    $grades[$k]->rawgrade = null;
                }
            }
            assignmentdistrib_grade_item_update($assignmentdistrib, $grades);
        } else {
            assignmentdistrib_grade_item_update($assignmentdistrib);
        }

    } else {
        $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
                  FROM {$CFG->prefix}assignmentdistrib a, {$CFG->prefix}course_modules cm, {$CFG->prefix}modules m
                 WHERE m.name='assignmentdistrib' AND m.id=cm.module AND cm.instance=a.id";
        if ($rs = get_recordset_sql($sql)) {
            while ($assignmentdistrib = rs_fetch_next_record($rs)) {
                if ($assignmentdistrib->grade != 0) {
                    assignmentdistrib_update_grades($assignmentdistrib);
                } else {
                    assignmentdistrib_grade_item_update($assignmentdistrib);
                }
            }
            rs_close($rs);
        }
    }
}

/**
 * Create grade item for given assignment
 *
 * @param object $assignmentdistrib object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function assignmentdistrib_grade_item_update($assignmentdistrib, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($assignmentdistrib->courseid)) {
        $assignmentdistrib->courseid = $assignmentdistrib->course;
    }

    $params = array('itemname'=>$assignmentdistrib->name);  // , 'idnumber'=>$assignmentdistrib->cmidnumber

    if ($assignmentdistrib->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $assignmentdistrib->grade;
        $params['grademin']  = 0;

    } else if ($assignmentdistrib->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$assignmentdistrib->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/assignmentdistrib', $assignmentdistrib->courseid, 'mod', 'assignmentdistrib', $assignmentdistrib->id, 0, $grades, $params);
}

/**
 * Delete grade item for given assignment
 *
 * @param object $assignmentdistrib object
 * @return object assignment
 */
function assignmentdistrib_grade_item_delete($assignmentdistrib) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($assignmentdistrib->courseid)) {
        $assignmentdistrib->courseid = $assignmentdistrib->course;
    }

    return grade_update('mod/assignmentdistrib', $assignmentdistrib->courseid, 'mod', 'assignmentdistrib', $assignmentdistrib->id, 0, NULL, array('deleted'=>1));
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of assignmentdistrib. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $assignmentdistribid ID of an instance of this module
 * @return mixed boolean/array of students
 **/
function assignmentdistrib_get_participants($assignmentdistribid) {
    global $CFG;
    $students = get_records_sql("SELECT submissions.userid as id
                                FROM {$CFG->prefix}assignmentdistrib_submissions AS submissions
                                    JOIN {$CFG->prefix}assignmentdistrib_assignments AS assignment
                                        ON assignment.id = submissions.assignmentdistrib_assignments_id
                                WHERE assignment.assignmentdistribid = $assignmentdistribid");
    return $students;
}

/**
 * This function returns if a scale is being used by one assignmentdistrib
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $assignmentdistribid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 **/
function assignmentdistrib_scale_used ($assignmentdistribid,$scaleid) {
    $return = false;

    //$rec = get_record("assignmentdistrib","id","$assignmentdistribid","scale","-$scaleid");
    //
    //if (!empty($rec)  && !empty($scaleid)) {
    //    $return = true;
    //}
   
    return $return;
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other assignmentdistrib functions go here.  Each of them must have a name that 
/// starts with assignmentdistrib_

//function assignmentdistrib_get_task_types($assignid) {
//	
//	return array('0' => 'Matlab', '1' => 'ATLAS', '2' => 'GPSS');
//}
//
//function assignmentdistrib_get_min_number_students($assignid) {
//	$listmin = array();
//	for ($i=0; $i<ASSIGNMENTDISTRIB_MIN_STUDENTS_GROUP; $i++) {
//		$listmin[$i] = $i+1;	
//	}
//	return $listmin;
//}
//
//function assignmentdistrib_get_max_number_students($assignid) {
//	$listmax = array();
//	for ($i=0; $i<ASSIGNMENTDISTRIB_MAX_STUDENTS_GROUP; $i++) {
//		$listmax[$i] = $i+1;	
//	}
//	return $listmax;
//}
//

function assignmentdistrib_get_max_repeat_tasks() { //not in use
    $listmax = array();
    for ($i=0; $i < ASSIGNMENTDISTRIB_MAX_REPEAT_TASKS; $i++) {
        $listmax[$i] = $i+1;	
    }
    return $listmax;
}

function assignmentdistrib_make_list_int($records, $field)
{
    $list = '';
    if($records !== false && count($records) > 0)
    {
        $first = true;
        foreach($records as $value) 
        {
            if($first==false) {
                $list .= ', ';
            } else {
                $first = false;
            }
            if(is_array($value)) {
                $list .= $value[$field];
            } else {
                $list .= $value->$field;
            }
        }
    }
    return $list;
}

function assignmentdistrib_make_array($records, $field)
{
    $array = array();
    if($records !== false && count($records) > 0)
    {
        foreach($records as $value) 
        {
            if(is_array($value)) {
                $array[] = $value[$field];
            } else {
                $array[] = $value->$field;
            }
        }
    }
    return $array;
}
//function assignmentdistrib_get_submit_types($assignid) {
//	$listtypes = array();
//	$listtypes[0] = get_string('upload', 'assign');
//	$listtypes[1] = get_string('paper', 'assign');
//
//	return $listtypes;
//}	
//
//function assignmentdistrib_get_max_number_points($assignid) {
//	$listmax = array();
//	for ($i=0; $i<ASSIGNMENTDISTRIB_MAX_NUMBER_POINTS; $i++) {
//		$listmax[$i] = $i+1;	
//	}
//	return $listmax;
//}
//
//function assignmentdistrib_get_task_contents($assignid) {
//	$contents = array();
//	for ($i=0; $i<2; $i++) {
//		$contents[$i] = "Jedan lijepi zadatak o tko zna cemu.<br/>lalalalalala.......";
//	}
//	$contents[2] = get_string('notaskyet', 'assign');
//	
//	return $contents;
//}
//
//function assignmentdistrib_get_groups($assignid) {
//	$groups = array();
//
//	for ($i = 0; $i<2; $i++)
//	{
//		$groups[$i]['id'] = $i;
//		$groups[$i]['name'] = 'Groupname' . $i;
//		$groups[$i]['grade'] = 18 + $i;
//		$groups[$i]['comment'] = 'Something';
//		$groups[$i]['lastmodifstud'] = '2006-02-21, 13:20';
//		$groups[$i]['lastmodifteach'] = '2006-02-21, 13:20';
//	}	
//
//	return $groups;
//}
//
//function assignmentdistrib_get_students($assignid, $groupid) {
//	$students = array();
//
//	for ($i = 0; $i<$groupid+3; $i++)
//	{
//		$students[$i]['id'] = $i;
//		$students[$i]['name'] = 'Student' . $groupid . ' - ' . $i;
//		$students[$i]['grade'] = 18 + $i;
//		$students[$i]['comment'] = 'Something';
//		$students[$i]['lastmodifstud'] = '2006-02-21, 13:20';
//		$students[$i]['lastmodifteach'] = '2006-02-21, 13:20';
//	}	
//
//	return $students;
//}
//
//function assignmentdistrib_get_tasks($assignid) {
//	$tasks = array();
//	
//	for ($i = 1; $i < 5; $i++) {
//		$tasks[$i]['id'] = $i;
//		$tasks[$i]['title'] = 'Task number'.$i;
//		$tasks[$i]['content'] = "Jedan lijepi zadatak o <i>tko zna ÄŤemu</i>.<br/>lalalalalala.......";
//		$tasks[$i]['minstudent'] = $i;
//		$tasks[$i]['maxstudent'] = $i*2;
//		$tasks[$i]['comment'] = "Komentar na zadatak";
//		$tasks[$i]['instance'] = $i*2;
//	}
//	return $tasks;
//}
//
//function assignmentdistrib_get_available_tasks($assignid) {
//	///Get free tasks available to assign to students
//	return assignmentdistrib_get_tasks($assignid);
//}
//function assignmentdistrib_get_available_task_titles($availabletasks) {
//	$titles = array();
//	foreach($availabletasks as $a)
//			$titles[$a['id']] = $a['title'];
//	
//	return $titles;
//}
//
//function assignmentdistrib_get_task($assignid, $taskid) {
//	$task = array();
//	
//	$task['id'] = 1;
//	$task['title'] = 'Task 1';
//	$task['content'] = 'Lalalala, jedan lijepi zadatak, bit Ä‡e prikazan u html-u......';
//	$task['minstudent'] = 2;
//	$task['maxstudent'] = 3;
//	$task['comment'] = 'Nemam komentara na ovo...';
//
//	return $task;
//}
//
//function assignmentdistrib_get_students_groups_for_task($assignid, $taskid) {
//	///gets the list of either groups or students assigned to a task
//	///could be divided into 2 different functions
//	
//	return assignmentdistrib_get_groups($assignid, $assignid);
//}
//
//function assignmentdistrib_make_yesno() {
//	
//	return array(get_string("yes"), get_string("no"));
//}

/**
 * Deletes assignments
 *
 * @param array $assignments
 */
function assignmentdistrib_delete_assignment($assignments, $force = false) {
    global $cm;
    
    $retval = true;
    
    foreach($assignments as $key => $value)
    {
        if(clean_param($key, PARAM_INT) != $key) {
            error('Delete assignment distrib');
        }
        
        if(count_records('assignmentdistrib_assignments', 'id', $key, 'assignmentdistribid', $cm->instance))    // Security check
        {
            $count = count_records('assignmentdistrib_submissions', 'assignmentdistrib_assignments_id', $key);
            
            if($count > 0 && $force !== true) {
                $retval = false;
                continue;
            } else {
                $delete = delete_records('assignmentdistrib_assignments', 'id', $key, 'assignmentdistribid', $cm->instance);
                if($delete === false) {
                    error('Delete assignment - assignment distrib');
                }
                
                $delete = delete_records('assignmentdistrib_submissions', 'assignmentdistrib_assignments_id', $key);
                if($delete === false) {
                    error('Delete assignment - students');
                }
            }
        }
    }
    return $retval;
}

function assignmentdistrib_array_first($array, &$key, &$value) {
    if(!is_array($array)) {
        error('Not array');
    }
    foreach($array as $key=>$value) {break;}
}

function assignmentdistrib_array_key_exists($key, $array) {
    if(!array_key_exists($key, $array)) {
        error("Unknown key");
    }
}

function assignmentdistrib_array_get_value($array, $key) {
    if(!array_key_exists($key, $array)) {
        error("Unknown key");
    } else {
        return $array[$key];
    }
}


function assignmentdistrib_change_grade(&$object, $grade, $comment, $commentformat) {
    $ret = false;
    global $USER;
    if($grade != $object->grade) {
        $ret = true;
        $object->grade = $grade;
        $object->gradedbyuserid = $USER->id;
        $object->timegraded = time();
    }
    if($comment != $object->comment) {
        $ret = true;
        $object->comment = $comment;
        $object->gradedbyuserid = $USER->id;
        $object->timegraded = time();
    }
    $object->commentformat = $commentformat;
    
    return $ret;
}

/**
 * Makes download URL
 *
 * @param string $relative_url
 * @return string
 */
function assignmentdistrib_download_url($relative_url)
{
    global $CFG;
    $id = optional_param('id', 0, PARAM_INT); // Course Module ID
     if (! $cm = get_coursemodule_from_id('assignmentdistrib', $id)) {
            error("Course Module ID was incorrect");
        }

        if (! $course = get_record("course", "id", $cm->course)) {
            error("Course is misconfigured");
        }
    if($relative_url[0] != '/') {
        $relative_url = '/'.$relative_url;
    }
    if ($CFG->slasharguments) {
        return "$CFG->wwwroot/file.php".'/'.$course->id.'/'.$CFG->moddata.'/assignmentdistrib'."$relative_url";
    } else {
        return "$CFG->wwwroot/file.php?file".'/'.$course->id.'/'.$CFG->moddata.'/assignmentdistrib'."$relative_url";  //FIXME?: file=$relative_url
    }
}


function mydump($var, $die = true) { // not in use
    global $USER;
    
    if($USER->username == 'rcadmin') {
        print_object($var);
        if($die) die();
    }
}

/**
* Functions for resetting the course
* 
* @author Sonja Milicic
*/

function assignmentdistrib_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'assignmentdistribheader', get_string('modulenameplural', 'assignmentdistrib'));

    $mform->addElement('checkbox', 'reset_assignmentdistrib_students', "Delete all student submissions");

}

function assignmentdistrib_reset_userdata($data) {
    if ($data->reset_assignmentdistrib_students == 1) {
        //empty submissions table
        if (!delete_records("{$CFG->prefix}assignmentdistrib_submissions", "", ""))
            error('Error deleting student submissions');
        if (!delete_records("{$CFG->prefix}assignmentdistrib_groups", "", ""))
            error('Error deleting student groups');
            
    }
}

function assignmentdistrib_reset_course_form_defaults($course) {
    return array('reset_assignmentdistrib_students'=>1);
}

?>