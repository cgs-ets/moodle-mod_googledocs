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
 * Contains the class used for the displaying the participants table.
 *
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/user/lib.php');

defined('MOODLE_INTERNAL') || die;

/**
 * Class for the displaying the googledocs  table.
 * Based on participants_table.pho
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class googledocs_table extends \flexible_table {
/**
     * @var int $courseid The course id
     */
    protected $courseid;

    /**
     * @var int|false False if groups not used, int if groups used, 0 for all groups.
     */
    protected $currentgroup;
    /**
     *
     * @var int instance
     */
    protected $instanceid;


    /**
     * @var int $roleid The role we are including, 0 means all enrolled users
     */
    protected $roleid;

    /**
     * @var int $enrolid The applied filter for the user enrolment ID.
     */
    protected $enrolid;


    /**
     * @var bool $selectall Has the user selected all users on the page?
     */
    protected $selectall;


    /**
     * @var \stdClass[] The list of groups with membership info for the course.
     */
    protected $groups;


    /**
     * @var \stdClass $course The course details.
     */
    protected $course;

    /**
     * @var  context $context The course context.
     */
    protected $context;

    /**
     *
     * @var stdClass
     */
    protected $googledocs;
    /**
     *
     * @var flexible_table $table
     */
    protected $table;

    public function __construct($courseid, $selectall, $context, $instanceid, $googledocs) {

        global $OUTPUT, $PAGE;

        //parent::__construct('mod-googledocs-files-view' . $courseid);

        $this->selectall = $selectall;
        $this->context = $context;
        $this->courseid = $courseid;

        $this->table = new flexible_table('mod-googledocs-files-view' . $courseid);

       // if ($bulkoperations) {
          $mastercheckbox = new \core\output\checkbox_toggleall('students-file-table', true, [
                'id' => 'select-all-student-files',
                'name' => 'select-all-students-files',
                'label' => $this->selectall ? get_string('deselectall') : get_string('selectall'),
                'labelclasses' => 'sr-only',
                'classes' => 'm-1',
                'checked' => false,//$this->selectall
        ]);

     //   }

         // Define the headers and columns.
        $columns = array('','picture', 'fullname', 'link', 'group', 'status');
        $headers = array($OUTPUT->render($mastercheckbox),
                        '',
                        get_string('fullname'),
                        get_string('sharedurl', 'mod_googledocs'),
                        get_string('group', 'mod_googledocs'),
                        get_string('status', 'mod_googledocs'));

        $this->table->define_columns($columns);
        $this->table->define_headers($headers);

        // Make this table sorted by first name by default.
      //  $this->table->sortable(true, 'firstname');
        //$this->sortable(true, 'group');

        $this->table->no_sorting('select');
        $this->table->no_sorting('link');
        $this->table->no_sorting('status');

        $this->table->define_baseurl($PAGE->url);
        $this->table->set_attribute('class', 'overviewTable');
        $this->table->column_style_all('padding', '10px 10px 10px 15px');
        $this->table->column_style_all('text-align', 'left');
        $this->table->column_style_all('vertical-align', 'middle');
        $this->table->column_style('', 'width', '5%');
        $this->table->column_style('picture', 'width', '5%');
        $this->table->column_style('fullname', 'width', '15%');
       // $this->table->column_style('sharedurl', 'width', '50px');
        $this->table->column_style('sharedurl', 'width', '15%');
        $this->table->column_style('sharedurl', 'padding', '0');
        $this->table->column_style('sharedurl', 'text-align', 'center');
        $this->table->column_style('sharedurl', 'width', '8%');
        $this->table->setup();

        // Set the variables we need to use later.
        $this->currentgroup = 0;
        $this->context = $context;
        $this->instanceid = $instanceid;
        $this->googledocs = $googledocs;
    }


    /**
     * Override
     *
     * @param \stdClass $data
     * @return string
     */
    public function query_db($pagesize, $useinitialsbar = true) {

        global $DB, $USER;
        $picturefields = user_picture::fields('u');
        $countgroups = $this->get_course_group_number($this->courseid);
        $student = false;

        if (has_capability('mod/googledocs:view', $this->context) &&
            is_enrolled($this->context, $USER->id, '', true) && !is_siteadmin()
            && !has_capability('mod/googledocs:viewall', $this->context)) {
            $student = true;
            $rawdata = "SELECT DISTINCT $picturefields, u.firstname, u.lastname, gf.name, gf.url
                        FROM mdl_user as u
                        INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                        WHERE gf.googledocid = ? AND u.id = ?
                        ORDER BY  u.firstname";

            $params = array($this->googledocs->id, $USER->id);

        }else if ($countgroups > 0){

            $rawdata = "SELECT  DISTINCT $picturefields, u.id, u.firstname, u.lastname, gf.url, gd.name, gm.groupid,
                        gr.name as 'Group', gd.course as  'COURSE ID'
                        FROM mdl_user as u
                        INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                        INNER JOIN mdl_googledocs as gd on gf.googledocid = gd.id
                        INNER JOIN mdl_groups_members as gm on gm.userid = u.id
                        INNER JOIN mdl_groups as gr on gr.id = gm.groupid and gr.courseid = gd.course
                        WHERE gd.course = ? AND gf.name like '{$this->googledocs->name }_%'";

              $params = array($this->courseid);

        } else{

            $rawdata = "SELECT DISTINCT $picturefields, u.id, u.firstname, u.lastname, gf.name, gf.url
                        FROM mdl_user as u
                        INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                        WHERE gf.googledocid = ?
                        ORDER BY  u.firstname";

            $params = array($this->instanceid);
        }

        $studentrecords = $DB->get_records_sql($rawdata, $params);
        return array(($countgroups > 0), $studentrecords, $student);
    }

    public function render_table() {

        global $OUTPUT, $CFG;
        list($hasgroup, $students, $studentview) = $this->query_db(0);

        $types = google_filetypes();
        $i = 0;

        if ($studentview) {
            $user = array_values($students);
            $extra = "onclick=\"this.target='_blank';\"";
            $icon = $types[get_doc_type_from_url($user[0]->url)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            print_string('clicktoopen', 'url', "<a href=\"{$user[0]->url}\"$extra><img class='link_icon' src='{$imgurl}'></a>");

        }else{

            foreach($students as $student) {

                $checkbox = new \core\output\checkbox_toggleall('students-file-table', false, [
                    'classes' => 'usercheckbox m-1',
                    'id' => 'user' . $student->id,
                    'name' => 'user' .$student->id,
                    'checked' => false,//$this->selectall,
                    'label' => get_string('selectitem', 'moodle', $student->firstname),
                    'labelclasses' => 'accesshide',
                ]);

                $picture = $OUTPUT->user_picture($student, array('course' => $this->courseid));
                $namelink = html_writer::link($CFG->wwwroot.'/user/view.php?id='.$student->id.'&course='.$this->courseid,
                    fullname($student));
                $icon = $types[get_doc_type_from_url($student->url)]['icon'];
                $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
                $image = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

                $rows[$i] = array('checkbox' => $OUTPUT->render($checkbox),
                                  'userid' => $student->id,
                                  'firstname' => strtoupper($student->firstname),
                                  'lastname' => strtoupper($student->lastname),
                                  'picture' => $picture,
                                  'fullname' => $namelink,
                                  'sharedurl' => html_writer::link($student->url, $image, array('target' => '_blank')),
                                  'group' => $hasgroup ? $student->group : 'No Group',
                                  'status' => 'Created (?)');

                    $rowdata = array($rows[$i]['checkbox'],
                                     $rows[$i]['picture'],
                                     $rows[$i]['fullname'],
                                     $rows[$i]['sharedurl'],
                                     $rows[$i]['group'],
                                     $rows[$i]['status']);

                    $this->table->add_data($rowdata);

                $i++;
            }
            $this->table->print_html();
        }

    }

    /**
     * Render the participants table.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Whether to use the initials bar which will only be used if there is a fullname column defined.
     * @param string $downloadhelpbutton
     */
   /* public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $PAGE;

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);

        /*if (has_capability('moodle/course:enrolreview', $this->context)) {
            $params = ['contextid' => $this->context->id, 'courseid' => $this->course->id];
            $PAGE->requires->js_call_amd('core_user/status_field', 'init', [$params]);
        }
    }*/

    /**
     * Return the number of groups for a particular course
     * @global type $DB
     * @param type $courseid
     * @return type
     */
    private function get_course_group_number($courseid){

        global $DB;
        $sql =" SELECT count(*)
                FROM  mdl_groups AS gr
                INNER JOIN mdl_googledocs as gd on gr.courseid = gd.course
                WHERE gd.course = :courseid;";

        return $DB->count_records_sql($sql, array('courseid' => $courseid));

    }

}