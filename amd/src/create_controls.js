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
 * @module mod_googledocs/controls
 */
define(['jquery', 'core/log', 'core/ajax', 'mod_googledocs/delete_controls', 'mod_googledocs/update_controls'], function ($, Log, Ajax, DeleteControl, UpdateControl) {
    'use strict';
    /**
     * Initializes the controls.
     */
    function init(create, dist_type) {
        Log.debug('mod_googledocs/update_control: initializing of the mod_googledocs control');
        Log.debug(dist_type);

        var parentfile_id = $('table.overviewTable').attr('data-googledocs-id');
        var files_to_erase = []; // Collection of files ids to delete after copies are created
        var instance_id = $('table.overviewTable').attr('data-instance-id');
        var isfoldertype = $('table.overviewTable').attr('data-isfolder');
        var countCalls = 0;
        var group_folder_ids;

        var control = new GoogledocsControl(parentfile_id, create, instance_id,
                files_to_erase, countCalls, dist_type, group_folder_ids, isfoldertype);

        control.main();
    }

    // Constructor.
    function GoogledocsControl(parentfile_id, create, instance_id,
            files_to_erase, countCalls, dist_type, group_folder_ids, isfoldertype) {

        var self = this;
        self.parentfile_id = parentfile_id;
        self.create = create;
        self.instance_id = instance_id;
        self.files_to_erase = files_to_erase;
        self.countCalls = countCalls;
        self.dist_type = dist_type;
        self.group_folder_ids = group_folder_ids;
        self.isfoldertype = isfoldertype;

    }

    GoogledocsControl.prototype.main = function () {
        var self = this;
        window.addEventListener('popstate', self.popstateHandler);
        if (!self.create) {
            window.addEventListener('beforeunload', self.beforeunloadHandler);
        }
        // Only call the create service if the files are not created.
        // This JS is called in the view.php page, which calls the function
        // that renders the table. It is the same table for created and processing
        //when sharing by group or by bygrouping other WS is called

        switch (self.dist_type) {

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
                self.initGroupingTags();
                if (!self.create) {
                    self.callGroupingFileService();
                }
                break;
            case 'std_copy_group':
                if (!self.create) {
                    self.create_group_folder();
                }
                break;
            case 'dist_share_same' :
                if (!self.create) {
                    self.callStudentFileService(self.parentfile_id);
                }
                break;

            case 'dist_share_same_group' :
                if (!self.create) {
                    self.initGroupTags();
                    self.shareSameGroupGroupingService();
                }
                break;
            case 'dist_share_same_grouping' :
                self.initGroupingTags();
                if (!self.create) {
                    self.callGroupingFileService(self.parentfile_id);
                }
                break;
            case 'std_copy_grouping' :
                if (!self.create) {
                    self.create_group_folder();
                }
                break;
            case 'std_copy_group_grouping':
                if (!self.create) {
                    self.create_group_folder();
                }
                break;
            case 'group_grouping_copy' :
                if (!self.create) {
                    self.callGroupGroupingFileService();
                } else {
                    self.initGGTags();
                }
                break;
            case 'dist_share_same_group_grouping' :
                if (!self.create) {
                    self.callGroupGroupingFileService();
                } else {
                    self.initGGTags();
                }
                break;

        }

        // When sharing by group or grouping. The same file is shared.
        // The generation of this file might be quick, but giving the students
        // a permission can take some time. In order for the entire sharing is done
        // The progress bar is only removed when all the ajax calls finish.
        $(document).ajaxStop(function () {

            $('tbody').children().each(function () {
                var tag = $(this).find('#status_col');
                self.createdTagHandler(tag);
            });

            var from_existing = $('table.overviewTable').attr('data-from-existing');

            if (self.dist_type == 'grouping_copy') {

                $('tbody.grouping-groups td.groups').each(function () {

                    $(this).find('table').each(function () {
                        var tag = $(this).find("#file_grouping");
                        self.createdTagHandler(tag);
                    });
                });

                if (self.files_to_erase.length > 0 && from_existing == 0) {
                    self.delete_file_from_grouping();
                    self.files_to_erase = [];
                }
            }

            // Once the students get their file or permission to access file
            // delete the original file (when the file is created with the activity module)
            // don't delete originals created with the "Create from existing" option.
            //For group and students distribution is the same process.
            var totalCalls = $('tbody').children().length;
            var file_to_update = $('table.overviewTable').attr('data-googledocs-id');

            $('table.overviewTable').removeAttr('data-googledocs-id');
            console.log('TOTAL CALLS TO DO', totalCalls);
            console.log('self.countCalls', self.countCalls);
            
            if (from_existing == 1 && self.countCalls == totalCalls
                    && file_to_update != undefined) {
                self.files_to_erase.push(file_to_update);
                console.log("instance_id", self.instance_id);
                UpdateControl.init(JSON.stringify(self.files_to_erase), self.instance_id);
                self.countCalls = 0;
            }

            if (self.dist_type == 'group_grouping_copy' && self.countCalls == totalCalls) {
                self.files_to_erase.push(file_to_update);
                DeleteControl.init(JSON.stringify(self.files_to_erase), self.dist_type);
                self.countCalls = 0;
            }

            if (self.countCalls == totalCalls && file_to_update != undefined
                    && from_existing == 0 ) { //&& self.dist_type != 'grouping_copy'
                self.files_to_erase.push(file_to_update);
                DeleteControl.init(JSON.stringify(self.files_to_erase), self.dist_type);
                self.countCalls = 0;
            }
            //Remove alert when trying to leave the page, after all files were created
            window.removeEventListener('beforeunload', self.beforeunloadHandler, false);
        });

    };

    GoogledocsControl.prototype.createdTagHandler = function (tag) {
        tag.removeClass('spinner-border color');
        tag.html('Created');
        tag.addClass('status-access');
    };
    
    // Triggers when user tries to leave the page and documents are being created.
    GoogledocsControl.prototype.beforeunloadHandler = function (event, created) {
        event.preventDefault();
        event.returnValue = '';
    };
    GoogledocsControl.prototype.popstateHandler = function (event) {
        Log.debug('popstateHandler');
    };

    GoogledocsControl.prototype.initTags = function () {
        var self = this;
        $('tbody').children().each(function (e) {
            if ($('#link_file_' + e).attr('href') != '#') {
                self.tagDisplay(e, self.create);
            } else {
                self.tagDisplay(e, self.create);
            }
        });
    };

    GoogledocsControl.prototype.initGGTags = function () {

        $('tbody').find('[data-g-name]').each(function () {
            var gid = $(this).attr('data-g-id');
            var statuscol = ($(this).find('div#status_col_' + gid));
            $(statuscol).html('Created');
            $(statuscol).addClass('status-access');
        });
    };

    GoogledocsControl.prototype.initGroupingTags = function () {
        var self = this;

        $('tbody').find('[data-grouping-name]').each(function () {
            var gid = $(this).attr('data-grouping-id');
            var statuscol = ($(this).find('div#status_col_' + gid));
            if (self.create) {
                $(statuscol).html('Created');
                $(statuscol).addClass('status-access');
            } else {
                $('div#status_col_' + gid).addClass('spinner-border color');
            }
        });
    };

    GoogledocsControl.prototype.initGroupTags = function () {

        $('tbody').find('[data-group-name]').each(function () {
            $('div#status_col').addClass('spinner-border color');
        });
    };

    /**
     * display created or failed on the table's status column.
     */
    GoogledocsControl.prototype.tagDisplay = function (rownumber, creation) {
        if (creation === true) {
            $('#file_' + rownumber).html('Created');
            $('#file_' + rownumber).addClass('status-access');

        } else {
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


    GoogledocsControl.prototype.callStudentFileService = function (parentfile_id, group_id = 0) {
        var self = this;

        Log.debug("callStudentFileService");
        $('tbody').children().each(function (e) {

            var student_id = $(this).attr('data-student-id');
            var student_email = $(this).attr('data-student-email');
            var student_name = $(this).attr('student-name');
            var gid;

            if (self.dist == 'dist_share_same_grouping' || self.dist == 'std_copy_grouping') {
                gid = $(this).attr('student-grouping-id');
            } else {
                gid = $(this).attr('student-group-id');
            }

            self.create_student_file(e, student_id, student_email, student_name, parentfile_id, gid);
        });

    };

    GoogledocsControl.prototype.callStudentFileServiceForGroup = function (parentfile_id, group_id, grouping_id = 0) {
        var self = this;
        Log.debug("Enters to: callStudentFileServiceForGroup");
        Log.debug("Parent file ID " + parentfile_id + "Group ID " + group_id + "Grouping ID " + grouping_id);

        $('tbody#group-members-' + group_id).children().each(function (e) {

            var student_id = $(this).attr('data-student-id');
            var student_email = $(this).attr('data-student-email');
            var student_name = $(this).attr('student-name');
            var student_group_id = $(this).attr('student-group-id');
            if (student_group_id == group_id ) {
                self.create_student_file(e, student_id, student_email, student_name, parentfile_id,
                        student_group_id, grouping_id);

            }
        });

    };


    GoogledocsControl.prototype.create_student_file = function (rownumber, student_id, student_email,
            student_name, parentfile_id,
            student_group_id = 0,
            student_grouping_id = 0) {
        var self = this;
        var folder_id = 0;

        if (self.dist_type != 'group_copy') {
            $('#file_' + rownumber).addClass('spinner-border color'); // progress bar visible. spinner-border
        }

        if (self.dist_type == 'std_copy_group'
                || self.dist_type == 'std_copy_grouping'
                || self.dist_type == 'std_copy_group_grouping') {

            folder_id = self.get_group_folder_id(student_group_id, self.group_folder_ids);
            Log.debug("create_student_file student_name = " + student_name);
            Log.debug("create_student_file folder_id = " + folder_id);
        }

        Ajax.call([{

                methodname: 'mod_googledocs_create_students_file',

                args: {
                    folder_group_id: folder_id,
                    group_id: student_group_id,
                    grouping_id: student_grouping_id,
                    instance_id: self.instance_id,
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

                    if (self.dist_type == 'std_copy_group'
                            || self.dist_type == 'std_copy_grouping'
                            || self.dist_type == 'std_copy_group_grouping') {

                        self.renderStudentLinks(urls, rownumber);

                    } else {
                        var ref = $('#' + 'link_file_' + rownumber);
                        $(ref).attr("href", urls[0]);
                    }

                    // Remove progress bar and display status
                    if (self.dist_type != 'group_copy') {
                        self.createdTagHandler($('#file_' + rownumber));
                    }

                },

                fail: function (reason) {
                    Log.error(reason);
                    $('#file_' + rownumber).removeClass('spinner-border color');
                    self.failedTag(rownumber);
                }
            }]);


    };

    // When dist. is each student from X group gets a copy. A student
    // can get more than one copy.
    GoogledocsControl.prototype.renderStudentLinks = function (urls, rownumber) {
        urls.forEach(function (url, index) {
            Log.debug('addLinks ' + rownumber);
            var ref = $('#' + 'link_file_' + rownumber);
            if (index === 0) {
                $(ref).attr("href", url);
            } else {
                var src = $(ref).find("img").attr("src");
                $(ref).append('<a target="_blank" id="link_file_' + rownumber + '"href="' + url + '" class="link_icon">\n\
                            <img src="' + src + '" class="link_icon"</a>');
            }
        }, rownumber);
    };

    GoogledocsControl.prototype.callGroupFileService = function () {

        var owner_email = $('table.overviewTable').attr('data-owner-email');
        var self = this;

        $('tbody').find('[data-group-name]').each(function () {
            self.countCalls++;
            var group_name = $(this).attr('data-group-name');
            var groupid = $(this).attr('data-group-id');
            var a_element = ($(this).find('#shared_link_url_' + groupid))[0]; //It is always the one element.
            ($(this).find('div#status_col')).addClass('spinner-border color');
            self.create_group_file(a_element, owner_email, group_name, groupid);

        });

    };

    GoogledocsControl.prototype.callGroupingFileService = function () {

        var self = this;
        var owner_email = $('table.overviewTable ').attr('data-owner-email');
        self.create_grouping_file(owner_email);
    };

    GoogledocsControl.prototype.callGroupGroupingFileService = function () {
        var owneremail = $('table.overviewTable').attr('data-owner-email');
        var instanceid = $('table.overviewTable').attr('data-instance-id');
        var self = this;

        $('tbody').find('[data-g-name]').each(function () {
            self.countCalls++;
            var gname = $(this).attr('data-g-name');
            var gid = $(this).attr('data-g-id');
            var gtype = $(this).attr('data-g-type');
            var aelement = ($(this).find('#shared_link_url_' + gid))[0]; //It is always the one element.
            var statuscol = ($(this).find('#status_col_' + gid));
            ($(this).find('div#status_col_' + gid)).addClass('spinner-border color');
            self.create_group_grouping_file(aelement, statuscol, owneremail, instanceid, gname, gid, gtype);

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
    GoogledocsControl.prototype.create_group_file = function (a_element = '',
            owner_email, group_name = '', group_id, parentfile_id = 0, grouping_id = 0) {

        var self = this;

        if (!self.grouping_sharing) {
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
                    Log.debug("mod_googledocs_create_group_file " + JSON.stringify(response));
                    if (self.dist_type == 'dist_share_same_group'
                            || self.dist_type == 'dist_share_same_grouping_copy'
                            || self.dist_type == 'dist_share_same_group_grouping_copy') {
                        self.append_links_to_icons(response.url); //  Traverse the list of students and add the link according to the group they belong to.
                    } else {
                        // Add file's link
                        $(a_element).attr("href", response.url);
                        //Returns the ID of the file created for the group.
                        Log.debug('Group ID ' + group_id);
                        self.callStudentFileServiceForGroup(response.googledocid, group_id);
                    }
                    if (response.isfoldertype != true) {
                        self.files_to_erase.push(parentfile_id);
                    }

                },
                fail: function (reason) {
                    Log.error(reason);
                }
            }]);


    };

    /**
     * Creates the file FileName_GroupingName for all the groupings selected.
     * The same url is shared with the members of the grouping groups.
     * @param String grouping_name
     * @param  int grouping_id
     * @param String owner_email
     */
    GoogledocsControl.prototype.create_grouping_file = function (owner_email) {
        var self = this;
        Log.debug("create_grouping_file llamada");

        Ajax.call([{
                methodname: 'mod_googledocs_create_grouping_file',
                args: {
                    owneremail: owner_email,
                    parentfileid: self.parentfile_id,
                },
                done: function (response) {
                    Log.debug(response);
                    $('tbody tr').each(function () {
                        self.countCalls++;
                        var id = $(this).attr('data-grouping-id');  // Get the grouping id
                        self.get_grouping_url(id, JSON.parse(response.groupingsurl));
                    });

                },
                fail: function (reason) {
                    Log.error(reason);
                }
            }]);
    };

    GoogledocsControl.prototype.create_group_grouping_file = function (aelement = '', statuscol = '', owneremail,
            instanceid, gname, gid, gtype) {
        var self = this;
        Ajax.call([{
                methodname: 'mod_googledocs_create_group_grouping_file',
                args: {
                    gid: gid,
                    gname: gname,
                    gtype: gtype,
                    instanceid: instanceid,
                    owneremail: owneremail,
                    parentfileid: self.parentfile_id,
                },
                done: function (response) {
                    // Add file's link.
                    $(aelement).attr("href", response.url);
                    // Change status.
                    self.createdTagHandler(statuscol);
                },
                fail: function (reason) {
                    Log.error(reason);
                }
            }]);
    };


    //Dist. Each student from a group gets a copy or each std. from a group share same copy. Create group folders
    GoogledocsControl.prototype.create_group_folder = function () {
        var self = this;
        Log.debug("create_group_folder " + self.instance_id);
        Ajax.call([{
                methodname: 'mod_googledocs_create_group_folder_struct',
                args: {
                    instanceid: self.instance_id,
                },
                done: function (response) {
                    Log.debug('mod_googledocs_create_group_folder_struct');
                    self.group_folder_ids = JSON.parse(response.group_folder_ids);
                    // Each student will get a copy.
                    if (self.dist_type == 'std_copy_group'
                            || self.dist_type == 'std_copy_grouping'
                            || self.dist_type == 'std_copy_group_grouping') {

                        self.callStudentFileService(self.parentfile_id);
                    }

                    // Create the copy and the owner is the teacher.
                    if (self.dist_type == 'dist_share_same_group'
                            || self.dist_type == 'dist_share_same_grouping_copy'
                            || self.dist_type == 'dist_share_same_group_grouping_copy') {

                        var g_ids = $('table.overviewTable').attr('data-all-groups');
                        var owner_email = $('table.overviewTable').attr('data-owner-email');
                        self.create_group_file('', owner_email, '', g_ids, self.parentfile_id);
                    }

                },
                fail: function (reason) {
                    Log.error(reason);
                }
            }]);
    };

    GoogledocsControl.prototype.shareSameGroupGroupingService = function () {
        var self = this;
        var g_ids = $('table.overviewTable').attr('data-all-groups');
        var owner_email = $('table.overviewTable').attr('data-owner-email');
        self.create_group_file('', owner_email, '', g_ids, self.parentfile_id);

    };

    /**
     * 
     * @param {type} group_id
     * @param {type} group_folder_ids
     * @returns {unresolved}
     */
    GoogledocsControl.prototype.get_group_folder_id = function (group_id, group_folder_ids) {
        var groups = group_id.split('-');
        for (var i = 0; i < group_folder_ids.length; i++) {
            if (group_folder_ids[i].group_id == group_id || groups.includes(group_folder_ids[i].group_id)) {
                return group_folder_ids[i].folder_id;
                break;
            }
        }

    };

    /**
     * Get the URL(s) generated for the grouping with the given id
     * If a student belongs to more than one grouping, return all the urls
     * This function is called when the distribution is dist_share_same_grouping      
     */
    GoogledocsControl.prototype.get_grouping_url_for_student = function (id, urls, rownumber) {
        console.log("EN get_grouping_url_for_student...")
        var ids = id.split('-');
        var links = [];
        var self = this;

        for (var i = 0; i < urls.length; i++) {

            if (ids.includes(urls[i].gid)) {
                links.push(urls[i].url);
            }
        }
        Log.debug(links);

        if (self.dist_type == 'dist_share_same_group') {
            self.append_links_helper(links, id);
        } else {
            self.append_links_helper(links, rownumber);

        }
    };

    /**
     * Get the URL generated for the grouping with the given id
     * This function is called when the distribution is grouping_copy
     */
    GoogledocsControl.prototype.get_grouping_url = function (id, urls) {

        var ref = $('#' + 'shared_link_url_' + id);
        var self = this;
        for (var i = 0; i < urls.length; i++) {
            if (id == (urls[i].gid)) {
                $(ref).attr("href", urls[i].url);
                var tag = $('#status_col_' + id);
                self.createdTagHandler(tag);
                break;
            }
        }

    };

    /**
     * For distributions involving GROUPS
     * @param {type} urls
     * @returns {undefined}
     */
    GoogledocsControl.prototype.append_links_to_icons = function (urls) {
        var self = this;

        $('tbody').children().each(function (e) {
            self.countCalls++;
            Log.debug('append_links_to_icons');
            var id = $(this).attr('data-group-id'); //student-group-id
            if (id != undefined) {
                self.get_grouping_url_for_student(id, JSON.parse(urls), e);
            }
        });

    };
    
    /**
     * Add the url in the href property. If the student has more than one
     * grouping, append new icons
     * Distribution = dist_share_same_grouping.
     * @param {type} urls
     * @param {type} rownumber
     * @returns {undefined}
     */
    GoogledocsControl.prototype.append_links_helper = function (urls, rownumber) {
        var self = this;

        urls.forEach(function (url, index) {
            var ref;
            if (self.dist_type == 'dist_share_same_group') {
                ref = $('#' + 'shared_link_url_' + rownumber);
            } else {
                ref = $('#' + 'link_file_' + rownumber);
            }
            var src = $(ref).find("img").attr("src");

            if (index == 0) {
                $(ref).attr("href", url); // Set link the existing icon
            } else {
                $(ref).append('<a target="_blank" id="link_file_' + rownumber +
                        '"href="' + url + '" class="link_icon">\n\ \n\
                <img src="' + src + '" class="link_icon"</a>');
            }

            $('#file_' + rownumber).removeClass('spinner-border color');
            self.tagDisplay(rownumber, true);
        });
    };
    
    return {
        init: init
    };
});