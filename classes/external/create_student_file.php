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
 * @copyright 2020 Veronica Bermegui, Canberra Grammar School <veronica.bermegui@cgs.act.edu.au>
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
 */
trait create_student_file {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *by_group : by_group,
                    by_grouping: by_grouping,
                    group_id : student_group_id,
                    grouping_id: student_grouping_id,
    */

    public static  function create_student_file_parameters(){
        return new external_function_parameters(
            array(
                  'by_group' => new external_value(PARAM_BOOL, 'True is distr. is by group'),
                  'by_grouping'  => new external_value(PARAM_BOOL, 'True is distr. is by grouping'),
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
     * @return a timetable for a user.
     */
    public static function create_student_file($by_group, $by_grouping, $group_id, $grouping_id,
                                               $instance_id, $parentfile_id,$student_email, $student_id, $student_name) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        //Parameters validation
        self::validate_parameters(self::create_student_file_parameters(),
            array(
                  'by_group' => $by_group,
                  'by_grouping' => $by_grouping,
                  'group_id' => $group_id,
                  'grouping_id' => $grouping_id,
                  'instance_id' => $instance_id,
                  'parentfile_id' => $parentfile_id,
                  'student_email'=> $student_email,
                  'student_id' => $student_id,
                  'student_name' => $student_name,
                )
        );

        if ($by_group) {
            $filedata = "SELECT gf.name as groupfilename, gf.url, gf.groupid, gd.*  FROM mdl_googledocs AS gd
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

        }else if($by_grouping){
             $filedata = "SELECT gf.name as groupfilename, gf.url, gf.groupid, gd.*  FROM mdl_googledocs AS gd
                            INNER JOIN mdl_googledocs_files AS gf
                            ON gd.id = gf.googledocid
                            WHERE gd.id = :instanceid AND gf.groupingid = :grouping_id";

            $data = $DB->get_record_sql($filedata, ['instanceid'=> $instance_id, 'grouping_id' =>$grouping_id]);
            // google_doc_url is the url of the original file
            // when dist is by group a file for the group is created and that file is the one
            //to share with the groups members.
            $data->google_doc_url = $data->url;
            $data->docid = $parentfile_id;
            $data->groupid = $group_id;
            $data->name = $data->groupfilename; //Get the name for the group's file.
        }else{
            $filedata = "SELECT * FROM mdl_googledocs WHERE docid = :parentfile_id ";
            $data = $DB->get_record_sql($filedata, ['parentfile_id'=> $parentfile_id]);

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

        if ($data->distribution == 'std_copy') {
           $url= $gdrive->make_file_copy($data, [$data->parentfolderid], $student, $role, $commenter, $fromexisting);
        }else if ($data->distribution == 'dist_share_same'){
           $url = $gdrive->share_single_copy($student, $data, $role, $commenter);
        }else if ($data->distribution == 'group_copy' || $data->distribution == 'grouping_copy'){
           $url = $gdrive->make_file_copy_for_group($data, $student, $role, $commenter, $fromexisting);
        }

        return array(
            'url'=>$url
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