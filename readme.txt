Mass Upload Utility
-------------------------------------------------------------------------------

Version: .95
Author: Scott Tesoriere <scott@tesoriere.com>
Build Date: 22 March 2010
Github Repository: http://github.com/scottkf/massuploadutility/tree/master
Requirements: Symphony 2

A symphony extension to allow you to add a folder of files into a section that 
has an upload field, it probably doesn't work with other upload fields.

Warning: This extension requires the use of shell_exec and copy(). Shell_exec
for getting the mimetype reliably, and copy to take care of the copying!


Installation
-------------------------------------------------------------------------------

1.  Upload the 'massuploadutilty' folder in this archive to your Symphony
  'extensions' folder.

2.  Enable it by selecting the "Mass Upload Utility", choose Enable from 
  the with-selected menu, then click Apply.


Usage
-------------------------------------------------------------------------------

1.  View the System > Add Multiple Files page to jam a bunch of files into a
  section.

2.  ????


TROUBLESHOOTING
-------------------------------------------------------------------------------

INSTALLING: If you have problems installing, manually create the 
	/workspace/upload/mui directory and chmod it 777, Then try installing again.