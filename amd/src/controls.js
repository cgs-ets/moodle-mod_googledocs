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
    function init(create, group_sharing) {
        Log.debug('mod_googledocs/control: initializing controls of the mod_googledocs');

        var parentfile_id = $('table.overviewTable').attr('data-googledoc-id');
        var instance_id = $('table.overviewTable').attr('data-instance-id');
        var control = new GoogledocsControl(parentfile_id, create, group_sharing, instance_id);
        control.main();
    }

    // Constructor.
    function GoogledocsControl( parentfile_id, create, group_sharing, instance_id) {
        var self = this;
        self.parentfile_id = parentfile_id;
        self.create = create;
        self.group_sharing = group_sharing;
        self.instance_id = instance_id;
    }

    GoogledocsControl.prototype.main = function () {
        var self = this;

        // Only call the create service if the files are not created.
        // This JS is called in the view.php page, which calls the function
        // that renders the table. It is the same table for created and processing
        //when sharing by group another WS is called
        if(!self.create && !self.group_sharing) {
            self.callStudentFileService(self.parentfile_id);
        }else if (!self.create && self.group_sharing) {
            self.callGroupFileService();
        }else{
              self.initTags();
        }
        
        // When sharing by group or grouping. The same file is shared.
        // The generation of this file might be quick, but the giving the students
        // a permission can take sometime. In order for the entire sharing is done
        // The progress bar is only removed when all the ajax calls finis.
        $(document).ajaxStop(function() {
           
           $('tbody').children().each(function(e){
            var tag = $(this).find('#status_col');
            tag.removeClass('progress_bar processing');
            tag.html('Sharing');
            tag.addClass('tag_doc success');
           });
        });

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
        var self = this;
 
        if(creation === true && !self.group_sharing){
            $('#file_' + rownumber).html('Created');
            $('#file_' + rownumber).addClass('tag_doc success');
        }else if(creation === true && self.group_sharing){
            $('div#status_col').html('Sharing');
            $('div#status_col').addClass('tag_doc success');
        }else{
            $('#file_' + rownumber).html('Failed');
            $('#file_' + rownumber).addClass('tag_doc failed');
        }

    };

    GoogledocsControl.prototype.callStudentFileService = function(parentfile_id, by_group = false, group_id = 0){
        var self = this;

        $('tbody').children().each(function(e){
            var student_id = $(this).attr('data-student-id');  
            var student_email = $(this).attr('data-student-email');
            var student_name = $(this).attr('student-name');
            self.create_student_file(e, student_id, student_email, student_name, parentfile_id);

        });
    };

    GoogledocsControl.prototype.callStudentFileServiceForByGroup = function(parentfile_id, group_id) {
        var self = this;
        $('tbody#group-members-'+group_id).children().each(function(e){
            
            var student_id = $(this).attr('data-student-id');
            var student_email = $(this).attr('data-student-email');
            var student_name = $(this).attr('student-name');
            var student_group_id = $(this).attr('student-group-id');
            
            console.log('student id: ' +student_id);
            
            if(student_group_id == group_id) {
                self.create_student_file(e, student_id, student_email, student_name, parentfile_id, true, student_group_id);
            }

        });

    };

    GoogledocsControl.prototype.create_student_file = function (rownumber, student_id, student_email,student_name, parentfile_id, 
                                                                by_group = false, student_group_id = 0) {
        var self = this;

        if(!by_group) {
           $('#file_' + rownumber).addClass('progress_bar processing'); // progress bar visible.
        }

        Ajax.call([{
                methodname: 'mod_googledocs_create_students_file',
                args: {
                    by_group : by_group,
                    group_id : student_group_id,
                    instance_id : self.instance_id,
                    parentfile_id: parentfile_id,
                    student_email: student_email,
                    student_id: student_id,
                    student_name: student_name
                },
                done: function (response) {
                    Log.debug(response.url);
                    console.log("rownumber: " + rownumber );
                    // Add file's link
                    var ref = $('#' + 'link_file_' + rownumber);
                    $(ref).attr("href", response.url);
                    // Remove progress bar and display status
                    if(!by_group) {
                        $('#file_' + rownumber).removeClass('progress_bar processing');
                        self.tagDisplay(rownumber, true);
                    }
                },
                fail: function (reason) {
                    Log.error(reason);
                    $('#file_' + rownumber).removeClass('progress_bar  processing');
                    self.tagDisplay(rownumber, false);
                }
            }]);
      
    };

    GoogledocsControl.prototype.callGroupFileService = function (){

        var owner_email = $('table.overviewTable').attr('data-owner-email');
        var self = this;
        $('tbody').find('[data-group-name]').each(function(e){
            var group_name = $(this).attr('data-group-name');
            var groupid =   $(this).attr('data-group-id'); 
            var a_element = ($(this).find('#shared_link_url_' + groupid))[0]; //It is always the one element.
           ($(this).find('div#status_col')).addClass('progress_bar processing');
            self.create_group_file(a_element, owner_email, group_name, groupid);
            //(($(this).find('div#status_col'))).removeClass('progress_bar processing');
        });

    };

    GoogledocsControl.prototype.create_group_file = function(a_element ,owner_email, group_name, group_id){

        var self = this;
      
        Ajax.call([{
            methodname: 'mod_googledocs_create_group_file',
            args: {
                group_name: group_name,
                group_id: group_id,
                owner_email: owner_email,
                parentfile_id: self.parentfile_id,
            },
            done: function (response) {
                console.log(response);
                 // Add file's link
                $(a_element).attr("href", response.url);
                //Returns the ID of the file created for the group.
                //$(this).find('div#status_col').removeClass('progress_bar processing');
                self.callStudentFileServiceForByGroup(response.googledocid, group_id);

            },
            fail: function (reason) {
                Log.error(reason);
            }
        }]);

        $('table.overviewTable').removeAttr('data-googledoc-id');

    };

        return {
            init: init
        };
 });