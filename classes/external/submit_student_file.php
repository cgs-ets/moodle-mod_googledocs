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
require_once($CFG->dirroot . '/mod/googledocs/locallib.php');

/**
 * Trait implementing the external function mod_googledocs_create_students_file
 */
trait submit_student_file {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
    */

    public static  function submit_student_file_parameters() {
        return new external_function_parameters(
            array(
                'fileid' => new external_value(PARAM_RAW, 'File ID given by Google drive'),
                'instanceid' => new external_value(PARAM_RAW, ' Googledoc instance id'),
                'groupid' => new external_value(PARAM_RAW, 'Group ID', PARAM_DEFAULT, '0'),
                'email' => new external_value(PARAM_RAW, 'Email of the user')
            )
        );
    }

    /**
     * Submit students file to grade.
     * @param  string $timetableuser represents a user.
     *         string $timetablerole represents the role of the user.
     *         int $date represents the date in timestamp format.
     *         int $nav represents a nav direction, 0: Backward, 1: Forward.
     * @return a timetable for a user.
     */
    public static function submit_student_file($fileid, $instanceid, $groupid, $email) {
        global $COURSE, $DB, $USER;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::submit_student_file_parameters(),
            array('fileid' => $fileid,
                  'instanceid' => $instanceid,
                  'groupid' => $groupid,
                  'email' =>$email)
        );

        // Get the Google Drive object.
        $gdrive =  new \googledrive($context->id, false, false, true, true);
        $email = strtolower($email);
        $result = $gdrive->update_permission_when_submitted($fileid, $email);

        $recordid = null;

        if ($result) {

            $data = new \stdClass();
            $data->googledoc = $instanceid;
            $data->timecreated = time();
            $data->timemodified = time();
            $data->userid = $USER->id;
            $data->groupid = $groupid;
            $data->status = get_string('submitted', 'googledocs');
            $recordid = $DB->insert_record('googledocs_submissions', $data, true);

            $r = "SELECT * FROM mdl_googledocs_files WHERE googledocid = :googledocid AND userid = :userid";
            $dataobject = $DB->get_record_sql($r, ['googledocid'=> $instanceid, 'userid' =>  $USER->id]);
            $update = new \stdClass();
            $update->id = $dataobject->id;
            $update->permission = 'view';
            $update->submit_status = 'Submitted';

            $DB->update_record('googledocs_files', $update);

        }

        return array(
            'recordid' => $recordid
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the student
     * @return external_single_structure
     *
     */
    public static function submit_student_file_returns() {
        return new external_single_structure(
                array(
                    'recordid' => new external_value(PARAM_RAW, 'DB record ID '),
                )
      );
    }
}