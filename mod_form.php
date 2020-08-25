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
 * The main googledocs configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_googledocs
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


require_once(__DIR__ . '/locallib.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/course/lib.php');

/**
 * Module instance settings form
 *
 * @package    mod_googledocs
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_googledocs_mod_form extends moodleform_mod {


   /* public function __construct($current, $section, $cm, $course) {
       parent::__construct($current, $section, $cm, $course);
       moodleform::__construct('fileprocessingtableview.php');
    }*/
    /**
     * Defines forms elements.
     */
    public function definition() {
       global $CFG, $PAGE;

       // Add the javascript required to enhance this mform.
       $PAGE->requires->js_call_amd('mod_googledocs/processing_control', 'init');
       $update = optional_param('update', 0, PARAM_INT);
       $course_groups = groups_get_all_groups($PAGE->course->id);
       // Start the instance config form.
       $mform = $this->_form;
       $mform->addElement('header', 'general', get_string('general', 'form'));

       // Get the Google Drive object.
       $client = new googledrive($this->context->id);

       // Check whether the user is logged into their Google account.
        if (!$client->check_google_login()) {

            // Print the login button.
            $button = $client->display_login_button();
            $mform->addElement('static', '', '', $button);

            // Add empty standard elements with only a cancel button.
            $this->standard_hidden_coursemodule_elements();
            $mform->addElement('hidden', 'completionunlocked', 0);
            $mform->setType('completionunlocked', PARAM_INT);
            $this->add_action_buttons(true, false, false);

        } else {

            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'use_document', '', get_string('create_new', 'googledocs'), 'new');
            $radioarray[] = $mform->createElement('radio', 'use_document', '', get_string('use_existing', 'googledocs'), 'existing');
            $use_document = $mform->addGroup($radioarray, 'document_choice', get_string('use_document', 'googledocs'), array(' '), false);
            $mform->setDefault('use_document', 'new');
            // $mform->addHelpButton('document_choice', 'document_choice_help', 'googledocs');
            $mform->addElement('text', 'name', get_string('document_name', 'googledocs'), array('size' => '64'));
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $mform->hideif('name', 'use_document', 'eq', 'existing');
            //$mform->addHelpButton('name', 'name_help', 'googledocs');

            if($update != 0) {
                $use_document->freeze();
            }
            $types = google_filetypes();
            $typesarray = array();
            foreach($types as $key => $type) {
                $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$type['icon']);
                $image = html_writer::empty_tag('img', array('src' => $imgurl, 'style' => 'width:30px;')) . '&nbsp;';
               $doctype = $mform->createElement('radio', 'document_type', '', $image.$type['name'], $type['mimetype']);

               if($update) {
                   $doctype->freeze();
               }
                $typesarray[] = $doctype;
            }

            $mform->addGroup($typesarray, 'document_type', get_string('document_type', 'googledocs'), array(' '), false);
            $mform->setDefault('document_type', $types['document']['mimetype']);

            $mform->hideif('document_type', 'use_document', 'eq', 'existing');
            // $mform->addHelpButton('document_type', 'document_type_help', 'googledocs');

            if($update != 0) {
                $doctype->freeze();
            }
            $mform->addElement('text', 'google_doc_url', get_string('google_doc_url', 'googledocs'), array('size'=>'64'));
            $mform->setType('google_doc_url', PARAM_RAW_TRIMMED);
            $mform->addRule('google_doc_url', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $mform->hideif('google_doc_url', 'use_document', 'eq', 'new');
            //$mform->addRule('externalurl', null, 'required', null, 'client');

            $permissions = array(
                'edit' => get_string('edit', 'googledocs'),
                'comment' => get_string('comment', 'googledocs'),
                'view' => get_string('view', 'googledocs'),
            );
            $mform->addElement('select', 'permissions', get_string('permissions', 'googledocs'), $permissions);
            $mform->setDefault('permissions', 'edit');

            if(!empty($course_groups)){
                $distribution = array(
                'std_copy' => get_string('dist_student_copy', 'googledocs'),
                'group_copy' => get_string('dist_group_copy', 'googledocs'),
                'dist_share_same' => get_string('dist_all_share_same', 'googledocs'));
            }else{
                $distribution = array(
                'std_copy' => get_string('dist_student_copy', 'googledocs'),
                'dist_share_same' => get_string('dist_all_share_same', 'googledocs'));
            }

            $distselect = $mform->addElement('select', 'distribution', get_string('distribution', 'googledocs'), $distribution);
           // $mform->setDefault('distribution', 'std_copy');
/* poner cuando este andando
            if($update != 0 ) { //&& ($this->get_current()->distribution == 'std_copy' || $this->get_current()->distribution == 'group_copy')
                $mform->addHelpButton('distribution_choice', 'distribution_choice', 'googledocs');
                $distselect->freeze();

            } */


            // Groups
            $groups = array('0' => 'All Groups');
            foreach($course_groups as $g) {
                // skip empty groups.
                if(!groups_get_members($g->id, 'u.id')) {
                    continue;
                }
                 $groups[$g->id] = $g->name;
            }

            if(!empty($course_groups)){
                $selectgroups = $mform->addElement('select', 'groups', get_string('groups', 'googledocs'), $groups);
                //$mform->setDefault('groups','0');
                $selectgroups->setMultiple(true);
            }

            // Grouping.
            $course_grouping = groups_get_all_groupings($PAGE->course->id);
            $grouping = array('0' => 'All Groupings');


            foreach($course_grouping as $g) {
                // Only list those groupings with groups in it.
                if(empty(groups_get_grouping_members($g->id))){
                    continue;
                }
                $grouping[$g->id] = $g->name;
            }

            if(!empty($course_groups)){
                $selectgrouping = $mform->addElement('select', 'groupings', get_string('groupings', 'googledocs'), $grouping);
                //$mform->setDefault('groupings', '0');
                $selectgrouping->setMultiple(true);
            }



            // Add standard buttons, common to all modules.
            $this->standard_coursemodule_elements();
            $this->add_action_buttons(true, null,false);




        }
    }

    /**
     * Validates forms elements.
     */
    function validation($data, $files) {
        global $PAGE;
        // Validating doc URL if sharing an existing doc.

         //   var_dump($data); exit;
        if ((isset($data['use_document'])) && $data['use_document'] == 'existing') {
            $data['name'] = '_';

            if(empty($data['google_doc_url'])) {
                $errors['google_doc_url'] = get_string('urlempty', 'googledocs');
            } else if (!googledocs_appears_valid_url($data['google_doc_url'])) {
                $errors['google_doc_url'] = get_string('urlinvalid', 'googledocs');
            }
        }
        //When creating from an existing file there is no file name to provide.
        //If this sentence is executed first,the validation fails.
        $errors = parent::validation($data, $files);

        return $errors;
    }

//    protected function apply_admin_locked_flags(): void {
//        global $PAGE;
//         // Add the javascript required to enhance this mform.
//        $PAGE->requires->js_call_amd('mod_googledocs/controls', 'init', array(true, true));
//        parent::apply_admin_locked_flags();
//    }




}