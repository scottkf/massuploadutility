Multiple Upload Injector
-------------------------------------------------------------------------------

Version: 2.2.1
Author: Scott Tesoriere <scott@tesoriere.com>
Build Date: 04 May 2011
Github Repository: http://github.com/scottkf/massuploadutility/tree/v2.2.1
Requirements: Symphony 2.2.1

If you're using an older version of Symphony, please use v0.9 of the Mass Upload Utility:
https://github.com/scottkf/massuploadutility/tree/v0.9

A symphony extension to allow you to add a folder of files into a section that 
has an upload field, it probably doesn't work with other upload fields.

Warning: This extension requires the use of shell_exec and copy(). Shell_exec
for getting the mimetype reliably, and copy to take care of the copying!


Installation
-------------------------------------------------------------------------------

1.  Upload the 'massuploadutility' folder in this archive to your Symphony
  'extensions' folder.

2.  Enable it by selecting the "Mass Upload Utility", choose Enable from 
  the with-selected menu, then click Apply.


Usage
-------------------------------------------------------------------------------

There are two ways:

A)
1a. Go to the index of a section with a supported field (currently only the upload)
2a. Upload the files, click the button that says 'Process Files'
3a. Choose default values if necessary, then click Process files, and if successful
  you will be redirected to the index. I still need to add a notification.



B)
1b.  View the System > Mass Upload Utility page to jam a bunch of files into a
  section.
2b.  Upload Files
3b.  Click the button that says 'Process'


TODO
-------------------------------------------------------------------------------
- Remove the Section listing from the MUU if a source is already chosen


TROUBLESHOOTING
-------------------------------------------------------------------------------

INSTALLING: If you have problems installing, manually create the 
	/workspace/uploads/mui directory and chmod it 777, Then try installing again.
