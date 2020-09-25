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
            array(
                  'grouping_name' => new external_value(PARAM_RAW, 'groupingname'),
                  'grouping_id' => new external_value(PARAM_RAW, 'grouping ID'),
                  'owner_email' =>  new external_value(PARAM_RAW, 'Owner of the file email'),
                  'parentfile_id' => new external_value(PARAM_ALPHANUMEXT, 'ID of the file to copy'),
            )
        );
    }


    public static function create_grouping_file($grouping_name, $grouping_id, $owner_email, $parentfile_id) {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        //Parameters validation
        self::validate_parameters(self::create_grouping_file_parameters(),
            array(
                  'grouping_name' => $grouping_name,
                  'grouping_id' => $grouping_id,
                  'owner_email'=> $owner_email,
                  'parentfile_id' => $parentfile_id)
        );

        $filedata = "SELECT * FROM mdl_googledocs WHERE docid = :parentfile_id ";
        $data = $DB->get_record_sql($filedata, ['parentfile_id'=> $parentfile_id]);

        // Generate the grouping file
        $gdrive = new \googledrive($context->id, false, false, true);
        list($role, $commenter) = $gdrive->format_permission($data->permissions);
        $grouping = new \stdClass();
        $grouping->id = $grouping_id;

        $grouping->email = $owner_email;
        $grouping->type = 'user';
        $grouping->isgrouping = true;
        $fromexisting = $data->use_document == 'new' ? false : true;

        $q = "SELECT gg.groupid, g.name FROM mdl_groupings_groups  as gg "
            . "INNER JOIN mdl_groups as g ON gg.groupid = g.id "
            . "WHERE gg.groupingid = :grouping_id";

        $groups = $DB->get_records_sql($q, ["grouping_id" => $grouping_id]);

        //Create the copies for the groups in the grouping
        
        $groups_details = [];
        foreach($groups as $group){

            $grouping->groupid = $group->groupid;
            $grouping->name =  $group ->name;
            $url = $gdrive->make_file_copy($data, $data->parentfolderid, $grouping, $role, $commenter, $fromexisting);
            $group_members = groups_get_members($group->groupid, "u.id, u.email");
            $docid =  $gdrive->get_file_id_from_url($url);

            $gdrive->permission_for_members_in_groups($group_members, $docid, $role, $commenter, $fromexisting);
            $group_detail = new \stdClass();
            $group_detail->group_id =  $group->groupid;
            $group_detail->url = $url;
            $groups_details[] = $group_detail;
          }

          //Files created and shared. Time to update
            $data->sharing = 1;
            $DB->update_record('googledocs', $data);

        return array(
            'googledocid' => $parentfile_id , //parent file to delete
            'urls' => json_encode($groups_details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)

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
                    'googledocid' => new external_value(PARAM_RAW,'file id to delete'),
                    'urls' => new external_value(PARAM_RAW,'urls created for the groups')
                 )
      );
    }
}