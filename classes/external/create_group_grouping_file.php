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

/**
 * Trait implementing the external function mod_googledocs_create_group_grouping_file
 * This service creates a file in Googledocs per group and grouping when
 * distribution  is group_grouping_copy.
 *
 */
trait create_group_grouping_file {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
    */
    public static  function create_group_grouping_file_parameters() {
        return new external_function_parameters(
            array('gid' => new external_value(PARAM_RAW, 'Group or Grouping ID'),
                  'gname' => new external_value(PARAM_RAW, 'Name of the group or grouping'),
                  'gtype' => new external_value(PARAM_RAW, 'Group or Grouping'),
                  'instanceid' => new external_value(PARAM_RAW, 'instance ID'),
                  'owneremail' => new external_value(PARAM_RAW, 'Author of the file email'),
                  'parentfileid' => new external_value(PARAM_ALPHANUMEXT, 'ID of the file to copy'),
            )
        );
    }

    /**
     * Create the file for the student
     * @param  string $timetableuser represents a user.
     *         string $timetablerole represents the role of the user.
     *         int $date represents the date in timestamp format.
     *         int $nav represents a nav direction, 0: Backward, 1: Forward.
     * @return a timetable for a user.$by_group, $by_grouping, $group_id, $grouping_id,
     */
    public static function create_group_grouping_file($gid, $gname, $gtype, $instanceid, $owneremail, $parentfileid) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::create_group_grouping_file_parameters(),
            array(
                  'gid' => $gid,
                  'gname' => $gname,
                  'gtype' => $gtype,
                  'instanceid' => $instanceid,
                  'owneremail' => $owneremail,
                  'parentfileid' => $parentfileid,
                )
        );

        $filedata = "SELECT * FROM mdl_googledocs WHERE id = :id ";
        $data = $DB->get_record_sql($filedata, ['id'=> $instanceid]);

        // Generate the group/grouping file.
        $gdrive = new \googledrive($context->id, false, false, true);
        list($role, $commenter) = $gdrive->format_permission($data->permissions);

        $owner = new \stdClass();
        $owner->gtype = $gtype;
        $owner->gid = $gid;
        $owner->id = $data->userid;
        $owner->email = $owneremail;
        $owner->type = 'user';
        $owner->name = $gname; // Adds the group/grouping name to the file.
        $fromexisting = $data->use_document == 'new' ? false : true;

        $url = $gdrive->make_file_copy($data,  $data->parentfolderid, $owner, $role, $commenter, $fromexisting, $gid);
        return array(
            'url' => $url
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the student
     * @return external_single_structure
     *
     */
    public static function create_group_grouping_file_returns(){
        return new external_single_structure(
                array(
                    'url' => new external_value(PARAM_RAW, 'File URL '),
                 )
      );
    }
}