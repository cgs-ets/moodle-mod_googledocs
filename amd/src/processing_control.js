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

/**
 * @module mod_googledocs/control
 */
define(['jquery', 'core/log', 'core/str'], function ($, Log, str) {
    'use strict';

    /**
     * Initializes the block controls.
     */
    function init() {
        Log.debug('mod_googledocs/processing_control');
        var saveAndDisplay = '#id_submitbutton';
        var selectGroup = '#id_groups';
        var selectGrouping = '#id_groupings'
        var control = new GoogledocsProcessingControl(saveAndDisplay, selectGroup, selectGrouping);
        control.main();
    }

    // Constructor.
    function GoogledocsProcessingControl(saveAndDisplay,  selectGroup, selectGrouping) {
        var self = this;
        self.saveAndDisplay = saveAndDisplay;
        self.selectGroup = selectGroup;
        self.selectGrouping = selectGrouping;

    };

    GoogledocsProcessingControl.prototype.main = function () {
        var self = this;
        self.processingMessageDisplay();
    };

    GoogledocsProcessingControl.prototype.processingMessageDisplay = function() {
        // Handle submit click.
        var self = this;
        $(self.saveAndDisplay).on('click', function() {
            $("<div class='d-flex flex-column align-items-center justify-content-center overlay'>"
                + "<div class='spinner-border processing' role='status'>"
                + "<span class='sr-only'>Loading...</span>"
                + "</div>"
                + "<div class = 'process_message'>\n\
                   <p>Saving to Google Drive. <br>\n\
                        This process can take some time.<br> \n\
                        Please do not close the browser.</p>\n\
                    </div></div>").appendTo('#page-content');

            });
    };
    GoogledocsProcessingControl.prototype.toggle = function(){
      var self = this;

      $(self.selectGroup).on('change', function() {
        var ids = [];
        $( "select#id_groups option:selected" ).each(function(ids) {
            ids.push($(this)[0].value );
            if ($(this)[0].value == 0) {
                return false;
            }

        });
        
        console.log(ids);

    });
    };

    return {
         init: init
    };
 });