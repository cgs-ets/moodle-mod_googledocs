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
 define(['jquery', 'core/log'], function ($, Log) {
    'use strict';

    /**
     * Initializes the block controls.
     */
    function init() {
        Log.debug('mod_googledocs/control: initializing controls of the mod_googledocs');
        var saveAndReturn = '#id_submitbutton2';
        var saveAndDisplay = '#id_submitbutton';
        var control = new GoogledocsControl(saveAndReturn, saveAndDisplay);
        control.main();
    }

    // Constructor.
    function GoogledocsControl(saveAndReturn, saveAndDisplay) {
        var self = this;
        self.saveAndReturn = saveAndReturn;
        self.saveAndDisplay = saveAndDisplay;
    }

    GoogledocsControl.prototype.main = function () {
        var self = this;
        self.ProcessingMessageDisplay(self.saveAndDisplay);
        self.ProcessingMessageDisplay(self.saveAndReturn);

    };

        GoogledocsControl.prototype.ProcessingMessageDisplay = function(buttonId) {
            // Handle submit click.

            $(buttonId).on('click', function() {
                $("<div class='d-flex flex-column align-items-center justify-content-center overlay'>"
                        + "<div class='spinner-border processing' role='status'>"
                        + "<span class='sr-only'>Loading...</span>"
                        + "</div>"
                        + "<div class = 'process_message'>\n\
                               <p>Saving files into My Drive. <br>\n\
                                  The process can take sometime.<br> \n\
                                  Please do not close the browser.</p>\n\
                            </div></div>").appendTo('#page-content');

            });
        };
        return {
            init: init
        };
 });