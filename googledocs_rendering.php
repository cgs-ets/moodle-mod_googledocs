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
 *
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/googledocs/locallib.php');

class googledocs_rendering {
    /*
     * @var int $courseid The course id
     */

    protected $courseid;

    /**
     * @var int instance
     */
    protected $instanceid;

    /**
     * @var \stdClass $course The course details.
     */
    protected $course;

    /**
     * @var  $context The course context.
     */
    protected $context;

    /**
     * @var stdClass
     */
    protected $googledocs;

    /**
     * @var array
     */
    protected $coursestudents;

    /**
     * @var boolean
     */
    protected $created;
    /**
     *
     * @var course module info
     */
    protected $cm;
    /**
     *
     * @var boolean
     */
    protected $canbegraded;

    public function __construct($courseid, $selectall, $context, $cm, $googledocs, $created = true) {
        $this->selectall = $selectall;
        $this->context = $context;
        $this->courseid = $courseid;
        $this->currentgroup = 0;
        $this->context = $context;
        $this->instanceid = $cm->instance;
        $this->cm = $cm;
        $this->googledocs = $googledocs;
        $this->created = $created;
        $this->coursestudents = get_role_users(5, $this->context, false, 'u.*');
        $this->canbegraded = ($googledocs->permissions == 'edit' || $googledocs->permissions == 'comment') ? true : false;
    }

    /**
     * Renders table with all files already created.
     * @global type $OUTPUT
     * @global type $CFG
     * @global type $PAGE
     */
    public function render_table() {
        global $USER, $DB;

        $types = google_filetypes();
        $isstudent = false;

        if (is_role_switched($this->courseid)) {
            $role = $DB->get_record('role', array('id' => $USER->access['rsw'][$this->context->path]));
            $isstudent = ($role->shortname == 'student');
        }

        if (has_capability('mod/googledocs:view', $this->context)
            && is_enrolled($this->context, $USER->id, '', true)
            && !is_siteadmin()
            && !has_capability('mod/googledocs:viewall', $this->context)) {
            $isstudent = true;
        }

        $this->render($types, $isstudent);
    }

    // Helper function.
    private function render($types, $isstudent) {
        global $USER;
        $students = $this->query_db();
        $usergroups = groups_get_user_groups($this->courseid, $USER->id);

        switch ($this->googledocs->distribution) {

            case 'std_copy' :

                if ($this->created && $isstudent) {
                    $this->render_files_for_student($types);
                } else if (!$this->created) {
                    $this->render_student_table_processing($types, $students, $this->googledocs->distribution);
                } else {
                    $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
                }

                break;

            case 'std_copy_group' :

                if ($this->created && $isstudent) {
                    $this->render_files_for_student($types);
                } else if (!$this->created) {
                    $this->render_student_table_processing($types, $students, $this->googledocs->distribution);
                } else {
                    $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
                }

                break;

            case 'std_copy_grouping' :
                if ($this->created && $isstudent) {
                    $this->render_files_for_student($types);
                } else if (!$this->created) {
                    $this->render_student_table_processing($types, $students, $this->googledocs->distribution);
                } else {
                    $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
                }

                break;

            case 'dist_share_same' :
                if ($this->created && $isstudent) {
                    $this->render_files_for_student($types);
                } else if (!$this->created) {
                    $this->render_student_table_processing($types, $students, $this->googledocs->distribution);
                } else {
                    $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
                }

                break;

            case 'dist_share_same_group' :
                if ($this->created && $isstudent) {
                    $this->render_files_for_students_in_groups($types, $usergroups);
                } else {
                    $this->render_table_by_group($types);
                }

                break;

            case 'dist_share_same_grouping':
                if ($this->created && $isstudent) {
                    $this->render_files_for_students_in_groups($types, $usergroups);
                    $this->render_grouping_student_table($types, $students);
                } else {
                    $this->render_table_by_grouping($types);
                }
                break;

            case 'group_copy':
                if ($this->created && $isstudent) {
                    $this->render_files_for_students_in_groups($types, $usergroups);
                } else {
                    $this->render_table_by_group($types);
                }
                break;

            case 'grouping_copy':
                if ($this->created && $isstudent) {
                    $this->render_files_for_students_in_groups($types, $usergroups);
                } else {
                    $this->render_table_by_grouping($types);
                }
                break;

            case 'group_grouping_copy' :
                if ($this->created && $isstudent) {
                    $this->render_files_for_students_in_group_grouping($types, $usergroups);
                } else {
                    $this->render_table_by_group_grouping($types);
                }
                break;

            case 'std_copy_group_grouping' :
                if ($this->created && $isstudent) {
                    $this->render_files_for_student_by_group_grouping($types, $usergroups);
                } else if (!$this->created) {
                    $this->render_student_table_processing($types, $students, $this->googledocs->distribution);
                } else {
                    $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
                }
                break;

            case 'dist_share_same_group_grouping' :
                if ($this->created && $isstudent) {
                    $this->render_files_for_students_in_groups($types, $usergroups);
                } else if (!$this->created) {
                    $this->render_table_by_group_grouping($types, $students, $this->googledocs->distribution);
                } else {
                    $this->render_table_by_group_grouping($types, $students, $this->googledocs->distribution);
                }
                break;
        }
    }

    public function render_work_in_progress() {
        global $OUTPUT;
        echo $OUTPUT->render_from_template('mod_googledocs/work_in_progress', '');
    }

    private function render_files_for_student($types) {
        global $DB, $USER, $CFG, $OUTPUT;

        $sql = "SELECT * FROM mdl_googledocs_files WHERE userid = :userid AND googledocid = :instanceid";
        $params = ['userid' => $USER->id, 'instanceid' => $this->googledocs->id];

        $result = $DB->get_records_sql($sql, $params);

        $this->get_students_file_view_content($result, $types);
    }

    /**
     * When dist. is by group, the record doesn't keep a 1 to 1 relationship with the user id
     */
    private function render_files_for_student_by_group_grouping($types, $usergroups) {
        global $DB, $USER, $CFG;

        $a = $usergroups[0]; // Has all the groups this user belongs to.

        if (empty($a)) {
            $result = [];
        } else {
            list($insql, $inparams) = $DB->get_in_or_equal($a);

            $sql = "SELECT * FROM mdl_googledocs_files
                    WHERE groupid  $insql  AND googledocid = {$this->instanceid} AND userid = {$USER->id}";

            $result = $DB->get_records_sql($sql, $inparams);
        }
        $this->get_students_file_view_content($result, $types);
    }

    private function render_files_for_students_in_groups($types, $usergroups = null) {
        global $DB;
        $a = $usergroups[0]; // Has all the groups this user belongs to.
        if (empty($a)) {
            $result = [];
        } else {
            list($insql, $inparams) = $DB->get_in_or_equal($a);

            $sql = "SELECT * FROM mdl_googledocs_files
                    WHERE groupid  $insql  AND googledocid = {$this->instanceid}";

            $result = $DB->get_records_sql($sql, $inparams);
        }
        $this->get_students_file_view_content($result, $types);
    }

    private function render_files_for_students_in_group_grouping($types, $usergroups = null) {
        global $DB;

        $groupids = $usergroups[0]; // Has all the groups this user belongs to.
        $groupingids = [];

        // Gather grouping and groups ids in one array.
        foreach ($usergroups as $i => $g) {
            if ($i != 0) {
                $groupingids [] = $i;
            }
        }

        $a = $usergroups[0]; // Has all the groups this user belongs to.

        list($insql, $inparams) = $DB->get_in_or_equal($groupids);
        // Get groups.
        $sql = "SELECT * FROM mdl_googledocs_files
                WHERE groupid  $insql  AND googledocid = {$this->instanceid}";
        $result = $DB->get_records_sql($sql, $inparams);

        list($insql, $inparams) = $DB->get_in_or_equal($groupingids);
        // Get groupings.
        $sql = "SELECT * FROM mdl_googledocs_files
                WHERE groupingid  $insql  AND googledocid = {$this->instanceid}";

        $result2 = $DB->get_records_sql($sql, $inparams);
        $result = array_merge($result, $result2);

        $this->get_students_file_view_content($result, $types);
    }

    private function get_students_file_view_content($sqlresult, $types) {
        global $DB, $USER, $OUTPUT, $CFG;

        // Get the Google Drive object.
        $client = new \googledrive($this->context->id, false, false, true, true);
        $emailaddress = $DB->get_record('user', array('id' => $USER->id), 'email');
        $data = ['isloggedintogoogle' => $client->check_google_login(),
            'email' => $emailaddress->email,
            'viewpermission' => $this->googledocs->permissions,
            'filename' => $this->googledocs->name,
            'intro' => format_module_intro('googledocs', $this->googledocs, $this->cm->id, false)
        ];

        $params = ['userid' => $USER->id, 'instanceid' => $this->googledocs->id];

        foreach ($sqlresult as $r) {

            if ($r->groupid == null) {
                $r->groupid = 0;
            }
            $submitstatus = false;
            $submitted = false;
            $graded;

            if ($view = $this->googledocs->permissions != 'view') {
                $sql = "SELECT status FROM mdl_googledocs_submissions WHERE userid = :userid
                        AND googledoc = :instanceid
                        AND groupid =  {$r->groupid}";

                $submitstatus = $DB->get_record_sql($sql, $params);
                $submitted = $submitstatus;
                $graded = $DB->get_record('googledocs_grades', array('userid' => $USER->id,
                    'googledocid' => $this->googledocs->id));
            }

            $extra = "onclick=\"this.target='_blank';\"";
            $icon = $types[get_file_type_from_string($r->url)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot . '/mod/googledocs/pix/' . $icon);
            $data ['files'][] = ['extra' => $extra,
                'icon' => $icon,
                'url' => $r->url,
                'groupid' => $r->groupid,
                'img' => $imgurl,
                'fileid' => get_file_id_from_url($r->url),
                'instanceid' => $this->googledocs->id,
                'submitted' => $submitted,
                'graded' => empty($graded) ? false : true,
                'permission' => ($submitted) ? 'View' : ucfirst($this->googledocs->permissions)
            ];
        }

        $data['nothingtodisplay'] = array_key_exists('files', $data);
        echo $OUTPUT->render_from_template('mod_googledocs/student_file_view', $data);
    }

    /**
     * Get the information needed to render table when the files are being processed
     * The students array is an an array of array.
     *
     * @param string $types
     * @param array $students
     * @param string $dist
     */
    private function render_student_table_processing($types, $students, $dist = '') {
        global $OUTPUT, $CFG, $DB;

        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');

        // We need all the group.
        $groupids = '';

        if ($dist == 'dist_share_same_group') {
            $groups = get_groups_details_from_json(json_decode($this->googledocs->group_grouping_json));
            foreach ($groups as $group) {
                $groupids .= '-' . $group->id;
            }
            $groupids = ltrim($groupids, '-');
        }

        $data = ['googledocid' => $this->googledocs->docid,
            'instanceid' => $this->googledocs->id,
            'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false,
            'members' => array(),
            'owneremail' => $owneremail->email,
            'all_groups' => $groupids,
            'canbegraded' => $this->canbegraded,
            'intro' => format_module_intro('googledocs', $this->googledocs, $this->cm->id, false),
            'nointro' => empty($this->googledocs->intro) ? true : false
        ];
        $i = 0;

        foreach ($students as $st) {
            foreach ($st as $student) {
                $picture = $OUTPUT->user_picture($student, array('course' => $this->courseid,
                    'includefullname' => true, 'class' => 'userpicture'));
                $icon = $types[get_file_type_from_string($this->googledocs->document_type)]['icon'];
                $imgurl = new moodle_url($CFG->wwwroot . '/mod/googledocs/pix/' . $icon);
                $image = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));
                $links = html_writer::link('#', $image, array('target' => '_blank',
                        'id' => 'link_file_' . $i));
                $urlparams = ['id' => $this->cm->id,
                    'action' => 'grader',
                    'userid' => $student->id
                ];
                $gradeurl = new moodle_url('/mod/googledocs/view_grading_app.php?', $urlparams);
                $data['students'][] = [
                    'picture' => $picture,
                    'fullname' => fullname($student),
                    'student-id' => $student->id,
                    'student-email' => $student->email,
                    'link' => $links,
                    'student-groupid' => isset($student->groupid) ? $student->groupid : '',
                    'status' => html_writer::start_div('', ["id" => 'file_' . $i]) . html_writer::end_div(),
                    'access' => ucfirst($this->googledocs->permissions),
                    'creating' => $this->created,
                    'gradeurl' => $gradeurl,
                ];

                $i++;
            }
        }

        echo $OUTPUT->render_from_template('mod_googledocs/student_table', $data);
    }

    /**
     * Dist = dist_share_same_grouping
     */
    private function render_grouping_student_table($types) {
        global $DB, $OUTPUT, $CFG;

        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');
        $studentsdetails = [];

        $groupingids = get_grouping_ids_from_json(json_decode($this->googledocs->group_grouping_json));

        foreach ($groupingids as $id) {
            $studentsdetails = array_merge($studentsdetails, groups_get_grouping_members($id));
        }

        // Remove duplicates.
        $students = array_map('json_encode', $studentsdetails);
        $students = array_unique($students);
        $students = array_map('json_decode', $students);

        $groupingids = implode("-", $groupingids);

        $data = ['googledocid' => $this->googledocs->docid,
            'instanceid' => $this->googledocs->id,
            'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false,
            'members' => array(),
            'owneremail' => $owneremail->email,
            'all_groupings' => $groupingids,
            'canbegraded' => $this->canbegraded
        ];
        $i = 0;
        foreach ($students as $j => $student) {

            $checkbox = new \core\output\checkbox_toggleall('students-file-table', false, [
                'classes' => 'usercheckbox m-1',
                'id' => 'user' . $student->id,
                'name' => 'user' . $student->id,
                'checked' => false,
                'label' => get_string('selectitem', 'moodle', $student->firstname),
                'labelclasses' => 'accesshide',
            ]);

            $picture = $OUTPUT->user_picture($student, array('course' => $this->courseid,
                'includefullname' => true, 'class' => 'userpicture'));
            $icon = $types[get_file_type_from_string($this->googledocs->document_type)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot . '/mod/googledocs/pix/' . $icon);
            $image = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

            $studentgroupingids = array_keys(groups_get_user_groups($this->courseid, $student->id));
            unset($studentgroupingids[array_key_last($studentgroupingids)]);
            $student->grouping = implode('-', $studentgroupingids);

            if ($this->created) {
                $urls = $this->get_grouping_file_url($student->id);
                $links = '';
                foreach ($urls as $url) {
                    $links .= html_writer::link($url->url, $image, array('target' => '_blank', 'id' => 'link_file_' . $i));
                    $status = html_writer::start_div('', ["id" => 'file_' . $i]) . 'Created' . html_writer::end_div();
                }
            } else {
                $links = html_writer::link('#', $image, array('target' => '_blank', 'id' => 'link_file_' . $i));
                $status = html_writer::start_div('', ["id" => 'file_' . $i]) . html_writer::end_div();
            }

            $data['students'][] = ['checkbox' => $OUTPUT->render($checkbox),
                'picture' => $picture,
                'fullname' => fullname($student),
                'student-id' => $student->id,
                'student-email' => $student->email,
                'link' => $links,
                'student-groupingid' => isset($student->grouping) ? $student->grouping : '',
                'status' => $status
            ];

            $i++;
            $links = '';
        }

        echo $OUTPUT->render_from_template('mod_googledocs/grouping_student_table', $data);
    }

    /**
     * Get the information needed to render table when the files are already created.
     * The students array is an array of objects.
     *
     * @param type $types
     * @param array $students
     * @param type $dist
     */
    private function render_table_by_students_files_created($types, $students, $dist = '') {
        global $OUTPUT, $CFG, $DB;
        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');

        // We need all the group ids.
        $group_ids = '';

        if ($dist == 'dist_share_same_group' || $dist == 'dist_share_same_grouping') {

            $groups = get_groups_details_from_json(json_decode($this->googledocs->group_grouping_json));
            foreach ($groups as $group) {
                $group_ids .= '-' . $group->id;
            }
            $group_ids = ltrim($group_ids, '-');
        }

        $data = ['googledocid' => $this->googledocs->docid,
            'docname' => $this->googledocs->name,
            'instanceid' => $this->googledocs->id,
            'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false,
            'members' => array(),
            'show_group' => false,
            'owneremail' => $owneremail->email,
            'all_groups' => $group_ids,
            'canbegraded' => $this->canbegraded,
            'intro' => format_module_intro('googledocs', $this->googledocs, $this->cm->id, false),
            'nointro' => empty($this->googledocs->intro) ? true : false
        ];

        $i = 0;

        foreach ($students as $student) {

            $picture = $OUTPUT->user_picture($student, array('course' => $this->courseid,
                'includefullname' => true, 'class' => 'userpicture'));
            $icon = $types[get_file_type_from_string($this->googledocs->document_type)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot . '/mod/googledocs/pix/' . $icon);
            $image = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));
            $links = '';

            $readytograde = false;
            $graded = false;
            if ($this->googledocs->permissions != 'view') { // If the file is only view then no grading.
                $readytograde = $DB->get_record('googledocs_submissions', array('userid' => $student->id,
                    'googledoc' => $this->googledocs->id));
            }

            $graded = $DB->get_record('googledocs_grades', array('userid' => $student->id,
                'googledocid' => $this->googledocs->id));

            // If a student belongs to more than one group, it can get more than one file. Render all.
            if ($dist == 'std_copy_group' || $dist == 'std_copy_grouping'
                || $dist == 'std_copy_group_grouping') {

                $urls = $DB->get_records('googledocs_files', array('userid' => $student->id,
                    'googledocid' => $this->googledocs->id), '');

                foreach ($urls as $url) {
                    $links .= html_writer::link($url->url, $image,
                        array('target' => '_blank','id' => 'link_file_' . $i));
                }
            }

            if ($dist == 'dist_share_same' || $dist == 'std_copy') {
                $links .= html_writer::link($student->url, $image, array('target' => '_blank',
                        'id' => 'link_file_' . $i));
            }

            if ($links == '') {
                $links .= html_writer::link($student->url, $image, array('target' => '_blank',
                        'id' => 'link_file_' . $i));
            }

            $urlparams = ['id' => $this->cm->id,
                'action' => 'grader',
                'userid' => $student->id
            ];
            $gradeurl = new moodle_url('/mod/googledocs/view_grading_app.php?', $urlparams);

            list($statustext, $accesstext, $class) = $this->get_status_style($readytograde, $this->googledocs->permissions, $graded);

            $data['students'][] = ['picture' => $picture,
                'fullname' => fullname($student),
                'student-id' => $student->id,
                'student-email' => $student->email,
                'link' => $links,
                'student-groupid' => isset($student->groupid) ? $student->groupid : '',
                'status' => html_writer::start_div($class, ['id' => 'file_' . $i]) . $statustext . html_writer::end_div(),
                'readytograde' => $readytograde,
                'access' => html_writer::start_div($class, ['id' => 'file_' . $i]) .
                $accesstext .
                html_writer::end_div(), ucfirst($this->googledocs->permissions),
                'gradeurl' => $gradeurl,
                'beengraded' => $graded,
                'gradevalue' => ($graded) ? $graded->grade : '',
            ];

            $i++;
        }

        echo $OUTPUT->render_from_template('mod_googledocs/student_table', $data);
    }

    /**
     *
     * @global type $OUTPUT
     * @global type $CFG
     * @param type $types
     */
    private function render_table_by_group($types) {

        global $OUTPUT, $CFG, $DB;
        $groupsandmembers = $this->get_groups_and_members();

        $icon = $types[get_file_type_from_string($this->googledocs->document_type)]['icon'];
        $imgurl = new moodle_url($CFG->wwwroot . '/mod/googledocs/pix/' . $icon);
        $iconimage = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

        // Get teacher email. Is the owner of the copies that are going to be created for each group.
        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');
        $groupids = '';
        $isfoldertype = ($this->googledocs->document_type == GDRIVEFILETYPE_FOLDER) ? true : false;
        if ($this->googledocs->distribution == 'dist_share_same_group') {
            $groups = get_groups_details_from_json(json_decode($this->googledocs->group_grouping_json));
            foreach ($groups as $group) {
                $groupids .= '-' . $group->id;
            }
            $groupids = ltrim($groupids, '-');
        }

        $data = [
            'groups' => array(),
            'googledocid' => '',
            'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false,
            'owneremail' => $owneremail->email,
            'all_groups' => $groupids, // A list of the groups ids. This is use in the Ajax call.
            'intro' => format_module_intro('googledocs', $this->googledocs, $this->cm->id, false),
            'isfoldertype' => $isfoldertype,
        ];

        $data['googledocid'] = $this->googledocs->docid;
        $data['instanceid'] = $this->googledocs->id;

        $urlshared = '#';

        $i = 0;

        foreach ($groupsandmembers as $groupmember => $members) {

            $conditions = ['googledocid' => $this->instanceid, 'groupid' => $members['groupid']];
            $urlshared = $DB->get_field('googledocs_files', 'url', $conditions, IGNORE_MISSING);

            $data['groups'][] = ['groupid' => $members['groupid'],
                'groupname' => $groupmember,
                'user_pictures' => $members['user_pictures'],
                'fileicon' => html_writer::link($urlshared, $iconimage,
                    array('target' => '_blank', 'id' => 'shared_link_url_' . $members['groupid'])),
                'sharing_status' => html_writer::start_div('', ["id" => 'status_col']) . html_writer::end_div(),
                'student_access' => ucfirst($this->googledocs->permissions)
            ];

            foreach ($members['groupmembers'] as $member) {

                $url = isset($member->url) ? $member->url : '#';

                $data['groups'][] = ['fullname' => fullname($member),
                    'link' => html_writer::link($url, $iconimage,
                        array('target' => '_blank', 'id' => 'link_file_' . $i)),
                    'status' => html_writer::start_div('', ["id" => 'file_' . $i]) . html_writer::end_div(),
                    'student-id' => $member->id,
                    'student-email' => $member->email,
                    'groupid' => $members['groupid']
                ];
                $i++;
            }
        }

        echo $OUTPUT->render_from_template('mod_googledocs/group_table', $data);
    }

    /**
     *
     * @global type $DB
     * @global type $CFG
     * @global type $OUTPUT
     * @param type $types
     */
    private function render_table_by_grouping($types) {
        global $DB, $OUTPUT;

        $j = json_decode($this->googledocs->group_grouping_json);
        // Get teacher email. Is the owner of the copies that are going to be created for each group.
        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');
        $data['docid'] = $this->googledocs->docid;
        $data['instanceid'] = $this->instanceid;
        $data['from_existing'] = ($this->googledocs->use_document == 'existing') ? true : false;
        $data['owneremail'] = $owneremail->email;
        $data ['studentaccess'] = ucfirst($this->googledocs->permissions);
        $data['intro'] = format_module_intro('googledocs', $this->googledocs, $this->cm->id, false);

        foreach ($j->c as $c => $condition) {
            if ($condition->type == 'grouping') {
                $groupingprops = groups_get_grouping($condition->id, 'id, name');
                $groupingdetails = $this->grouping_details_helper_function($types, $groupingprops);

                foreach ($groupingdetails as $gg) {
                    $data['groupings']['groupingdetails'][] = ['groupingid' => $gg['groupingid'],
                        'groupingdname' => $gg['groupingdname'],
                        'memberspictures' => $gg['memberspictures'],
                        'fileicon' => $gg['fileicon'],
                        'sharingstatus' => html_writer::start_div('',
                            ["id" => 'status_col_' . $gg['groupingid']]) . html_writer::end_div()
                    ];
                }
            }
        }

        echo $OUTPUT->render_from_template('mod_googledocs/grouping_table', $data);
    }

    private function render_table_by_group_grouping($types) {
        global $OUTPUT, $DB, $CFG;

        // Get teacher email. Is the owner of the copies that are going to be created for each group.
        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');
        $data['docid'] = $this->googledocs->docid;
        $data['instanceid'] = $this->instanceid;
        $data['from_existing'] = ($this->googledocs->use_document == 'existing') ? true : false;
        $data['owneremail'] = $owneremail->email;
        $data['studentaccess'] = ucfirst($this->googledocs->permissions);
        $data['intro'] = format_module_intro('googledocs', $this->googledocs, $this->cm->id, false);

        $groupsandmembers = $this->get_groups_and_members();
        $j = json_decode($this->googledocs->group_grouping_json);

        $icon = $types[get_file_type_from_string($this->googledocs->document_type)]['icon'];
        $imgurl = new moodle_url($CFG->wwwroot . '/mod/googledocs/pix/' . $icon);
        $iconimage = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

        // Get the Groups details.
        foreach ($groupsandmembers as $groupmember => $members) {

            $urlshared = '';
            if ($this->created) {
                $conditions = ['googledocid' => $this->googledocs->id, 'groupid' => $members['groupid']];
                $urlshared = $DB->get_field('googledocs_files', 'url', $conditions, IGNORE_MISSING);
            }
            $data['group_grouping']['ggdetails'][] = ['gid' => $members['groupid'],
                'gname' => $groupmember,
                'gtype' => 'group',
                'memberspictures' => $members['user_pictures'],
                'fileicon' => html_writer::link($urlshared, $iconimage,
                    array('target' => '_blank', 'id' => 'shared_link_url_' . $members['groupid'])),
                'sharingstatus' => html_writer::start_div('',
                    ["id" => 'status_col_' . $members['groupid']]) . html_writer::end_div(),
            ];
        }

        // Get the groupings details.
        foreach ($j->c as $c => $condition) {
            if ($condition->type == 'grouping') {
                $groupingprops = groups_get_grouping($condition->id, 'id, name');
                $groupingdetails = $this->grouping_details_helper_function($types, $groupingprops);

                foreach ($groupingdetails as $gg) {
                    $data['group_grouping']['ggdetails'][] = ['gid' => $gg['groupingid'],
                        'gname' => $gg['groupingdname'],
                        'gtype' => $condition->type,
                        'memberspictures' => $gg['memberspictures'],
                        'fileicon' => $gg['fileicon'],
                        'sharingstatus' => html_writer::start_div('',
                            ["id" => 'status_col_' . $gg['groupingid']]) . html_writer::end_div(),
                        'status'
                    ];
                }
            }
        }

        echo $OUTPUT->render_from_template('mod_googledocs/group_grouping_table', $data);
    }

    private function grouping_details_helper_function($types, $gropingproperties) {
        global $CFG, $DB, $OUTPUT;

        $icon = $types[get_file_type_from_string($this->googledocs->document_type)]['icon'];
        $imgurl = new moodle_url($CFG->wwwroot . '/mod/googledocs/pix/' . $icon);
        $iconimage = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

        $members = groups_get_grouping_members($gropingproperties->id);
        $memberspictures = '';
        $data = [];
        $urlshared = '';

        foreach ($members as $member) {
            $memberspictures .= $OUTPUT->user_picture($member, array('course' => $this->courseid,
                'includefullname' => false, 'class' => 'userpicture'));
        }

        if ($this->created) {
            $conditions = ['googledocid' => $this->googledocs->id, 'groupingid' => $gropingproperties->id];
            $urlshared = $DB->get_field('googledocs_files', 'url', $conditions, IGNORE_MISSING);
        }

        $data[$gropingproperties->name] = ['groupingid' => $gropingproperties->id,
            'groupingdname' => $gropingproperties->name,
            'fileicon' => html_writer::link($urlshared, $iconimage,
                array('target' => '_blank', 'id' => 'shared_link_url_' . $gropingproperties->id)), $iconimage,
            'sharingstatus' => html_writer::start_div('',
                ["id" => 'file_grouping']) . html_writer::end_div(),
            'memberspictures' => $memberspictures,
        ];

        return $data;
    }

    /**
     * Fetches data from the DB needed to render a table when the type of distribution
     * is std_copy
     * @global type $DB
     * @global type $USER
     * @return array
     */
    public function query_db() {

        global $DB, $USER;
        $picturefields = user_picture::fields('u');

        $studentrecords = '';

        if (has_capability('mod/googledocs:view', $this->context)
            && is_enrolled($this->context, $USER->id, '', true) && !is_siteadmin()
            && !has_capability('mod/googledocs:viewall', $this->context)
            && $this->googledocs->distribution == 'std_copy') {

            list($rawdata, $params) = $this->query_student_file_view($picturefields);
        } else {

            if ($this->created) {
                list($rawdata, $params) = $this->queries_get_students_list_created($picturefields);
                $studentrecords = $DB->get_records_sql($rawdata, $params);

            } else {

                $studentrecords = $this->queries_get_students_list_processing();
                return array($studentrecords);
            }
        }

        return $studentrecords;
    }

    /**
     * Return the number of groups for a particular course
     * @global type $DB
     * @param type $courseid
     * @return type
     */
    private function get_course_group_number($courseid) {

        global $DB;
        $sql = " SELECT count(*)
                FROM  mdl_groups AS gr
                INNER JOIN mdl_googledocs as gd on gr.courseid = gd.course
                WHERE gd.course = :courseid;";

        return $DB->count_records_sql($sql, array('courseid' => $courseid));
    }

    /**
     * Fetch the groups and the members of them.
     * @global type $DB
     * @return type
     */
    private function get_groups_and_members() {
        global $DB, $OUTPUT;

        $groups = get_groups_details_from_json(json_decode($this->googledocs->group_grouping_json));

        $j = json_decode($this->googledocs->group_grouping_json);
        $groupids = [];
        $groupmembers = [];
        $i = 0;

        foreach ($groups as $group) {
            $groupids[$i] = $group->id;
            $i++;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($groupids);

        $sql = "SELECT  id, name FROM mdl_groups
                WHERE id  $insql";

        $groupsresult = $DB->get_records_sql($sql, $inparams);
        $user_pictures = '';

        foreach ($groupsresult as $gr) {

            $members = groups_get_members($gr->id, $fields = 'u.*', $sort = 'firstname ASC');
            if (empty($members)) {
                continue;
            }

            foreach ($members as $member) {
                $user_pictures .= $OUTPUT->user_picture($member,
                    array('course' => $this->courseid, 'includefullname' => false, 'class' => 'userpicture'));
            }

            $groupmembers[$gr->name] = ['groupid' => $gr->id,
                'user_pictures' => $user_pictures,
                'groupmembers' => $members
            ];

            $user_pictures = '';
        }

        return $groupmembers;
    }

    private function set_data_for_grouping_table($groupingmembers) {
        global $OUTPUT;
        $i = 0;
        $data = [];

        foreach ($groupingmembers as $member) {

            $data [] = ['picture' => $OUTPUT->user_picture($member, array('course' => $this->courseid,
                    'includefullname' => true,
                    'class' => 'userpicture ')),
                'fullname' => fullname($member),
                'status' => html_writer::start_div('', ["id" => 'file_' . $i]) . html_writer::end_div(),
                'student-id' => $member->id,
                'student-email' => $member->email
            ];
            $i++;
        }

        return $data;
    }

    /**
     * Returns an array with the group name, id and the members
     * belonging to the group with its members.
     * @global type $DB
     * @param type $groupids
     * @return array
     */
    private function get_grouping_groups_and_members($groupingid) {
        global $DB, $OUTPUT;
        list($insql, $inparams) = $DB->get_in_or_equal($groupingid);
        $groupmembers = [];

        $sql = "SELECT  groupid FROM mdl_groupings_groups
                WHERE groupingid  $insql";

        $ggresult = $DB->get_records_sql($sql, $inparams);
        $url = "#";
        $user_pictures = '';
        foreach ($ggresult as $gg) {

            $group = groups_get_group($gg->groupid);
            $gmembers = groups_get_members($gg->groupid, 'u.*', $sort = 'firstname ASC');

            foreach ($gmembers as $gmember) {
                $user_pictures .= $OUTPUT->user_picture($gmember, array('course' => $this->courseid,
                    'includefullname' => false, 'class' => 'userpicture'));
            }

            if ($this->created) {
                $conditions = ['googledocid' => $this->instanceid, 'groupid' => $gg->groupid, 'groupingid' => $groupingid];
                $url = $DB->get_field('googledocs_files', 'url', $conditions, IGNORE_MISSING);
            }

            $groupmembers [] = ['groupid' => $gg->groupid,
                'group_name' => $group->name,
                'user_pictures' => $user_pictures,
                'url' => $url,
                'groupmembers' => $this->set_data_for_grouping_table($gmembers)];
            $user_pictures = '';
        }

        return $groupmembers;
    }

    /**
     * This query fetches the student file info
     * to display when a student clicks on the name of the file in a course.
     */
    private function query_student_file_view($picturefields) {
        global $USER, $DB;
        $usergroups = groups_get_user_groups($this->courseid, $USER->id);

        if ($this->googledocs->distribution == "group_copy" && !empty($usergroups)
            || $this->googledocs->distribution == "std_copy_group"
            || $this->googledocs->distribution == "std_copy_grouping") {

            foreach ($usergroups as $ug => $groups) {
                $a = $groups;
            }

            list($insql, $inparams) = $DB->get_in_or_equal($a);
            $sql = "SELECT url FROM mdl_googledocs_files WHERE groupid  $insql";
            $r = $DB->get_records_sql($sql, $inparams);

            return array("", "", $r);
        } else {

            $rawdata = "SELECT u.id, DISTINCT $picturefields, u.firstname, u.lastname, gf.name, gf.url
                        FROM mdl_user as u
                        INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                        WHERE gf.googledocid = ? AND u.id = ?
                        ORDER BY  u.firstname";

            $params = array($this->googledocs->id, $USER->id);

            return array($rawdata, $params);
        }
    }

    /*
     * This queries are executed when the table view corresponds to
     * a set of files already created.
     */

    private function queries_get_students_list_created($picturefields) {

        switch ($this->googledocs->distribution) {
            case 'group_copy':
                list($rawdata, $params) = $this->query_get_students_list_created_by_group_grouping($picturefields);
                break;
            case 'std_copy' :
                list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'std_copy_group' :
                list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'dist_share_same_group':
                list($rawdata, $params) = $this->query_get_student_list_created_by_dist_share_same_group_copy($picturefields);
                break;
            case 'std_copy_grouping':
                list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'grouping_copy':
                list($rawdata, $params) = $this->query_get_students_list_created_by_group_grouping($picturefields, true);
                break;
            case 'dist_share_same_grouping':
                list($rawdata, $params) = $this->query_get_student_list_created_by_dist_share_same_group_copy($picturefields);
                break;
            case 'dist_share_same' :
                list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'std_copy_group_grouping':
                list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'group_grouping_copy':
                list($rawdata, $params) = $this->query_get_students_list_created_by_group_grouping($picturefields);
                break;
            case 'dist_share_same_group_grouping':
                list($rawdata, $params) = $this->query_get_student_list_created_by_dist_share_same_group_copy($picturefields);
            default:
                break;
        }

        return array($rawdata, $params);
    }

    private function query_get_students_list_created_by_group_grouping($picturefields, $grouping = false) {

        $countgroups = $this->get_course_group_number($this->courseid);

        $j = json_decode($this->googledocs->group_grouping_json);

        if ($countgroups > 0 || !(empty($j->c))) {
            if (!$grouping) {

                $rawdata = "SELECT  DISTINCT $picturefields, u.id, u.firstname, u.lastname, gf.url, gd.name, gm.groupid,
                            gr.name as 'Group', gd.course as  'COURSE ID'
                            FROM mdl_user as u
                            INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                            INNER JOIN mdl_googledocs as gd on gf.googledocid = gd.id
                            INNER JOIN mdl_groups_members as gm on gm.userid = u.id
                            INNER JOIN mdl_groups as gr on gr.id = gm.groupid and gr.courseid = gd.course
                            WHERE gd.course = ? AND gd.id = ?  AND (gf.name like '{$this->googledocs->name }_%'
                                                OR gf.name like '{$this->googledocs->name }')";
                $params = array($this->courseid, $this->googledocs->id);
            } else {

                $rawdata = "SELECT  DISTINCT gf.id, $picturefields, gf.url,  gm.groupid
                                FROM mdl_user as u
                                INNER JOIN mdl_groups_members as gm on gm.userid = u.id
                                INNER JOIN mdl_groupings_groups as gg ON gg.groupid = gm.groupid
                                INNER JOIN mdl_googledocs_files as gf on gf.groupid = gm.groupid and gf.groupingid = gg.groupingid
                                WHERE gf.googledocid = ?";

                $params = array($this->googledocs->id);
            }

            return array($rawdata, $params);
        }
    }

    /**
     *
     * @param type $picturefields
     * @return type
     */
    private function query_get_students_list_created($picturefields) {

        $rawdata = "SELECT  DISTINCT gf.id, $picturefields, u.firstname, u.lastname, gf.name, gf.url as url, gf.groupid,
                    gf.permission, u.id
                    FROM mdl_user as u
                    JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                    WHERE gf.googledocid = ?  ORDER BY u.lastname "; // TODO: VALIDATE IN SQL SERVER.

        $params = array($this->instanceid);
        return array($rawdata, $params);
    }

    private function query_get_student_list_created_by_dist_share_same_group_copy($picturefields) {

        $rawdata = "SELECT DISTINCT $picturefields, gm.groupid, gf.url as url FROM mdl_groups_members AS gm
                    INNER JOIN mdl_user AS u ON gm.userid = u.id
                    INNER JOIN mdl_googledocs_files as gf ON gf.groupid = gm.groupid
                    WHERE gf.googledocid = ?
                    -- GROUP BY u.id;";

        $params = array($this->instanceid);

        return array($rawdata, $params);
    }

    /**
     * Fetch the students that are going to get a file
     * @param type $picturefields
     * @param type $countgroups
     * @return type
     */
    private function queries_get_students_list_processing() {

        $j = json_decode($this->googledocs->group_grouping_json);
        $countgroups = $this->get_course_group_number($this->courseid);
        $students = [];

        if ($countgroups == 0 || empty($j->c)) { // TODO: std_copy  test course with and without gruops.
            return $this->coursestudents;
        } else if ($this->googledocs->distribution != 'dist_share_same_grouping'
            && $this->googledocs->distribution != 'grouping_copy'
            && $this->googledocs->distribution != 'std_copy_grouping') {
            $students = $this->get_students_by_group($this->coursestudents, $this->googledocs->group_grouping_json,
                $this->googledocs->course);
        } else if ($this->googledocs->distribution == 'dist_share_same_group_grouping') {
            $studentsingroups = $this->get_students_by_group($this->coursestudents, $this->googledocs->group_grouping_json,
                $this->googledocs->course);
            $studentsingroupings = $this->get_students_by_grouping($this->coursestudents,
                $this->googledocs->group_grouping_json, $this->googledocs->course);

            $students = array_merge($studentsingroups, $studentsingroupings($id));
        } else {
            $students = $this->get_students_by_grouping($this->coursestudents, $this->googledocs->group_grouping_json,
                $this->googledocs->course);
        }

        return $students;
    }

    /* Returns the students in a group.
     *
     * @param type $coursestudents
     * @param type $conditionjson
     * @param type $courseid
     * @return array stdClass
     */

    private function get_students_by_group($coursestudents, $conditionjson, $courseid) {

        $groupmembers = get_group_members_ids($conditionjson, $courseid);

        foreach ($coursestudents as $student) {
            if (in_array($student->id, $groupmembers)) {
                $student->groupid = get_students_group_ids($student->id, $courseid);
                $students[] = $student;
            }
        }
        return $students;
    }

    private function get_students_by_grouping($coursestudents, $conditionjson, $courseid) {

        $groupmembers = get_grouping_members_ids($conditionjson, $courseid);

        foreach ($coursestudents as $student) {
            if (in_array($student->id, $groupmembers)) {
                $studentgroupingids = array_keys(groups_get_user_groups($this->courseid, $student->id));
                unset($studentgroupingids[array_key_last($studentgroupingids)]);
                $student->groupid = implode('-', $studentgroupingids);
                $students[] = $student;
            }
        };

        return $students;
    }

    /**
     * Get the URL(S) the student can access to when rendering distr. by grouping.
     * @global type $DB
     * @param type $studentid
     */
    private function get_grouping_file_url($studentid) {
        global $DB;

        $sql = "SELECT gf.url as url
                FROM mdl_googledocs_files AS gf
                INNER JOIN mdl_groupings_groups AS gg ON gf.groupingid = gg.groupingid
                INNER JOIN mdl_groups_members AS gm ON gg.groupid = gm.groupid
                WHERE gf.googledocid = {$this->instanceid} AND gm.userid = {$studentid}";

        $r = $DB->get_records_sql($sql);
        return $r;
    }

    private function get_students_files_url($groupsandmembers) {
        global $DB;

        foreach ($groupsandmembers as $groupmember => $members) {
            foreach ($members['groupmembers'] as $member) {
                $conditions = ['googledocid' => $this->instanceid, 'userid' => $member->id];
                $url = $DB->get_field('googledocs_files', 'url', $conditions, IGNORE_MISSING);
                $member->url = $url;
            }
        }

        return $groupsandmembers;
    }

    public function view_grading_summary() {
        global $OUTPUT;

        $participants = count_students($this->googledocs->id);
        $urlparams = array('id' => $this->cm->id, 'action' => 'grading', 'fromsummary' => 'fs');
        $url = new moodle_url('/mod/googledocs/view.php?', $urlparams);
        $submitted = count_submitted_files($this->googledocs->id);
        $data = ['title' => $this->googledocs->name,
            'participants' => $participants,
            'submitted' => $submitted,
            'needsgrading' => $submitted,
            'url' => $url,
            'viewgrading' => get_string('viewgrading', 'googledocs')
        ];

        echo $OUTPUT->render_from_template('mod_googledocs/grading_summary', $data);
    }

    /**
     * View entire grading page.
     *
     * @return string
     */
    public function view_grading_table() {
        global $OUTPUT, $DB;

        $picturefields = user_picture::fields('u');
        $sql = "SELECT $picturefields, u.username
                FROM mdl_googledocs_files as gf INNER JOIN mdl_user as u ON gf.userid = u.id
                WHERE googledocid = {$this->googledocs->id}";
        $users = $DB->get_records_sql($sql);


        $submitted = count_submitted_files($this->googledocs->id);

        $url = new moodle_url('/mod/googledocs/view.php?action=grading&id' . $this->cm->id . 'tifirst');
        $data = ['docname' => $this->googledocs->name,
            'cmid' => $this->cm->id,
            'title' => get_string('title', 'googledocs'),
            'class' => 'firstinitial',
            'current' => 'A',
            'all' => get_string('all', 'googledocs'),
            'group' => $this->get_alphabet(),
            'courseid' => $this->googledocs->course,
            'googledocid' => $this->googledocs->id,
            'maxgrade' => $this->googledocs->grade,
        ];

        foreach ($users as $user) {

            $sql = "SELECT * FROM mdl_googledocs_submissions WHERE userid = :userid
                    AND googledoc = :instanceid";
            $params = ['userid' => $user->id, 'instanceid' => $this->googledocs->id];

            $submitted = $DB->get_record_sql($sql, $params);

            $sql = "SELECT *, grades.grade as gradevalue FROM mdl_googledocs_grades as grades
                    INNER JOIN mdl_googledocsfeedback_comments as comments ON grades.id = comments.grade
                    WHERE grades.userid = :userid and grades.googledocid = :instanceid;";

            $grading = $DB->get_record_sql($sql, $params);

            $userprofile = new \moodle_url('/user/profile.php', array('id' => $user->id));
            $link = html_writer::tag('a', $user->firstname . ' ' . $user->lastname, array('href' => $userprofile));

            $urlparams = array('id' => $this->cm->id, 'action' => 'grading', 'fromsummary' => 'fs', 'userid' => $user->id);
            $grade = new moodle_url('/mod/googledocs/view_grading_app.php?', $urlparams);

            $urlparams = ['id' => $this->cm->id,
                'action' => 'grader',
                'userid' => $user->id
            ];
            $gradeurl = new moodle_url('/mod/googledocs/view_grading_app.php?', $urlparams);

            $data['users'][] = ['picture' => $OUTPUT->user_picture($user, array('course' => $this->courseid,
                    'includefullname' => false, 'class' => 'userpicture')),
                'fullname' => $link,
                'email' => $user->email,
                'userid' => $user->id,
                'gradeurl' => $gradeurl,
                'username' => $user->username,
                'submited' => $submitted != false ? true : $submitted,
                'grade' => $grade,
                'gradegiven' => (!empty($grading) ? $grading->gradevalue : ''),
                'comment' => (!empty($grading) ? $grading->commenttext : ''),
                'lastmodified' => (!empty($grading) ? date('l, d F Y, g:i A', $grading->timemodified) : '-')
            ];
        }

        echo $OUTPUT->render_from_template('mod_googledocs/grading_table', $data);
    }

    public function view_grading_app($userid) {
        global $OUTPUT, $DB, $CFG, $COURSE;

        $user = $DB->get_record('user', array('id' => $userid));
        $sql = " SELECT url FROM mdl_googledocs_files
                 WHERE userid = {$userid} and googledocid = {$this->googledocs->id};";
        $url = $DB->get_record_sql($sql);
        $isfolder =  $this->googledocs->document_type == GDRIVEFILETYPE_FOLDER;

        list($gradegiven, $commentgiven) = get_grade_comments($this->googledocs->id, $userid);

        // Get data from gradebook.
        $sql = "SELECT * FROM mdl_grade_grades as gg
                WHERE itemid = (SELECT id as itemid FROM mdl_grade_items
                                WHERE iteminstance = {$this->googledocs->id}
                                AND itemtype = 'mod' AND itemmodule = 'googledocs' )
                AND userid = {$userid}";
        $gg = $DB->get_record_sql($sql);

        $lockedoroverriden = false;
        $gradefromgradebook = 0;
        $gradebookurl = '';
        if ($gg && ($gg->locked != "0" || $gg->overridden != "0")) {
            $lockedoroverriden = true;
            $gradefromgradebook = $gg->finalgrade;
            $gradebookurl = new moodle_url($CFG->wwwroot . '/grade/report/grader/index.php?', ['id' => $COURSE->id]);
        }

        $data = ['userid' => $userid,
            'courseid' => $this->courseid,
            'showuseridentity' => true,
            'coursename' => $this->context->get_context_name(),
            'cmid' => $this->cm->id,
            'name' => $this->googledocs->name,
            'caneditsettings' => false,
            'actiongrading' => 'grading',
            'viewgrading' => get_string('viewgrading', 'googledocs'),
            'googledocid' => $this->googledocs->id,
            'usersummary' => $OUTPUT->user_picture($user, array('course' => $this->courseid, 'includefullname' => true, 'class' => 'userpicture')),
            'useremail' => $user->email,
            'fileurl' =>  $isfolder ? get_formated_folder_url($url->url) : $url->url,
            'maxgrade' => $this->googledocs->grade,
            'gradegiven' => $gradegiven,
            'graded' => ($gradegiven == '') ? false : true,
            'commentgiven' => $commentgiven,
            'users' => $this->get_list_participants($this->googledocs->id),
            'lockedoroverriden' => $lockedoroverriden,
            'finalgrade' => number_format($gradefromgradebook, 2),
            'gradebookurl' => $gradebookurl,
            'display' => true,
            'contextid' => $this->context->id,
        ];

        echo $OUTPUT->render_from_template('mod_googledocs/grading_app', $data);
    }

    private function get_alphabet() {

        foreach (range('A', 'Z') as $letter) {
            $group ['letter'][] = ['name' => $letter,
                'url' => new moodle_url('/mod/googledocs/view.php?action=grading&id' . $this->cm->id . 'tifirst=' . $letter)
            ];
        }

        return $group;
    }

    private function get_status_style($isready, $permission, $graded) {

        if ($graded) {
            return ['Graded', ucfirst($permission), 'status-access'];
        }
        if ($isready && !$graded) {
            return ['Submitted', 'View', 'status-access'];
        } else if (!$isready) {
            return ['Created', ucfirst($permission), 'status-access'];
        }
    }

    private function get_list_participants($googledocid) {
        global $DB; // By selecting the user id first, you avoid the duplicate warning.
        $sql = "SELECT u.id, CONCAT(u.firstname,' ', u.lastname) as fullname, gf.* FROM mdl_googledocs_files as gf
                JOIN mdl_user as u ON gf.userid = u.id
                WHERE googledocid = :googledocid ORDER BY u.lastname;";

        $participants = $DB->get_records_sql($sql, array('googledocid' => $googledocid));
        $users = [];

        foreach ($participants as $participant) {
            $user = new \stdClass();
            $user->userid = $participant->userid;
            $user->fullname = $participant->fullname;
            list($user->grade, $user->comment) = get_grade_comments($googledocid, $participant->userid);
            $users [] = $user;
        }

        return $users;
    }
}
