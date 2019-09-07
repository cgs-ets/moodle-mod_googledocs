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

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_googledocs
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_googledocs_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        require_once($CFG->libdir . '/google/lib.php');
        $client = get_google_client();
        // if (!$client->is_logged_in()) {
        //     $googleauthlink = $client->display_login_button();
        //     $mform->addElement('static', '', '', $googleauthlink);
        //     // $mform->addElement('static', 'needauthentication', '', get_string('needauthentication', 'googledocs'). '<br/>'. $googleauthlink);
        // }

        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'use_document', '', get_string('create_new', 'googledocs'), 'new');
        $radioarray[] = $mform->createElement('radio', 'use_document', '', get_string('use_existing', 'googledocs'), 'existing');
        $mform->addGroup($radioarray, 'document_choice', get_string('use_document', 'googledocs'), array(' '), false);
        $mform->setDefault('use_document', 'new');
        // $mform->addHelpButton('document_choice', 'document_choice_help', 'googledocs');

        $mform->addElement('text', 'doc_name', get_string('document_name', 'googledocs'), array('size' => '64'));
        $mform->setType('doc_name', PARAM_TEXT);
        // $mform->addRule('doc_name', null, 'required', null, 'client');
        $mform->addRule('doc_name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->hideif('doc_name', 'use_document', 'eq', 'existing');
        // $mform->addHelpButton('doc_name', 'doc_name_help', 'googledocs');

        $types = google_filetypes();
        $typesarray = array();
        foreach($types as $key => $type) {
            $imgurl = new moodle_url($CFG->wwwroot.'/mod/googledocs/pix/'.$type['icon']);
            $image = html_writer::empty_tag('img', array('src' => $imgurl, 'style' => 'width:30px;')) . '&nbsp;';
            $typesarray[] = $mform->createElement('radio', 'document_type', '', $image.$type['name'], $type['mimetype']);
        }
        $mform->addGroup($typesarray, 'document_type', get_string('document_type', 'googledocs'), array(' '), false);
        $mform->setDefault('document_type', $types['doc']['mimetype']);
        $mform->hideif('document_type', 'use_document', 'eq', 'existing');
        // $mform->addHelpButton('document_type', 'document_type_help', 'googledocs');

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
        // $mform->addHelpButton('permissions', 'permissions_help', 'googledocs');

        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'distribution', '', get_string('each_gets_own', 'googledocs'), 'each_gets_own');
        $radioarray[] = $mform->createElement('radio', 'distribution', '', get_string('all_share', 'googledocs'), 'all_share');
        $mform->addGroup($radioarray, 'distribution_choice', get_string('distribution', 'googledocs'), array(' '), false);
        $mform->setDefault('distribution', 'each_gets_own');
        // $mform->addHelpButton('distribution_choice', 'distribution_choice_help', 'googledocs');

        // $rawfilepermissions = gdrive_filepermissions();
        // foreach($rawfilepermissions  as $key => $rawfilepermission) {
        //     $listfilepermissions[$key] = $rawfilepermission['name'];
        // }
        // $mform->addElement('select', 'gdrivepermissions', get_string('gdrivepermissions', 'googledocs'), $listfilepermissions);
        // $mform->setDefault('gdrivepermissions', GDRIVEFILEPERMISSION_AUTHER_STUDENTS_RC);

        // $mform->addElement('hidden', 'display');
        // $mform->setType('display', PARAM_INT);
        // $mform->setDefault('display', 0);
        // $mform->addElement('hidden', 'displayoptions');
        // $mform->setType('displayoptions', PARAM_TEXT);
        // $mform->setDefault('displayoptions', '');
        // $mform->addElement('hidden', 'course');
        // $mform->setType('course', PARAM_INT);
        // $mform->setDefault('course', $COURSE->id);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validating Entered url, we are looking for obvious problems only,
        // teachers are responsible for testing if it actually works.

        // This is not a security validation!! Teachers are allowed to enter "javascript:alert(666)" for example.

        // NOTE: do not try to explain the difference between URL and URI, people would be only confused...

        if (!empty($data['gdriveurl'])) {
            $url = $data['gdriveurl'];
            if (preg_match('|^/|', $url)) {
                // links relative to server root are ok - no validation necessary

            } else if (preg_match('|^[a-z]+://|i', $url) or preg_match('|^https?:|i', $url) or preg_match('|^ftp:|i', $url)) {
                // normal URL
                if (!googledocs_appears_valid_url($url)) {
                    $errors['gdriveurl'] = get_string('invalidurl', 'url');
                }

            } else if (preg_match('|^[a-z]+:|i', $url)) {
                // general URI such as teamspeak, mailto, etc. - it may or may not work in all browsers,
                // we do not validate these at all, sorry

            } else {
                // invalid URI, we try to fix it by adding 'http://' prefix,
                // relative links are NOT allowed because we display the link on different pages!
                if (!googledocs_appears_valid_url('http://'.$url)) {
                    $errors['gdriveurl'] = get_string('invalidurl', 'url');
                }
            }
        }
        return $errors;
    }
}
