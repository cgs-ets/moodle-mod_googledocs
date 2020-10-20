<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *  External Web Service Template
 *
 * @package   mod_googledocs
 * @category
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_googledocs\external;

defined('MOODLE_INTERNAL') || die();

use external_function_parameters;
use external_value;
use external_single_structure;



require_once($CFG->libdir.'/externallib.php');
require_once($CFG->dirroot . '/mod/googledocs/lib.php');

/**
 * Trait implementing the external function mod_googledocs_create_students_file
 * This service performs differently depending on the distribution
 * dist = std_copy: Create a file for each student in the course
 * dist = dist_share_same Creates one file and assign the permissions to all students
 * dist = group_copy: Creates a copy for each group, then assign permission to the group members.
 * dist = grouping_copy: Creates a copy for each groping, then makes a copy for each group inside the grouping
 *        finally, assign permissions to the groups members.
 * For the rest of the distribution there are other services created.
 */
trait create_student_file {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
    */

    public static  function create_student_file_parameters(){
        return new external_function_parameters(
            array(
                  'folder_group_id' => new external_value(PARAM_RAW, 'Folder Group ID', PARAM_DEFAULT, 0),
                  'group_id' => new external_value(PARAM_RAW, 'Group ID'),
                  'grouping_id' => new external_value(PARAM_RAW, 'Grouping ID'),
                  'instance_id' => new external_value(PARAM_RAW, 'instance ID'),
                  'parentfile_id' => new external_value(PARAM_ALPHANUMEXT, 'ID of the file to copy'),
                  'student_email' =>  new external_value(PARAM_RAW, 'student email'),
                  'student_id' => new external_value(PARAM_RAW, 'student ID'),
                  'student_name' => new external_value(PARAM_RAW, 'studentname')
            )
        );
    }

    /**
     * Create the file for the student
     * @param  string $timetableuser represents a user.
     *         string $timetablerole represents the role of the user.
     *         int $date represents the date in timestamp format.
     *         int $nav represents a nav direction, 0: Backward, 1: Forward.
     * @return a timetable for a user.$by_group, $by_grouping, $group_id, $grouping_id,
     */
    public static function create_student_file($folder_group_id, $group_id, $grouping_id,
                                               $instance_id, $parentfile_id,$student_email, $student_id, $student_name) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        //Parameters validation
        self::validate_parameters(self::create_student_file_parameters(),
            array(
                  'folder_group_id' => $folder_group_id,
                  'group_id' => $group_id,
                  'grouping_id' => $grouping_id,
                  'instance_id' => $instance_id,
                  'parentfile_id' => $parentfile_id,
                  'student_email'=> $student_email,
                  'student_id' => $student_id,
                  'student_name' => $student_name,
                )
        );

        $filedata = "SELECT * FROM mdl_googledocs WHERE id = :id ";
        $data = $DB->get_record_sql($filedata, ['id'=> $instance_id]);

        if ($data->distribution == 'group_copy' || $data->distribution == 'group_grouping_copy') {
            $filedata = "SELECT gf.name as groupfilename, gf.url, gf.groupid, gf.groupingid, gd.*  FROM mdl_googledocs AS gd
                            INNER JOIN mdl_googledocs_files AS gf
                            ON gd.id = gf.googledocid
                            WHERE gd.id = :instanceid AND gf.groupid = :group_id";

            $data = $DB->get_record_sql($filedata, ['instanceid'=> $instance_id, 'group_id' =>$group_id]);
            // google_doc_url is the url of the original file
            // when dist is by group a file for the group is created and that file is the one
            //to share with the groups members.
            $data->google_doc_url = $data->url;
            $data->docid = $parentfile_id;
            $data->name = $data->groupfilename; //Get the name for the group's file.
            $data->groupingid = $grouping_id;

        }

        // Generate the student file
        $gdrive = new \googledrive($context->id, false, false, true);
        list($role, $commenter) = $gdrive->format_permission($data->permissions);
        $student = new \stdClass();
        $student->id = $student_id;
        $student->name = $student_name;
        $student->email = $student_email;
        $student->type = 'user';
        $fromexisting = $data->use_document == 'new' ? false : true;
        //$teachers = $gdrive->get_enrolled_teachers($data->course);

        switch ($data->distribution) {
            case 'std_copy':
                $url [] = $gdrive->make_file_copy($data, $data->parentfolderid, $student, $role,
                    $commenter, $fromexisting);
                $data->sharing = 1;
                $DB->update_record('googledocs', $data);
                break;
            case 'dist_share_same':
                $url [] = $gdrive->share_single_copy($student, $data, $role, $commenter, true);
                break;
            case 'group_copy' :
                $url [] = $gdrive->make_file_copy_for_group($data, $student, $role, $commenter, $fromexisting);
                break;
            case 'std_copy_group' :
                 $url = $gdrive->std_copy_group_grouping_copy($data, $student, $role, $commenter, $fromexisting, $gdrive);
                break;
            case 'std_copy_grouping':
                $url = $gdrive->std_copy_group_grouping_copy($data, $student, $role, $commenter, $fromexisting, $gdrive);
                break;
            case 'std_copy_group_grouping':
                $url = $gdrive->std_copy_group_grouping_copy($data, $student, $role, $commenter, $fromexisting, $gdrive);
                break;
            case 'group_grouping_copy':
                 $url [] = $gdrive->make_file_copy_for_group($data, $student, $role, $commenter, $fromexisting);
                break;

            default:
                break;
        }

        return array(
            'url'=> json_encode($url, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the student
     * @return external_single_structure
     *
     */
    public static function create_student_file_returns(){
        return new external_single_structure(
                array(
                    'url' => new external_value(PARAM_RAW,'File URL '),
                 )
      );
    }
}