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
 * Trait implementing the external function mod_googledocs_create_groupings_file
 */
trait create_grouping_file {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
    */

    public static  function create_grouping_file_parameters(){
        return new external_function_parameters(
            array( 'owneremail' => new external_value(PARAM_RAW, 'Owner of the file email'),
                  'parentfileid' => new external_value(PARAM_ALPHANUMEXT, 'ID of the file to copy'),
            )
        );
    }


    public static function create_grouping_file($owneremail, $parentfileid) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(self::create_grouping_file_parameters(),
            array('owneremail'=> $owneremail,
                  'parentfileid' => $parentfileid)
        );

        $filedata = "SELECT * FROM mdl_googledocs WHERE docid = :parentfile_id ";
        $data = $DB->get_record_sql($filedata, ['parentfile_id'=> $parentfileid]);

        // Generate the grouping file.
        $gdrive = new \googledrive($context->id, false, false, true);
        list($role, $commenter) = $gdrive->format_permission($data->permissions);
        $gids = get_grouping_ids_from_json(json_decode($data->group_grouping_json));
        $groupingurls = [];
        $teachers = $gdrive->get_enrolled_teachers($data->course);

        // Create the files for each grouping.

        foreach ($gids as $id) {
            $grouping = new \stdClass();
            $grouping->id = $id;
            $grouping->name = groups_get_grouping_name($id);
            $grouping->email = $owneremail;
            $grouping->type = 'user';
            $grouping->isgrouping = true;
            $fromexisting = $data->use_document == 'new' ? false : true;

            $url = $gdrive->make_file_copy($data, $data->parentfolderid, $grouping, $role, $commenter, $fromexisting, $id, $teachers);
            $details = new \stdClass();
            $details->gid = $id;
            $details->url = $url;
            $groupingurls[] = $details;
        }
       //Files created and shared. Time to update
        $data->sharing = 1;
        $DB->update_record('googledocs', $data);

        return array(
            'groupingsurl' => json_encode($groupingurls, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the grouping
     * @return external_single_structure
     *
     */
    public static function create_grouping_file_returns(){
        return new external_single_structure(
                array(
                    'groupingsurl' => new external_value(PARAM_RAW, 'urls created for the groupingss')
                )
      );
    }
}