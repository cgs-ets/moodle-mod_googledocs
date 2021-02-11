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
 * Library of interface functions and constants for module googledocs
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the googledocs specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_googledocs
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 *             2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function googledocs_supports($feature) {

    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the googledocs into the database
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $googledocs Submitted data from the form in mod_form.php
 * @param mod_googledocs_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted googledocs record
 */
function googledocs_add_instance(stdClass $googledocs, mod_googledocs_mod_form $mform = null) {
    global $USER;

    try {

        $googledocs->timecreated = time();
        $context = context_course::instance($googledocs->course);
        $gdrive = new googledrive($context->id);
        $types = google_filetypes();

        if (!$gdrive->check_google_login()) {
            $googleauthlink = $gdrive->display_login_button();
            $mform->addElement('html', $googleauthlink);
            throw new Exception('Error - not authenticated with Google!');
        }

        $author = array('emailAddress' => $USER->email, 'displayName' => fullname($USER));
        $coursestudents = get_role_users(5, $context);
        $students = $gdrive->get_enrolled_students($googledocs->course);
        $group_grouping = [];
        $dist = '';

        $intro = (($mform->get_submitted_data())->introeditor);

        if (!empty(($mform->get_submitted_data())->groups) && !everyone(($mform->get_submitted_data())->groups)) {
            list($group_grouping, $dist) = prepare_json(($mform->get_submitted_data())->groups, $googledocs->course);
        }

        list($dist, $owncopy) = distribution_type($mform->get_submitted_data(), $dist);

        if (!empty($group_grouping)) {

            $jsongroup = new stdClass();
            $jsongroup->c = $group_grouping;
            $googledocs->group_grouping_json = json_encode($jsongroup);
            if ($dist == 'dist_share_same_grouping' || $dist == 'grouping_copy' || $dist == 'std_copy_grouping') {
                $students = get_users_in_grouping($coursestudents, json_encode($jsongroup));
            } else {
                $students = get_users_in_group($coursestudents, json_encode($jsongroup), $googledocs->course);
            }

            $course_groups = count(groups_get_all_groups($googledocs->course));
            $selected_groups = count(get_groups_details_from_json($jsongroup));
        }

        if ($students == null) {
            throw new exception('No Students provided. The files were not created');
        }

        // Use existing doc.
        if (($mform->get_submitted_data())->use_document == 'existing') {
            // Save new file in a COURSE Folder.
            $sharedlink = $gdrive->share_existing_file($mform->get_submitted_data(), $owncopy, $students, $dist);
            $folderid = $sharedlink[3];
            $file = new stdClass();
            $file->id = ($sharedlink[0])->id;
            $file->title = ($sharedlink[0])->title;
            $file->createdDate = ($sharedlink[0])->createdDate;

            $googledocs->document_type = $types[get_file_type_from_string($googledocs->google_doc_url)]['mimetype'];
            $googledocs->id = $gdrive->save_instance($googledocs, $file, $sharedlink, $folderid, $owncopy, $dist,
                $intro, true,($mform->get_submitted_data())->google_doc_url);
        } else {
            // Save new file in a new folder.
            list($folderid, $createddate) = $gdrive->create_folder($googledocs->name_doc, $author);

            // When sharing same folder for all give permissions to the folder created here.
            // Don't generate a nested folder with the same name.
           if ($googledocs->document_type == $types['folder']['mimetype'] && $dist == 'dist_share_same' ) {
                $url = url_templates();
                $sharedlink  = sprintf($url[GDRIVEFILETYPE_FOLDER]['linktemplate'], $folderid);
                // Folders are files. Keep the naming convention in the save_instance function.
                $file = new stdClass();
                $file->id = $folderid;
                $file->title = $googledocs->name_doc;
                $file->createdDate = $createddate;
           } else {
               list ($file, $sharedlink, $a) =  $gdrive->create_file($googledocs->name_doc, $googledocs->document_type, $author, $students, $folderid);
           }

            $googledocs->name = $googledocs->name_doc;
            $googledocs->id = $gdrive->save_instance($googledocs, $file, $sharedlink, $folderid, $owncopy, $dist, $intro);
            $gdrive->save_work_task_scheduled($file->id, $students, $googledocs->id);

        }

        googledocs_grade_item_update($googledocs);
        return $googledocs->id;
    } catch (Exception $ex) {
        throw $ex;
    }
}

/**
 * Updates an instance of the googledocs in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $googledocs An object from the form in mod_form.php
 * @param mod_googledocs_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function googledocs_update_instance(stdClass $googledocs, mod_googledocs_mod_form $mform = null) {
    global $DB;

    $context = context_course::instance($googledocs->course);
    $gdrive = new googledrive($context->id, true);
    $currentvalues = $DB->get_record('googledocs', ['id' => $googledocs->instance], '*');

    if ($googledocs->intro != $currentvalues->intro || $googledocs->showdescription != $currentvalues->intro) {
        $currentvalues->intro = $googledocs->intro;
        $updateresult = true;
    }

    if ($currentvalues->permissions != $googledocs->permissions) {
        $detail = new stdClass();
        $detail->permissions = $googledocs->permissions;
        $currentvalues->permissions = $googledocs->permissions;
        $updateresult  = $gdrive->updates($currentvalues, $detail);  // Update the google file permission.
    }

    if (!$updateresult) { // Error on the update.
        $result = false;
    } else {
        $currentvalues->update_status = 'modified';
        // You may have to add extra stuff in here.
        $result = $DB->update_record('googledocs', $currentvalues);
        // googledocs_grade_item_update($googledocs);
    }
    return $result;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every googledocs event in the site is checked, else
 * only googledocs events belonging to the course specified are checked.
 * This is only required if the module is generating calendar events.
 *
 * @param int $courseid Course ID
 * @return bool
 */
function googledocs_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$googledocss = $DB->get_records('googledocs')) {
            return true;
        }
    } else {
        if (!$googledocss = $DB->get_records('googledocs', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($googledocss as $googledocs) {
        // Create a function such as the one below to deal with updating calendar events.
        // googledocs_update_events($googledocs);
    }

    return true;
}

/**
 * Removes an instance of the newmodule from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 * This function is run by the cron task  from course module
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function googledocs_delete_instance($id) {
    global $DB;

    if (!$googledocs = $DB->get_record('googledocs', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('googledocs_submissions', array('googledoc' => $googledocs->id));
    $DB->delete_records('googledocs_files', array('googledocid' => $googledocs->id));
    $DB->delete_records('googledocs_work_task', array('googledocid' => $googledocs->id));
    $DB->delete_records('googledocs_folders', array('googledocid' => $googledocs->id));
    $DB->delete_records('googledocs', array('id' => $googledocs->id));

    googledocs_grade_item_delete($googledocs);
    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $googledocs The googledocs instance record
 * @return stdClass|null
 */
function googledocs_user_outline($course, $user, $mod, $googledocs) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $googledocs the module instance record
 */
function googledocs_user_complete($course, $user, $mod, $googledocs) {

}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in googledocs activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function googledocs_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link googledocs_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function googledocs_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {

}

/**
 * Prints single activity item prepared by {@link googledocs_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function googledocs_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {

}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function googledocs_cron() {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function googledocs_get_extra_capabilities() {
    return array();
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function googledocs_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for googledocs file areas
 *
 * @package mod_googledocs
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function googledocs_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {

    return null;
}

/**
 * Serves the files from the googledocs file areas
 *
 * @package mod_googledocs
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the googledocs's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function googledocs_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding googledocs nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the googledocs module instance
 * @param stdClass $course current course record
 * @param stdClass $module current googledocs instance record
 * @param cm_info $cm course module information
 */
/*
  function googledocs_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
  // TODO Delete this function and its docblock, or implement it.
  }
 */

/**
 * Extends the settings navigation with the googledocs settings
 *
 * This function is called when the context for the page is a googledocs module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $googledocsnode googledocs administration node
 */
function googledocs_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $googledocsnode = null) {
    // TODO Delete this function and its docblock, or implement it.
    // TODO: what Google drive documents are allowed SELECT box
}

/* GRADING API */

/**
 * Create grade item for given googledoc instance by calling grade_update().
 * @category grade
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_NONE
 * @param object $googledoc object with extra cmidnumber
 * @param array|object $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function googledocs_grade_item_update($googledoc, $grades = null) {
    global $CFG;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }

    if (property_exists($googledoc, 'cmidnumber')) { // May not be always present.
        $params = array('itemname' => $googledoc->name, 'idnumber' => $googledoc->cmidnumber);
    } else {
        $params = array('itemname' => $googledoc->name);
    }

    if (!isset($googledoc->courseid)) {
        $googledoc->courseid = $googledoc->course;
    }

    if ($googledoc->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $googledoc->grade;
        $params['grademin'] = 0;
    } else if ($googledoc->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$googledoc->grade;

        // Make sure current grade fetched correctly from $grades
        $currentgrade = null;
        if (!empty($grades)) {
            if (is_array($grades)) {
                $currentgrade = reset($grades);
            } else {
                $currentgrade = $grades;
            }
        }

        // When converting a score to a scale, use scale's grade maximum to calculate it.
        if (!empty($currentgrade) && $currentgrade->rawgrade !== null) {
            $grade = grade_get_grades($googledoc->course, 'mod', 'googledoc', $googledoc->id, $currentgrade->userid);
            $params['grademax'] = reset($grade->items)->grademax;
        }
    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    } else if (!empty($grades)) {
        // Need to calculate raw grade (Note: $grades has many forms).
        if (is_object($grades)) {
            $grades = array($grades->userid => $grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid'] => $grades);
        }
        foreach ($grades as $key => $grade) {
            if (!is_array($grade)) {
                $grades[$key] = $grade = (array) $grade;
            }
            //check raw grade isnt null otherwise we erroneously insert a grade of 0
            if ($grade['rawgrade'] !== null) {
                $grades[$key]['rawgrade'] = ($grade['rawgrade'] * $params['grademax'] / 100);
                // Update mdl_googledocs_table.
            } else {
                // Setting rawgrade to null just in case user is deleting a grade.
                $grades[$key]['rawgrade'] = null;
            }
        }
    }

    return grade_update('mod/googledocs', $googledoc->course, 'mod', 'googledocs', $googledoc->id, 0, $grades, $params);
}

/**
 * Update the grade(s) for the supplied user.
 * @param stdClass  $googledocs
 * @param int $userid
 * @param bool $nullifnone
 */
function googledocs_update_grades($googledocs, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($googledocs->grade == 0) {
        googledocs_grade_item_update($googledocs);
    } else if ($grades = googledocs_get_user_grades($googledocs, $userid)) {

        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }

        googledocs_grade_item_update($googledocs, $grades);
    } else {
        googledocs_grade_item_update($googledocs);
    }
}

function googledocs_get_user_grades($googledocinstance, $userid = 0) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/googledocs/locallib.php');
    return get_user_grades_for_gradebook($userid, $googledocinstance);
}

function googledocs_grade_item_delete($googledoc) {
    global $DB;
    $DB->delete_records('googledocs_grades', array('googledocid' => $googledoc->id));
    $DB->delete_records('googledocsfeedback_comments', array('googledoc' => $googledoc->id));
}
