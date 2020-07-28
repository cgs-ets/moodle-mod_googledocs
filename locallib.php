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
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot . '/mod/googledocs/googledocs_table.php');


define('GDRIVEFILEPERMISSION_COMMENTER', 'comment'); // Student can Read and Comment.
define('GDRIVEFILEPERMISSION_EDITOR', 'edit'); // Students can Read and Write.
define('GDRIVEFILEPERMISSION_READER', 'view'); // Students can read.
define('GDRIVEFILETYPE_DOCUMENT', 'application/vnd.google-apps.document');
define('GDRIVEFILETYPE_FOLDER', 'application/vnd.google-apps.folder');


/**
 * Google Drive file types.
 *
 * @return array google drive file types.
 * http://stackoverflow.com/questions/11412497/what-are-the-google-apps-mime-types-in-google-docs-and-google-drive#11415443
 * https://developers.google.com/drive/api/v2/ref-roles
 */

function google_filetypes() {
    $types = array (
        'document' => array(
            'name'     => get_string('google_doc', 'mod_googledocs'),
            'mimetype' => 'application/vnd.google-apps.document',
            'icon'     => 'document.svg',
        ),
        'spreadsheets' => array(
            'name'     => get_string('google_sheet', 'mod_googledocs'),
            'mimetype' => 'application/vnd.google-apps.spreadsheet',
            'icon'     => 'spreadsheets.svg',
        ),
        'presentation' => array(
            'name'     => get_string('google_slides', 'mod_googledocs'),
            'mimetype' => 'application/vnd.google-apps.presentation',
            'icon'     => 'presentation.svg',
        ),
    );

    return $types;
}

function get_doc_type_from_url($url) {

    if (strpos($url, 'document')) {
        return 'document';
    } else if (strpos($url, 'spreadsheets')) {
        return 'spreadsheets';
    } else {
        return 'presentation';
    }

}

/**
 * Returns format of the link, depending on the file type
 * @return string
 */
function url_templates() {
    $sharedlink = array();

    $sharedlink['application/vnd.google-apps.document'] = array(
        'linktemplate' => 'https://docs.google.com/document/d/%s/edit?usp=sharing');
    $sharedlink['application/vnd.google-apps.presentation'] = array(
        'linktemplate' => 'https://docs.google.com/presentation/d/%s/edit?usp=sharing');
    $sharedlink['application/vnd.google-apps.spreadsheet'] = array(
        'linktemplate' => 'https://docs.google.com/spreadsheets/d/%s/edit?usp=sharing');
     $sharedlink['application/vnd.google-apps.folder'] = array(
        'linktemplate' => 'https://drive.google.com/drive/folders/%susp=sharing');

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
        return (bool)preg_match('/^[a-z]+:\/\/([^:@\s]+:[^@\s]+@)?[a-z0-9_\.\-]+(:[0-9]+)?(\/[^#]*)?(#.*)?$/i', $url);
    } else {
        return (bool)preg_match('/^[a-z]+:\/\/...*$/i', $url);
    }
}

function oauth_ready() {

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

    private $api_key ;

    /**
     * Constructor.
     *
     * @param int $cmid mod_googledrive instance id.
     * @return void
     */
    public function __construct($cmid, $update = false, $students = false) {
        global $CFG;

        $this->cmid = $cmid;

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
        //$this->client->setApprovalPrompt('force');

        $returnurl = new moodle_url(self::CALLBACKURL);
        $this->client->setRedirectUri($returnurl->out(false));
        //var_dump( json_decode($_SESSION['SESSION']->googledrive_rwaccesstoken)); exit;
        if ($update) {
           $accesstoken = json_decode($_SESSION['SESSION']->googledrive_rwaccesstoken);
           $this->client->refreshToken($accesstoken->refresh_token);
        }

        if($students != null) {
            $this->set_students($students);
        }

        $this->service = new Google_Service_Drive($this->client);

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
    public function display_login_button() {
        // Create a URL that leaads back to the callback() above function on successful authentication.
        $returnurl = new moodle_url('/mod/googledocs/oauth2_callback.php');
        $returnurl->param('callback', 'yes');
        $returnurl->param('cmid', $this->cmid);
        $returnurl->param('sesskey', sesskey());

        // Get the client auth URL and embed the return URL.
        $url = new moodle_url($this->client->createAuthUrl());
        $url->param('state', $returnurl->out_as_local_url(false));

        // Create the button HTML.
        $title = get_string('login', 'repository');
        $link = '<button class="btn-primary btn">'.$title.'</button>';
        $jslink = 'window.open(\''.$url.'\', \''.$title.'\', \'width=600,height=300\'); return false;';
        $output = '<a href="#" onclick="'.$jslink.'">'.$link.'</a>';

        return $output;
    }


    public function read_gdrive_files() {
        // Get the API client and construct the service object.
        //$client = getClient();
        //$service = new Google_Service_Drive($client);

        // Print the names and IDs for up to 10 files.
        $optparams = array(
            'pageSize' => 10,
            'fields' => 'nextPageToken, files(id, name)'
        );
        $results = $this->service->files->listFiles($optparams);

        if (count($results->getFiles()) == 0) {
            print "No files found.\n";
        } else {
            print "Files:\n";
            foreach ($results->getFiles() as $file) {
                printf("%s (%s)\n", $file->getName(), $file->getId());
            }
        }
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
        foreach($students as $student) {
            $this->students[] = $student;
        }

    }

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
    public function share_existing_file(stdClass $mform , $owncopy, $students) {

        try {

            $document_type =  $mform->document_type;
            $url = $mform->google_doc_url;
            $fileid = $this->get_file_id_from_url($url);

            $file = $this->service->files->get($fileid);
            $permissiontype = $mform->permissions;
            $links = array();
            $urlTemplate = url_templates();

            // Set the parent folder.
            $parentdirid = $this->create_folder($file->title);
            $parent = new Google_Service_Drive_ParentReference();
            $parent->setId($parentdirid);

            // The primary role can be either reader or writer.
            // Commenter is an additional role.
            $commenter = false;

            if ($permissiontype == GDRIVEFILEPERMISSION_COMMENTER) {
                $studentpermissions = 'reader';
                $commenter = true;
            } else if ( $permissiontype == GDRIVEFILEPERMISSION_READER) {
                $studentpermissions = 'reader';
            }else{
                $studentpermissions = 'writer';
            }

            if ($owncopy) {
               $links = $this->make_copies($file->id, array($parent), $file->title, $students, $studentpermissions, $commenter, true);
               $sharedlink = sprintf($urlTemplate[$document_type]['linktemplate'], $file->id);
               $sharedfile = array($file, $sharedlink, $links, $parentdirid);
            } else {
                $links = $this->make_copy($file, $parent,$students, $studentpermissions, $commenter);
                $sharedfile = array($links[0], $links[1], $links[2], $parentdirid);
            }

            return $sharedfile;

        } catch (Exception $ex) {
            print "An error occurred: " . $ex->getMessage();
        }

        return null;

    }

    /**
     * Create a new Google drive folder
     * Directory structure: SiteFolder/CourseNameFolder/newfolder
     *
     * @param string $dirname
     * @param array $author
     */
    public function create_folder($dirname, $author = array() ) {
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
        //course folder doesnt exist. Create it inside Site Folder
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

        // Create the folder with the given name
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $dirname,
            'mimeType' => GDRIVEFILETYPE_FOLDER,
            'parents' => array($courseparent),
            'uploadType' => 'multipart'));

        $customdir = $this->service->files->insert($fileMetadata, array('fields' => 'id'));

       return  $customdir->id;
    }
    /**
     * Create a folder in a given parent
     * @param string $dirname
     * @param array $parentid
     * @return string
     */
    public function create_child_folder($dirname, $parentid){
        // Create the folder with the given name.

        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'title' => $dirname,
            'mimeType' => GDRIVEFILETYPE_FOLDER,
            'parents' => $parentid,
            'uploadType' => 'multipart'));

        $customdir = $this->service->files->insert($fileMetadata, array('fields' => 'id'));
       return  $customdir->id;
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
    public function create_file($docname, $gfiletype = GDRIVEFILETYPE_DOCUMENT,
                                      $permissiontype = GDRIVEFILEPERMISSION_WRITER,
                                      $author = array(), $students = array(), $parentid = null, $copy = false) {

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

        try{
            // Create a Google Doc file.
            $fileMetadata = new \Google_Service_Drive_DriveFile(array(
                'title' => $docname,
                'mimeType' => $gfiletype,
                'content' => '',
                'parents'=> array($parent),
                'uploadType' => 'multipart'));
            //In the array, add the attributes you want in the response
            $file = $this->service->files->insert($fileMetadata, array('fields' => 'id, createdDate, shared, title, alternateLink'));

            if (!empty($this->author)) {
                $this->author['type'] = 'user';
                $this->author['role'] = 'owner'; //writer
                $this->insert_permission( $this->service, $file->id ,$this->author['emailAddress'],
                    $this->author['type'], $this->author['role']);
            }

            // Set proper permissions to all students.
            // The primary role can be either reader or writer.
            $commenter = false;
            if ($permissiontype == GDRIVEFILEPERMISSION_COMMENTER) {
                $studentpermissions = 'reader';
                $commenter = true;
            } elseif ($permissiontype == GDRIVEFILEPERMISSION_READER) {
                  $studentpermissions = 'reader';
            }else{
                $studentpermissions = 'writer';

            }
            $links_for_students = array();
            $url = url_templates();

            if($copy){
               $links_for_students = $this->make_copies($file->id, array($parent), $docname, $students, $studentpermissions, $commenter);
            }else{
                //https://developers.googleblog.com/2018/03/discontinuing-support-for-json-rpc-and.html
                //Global HTTP Batch Endpoints (www.googleapis.com/batch) will cease to work on August 12, 2020
                // Give proper permissions to author (teacher).
                if (!empty($this->students)) {
                    foreach ($this->students as $student) {
                        $this->insert_permission($this->service, $file->id, $student['emailAddress'], 'user',$studentpermissions,
                            $commenter);

                        $links_for_students[$student['id']] = array ($file->alternateLink,
                            'filename' => $docname);
                    }
                }
            }
            $sharedlink = sprintf($url[$gfiletype]['linktemplate'], $file->id);
            $sharedfile = array($file, $sharedlink, $links_for_students );

            return $sharedfile;

        } catch (Exception $ex) {
            print "An error occurred: " . $ex->getMessage();
        }
        return null;
    }

    /*
     * Create a copy of the master file when distribution is one for all.
     * Make a copy in the the course folder with the name of the file
     * provide access (permission) to the students.
     */
    private function make_copy( $file, $parent, $students, $studentpermissions, $commenter = false){

        //Make a copy of the original file in folder inside the course folder
        $url = url_templates();
        $copiedfile = new \Google_Service_Drive_DriveFile();
        $copiedfile->setTitle($file->title);
        $copiedfile->setParents(array($parent));
        $copy = $this->service->files->copy($file->id,$copiedfile);

        $links = array ();
        $linktoshare = $copy->selfLink;
        // Insert the permission to the shared file.
        foreach ($students as $student) {
            $this->insert_permission($this->service, $copy->id,
            $student['emailAddress'], 'user', $studentpermissions, $commenter);
            $links[$student['id']] = array($copy->alternateLink, 'filename' => $file->title);
        }
        $copy_details = array ($copy, $copy->alternateLink, $links);


        return $copy_details;

    }
    /**
     * Create  copies of the file with a given $fileid.
     * Save the copies in a folder with the format fileName_Students, to keep the parent folder organised.
     * Assign permissions to the file. This provide access to the students.
     * @param string $fileid
     * @param array $parent
     * @param string $docname
     * @param string $students
     * @param string $studentpermissions
     * @param boolean $commenter
     */
    private function make_copies($fileid, $parent, $docname, $students, $studentpermissions, $commenter = false, $fromexisting = false){
        $links = array();
        $url = url_templates();

        if (!empty($students)) {
            foreach ($students as $student) {
                $copiedfile = new \Google_Service_Drive_DriveFile();
                $copiedfile->setTitle($docname .'_'.$student['displayName']);
                if($fromexisting) {
                    $copiedfile->setParents($parent);
                }
                $copyid = $this->service->files->copy($fileid,$copiedfile);
                $links[$student['id']] = array(sprintf($url[$copyid->mimeType]['linktemplate'], $copyid->id),
                    'filename' => $docname .'_'.$student['displayName']);

                $this->insert_permission($this->service, $copyid->id, $student['emailAddress'], 'user', $studentpermissions, $commenter);
            }
        }

        return $links;
    }

   /**
    * Update the file(s) and folder(s) in Google Drive.
    *
    * @param StdClass $instance
    * @param Object $details submitted data
    * @return \Exception
    */
    public function updates($instance, $details)  {
        global $DB;
        $result = false;

        try {

            $fileId = $instance->docid;
            $parentfolderid = $instance->parentfolderid;

            // Updates the "master" file.
           // $result = $this->update_file_request($fileId, $details);
            $result = $this->update_file_request($parentfolderid,$details);
            if (!$result) { throw  new Exception ("Unable to update file in My Drive.");}

            //Update the name of the folder the master file is in.
            $result = $this->update_file_request($parentfolderid,$details);

            // Update the students files
            $this->update_students_files($instance,$details);

        } catch (Exception $ex) {

            print  $ex->getMessage();
            $error = new stdClass();
            $error->id = $instance->id;
            $error->update_status = $ex->getMessage();

            $DB->update_record('googledocs', $error); // record the error.
            return $ex->getMessage();
        }

        return $result;
    }
    /**
     *  Helper function that calls the different updates a file can have.
     * @global type $DB
     * @param stdClass $instance
     * @param Object $details
     */
    private function update_students_files($instance, $details) {
        global $DB;

        $gf = "SELECT * FROM mdl_googledocs_files WHERE googledocid = :instance_id";
        $result = $DB->get_records_sql($gf, ['instance_id'=> $instance->id]);
        $students = $this->get_enrolled_students($instance->course);

        if ($instance->distribution == 'all_share' && (isset($details->distribution) && $details->distribution == 'all_share')) { //Same name for all
            $this->update_shared_copy($result, $DB, $details, $instance);
        } else if ($instance->distribution == 'all_share' && (isset ($details->distribution) &&
            $details->distribution== 'each_gets_own')){
            $sharedlink = $this->share_existing_file($details, true, $students);
            $this->update_students_links_records($sharedlink[2],  $result);
            $this->update_distribution($DB, $instance);

        } else if($instance->distribution == 'each_gets_own'){
            $this->update_students_copies($result, $DB, $students, $details, $instance);
        }

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
        foreach($result as $r=>$i) {
            $newdata->id = $i->id;
            $newdata->name = $details->name;
            $newdata->update_status = 'updated';
            $newdata->url = $instance->google_doc_url;

            $DB->update_record('googledocs_files', $newdata);
        }
        // Update permissions.
         if ($details->permissions != $instance->permissions ) {
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

        foreach($result as $r=>$i) {

            $fileid = $this->get_file_id_from_url($i->url);
            $newdata->id = $i->id;
            $newdata->name = $details->name .'_'. ($students[$j])['displayName'];
            $resultdetailsupdate  = $this->update_file_request($fileid, $newdata);

            if ($resultdetailsupdate) {
                $newdata->update_status = 'updated';
            }else {
                $newdata->name = $i->name; // Don't update name as there was an error
                $newdata->update_status = 'error';
            }
            $DB->update_record('googledocs_files', $newdata);
            $j++;
        }

       // Update permissions.
        if ($details->permissions != $instance->permissions) {

           $filename = $instance->name;
           $results = $DB->get_records_sql('SELECT * FROM {googledocs_files} WHERE '.$DB->sql_like('name', ':name'),
                                    ['name' => '%'.$DB->sql_like_escape($filename).'%']);

           foreach ($results as $result =>$r) {
               $fileid = $this->get_file_id_from_url($r->url);
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

    /**
     * Get a file.
     *
     * @param string $reference reference of the file.
     * @param string $file name to save the file to.
     * @return string JSON encoded array of information about the file.
     */
    public function get_file($reference, $filename = '') {
        global $CFG;

        $auth = $this->client->getAuth();
        $request = $auth->authenticatedRequest(new Google_Http_Request($reference));
        if ($request->getResponseHttpCode() == 200) {
            $path = $this->prepare_file($filename);
            $content = $request->getResponseBody();
            if (file_put_contents($path, $content) !== false) {
                @chmod($path, $CFG->filepermissions);
                return array(
                    'path' => $path,
                    'url' => $reference
                );
            }
        }
        throw new repository_exception('cannotdownload', 'repository');
    }

    /**
     * Return the id of a given file
     * @param Google_Service_Drive $service
     * @param String $filename
     * @return String
     */
    public function get_file_id($filename) {

        $result = array();
        $pageToken = NULL;

        if($this->service->files != null) {
            do {
                try {
                    $parameters = array();
                    if ($pageToken) {
                        $parameters['pageToken'] = $pageToken;
                    }
                    $files = $this->service->files->listFiles($parameters);
                    $result = array_merge($result, $files->getItems());
                    $pageToken = $files->getNextPageToken();

                } catch (Exception $e) {
                    print "An error occurred: " . $e->getMessage();
                    $pageToken = NULL;
                }
            } while ($pageToken);

            foreach ($result as $r){
                if($r->title == $filename){
                   return $r->id;
                }
            }

        }
        return null;
    }

    /**
     * Helper function to get the file id from a given URL.
     * @param type $url
     * @param type $doctype
     * @return type
     */
    private function get_file_id_from_url($url) {

        if (strpos($url, 'document')) {
            $doctype = 'document';
        } else if (strpos($url, 'spreadsheets')) {
            $doctype = 'spreadsheets';
        } else {
            $doctype ='presentation';
        }

        if (preg_match('/\/\/docs\.google\.com\/'.$doctype.'\/d\/(.+)\/edit\b\?/', $url, $match) == 1) {
            $fileid = $match[1] ;
        }
        return $fileid;
    }



    /**
     * Helper function to get the students enrolled
     *
     * @param int $courseid
     * @return type
     */
    private function get_enrolled_students($courseid){

        $context = \context_course::instance($courseid);

        $coursestudents = get_role_users(5, $context);
        foreach ($coursestudents as $student) {
            $students[] = array('id' => $student->id, 'emailAddress' => $student->email,
            'displayName' => $student->firstname . ' ' . $student->lastname);
        }
        return $students;
    }

    /**
     * Edit/Create Admin Settings Moodle form.
     *
     * @param moodleform $mform Moodle form (passed by reference).
     * @param string $classname repository class name.
     */
    /*
    public static function type_config_form($mform, $classname = 'repository') {

        // TODO: this function is not used, yet.
        // We are using Moodle's google api clientid & secret, for now.

        $callbackurl = new moodle_url(self::CALLBACKURL);

        $a = new stdClass;
        $a->docsurl = get_docs_url('Google_OAuth_2.0_setup');
        $a->callbackurl = $callbackurl->out(false);

        $mform->addElement('static', null, '', get_string('oauthinfo', 'repository_googledocs', $a));

        parent::type_config_form($mform);
        $mform->addElement('text', 'clientid', get_string('clientid', 'repository_googledocs'));
        $mform->setType('clientid', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'secret', get_string('secret', 'repository_googledocs'));
        $mform->setType('secret', PARAM_RAW_TRIMMED);

        $strrequired = get_string('required');
        $mform->addRule('clientid', $strrequired, 'required', null, 'client');
        $mform->addRule('secret', $strrequired, 'required', null, 'client');
    }
    */

    /**
     * Insert a new permission to a given file
     * @param Google_Service_Drive $service Drive API service instance.
     * @param String $fileId ID of the file to insert permission for.
     * @param String $value User or group e-mail address, domain name or NULL for default" type.
     * @param String $type The value "user", "group", "domain" or "default".
     * @param String $role The value "owner", "writer" or "reader".
     */
    function insert_permission($service, $fileId, $value, $type, $role, $commenter = false) {
        $newPermission = new Google_Service_Drive_Permission();
        $newPermission->setValue($value);
        $newPermission->setRole($role);
        $newPermission->setType($type);

        if($commenter) {
            $newPermission->setAdditionalRoles(array('commenter'));
        }
        try {
            $service->permissions->insert($fileId, $newPermission);
        } catch (Exception $e) {
            print "An error occurred: " . $e->getMessage();
        }
        return null;
    }

    /**
    * Retrieve a list of permissions.
    *
    * @param Google_Service_Drive $service Drive API service instance.
    * @param String $fileId ID of the file to retrieve permissions for.
    * @return Array List of permissions.
    */
    function get_permissions($fileId) {
        try {
            $permissions = $this->service->permissions->listPermissions($fileId, array ('fields' => 'items'));

            return $permissions->getItems();
        } catch (Exception $e) {
           print "An error occurred: " . $e->getMessage();
        }
        return NULL;
    }

    /**
     * Helper function to save the instance record in DB
     * @global type $USER
     * @global type $DB
     * @param type $googledocs
     * @param type $sharedlink
     * @return type
     */
    public function save_instance($googledocs, $sharedlink, $folderid, $owncopy = false){

        global $USER, $DB;
        $googledocs->google_doc_url = !$owncopy ? $sharedlink[1] : null;
        $googledocs->docid = !$owncopy ? ($sharedlink[0])->id : null;
        $googledocs->parentfolderid = $folderid;
        $googledocs->userid = $USER->id;
        $googledocs->timeshared =  (strtotime(($sharedlink[0])->createdDate));
        $googledocs->timemodified = $googledocs->timecreated;
        $googledocs->name = ($sharedlink[0])->title;
        $googledocs->intro = ($sharedlink[0])->title;
        $googledocs->use_document = $googledocs->use_document;
        $googledocs->sharing = 1; //($sharedlink[0])->shared;
        $googledocs->introformat = FORMAT_MOODLE;

        if($owncopy) {
            $this->delete_file_request(($sharedlink[0])->id);
        }
        return $DB->insert_record('googledocs', $googledocs);
    }

    /**
     * Helper function to save the students links records.
     * @global type $DB
     * @param type $sharedlink
     * @param type $instanceid
     */
    public function save_students_links_records($links_for_students, $instanceid){
        global  $DB;
        $data = new \stdClass();
        foreach($links_for_students  as $sl=>$s){
            $data->userid = $sl;
            $data->googledocid = $instanceid;
            $data-> url = $s[0];
            $data->name = $s['filename'];
            $DB->insert_record('googledocs_files', $data);
        }
    }
    /**
     * Helper function to update the students links when there was an update
     * on the files distribution (from all_share to each_gets_own)
     * @global type $DB
     * @param type $links_for_students
     * @param type $instanceid
     */
    public function update_students_links_records($links_for_students, $results){
        global  $DB;
        $data = new \stdClass();
        $id = current($results);

        foreach($links_for_students  as $sl=>$s){
            $data->id = $id->id;
            $data-> url = $s[0];
            $data->name = $s['filename'];
            $DB->update_record('googledocs_files', $data);
            $id = next($results);
        }
    }

    /**
     * Calls the update function from Googles API using Curl
     * The update function from Drive.php did not work.
     *
     * @param type $fileid
     * @param type $details
     */
    private function update_file_request($fileid, $details, $studentsfolder = false) {

        $title = $studentsfolder ? $details->name .'_students' : $details->name;
        $data = array ('title' => $title);
        $data_string = json_encode($data);
        $contentlength = strlen($data_string);

        $token = $this->refresh_token();

        $url = "https://www.googleapis.com/drive/v2/files/".$fileid."?uploadType=multipart?key=". $this->api_key ;
        $header = ['Authorization: Bearer ' . $token,
                   'Accept: application/json',
                   'Content-Type: application/json',
                   'Content-Length:' . $contentlength];

        $ch = curl_init($url);
        //curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_exec($ch);
        try {
            $r = (curl_getinfo($ch))['http_code'] === 200;
        } catch (Exception $ex) {
            print ($ex->getMessage());
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

        $data = array ('role' => $permission->role,
                       'additionalRoles' => $permission->additionalRoles);

        $data_string = json_encode($data);
        $contentlength = strlen($data_string);

        $token = $this->refresh_token();

        $url = "https://www.googleapis.com/drive/v2/files/".$fileId."/permissions/".$permission->id."?key=". $this->api_key ;
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

        try {
            $r = (curl_getinfo($ch))['http_code'] === 200;
        } catch (Exception $ex) {
            print ($ex->getMessage());
        } finally {
            curl_close($ch);
        }
        return $r;
    }

    /*
     * Deletes a permission from a file
     */
    private function delete_permission_request($fileId, $permissionId) {

        $token = $this->refresh_token();

        $url = "https://www.googleapis.com/drive/v2/files/".$fileId."/permissions/".$permissionId."?key=". $this->api_key ;
        $header = ['Authorization: Bearer ' . $token,
                   'Accept: application/json',];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_exec($ch);
        try {
            $r = (curl_getinfo($ch))['http_code'] === 200;
        } catch (Exception $ex) {
            print ($ex->getMessage());
        } finally {
            curl_close($ch);
        }

        return $r;

    }
    /**
     * Delete file.
     * The class curlio.php in google folder, doesnt have the DELETE option
     * therefor $service->files->delete throws a coding exception.
     * That is why this function exists.
     * @param type $fileId
     * @return type
     * @throws Exception
     */
    private function delete_file_request($fileId) {

        $token = $this->refresh_token();

        $url = "https://www.googleapis.com/drive/v2/files/".$fileId."?key=". $this->api_key ;
        $header = ['Authorization: Bearer ' . $token,
                   'Accept: application/json',];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch,CURLOPT_ENCODING , "");
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);


        try {

            $r = (curl_getinfo($ch))['http_code'] === 204;

        } catch (Exception $ex) {
                print ($ex->getMessage());
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
    private function update_permissions($fileId, $details, $instance) {

        $permissionlist =  $this->get_permissions($fileId);

        try {

            $commenter = false;
            $removeaditionalrole = false;

            if ($instance->permissions  == GDRIVEFILEPERMISSION_COMMENTER  &&
                $details->permissions != GDRIVEFILEPERMISSION_COMMENTER) {
                $removeaditionalrole = true;
            }

            if ($details->permissions == GDRIVEFILEPERMISSION_COMMENTER) {
                $commenter = true;
                $newrole = 'reader';
            } else if ($details->permissions == GDRIVEFILEPERMISSION_READER ||
                $instance->permissions == GDRIVEFILEPERMISSION_COMMENTER) {
                $newrole = 'reader';
            }else{
                $newrole = 'writer';
            }

            foreach ($permissionlist as $pl => $l) {

                if($l->role === "owner") {
                    continue;
                }

                if ($commenter) {
                    $l->setAdditionalRoles(array('commenter'));
                }else if ($removeaditionalrole) {
                    $l->setAdditionalRoles(array());
                }

                $l->setRole($newrole);
                $this->update_permission_request($fileId, $l);

            }

        } catch(Exception $ex) {
           print($ex->getMessage());
        }
    }

    /**
     * Removes the permission given to students when all_share distr. changes
     * to each_gets_own
     * @global type $DB
     * @param type $result
     * @param type $instance
     * @param type $details
     */
    private function update_distribution($result, $instance) {
        global $DB;

        try{

            foreach($result as $r=>$i) {

                $fileId = $this->get_file_id_from_url($i->url);
                $permissionlist =  $this->get_permissions($fileId);

                foreach ($permissionlist as $pl => $l) {

                    if($l->role === "owner") {
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
     * Helper function to refresh access token.
     * The access token set in the session doesn't update
     * the expiration time. By refreshing the token, the error 401
     * is avoided.
     * @return type
     */
    private function refresh_token() {
        $accesstoken = json_decode($_SESSION['SESSION']->googledrive_rwaccesstoken);
        //$token = $accesstoken->access_token;
        //To avoid error in authentication, refresh token.
        $this->client->refreshToken($accesstoken->refresh_token);
        $token= (json_decode($this->client->getAccessToken()))->access_token;

        return $token;
    }


}