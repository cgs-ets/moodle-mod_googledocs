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
require_once($CFG->libdir.'/externallib.php');
require_once($CFG->dirroot . '/mod/googledocs/lib.php');

/**
 * Trait implementing the external function mod_save_quick_grading
 */

trait save_quick_grading{


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
    */

    public static  function save_quick_grading_parameters(){
        return new external_function_parameters(
            array(
               'grades' => new external_value(PARAM_RAW, 'A JSON with all the grades'),
            )
        );
    }


    public static function save_quick_grading($grades) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

       //Parameters validation.
        self::validate_parameters(self::save_quick_grading_parameters(),
            array('grades' => $grades)
        );

        $data = new \stdClass();
        $data->googledocid = $googledocid;
        $data->userid = $userid;
        $data->grade = $grade;
        $data->late = 0;
        $data->completed = 1;

        /*if (($current->grade < 0 || $current->grade === null) &&
                ($modified->grade < 0 || $modified->grade === null)) {
                // Different ways to indicate no grade.
                $modified->grade = $current->grade; // Keep existing grade.
            }
            // Treat 0 and null as different values.
            if ($current->grade !== null) {
                $current->grade = floatval($current->grade);
            }*/

        //$recordid = $DB->insert_record('googledocs_grades', $data, true);
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
    public static function save_quick_grading_returns(){
        return new external_single_structure(
                array(
                   'recordid' => new external_value(PARAM_RAW, 'DB record ID '),
                )
      );
    }
}