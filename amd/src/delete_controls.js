// Standard license block omitted.
/*
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * @module mod_googledocs/delete_controls
  * define(['jquery', 'core/log', 'core/ajax', 'mod_googledocs/delete_controls'], function ($, Log, Ajax, DeleteControl)
  */
define(['jquery', 'core/ajax', 'core/log'], function($, Ajax, Log) {
  'use strict';
    /**
     * Initializes the delete controls.
     */
    function init(filesToDelete, dist_type) {
        Log.debug('mod_googledocs/delete_controls: initializing delete_controls of the mod_googledocs');
        
        var control = new GoogledocsDeleteControl(filesToDelete, dist_type);
        control.main();
    }

    // Constructor
    function GoogledocsDeleteControl (filesToDelete, dist_type){
        var self = this;
        self.filesToDelete = filesToDelete;
        self.dist_type = dist_type;
    };

    GoogledocsDeleteControl.prototype.main = function() {
        var self = this;
        self.deleteFiles();
    };

     GoogledocsDeleteControl.prototype.deleteFiles = function() {

         var self = this;

         Ajax.call([{
            methodname: 'mod_googledocs_delete_files',
            args: {
                dist_type: self.dist_type,
                file_ids: self.filesToDelete
            },
            done: function (response) {
                Log.debug(response);
            },
            fail: function (reason) {
                Log.error(reason);
            }
        }]);

    };

    return {
        init: init
    };
});