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
 * Provides the mod_googledocs/control module
 *
 * @package   mod_googledocs
 * @category  output
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googledocs/control
 */
define(['jquery', 'core/log', 'core/ajax'], function ($, Log, Ajax) {
    'use strict';
    /**
     * Initializes the block controls.
     */
    function init(create) {
        Log.debug('mod_googledocs/control: initializing controls of the mod_googledocs');
        var saveAndDisplay = '#id_submitbutton';
        var parentfile_id = $('table.overviewTable').attr('data-googledoc-id');
        var control = new GoogledocsControl(saveAndDisplay, parentfile_id, create);
        control.main();
    }

    // Constructor.
    function GoogledocsControl(saveAndDisplay, parentfile_id, create) {
        var self = this;
        self.parentfile_id = parentfile_id;
        self.saveAndDisplay = saveAndDisplay;
        self.create = create;
    }

    GoogledocsControl.prototype.main = function () {
        var self = this;
        self.processingMessageDisplay(self.saveAndReturn);
        // Only call the create service if the files are not created.
        // This JS is called in the view.php page, which calls the function
        // that renders the table. It is the same table for created and processing
       
        if(!self.create) {
            self.callService();
        }else{
              self.initTags();
        }

    };
    
    GoogledocsControl.prototype.initTags = function (){
        var self = this;
        $('tbody').children().each(function(e){
            if ($('#link_file_' + e).attr('href') != '#'){
                self.tagDisplay(e, true);
            }else{
                self.tagDisplay(e, false);
            }
        });
    };
    /**
     *
     * @param {int} rownumber
     * @param  boolean creation
     * @returns display created or failed on the table's status column.
     */
    GoogledocsControl.prototype.tagDisplay = function(rownumber, creation){

        if(creation === true){
            $('#file_' + rownumber).html('Created');
            $('#file_' + rownumber).addClass('tag_doc success');
        }else{
            $('#file_' + rownumber).html('Failed');
            $('#file_' + rownumber).addClass('tag_doc failed');
        }
    };

    GoogledocsControl.prototype.processingMessageDisplay = function(buttonId) {
        // Handle submit click.

        $(buttonId).on('click', function() {
            $("<div class='d-flex flex-column align-items-center justify-content-center overlay'>"
                + "<div class='spinner-border processing' role='status'>"
                + "<span class='sr-only'>Loading...</span>"
                + "</div>"
//                + "<div class = 'process_message'>\n\
//                    <p>Saving files into My Drive. <br>\n\
//                        The process can take sometime.<br> \n\
//                        Please do not close the browser.</p>\n\
                 +   "</div></div>").appendTo('#page-content');

            });
    };
    GoogledocsControl.prototype.callService = function(){
        var self = this;

        $('tbody').children().each(function(e){
            var student_id = $(this).find('#file_' + e).attr('data-student-id');
            if (typeof student_id != "undefined"){
                var student_email=  $(this).find('#file_' + e).attr('data-student-email');
                var student_name =  $(this).find('a#fullname_' + student_id).html();
                self.create_student_file(e, student_id, student_email, student_name);
            }
        });
    };

    GoogledocsControl.prototype.create_student_file = function (rownumber, student_id, student_email, student_name) {
        var self = this;
        $('#file_' + rownumber).addClass('progress_bar processing'); // progress bar visible.
       
        Ajax.call([{
                methodname: 'mod_googledocs_create_students_file',
                args: {
                    parentfile_id: self.parentfile_id,
                    student_email: student_email,
                    student_name: student_name,
                    student_id: student_id,
                },
                done: function (response) {
                    Log.debug(response.url);
                    // Add file's link
                    var ref = $('#' + 'link_file_' + rownumber);
                    $(ref).attr("href", response.url);
                    // Remove progress bar and display status
                    $('#file_' + rownumber).removeClass('progress_bar processing');
                    self.tagDisplay(rownumber, true);


                },
                fail: function (reason) {
                    Log.error(reason);
                    $('#file_' + rownumber).removeClass('progress_bar  processing');
                    self.tagDisplay(rownumber, false);
                }
            }]);
    };

        return {
            init: init
        };
 });