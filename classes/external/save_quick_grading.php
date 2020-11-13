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
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

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
        global $COURSE, $DB, $USER;

        $context = \context_user::instance($COURSE->id);

        self::validate_context($context);

       //Parameters validation.
        self::validate_parameters(self::save_quick_grading_parameters(),
            array('grades' => $grades)
        );
        $grades = json_decode($grades);
        $sql = "SELECT * FROM mdl_googledocs_grades  WHERE userid = :userid AND googledocid = :googledocid";
        $modifiedtimes = [];


        foreach ($grades as $grade) {
            $params = ['userid' => $grade->userid, 'googledocid' => $grade->googledocid];
            $current  =  $DB->get_records_sql($sql,$params);

            if(empty($current)) {  //New entry
                $data = new \stdClass();
                $data->googledocid = $grade->googledocid;
                $data->userid = $grade->userid;
                $data->timecreated = time();
                $data->timemodified = time();
                $data->grader = $USER->id;
                $data->grade = floatval($grade->grade);
                $finalgrade = (($data->grade > 0 && $data->grade <= $grade->maxgrade) ?
                    strval($data->grade) . '/' . strval($grade->maxgrade) : '-');
                $recordid = $DB->insert_record('googledocs_grades', $data, true);
                $modifiedtimes [] = ['userid' =>$grade->userid,
                                     'grade' => $data->grade,
                                     'googledocid' => $grade->googledocid,
                                     'comment' => $grade->comment,
                                     'rownumber' => $grade->rownumber,
                                     'timemodified' => date('l, d F Y, g:i A', $data->timemodified),
                                     'finalgrade' =>  $finalgrade ,
                                    ];

                if ($recordid != null) {
                    $data = new \stdClass();
                    $data->googledoc = $grade->googledocid;
                    $data->grade = $recordid;
                    $data->commenttext = $grade->comment;
                    $data->commentformat = 1;
                    $commentfeedbackrecord = $DB->insert_record('googledocsfeedback_comments', $data, true);
                }
            }

        }
        return array(
         'modifiedtimes' => json_encode($modifiedtimes)
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
                   'modifiedtimes' => new external_value(PARAM_RAW, 'Time modified  '),
                )
      );
    }
}