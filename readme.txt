Mass Upload Utility
-------------------------------------------------------------------------------

Version: 2.2.1.0
Author: Scott Tesoriere <scott@tesoriere.com>
Build Date: 05 May 2011
Github Repository: http://github.com/scottkf/massuploadutility/tree/v2.2.1.0
Requirements: Symphony 2.2.1

If you're using an older version of Symphony, please use v0.9 of the Mass Upload Utility:
https://github.com/scottkf/massuploadutility/tree/v0.9

A symphony extension to allow you to add a folder of files into a section that 
has an upload field, it probably doesn't work with other upload fields.

Warning: This extension requires the use of shell_exec and copy(). Shell_exec
for getting the mimetype reliably, and copy to take care of the copying!


CHANGELOG
-------------------------------------------------------------------------------

v2.2.1.1
* Uploading via AJAX
-- Still need to figure out what to do for error checking if a file fails to validate, rollback or skip? Probably just skip and highlight it.
-- Also count how many were successful and let the user know.

v2.2.1.0
* I've integrated html5 into the extension, but if you choose a large amount of files it will timeout
* There is currently no queue or progress bar, it simply posts all the files.
* It should also now work with *ANY* upload field, but I have not tested it yet.
* It no longer uses shell exec.
* It no longer creates a directory in your workspace folder.

Installation
-------------------------------------------------------------------------------

1.  Upload the 'massuploadutility' folder in this archive to your Symphony
  'extensions' folder.

2.  Enable it by selecting the "Mass Upload Utility", choose Enable from 
  the with-selected menu, then click Apply.


Usage
-------------------------------------------------------------------------------

1. Go to the index of a section with an upload field
2. Click the button that says 'Add Many'
3. Choose default values if necessary, then click Process files, and if successful
  you will be redirected to the index. 


BUGS
-------------------------------------------------------------------------------

- If you receive an error on the form, you have to select all the files again
- No notification is received on the index after successful input
- If more than one upload field is in a section, this will fail

TROUBLESHOOTING
-------------------------------------------------------------------------------

INSTALLING: If you have problems installing, manually create the 
	/workspace/uploads/mui directory and chmod it 777, Then try installing again.
