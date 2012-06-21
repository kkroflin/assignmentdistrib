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
    
    $id = required_param('id', PARAM_INT); // Course Module ID
    $uid = required_param('uid', PARAM_INT);  // User ID
    
    if (! $cm = get_record("course_modules", "id", $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }

    if (! $assignmentdistrib = get_record("assignmentdistrib", "id", $cm->instance)) {
        error("Course module is incorrect");
    }

    require_login($course->id);
    add_to_log($course->id, "assignmentdistrib", "view: onlinetext", "onlinetext.php?id=$cm->id&uid=$uid");

/// Print the page header
    if(!isteacher($course->id)) {
        error('Forbidden');
    }

    $record = get_record('assignmentdistrib', 'id', $cm->instance);
    $time = time();

    $assignmentsubmission = get_record_sql("SELECT *, dbas.id AS submissionid
                                            FROM {$CFG->prefix}assignmentdistrib_submissions AS dbas
                                                , {$CFG->prefix}assignmentdistrib_assignments AS dbaa
                                            WHERE  dbas.assignmentdistrib_assignments_id = dbaa.id
                                                AND dbas.userid={$uid}
                                                AND dbaa.assignmentdistribid={$cm->instance}
                                            ");
    $name = $assignmentsubmission->name;
    print_header($name, $name);
    print_simple_box_start('center', '90%', '', '', 'generalbox', 'view');
    
    if($assignmentsubmission && $assignmentsubmission->timemodified != 0)
    {
        if($assignmentsubmission->assignmenttype == ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_ONLINETEXT) {
            echo format_text($assignmentsubmission->var1, $assignmentsubmission->var2);
        } else if($assignmentsubmission->assignmenttype == ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_UPLOADSINGLE) {
            echo '<a target="_blank" href="'.assignmentdistrib_download_url($assignmentsubmission->var1).'">Download</a>';
        } else {
            echo '<em>Offline activity</em>';
        }
    } else {
        error('Not found');
    }
    print_simple_box_end();
    
/// Finish the page
    print_footer('none');
?>
