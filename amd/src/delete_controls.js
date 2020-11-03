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

/*
 * @package    mod_googledocs
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
 /**
  * @module mod_googledocs/delete_controls
  * 
  */
define(['core/ajax', 'core/log'], function(Ajax, Log) {
  'use strict';
    /**
     * Initializes the delete controls.
     */
    function init(filesToDelete, dist_type) {
        Log.debug('mod_googledocs/delete_controls: initializing delete_controls of the mod_googledocs');
        Log.debug(filesToDelete);
        Log.debug(dist_type);
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