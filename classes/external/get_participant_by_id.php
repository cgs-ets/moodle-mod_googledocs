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
use core_user_external;

require_once($CFG->libdir.'/externallib.php');
require_once($CFG->dirroot . '/mod/googledocs/lib.php');
require_once($CFG->dirroot . '/mod/googledocs/locallib.php');
require_once($CFG->dirroot . "/user/lib.php");
require_once("$CFG->dirroot/user/externallib.php");

/**
 * Trait implementing the external function get_participant_by_id
 */
trait get_participant_by_id {


    /**
     * Returns description of method parameters
     *
    */

    public static  function get_participant_by_id_parameters(){
         return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_RAW, 'user ID'),
                'googledocid' => new external_value(PARAM_RAW, 'Instance ID'),
            )
        );
    }


    public static function get_participant_by_id($userid, $googledocid) {
        global $COURSE, $DB, $PAGE, $CFG;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::get_participant_by_id_parameters(),
            array('userid' => $userid,
                'googledocid' => $googledocid)
        );

        // Get the File and grading details
        $sql = "SELECT u.id as userid, u.firstname, u.lastname, g.grade as maxgrade, gf.* FROM mdl_googledocs_files as gf
                INNER JOIN mdl_user as u ON gf.userid = u.id
                JOIN mdl_googledocs as g on g.id = {$googledocid}
                WHERE googledocid = {$googledocid} and gf.userid = {$userid};";

        $results = $DB->get_records_sql($sql);

        $graded = $DB->get_record('googledocs_grades', array('googledocid' => $googledocid, 'userid' => $userid));

        $data = new \stdClass();
         // Get data from gradebook
        $sql = "SELECT * FROM mdl_grade_grades as gg
                WHERE itemid = (SELECT id as itemid FROM mdl_grade_items
                                WHERE iteminstance = {$googledocid}
                                AND itemtype = 'mod' AND itemmodule = 'googledocs' )
                AND userid = {$userid}";
        $gg = $DB->get_record_sql($sql);

        $lockedoroverriden = false;
        $gradefromgradebook = 0;
        $gradebookurl = '';

        if ($gg && ($gg->locked != "0" || $gg->overridden != "0")) {
            $lockedoroverriden = true;
            $gradefromgradebook = $gg->finalgrade;
            $gradebookurl = new \moodle_url($CFG->wwwroot . '/grade/report/grader/index.php?', ['id' =>$COURSE->id]);
        }

        foreach ($results as $record) {
            $data->userid = $userid;
            $data->fileurl = $record->url;
            $data->maxgrade = $record->maxgrade;
            $data->graded =$graded;
            $data->finalgrade = number_format($gradefromgradebook,2);
            $data->lockedoroverriden = $lockedoroverriden;
            $data->lockedoroverriden = $lockedoroverriden;
            list($data->gradegiven, $data->commentgiven) = get_grade_comments($googledocid, $record->userid);
        }

        $output = $PAGE->get_renderer('core');
        return array(
            'html' => $output->render_from_template('mod_googledocs/grading_panel', $data),
            'data' => json_encode($data),
        );


    }

    /**
     * Describes the structure of the function return value.
     * @return external_single_structures
     */
    public static function get_participant_by_id_returns() {
        $userdescription = core_user_external::user_description();
        $userdescription->default = [];
        $userdescription->required = VALUE_OPTIONAL;

        return new external_single_structure(array(
            'html' => new external_value(PARAM_RAW, 'HTML with the grade panel'),
            'data' => new external_value(PARAM_RAW, 'Template Context'),
        ));
    }
}