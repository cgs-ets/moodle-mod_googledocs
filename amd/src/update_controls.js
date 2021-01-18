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
define(['core/ajax', 'core/log'], function (Ajax, Log) {
    'use strict';
    /**
     * Initializes the update controls.
     */
    function init(file_ids, instance_id) {
        Log.debug('mod_googledocs/update_controls: initializing update_controls of the mod_googledocs');
        Log.debug(file_ids);
        Log.debug(instance_id);

        var control = new GoogledocsUpdateControl(file_ids, instance_id);
        control.main();
    }

    // Constructor
    function GoogledocsUpdateControl(file_ids, instance_id) {
        var self = this;
        self.file_ids = file_ids;
        self.instance_id = instance_id;
    }

    GoogledocsUpdateControl.prototype.main = function () {
        var self = this;
        self.updateSharing();
    };

    GoogledocsUpdateControl.prototype.updateSharing = function () {

        var self = this;

        Ajax.call([{
                methodname: 'mod_googledocs_update_sharing',
                args: {
                    file_ids: self.file_ids,
                    instance_id: self.instance_id
                },
                done: function (response) {
                    Log.debug('mod_googledocs_update_sharing ');
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