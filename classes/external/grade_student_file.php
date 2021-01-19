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

require_once($CFG->dirroot . '/mod/googledocs/locallib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/googledocs/lib.php');

/**
 * Trait implementing the external function mod_googledocs_grade_student_file
 */
trait grade_student_file {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */
    public static function grade_student_file_parameters() {
        return new external_function_parameters(
            array(
            'googledocid' => new external_value(PARAM_RAW, 'Instance ID'),
            'userid' => new external_value(PARAM_RAW, 'user ID'),
            'grade' => new external_value(PARAM_RAW, 'Grade'),
            )
        );
    }

    public static function grade_student_file($googledocid, $userid, $grade) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::grade_student_file_parameters(),
            array('googledocid' => $googledocid,
                'userid' => $userid,
                'grade' => $grade)
        );

        $data = new \stdClass();
        $data->googledocid = $googledocid;
        $data->userid = $userid;
        $data->grade = $grade;
        $data->late = 0;
        $data->completed = 1;

        $recordid = $DB->insert_record('googledocs_grades', $data, true);
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
    public static function grade_student_file_returns() {
        return new external_single_structure(
            array(
            'recordid' => new external_value(PARAM_RAW, 'DB record ID '),
            )
        );
    }

}
