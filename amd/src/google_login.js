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
 * Provides the mod_googledocs/submit_controls
 *
 * @package   mod_googledocs
 * @category  output
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googledocs/submit_controls
 */

define(['jquery', 'core/ajax', 'core/log'], function ($, Ajax, Log) {
    var init = function () {

        $("button#googlebtn").on('click', function () {
            Ajax.call([{
                    methodname: 'mod_googledocs_google_login_student',
                    args: {

                    },
                    done: function (response) {
                        Log.debug("mod_googledocs_google_login_student finished");
                        var res = JSON.parse(response.result);
                        var loginbutton = res.loginbutton;
                        $('button#googlebtn').replaceWith(loginbutton);
                        $('#googleloginbtn').trigger('click');
                    },
                    fail: function (reason) {
                        Log.error(reason);
                    }
                }]);
        });
        $("button#googlebtnlogout").on('click', function(){          
            window.open("https://accounts.google.com/logout", 'Logout', 'width=600,height=800');
            $("button#googlebtn").removeClass('disabled');
            $("button#googlebtnlogout").addClass('disabled');
            $("[data-toggle='tooltip']").tooltip('hide');
            // Disable the button
            var input = this;           
            input.disabled = true;
        });
    };

    return {
        'init': init
    };
});