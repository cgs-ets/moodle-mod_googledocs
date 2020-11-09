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
 * Trait implementing the external function mod_googledocs_delete_files
 */
trait get_participant {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
ethodname: 'mod_googledocs_get_participant',
                    args: {
                        userid: userid,
                        googledocid: googledocId,

                    }
    */

    public static  function get_participant_parameters(){
         return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_RAW, 'Distribution type'),
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

        if ($userid == $USER->id) {
            $participant = clone ($USER);
        } else {
            $participant = $DB->get_record('user', array('id' => $userid));
        }

        $return['user'] = user_get_user_details($participant, $COURSE);

    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the grouping
     * @return external_single_structures     *
     */
    public static function get_participant_returns(){
        $userdescription = core_user_external::user_description();
        $userdescription->default = [];
        $userdescription->required = VALUE_OPTIONAL;

        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'ID of the user'),
            'fullname' => new external_value(PARAM_NOTAGS, 'The fullname of the user'),

            'user' => $userdescription,
        ));
    }
}