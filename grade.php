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
 * Redirects the user to either a googledoc or to the googledoc statistics
 *
 * @package   mod_googledocs
 * @category  grade
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

/**
 * Require config.php
 */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/googledocs/locallib.php');

$new = optional_param('forceview', 0, PARAM_INT);

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'googledocs');
$googledocs = $DB->get_record('googledocs', array('id' => $cm->instance), '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
$PAGE->set_context($coursecontext); // Every page needs a context.

$PAGE->set_url('/mod/googledocs/grade.php', array('id'=>$cm->id));

if(has_capability('mod/googledocs:viewall', $coursecontext)){
  redirect('view.php?id='.$cm->id.'&action='.get_string('grading', 'googledocs'));
} else {
  redirect('view.php?id='.$cm->id);
}
