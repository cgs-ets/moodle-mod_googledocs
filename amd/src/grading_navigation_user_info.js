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
  * @module mod_googledocs/grading_navigation_user_info
  * 
  */
define(['jquery', 'core/ajax', 'core/log', 'core/templates', 'core/notification'], 
        function($, Ajax, Log, Templates, Notifications) {
  'use strict';
    /**
     * Initializes the delete controls.
     */
    function init() {
        Log.debug('mod_googledocs/grading_navigation_user_info: initializing grading_navigation_user_info of the mod_googledocs');
        var region = $('[data-region="user-info"]').first();
        var control = new GradingNavUserInfo(region);
        control.main();
    }

    // Constructor
    function GradingNavUserInfo (region){
        var self = this;
        self.region = region;
        Log.debug(region);
        alert("ENTRO al constructor");
    };

    GradingNavUserInfo.prototype.main = function() {
        var self = this;
        //self.getUserInfo();
        Log.debug("ESTOY EN EL MAIN");

    }

    GradingNavUserInfo.prototype.getUserInfo = function() {

        var self = this;

         Ajax.call([{
            methodname: 'mod_googledocs_get_participant',
            args: {
                userid: self.userid,
                googledocid: self.googledocid
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