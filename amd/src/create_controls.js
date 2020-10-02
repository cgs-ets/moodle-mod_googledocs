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
 * Provides the mod_googledocs/create_control module
 *
 * @package   mod_googledocs
 * @category  output
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googledocs/control
 */
define(['jquery', 'core/log', 'core/ajax', 'mod_googledocs/delete_controls', 'mod_googledocs/update_controls'], 
function ($, Log, Ajax, DeleteControl, UpdateControl) {
    'use strict';
    /**
     * Initializes the controls.
     */
    function init(create, dist_type) {
        Log.debug('mod_googledocs/update_control: initializing of the mod_googledocs control');
        Log.debug(dist_type);

        var parentfile_id;
        var files_to_erase = []; // Collection of files ids to delete after copies are created

        if (dist_type == 'grouping_copy') {
            parentfile_id = $('table.groupingTable').attr('data-parentfile-id');
        }else{
            parentfile_id = $('table.overviewTable').attr('data-googledoc-id');
        }

        var instance_id = $('table.overviewTable').attr('data-instance-id');
        var countCalls = 0;
        var group_folder_ids;

        var control = new GoogledocsControl(parentfile_id, create,  
        instance_id, files_to_erase, countCalls, dist_type, group_folder_ids);

        control.main();
    }

    // Constructor.
    function GoogledocsControl( parentfile_id, create,  instance_id, 
     files_to_erase, countCalls, dist_type, group_folder_ids) {
        var self = this;
        self.parentfile_id = parentfile_id;
        self.create = create;
        self.instance_id = instance_id;
        self.files_to_erase = files_to_erase;
        self.countCalls = countCalls;
        self.dist_type = dist_type;
        self.group_folder_ids = group_folder_ids;

    }

    GoogledocsControl.prototype.main = function () {
        var self = this;
        
        // Only call the create service if the files are not created.
        // This JS is called in the view.php page, which calls the function
        // that renders the table. It is the same table for created and processing
        //when sharing by group or by bygrouping other WS is called


        self.initTags();

        switch(self.dist_type) {

            case 'std_copy' :
                if (!self.create) {
                    self.callStudentFileService(self.parentfile_id);
                }
                break;

            case 'group_copy' :
                if (!self.create) {
                    self.callGroupFileService();
                }
                break;

            case 'grouping_copy' :
                 if (!self.create) {
                    self.callGroupingFileService();
                }
                break;

            case 'std_copy_group_copy':
                if(!self.create) {
                   self.create_group_folder();
                }
                break;

            case 'dist_share_same' :
                if(!self.create) {
                   Log.debug("dist_share_same " + self.parentfile_id);
                   self.callStudentFileService(self.parentfile_id);
                }
                break;

            case 'dist_share_same_group_copy' :
                if(!self.create) {
                   self.create_group_folder();
                }
                break;
            case 'dist_share_same_grouping_copy' :
                if(!self.create) {
                   self.create_group_folder();
                }
                break;
            case 'std_copy_grouping_copy' :
                if(!self.create) {
                    self.create_group_folder();
                }
                break;
            case 'std_copy_group_grouping_copy':
                if(!self.create) {
                    self.create_group_folder();
                }
                break;
            case 'group_grouping_copy' :
                if (!self.create) {
                    self.callGroupFileService();
                }
                break;
            case 'dist_share_same_group_grouping_copy' :
                 if(!self.create) {
                   self.create_group_folder();
                  }
                break;
              


        }
        
        

        // When sharing by group or grouping. The same file is shared.
        // The generation of this file might be quick, but giving the students
        // a permission can take some time. In order for the entire sharing is done
        // The progress bar is only removed when all the ajax calls finish.
        $(document).ajaxStop(function() {

           $('tbody').children().each(function(e){
            var tag = $(this).find('#status_col');
            tag.removeClass('spinner-border color');
            tag.html('Created');
            tag.addClass('tag_doc success');
           });
           
           var from_existing = $('table.overviewTable').attr('data-from-existing');
           
            if(self.dist_type == 'grouping_copy'){

               $('tbody.grouping-groups td.groups').each(function(){

                $(this).find('table').each(function(){
                    var tag = $(this).find("#file_grouping");
                    tag.removeClass('spinner-border color');
                    tag.html('Created');
                    tag.addClass('tag_doc success');
                });
             });

                if(self.files_to_erase.length > 0  && from_existing == 0) {
                    self.delete_file_from_grouping();
                    self.files_to_erase = [];
                }
           }

           // Once the students get their file or permission to access file
           // delete the original file (when the file is created with the activity module)
           // don't delete originals created with the "Create from existing" option.
           //For group and students distribution is the same process.
            var totalCalls = $('tbody').children().length;
        
            var file_to_delete = $('table.overviewTable').attr('data-googledoc-id');
            //Log.debug("file_to_delete " + file_to_delete);
            //Log.debug('totalCalls ' + totalCalls);
            //Log.debug('countCalls ' + self.countCalls);
            $('table.overviewTable').removeAttr('data-googledoc-id');
            
            if(from_existing == 1 && self.countCalls == totalCalls
                    && file_to_delete != undefined ) {
               self.files_to_erase.push(file_to_delete);
               UpdateControl.init(JSON.stringify(self.files_to_erase));
               self.countCalls = 0;
            }
            
            if (self.countCalls == totalCalls && file_to_delete != undefined 
                    && from_existing == 0 && self.dist_type != 'grouping_copy'){
                self.files_to_erase.push(file_to_delete);
                DeleteControl.init(JSON.stringify(self.files_to_erase), self.dist_type);
                self.countCalls = 0;
            }


        });

    };

    GoogledocsControl.prototype.initTags = function (){
        var self = this;

        $('tbody').children().each(function(e){
            if ($('#link_file_' + e).attr('href') != '#'){
                self.tagDisplay(e, self.create);
            }else{
                self.tagDisplay(e, self.create);
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
        if(creation === true) {
            $('#file_' + rownumber).html('Created');
            $('#file_' + rownumber).addClass('tag_doc success');

        }else{
           // $('#file_' + rownumber).html('Failed');
            $('#file_' + rownumber).addClass('spinner-border color');
        }

    };
    
    GoogledocsControl.prototype.failedTag = function (rownumber) {
         $('#file_' + rownumber).html('Failed');
    }

    /**
     * Example with file name Grouping_Share and groupings called Even and Odd respectively
     * Even grouping has groups 2 and 4, Odd has groups 1 and 3
     * Files created:
     * 1. Grouping_Share 
     * 2. Grouping_share_odd
     *     Grouping_Share_Grouping_share_odd_group_1
     *       Grouping_Share_Grouping_share_odd_group_3
     * 3. Grouping_share_even
     *     Grouping_Share_Grouping_share_even_group_2
     *       Grouping_Share_Grouping_share_even_group_4
     *
     * This function process the deletion of the following files:
     *    Grouping_Share
     *    Grouping_share_odd
     *    Grouping_share_even
     * @returns {undefined}
     */
    GoogledocsControl.prototype.delete_file_from_grouping = function () {
        var self = this;
        DeleteControl.init(JSON.stringify(self.files_to_erase), self.dist_type);
        self.files_to_erase = [];
        self.countCalls = 0;
    };


    GoogledocsControl.prototype.callStudentFileService = function(parentfile_id, group_id = 0 ){
        var self = this;

        Log.debug("callStudentFileService");
        $('tbody').children().each(function(e){

            var student_id = $(this).attr('data-student-id');
            var student_email = $(this).attr('data-student-email');
            var student_name = $(this).attr('student-name');
            var group_id = $(this).attr('student-group-id');

            self.create_student_file(e, student_id, student_email, student_name, parentfile_id, group_id);

        });

    };

    GoogledocsControl.prototype.callStudentFileServiceForGroup = function(parentfile_id, group_id, grouping_id = 0) {
        var self = this;
        Log.debug("Enters to: callStudentFileServiceForGroup");
        Log.debug("Parent file ID " + parentfile_id + "Group ID " + group_id + "Grouping ID " + grouping_id);
        $('tbody#group-members-'+group_id).children().each(function(e){

            var student_id = $(this).attr('data-student-id');
            var student_email = $(this).attr('data-student-email');
            var student_name = $(this).attr('student-name');
            var student_group_id = $(this).attr('student-group-id');
            if(student_group_id == group_id) {
                self.create_student_file(e, student_id, student_email, student_name, parentfile_id,
                                          student_group_id,  grouping_id );

            }
        });

    };


    GoogledocsControl.prototype.create_student_file = function (rownumber, student_id, student_email,student_name, parentfile_id, 
                                                                student_group_id = 0,
                                                                student_grouping_id = 0 ) {
        var self = this;
        var folder_id = 0;

        if(self.dist_type != 'group_copy') {
           $('#file_' + rownumber).addClass('spinner-border color'); // progress bar visible. spinner-border
        }

        if(self.dist_type == 'std_copy_group_copy' || self.dist_type == 'std_copy_grouping_copy'
                || self.dist_type == 'std_copy_group_grouping_copy') {
            folder_id = self.get_group_folder_id(student_group_id, self.group_folder_ids);
            Log.debug("create_student_file folder_id = " + folder_id );
        }


        Ajax.call([{

                methodname: 'mod_googledocs_create_students_file',

                args: {
                    folder_group_id: folder_id,
                    group_id : student_group_id,
                    grouping_id: student_grouping_id,
                    instance_id : self.instance_id,
                    parentfile_id: parentfile_id,
                    student_email: student_email,
                    student_id: student_id,
                    student_name: student_name
                },

                done: function (response) {
                    Log.debug(response.url);
                    self.countCalls++;
                    // Add file's link

                    var urls = JSON.parse(response.url);

                    if (self.dist_type == 'std_copy_group_copy' ||self.dist_type == 'std_copy_grouping_copy'
                            || self.dist_type == 'std_copy_group_grouping_copy') {
                        self.renderStudentLinks(urls, rownumber);
                    }else{
                        var ref = $('#' + 'link_file_' + rownumber);
                        $(ref).attr("href", urls[0]);
                    }

                    // Remove progress bar and display status
                    if(self.dist_type != 'group_copy') {
                        $('#file_' + rownumber).removeClass('spinner-border color');
                        self.tagDisplay(rownumber, true);
                    }

                },

                fail: function (reason) {
                    Log.error(reason);
                    $('#file_' + rownumber).removeClass('spinner-border color');
                    $('#file_' + rownumber).addClass('failed');
                    self.failedTag(rownumber);
                }
            }]);


    };

    // When dist. is each student from X group gets a copy. A student
    // can get more than one copy. 
     GoogledocsControl.prototype.renderStudentLinks = function(urls, rownumber) {
        urls.forEach(function(url, index){
                       Log.debug('addLinks ' + rownumber);
                        var ref = $('#' + 'link_file_' + rownumber);
                        if(index === 0) {
                           $(ref).attr("href", url);
                        }else{
                           var src = $(ref).find("img").attr("src");
                           $(ref).append('<a target="_blank" id="link_file_' + rownumber +  '"href="' + url + '" class="link_icon">\n\
                            <img src="'+ src +'" class="link_icon"</a>');
                        }
                    }, rownumber);
     }

    GoogledocsControl.prototype.callGroupFileService = function (){

        var owner_email = $('table.overviewTable').attr('data-owner-email');
        var self = this;

        $('tbody').find('[data-group-name]').each(function(){
            self.countCalls++;
            var group_name = $(this).attr('data-group-name');
            var groupid =   $(this).attr('data-group-id');
            var a_element = ($(this).find('#shared_link_url_' + groupid))[0]; //It is always the one element.
           ($(this).find('div#status_col')).addClass('spinner-border color');
            self.create_group_file(a_element, owner_email, group_name, groupid);

        });

    };

    GoogledocsControl.prototype.callGroupingFileService = function (){

        $('tbody.grouping-groups td.groups').each(function(){
           $(this).find('table').each(function(){
                ($(this).find("#file_grouping")).addClass('spinner-border color');
           });
        });

        var owner_email = $('table.groupingTable').attr('data-owner-email');

        var self = this;

        $('tbody').find('[data-grouping-name]').each(function(){
            var grouping_name = $(this).attr('data-grouping-name');
            var grouping_id =   $(this).attr('data-grouping-id');
            self.create_grouping_file (grouping_name, grouping_id, owner_email);
        });
    };

    /**
     * Creates the file FileName_Group_Name
     * @param htmlElement a_element
     * @param String owner_email
     * @param String group_name
     * @param int group_id
     * @param int parentfile_id
     * @param int grouping_id
     *
     */
    GoogledocsControl.prototype.create_group_file = function(a_element = '' , owner_email, group_name = '', group_id, parentfile_id = 0,
    grouping_id = 0){

        var self = this;

        if(!self.grouping_sharing) {
            parentfile_id = self.parentfile_id;
        }

        Ajax.call([{
            methodname: 'mod_googledocs_create_group_file',
            args: {
                group_name: group_name,
                group_id: group_id,
                grouping_id: grouping_id,
                instance_id: self.instance_id,
                owner_email: owner_email,
                parentfile_id: parentfile_id,
            },
            done: function (response) { 
                Log.debug("mod_googledocs_create_group_file " + response);
                if(self.dist_type == 'dist_share_same_group_copy' 
                        || self.dist_type == 'dist_share_same_grouping_copy'
                        || self.dist_type == 'dist_share_same_group_grouping_copy') {
                    self.append_links_to_icons(response.url); //  Traverse the list of students and add the link accoding to the group they belong to.                    
                }else{
                    // Add file's link
                   $(a_element).attr("href", response.url);
                   //Returns the ID of the file created for the group.
                   Log.debug('Group ID' + group_id)
                   self.callStudentFileServiceForGroup(response.googledocid, group_id);
                }
               
                self.files_to_erase.push(parentfile_id);

            },
            fail: function (reason) {
                Log.error(reason);
            }
        }]);


    };
    /**
     * Creates the file FileName_GroupingName
     * @param String grouping_name
     * @param  int grouping_id
     * @param String owner_email
     *
     */
    GoogledocsControl.prototype.create_grouping_file = function (grouping_name, grouping_id, owner_email) {
        var self = this;

        Ajax.call([{
            methodname: 'mod_googledocs_create_grouping_file',
            args: {
                grouping_name: grouping_name,
                grouping_id: grouping_id,
                owner_email: owner_email,
                parentfile_id: self.parentfile_id,
            },
            done: function (response) {
                console.log(response);
                // Add file's link
                //Returns the ID of the file created for the groups and the URL. 
                self.callGroupingGroupFileService(grouping_id, response.urls);
                self.files_to_erase.push(response.googledocid);
            },
            fail: function (reason) {
                Log.error(reason);
            }
        }]);
    };

   /**
    * Creates the file FileName_GroupingName_GroupName
    * @param int parentfile_id
    * @param int grouping_id
    * @param url url
    *
    */
    GoogledocsControl.prototype.callGroupingGroupFileService = function(grouping_id, urls){
        var  self = this;
        var  links = JSON.parse(urls);

       $('tbody.grouping-groups td.groups').each(function(e){

            $(this).find('table').each(function(){
                var t = $(this);
                var group_id = t.attr('data-group-id');
                var link = (t.find('#shared_link_' + grouping_id))[0];
                var url = self.get_grouping_url(group_id, links);

                $(link).attr("href",url);

            });

        });
    };

    //Dist. Each student from a group gets a copy or each std. from a group share same copy. Create group folders
    GoogledocsControl.prototype.create_group_folder = function(){
        var self = this;

        Ajax.call([{
            methodname: 'mod_create_group_folder_struct',
            args: {
                instance_id: self.instance_id
            },
            done: function (response) {
               Log.debug('mod_create_group_folder_struct');
               self.group_folder_ids = JSON.parse(response.group_folder_ids);
               
               //Each student will get a copy
               if(self.dist_type ==  'std_copy_group_copy' || self.dist_type == 'std_copy_grouping_copy'
                       || self.dist_type == 'std_copy_group_grouping_copy') {
                self.callStudentFileService(self.parentfile_id);
               }
               //Create the copy and the owner is the teacher.
               if(self.dist_type == 'dist_share_same_group_copy' 
                       || self.dist_type == 'dist_share_same_grouping_copy'
                       || self.dist_type == 'dist_share_same_group_grouping_copy') {
                 
                   var g_ids = $('table.overviewTable').attr('data-all-groups');
                   var owner_email = $('table.overviewTable').attr('data-owner-email');
                   self.create_group_file('',owner_email,'' ,g_ids, self.parentfile_id);
               }
              

            },
            fail: function (reason) {
                Log.error(reason);
            }
        }]);
    };

    GoogledocsControl.prototype.get_group_folder_id = function (group_id, group_folder_ids) {
        Log.debug("get_group_folder_id " +  group_id + " " + group_folder_ids);
        var groups = group_id.split('-');
        for(var i = 0; i < group_folder_ids.length; i++) {
            if (group_folder_ids[i].group_id == group_id || groups.includes(group_folder_ids[i].group_id)) {
               return group_folder_ids[i].folder_id;
               break;
            }
        }

    };

    GoogledocsControl.prototype.get_grouping_url = function (group_id, urls) {
        Log.debug("get_grouping_url");

        for(var i= 0; i< urls.length; i++) {
            if(urls[i].group_id == group_id){
                return urls[i].url;
                break;
            }

        }
    };

    GoogledocsControl.prototype.append_links_to_icons = function(urls) {
        var self = this;
        var links = JSON.parse(urls);

        $('tbody').children().each(function(e){
            self.countCalls++;
            Log.debug('append_links_to_icons');

            var group_id = $(this).attr('student-group-id');
            Log.debug(group_id);
            var ids = group_id.split("-");
            Log.debug("ids " + ids);

            self.append_links_helper(ids, links,e);

            $('#file_' + e).removeClass('spinner-border color');
             self.tagDisplay(e, true);

        });

    };

    GoogledocsControl.prototype.append_links_helper = function(ids, urls, rownumber) {
        var self = this;
        ids.forEach(function(id){
            var ref = $('#' + 'link_file_' + rownumber);
            var src = $(ref).find("img").attr("src");
            var url = self.get_grouping_url(id, urls);
            Log.debug("URL => " + url );
            if (url != undefined) {
                if( $(ref).attr("href") == "#"){
                   $(ref).attr("href", url);
                }else{
                    $(ref).append('<a target="_blank" id="link_file_' + rownumber +  '"href="' + url + '" class="link_icon">\n\
                    <img src="'+ src +'" class="link_icon"</a>');
                }
            }

        });
    };
    
    

    return {
        init: init
    };
 });