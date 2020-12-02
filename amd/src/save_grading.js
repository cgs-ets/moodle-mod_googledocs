// Standard license block omitted.
/*
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * @module mod_googledocs/update_controls
  * 
  */
define(['jquery', 'core/ajax', 'core/log','core/str', 'core/notification'], 
    function($, Ajax, Log, str, notification) {
  'use strict';
    /**
     * Initializes the update controls.
     */
    function init() {
        Log.debug('mod_googledocs/SaveGrading: initializing SaveGrading of the mod_googledocs');
        var control = new GoogledocSaveGrading();
        control.main();
    }

    // Constructor
    function GoogledocSaveGrading (){
        var self = this;
    };


    GoogledocSaveGrading.prototype.main = function() {
        var self = this;
        self.get_users();
        self.saveGrading();
    };
    GoogledocSaveGrading.prototype.get_users = function() {
        var select = $('[data-region="user-selector"]').find('[data-action=change-user]');  
        var googledocid = select.attr('data-googledocid');
        var groupid = select.attr('data-groupid');

        Ajax.call([{
            methodname: 'mod_googledocs_get_participants',
            args: {googledocid: googledocid, groupid: groupid},
            done: this._setUsers.bind(this),
            fail: notification.exception
        }]);
        return true;
    };

    GoogledocSaveGrading.prototype._setUsers = function(users) {
        this.users = JSON.parse(users.users);
    };

    GoogledocSaveGrading.prototype.saveGrading = function() {
        var self = this; 
        var buttonpressed;
       
        $('input[name="savechanges"').click(function() {
            buttonpressed = $(this).attr('name');
        });
        $('input[name="saveandshownext"').click(function() {
            buttonpressed = $(this).attr('name');
        });

        $("#gradeform").on('submit', function (e) {

            var gradeval = parseFloat($('input').first().val());
            if (isNaN(gradeval) || gradeval > 100 || gradeval < 0) {
               $("#id_error_grade").removeAttr('hidden');
               e.stopImmediatePropagation();
               return false;
            } else {
                $("#id_error_grade").attr('hidden', true);
                $('[data-region="overlay"]').show();
            }
            e.preventDefault();

            var grade = {
                userid : $('[data-region="user-info"]').attr('data-userid'), //data returns old values
                googledocid: String($('[data-region="user-info"]').data('googledocid')),
                courseid: String($('[data-region="googledoc-info"]').data('courseid')),
                formdata: $(this).serialize()
            };

            Ajax.call([{
                methodname: 'mod_googledocs_save_quick_grading',
                args: {
                    grade : JSON.stringify(grade),
                },
                done: self._handleFormSubmissionResponse.bind(this, buttonpressed, self),
                fail: function (reason) {
                        Log.error(reason);
                    }
            }]);
        });

    };

    GoogledocSaveGrading.prototype._handleFormSubmissionResponse = function(formdata, savegradingobject, response) {

        var nextUserId = $('select.custom-select option:selected').next().val();
        console.log(response);
       
        str.get_strings([
            {key: 'changessaved', component: 'core'},
            {key: 'gradechangessaveddetail', component: 'mod_googledocs'},
        ]).done(function(strs) {
            notification.alert(strs[0], strs[1]);
        }).fail(notification.exception);
        
        if (formdata == 'savechanges') {
            if ($('.grade-input').val() != '' || $('.grade-input').val() !='0.00') {
                $('span.gradedtag').removeAttr('hidden');
            }
//            $('.textarea-comment').val('');
//            $('.grade-input').val('');
        } else {
           savegradingobject.get_next_user(nextUserId, savegradingobject); 
        }

        $('[data-region="overlay"]').hide();
    };
    
    GoogledocSaveGrading.prototype.get_next_user = function (nextuserid, savegradingref) {
        var currentuserid = $('[data-region="user-info"]').attr('data-userid');
        var googledocid = String($('[data-region="user-info"]').data('googledocid'))
         Ajax.call([{
            methodname: 'mod_googledocs_get_next_participant_details',
            args: {
                    userid : nextuserid,
                    googledocid: googledocid
                },
            done:function(response){
                    Log.debug(('Grade values retrieved successfuly.'));
                    $(document).trigger('user-changed', nextuserid);   // Refresh name
                    $('select.custom-select option:selected').next().attr('selected', 'selected');
                    $(`select.custom-select option[value='${currentuserid}']`).removeAttr('selected'); // Refresh select                  
                    var url = new URL(window.location); //refresh url
                    url.searchParams.get('userid');
                    url.searchParams.set('userid', nextuserid);
                    // We do this so a browser refresh will return to the same user.
                    window.history.replaceState({}, "", url);

                    savegradingref.refreshGradePanel(response.html);
            },
           fail: function(reason) {
                Log.error('mod_googledocs_get_participant_by_id. Unable to get elements');
                Log.debug(reason);
            }
        }]);
    };

    GoogledocSaveGrading.prototype.refreshGradePanel = function (htmlResult) {
        var region = $('[data-region="grade"]');

        region.fadeOut(300, function() {
            region.replaceWith(htmlResult);
            region.show();
            $("#gradeform").on('submit', GoogledocSaveGrading.prototype.saveGrading()); //Re-attach the event
        });
    };
    

    return {
        init: init
    };
});