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
require_once($CFG->dirroot . '/mod/googledocs/locallib.php');

/**
 * Trait implementing the external function mod_googledocs_create_groups_file
 */
trait create_group_file {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
    */

    public static  function create_group_file_parameters(){
        return new external_function_parameters(
            array(
                  'group_name' => new external_value(PARAM_RAW, 'groupname'),
                  'group_id' => new external_value(PARAM_RAW, 'group ID'),
                  'owner_email' =>  new external_value(PARAM_RAW, 'Owner of the file email'),
                  'parentfile_id' => new external_value(PARAM_ALPHANUMEXT, 'ID of the file to copy'),
            )
        );
    }

    /**
     * Create the file for the group
     * @param  string $timetableuser represents a user.
     *         string $timetablerole represents the role of the user.
     *         int $date represents the date in timestamp format.
     *         int $nav represents a nav direction, 0: Backward, 1: Forward.
     * @return a timetable for a user.
     */
    public static function create_group_file($group_name, $group_id, $owner_email, $parentfile_id) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        //Parameters validation
        self::validate_parameters(self::create_group_file_parameters(),
            array(
                  'group_name' => $group_name,
                  'group_id' => $group_id,
                  'owner_email'=> $owner_email,
                  'parentfile_id' => $parentfile_id)
        );

        $filedata = "SELECT * FROM mdl_googledocs WHERE docid = :parentfile_id ";
        $data = $DB->get_record_sql($filedata, ['parentfile_id'=> $parentfile_id]);



        // Generate the group file
        $gdrive = new \googledrive($context->id, false, false, true);
        list($role, $commenter) = $gdrive->format_permission($data->permissions);
        $group = new \stdClass();
        $group->id = $group_id;
        $group->name = $group_name;
        $group->email = $owner_email;
        $group->type = 'user';
        $group->isgroup = true;
        $fromexisting = $data->use_document == 'new' ? false : true;

        $url= $gdrive->make_file_copy($data, $data->parentfolderid, $group, $role, $commenter, $fromexisting);
        $googledocid = $gdrive->get_file_id_from_url($url);

        return array(
            'googledocid' => $googledocid,
            'url' => $url
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the group
     * @return external_single_structure
     *
     */
    public static function create_group_file_returns(){
        return new external_single_structure(
                array(
                    'googledocid' => new external_value(PARAM_RAW,'file id'),
                     'url' => new external_value(PARAM_RAW,'File URL '),
                 )
      );
    }
}