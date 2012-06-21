<?php
/**
 * This page prints a particular instance of assignmentdistrib
 * 
 * @author  Krešimir Kroflin
 * @package assignmentdistrib
 **/
    require_once("../../config.php");
    require_once("lib.php");
    
    /*
    mod/assignmentdistrib/assignment.php?
    &aid='.s($item->assignment_id)
    
    */
                	          
    $id = required_param('id', PARAM_INT); // Course Module ID, or
    
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
     $studentid =  required_param('studentid', PARAM_INT);
    //get assignment id
    $aidq = get_record_sql("SELECT assignmentdistrib_assignments_id FROM {$CFG->prefix}assignmentdistrib_submissions
                           JOIN {$CFG->prefix}assignmentdistrib_assignments ON assignmentdistrib_assignments_id = {$CFG->prefix}assignmentdistrib_assignments.id
                           WHERE userid = $studentid AND assignmentdistribid = $assignmentdistrib->id");
    $aid = $aidq->assignmentdistrib_assignments_id;
    add_to_log($course->id, "assignmentdistrib", "view assignment", "view.php?id=$cm->id&aid=$aid");

    $strassignmentdistribs = get_string("modulenameplural", "assignmentdistrib");
    $strassignmentdistrib  = get_string("modulename", "assignmentdistrib");

    if(!isteacher($course->id) && !isstudent($course->id)) {
        error('Forbidden');
    }
    
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
    
    print_header($name, $name);
    
    //print_heading(get_string('viewassignment', 'assignmentdistrib'));
    print_simple_box_start('center', '90%', '', '', 'generalbox', 'view');
    ?>
    
    <table cellpadding="5" width="100%">
        <tr valign="top">
            <td align="right" nowrap><strong><?php  print_string('name') ?>:</strong></td>
            <td><?php p($name) ?></td>
        </tr>
        <tr valign="top">
            <td align="right" nowrap><strong><?php print_string("description", "assignmentdistrib") ?>:</strong></td>
            <td><?php echo format_text($description, $descriptionformat); ?></td>
        </tr>
        <tr valign="top">
            <td colspan="2"><hr /></td>
        </tr>
    
        <tr valign="top">
            <td align="right"><strong><?php  print_string('groupstudentmin', 'assignmentdistrib') ?>:<sup>(1)</sup></strong></td>
            <td align="left" valign="middle"><?php p($groupstudentmin) ?></td>
        </tr>
        <tr valign="top">
            <td align="right"><strong><?php  print_string('groupstudentmax', 'assignmentdistrib') ?>:<sup>(1)</sup></strong></td>
            <td align="left" valign="middle"><?php p($groupstudentmax) ?></td>
        </tr>
        <tr valign="top">
            <td align="right"><strong><?php  print_string('maxnumberofrepeats', 'assignmentdistrib') ?>:<sup>(2)</sup></strong></td>
            <td align="left" valign="middle"><?php p($maxnumberofrepeats) ?></td>
        </tr>
    </table>
    <div style="color: gray; margin-top: 15px; font-size: smaller;">
        <sup>(1)</sup> <?php  print_string('notice_group_add', 'assignmentdistrib'); ?><br>
        <sup>(2)</sup> <?php  print_string('notice_maxnumberofrepeats', 'assignmentdistrib'); ?><br>
    </div>
    <br />
    <?php
    print_simple_box_end();

    
/// Finish the page
    print_footer('none');    
?>
