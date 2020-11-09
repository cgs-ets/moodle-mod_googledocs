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
define(['jquery', 'core/ajax', 'core/log'], function($,Ajax, Log) {
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
    };

     GoogledocSaveQuickGrading.prototype.saveQuickGrading = function() {

        $('button.submit-quickgrade').on('click', function () {
            Log.debug("Guardar los datos de los inputs con valores");
            var listOfGrades = [];
            $('tbody').children().each(function(e){
                var userid = $(this).attr('data-user-id');
                var grade = $(this).find("#quickgrade_" + userid).val();
                var comment = $(this).find("#quickgrade_comments_" + userid).val();
                /* False for null,undefined,0,000,"",false. True for string "0" and whitespace " ".*/
                if( grade.length == 0 && comment.length == 0) {
                    return;
                }

                var grade = {
                    id : userid,
                    grade : grade,
                    comment: comment
                }
                listOfGrades.push(grade);

            });

            Ajax.call([{
                methodname: 'mod_googledocs_save_quick_grading',
                args: {
                    grades : JSON.stringify(listOfGrades)
                },
                done: function (response) {
                    Log.debug(response);
                },
                fail: function (reason) {
                    Log.error(reason);
                }
            }]);

        });

    };

    return {
        init: init
    };
});