// Standard license block omitted.
/*
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * @module mod_googledocs/update_controls
  * define(['jquery', 'core/log', 'core/ajax', 'mod_googledocs/update_controls'], function ($, Log, Ajax, UpdateControl)
  */
define(['jquery', 'core/ajax', 'core/log', 'core/notification'], function($,Ajax, Log, Notification) {
  'use strict';
    /**
     * Initializes the update controls.
     */
    function init() {
        Log.debug('mod_googledocs/SaveQuickGrading: initializing SaveQuickGrading of the mod_googledocs');
       
        var control = new GoogledocSaveQuickGrading();
        control.main();
    }

    // Constructor
    function GoogledocSaveQuickGrading (){      
        Log.debug("constructor" );
    };

    GoogledocSaveQuickGrading.prototype.main = function() {
        var self = this;
        self.saveQuickGrading();
        self.refreshDateModified;
        self.onGradeChangeHandler();
        self.onCommentChangeHandler();
    };

    GoogledocSaveQuickGrading.prototype.saveQuickGrading = function() {
        var self = this;
        $('button.submit-quickgrade').on('click', function () {

            var listOfGrades = [];
            var googledocid = $('table.gradetb').attr('data-googledocid');
            var maxgrade = $('table.gradetb').attr('data-maxgrade');
            var courseid = $('table.gradetb').attr('data-maxgrade');

            $('tbody').children().each(function(e){
                var userid = $(this).attr('data-user-id');
                var grade = $(this).find("#quickgrade_" + userid).val();
                var comment = $(this).find("#quickgrade_comments_" + userid).val();

                if( grade.length == 0 && comment.length == 0) {
                    return;
                }

                var grade = {
                    userid : userid,
                    grade : grade,
                    comment: comment,
                    googledocid: googledocid,
                    rownumber: e,
                    maxgrade:maxgrade,
                    courseid:courseid
                }
                listOfGrades.push(grade);

            });

            Ajax.call([{
                methodname: 'mod_googledocs_save_quick_grading',
                args: {
                    grades : JSON.stringify(listOfGrades)
                },
                done: function (response) {
                    Log.debug(JSON.parse(response.modifiedtimes));
                    self.refreshDateModified(JSON.parse(response.modifiedtimes));
                    Notification.addNotification({
                        message: 'The grade changes were saved',
                        type: 'success'
                    });
                },
                fail: function (reason) {
                    Notification.addNotification({
                        message: 'The grade changes were saved',
                        type: 'info'
                    });
                    Log.error(reason);
                }
            }]);

        });

    };

    GoogledocSaveQuickGrading.prototype.refreshDateModified = function(grades) {

        grades.forEach((grade) => {
            // tr is counting the header row too.
            var currentText =  $( "tr" ).eq(grade.rownumber + 1).find('input.quickgrade').val();

            $( "tr" ).eq(grade.rownumber + 1).find('input.quickgrade').val(function(i,v){
                return v.replace(currentText, grade.grade);
            });
            $( "tr" ).eq(grade.rownumber + 1).find('input.quickgrade').parent().removeClass('quickgrademodified')
            $( "tr" ).eq(grade.rownumber + 1).find('textarea.quickgrade').parent().removeClass('quickgrademodified');
            $( "tr" ).eq( grade.rownumber + 1 ).find('td.timemod').html(grade.timemodified);
            $( "tr" ).eq( grade.rownumber + 1 ).find('td.timemod').html(grade.timemodified);
            $( "tr" ).eq( grade.rownumber + 1 ).find('td.finalgrade').html(grade.finalgrade);
            $( "tr" ).eq( grade.rownumber + 1 ).find('div.submissionstatussubmitted ').html('Graded');
            //submissionstatus 
            $( "tr" ).eq( grade.rownumber + 1 ).find('div.submissionstatus ').html('Graded').addClass('alert-success');;
            
        });
    };
    
    GoogledocSaveQuickGrading.prototype.onGradeChangeHandler = function() {
        $('input.quickgrade').on('change', function (e) {
            var target = $(e.target)[0];
            $(target).parent().addClass('quickgrademodified');
        });
    };
    
    GoogledocSaveQuickGrading.prototype.onCommentChangeHandler = function() {
        $('textarea.quickgrade').on('change', function (e) {
            var target = $(e.target)[0];
            $(target).parent().addClass('quickgrademodified');
        });
    };

    return {
        init: init
    };
});