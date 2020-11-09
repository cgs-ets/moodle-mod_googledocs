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
 * Plugin external functions and services are defined here.
 *
 * @package   mod_googledocs
 * @category    external
 * @copyright 2020 Veronica Bermegui, Canberra Grammar School <veronica.bermegui@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_googledocs_create_students_file' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'create_students_file', // External function name.
        'description' => 'Create googledoc file for students', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],

    'mod_googledocs_create_group_file' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'create_group_file', // External function name.
        'description' => 'Create googledoc file for a group', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],

    'mod_googledocs_create_grouping_file' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'create_grouping_file', // External function name.
        'description' => 'Create googledoc file for a grouping ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],
    'mod_googledocs_create_group_grouping_file' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'create_group_grouping_file', // External function name.
        'description' => 'Create ggoledoc file for a group and grouping ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],
    'mod_googledocs_delete_files' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'delete_files', // External function name.
        'description' => 'Create googledoc file for a grouping ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],
    'mod_googledocs_update_sharing' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'update_sharing', // External function name.
        'description' => 'Update sharing status ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],
    'mod_googledocs_create_group_folder_struct' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'create_group_folder_struct', // External function name.
        'description' => 'Create groups folders ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],
    'mod_googledocs_google_login_student' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'google_login_student', // External function name.
        'description' => 'Give Moodle access to the student Google Drive  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],
    'mod_googledocs_submit_student_file' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'submit_student_file', // External function name.
        'description' => 'Submit file to grade  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],

    'mod_googledocs_grade_student_file' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'grade_student_file', // External function name.
        'description' => 'Grade student file  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],

    'mod_googledocs_save_quick_grading' => [
        'classname' => 'mod_googledocs\external\api', // Class containing a reference to the external function.
        'methodname' => 'save_quick_grading', // External function name.
        'description' => 'Sav grading and comment  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ]

];