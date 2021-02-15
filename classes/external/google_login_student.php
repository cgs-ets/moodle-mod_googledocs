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
 * Trait implementing the external function mod_googledocs_google_login_student
 */
trait google_login_student {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */
    public static function google_login_student_parameters() {
        return new external_function_parameters(
            array(
            )
        );
    }

    public static function google_login_student() {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Get the Google Drive object.
        $client = new \googledrive($context->id, false, false, true, true);
        $login = ['isloggedin' => $client->check_google_login()];

        // Check whether the user is logged into their Google account.

        if (!$client->check_google_login()) {
            $output = $client->display_login_button(true, $context);
            $login ['loginbutton'] = $output;
        }

        return array(
            'result' => json_encode($login)
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the student
     * @return external_single_structure
     *
     */
    public static function google_login_student_returns() {
        return new external_single_structure(
            array(
            'result' => new external_value(PARAM_RAW, 'Google login button and login status'),
            )
        );
    }

}
