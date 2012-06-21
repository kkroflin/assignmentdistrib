<?php
/**
 * This page lists all the instances of assignmentdistrib in a particular course
 *
 * @author Krešimir Kroflin
 * @version $Id: index.php,v 1.1 2008-11-10 13:28:29 kresimir Exp $
 * @package assignmentdistrib
 **/

/// Replace assignmentdistrib with the name of your module

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id', PARAM_INT);   // course

    if (! $course = get_record("course", "id", $id)) {
        error("Course ID is incorrect");
    }

    require_login($course->id);

    add_to_log($course->id, "assignmentdistrib", "view all", "index.php?id=$course->id", "");


/// Get all required strings

    $strassignmentdistribs = get_string("modulenameplural", "assignmentdistrib");
    $strassignmentdistrib  = get_string("modulename", "assignmentdistrib");
    $strassignments = get_string("modulenameplural", "assignment");

/// Print the header

    $navlinks = array(
                    array('name' => $strassignmentdistribs, 'link' => '', 'type' => 'activity')
                );
    $navigation  = build_navigation($navlinks);
    /*
    if ($course->category) {
        $navigation = "<a href=\"../../course/view.php?id=$course->id\">$course->shortname</a> ->";
    } else {
        $navigation = '';
    }

    print_header("$course->shortname: $strassignmentdistribs", "$course->fullname", "$navigation $strassignmentdistribs", "", "", true, "", navmenu($course));
    */
    print_header("$course->shortname: $strassignmentdistribs", "$course->fullname", $navigation, "", "", true, "", navmenu($course));

/// Get all the appropriate data

    if (! $assignmentdistribs = get_all_instances_in_course("assignmentdistrib", $course)) {
        notice("There are no assignmentdistribs", "../../course/view.php?id=$course->id");
        die;
    }

/// Print the list of instances (your module will probably extend this)

    $timenow = time();
    $strname  = get_string("name");
    $strweek  = get_string("week");
    $strtopic  = get_string("topic");

    if ($course->format == "weeks") {
        $table->head  = array ($strweek, $strname);
        $table->align = array ("center", "left");
    } else if ($course->format == "topics") {
        $table->head  = array ($strtopic, $strname);
        $table->align = array ("center", "left", "left", "left");
    } else {
        $table->head  = array ($strname);
        $table->align = array ("left", "left", "left");
    }

    foreach ($assignmentdistribs as $assignmentdistrib) {
        if (!$assignmentdistrib->visible) {
            //Show dimmed if the mod is hidden
            $link = "<a class=\"dimmed\" href=\"view.php?id=$assignmentdistrib->coursemodule\">$assignmentdistrib->name</a>";
        } else {
            //Show normal if the mod is visible
            $link = "<a href=\"view.php?id=$assignmentdistrib->coursemodule\">$assignmentdistrib->name</a>";
        }

        if ($course->format == "weeks" or $course->format == "topics") {
            $table->data[] = array ($assignmentdistrib->section, $link);
        } else {
            $table->data[] = array ($link);
        }
    }

    echo "<br />";

    print_table($table);

/// Finish the page

    print_footer($course);

?>
