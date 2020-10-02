# moodle-mod_googledocs

Googledocs is an activity module to create and distribute G Suite files to students enrolled in a course.

# Authors
Michael de Raadt  
Veronica V. Bermegui <veronica.v.bermegui@gmail.com>

# Installation

## Clone
 * Clone this repository into the mod folder and rename it to googledocs. The following command does both steps:  
  https://github.com/cgs-ets/moodle-mod_googledocs.git googledocs  
 * Log into Moodle as an administrator.  
  Open the administration area http://your-moodle-site/admin to start the installation automatically. 

# Configuration

Googledocs uses OAuth 2 services.   

To configure the issuer (Google) follow the steps in Moodle's documentation.
https://docs.moodle.org/39/en/OAuth_2_Google_service. 

After installing the module, you are requested to provide an API Key. This key is generated in your Google API console. 

## Use

1. Navigate to a course.
2. Click on "Turn editing on".
3. Click on Add an activity or resource
4. Select the activity Google Doc and add it.
5. Click on the button with the name "Log in to your account".
6. Select the Google account you want to use.
7. Fill the form to create your file.
8. Click on Save and display. 
9. The generation and distribution of files is done as a set of Ajax calls. It is neccesary not to close the browser window while this process takes place.


