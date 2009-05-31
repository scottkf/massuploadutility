<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.authormanager.php');	


	/*
		Caveats:
		- It rolls back all entries if any of them error (file doesn't validate, can't copy, you left some fields, duplicates)
	
	
		Issues: 
		- sloppy as shit
		- the error logic is weird and slapped together, and needs to be fixed
		- probably only works with the default upload field
		- 
	
	*/

	class contentExtensionMassUploadUtilityInject extends AdministrationPage {
		protected $_action = '';
		protected $_ignored_files = array();
		protected $_driver = null;
		protected $_errors = array();
		protected $_entries_count = '';
		protected $_uri = null;
		protected $_valid = false;
		protected $_section_id = 0;
		protected $_message = '';
		protected $_files = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/massuploadutility/inject';
			$this->_driver = $this->_Parent->ExtensionManager->create('MassUploadUtility');
		}
		
		function __switchboard($type='view'){
			$function = ($type == 'action' ? '__action' : '__view') . ucfirst($this->_context[0]);

			if(!method_exists($this, $function)) {
				
				## If there is no action function, just return without doing anything
				if($type == 'action') return;
				
				$this->_Parent->errorPageNotFound();
				
			}
			
			$this->$function();

		}
				
		public function build($context) {
			if (!empty($this->_errors))
				$this->_valid = false;
			parent::build($context);
		}
		

		function view(){			
			$this->__switchboard();	
		}
		
		function action(){			
			$this->__switchboard('action');		
		}
		
		function __action() {
			$this->_action = 'view';
		}
		
		function __actionDo(){
			if (!isset($_POST['fields']['source']) or $_POST['fields']['source'] <= 0)
				{ $this->_errors[] = 'You didn\'t choose a source, perhaps you don\'t have any sections with an upload field in them?'; $this->_valid = false; return; }
			if (!isset($_POST['fields']['sourcedir']) or !preg_match('/^\/workspace\/uploads\/mui/i', $_POST['fields']['sourcedir']))
				{ $this->_errors[] = 'Fail!'; $this->_valid = false; return; }
			

			$this->_section_id = $_POST['fields']['source']; // section id
			$entryManager = new EntryManager($this->_Parent);


			$sectionManager = new SectionManager($this->_Parent);
	    $section = $sectionManager->fetch($this->_section_id);

			// get all the fields for the types we support, and get ready to put the filename in them
			foreach ($this->_driver->getTypes() as $type) {
				$f = $section->fetchFields($type);
				if (count($f) > 0)
					foreach ($f as $field) $field_names[] = $field; //array($field->get('element_name'), $field->get('destination'));
			}
			$files = General::listStructure(DOCROOT . $_POST['fields']['sourcedir']);

			if (count($files['filelist']) == 0)
				{ $this->_errors[] = "There are no files in this directory: {$_POST['fields']['sourcedir']}."; $this->_valid = false; return; }

			// a list of all the entries so we can rollback
			$entries = array();

			foreach ($files['filelist'] as $k=>$f) {
				$continue = false;
				$this->_files[] = $f;
				$entry =& $entryManager->create();
				$entry->set('section_id', $this->_section_id);
				$entry->set('author_id', $this->_Parent->Author->get('id'));
				$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
				$chkfields = $fields = $_POST['fields'][$this->_section_id];
				// loop over all the supported fields
				foreach ($field_names as $field) {
					$dest = $field->get('destination');
					$name = $field->get('element_name');
					$tmp_name = DOCROOT . $_POST['fields']['sourcedir'] . '/' . $f;
					$new_name = DOCROOT . $dest . '/' . $f;
					/* if you don't want to rollback implement this */
					// if($field->get('validator') != NULL){
					//     $rule = $field->get('validator');
					// 		
					// 		// skip this file since it doesn't validate
					//     if(!General::validateString($tmp_name, $rule)) {
					// 			;
					// 			// $continue = true;
					// 		}
					// }
					$type = trim(shell_exec('file -b --mime '.escapeshellarg($tmp_name)));
					$size = filesize($tmp_name);

					// setup fields to check the post
					$chkfields[$name][name] = $f;
					$chkfields[$name][type] = $type;
					$chkfields[$name][tmp_name] = $tmp_name;
					$chkfields[$name][error] = 0;
					$chkfields[$name][size] = $size;
					
					// an array to copy the files after
					$copy[] = array($tmp_name, $new_name);

					// setup upload fields as they should be as if they were processed
					$fields[$name][file] = preg_replace("/^\/workspace/", '', $dest) . '/' . $f;
					$fields[$name][size] = $size;
					$fields[$name][mimetype] = $type;
					$fields[$name][meta] = serialize($this->getMetaInfo(DOCROOT . $fields[$name][file], $type));
				}

				// skip the file if it doesn't validate
				// if ($continue == true) continue;
				if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($chkfields, $this->_errors))
				{ $this->_ignored_files[] = $f; break; }

				// now we can copy the files to their new location since everything's validated
				foreach ($copy as $c) {
					if (@copy($c[0], $c[1])) {
						@chmod($c[1], intval(0755, 8));
					}
					else { $this->_errors[] = "Couldn't copy the files to the {$dest} directory. "; return; }
				}

				// setup the data, process it
				if(__ENTRY_OK__ != $this->setDataFromPost($entry, $fields, $this->_errors, false, false, $entries))
				{ $this->_ignored_files[] = $f; break; }

				// commit the entry if we made it
				if(!$entry->commit()){
					define_safe('__SYM_DB_INSERT_FAILED__', true);
				}
				else
					$this->_valid = true;

			}

			// rollback, delete all entries by id
			if ($this->_valid == false && count($entries) > 0) {

				$entryManager->delete($entries);
				return;
			}
			// if we made it here, and they want us to delete the files, it shall beDOCROOT . $_POST['fields']['sourcedir']
			if (isset($_POST['fields']['remove']) && 
					$_POST['fields']['remove'] == 'on' && 
					$this->_valid == true) {
				foreach ($files['filelist'] as $k=>$f)
					unlink(DOCROOT . $_POST['fields']['sourcedir'].'/'.$f);

				// already sanitized the sourcedir so no one can accidentally delete stuff 
				// 	from anywhere but the uploads directory, make sure not to delete mui dir
				if ($_POST['fields']['sourcedir'] != '/workspace'.$this->_driver->getMUI()) {
					rmdir(DOCROOT . $_POST['fields']['sourcedir'].'/');
				}
			}
			$this->_entries_count = count($files['filelist']) - count($this->_ignored_files);
		}
		

		/*-------------------------------------------------------------------------
			Index
		-------------------------------------------------------------------------*/

		public function __view() {
			$this->__viewIndex();
		}
		public function __viewIndex() {
			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle('Symphony &ndash; Add Multiple Files From a Folder');
			$this->appendSubheading('Upload!');

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Essentials'));


			$p = new XMLElement('p');
			$p->setAttribute('id', 'guideme');
			$p->setValue('Upload some files. They will be put under a directory named: <b>/workspace'.$this->_driver->getMUI().'/'.date('Y-m-d').'.</b>');

			$fileinput = new XMLElement('div');
			$fileinput->setAttribute('id', 'fileInput');

			$input = Widget::Input('fileInput', null, 'file');
			$input->setAttribute('style', 'display:none');
			$input->setAttribute('height', '30');
			$input->setAttribute('width', '110');
			$input->setAttribute('id', 'fileInput');
			
			$script = new XMLElement('script');
			$script->setAttribute("type", 'text/javascript');
			$folder_name = date("Y-m-d");
			$path = preg_replace('/^http\:\/\/.*\//i', '', URL);
			if (preg_match('/http\:\/\//i', $path)) $path = '';
			if (!General::realiseDirectory(WORKSPACE.$this->_driver->getMUI().'/'.$folder_name, intval('0755', 8)))
				; // could already exist echo "failed!";
			// echo $path;
			// echo WORKSPACE.$this->upload.'/'.$folder_name;
			// echo $_SERVER['DOCUMENT_ROOT']."/".$path."/workspace".$this->_driver->getMUI()."/".$folder_name;
			// echo $folder_name;
			$js = "
				$(document).ready(function() {
					$('#fileInput').fileUpload ({
						'uploader'  : '".URL."/extensions/massuploadutility/assets/uploader.swf',
						'script'    : '".(($path != '') ? '/'.$path : '')."/extensions/massuploadutility/assets/upload.php',
						'cancelImg' : '".URL."/extensions/massuploadutility/assets/cancel.png',
						'auto'      : true,
					  'displayData': 'speed',
					  'simUploadLimit': 2,
						'folder'    : '".(($path != '') ? '/'.$path : '')."/workspace".$this->_driver->getMUI()."/".$folder_name."',
					  'multi'			: true,
			      'onAllComplete': function(event, queueID, fileObj, response, data) { $('#guideme').html('Upload complete! <b>Add more or click the button that says Process Files!</b>'); $('#uploadcomplete').show(); },
						'onError': function (a, b, c, d) {
							if (d.status == 404)
								alert('Could not find upload script. Use a path relative to: '+'<?= getcwd() ?>');
							else if (d.type === \"HTTP\")
								alert('error '+d.type+\": \"+d.status);
							else if (d.type ===\"File Size\")
								alert(c.name+' '+d.type+' Limit: '+Math.round(d.sizeLimit/1024)+'KB');
							else
								alert('error '+d.type+\": \"+d.text);
						}
					});
				});
				";
			$script->setValue($js);
			
			$div = new XMLElement('div');
			$div->setAttribute('id', 'fileInputQueue');
			$div->setAttribute('class', 'fileUploadQueue');
			
			$queue = new XMLElement('a');
			$queue->setAttribute('href', "javascript:$('#fileInput').fileUploadClearQueue();");
			$queue->setValue('Clear Queue');

			$fieldset->appendChild($p);
			$fieldset->appendChild($fileinput);
			$fieldset->appendChild($script);
			$fieldset->appendChild($div);
			$fieldset->appendChild($queue);

			/* now the section fields */
			$submit = Widget::Input('action[save]','Process files','button', array('accesskey' => 's'));
			$submit->setAttribute('onClick', "window.location='".$this->_uri."/do'");
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			$div = new XMLElement('div');
			$div->setAttribute('class', 'uploadcomplete');
			$div->setAttribute('id', 'uploadcomplete');
			$div->setAttribute('style', 'display:none');
			$actions->appendChild($submit);
			$div->appendChild($actions);
			// <p><strong>Multiple File Upload</strong></p>
			// <input style="display: none;" id="fileInput2" name="fileInput2" height="30" type="file" width="110">
			// <embed src="/_scripts/uploader.swf?fileUploadID=fileInput2&amp;pagepath=/demo/&amp;script=/_scripts/upload.php&amp;folder=/_uploads&amp;btnWidth=110&amp;btnHeight=30&amp;wmode=opaque&amp;multi=true&amp;fileDataName=Filedata" 
			//     quality="high" id="fileInput2Uploader" class="fileUploaderBtn" name="fileInput2Uploader" allowscriptaccess="sameDomain" wmode="opaque" type="application/x-shockwave-flash" height="30" width="110"
			//     />
			// <div id="fileInput2Queue" class="fileUploadQueue"></div>
			//   <br>
			//   <a href="javascript:$('#fileInput2').fileUploadStart();">Upload Files</a> | <a href="javascript:$('#fileInput2').fileUploadClearQueue();">Clear Queue</a>

			$this->Form->appendChild($fieldset);
			$this->Form->appendChild($div);
		}
		
		/* main page */

		public function __viewDo() {
			if (count($_POST) > 0 && count($this->_errors) > 0) {
				if (is_array($this->_errors)) {
					$this->pageAlert("
						An error occurred while processing this form.
						<a href=\"#error\">".$this->parseErrors()." Rolling back.</a>",
						AdministrationPage::PAGE_ALERT_ERROR);
				}
			}
			elseif (count($_POST) > 0) {
				$this->pageAlert(
					"Successfully added a whole slew of entries, {$this->_entries_count} to be exact. 
					To do it again, <a href=\"{$this->_uri}/\">Give it another go below.</a>",
					Alert::SUCCESS, 
					array('created', URL, 'extension/multipleuploadinjector'));
			}
			
			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle('Symphony &ndash; Add Multiple Files From a Folder');

			// // Edit:
			// if ($this->_action == 'edit')	{
			// 	if (!$this->_valid && count($this->_errors) == 0)
			// 		if (count($this->_files) > 0) {
			// 			$this->appendSubHeading('Added '.implode(', ',$this->_files));
			// 		}
			// }
			// else 
				$this->appendSubheading('Inject!');

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Essentials'));

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			$label = Widget::Label(__('Source (where these are all going)'));	
			
			$sectionManager = new SectionManager($this->_Parent);
	    $sections = $sectionManager->fetch();
			$options[0]['label'] = 'Sections';
			foreach($sections as $section) { 
				$s = $section->fetchFields();
				$go = false;
				foreach ($s as $f) if (in_array($f->get('type'),$this->_driver->getTypes())) $go = true;
				if ($go) {
					$field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
					$options[0]['options'][] = array($section->get('id'), ($_POST['fields']['source'] == $section->get('id')), $section->get('name'));
				}
			}
			$label->appendChild(Widget::Select('fields[source]', $options, array('id' => 'context')));
			$div->appendChild($label);

			$label = Widget::Label(__('Directory where images are stored'));
			$options = array();
			$options[] = array('/workspace'.$this->_driver->getMUI(), false, '/workspace'.$this->_driver->getMUI());
			$ignore = array('events', 'data-sources', 'text-formatters', 'pages', 'utilities');
			$directories = General::listDirStructure(WORKSPACE . $this->_driver->getMUI(), true, 'asc', DOCROOT, $ignore);	   	
			if(!empty($directories) && is_array($directories)){
				foreach($directories as $d) {
					$d = '/' . trim($d, '/');
					if(!in_array($d, $ignore)) $options[] = array($d, ($d == '/workspace'.$this->_driver->getMUI().'/'.date('Y-m-d')) ? true : false, $d);
				}	
			}

			$label->appendChild(Widget::Select('fields[sourcedir]', $options));
			$div->appendChild($label);
			$fieldset->appendChild($div);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			$label = Widget::Label(__('Delete directory and contents after successful import?'));
			$label->appendChild(Widget::Input('fields[remove]', null, 'checkbox'));
			$div->appendChild($label);
			$fieldset->appendChild($div);

			
			$this->Form->appendChild($fieldset);
			/* now the section fields */
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual ' . __('sections') . ' ' . __('authors') . ' ' . __('navigation') . ' ' . __('Sections') . ' ' . __('System'));
			$fieldset->appendChild(new XMLElement('legend', __('Choose Default Values')));


			$entryManager = new EntryManager($this->_Parent);

			foreach($field_groups as $section_id => $section_data){	

				// create a dummy entry
				$entry = $entryManager->create();
				$entry->set('section_id', $section_id);
				$div = new XMLElement('div');
				$div->setAttribute('class', 'contextual ' . $section_id);
				
				
				$primary = new XMLElement('fieldset');
				$primary->setAttribute('class', 'primary');

				$sidebar_fields = $section_data['section']->fetchFields();
				// $main_fields = $section_data['section']->fetchFields(NULL, 'main');

				// if(is_array($main_fields) && !empty($main_fields)){
				// 	foreach($main_fields as $field){
				// 		if (!in_array($field->get('type'),$this->_driver->getTypes()))
				// 			$primary->appendChild($this->__wrapFieldWithDiv($field));
				// 	}
				// 	$div->appendChild($primary);
				// }

				if(is_array($sidebar_fields) && !empty($sidebar_fields)){
					$sidebar = new XMLElement('fieldset');
					$sidebar->setAttribute('class', 'primary');

					foreach($sidebar_fields as $field){
						if (!in_array($field->get('type'),$this->_driver->getTypes())) 
							$sidebar->appendChild($this->__wrapFieldWithDiv($field, $entry, $section_id, null, null));
					}

					$div->appendChild($sidebar);
				}				

				
				
				$fieldset->appendChild($div);
				
				
			}
			
			$this->Form->appendChild($fieldset);
			$submit = Widget::Input('action[save]','Process files','submit', array('accesskey' => 's'));
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild($submit);
			$this->Form->appendChild($div);

		}
		
		
		
	
	
		
		private function __wrapFieldWithDiv(Field $field, Entry $entry, $prefix = null, $postfix = null, $css = null){
			$div = new XMLElement('div', NULL, array('class' => 'field field-'.$field->handle().($field->get('required') == 'yes' ? ' required' : '')));
			if ($css != null) $div->setAttribute('style', $css);
			$field->displayPublishPanel(
				$div, $_POST['fields'][$field->get('element_name')],
				(isset($this->_errors[$field->get('id')]) ? $this->_errors[$field->get('id')] : NULL),
				$prefix ? '['.$prefix.']' : null,
				null, (is_numeric($entry->get('id')) ? $entry->get('id') : NULL)
			);
			return $div;
		}

		private function setDataFromPost($entry, $data, &$error, $simulate=false, $ignore_missing_fields=false, &$entries){
			$error = NULL;
			
			$status = __ENTRY_OK__;
			// Entry has no ID, create it:
			if(!$entry->get('id') && $simulate == false) {
				$entry->_engine->Database->insert($entry->get(), 'tbl_entries');
				if(!$entry_id = $entry->_engine->Database->getInsertID()) return __ENTRY_FIELD_ERROR__;
				$entry->set('id', $entry_id);
			}			

			$SectionManager = new SectionManager($this->_Parent);
			$EntryManager = new EntryManager($this->_Parent);
			$section = $SectionManager->fetch($entry->get('section_id'));
			$schema = $section->fetchFieldsSchema();

			foreach($schema as $info){
				$result = NULL;
				$field = $EntryManager->fieldManager->fetch($info['id']);

				if($ignore_missing_fields && !isset($data[$field->get('element_name')])) continue;
				if (!in_array($field->get('type'),$this->_driver->getTypes())) {
					$result = $field->processRawFieldData(
						(isset($data[$info['element_name']]) ? $data[$info['element_name']] : NULL), $s, $m, false, $entry->get('id')
					);

					if($s != Field::__OK__){
						$status = __ENTRY_FIELD_ERROR__;
						$error = array('field_id' => $info['id'], 'message' => $m);
					}
				} 
				else 
				{ $status = __ENTRY_OK__; $result = $data[$field->get('element_name')]; }
				

				$entry->setData($info['id'], $result);
			}

			if($status != __ENTRY_OK__ and !is_null($entry_id)) {
				$entry->_engine->Database->delete('tbl_entries', " `id` = '$entry_id' ");
			}
			$entries[] = $entry_id;
			return $status;
		}

		public static function getMetaInfo($file, $type){

			$imageMimeTypes = array(
				'image/gif',
				'image/jpg',
				'image/jpeg',
				'image/png',
			);
			
			$meta = array();
			
			$meta['creation'] = DateTimeObj::get('c', filemtime($file));
			
			if(in_array($type, $imageMimeTypes) && $array = @getimagesize($file)){
				$meta['width']    = $array[0];
				$meta['height']   = $array[1];
			}
			
			return $meta;
			
		}
		
		public function parseErrors() {
			if (is_array($this->_errors)) {
				foreach ($this->_errors as $k=>$v) {
					if (preg_match("/File Chosen in \'.*\' does not match allowable file types for that field/i", $v)) {
						$a = 'File \''.implode(', ', $this->_ignored_files).'\' does not match allowable filetypes for that fields. Please remove this file and try again.';
						return $a;
					} else if (!preg_match('/required/i', $v)) {
						return $v; 
					}
					// } else if (preg_match('/A file with the name/i', $v)) {
					// 	return $v;
					// } else if (preg_match('/There are no files/i', $v)) {
					// 	return $v;
					// } else if (preg_match('/^Fail/i', $v)) {
					// 	return $v;
					// } else if (preg_match('/You didn\'t choose a source/i', $v)) {
					// 	return $v;
					// } else if (preg_match('/Destination folder .* is not writable/i', $v)) {
					// 	return $v;
				}
			}
		}

	}
?>