<?php
/**
 * Upgrade procedures for assignmentdistrib
 *
 * @author Krešimir Kroflin
 * @package assignmentdistrib
 **/

/**
 * This function does anything necessary to upgrade 
 * older versions to match current functionality 
 *
 * @uses $CFG
 * @param int $oldversion The prior version number
 * @return boolean Success/Failure
 **/
function assignmentdistrib_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2007010101) {
        table_column('assignmentdistrib_groups', 'cumulativegrade', 'grade', 'integer', '11', 'unsigned', 0, 'not null', '');
    }
    if ($oldversion < 2007010801) {
        table_column('assignmentdistrib_submissions', '', 'timegraded', 'integer', '10', 'unsigned', 0, 'not null', 'grade');
    }
    if ($oldversion < 2007010803) {
        table_column('assignmentdistrib_groups', '', 'timegraded', 'integer', '10', 'unsigned', 0, 'not null', 'grade');
    }
    if ($oldversion < 2007011501) {
        table_column('assignmentdistrib_submissions', '', 'gradedbyuserid', 'integer', '10', 'unsigned', 0, 'not null', 'grade');
        table_column('assignmentdistrib_groups', '', 'gradedbyuserid', 'integer', '10', 'unsigned', 0, 'not null', 'grade');
    }
    if ($oldversion < 2007011502) {
        modify_database('', 'ALTER TABLE prefix_assignmentdistrib_submissions DROP COLUMN teacher');
        modify_database('', 'ALTER TABLE prefix_assignmentdistrib_submissions DROP COLUMN timemarked');
    }
    if ($oldversion < 2007011503) {
        table_column('assignmentdistrib_groups', 'grade', 'grade', 'integer', '11', 'signed', -1, 'not null', '');
        table_column('assignmentdistrib_submissions', 'grade', 'grade', 'integer', '11', 'signed', -1, 'not null', '');
    }
    if ($oldversion < 2007011504) {
        modify_database('', 'UPDATE prefix_assignmentdistrib_submissions SET grade = -1 WHERE timegraded = 0');
        modify_database('', 'UPDATE prefix_assignmentdistrib_groups SET grade = -1 WHERE timegraded = 0');
    }
    if ($oldversion < 2009090701) {
        table_column('assignmentdistrib_assignments', '', 'available', 'integer', '1', 'unsigned', 1, 'not null', 'groupstudentmax');
        table_column('assignmentdistrib_assignments', '', 'approved', 'integer', '1', 'unsigned', 1, 'not null', 'available');
        table_column('assignmentdistrib_assignments', '', 'createdbyuserid', 'integer', '10', 'unsigned', 0, 'not null', 'assignmentdistribid');
        
        table_column('assignmentdistrib', '', 'allowgroupmarket', 'integer', '1', 'unsigned', 0, 'not null', 'assignmenttype');
        table_column('assignmentdistrib', '', 'allowstudentchange', 'integer', '1', 'unsigned', 0, 'not null', 'allowgroupmarket');
        table_column('assignmentdistrib', '', 'leadercangrade', 'integer', '1', 'unsigned', 1, 'not null', 'timeavailable');
        table_column('assignmentdistrib', '', 'suggestable', 'integer', '1', 'unsigned', 0, 'not null', 'leadercangrade');
        
        table_column('assignmentdistrib_groups', '', 'allowjoin', 'integer', '1', 'unsigned', 0, 'not null', '');
        table_column('assignmentdistrib_groups', 'cumulativegrade', 'grade', 'integer', '11', '', -1, 'not null', '');
        table_column('assignmentdistrib_groups', '', 'gradedbyuserid', 'integer', '10', 'unsigned', 0, 'not null', 'grade');
        table_column('assignmentdistrib_groups', '', 'timegraded', 'integer', '10', 'unsigned', 0, 'not null', 'gradedbyuserid');

        modify_database("", "ALTER TABLE `prefix_assignmentdistrib_groups` DROP KEY teacher");
        modify_database("", "ALTER TABLE `prefix_assignmentdistrib_groups` DROP KEY timemarked");
        
        
        table_column('assignmentdistrib_submissions', 'userid', 'userid', 'integer', '10', 'unsigned', 0, 'not null');
        
        table_column('assignmentdistrib_submissions', 'grade', 'grade', 'integer', '11', '', -1, 'not null');
        table_column('assignmentdistrib_submissions', 'teacher', 'gradedbyuserid', 'integer', '10', 'unsigned', 0, 'not null');
        table_column('assignmentdistrib_submissions', 'timemarked', 'timegraded', 'integer', '10', 'unsigned', 0, 'not null');
        
        modify_database("", "CREATE TABLE `prefix_assignmentdistrib_penalties` (
                                      `id` int(10) unsigned NOT NULL auto_increment,
                                      `assignmentdistrib_id` int(10) unsigned NOT NULL default '0',
                                      `time` int(10) unsigned NOT NULL default '0',
                                      `penalty_grade` int(11) NOT NULL default '0',
                                      PRIMARY KEY(`id`),
                                      KEY `assignmentdistrib_id` (`assignmentdistrib_id`)
                        ) CHARSET=utf8 COMMENT='Defines penalties for late submissions';");
    }
    if($oldversion < 2010112406) {
        assignmentdistrib_update_grades();
    }
    if($oldversion < 2011101401) {
        modify_database('', 'UPDATE `mdl_assignmentdistrib_submissions`
                            SET var1=MID(var1, LOCATE(\'assignmentdistrib\',var1)+17)
                            WHERE var1 LIKE \'%assignmentdistrib%\'');
    }
    if($oldversion < 2011101405) {
        $submission_all = get_records('assignmentdistrib_submissions', 'assignmenttype', ASSIGNMENTDISTRIB_ASSIGNMENTTYPE_UPLOADSINGLE);
        foreach($submission_all as $submission) {
            $updatedata = new object();
            $updatedata->id = $submission->id;
            //var1 = /16/submitted/calibre.exe

            $var1 = $submission->var1;
            $var1_exploded = explode('/', $submission->var1);
            if($var1_exploded[2] == 'submissions') continue;

            $assignment = get_record('assignmentdistrib_assignments', 'id', $submission->assignmentdistrib_assignments_id);
            $aid = $assignment->assignmentdistribid;

            $assignmentdistrib = get_record('assignmentdistrib', 'id', $aid);
            $course_id = $assignmentdistrib->course;

            //$dirnamebase = '/' . $assignmentdistrib . '/submitted/';
            $file = $var1_exploded[3];

            $dir_root =  $CFG->dataroot . '/'.$course_id.'/'.$CFG->moddata.'/assignmentdistrib';
            $dir_rel_old = '/'.$var1_exploded[1].'/submitted';
            $dir_rel_new = '/'.$aid.'/submissions';

            $var1_new = $dir_rel_new . '/' . $file;
            $updatedata->var1 = $var1_new;

            $dir_new = $dir_root . $dir_rel_new;
            //print_object($var1);
            //print_object($var1_new);
            //die();
            $status = true;

            $file_old = $dir_root . $var1;
            $file_new = $dir_root . $var1_new;
            if(!is_file($file_new) || is_file($file_old)) {
                if(!is_dir($dir_new)) {
                    $status = $status && mkdir($dir_new, 0777, true);
                }

                if(!$status) {
                    return false;
                }

                $status = $status && rename($dir_root . $var1, $dir_root . $var1_new);
                if(!$status) {
                    return false;
                }
            }

            update_record('assignmentdistrib_submissions', $updatedata);
        }
    }

    return true;
}

?>
