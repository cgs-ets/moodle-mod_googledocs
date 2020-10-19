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
 * This service creates group or grouping folders.
 */
trait create_group_folder_struct {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
    */

    public static  function create_group_folder_struct_parameters(){
        return new external_function_parameters(
            array( 'instanceid' => new external_value(PARAM_RAW, 'instance ID')
                ));
    }

 /**
  * Create the folder for the group(s)
  * @global type $COURSE
  * @global type $DB
  * @param int $instanceid
  * @return array
  */
    public static function create_group_folder_struct($instanceid) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::create_group_folder_struct_parameters(),
            array('instanceid' => $instanceid));

        $filedata = "SELECT * FROM mdl_googledocs WHERE id = :id ";
        $data = $DB->get_record_sql($filedata, ['id'=> $instanceid]);

        // Generate the student file.
        $gdrive = new \googledrive($context->id, false, false, true);
        $ids = $gdrive->make_group_folder($data);

        return array(
            'group_folder_ids' => json_encode($ids, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Returns the folders ids generate by google drive.
     *
     * @return external_single_structure
     *
     */
    public static function create_group_folder_struct_returns(){
        return new external_single_structure(
                array(
                    'group_folder_ids' => new external_value(PARAM_RAW, 'folder and group id')
                )
      );
    }
}