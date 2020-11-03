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
 * Provides the mod_googledocs/modal_grade
 *
 * @package   mod_googledocs
 * @category  output
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_googledocs/submit_controls
 */

define(['jquery',  'core/ajax', 'core/modal_factory', 'core/templates', 'core/modal_events',  'core/log'],
        function ($, Ajax, ModalFactory, Templates, ModalEvents, Log) {

            var init = function (studentid, instanceid) {

                $('button#submitbtn_' + studentid).on('click', function (e) {

                    var docname = $('table.overviewTable').attr('data-file-name');   
                    var url = $('tbody').find("tr[data-student-id='"+studentid+"']").find('a')[1];
                    var src = $(url).attr('href');
                    Log.debug(src);
                    ModalFactory.create({
                        title: docname,
                        body: '<iframe height="600" width="750" \n\
                                src="'+src+ '"> \n\
                                Your browser does not diplay iFrames\n\
                                </iframe>',
                        footer: "<div class='grade' style='margin-right:40%'><label for='fgrade'>Grade</label>\n\
                                 <input type='text' id='fgrade_"+studentid+"' name='fgrade' >\n\
                                 </div><button type='button' class='btn btn-primary' data-action='save'>Save</button>\n\
                                <button type='button' class='btn btn-primary' data-action='next'>Next</button>\n\
                                <button type='button' class='btn btn-secondary' data-action='cancel'>Cancel</button>",
                        large: true
                    }).done(function (modal) {

                    $(modal.footer[0]).find("button[data-action='save']").on('click', function(){

                        var gradeInput =  $('div.grade').find('input#fgrade_'+studentid).val();
                        var grade = parseFloat(gradeInput);

                        if ($('div.grade').find('p.grademessage').length > 0) {
                            $('div.grade').find('p.grademessage').remove();
                        }


                            if(isNaN(grade)) {
                                $( ".grade" ).append( "<p class='alert-danger alert grademessage'>Please provide a valid grade</p>" );

                            } else {
                                Ajax.call([{
                                  methodname: 'mod_googledocs_grade_student_file',
                                      args: {
                                              googledocid: instanceid,
                                              userid: studentid,
                                              grade: grade,

                                              },
                                      done: function (response) {
                                              var button = $('div.modal-footer').find("button[data-action='save']");
                                              $(button).prop('disabled',true);
                                              $('div.grade').find('input#fgrade_118').val(""); // Erase vale from input.
                                              $( ".grade" ).append( "<p class='alert-success alert grademessage'>Grade saved</p>" );
                                              Log.debug(response);
                                          },
                                      fail: function (reason) {
                                              Log.error(reason);
                                          }
                              }]);

                            }

                        });

                        $(modal.footer[0]).find("button[data-action='next']").on('click', function(e){
                            Log.debug("next CLICKED");
                        });

                        $(modal.footer[0]).find("button[data-action='cancel']").on('click', function(e){   
                          modal.hide();

                        });


                        modal.show();
                    });


                });
            };

            return {
                init: function (studentid, instanceid) {
                    init(studentid, instanceid);
                }
            };
        });