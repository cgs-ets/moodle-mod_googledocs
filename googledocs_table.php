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

    protected $coursestudents;

    protected $created;

    protected $bygroup;

    public function __construct($courseid, $selectall, $context, $instanceid, $googledocs, $created = true, $bygroup = false) {

        global $OUTPUT, $PAGE;

        $this->selectall = $selectall;
        $this->context = $context;
        $this->courseid = $courseid;
        $this->currentgroup = 0;
        $this->context = $context;
        $this->instanceid = $instanceid;
        $this->googledocs = $googledocs;
        $this->created = $created;
        $this->bygroup = $bygroup;
        $this->coursestudents = get_role_users(5, $this->context, false,'u.*');

        $this->table = new flexible_table('mod-googledocs-files-view' . $courseid);

        if(!$bygroup) {
            $this->table_all_students($this->table, $googledocs);
       }else{
          // $this->table_by_groups($this->table, $googledocs);
       }

        // Set the variables we need to use later.


    }

    /**
     * This table is displayed when the distribution is either "each student gets a copy"
     * or all share same file
     * All
     */
    private function table_all_students($table, $googledocs) {
         global $OUTPUT, $PAGE;

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
                       get_string('groupheader', 'mod_googledocs'),
                        get_string('status', 'mod_googledocs'));

        $table->define_columns($columns);
        $table->define_headers($headers);

        // Make this table sorted by first name by default.
      //  $table->sortable(true, 'firstname');
        //$this->sortable(true, 'group');

        $table->no_sorting('select');
        $table->no_sorting('link');
        $table->no_sorting('status');

        $table->define_baseurl($PAGE->url);
        $table->set_attribute('class', 'overviewTable');
        $table->set_attribute('data-googledoc-id', $googledocs->docid);
        $table->column_style_all('padding', '10px 10px 10px 15px');
        $table->column_style_all('text-align', 'left');
        $table->column_style_all('vertical-align', 'middle');
        $table->column_style('', 'width', '5%');
        $table->column_style('picture', 'width', '5%');
        $table->column_style('fullname', 'width', '15%');
        $table->column_style('sharedurl', 'width', '15%');
        $table->column_style('sharedurl', 'padding', '0');
        $table->column_style('sharedurl', 'text-align', 'center');
        $table->column_style('sharedurl', 'width', '8%');

        $table->setup();
    }

    private function  table_by_groups($table, $googledocs) {
        global $OUTPUT, $PAGE;

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
        $columns = array('','Groups', 'link', 'status');
        $headers = array($OUTPUT->render($mastercheckbox),
                        get_string('groupheader', 'mod_googledocs'),
                        get_string('sharedurl', 'mod_googledocs'),
                        get_string('status', 'mod_googledocs'));

        $table->define_columns($columns);
        $table->define_headers($headers);

        // Make this table sorted by first name by default.
      //  $table->sortable(true, 'firstname');
        //$this->sortable(true, 'group');

        $table->no_sorting('select');
        $table->no_sorting('link');
        $table->no_sorting('status');

        $table->define_baseurl($PAGE->url);
        $table->set_attribute('class', 'overviewTable');
        $table->set_attribute('data-googledoc-id', $googledocs->docid);
        $table->column_style_all('padding', '10px 10px 10px 15px');
        $table->column_style_all('text-align', 'left');
        $table->column_style_all('vertical-align', 'middle');
        $table->column_style('', 'width', '5%');
        $table->column_style('groupheader', 'width', '15%');
        $table->column_style('sharedurl', 'width', '15%');
        $table->column_style('sharedurl', 'padding', '0');
        $table->column_style('sharedurl', 'text-align', 'center');
        $table->column_style('sharedurl', 'width', '8%');

        $table->setup();
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
            list($rawdata, $params) = $this->query_student_file_view($picturefields);
        }else {
            if($this->created) {
                list($rawdata, $params) = $this->queries_get_students_list_created($picturefields);
            }else{
               $studentrecords = $this->queries_get_students_list_processing($picturefields, $countgroups);

               return array(($countgroups > 0), $studentrecords, $student);
            }
        }

        $studentrecords = $DB->get_records_sql($rawdata, $params);
        return array(($countgroups > 0), $studentrecords, $student);
    }


    /**
     * Renders table with all files already created.
     * @global type $OUTPUT
     * @global type $CFG
     * @global type $PAGE
     */
    public function render_table() {
        global $CFG;
        list($hasgroup, $students, $studentview) = $this->query_db(0);
        $types = google_filetypes();
        //$this->render_table_by_group($types); //TODO: BORRAR DESPUES
        if ($studentview) {
            $user = array_values($students);
            $extra = "onclick=\"this.target='_blank';\"";
            $icon = $types[get_doc_type_from_url($user[0]->url)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            print_string('clicktoopen', 'url', "<a href=\"{$user[0]->url}\"$extra><img class='link_icon' src='{$imgurl}'></a>");

        }else if(!$this->bygroup){
              $this->render_table_by_students($types, $hasgroup, $students);
        }else{
            //$this->render_table_by_group($types);
        }

        return  $this->coursestudents;

    }

    private function render_table_by_students($types, $hasgroup, $students){
        global $OUTPUT, $CFG;
        $i = 0;

        foreach($students as $student) {
            $checkbox = new \core\output\checkbox_toggleall('students-file-table', false, [
                'classes' => 'usercheckbox m-1',
                'id' => 'user' . $student->id,
                'name' => 'user' .$student->id,
                'checked' => false,
                'label' => get_string('selectitem', 'moodle', $student->firstname),
                'labelclasses' => 'accesshide',
            ]);

            $picture = $OUTPUT->user_picture($student, array('course' => $this->courseid));
            $namelink = html_writer::link($CFG->wwwroot.'/user/view.php?id='.$student->id.'&course='.$this->courseid,
                fullname($student), array('id' => 'fullname_' . $student->id));

            $icon = $types[get_doc_type_from_url($this->googledocs->document_type)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            $image = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));
            $url = isset($student->url) ? $student->url : '#';

            $student->group = $this->get_students_group_names($student->id, $this->courseid);
            $groupname = !empty($student->group)  ? $student->group : 'No Group';

            $rows[$i] = array('checkbox' => $OUTPUT->render($checkbox),
                            'userid' => $student->id,
                            'firstname' => strtoupper($student->firstname),
                            'lastname' => strtoupper($student->lastname),
                            'picture' => $picture,
                            'fullname' => $namelink,
                            'sharedurl' =>html_writer::link($url, $image, array('target' => '_blank', 'id'=>'link_file_'. $i)),
                            'group' => $groupname,
                            'status' => html_writer::start_div('', ["id"=>'file_'. $i, "data-student-id" => $student->id, "data-student-email"=>$student->email]).html_writer::end_div());

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

    private function render_table_by_group ($types){
        global $OUTPUT, $CFG;
        list($groups, $groupsmembers) = $this->get_groups_and_members();
        $icon = $types[get_doc_type_from_url($this->googledocs->document_type)]['icon'];
        $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
        $iconimage = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

        $data = [
            'groupid' => '',
            'groupname' => '',
            'googledocid' => '',
            'members' => array()
        ];

        foreach($groups as $group) {
           $data['groupid'] = $group->id;
           $data['groupname'] = $group->name;
           $data['googledocid'] = $this->googledocs->docid;
        }

        foreach($groupsmembers as $groupmember=> $members) {
            foreach($members as $member) {
                $data['members'][] = ['picture' => $OUTPUT->user_picture($member, array('course' => $this->courseid)) ,
                                      'fullname' => $member->firstname .' '. $member->lastname,
                                      'link' => $iconimage,
                                      'status' => html_writer::start_div('progress_bar processing').html_writer::end_div(),
                                      'student-id' =>$member->id,
                                      'student-email'=>$member->email

                    ];
            }
        }

 echo $OUTPUT->render_from_template('mod_googledocs/group_table', $data);
exit;


    }
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

    /**
     * Fetch the groups and the members of them.
     * @global type $DB
     * @return type
     */
    private function get_groups_and_members(){
        global $DB;

        $j = json_decode($this->googledocs->group_grouping_json);
        $groupids = [];
        $groupmembers = [];
        $i= 0;

        foreach($j->c as $c =>$condition) {
            if ($condition->type == 'group'){
                $groupids[$i] = $condition->id;
                $i++;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($groupids);

         $sql = "SELECT  id, name FROM mdl_groups
                 WHERE id  $insql";

        $groupsresult  =  $DB->get_records_sql($sql, $inparams);

        foreach ($groupsresult as $gr) {
            $groupmembers[$gr->name] = groups_get_members($gr->id, $fields='u.*', $sort='firstname ASC');

        }

       return array($groupsresult, $groupmembers);
    }

    /*
     * This queries are executed when the table view corresponds to
     * a set of files already created.
     */
    private function queries_get_students_list_created($picturefields){
        global $DB;
        $countgroups = $this->get_course_group_number($this->courseid);
        $j = json_decode($this->googledocs->group_grouping_json);
        if ($countgroups > 0 || !(empty($j->c))) {
            $rawdata = "SELECT  DISTINCT $picturefields, u.id, u.firstname, u.lastname, gf.url, gd.name, gm.groupid,
                        gr.name as 'Group', gd.course as  'COURSE ID'
                        FROM mdl_user as u
                        INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                        INNER JOIN mdl_googledocs as gd on gf.googledocid = gd.id
                        INNER JOIN mdl_groups_members as gm on gm.userid = u.id
                        INNER JOIN mdl_groups as gr on gr.id = gm.groupid and gr.courseid = gd.course
                        WHERE gd.course = ? AND (gf.name like '{$this->googledocs->name }_%'
                                            OR gf.name like '{$this->googledocs->name }')";

             $params = array($this->courseid);

        }else {
            $rawdata = "SELECT DISTINCT $picturefields, u.id, u.firstname, u.lastname, gf.name, gf.url
                        FROM mdl_user as u
                        INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                        WHERE gf.googledocid = ?
                        ORDER BY  u.firstname";

            $params = array($this->instanceid);
        }

         return array($rawdata, $params);

    }

    /**
     * Fetch the students that are going to get a file
     * @param type $picturefields
     * @param type $countgroups
     * @return type
     */
    private function queries_get_students_list_processing($picturefields, $countgroups) {

       $j = json_decode($this->googledocs->group_grouping_json);

       if($countgroups == 0 || empty($j->c)) {
           return  $this->coursestudents;
       }else{
          $students = $this->get_students_by_group($this->coursestudents, $this->googledocs->group_grouping_json,
                $this->googledocs->course);

            return $students;
       }
    }

    /**
     * Returns the students in a group.
     *
     * @param type $coursestudents
     * @param type $conditionjson
     * @param type $courseid
     * @return array stdClass
     */
    private function  get_students_by_group($coursestudents, $conditionjson, $courseid){

            $groupmembers = get_group_grouping_members_ids($conditionjson, $courseid);
            $i=0;
            foreach ($coursestudents as $student) {

                if(in_array($student->id, $groupmembers)){
                    $students[$i] = $student;
                    $i++;
                }
            }
            return $students;
    }

    /**
     * Filter the name of the group the student belongs to.
     * This information fills the Groups column.
     * @param type $userid
     * @param type $courseid
     * @return string
     */
    private function get_students_group_names($userid, $courseid) {
        $usergroups =  groups_get_user_groups($courseid, $userid);
        $groupnames = '';
        foreach($usergroups as $usergroup => $ug)  {
            $names = array_merge_recursive($usergroups[$usergroup]);
        }

        foreach($names as $name) {
            $groupnames .= ' ' . groups_get_group_name($name);
        }

        return $groupnames;
    }

    /**
     * This query fetches the student file info
     * to display when a student clicks on the name of the file in a course.
     */
    private function query_student_file_view($picturefields) {
        global $USER;
        $rawdata = "SELECT DISTINCT $picturefields, u.firstname, u.lastname, gf.name, gf.url
                    FROM mdl_user as u
                    INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                    WHERE gf.googledocid = ? AND u.id = ?
                    ORDER BY  u.firstname";

            $params = array($this->googledocs->id, $USER->id);

        return array($rawdata, $params);
    }


}