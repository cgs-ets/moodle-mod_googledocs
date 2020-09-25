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
require_once($CFG->dirroot . '/mod/googledocs/locallib.php');

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

    protected $picturefields;


   public function __construct($courseid, $selectall, $context, $instanceid, $googledocs, $created = true){

        $this->selectall = $selectall;
        $this->context = $context;
        $this->courseid = $courseid;
        $this->currentgroup = 0;
        $this->context = $context;
        $this->instanceid = $instanceid;
        $this->googledocs = $googledocs;
        $this->created = $created;
        $this->picturefields = user_picture::fields('u');

        $this->coursestudents = get_role_users(5, $this->context, false,'u.*');

   }


    /**
     * Renders table with all files already created.
     * @global type $OUTPUT
     * @global type $CFG
     * @global type $PAGE
     */
    public function render_table() {
        global  $USER;

        $types = google_filetypes();
        $is_student = false;

        if (has_capability('mod/googledocs:view', $this->context) &&
            is_enrolled($this->context, $USER->id, '', true) && !is_siteadmin()
            && !has_capability('mod/googledocs:viewall', $this->context) ) {
            $is_student = true;
        }

        $this->render($types, $is_student);

    }

    //Helper function
    private function render($types, $is_student) {
       global $USER;
       $students = $this->query_db();
       $usergroups =  groups_get_user_groups($this->courseid, $USER->id);


      switch ($this->googledocs->distribution) {
        case 'std_copy':
            if ($this->created && $is_student) {
                $this->render_files_for_student($types);
            }else if(!$this->created){
                $this->render_table_by_students_files_processing($types,  $students, $this->googledocs->distribution);
            }else{
                $this->render_table_by_students_files_created($types,  $students, $this->googledocs->distribution);
            }
        break;

        case 'std_copy_group_copy':
            if ($this->created && $is_student) {
              $this->render_files_for_student($types);
            }else if(!$this->created){
               $this->render_table_by_students_files_processing($types,  $students, $this->googledocs->distribution);
            }else{
               $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
            }
        break;

        case 'std_copy_grouping_copy':
            if ($this->created && $is_student) {
                $this->render_files_for_student($types);
            }else if(!$this->created){
                $this->render_table_by_students_files_processing($types, $students, $this->googledocs->distribution);
            }else{
                $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
            }
        break;

        case 'dist_share_same':
            if ($this->created && $is_student) {
               $this->render_files_for_student($types);
            }else if(!$this->created){
              $this->render_table_by_students_files_processing($types, $students, $this->googledocs->distribution);
            }else{
               $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
            }
        break;

        case 'dist_share_same_group_copy':
            if ($this->created && $is_student) {
                $this->render_files_for_students_in_groups($types, $usergroups);
            }else if(!$this->created){
              $this->render_table_by_students_files_processing($types, $students, $this->googledocs->distribution);
            }else{
                $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
            }
        break;

        case 'dist_share_same_grouping_copy':
            if($this->created && $is_student){
                $this->render_files_for_students_in_groups($types, $usergroups);
            }else if(!$this->created){
               $this->render_table_by_students_files_processing($types, $students, $this->googledocs->distribution);
            }else{
               $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
            }
        break;

        case 'group_copy':

            if ($this->created && $is_student) {
               $this->render_files_for_students_in_groups($types, $usergroups);
            }else{
                $this->render_table_by_group($types);
            }
        break;

        case 'grouping_copy':
            if($this->created && $is_student) {
                $this->render_files_for_students_in_groups($types, $usergroups);
            }else{
                $this->render_table_by_grouping($types);
            }
        break;

        case 'group_grouping_copy':
           if ($this->created && $is_student) {
               $this->render_files_for_students_in_groups($types, $usergroups);
            }else{
                $this->render_table_by_group($types);
            }
            break;

        case 'std_copy_group_grouping_copy':
            if($this->created && $is_student) {
                $this->render_files_for_student_by_group_grouping($types, $usergroups);
            }else if(!$this->created){
                $this->render_table_by_students_files_processing($types, $students, $this->googledocs->distribution);
            }else{
                $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
            }
        break;

        case 'dist_share_same_group_grouping_copy' :
            if ($this->created && $is_student) {
                $this->render_files_for_students_in_groups($types, $usergroups);
            }else if(!$this->created){
              $this->render_table_by_students_files_processing($types, $students, $this->googledocs->distribution);
            }else{
                $this->render_table_by_students_files_created($types, $students, $this->googledocs->distribution);
            }
            break;

    }
   }


    private function render_work_in_progress(){
        global $OUTPUT;
        echo $OUTPUT->render_from_template('mod_googledocs/work_in_progress', '');
    }

    private function render_files_for_student($types) {
        global $DB, $USER, $CFG;

        $sql = "SELECT url FROM mdl_googledocs_files WHERE userid = :userid AND googledocid = :instanceid";
        $params = ['userid' => $USER->id, 'instanceid' => $this->googledocs->id];

        $result = $DB->get_records_sql($sql, $params);

          foreach($result as $r) {
            $extra = "onclick=\"this.target='_blank';\"";
            $icon = $types[get_doc_type_from_string($r->url)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            print_string('clicktoopen', 'url', "<a href=\"{$r->url}\"$extra><img class='link_icon'src='{$imgurl}'></a>");
            echo '<br>';
        }

    }
    /**
     * When dist. is by group, the record doesnt keep a 1 to 1 relationship with the user id
     */
    private function render_files_for_student_by_group_grouping($types, $usergroups) {
        global $DB, $USER, $CFG;
         global $CFG, $DB;
        $a = $usergroups[0]; // Has all the groups this user belongs to

        list($insql, $inparams) = $DB->get_in_or_equal($a);

        $sql = "SELECT url FROM mdl_googledocs_files
                WHERE groupid  $insql  AND googledocid = {$this->instanceid} AND userid = {$USER->id}";

        $result = $DB->get_records_sql($sql, $inparams);

        foreach($result as $r) {
            $extra = "onclick=\"this.target='_blank';\"";
            $icon = $types[get_doc_type_from_string($r->url)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            print_string('clicktoopen', 'url', "<a href=\"{$r->url}\"$extra><img class='link_icon'src='{$imgurl}'></a>");
            echo '<br>';
        }
    }
    private function render_files_for_students_in_groups($types, $usergroups = null){
       global $CFG, $DB;
       $a = $usergroups[0]; // Has all the groups this user belongs to

        list($insql, $inparams) = $DB->get_in_or_equal($a);

        $sql = "SELECT url FROM mdl_googledocs_files
                WHERE groupid  $insql  AND googledocid = {$this->instanceid}";

        $result = $DB->get_records_sql($sql, $inparams);

        foreach($result as $r) {
            $extra = "onclick=\"this.target='_blank';\"";
            $icon = $types[get_doc_type_from_string($r->url)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            print_string('clicktoopen', 'url', "<a href=\"{$r->url}\"$extra><img class='link_icon'src='{$imgurl}'></a>");
            echo '<br>';
        }
    }

    /**
     * Get the information needed to render table when the files are being processed
     * The students array is an an array of array.
     *
     * @param string $types
     * @param array $students
     * @param string $dist
     */
    private function render_table_by_students_files_processing($types, $students, $dist = ''){
        global $OUTPUT, $CFG, $DB;

        $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');
      
        //We need all the group ids
        $group_ids='';
        if($dist == 'dist_share_same_group_copy' || $dist == 'dist_share_same_grouping_copy'
            || $dist == 'dist_share_same_group_grouping_copy') {
            $groups = get_groups_details_from_json(json_decode($this->googledocs->group_grouping_json));
            foreach($groups as $group) {
                $group_ids .= '-' . $group->id;
            }

           $group_ids = ltrim($group_ids, '-');
        }

        $data = ['googledocid' => $this->googledocs->docid,
                 'instanceid' => $this->googledocs->id,
                 'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false ,
                 'members' => array(),
                 'show_group' => false,
                 'owneremail' => $owneremail->email,
                 'all_groups' => $group_ids
                ];
        $i = 0;

        foreach($students as $st) {

           foreach($st as $student) {

             $checkbox = new \core\output\checkbox_toggleall('students-file-table', false, [
                'classes' => 'usercheckbox m-1',
                'id' => 'user' . $student->id,
                'name' => 'user' .$student->id,
                'checked' => false,
                'label' => get_string('selectitem', 'moodle', $student->firstname),
                'labelclasses' => 'accesshide',
            ]);

            $picture = $OUTPUT->user_picture($student, array('course' => $this->courseid,
                'includefullname' => true, 'class' =>'userpicture'));
            $icon = $types[get_doc_type_from_string($this->googledocs->document_type)]['icon'];
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$icon);
            $image = html_writer::empty_tag('img', array('src' => $imgurl, 'class' => 'link_icon'));
            $links =  html_writer::link('#', $image, array('target' => '_blank',
                              'id'=>'link_file_'. $i));

            $student->group = $this->get_students_group_names($student->id, $this->courseid);

            $data['students'][] = ['checkbox' => $OUTPUT->render($checkbox),
                                   'picture' => $picture,
                                   'fullname' =>  fullname($student),
                                   'student-id' => $student->id,
                                   'student-email'=>$student->email,
                                   'link' => $links,
                                   'student-groupid' => isset($student->groupid) ? $student->groupid : '',
                                   'status' => html_writer::start_div('', ["id"=>'file_'. $i]).html_writer::end_div()
                ];

                $i++;
        }
    }
        echo $OUTPUT->render_from_template('mod_googledocs/student_table', $data);
    }

    /**
     * Get the information needed to render table when the files are already created.
     * The students array is an array of objects.
     *
     * @param type $types
     * @param array $students
     * @param type $dist
     */
    private function render_table_by_students_files_created($types, $students, $dist = ''){
        global $OUTPUT, $CFG, $DB;
       $owneremail = $DB->get_record('user', array('id' => $this->googledocs->userid), 'email');

        //We need all the group ids
        $group_ids='';
        if($dist == 'dist_share_same_group_copy' || $dist == 'dist_share_same_grouping_copy') {
            $groups = get_groups_details_from_json(json_decode($this->googledocs->group_grouping_json));
            foreach($groups as $group) {
                $group_ids .= '-' . $group->id;
            }

           $group_ids = ltrim($group_ids, '-');
        }

        $data = ['googledocid' => $this->googledocs->docid,
                 'instanceid' => $this->googledocs->id,
                 'from_existing' => ($this->googledocs->use_document == 'existing') ? true : false ,
                 'members' => array(),
                 'show_group' => false,
                 'owneremail' => $owneremail->email,
                 'all_groups' => $group_ids
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


            $student->group = $this->get_students_group_names($student->id, $this->courseid);
            // If a student belongs to more than one group, it can get more than one file. render all

            $urls = explode(",", $student->url);
            $links = '';
            foreach($urls as $url ){
                $links .= html_writer::link($url, $image, array('target' => '_blank',
                              'id'=>'link_file_'. $i));
            }

            $data['students'][] = ['checkbox' => $OUTPUT->render($checkbox),
                                   'picture' => $picture,
                                   'fullname' =>  fullname($student),
                                   'student-id' => $student->id,
                                   'student-email'=>$student->email,
                                   'link' => $links,
                                   'student-groupid' => isset($student->groupid) ? $student->groupid : '',
                                   'status' => html_writer::start_div('', ["id"=>'file_'. $i]).html_writer::end_div()
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
                                  'user_pictures' => $members['user_pictures'],
                                  'fileicon' => html_writer::link($urlshared, $iconimage, array('target' => '_blank','id'=>'shared_link_url_'. $members['groupid'])),
                                  'sharing_status' => html_writer::start_div('', ["id"=>'status_col']).html_writer::end_div(),
                                 ];

            foreach($members['groupmembers'] as $member) {

                $url = isset($member->url) ? $member->url : '#';

                $data['groups'][] =  ['fullname' =>  fullname($member),
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
                                            'grouping_id' => $groupingdetails->id,
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
        $studentrecords ='';

        if (has_capability('mod/googledocs:view', $this->context) &&
            is_enrolled($this->context, $USER->id, '', true) && !is_siteadmin()
            && !has_capability('mod/googledocs:viewall', $this->context) && $this->googledocs->distribution == 'std_copy') {

            list($rawdata, $params) = $this->query_student_file_view($picturefields);

        }else {
            if($this->created) {
                list($rawdata, $params) = $this->queries_get_students_list_created($picturefields);

                if($this->googledocs->distribution == 'grouping_copy' && $this->created) {
                    $studentrecords = $DB->execute($rawdata, $params);
                }else{
                    $studentrecords = $DB->get_records_sql($rawdata, $params);
                }
            }else{
               $studentrecords = $this->queries_get_students_list_processing($countgroups);
               return array($studentrecords);
            }
        }

        return  $studentrecords;
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
        global $DB, $OUTPUT;

        $groups = get_groups_details_from_json(json_decode($this->googledocs->group_grouping_json));

        $j = json_decode($this->googledocs->group_grouping_json);
        $groupids = [];
        $groupmembers = [];
        $i= 0;

        foreach($groups as $group) {
            $groupids[$i] =  $group->id;
            $i++;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($groupids);

        $sql = "SELECT  id, name FROM mdl_groups
                WHERE id  $insql";

        $groupsresult  =  $DB->get_records_sql($sql, $inparams);
        $user_pictures='';
        foreach ($groupsresult as $gr) {
            $members = groups_get_members($gr->id, $fields='u.*', $sort='firstname ASC');

            if(empty($members)) {
                continue;
            }
            foreach($members as $member) {

                $user_pictures .= $OUTPUT->user_picture($member, array('course' => $this->courseid, 'includefullname' => false, 'class' =>'userpicture' ));

            }
            $groupmembers[$gr->name] = [ 'groupid' => $gr->id,
                                         'user_pictures' => $user_pictures,
                                         'groupmembers' => $members];


            $user_pictures = '';
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
        global $DB, $OUTPUT;
        list($insql, $inparams) = $DB->get_in_or_equal($groupingid);
        $groupmembers = [];

        $sql = "SELECT  groupid FROM mdl_groupings_groups
                WHERE groupingid  $insql";

        $ggresult  =  $DB->get_records_sql($sql, $inparams);
        $url = "#";
        $user_pictures = '';
        foreach ($ggresult as $gg) {

            $group = groups_get_group($gg->groupid);
            $gmembers = groups_get_members($gg->groupid,'u.*', $sort='firstname ASC');

            foreach($gmembers as $gmember) {
                $user_pictures .= $OUTPUT->user_picture($gmember, array('course' => $this->courseid, 'includefullname' => false, 'class' =>'userpicture' ));
            }

            if($this->created){
                $conditions =['googledocid' => $this->instanceid, 'groupid' => $gg->groupid, 'groupingid' => $groupingid];
                $url =  $DB->get_field('googledocs_files', 'url', $conditions,  IGNORE_MISSING);
            }


            $groupmembers []= [ 'groupid' => $gg->groupid,
                                'group_name' => $group->name,
                                'user_pictures' => $user_pictures,
                                'url' => $url,
                                'groupmembers' => $this->set_data_for_grouping_table($gmembers) ];
            $user_pictures ='';
        }


       return $groupmembers;
    }

    /**
     * This query fetches the student file info
     * to display when a student clicks on the name of the file in a course.
     */
    private function query_student_file_view($picturefields) {
        global $USER, $DB;
        $usergroups =  groups_get_user_groups($this->courseid, $USER->id);


        if ($this->googledocs->distribution == "group_copy" && !empty($usergroups) ||
            $this->googledocs->distribution == "std_copy_group_copy" ||  $this->googledocs->distribution == "std_copy_grouping_copy") {
            foreach($usergroups as $ug=>$groups){
                $a = $groups;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($a);

            $sql = "SELECT url FROM mdl_googledocs_files
                    WHERE groupid  $insql";

            $r = $DB->get_records_sql($sql, $inparams);
            return array("", "", $r);
        }else{

            $rawdata = "SELECT DISTINCT $picturefields, u.firstname, u.lastname, gf.name, gf.url
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
    private function queries_get_students_list_created($picturefields){

        switch ($this->googledocs->distribution) {
            case 'group_copy':
                list($rawdata, $params) = $this->query_get_students_list_created_by_group_grouping($picturefields);
                break;
            case 'std_copy' :
                list($rawdata, $params) =  $this->query_get_students_list_created($picturefields);
                break;
            case 'std_copy_group_copy' :
                list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'dist_share_same_group_copy':
                list($rawdata, $params) = $this->query_get_student_list_created_by_dist_share_same_group_copy($picturefields);
                break;
            case 'std_copy_grouping_copy':
                list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'grouping_copy':
                  list($rawdata, $params) = $this->query_get_students_list_created_by_group_grouping($picturefields, true);
                break;
            case 'dist_share_same_grouping_copy':
                 list($rawdata, $params) = $this->query_get_student_list_created_by_dist_share_same_group_copy($picturefields);
                break;
            case 'dist_share_same' :
                  list($rawdata, $params) =  $this->query_get_students_list_created($picturefields);
                break;
            case 'std_copy_group_grouping_copy':
                 list($rawdata, $params) = $this->query_get_students_list_created($picturefields);
                break;
            case 'group_grouping_copy':
                  list($rawdata, $params) = $this->query_get_students_list_created_by_group_grouping($picturefields);
                break;
            case 'dist_share_same_group_grouping_copy':
                 list($rawdata, $params) = $this->query_get_student_list_created_by_dist_share_same_group_copy($picturefields);
            default:
                break;
        }
        //var_dump($rawdata); exit;
        return array($rawdata, $params);

    }
    private function query_get_students_list_created_by_group_grouping ($picturefields, $grouping = false) {

        $countgroups = $this->get_course_group_number($this->courseid);

        $j = json_decode($this->googledocs->group_grouping_json);

           if ( $countgroups > 0 || !(empty($j->c))) {
               if(!$grouping) {

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

               }else{

                    $rawdata = "SELECT  DISTINCT gf.id, $picturefields, gf.url,  gm.groupid
                                FROM mdl_user as u
                                INNER JOIN mdl_groups_members as gm on gm.userid = u.id
                                INNER JOIN mdl_groupings_groups as gg ON gg.groupid = gm.groupid
                                INNER JOIN mdl_googledocs_files as gf on gf.groupid = gm.groupid and gf.groupingid = gg.groupingid
                                WHERE gf.googledocid = ?";

                    $params = array($this->googledocs->id);
                }
             //print_object($rawdata); exit;
             return array($rawdata, $params);
        }
    }

    /**
     *
     * @param type $picturefields
     * @return type
     */
    private function query_get_students_list_created($picturefields){

        $rawdata = "SELECT DISTINCT $picturefields, u.id, u.firstname, u.lastname, gf.name, group_concat(gf.url) AS url, gf.groupid
                    FROM mdl_user as u
                    INNER JOIN mdl_googledocs_files  as gf on u.id  = gf.userid
                    WHERE gf.googledocid = ?
                    GROUP BY u.id";

            /**
             * SQL SERVER QUERY: DONT DELETE
             * SELECT u.id, u.firstname,  url = STUFF((
                                                    SELECT ',' + f.url
                                                    FROM mdl_googledocs_files f

                                                    WHERE  f.userid = u.id
                                                    FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, '')
                FROM mdl_user as u
                INNER JOIN  mdl_googledocs_files as gf
                ON u.id = gf.userid;
             */



            $params = array($this->instanceid);

            return array($rawdata, $params);
    }

    private function query_get_student_list_created_by_dist_share_same_group_copy($picturefileds) {

        $rawdata = "SELECT DISTINCT $picturefileds, gm.groupid, group_concat(gf.url) as url FROM mdl_groups_members AS gm
                    INNER JOIN mdl_user AS u ON gm.userid = u.id
                    INNER JOIN mdl_googledocs_files as gf ON gf.groupid = gm.groupid
                    WHERE gf.googledocid = ?
                    GROUP BY u.id;";
        $params = array($this->instanceid);

        return array($rawdata, $params);
    }

    /**
     * Fetch the students that are going to get a file
     * @param type $picturefields
     * @param type $countgroups
     * @return type
     */
    private function queries_get_students_list_processing($countgroups) {

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
                $student->groupid = get_students_group_ids($student->id, $courseid);
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
     * TODO: It ws requested not to show the column. Delete when the code goes to prod.
     *
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