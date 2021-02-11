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

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/googledocs/lib.php');
require_once($CFG->dirroot . '/mod/googledocs/locallib.php');

/**
 * Trait implementing the external function mod_googledocs_update_sharing
 */
trait update_sharing {

    /**
     * Returns description of method parameters
     * @return external_function_parameters

     */
    public static function update_sharing_parameters() {
        return new external_function_parameters(
            array(
            'file_ids' => new external_value(PARAM_RAW, 'File ID'),
            'instance_id' => new external_value(PARAM_RAW, 'Instance id'),
            )
        );
    }

    public static function update_sharing($file_ids, $instance_id) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::update_sharing_parameters(),
            array('file_ids' => $file_ids,
                'instance_id' => $instance_id)
        );

        $file_ids = json_decode($file_ids, $instance_id);
        $r = [];
        foreach ($file_ids as $i => $id) {

            $id = $DB->get_field('googledocs', 'id', ['docid' => $id, 'id' => $instance_id]);
            $d = new \stdClass();
            $d->id = $id;
            $d->sharing = 1;
            $r [] = $DB->update_record('googledocs', $d);
        }

     
        return array(
            'results' => json_encode($r)
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the grouping
     * @return external_single_structure
     *
     */
    public static function update_sharing_returns() {
        return new external_single_structure(
            array(
            'results' => new external_value(PARAM_RAW, 'DB update result'),
            )
        );
    }

}
