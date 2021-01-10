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
 * Provides the mod_googledocs/submit_control
 *
 * @package   mod_googledocs
 * @category  output
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googledocs/submit_controls
 */

define(['jquery', 'core/ajax', 'core/log'], function ($, Ajax, Log) {
    var init = function (fileid, instanceid, groupid, email) {

        $("button#submitbtn_" + fileid).on('click', function () {
            $('button#submitbtn_' + fileid).replaceWith("<div id='submitting_" + fileid + "'"
            + "class ='spinner-border color'></div>");
            Ajax.call([{
                methodname: 'mod_googledocs_submit_student_file',
                    args: {
                            fileid: fileid,
                            instanceid: instanceid,
                            groupid: groupid,
                            email: email
                            },
                    done: function (response) {
                            Log.debug('mod_googledocs_submit_student_file');
                            if (response.recordid != null) {
                                $('div#submitting_' + fileid).
                                    replaceWith("<h4><span class='badge badge-primary'>Submitted</span></h4>");
                            } else{
                                $('div#submitting_' + fileid).
                                    replaceWith("<p><span class='alert alert-danger'>Please try again later.</span></p>");
                            }
                                Log.debug(response);
                            },
                    fail: function (reason) {
                            Log.error(reason);
                            }
            }]);
        });
    };

    return {
        init: function (fileid, instanceid, groupid, email) {
            init(fileid, instanceid, groupid, email);
        }
    };
});