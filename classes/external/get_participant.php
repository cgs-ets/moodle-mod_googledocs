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

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/googledocs/lib.php');
require_once($CFG->dirroot . '/mod/googledocs/locallib.php');
require_once($CFG->dirroot . "/user/lib.php");
require_once("$CFG->dirroot/user/externallib.php");

/**
 * Trait implementing the external function mod_googledocs_delete_files
 */
trait get_participant {

    /**
     * Returns description of method parameters
     *
     */
    public static function get_participant_parameters() {
        return new external_function_parameters(
            array(
            'userid' => new external_value(PARAM_RAW, 'user ID'),
            'googledocid' => new external_value(PARAM_RAW, 'Instance ID'),
            )
        );
    }

    public static function get_participant($userid, $googledocid) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::get_participant_parameters(),
            array('userid' => $userid,
                'googledocid' => $googledocid)
        );
        // Get the File and grading details.
        $sql = "SELECT u.id as userid, u.firstname, u.lastname, gf.* FROM mdl_googledocs_files as gf
                INNER JOIN mdl_user as u ON gf.userid = u.id
                WHERE googledocid = :googledocid and gf.userid = :userid
                ORDER BY  u.firstname";

        $results = $DB->get_records_sql($sql, array('googledocid' => $googledocid, 'userid' => $userid));

        $filegradedata = new \stdClass();
        foreach ($results as $record) {
            $filegradedata->fileurl = $record->url;
            list($filegradedata->grade, $filegradedata->comment) = get_grade_comments($googledocid, $record->userid);
        }
        $participant = $DB->get_record('user', array('id' => $userid));

        $user = (object) user_get_user_details($participant, $COURSE);

        return ['id' => $user->id,
            'fullname' => $user->fullname,
            'fileurl' => $filegradedata->fileurl,
            'commentgiven' => $filegradedata->comment,
            'gradegiven' => $filegradedata->grade,
            'user' => $user];
    }

    /**
     * Describes the structure of the function return value.
     * @return external_single_structures
     */
    public static function get_participant_returns() {
        $userdescription = core_user_external::user_description();
        $userdescription->default = [];
        $userdescription->required = VALUE_OPTIONAL;

        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'ID of the user'),
            'fullname' => new external_value(PARAM_NOTAGS, 'The fullname of the user'),
            'fileurl' => new external_value(PARAM_RAW, 'URL'),
            'commentgiven' => new external_value(PARAM_RAW, 'URL'),
            'gradegiven' => new external_value(PARAM_RAW, 'URL'),
            'groupid' => new external_value(PARAM_INT, 'for group assignments this is the group id', VALUE_OPTIONAL),
            'user' => $userdescription
        ));
    }

}
