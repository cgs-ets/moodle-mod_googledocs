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
 * Internal library of functions for module googledocs
 *
 * All the googledocs specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_googledocs
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 *             2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/google/lib.php');
require_once($CFG->libdir . '/tablelib.php');

define('GDRIVEFILEPERMISSION_COMMENTER', 'comment'); // Student can Read and Comment.
define('GDRIVEFILEPERMISSION_EDITOR', 'edit'); // Students can Read and Write.
define('GDRIVEFILEPERMISSION_READER', 'view'); // Students can read.
define('GDRIVEFILETYPE_DOCUMENT', 'application/vnd.google-apps.document');
define('GDRIVEFILETYPE_PRESENTATION', 'application/vnd.google-apps.presentation');
define('GDRIVEFILETYPE_SPREADSHEET', 'application/vnd.google-apps.spreadsheet');
define('GDRIVEFILETYPE_FOLDER', 'application/vnd.google-apps.folder');

// Grading states.
define('GOOGLEDOCS_GRADING_STATUS_GRADED', 'graded');
define('GOOGLEDOCS_GRADING_STATUS_NOT_GRADED', 'notgraded');

/**
 * Google Drive file types.
 *
 * @return array google drive file types. *
 * https://developers.google.com/drive/api/v2/ref-roles
 */
function google_filetypes() {
    $types = array(
        'document' => array(
            'name' => get_string('google_doc', 'mod_googledocs'),
            'mimetype' => 'application/vnd.google-apps.document',
            'icon' => 'document.svg',
        ),
        'spreadsheets' => array(
            'name' => get_string('google_sheet', 'mod_googledocs'),
            'mimetype' => 'application/vnd.google-apps.spreadsheet',
            'icon' => 'spreadsheets.svg',
        ),
        'presentation' => array(
            'name' => get_string('google_slides', 'mod_googledocs'),
            'mimetype' => 'application/vnd.google-apps.presentation',
            'icon' => 'presentation.svg',
        ),
        'folder' => array(
          'name' => get_string('google_folder', 'mod_googledocs'),
          'mimetype' =>'application/vnd.google-apps.folder',
          'icon' =>'folder.svg',
          ),
    );

    return $types;
}

function get_file_type_from_string($str) {

    if (strpos($str, 'document')) {
        return 'document';
    }
    if (strpos($str, 'spreadsheets') || strpos($str, 'spreadsheet')) {
        return 'spreadsheets';
    }
    if (strpos($str, 'presentation')) {
        return 'presentation';
    }
    if (strpos($str, 'folder')) {
        return 'folder';
    }

}

/**
 * Returns format of the link, depending on the file type
 * @return string
 */
function url_templates() {
    $sharedlink = array();

    $sharedlink[GDRIVEFILETYPE_DOCUMENT] = array('linktemplate' => 'https://docs.google.com/document/d/%s/edit?usp=sharing',
        'linkbegin' => 'https://docs.google.com/document/d/',
        'linkend' => '/edit?usp=sharing'
    );
    $sharedlink[GDRIVEFILETYPE_PRESENTATION] = array('linktemplate' => 'https://docs.google.com/presentation/d/%s/edit?usp=sharing',
        'linkbegin' => 'https://docs.google.com/presentation/d/',
        'linkend' => '/edit?usp=sharing'
    );
    $sharedlink[GDRIVEFILETYPE_SPREADSHEET] = array(
        'linktemplate' => 'https://docs.google.com/spreadsheets/d/%s/edit?usp=sharing',
        'linlbegin' => 'https://docs.google.com/spreadsheets/d/',
        'linkend' => '/edit?usp=sharing'
    );
    $sharedlink[GDRIVEFILETYPE_FOLDER] = array(
        'linktemplate' => 'https://drive.google.com/drive/folders/%s/?usp=sharing',
        'linkdisplay' => 'https://drive.google.com/embeddedfolderview?id=%s#grid');

    return $sharedlink;
}

/**
 * This methods does weak url validation, we are looking for major problems only,
 * no strict RFE validation.
 * TODO: Make this stricter
 *
 * @param $url
 * @return bool true is seems valid, false if definitely not valid URL
 */
function googledocs_appears_valid_url($url) {
    if (preg_match('/^(\/|https?:|ftp:)/i', $url)) {
        // NOTE: this is not exact validation, we look for severely malformed URLs only.
        return (bool) preg_match('/^[a-z]+:\/\/([^:@\s]+:[^@\s]+@)?[a-z0-9_\.\-]+(:[0-9]+)?(\/[^#]*)?(#.*)?$/i', $url);
    } else {
        return (bool) preg_match('/^[a-z]+:\/\/...*$/i', $url);
    }
}

/**
 * Helper function to get the file id from a given URL.
 * @param type $url
 * @param type $doctype
 * @return type
 */
function get_file_id_from_url($url) {

    if (preg_match('#\/(d|folders)\/([a-zA-Z0-9-_]+)#', $url, $match) == 1) {
        $fileid = $match[2];
    }
    return $fileid;
}

function oauth_ready() {

}

// Helper function to call the appropiate function depending on the distribution s
function make_file_copy_helper($data, $student, $role, $commenter, $fromexisting, $teachers, $gdrive) {
    global $DB;
    switch ($data->distribution) {
        case 'std_copy':
            $url [] = $gdrive->make_file_copy($data, $data->parentfolderid, $student, $role,
                $commenter, $fromexisting, 0, $teachers);
            break;
        case 'dist_share_same':
            $url [] = $gdrive->share_single_copy($student, $data, $role, $commenter, true, false);
            break;
        case 'group_copy' :
            $url [] = $gdrive->make_file_copy_for_group($data, $student, $role, $commenter, $fromexisting, $teachers);
            break;
        case 'std_copy_group':
            $url = $gdrive->std_copy_group_grouping_copy($data, $student, $role, $commenter, $fromexisting, $gdrive, $teachers);
            break;
        case 'std_copy_grouping':
            $url = $gdrive->std_copy_group_grouping_copy($data, $student, $role, $commenter, $fromexisting, $gdrive, $teachers);
            break;
        case 'std_copy_group_grouping':
            $url = $gdrive->std_copy_group_grouping_copy($data, $student, $role, $commenter, $fromexisting, $gdrive, $teachers);
            break;
        case 'group_grouping_copy':
            $url [] = $gdrive->make_file_copy_for_group($data, $student, $role, $commenter, $fromexisting, $teachers);
            break;

        default:
            break;
    }
    $data->sharing = 1; // It got here means that at least is being shared with one.
    $DB->update_record('googledocs', $data);
    return $url;
}

// ----------------------------------- format folder url---------------------- //
function get_formated_folder_url($url) {
    $urltemplate = url_templates();
    $fileid = get_file_id_from_url($url);
    $folderurl = sprintf($urltemplate[GDRIVEFILETYPE_FOLDER]['linkdisplay'], $fileid);

    return $folderurl;
}

// ----------------------------------- Group-Grouping helper functions---------------------- //

/**
 *
 * @param type $courseusers
 * @param type $conditionsjson
 * @return array of students with the format needed to create docs.
 */
function get_users_in_group($courseusers, $conditionsjson, $courseid) {

    $groupmembers = get_group_members_ids($conditionsjson, $courseid);
    $users = null;

    foreach ($courseusers as $user) {

        if (in_array($user->id, $groupmembers)) {
            $users[] = $user;
        }
    }

    return $users;
}

/**
 * Get users that belongs to the groupings selected in the form.
 * users can be teachers or students
 * @param type $courseusers
 * @param type $conditionsjson
 * @return string
 */
function get_users_in_grouping($courseusers, $conditionsjson) {

    $groupingmembers = get_grouping_members_ids($conditionsjson);
    foreach ($courseusers as $user) {
        if (in_array($user->id, $groupingmembers)) {
            $users[] = $user;
        }
    }
    return $users;
}

/**
 * Return the number of groups for a particular course
 * @global type $DB
 * @param type $courseid
 * @return type
 */
function get_course_group_number($courseid) {
    global $DB;
    $sql = " SELECT count(*)
            FROM  mdl_groups AS gr
            INNER JOIN mdl_googledocs as gd on gr.courseid = gd.course
            WHERE gd.course = :courseid;";

    return $DB->count_records_sql($sql, array('courseid' => $courseid));
}

/**
 * Return the ids of the students from all the groups  the file has to be created for
 * This function is used when the group is set in the general area in the form.
 * @param json $conditionsjson
 * @return array
 */
function get_group_members_ids($conditionsjson) {

    $j = json_decode($conditionsjson);
    $groupmembers = [];
    $groups = get_groups_details_from_json($j);

    foreach ($groups as $group) {
        $groupmembers = array_merge($groupmembers, groups_get_members($group->id, $fields = 'u.id'));
    }

    return array_column($groupmembers, 'id');
}

function get_grouping_members_ids($conditionsjson) {

    $j = json_decode($conditionsjson);
    $groupingids = get_grouping_ids_from_json($j);
    $groupingmembers = [];

    foreach ($groupingids as $id) {
        $groupingmembers = array_merge($groupingmembers, groups_get_grouping_members($id, $fields = 'u.id'));
    }

    return array_column($groupingmembers, 'id');
}

/**
 * Checks if the group option selected is everyone
 * @param type $data
 * @return boolean
 */
function everyone($data) {
    list($id, $type) = explode('_', current($data));
    return $type == 'everyone';
}

/**
 * Generate an array with stdClass object that has the format
 * needed to generate the JSON
 * @param type $type
 * @param type $data
 * @return array
 */
function prepare_json($data, $courseid = 0) {
    $conditions = array();

    $combination = !empty((preg_grep("/_grouping/", $data)) && !(empty(preg_grep("/_group/", $data))));
    $dist = '';

    foreach ($data as $d) {
        list($id, $type) = explode('_', $d);
        $condition = new stdClass();
        $dist .= $type . ",";
        $condition->id = $id;
        $condition->type = $type;
        array_push($conditions, $condition);
    }

    $dist = rtrim($dist, ",");
    $combination = (array_unique(explode(',', $dist)));

    if (count($combination) > 1) {
        $dist = 'group_grouping';
    } else if ($combination[0] == 'grouping') {
        $dist = 'grouping';
    } else {
        $dist = 'group';
    }

    return array($conditions, $dist);
}

/*
 * Some templates needs the grouping ids formated with a "-"
 * to be able to create files.
 */

function get_grouping_ids_formated($userid, $courseid) {
    $studentgroupingids = array_keys(groups_get_user_groups($courseid, $userid));
    unset($studentgroupingids[array_key_last($studentgroupingids)]);
    return implode('-', $studentgroupingids);
}

/**
 * Distribution types
 * Possible combinations:
 *
 *  Each student in the course gets a copy.
 *  Each student in a group in the course gets a copy.
 *  Each student in a grouping group in the course gets a copy.
 *  All students in the course share same copy.
 *  All students in a group in the course share same copy.
 *  All students in a grouping group in the course share same copy.
 *  Each group gets a copy.
 *  Each grouping gets a copy.
 *  Each group and grouping gets a copy.
 * @param type $data_submmited
 * @param type $dist
 * @return array(string, boolean)
 */
function distribution_type($data_submmited, $dist) {

    if (!empty($data_submmited->groups) && $dist != ''
        && $data_submmited->distribution == 'std_copy') {
        return array('std_copy_' . $dist, true);
    }

    if (!empty($data_submmited->groups) && $dist != ''
        && $data_submmited->distribution == 'dist_share_same') {
        return array('dist_share_same_' . $dist, false);
    }

    if ($dist == '' && $data_submmited->distribution == 'std_copy') {
        return array($data_submmited->distribution, true);
    }

    if ($dist == '' && $data_submmited->distribution == 'dist_share_same') {
        return array($data_submmited->distribution, false);
    }

    if ($dist == 'group' && $data_submmited->distribution == "group_copy") {
        return array($data_submmited->distribution, false);
    }

    if ($dist == 'grouping' && $data_submmited->distribution == "group_copy") {
        return array($dist . '_copy', false);
    }

    if ($dist == 'group_grouping' && $data_submmited->distribution == 'group_copy') {
        return array($dist . '_copy', false);
    }

    return array($dist, true);
}

/**
 * Create a string with the group ids a student belongs to
 * @param type $userid
 * @param type $courseid
 * @return type
 */
function get_students_group_ids($userid, $courseid) {

    $ids = groups_get_user_groups($courseid, $userid)[0];
    $group_ids = '';

    foreach ($ids as $i) {
        $group_ids .= $i . '-';
    }

    return rtrim($group_ids, "-");
}

/**
 * Get the google drive folder id for a student.
 * This covers the case where a student belongs to more than a group and it will receive file
 * copies for every group it belongs to.
 *
 * @global type $DB
 * @param type $userid
 * @param type $courseid
 * @return type
 */
function get_folder_id_reference($userid, $courseid, $instanceid, $grouping = false) {
    global $DB;

    if ($grouping) {
        $ids = explode("-", get_grouping_ids_formated($userid, $courseid));
    } else {
        $ids = explode("-", get_students_group_ids($userid, $courseid));
    }

    list($insql, $inparams) = $DB->get_in_or_equal($ids);

    $sql = "SELECT folder_id, group_id FROM mdl_googledocs_folders
            WHERE group_id  $insql AND googledocid = {$instanceid}";

    $r = $DB->get_records_sql($sql, $inparams);

    return $r;
}

/**
 * Filter the group grouping data to just groups without duplicates
 * @global type $DB
 * @param type $data
 * @return type
 */
function get_groups_details_from_json($data) {

    $groups = [];

    foreach ($data->c as $c) {
        if ($c->type == 'group') {
            $g = new stdClass();
            $g->id = $c->id;
            $g->name = groups_get_group_name($c->id);
            $groups[] = $g;
        }
    }
    // Remove empty groups.
    foreach ($groups as $group => $g) {
        if (!groups_get_members($g->id, 'u.id')) {
            unset($groups[$group]);
        }
    }
    return $groups;
}

function get_groupings_details_from_json($data) {
    $groupings = [];
    foreach ($data->c as $c) {
        if ($c->type == 'grouping') {
            $g = new stdClass();
            $g->id = $c->id;
            $g->name = groups_get_grouping_name($c->id);
            $groupings[] = $g;
        }
    }
    return $groupings;
}

function get_grouping_ids_from_json($data) {
    $groupingids = get_id_detail_from_json($data, "grouping");
    foreach ($groupingids as $id) {
        if (!groups_get_grouping_members($id, 'u.id')) {
            unset($groupingids[$id]);
        }
    }
    return $groupingids;
}

/**
 * Get the group or grouping ids from the group_grouping_json attr.
 * @param string $groupgroupingjson
 * @param string $type
 * @return \stdClass
 */
function get_id_detail_from_json($groupgroupingjson, $type) {
    $ids = [];

    foreach ($groupgroupingjson->c as $c) {
        if ($c->type == $type) {
            $ids [] = $c->id;
        }
    }
    return $ids;
}

/**
 * Helper function to display groups and/or groupings
 * in form when updating.
 */
function get_groups_formatted_for_form($data) {
    $dataformatted = [];
    foreach ($data->c as $c) {
        $dataformatted [] = $c->id . '_' . $c->type;
    }
    return $dataformatted;
}

// ----------------------------------- Submission Gradings helper functions----------------------//

function count_students($googledocid) {
    global $DB;
    $sql = "SELECT count(*) FROM mdl_googledocs_files WHERE googledocid = {$googledocid}";
    return $DB->count_records_sql($sql);
}

function count_submitted_files($googledocid) {
    global $DB;
    $submissions = "SELECT count(*) FROM mdl_googledocs_submissions WHERE googledoc = {$googledocid}";
    return $DB->count_records_sql($submissions);
}

function get_grade_comments($googledocid, $userid) {

    global $DB;
    $sql = "SELECT * FROM mdl_googledocs_submissions WHERE userid = :userid
                AND googledoc = :instanceid";
    $params = ['userid' => $userid, 'instanceid' => $googledocid];

    $submitted = $DB->get_record_sql($sql, $params);

    $sql = "SELECT comments.commenttext as comment, grades.grade as gradevalue FROM mdl_googledocs_grades as grades
                INNER JOIN mdl_googledocsfeedback_comments as comments ON grades.id = comments.grade
                WHERE grades.userid = :userid and grades.googledocid = :instanceid;";

    $grading = $DB->get_record_sql($sql, $params);

    if ($grading) {
        return array($grading->gradevalue, $grading->comment);
    } else {
        return array('', '');
    }
}

function is_gradebook_feedback_enabled() {
    // Get default grade book feedback plugin.
    $adminconfig = get_config('googledocs');
    $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;
    $gradebookplugin = str_replace('assignfeedback_', '', $gradebookplugin);

    // Check if default gradebook feedback is visible and enabled.
    $gradebookfeedbackplugin = get_feedback_plugin_by_type($gradebookplugin);

    if (empty($gradebookfeedbackplugin)) {
        return false;
    }

    if ($gradebookfeedbackplugin->is_visible() && $gradebookfeedbackplugin->is_enabled()) {
        return true;
    }

    // Gradebook feedback plugin is either not visible/enabled.
    return false;
}

function get_feedback_plugin_by_type($type) {
    return get_plugin_by_type('assignfeedback', $type);
}

function get_plugin_by_type($subtype, $type) {
    $shortsubtype = substr($subtype, strlen('assign'));
    $name = $shortsubtype . 'plugins';
    if ($name != 'feedbackplugins' && $name != 'submissionplugins') {
        return null;
    }
    $pluginlist = $name;
    foreach ($pluginlist as $plugin) {
        if ($plugin->get_type() == $type) {
            return $plugin;
        }
    }
    return null;
}

function get_user_grades_for_gradebook($userid = 0, $instance) {
    global $DB;
    $grades = array();
    $adminconfig = get_config('googledocs');

    $gradebookplugin = is_gradebook_feedback_enabled();

    if ($userid) {
        $where = ' WHERE u.id = :userid ';
    } else {
        $where = ' WHERE u.id != :userid ';
    }
    $params = ['googledocid1' => $instance->id,
        'googledocid2' => $instance->id,
        'googledocid3' => $instance->id,
        'userid' => $userid];

    $graderesults = $DB->get_recordset_sql('SELECT u.id as userid, s.timemodified as datesubmitted,
                                            g.grade as rawgrade, g.timemodified as dategraded, g.grader as usermodified,                                             fc.commenttext as feedback, fc.commentformat as feedbackformat
                                            FROM mdl_user as u
                                            LEFT JOIN mdl_googledocs_submissions as s
                                            ON u.id = s.userid and s.googledoc = :googledocid1
                                            JOIN mdl_googledocs_grades as g
                                            ON u.id = g.userid and g.googledocid = :googledocid2
                                            JOIN mdl_googledocsfeedback_comments as fc
                                            ON fc.googledoc = :googledocid3 AND fc.grade = g.id' .
        $where, $params);

    foreach ($graderesults as $result) {
        $gradingstatus = get_grading_status($result->userid, $instance->id);
        if ($gradingstatus == GOOGLEDOCS_GRADING_STATUS_GRADED) {
            $gradebookgrade = clone $result;
            // Now get the feedback.
            $gradebookgrade->feedback = $result->feedback;
            $gradebookgrade->feedbackformat = $result->feedbackformat;
            $grades[$gradebookgrade->userid] = $gradebookgrade;
        }
    }
    $graderesults->close();
    return $grades;
}

function get_grading_status($userid, $googledoc) {
    global $DB;
    $sql = "SELECT * FROM mdl_googledocs_grades WHERE userid = {$userid}
            AND googledocid = {$googledoc};";
    $grades = $DB->get_record_sql($sql);

    if ($grades) {
        return GOOGLEDOCS_GRADING_STATUS_GRADED;
    } else {
        return GOOGLEDOCS_GRADING_STATUS_NOT_GRADED;
    }
}

/**
 * Implements a renderable grading options form
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class googledocs_form implements renderable {

    /** @var moodleform $form is the edit submission form */
    public $form = null;

    /** @var string $classname is the name of the class to assign to the container */
    public $classname = '';

    /** @var string $jsinitfunction is an optional js function to add to the page requires */
    public $jsinitfunction = '';

    /**
     * Constructor
     * @param string $classname This is the class name for the container div
     * @param moodleform $form This is the moodleform
     * @param string $jsinitfunction This is an optional js function to add to the page requires
     */
    public function __construct($classname, moodleform $form, $jsinitfunction = '') {
        $this->classname = $classname;
        $this->form = $form;
        $this->jsinitfunction = $jsinitfunction;
    }

}

/**
 * Google Docs Plugin
 *
 * @since Moodle 3.1
 * @package    mod_googledrive
 * @copyright  2016 Nadav Kavalerchik <nadavkav@gmail.com>
 * @copyright  2009 Dan Poltawski <talktodan@gmail.com> (original work)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class googledrive {

    /**
     * Google OAuth issuer.
     */
    private $issuer = null;

    /**
     * Google Client.
     * @var Google_Client
     */
    private $client = null;

    /**
     * Google Drive Service.
     * @var Google_Drive_Service
     */
    private $service = null;

    /**
     * Session key to store the accesstoken.
     * @var string
     */
    const SESSIONKEY = 'googledrive_rwaccesstoken';

    /**
     * URI to the callback file for OAuth.
     * @var string
     */
    const CALLBACKURL = '/admin/oauth2callback.php';

    // Calling mod_url cmid.
    private $cmid = null;
    // Author (probably the teacher) array(type, role, emailAddress, displayName).
    private $author = array();
    // List (array) of students (array).
    private $students = array();
    private $api_key;
    private $referrer;
    private $googledocinstance;

    /**
     * Constructor.
     *
     * @param int $cmid mod_googledocs instance id.
     * @return void
     */
    public function __construct($cmid, $update = false, $students = false, $fromws = false, $loginstudent = false, $googledocinstance = null) {
        global $CFG;

        $this->cmid = $cmid;
        $this->googledocinstance = $googledocinstance;

        // Get the OAuth issuer.
        if (!isset($CFG->googledocs_oauth)) {
            debugging('Google docs OAuth issuer not set globally.');
            return;
        }
        $this->issuer = \core\oauth2\api::get_issuer($CFG->googledocs_oauth);

        if (!$this->issuer->is_configured() || !$this->issuer->get('enabled')) {
            debugging('Google docs OAuth issuer not configured and enabled.');
            return;
        }

        $this->api_key = (get_config('mod_googledocs'))->googledocs_api_key;

        // Get the Google client.
        $this->client = get_google_client();
        $this->client->setScopes(array(
            \Google_Service_Drive::DRIVE,
            \Google_Service_Drive::DRIVE_APPDATA,
            \Google_Service_Drive::DRIVE_METADATA,
            \Google_Service_Drive::DRIVE_FILE));

        $this->client->setClientId($this->issuer->get('clientid'));
        $this->client->setClientSecret($this->issuer->get('clientsecret'));
        $this->client->setAccessType('offline');
        $this->client->setApprovalPrompt('force');

        $returnurl = new moodle_url(self::CALLBACKURL);
        $this->client->setRedirectUri($returnurl->out(false));

        if ($update || $fromws && !$loginstudent) {
            $this->refresh_token();
        }

        if ($students != null) {
            $this->set_students($students);
        }

        $this->service = new Google_Service_Drive($this->client);
        $this->referrer = (get_config('mod_googledocs'))->referrer;
    }

    /**
     * Returns the access token if any.
     *
     * @return string|null access token.
     */
    protected function get_access_token() {
        global $SESSION;
        if (isset($SESSION->{self::SESSIONKEY})) {
            return $SESSION->{self::SESSIONKEY};
        }
        return null;
    }

    /**
     * Store the access token in the session.
     *
     * @param string $token token to store.
     * @return void
     */
    protected function store_access_token($token) {
        global $SESSION;
        $SESSION->{self::SESSIONKEY} = $token;
    }

    /**
     * Helper function to refresh access token.
     * The access token set in the session doesn't update
     * the expiration time. By refreshing the token, the error 401
     * is avoided.
     * @return string
     */
    public function refresh_token() {
        $accesstoken = json_decode($_SESSION['SESSION']->googledrive_rwaccesstoken);
        //To avoid error in authentication, refresh token.
        $this->client->refreshToken($accesstoken->refresh_token);
        $token = (json_decode($this->client->getAccessToken()))->access_token;

        return $token;
    }

    /**
     * Callback method during authentication.
     *
     * @return void
     */
    public function callback() {

        if ($code = required_param('oauth2code', PARAM_RAW)) {
            $this->client->authenticate($code);
            $this->store_access_token($this->client->getAccessToken());
        }
    }

    public function get_client() {
        return $this->client;
    }

    /**
     * Checks whether the user is authenticate or not.
     *
     * @return bool true when logged in.
     */
    public function check_google_login() {

        if ($token = $this->get_access_token()) {
            $this->client->setAccessToken($token);
            return true;
        }
        return false;
    }

    /**
     * Return HTML link to Google authentication service.
     *
     * @return string HTML link to Google authentication service.
     */
    public function display_login_button($fromUI = false) {
        // Create a URL that leads back to the callback() above function on successful authentication.
        $returnurl = new moodle_url('/mod/googledocs/oauth2_callback.php');
        $returnurl->param('callback', 'yes');
        $returnurl->param('cmid', $this->cmid);
        $returnurl->param('sesskey', sesskey());

        // Get the client auth URL and embed the return URL.
        $url = new moodle_url($this->client->createAuthUrl());
        $url->param('state', $returnurl->out_as_local_url(false));

        // Create the button HTML.
        $title = $fromUI ? get_string('logintosubmit', 'googledocs') : get_string('login', 'repository');

        $link = '<button id ="googleloginbtn" class="btn-primary btn">' . $title . '</button>';
        $jslink = 'window.open(\'' . $url . '\', \'' . $title . '\', \'width=600,height=800\'); return false;';

        $output = '<a href="#" onclick="' . $jslink . '">' . $link . '</a>';

        return $output;
    }

    public function format_permission($permissiontype) {
        $commenter = false;
        if ($permissiontype == GDRIVEFILEPERMISSION_COMMENTER) {
            $studentpermissions = 'reader';
            $commenter = true;
        } else if ($permissiontype == GDRIVEFILEPERMISSION_READER) {
            $studentpermissions = 'reader';
        } else {
            $studentpermissions = 'writer';
        }

        return array($studentpermissions, $commenter);
    }

    /**
     * Set author details.
     *
     * @param array $author Author details.
     * @return void
     */
    public function set_author($author = array()) {
        $this->author = $author;
    }

    /**
     * Set student's details.
     *
     * @param array $students student's details.
     * @return void
     */
    public function set_students($students = array()) {
        foreach ($students as $student) {
            $this->students[] = $student;
        }
    }

    // ------------------------------------ CRUD G Suite Documents --------------------------//

    /**
     * Creates a copy of an existing file.
     * If distribution is "Each gets their own copy" create the copies and distribute them.
     * If distribution is "All share single copy" Create a copy of the master file and
     * distribute it.
     * @param stdClass $mform
     * @param boolean $owncopy
     * @param array $students
     * @return array
     */
    public function share_existing_file(stdClass $mform, $owncopy, $students, $dist = '') {

        try {

            $document_type = $mform->document_type;
            $url = $mform->google_doc_url;
            $fileid = get_file_id_from_url($url);

            $file = $this->service->files->get($fileid);
            $permissiontype = $mform->permissions;
            $links = array();
            $urlTemplate = url_templates();

            // Set the parent folder.
            list($parentdirid, $createddate) = $this->create_folder($file->title);
            $parent = new Google_Service_Drive_ParentReference();
            $parent->setId($parentdirid);

            // The primary role can be either reader or writer.
            // Commenter is an additional role.
            $commenter = false;

            if ($permissiontype == GDRIVEFILEPERMISSION_COMMENTER) {
                $studentpermissions = 'reader';
                $commenter = true;
            } else if ($permissiontype == GDRIVEFILEPERMISSION_READER) {
                $studentpermissions = 'reader';
            } else {
                $studentpermissions = 'writer';
            }

            if ($owncopy) {
                $sharedlink = sprintf($urlTemplate[$document_type]['linktemplate'], $file->id);
                $sharedfile = array($file, $sharedlink, $links, $parentdirid);
            } else {
                list($filecopy,$alternateLink, $studentlinks) = $this->make_copy($file, $parent, $students, $studentpermissions, $commenter, $dist);
                $sharedfile = array($filecopy, $alternateLink, $studentlinks, $parentdirid, $url);
            }

            return $sharedfile;
        } catch (Exception $ex) {
            throw $ex->getMessage();
        }
    }

    /**
     * Create a new Google drive folder
     * Directory structure: SiteFolder/CourseNameFolder/newfolder
     *
     * @param string $dirname
     * @param array $author
     */
    public function create_folder($dirname, $author = array()) {
        global $COURSE, $SITE;

        if (!empty($author)) {
            $this->author = $author;
        }

        $sitefolderid = $this->get_file_id($SITE->fullname);
        $rootparent = new Google_Service_Drive_ParentReference();

        if ($sitefolderid == null) {
            $sitefolder = new \Google_Service_Drive_DriveFile(array(
                'title' => $SITE->fullname,
                'mimeType' => GDRIVEFILETYPE_FOLDER,
                'uploadType' => 'multipart'));
            $sitefolderid = $this->service->files->insert($sitefolder, array('fields' => 'id'));
            $rootparent->setId($sitefolderid->id);
        } else {
            $rootparent->setId($sitefolderid);
        }

        $coursefolderid = $this->get_file_id($COURSE->fullname);

        $courseparent = new Google_Service_Drive_ParentReference();
        // Course folder doesnt exist. Create it inside Site Folder.
        if ($coursefolderid == null) {
            $coursefolder = new \Google_Service_Drive_DriveFile(array(
                'title' => $COURSE->fullname,
                'mimeType' => GDRIVEFILETYPE_FOLDER,
                'parents' => array($rootparent),
                'uploadType' => 'multipart'));
            $coursedirid = $this->service->files->insert($coursefolder, array('fields' => 'id'));
            $courseparent->setId($coursedirid->id);
        } else {
            $courseparent->setId($coursefolderid);
        }

        // Create the folder with the given name.
        $filemetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $dirname,
            'mimeType' => GDRIVEFILETYPE_FOLDER,
            'parents' => array($courseparent),
            'uploadType' => 'multipart'));

        $customdir = $this->service->files->insert($filemetadata, array('fields' => 'id, createdDate'));

        return array($customdir->id, $customdir->createdDate);
    }

    /**
     * Create a folder in a given parent
     * @param string $dirname
     * @param array $parentid
     * @return string
     */
    public function create_child_folder($dirname, $parents) {

        // Create the folder with the given name.
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $dirname,
            'mimeType' => GDRIVEFILETYPE_FOLDER,
            'parents' => $parents,
            'uploadType' => 'multipart'));

        $customdir = $this->service->files->insert($fileMetadata, array('fields' => 'id'));

        return $customdir->id;
    }

    /**
     * Helper function to create folder for students
     * as folders can't be copied (API restriction)
     * Generate a new folder for each student from 'scratch'
     * @param type $data
     */
    public function create_folder_for_student($data, $student, $role, $commenter, $teachers = null) {
        global $DB;

        $parentRef = new Google_Service_Drive_ParentReference();
        $parentRef->setId($data->parentfolderid);
        $foldername = "$data->name _ $student->name";

        $permission = new Google_Service_Drive_Permission();
        $permission->setValue($student->email);
        $permission->setRole($role);
        $permission->setType($student->type);

        if ($commenter) {
            $permission->setAdditionalRoles(array('commenter'));
        }

        // Create the folder with the given name.
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $foldername,
            'mimeType' => GDRIVEFILETYPE_FOLDER,
            'parents' => array($parentRef),
            'uploadType' => 'multipart',
        ));

        $customdir = $this->service->files->insert($fileMetadata, array('fields' => 'id, createdDate, shared, title, alternateLink'));
        $this->insert_permission($this->service, $customdir->id, $student->email, $student->type, $role);

        foreach ($teachers as $teacher) {
            $this->insert_permission($this->service, $customdir->id, $teacher->email, 'user', 'writer', false, true);
        }

        // Save in DB.
        $record = new \stdClass();
        $record->userid = $student->id;
        $record->googledocid = $data->id;
        $record->name = $foldername;
        $record->url = $customdir->alternateLink;
        $record->permission = $data->permissions;

        $DB->insert_record('googledocs_files', $record);

        return $customdir->alternateLink;
    }

    /**
     * Helper function to create files of type folder for groups/groupings.
     * @param Google_Service_Drive_ParentReference $parentfolderidref is the nested folder id when distribution is for students
     * who belong to X group. The folder structure is:
     *  COURSEFOLDER
     *      |
     *      |__NameOfTheMainFolder
     *              |
     *              |__NameOfTheGroup/grouping
     *                        |
     *                        |__FolderStudentName
     */
    public function create_folder_for_group_grouping($data, $gdata, $parentfolderidref = null) {
        global $DB;

        $parentRef = new Google_Service_Drive_ParentReference();
        $foldername = "$data->name _ $gdata->name";


        if ($parentfolderidref != null
            && ($data->distribution == 'std_copy_grouping'
                || $data->distribution == 'std_copy_group_grouping')
                || $data->distribution == 'std_copy_group') {
            $parentRef->setId($parentfolderidref);
        } else {
            $parentRef->setId($data->parentfolderid);
        }
        $parentreferences = array($parentRef);

        // Create the folder with the given name.
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $foldername,
            'mimeType' => GDRIVEFILETYPE_FOLDER,
            'parents' => $parentreferences,
            'uploadType' => 'multipart'));

        $customdir = $this->service->files->insert($fileMetadata, array('fields' => 'id, createdDate, shared, title, alternateLink'));

        return $customdir;
    }

    /**
     * Create a new Google drive file and share it with proper permissions.
     *
     * @param string $docname Google document name.
     * @param int $gfiletype google drive file type. (default: doc. can be doc/presentation/spreadsheet...)
     * @param int $permissiontype permission type. (default: writer)
     * @param array $author Author details.
     * @param array $students student's details.
     * @return array with, if successful or null.
     */
    public function create_file($docname, $gfiletype = GDRIVEFILETYPE_DOCUMENT, $author = array(),
        $students = array(), $parentid = null) {

        if (!empty($author)) {
            $this->author = $author;
        }

        if (!empty($students)) {
            $this->students = $students;
        }

        if ($parentid != null) {
            $parent = new Google_Service_Drive_ParentReference();
            $parent->setId($parentid);
        }

        try {
            // Create a Google Doc file.
            $fileMetadata = new \Google_Service_Drive_DriveFile(array(
                'title' => $docname,
                'mimeType' => $gfiletype,
                'content' => '',
                'parents' => array($parent),
                'uploadType' => 'multipart'));

            // In the array, add the attributes you want in the response.
            $file = $this->service->files->insert($fileMetadata, array('fields' => 'id, createdDate, shared, title, alternateLink'));

            if (!empty($this->author)) {
                $this->author['type'] = 'user';
                $this->author['role'] = 'owner';
                $this->insert_permission($this->service, $file->id, $this->author['emailAddress'],
                    $this->author['type'], $this->author['role']);
            }
            $url = url_templates();
            $sharedlink = sprintf($url[$gfiletype]['linktemplate'], $file->id);
            //$sharedfile = array($file, $sharedlink, array());

            return array($file, $sharedlink, array());
        } catch (Exception $ex) {
          throw  new exception ('There was an error when creating the file');
        }

    }

    /*
     * Create a copy of the master file when distribution is one for all.
     * Make a copy in the the course folder with the name of the file
     * provide access (permission) to the students.
     * alternateLink = A link for opening the file in a relevant Google editor or viewer
     */

    private function make_copy($file, $parent, $students, $studentpermissions, $commenter = false, $dist = '') {

        // Make a copy of the original file in folder inside the course folder.
        $copiedfile = new \Google_Service_Drive_DriveFile();
        $copiedfile->setTitle($file->title);
        $copiedfile->setParents(array($parent));
        $copy = $this->service->files->copy($file->id, $copiedfile);

        $studentlinks = array();

        if ($dist != "dist_share_same_group_copy"
            && $dist != 'dist_share_same_group'
            && $dist != 'grouping_copy'
            && $dist != 'group_grouping_copy'
            && $dist != 'group_copy') {
            // Insert the permission to the shared file.
            foreach ($students as $student) {
                $this->insert_permission($this->service, $copy->id,
                    $student['emailAddress'], 'user', $studentpermissions, $commenter);
                $studentlinks[$student['id']] = array($copy->alternateLink, 'filename' => $file->title);
            }
        }

        return array($copy, $copy->alternateLink, $studentlinks);

    }

    /**
     * Create  copies of the file with a given $fileid.
     *
     * Assign permissions to the file. This provide access to the students.
     * @param string $fileid
     * @param array $parent
     * @param string $docname
     * @param string $students
     * @param string $studentpermissions
     * @param boolean $commenter
     */
    private function make_copies($fileid, $parent, $docname, $students, $studentpermissions, $commenter = false, $fromexisting = false) {
        $links = array();
        $url = url_templates();

        if (!empty($students)) {
            foreach ($students as $student) {
                $copiedfile = new \Google_Service_Drive_DriveFile();
                $copiedfile->setTitle($docname . '_' . $student['displayName']);
                if ($fromexisting) {
                    $copiedfile->setParents($parent);
                }
                $copyid = $this->service->files->copy($fileid, $copiedfile);
                $links[$student['id']] = array(sprintf($url[$copyid->mimeType]['linktemplate'], $copyid->id),
                    'filename' => $docname . '_' . $student['displayName']);

                $this->insert_permission($this->service, $copyid->id, $student['emailAddress'], 'user', $studentpermissions, $commenter);
            }
        }

        return $links;
    }

    /**
     * Called by the WS.
     */
    public function share_single_copy($user, $data, $role, $commenter, $makerecord = false, $teacher = false) {
        global $DB;

        $this->insert_permission($this->service, $data->docid, $user->email, 'user', $role, $commenter, $teacher);

        $d = new stdClass();
        $d->userid = $user->id;
        $d->googledocid = $data->id;
        $d->url = $data->google_doc_url;
        $d->name = $data->name;
        $d->groupingid = $data->groupingid;

        if ($makerecord) {
            $DB->insert_record('googledocs_files', $d);
        }
        $this->update_creation_and_sharing_status($data, $d);

        return $data->google_doc_url;
    }

    /* Create a copy for the student called by the ws entity  can be student, group, groping.
       Gid can be either group id or grouping.
     */
    public function make_file_copy($data, $parentdirid, $entity, $permission, $commenter = false,
        $fromexisting = false, $gid = 0, $teachers = null) {
        global $DB;
        $url = url_templates();

        $docname = $fromexisting ? ($this->getFile($data->docid))->getTitle() : $data->name;

        $copyname = $docname . '_' . $entity->name;
        $copiedfile = new \Google_Service_Drive_DriveFile();
        $copiedfile->setTitle($copyname);

        if ($fromexisting || $data->distribution == 'std_copy_group'
            || $data->distribution == 'dist_share_same_group'
            || $data->distribution == 'dist_share_same_grouping'
            || $data->distribution == 'dist_share_same_group_grouping'
            || $data->distribution == 'std_copy_grouping'
            || $data->distribution == 'std_copy_group_grouping') {

            $parent = new Google_Service_Drive_ParentReference();
            $parent->setId($parentdirid);
            $copiedfile->setParents(array($parent));
        }

        if ($data->document_type != GDRIVEFILETYPE_FOLDER) {
            $file = $this->service->files->copy($data->docid, $copiedfile);
            $link = sprintf($url[$file->mimeType]['linktemplate'], $file->id);
        } else {
            $file = $this->create_folder_for_group_grouping($data, $entity, $parentdirid);
            $link = $file->alternateLink;
        }

        $this->insert_permission($this->service, $file->id, $entity->email, $entity->type, $permission, $commenter);

        foreach ($teachers as $teacher) {
            if ($data->userid != $teacher->id) { // Skip teacher who created the file.
                $this->insert_permission($this->service, $file->id, $teacher->email, $entity->type, 'writer', false, true);
            }
        }

        $entityfiledata = new stdClass();
        $entityfiledata->googledocid = $data->id;
        $entityfiledata->url = $link;
        $entityfiledata->name = $copyname;
        $entityfiledata->permission = $data->permissions;

        switch ($data->distribution) {
            case 'group_copy':
                $entityfiledata->groupid = $entity->id;
                break;
            case 'grouping_copy':
                $entityfiledata->groupingid = $entity->id;
                $this->permission_for_members_in_grouping($entity->id,
                    $file->id, $permission, $commenter);
                break;
            case 'std_copy_group' :
                $entityfiledata->userid = $entity->id;
                $entityfiledata->groupid = $gid;
                break;
            case 'std_copy' :
                $entityfiledata->userid = $entity->id;
                break;
            case 'dist_share_same_group' :
                $entityfiledata->groupid = $gid;
                $this->permission_for_members_in_groups($gid, $file->id, $permission, $commenter);
                break;
            case 'dist_share_same_grouping':
                $entityfiledata->groupingid = $gid;
                $this->permission_for_members_in_grouping($gid, $file->id, $permission, $commenter);
                break;
            case 'std_copy_grouping':
                $entityfiledata->userid = $entity->id;
                $entityfiledata->groupid = $gid;
                break;
            case 'std_copy_group_grouping' :
                $entityfiledata->userid = $entity->id;
                $entityfiledata->groupid = $gid;
                break;
            case 'group_grouping_copy':
                if ($entity->gtype == 'group') {
                    $entityfiledata->groupid = $entity->gid;
                    $this->permission_for_members_in_groups($entity->gid, $file->id, $permission, $commenter);
                } else {
                    $entityfiledata->groupingid = $entity->gid;
                    $this->permission_for_members_in_grouping($entity->gid, $file->id, $permission, $commenter);
                }
                break;
            case 'dist_share_same_group_grouping' :
                if ($entity->gtype == 'group') {
                    $entityfiledata->groupid = $gid;
                    $this->permission_for_members_in_groups($gid, $file->id, $permission, $commenter);
                } else {
                    $entityfiledata->groupingid = $gid;
                    $this->permission_for_members_in_grouping($gid, $file->id, $permission, $commenter);
                }
                break;

            default:
                break;
        }
        // Save in DB.
        $DB->insert_record('googledocs_files', $entityfiledata);

        //Update creation_status.
        //If the copy is for a student and not a group or grouping. Because at this point
        //the copies are not being shared with the member yet.
        if ((!isset($entity->groupid) || !isset($entityfiledata->groupingid))
            && $data->distribution != 'group_grouping_copy'
            &&  $data->distribution != 'group_copy') {
            $this->update_creation_and_sharing_status($data, $entityfiledata);
        }

        return $link;
    }

    //Distribution = std_copy_grouping_copy or std_copy_group. Called by WS
    public function std_copy_group_grouping_copy($data, $student, $role, $commenter, $fromexisting, $gdrive, $teachers = null) {

        $grouping = false;
        $group_ids = [];
        $url;

        if ($data->distribution != 'std_copy_grouping') {
            $gdetails = get_groups_details_from_json(json_decode($data->group_grouping_json));
        } else {
            $grouping = true;
            $gdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
        }

        foreach ($gdetails as $g) {
            $group_ids [] = $g->id;
        }

        $folder_group_ids = get_folder_id_reference($student->id, $data->course, $data->id, $grouping);

        foreach ($folder_group_ids as $folder) {
            if (!in_array($folder->group_id, $group_ids)) {
                continue;
            }
            $url [] = $gdrive->make_file_copy($data, $folder->folder_id, $student, $role,
                $commenter, $fromexisting, $folder->group_id, $teachers);
        }
        return $url;
    }

    // Each group gets a copy. function called by the WS.
    public function make_file_copy_for_group($data, $student, $role, $commenter = false, $fromexisting = false, $teachers = null) {

        $groupmembers = groups_get_members($data->groupid, $fields = 'u.id');
        $groupmembersids = array_column($groupmembers, 'id');

        if (in_array($student->id, $groupmembersids)) {
            return $this->share_single_copy($student, $data, $role, $commenter);
        }

        foreach ($teachers as $teacher) {

            $gdrive->share_single_copy($teacher, $data, 'writer', false, false, true);
        }
    }

    /**
     * Give permission to group members to access (aka permission) file.
     * @param type $group_members
     * @param type $docid
     * @param type $role
     * @param type $commenter
     * @param type $fromexisting
     */
    public function permission_for_members_in_groups($groupid, $docid, $role, $commenter = false) {
        $members = groups_get_members($groupid, "u.id, u.email");
        foreach ($members as $member) {
            $this->insert_permission($this->service, $docid, $member->email, 'user', $role, $commenter);
        }
    }

    public function permission_for_members_in_grouping($groupingid, $docid, $role, $commenter = false) {
        $members = groups_get_grouping_members($groupingid);
        foreach ($members as $member) {
            $this->insert_permission($this->service, $docid, $member->email, 'user', $role, $commenter);
        }
    }

    /**
     * Create the folder structure in Google drive
     * Example: homework is the file name for groups 1 and 2 in course Math year 7
     * In Drive: (Capital names represent folders)
     *
     *      CGS CONNECT
     *          |
     *      MATH YEAR 7
     *          |
     *       HOMEWORK
     *          |_ GROUP 1
     *          |_ GROUP 2
     *
     * returns an array with the  google drive ids of the folders
     *
     */
    public function make_group_folder($data) {
        global $DB;

        if ($data->distribution != 'std_copy_grouping') {
            $gdetails = get_groups_details_from_json(json_decode($data->group_grouping_json));
        } else {  // Create the folders with the grouping name.
            $gdetails = get_groupings_details_from_json(json_decode($data->group_grouping_json));
        }

        $parentRef = new Google_Service_Drive_ParentReference();
        $parentRef->setId($data->parentfolderid);
        $ids = [];

        foreach ($gdetails as $g) {
            $r = new stdClass();
            $r->googledocid = $data->id;
            $r->group_id = $g->id;
            $r->folder_id = $this->create_child_folder($g->name, array($parentRef));
            $DB->insert_record('googledocs_folders', $r);
            $ids[] = $r;
        }

        return $ids;
    }

    /**
     * Update status in googledocs and  googledocs_work_task tables
     * @global type $DB
     * @param type $data
     * @param type $user
     */
    private function update_creation_and_sharing_status($data, $user) {
        global $DB;
        // Update creation_status.
        $conditions = ['docid' => $data->docid, 'googledocid' => $data->id, 'userid' => $user->userid];

        $id = $DB->get_field('googledocs_work_task', 'id', $conditions);
        $d = new StdClass();
        $d->creation_status = 1;

        if ($id) {
            $d->id = $id;
            $DB->update_record('googledocs_work_task', $d);
        } else { // It comes from dist. by group/grouping. It has to be added.
            $d->docid = $data->docid;
            $d->googledocid = $data->id;
            $d->userid = $user->userid;
            $DB->insert_record(googledocs_work_task, $d);
        }

        // Update sharing status.
    }

    /**
     *
     * @global type $DB
     * @param object $instance
     * @param object $newpermission
     * @return boolean
     */
    public function updates($instance, $newpermission) {
        global $DB;
        $conditions = ['googledocid' => $instance->id];
        $records = $DB->get_records('googledocs_files', $conditions, '', $fields = '*');
        $saved = true;
        $savedResults = [];

        foreach ($records as $record) {
            $fileId = get_file_id_from_url($record->url);
            $saved = $this->update_permissions($fileId, $newpermission, $instance); // this updates the file in Google drive.
            $savedResults [] = $saved; // Collect the result of the request.

            if ($saved) {
                $record->permission = $newpermission->permissions;
                $DB->update_record('googledocs_files', $record);
            }
        }

        return in_array(true, $savedResults);
    }

    /**
     * Helper function, updates the name of the file on the students record
     * (googledocs_files).
     * @param type $result
     * @param type $DB
     * @param type $details
     */
    private function update_shared_copy($result, $DB, $details, $instance) {
        $newdata = new \stdClass();
        foreach ($result as $r => $i) {
            $newdata->id = $i->id;
            $newdata->name = $details->name;
            $newdata->update_status = 'updated';
            $newdata->url = $instance->google_doc_url;

            $DB->update_record('googledocs_files', $newdata);
        }
        // Update permissions.
        if ($details->permissions != $instance->permissions) {
            $this->update_permissions($instance->docid, $details, $instance);
        }
    }

    /**
     * Updates the student's files in Google drive and  its records in the DB.
     *
     * @param type $result
     * @param type $DB
     * @param type $students
     * @param type $details
     */
    private function update_students_copies($result, $DB, $students, $details, $instance) {
        $j = 0; // index to traverse enrolled students
        $newdata = new \stdClass();
        $resultdetailsupdate = false;

        foreach ($result as $r => $i) {

            $fileid = get_file_id_from_url($i->url);
            $newdata->id = $i->id;
            $newdata->name = $details->name . '_' . ($students[$j])['displayName'];
            $resultdetailsupdate = $this->update_file_request($fileid, $newdata);

            if ($resultdetailsupdate) {
                $newdata->update_status = 'updated';
            } else {
                $newdata->name = $i->name; // Don't update name as there was an error
                $newdata->update_status = 'error';
            }
            $DB->update_record('googledocs_files', $newdata);
            $j++;
        }

        // Update permissions.
        if ($details->permissions != $instance->permissions) {

            $filename = $instance->name;
            $results = $DB->get_records_sql('SELECT * FROM {googledocs_files} WHERE ' . $DB->sql_like('name', ':name'),
                ['name' => '%' . $DB->sql_like_escape($filename) . '%']);

            foreach ($results as $result => $r) {
                $fileid = get_file_id_from_url($r->url);
                $this->update_permissions($fileid, $details, $instance);
            }
        }
    }

    /**
     * Logout.
     *
     * @return void
     */
    public function logout() {
        $this->store_access_token(null);
        //return parent::logout();
    }

    public function get_service() {
        return $this->service;
    }

    /**
     *
     * @param String $fileId
     * @return File Resource
     */
    public function getFile($fileId) {
        try {
            $file = $this->service->files->get($fileId);
            return $file;
        } catch (Exception $e) {
            error_log("An error occurred: " . $e->getMessage());

        }
    }

    /**
     * Return the id of a given file
     * @param Google_Service_Drive $service
     * @param String $filename
     * @return String
     */
    public function get_file_id($filename) {

        $p = ['q' => "mimeType = '" . GDRIVEFILETYPE_FOLDER . "' and title = '$filename' and trashed  = false and 'me' in owners",
            'corpus' => 'DEFAULT',
            'maxResults' => 1,
            'fields' => 'items'
        ];

        $result = $this->service->files->listFiles($p);

        foreach ($result as $r) {
            if ($r->title == $filename) {
                return ($r->id);
            }
        }

        return null;
    }

    /**
     * Helper function to get the students enrolled
     *
     * @param int $courseid
     * @return type
     */
    public function get_enrolled_students($courseid) {

        $context = \context_course::instance($courseid);

        $coursestudents = get_role_users(5, $context, false, 'u.*', 'u.lastname', 'u.id ASC');
        foreach ($coursestudents as $student) {
            $students[] = array('id' => $student->id, 'emailAddress' => $student->email,
                'displayName' => $student->firstname . ' ' . $student->lastname);
        }
        return $students;
    }

    /**
     * Get editing and non-editing teachers in the course except for the teacher
     * that is creating the activity
     * Role id = 3 --> Editing Teacher
     * Role id = 4 --> Non editing teacher
     * @param type $courseid
     */
    public function get_enrolled_teachers($courseid) {
        global $USER;

        $context = \context_course::instance($courseid);
        $teachers = [];
        $roles = ['3','4'];
        $courseteachers = get_role_users('', $context, false, 'ra.id ,  u.email, u.lastname, u.firstname',
            'ra.id ASC');

        foreach ($courseteachers as $teacher) {

            if ($teacher->id != $USER->id && in_array($teacher->roleid, $roles)) {
                $teachers [] = $teacher;
            }
        }

        return $teachers;
    }

    /**
     * Insert a new permission to a given file
     * @param Google_Service_Drive $service Drive API service instance.
     * @param String $fileId ID of the file to insert permission for.
     * @param String $value User or group e-mail address, domain name or NULL for default" type.
     * @param String $type The value "user", "group", "domain" or "default".
     * @param String $role The value "owner", "writer" or "reader".
     */
    public function insert_permission($service, $fileId, $value, $type, $role, $commenter = false, $is_teacher = false) {
        $newPermission = new Google_Service_Drive_Permission();
        $newPermission->setValue($value);
        $newPermission->setRole($role);
        $newPermission->setType($type);

        if ($commenter) {
            $newPermission->setAdditionalRoles(array('commenter'));
        }

        try {
            if ($is_teacher) {
                $service->permissions->insert($fileId, $newPermission, array('sendNotificationEmails' => false));
            } else if ($this->googledocinstance != null) {
                $emailMessage = get_string('emailmessageGoogleNotification',
                    'googledocs', $this->set_email_message_content());
                return $service->permissions->insert($fileId, $newPermission, array('emailMessage' => $emailMessage));
            } else {
                return $service->permissions->insert($fileId, $newPermission);
            }
        } catch (Exception $e) {
            error_log("An error occurred: " . $e->getMessage());
        }

        return null;
    }

    private function set_email_message_content() {
        global $DB;
        $sql = "SELECT id FROM mdl_course_modules WHERE course = :courseid AND instance = :instanceid;";
        $params = ['courseid' => $this->googledocinstance->course, 'instanceid' => $this->googledocinstance->id];
        $cm = $DB->get_record_sql($sql, $params);
        $url = new moodle_url('/mod/googledocs/view.php?', ['id' => $cm->id]);
        $a = (object) [
                'url' => $url->__toString(),
        ];
        return $a;
    }

    /**
     * Retrieve a list of permissions.
     *
     * @param Google_Service_Drive $service Drive API service instance.
     * @param String $fileId ID of the file to retrieve permissions for.
     * @return Array List of permissions.
     */
    public function get_permissions($fileId) {
        try {
            $permissions = $this->service->permissions->listPermissions($fileId, array('fields' => 'items'));
            return $permissions->getItems();
        } catch (Exception $e) {
            error_log("An error occurred: " . $e->getMessage());
        }
        return null;
    }

// --------------------------------------------- DATA ACCESS FUNCTIONS -------------------------- //

    /**
     * Helper function to save the instance record in DB
     * @global type $googledocs
     * @global type $sharedlink
     * @param type $folderid
     * @param type $owncopy
     * @return type
     */
    public function save_instance($googledocs, $file, $sharedlink, $folderid, $owncopy = false, $dist, $intro = '', $fromexisting = false, $existingurl = '') {

        global $USER, $DB;
        if ($fromexisting) {
            $googledocs->google_doc_url = $existingurl;
        } else if(!$owncopy || $googledocs->distribution == 'group_copy'){
            //$googledocs->google_doc_url = $sharedlink[1];
            $googledocs->google_doc_url = $sharedlink;
        }

        $googledocs->docid = $file->id;
        $googledocs->parentfolderid = $folderid;
        $googledocs->userid = $USER->id;
        $googledocs->timeshared = (strtotime($file->createdDate));
        $googledocs->timemodified = $googledocs->timecreated;
        $googledocs->name = $file->title;
        $googledocs->intro = $intro['text'];
        $googledocs->use_document = $googledocs->use_document;
        $googledocs->sharing = 0;  // Up to this point the copies are not created yet.
        $googledocs->distribution = $dist;
        $googledocs->introformat = $intro['format'];


        return $DB->insert_record('googledocs', $googledocs);
    }

    /**
     * Helper function to save the students links records.
     * @global type $DB
     * @param type $sharedlink
     * @param type $instanceid
     */
    public function save_students_links_records($links_for_students, $instanceid) {
        global $DB;
        $data = new \stdClass();
        foreach ($links_for_students as $sl => $s) {
            $data->userid = $sl;
            $data->googledocid = $instanceid;
            $data->url = $s[0];
            $data->name = $s['filename'];
            $DB->insert_record('googledocs_files', $data);
        }
    }

    /**
     * Save relevant data to create students file copies
     * @global type $DB
     * @param type $file
     * @param type $students
     */
    public function save_work_task_scheduled($fileid, $students, $googledocid) {
        global $DB;
        foreach ($students as $s) {

            $data = new \stdClass();
            $data->docid = $fileid;
            $data->googledocid = $googledocid;
            $data->userid = !is_object($s)? $s['id'] : $s->id;

            $DB->insert_record('googledocs_work_task', $data);
        }
    }

    /**
     * Helper function to update the students links when there was an update
     * on the files distribution (from all_share to each_gets_own)
     * @global type $DB
     * @param type $links_for_students
     * @param type $instanceid
     */
    public function update_students_links_records($links_for_students, $results) {
        global $DB;
        $data = new \stdClass();
        $id = current($results);

        foreach ($links_for_students as $sl => $s) {
            $data->id = $id->id;
            $data->url = $s[0];
            $data->name = $s['filename'];
            $DB->update_record('googledocs_files', $data);
            $id = next($results);
        }
    }

    // ------------------------------------------HTTP Requests -----------------------------------------//

    /**
     * Calls the update function from Googles API using Curl
     * The update function from Drive.php did not work.
     *
     * @param type $fileid
     * @param type $details
     */
    private function update_file_request($fileid, $details, $studentsfolder = false) {

        try {
            $title = $studentsfolder ? $details->name . '_students' : $details->name_doc;
            $data = array('title' => $title);
            $data_string = json_encode($data);
            $contentlength = strlen($data_string);

            $token = $this->refresh_token();

            $url = "https://www.googleapis.com/drive/v2/files/" . $fileid . "?uploadType=multipart?key=" . $this->api_key;
            $header = ['Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/json',
                'Content-Length:' . $contentlength];

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_exec($ch);
            $r = (curl_getinfo($ch))['http_code'] === 200;
        } catch (Exception $ex) {
            error_log($ex->getMessage());
        } finally {
            curl_close($ch);
        }
        return $r;
    }

    /**
     * Calls the update function from Googles API using Curl
     * The update function from Drive.php did not work.
     * @param type $fileId
     * @param type $permission
     * @return type
     */
    private function update_permission_request($fileId, $permission) {
        $r = false;

        try {

            $data = array('role' => $permission->role,
                'additionalRoles' => $permission->additionalRoles);

            $data_string = json_encode($data);
            $contentlength = strlen($data_string);

            $token = $this->refresh_token();

            $url = "https://www.googleapis.com/drive/v2/files/" . $fileId . "/permissions/" . $permission->id . "?key=" . $this->api_key;
            $header = ['Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/json',
                'Content-Length:' . $contentlength];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_REFERER, $this->referrer);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_exec($ch);

            $r = (curl_getinfo($ch))['http_code'] == 200;

        } catch (Exception $ex) {
            error_log($ex->getMessage());
        } finally {
            curl_close($ch);
        }

        return $r;
    }

    /**
     * Calls the update function from Googles API using Curl
     * The update function from Drive.php did not work.
     * This update is done verb PATCH.
     * @param type $fileId
     * @param type $permission
     * @return type
     */
    private function update_patch_permission_request($fileId, $permission) {

        try {

            $data = array('role' => $permission->role,
                'additionalRoles' => $permission->additionalRoles);

            $data_string = json_encode($data);
            $contentlength = strlen($data_string);

            $token = $this->refresh_token();

            $url = "https://www.googleapis.com/drive/v2/files/" . $fileId . "/permissions/" . $permission->id . "?key=" . $this->api_key;
            $header = ['Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/json',
                'Content-Length:' . $contentlength];

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_REFERER, $this->referrer);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_exec($ch);

            $r = (curl_getinfo($ch))['http_code'] === 200;
        } catch (Exception $ex) {
            error_log($ex->getMessage());
        } finally {
            curl_close($ch);
        }
        return $r;
    }

    /*
     * Deletes a permission from a file
     */

    private function delete_permission_request($fileId, $permissionId) {

        try {
            $token = $this->refresh_token();

            $url = "https://www.googleapis.com/drive/v2/files/" . $fileId . "/permissions/" . $permissionId . "?key=" . $this->api_key;
            $header = ['Authorization: Bearer ' . $token,
                'Accept: application/json'];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_exec($ch);
            $r = (curl_getinfo($ch))['http_code'] === 200;
        } catch (Exception $ex) {
            error_log($ex->getMessage());
        } finally {
            curl_close($ch);
        }

        return $r;
    }

    /**
     * Delete file.
     * The class curlio.php in google folder, doesn't have the DELETE option
     * therefor $service->files->delete throws a coding exception.
     * That is why this function exists.
     * @param type $fileId
     * @return type
     * @throws Exception
     */
    public function delete_file_request($fileId) {

        try {
            $token = $this->refresh_token();

            $url = "https://www.googleapis.com/drive/v2/files/" . $fileId . "?key=" . $this->api_key;
            $header = ['Authorization: Bearer ' . $token,
                'Accept: application/json',];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_REFERER, $this->referrer);
            curl_exec($ch);

            $r = (curl_getinfo($ch))['http_code'] === 204;
            // $r = curl_getinfo($ch);
        } catch (Exception $ex) {
            error_log($ex->getMessage());
        } finally {
            curl_close($ch);
        }

        return $r;
    }

    /**
     * Updates the permission  of a shared file
     * @param type $fileId
     * @param type $permissionlist
     * @return type
     */
    public function update_permissions($fileId, $details, $instance) {
        $saved = true;

        try {

            $permissionlist = $this->get_permissions($fileId);
            $commenter = false;
            $removeaditionalrole = false;

            if ($instance->permissions == GDRIVEFILEPERMISSION_COMMENTER && $details->permissions != GDRIVEFILEPERMISSION_COMMENTER) {
                $removeaditionalrole = true;
            }

            if ($details->permissions == GDRIVEFILEPERMISSION_COMMENTER) {
                $commenter = true;
                $newrole = 'reader';
            } else if ($details->permissions == GDRIVEFILEPERMISSION_READER || $instance->permissions == GDRIVEFILEPERMISSION_COMMENTER) {
                $newrole = 'reader';
            } else {
                $newrole = 'writer';
            }

            foreach ($permissionlist as $pl => $l) {

                if ($l->role === "owner") {
                    continue;
                }

                if ($commenter) {
                    $l->setAdditionalRoles(array('commenter'));
                } else if ($removeaditionalrole) {
                    $l->setAdditionalRoles(array());
                }

                $l->setRole($newrole);

                $saved = $this->update_permission_request($fileId, $l);
            }
        } catch (Exception $ex) {
            $saved = false;
            error_log($ex->getMessage());
        }
        return $saved;
    }

    /**
     * This function is called when the student submits a document.
     * Set new permission to viewer.
     * @param type $fileid
     */
    public function update_permission_when_submitted($fileid, $email) {
        $this->refresh_token();
        $permissionlist = $this->get_permissions($fileid);
        $newrole = 'reader';

        try {

            foreach ($permissionlist as $pl => $l) {

                if ($l->role === "owner") {
                    continue;
                }

                if ($l->emailAddress === $email) {

                    $l->setRole($newrole);
                    return $this->update_patch_permission_request($fileid, $l);
                }
            }
        } catch (Exception $ex) {
            error_log($ex->getMessage());
        }
    }

    /**
     * Removes the permission given to students when all_share distr. changes
     * to each_gets_own
     * @param type $result
     * @param type $instance
     * @param type $details
     */
    private function update_distribution($result, $instance) {

        try {

            foreach ($result as $r => $i) {

                $fileId = get_file_id_from_url($i->url);
                $permissionlist = $this->get_permissions($fileId);

                foreach ($permissionlist as $pl => $l) {

                    if ($l->role === "owner") {
                        continue;
                    }
                    $this->delete_permission_request($instance->docid, $l->id);
                }
            }
        } catch (Exception $ex) {
            print($ex->getMessage());
        }
    }

    /**
     * When distributing by group or grouping. The original file is deleted
     * we need to update the url of the googledocs table in order to display
     * have a valid link after.
     * @global type $DB
     * @param type $data
     * @param type $url
     */
    public function update_shared_url($url, $instance_id) {

        global $DB;

        $params = ['googledocid' => $this->instanceid, 'groupingid' => $data->groupingid];
        $id = $DB->get_field('googledocs_files', 'id', $params, IGNORE_MISSING);
        $gd = new StdClass();
        foreach ($url as $u) {
            $gd->url = $u->url;
        }
        $gd->id = $id;

        $DB->update_record('googledocs_files', $gd);
    }

    public function create_dummy_folders() {
        for ($i = 0; $i < 20; $i++) {
            $dirname = 'Folder_' . $i;
            $this->create_folder($dirname);
        }
    }

}
