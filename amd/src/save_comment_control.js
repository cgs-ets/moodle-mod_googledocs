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
    function init(texto) {
        Log.debug('mod_googledocs/comments: initializing update_controls of the mod_googledocs');
       
        var control = new GoogledocSaveCommentControl(texto);
        control.main();
    }

    // Constructor
    function GoogledocSaveCommentControl (texto){
        var self = this;
        self.texto =texto;
        Log.debug("constructor" + texto);
    };

    GoogledocSaveCommentControl.prototype.main = function() {
        var self = this;
        self.updateSharing();
    };

     GoogledocSaveCommentControl.prototype.updateSharing = function() {

        $('button.submit-comment').on('click', function () {

            alert(this.texto);
        });

    };

    return {
        init: init
    };
});