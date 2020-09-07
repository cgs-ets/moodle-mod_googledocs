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
 *
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/user/lib.php');

defined('MOODLE_INTERNAL') || die;

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
   *
   * @var stdClass
   */
    protected $googledocs;
    /**
     * @var array
     */
    protected $coursestudents;
    /**
     *
     * @var boolean
     */
    protected $created;
    /**
     *
     * @var boolean
     */
    protected $bygroup;

    protected $bygrouping;

   public function __construct($courseid, $selectall, $context, $instanceid, $googledocs, $created = true, $bygroup = false, $bygrouping = false){

        $this->selectall = $selectall;
        $this->context = $context;
        $this->courseid = $courseid;
        $this->currentgroup = 0;
        $this->context = $context;
        $this->instanceid = $instanceid;
        $this->googledocs = $googledocs;
        $this->created = $created;
        $this->bygroup = $bygroup;
        $this->bygrouping = $bygrouping;
        $this->coursestudents = get_role_users(5, $this->context, false,'u.*');

   }


    /**
     * Renders table with all files already created.
     * @global type $OUTPUT
     * @global type $CFG
     * @global type $PAGE
     */
    public function render_table() {
        global $CFG;
        list($hasgroup, $students, $studentview) = $this->query_db();
        $types = google_filetypes();

        if ($studentview) {
            $user = array_values($students);
            $extra = "onclick=\"this.target='_blank';\"";
            $icon = $types[get_doc_type_from_string($user[0]->url)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            print_string('clicktoopen', 'url', "<a href=\"{$user[0]->url}\"$extra><img class='link_icon' src='{$imgurl}'></a>");

        }else if (!$this->bygroup && !$this->bygrouping){
            $this->render_table_by_students($types, $hasgroup, $students);
        }else if ($this->bygroup) {
            $this->render_table_by_group($types);
        }else if ($this->bygrouping){
            $this->render_table_by_grouping($types);
        }else{
            //Render both group and grouping. It will go first.
        }

        return  $this->coursestudents;

    }

    private function render_table_by_students($types, $hasgroup, $students){
        global $OUTPUT, $CFG;


        $data = ['googledocid' => $this->googledocs->docid,
                 'instanceid' => $this->googledocs->id,
                 'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false ,
                 'members' => array()
                ];
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

            $picture = $OUTPUT->user_picture($student, array('course' => $this->courseid, 'includefullname' => true, 'class' =>'userpicture'));
            $icon = $types[get_doc_type_from_string($this->googledocs->document_type)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            $image = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));
            $url = isset($student->url) ? $student->url : '#';

            $student->group = $this->get_students_group_names($student->id, $this->courseid);

            if(!empty($student->group)) {
                $groupname = trim($student->group);
                $student->groupid = groups_get_group_by_name($this->courseid, $groupname);
            }else{
                $groupname ='No Group';
                $student->groupid = null;
            }

            $data['students'][] = ['checkbox' => $OUTPUT->render($checkbox),
                                   'picture' => $picture,
                                   'fullname' =>  fullname($student),
                                   'student-id' => $student->id,
                                   'student-email'=>$student->email,
                                   'link' => html_writer::link($url, $image, array('target' => '_blank',
                                                                                    'id'=>'link_file_'. $i)),
                                   'groupname' => $groupname,
                                   'student-groupid' => $student->groupid,
                                   'status' => html_writer::start_div('', ["id"=>'file_'. $i]).html_writer::end_div(),

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
    private function render_table_by_group ($types){

        global $OUTPUT, $CFG, $DB;
        $groupsandmembers = $this->get_groups_and_members();

        $icon = $types[get_doc_type_from_string($this->googledocs->document_type)]['icon'];
      //  var_dump($icon); exit;
        $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
        $iconimage = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

        //Get teacher email. Is the owner of the copies that are going to be created for each group.
        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');

        $data = [
            'groups' =>array(),
            'googledocid' => '',
            'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false ,
            'owneremail' => $owneremail->email,
        ];

        $data['googledocid'] = $this->googledocs->docid;
        $data['instanceid'] = $this->googledocs->id;

        $urlshared =  '#';

        $i = 0;

        foreach($groupsandmembers as $groupmember=> $members) {

            $conditions =['googledocid' => $this->instanceid, 'groupid' => $members['groupid']];
            $urlshared =  $DB->get_field('googledocs_files', 'url', $conditions,  IGNORE_MISSING);

            $data['groups'][] = ['groupid' => $members['groupid'],
                                  'groupname' => $groupmember,
                                  'fileicon' => html_writer::link($urlshared, $iconimage, array('target' => '_blank','id'=>'shared_link_url_'. $members['groupid'])),
                                  'sharing_status' => html_writer::start_div('', ["id"=>'status_col']).html_writer::end_div()
                                 ];

            foreach($members['groupmembers'] as $member) {

                $url = isset($member->url) ? $member->url : '#';

                $data['groups'][] =  ['picture' => $OUTPUT->user_picture($member, array('course' => $this->courseid, 'includefullname' => true, 'class' =>'userpicture ' )) ,
                                      'fullname' =>  fullname($member),
                                      'link' => html_writer::link($url, $iconimage, array('target' => '_blank','id'=>'link_file_'. $i)),
                                      'status' => html_writer::start_div('', ["id"=>'file_'. $i]).html_writer::end_div(),
                                      'student-id' =>$member->id,
                                      'student-email'=>$member->email,
                                      'groupid' => $members['groupid'],

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
        global $DB, $CFG, $OUTPUT;

        $icon = $types[get_doc_type_from_string($this->googledocs->document_type)]['icon'];
        $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
        $iconimage = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));

        $j = json_decode($this->googledocs->group_grouping_json);
        //Get teacher email. Is the owner of the copies that are going to be created for each group.
        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');

        $data['file_details'] = [ 'owneremail' => $owneremail->email,
                                  'docid' =>$this->googledocs->docid,
                                  'instanceid' =>$this->instanceid,
                                  'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false ,
                                ];

        foreach($j->c as $c =>$condition) {
            if ($condition->type == 'grouping'){
                $groupingdetails = groups_get_grouping($condition->id, 'id, name' );
                $members = $this->get_grouping_groups_and_members($groupingdetails->id);

                if (!empty($members)) {

                    $data['groupings'][] = ['grouping_name' => $groupingdetails->name,
                                            'grouping_id' => $groupingdetails->id,  //grouping id
                                            'fileicon' => $iconimage ,
                                            'sharing_status' => html_writer::start_div('', ["id"=>'file_grouping']).html_writer::end_div(),
                                            'grouping_members' => $members];

                }
            }

        }

        echo $OUTPUT->render_from_template('mod_googledocs/grouping_table', $data);

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
            $members = groups_get_members($gr->id, $fields='u.*', $sort='firstname ASC');

            if(empty($members)) {
                continue;
            }
            $groupmembers[$gr->name] = [ 'groupid' => $gr->id,
                                         'groupmembers' => $members];
        }

       return $groupmembers;
    }

    private function set_data_for_grouping_table($groupingmembers) {
        global $OUTPUT;
        $i = 0;
        $data = [];


        foreach($groupingmembers as $member ){

                $data [] = ['picture' => $OUTPUT->user_picture($member,
                                                array('course' => $this->courseid, 'includefullname' => true, 'class' =>'userpicture ' )) ,
                           'fullname' =>  fullname($member),
                            'status' => html_writer::start_div('', ["id"=>'file_'. $i]).html_writer::end_div(),
                            'student-id' =>$member->id,
                            'student-email'=>$member->email,

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
        global $DB;
        list($insql, $inparams) = $DB->get_in_or_equal($groupingid);
        $groupmembers = [];

        $sql = "SELECT  groupid FROM mdl_groupings_groups
                WHERE groupingid  $insql";

        $ggresult  =  $DB->get_records_sql($sql, $inparams);
        $url = "#";
        foreach ($ggresult as $gg) {

            $group = groups_get_group($gg->groupid);
            $gmembers = groups_get_members($gg->groupid,'u.*', $sort='firstname ASC');

            if($this->created){
                $conditions =['googledocid' => $this->instanceid, 'groupid' => $gg->groupid];
                $url =  $DB->get_field('googledocs_files', 'url', $conditions,  IGNORE_MISSING);
            }

            $groupmembers []= [ 'groupid' => $gg->groupid,
                                'group_name' =>$group->name,
                                'url' => $url,
                                'groupmembers' => $this->set_data_for_grouping_table($gmembers) ];
        }


       return $groupmembers;
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

  /* * Returns the students in a group.
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

    private function get_students_files_url($groupsandmembers){
        global $DB;

        foreach($groupsandmembers as $groupmember=> $members) {
            foreach($members['groupmembers'] as $member) {
                $conditions =['googledocid' => $this->instanceid, 'userid' => $member->id];
                $url =  $DB->get_field('googledocs_files', 'url', $conditions,  IGNORE_MISSING);
                $member->url = $url;
            }
        }

        return $groupsandmembers;
    }








}