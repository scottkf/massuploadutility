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
		- this will not install on most servers unless workspace is 777 (or /workspace/upload)
	
	*/

	class contentExtensionMassUploadUtilityInject extends AdministrationPage {
		protected $_action = '';
		protected $_driver = null;
		protected $_errors = array();
		protected $_entries_count = '';
		protected $_uri = null;
		protected $_valid = false;
		protected $_message = '';
		
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

			if (!isset($_REQUEST['MUUsource']) or $_REQUEST['MUUsource'] == '')
			{ $this->_message = 'You didn\'t choose a source, perhaps you don\'t have any sections with an upload field in them?'; $this->_valid = false; return; }

			// $fields = $_POST['fields'];
			$upload_field_name = General::sanitize($_POST['upload_field_name']);
			if(isset($_FILES['fields'])){
				$filedata = General::processFilePostData($_FILES['fields']);
				
				foreach($filedata as $handle => $data){
					if(!isset($fields[$handle])) $fields[$handle] = $data;
					elseif(isset($data['error']) && $data['error'] == 4) $fields['handle'] = NULL;
					else{

						foreach($data as $ii => $d){
							if(isset($d['error']) && $d['error'] == 4) $fields[$handle][$ii] = NULL;
							elseif(is_array($d) && !empty($d)){

								foreach($d as $key => $val)
									$fields[$handle][$ii][$key] = $val;
							}
						}
					}
				}
			}

			if ($fields[$upload_field_name][0][0] == '') {
				$this->_message = "Please select files to upload."; $this->_valid = false; return; 
			}
	

			
			$sectionManager = new SectionManager($this->_Parent);
			$section_id = $sectionManager->fetchIDFromHandle(General::sanitize($_REQUEST['MUUsource']));
			// $this->_section_id = $_POST['fields']['source']; // section id
			$entryManager = new EntryManager($this->_Parent);



			if(!$section = $sectionManager->fetch($section_id))
				Administration::instance()->customError(__('Unknown Section'), __('The Section you are looking, <code>%s</code> for could not be found.', $this->_context['section_handle']));


			// a list of all the entries so we can rollback
			$entries = array();
			
			foreach ($fields[$upload_field_name] as $v) {
				$entry =& $entryManager->create();
				$entry->set('section_id', $section_id);
				$entry->set('author_id', $this->_Parent->Author->get('id'));
				$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));				
				
				foreach ($_POST['fields'] as $k=>$val) {
					if ($upload_field_name != $k) {
						$nfields[$k] = $val;
					}
				}
				$nfields[$upload_field_name]['name'] = $v[0];
				$nfields[$upload_field_name]['type'] = $v[1];
				$nfields[$upload_field_name]['tmp_name'] = $v[2];
				$nfields[$upload_field_name]['error'] = $v[3];
				$nfields[$upload_field_name]['size'] = $v[4];
			
				if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($nfields, $error))
				{ $this->_errors[key($error)] = $error[key($error)]; break; }

				// setup the data, process it
				// if(__ENTRY_OK__ != $this->setDataFromPost($entry, $fields, $this->_errors, false, false, $entries))
				elseif(__ENTRY_OK__ != $entry->setDataFromPost($nfields, $error)) {
					$this->_errors[] = $error['message'];
					// $this->pageAlert($error['message'], Alert::ERROR);
					break; 
				}
				Symphony::ExtensionManager()->notifyMembers('EntryPreCreate', '/publish/new/', array('section' => $section, 'entry' => &$entry, 'fields' => &$nfields));

				// commit the entry if we made it
				if(!$entry->commit()){
					define_safe('__SYM_DB_INSERT_FAILED__', true);
					$this->pageAlert(NULL, Alert::ERROR);
				}
				else
				{
					// keep track of it if it was inserted
					$entries[] = $entry->get('id');
					$this->_valid = true;
					Symphony::ExtensionManager()->notifyMembers('EntryPostCreate', '/publish/new/', array('section' => $section, 'entry' => $entry, 'fields' => $nfields));
				}

			}
			



			// rollback, delete all entries by id
			if ($this->_valid == false && count($entries) > 0) {

				$entryManager->delete($entries);
				return;
			}

			$this->_entries_count = count($fields[$upload_field_name]);
			
		}
		

		/*-------------------------------------------------------------------------
			Index
		-------------------------------------------------------------------------*/

		public function __view() {
			$this->__viewIndex();
		}
		public function __viewIndex() {

		}
		
		/* main page */

		public function __viewDo() {
			if ((count($_POST) > 0 && count($this->_errors) > 0) || $this->_message != '') {
				if (is_array($this->_errors)) {
					// print_r($this->_errors);
					$this->pageAlert("
						An error occurred while processing this form. ".($this->_message != '' ? $this->_message: "See below for details.").
						"<a href=\"#error\">Rolling back.</a>",
						Alert::ERROR);
				}
			}
			elseif (count($_POST) > 0) {
				$this->pageAlert(
					"Successfully added a whole slew of entries, {$this->_entries_count} to be exact. 
					To do it again, <a href=\"{$this->_uri}/\">Give it another go below.</a>",
					Alert::SUCCESS, 
					array('created', URL, 'extension/multipleuploadinjector'));
				redirect(SYMPHONY_URL . '/publish/'.General::sanitize($_REQUEST['MUUsource']));
			}
			
			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle('Symphony &ndash; Add Multiple Files From a Folder');


			$this->appendSubheading('Inject some files into the <strong>'.General::sanitize($_REQUEST['MUUsource']).'</strong> section!');


			
			// $script = new XMLElement('script');
			// $script->setAttribute("type", 'text/javascript');
			// $js = '
			// jQuery(document).ready(function() {
			// 	                    jQuery("#upload_field").html5_upload({
			// 	                            url: function(number) {
			// 	                                    return prompt(number + " url", "/");
			// 	                            },
			// 	                            sendBoundary: window.FormData || $.browser.mozilla,
			// 	                            onStart: function(event, total) {
			// 	                                    return confirm("You are trying to upload " + total + " files. Are you sure?");
			// 	                            },
			// 	                            setName: function(text) {
			// 	                                            jQuery("#progress_report_name").text(text);
			// 	                            },
			// 	                            setStatus: function(text) {
			// 	                                    jQuery("#progress_report_status").text(text);
			// 	                            },
			// 	                            setProgress: function(val) {
			// 	                                    jQuery("#progress_report_bar").css(\'width\', Math.ceil(val*100)+"%");
			// 	                            },
			// 	                            onFinishOne: function(event, response, name, number, total) {
			// 	                                    //alert(response);
			// 	                            }
			// 	                    });
			// 	            });
			// 	';
			// $script->setValue($js);


			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			// $this->Form->appendChild($fieldset);
			/* now the section fields */
			// $fieldset->setAttribute('class', 'settings contextual ' . __('sections') . ' ' . __('authors') . ' ' . __('navigation') . ' ' . __('Sections') . ' ' . __('System'));
			$fieldset->appendChild(new XMLElement('legend', __('Choose Default Values')));


			$entryManager = new EntryManager($this->_Parent);
			$sectionManager = new SectionManager($this->_Parent);

			$section = $sectionManager->fetch($sectionManager->fetchIDFromHandle(General::sanitize($_REQUEST['MUUsource'])));
			$s = $section->fetchFields();

			// create a dummy entry
			$entry = $entryManager->create();
			$entry->set('section_id', $section->get('id'));
				
				
			$this->Form->appendChild($fieldset);
				
			$primary = new XMLElement('fieldset');
			$primary->setAttribute('class', 'primary');
			$sidebar_fields = $section->fetchFields(NULL, 'sidebar');
			$main_fields = $section->fetchFields(NULL, 'main');

			if ((!is_array($main_fields) || empty($main_fields)) && (!is_array($sidebar_fields) || empty($sidebar_fields))) {
				$primary->appendChild(new XMLElement('p', __(
					'It looks like you\'re trying to create an entry. Perhaps you want fields first? <a href="%s">Click here to create some.</a>',
					array(
						SYMPHONY_URL . '/blueprints/sections/edit/' . $section->get('id') . '/'
					)
				)));
				$this->Form->appendChild($primary);
			}

			else {
				if (is_array($main_fields) && !empty($main_fields)) {
					foreach ($main_fields as $field) {
							$primary->appendChild($this->__wrapFieldWithDiv($field, $entry));
					}

					$this->Form->appendChild($primary);
				}

				if (is_array($sidebar_fields) && !empty($sidebar_fields)) {
					$sidebar = new XMLElement('fieldset');
					$sidebar->setAttribute('class', 'secondary');

					foreach ($sidebar_fields as $field) {
						 $sidebar->appendChild($this->__wrapFieldWithDiv($field, $entry));
					}

					$this->Form->appendChild($sidebar);
				}
			}
			
			

			$hidden = Widget::Input('MUUsource', General::sanitize($_REQUEST['MUUsource']), 'hidden');
			$submit = Widget::Input('action[save]','Process files','submit', array('accesskey' => 's'));
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild($hidden);
			$div->appendChild($submit);
			$this->Form->appendChild($div);

		}
		
		
		
	
	
		
		private function __wrapFieldWithDiv(Field &$field, Entry &$entry, $prefix = null, $postfix = null, $css = null){
			$div = new XMLElement('div', NULL, array('id' => 'field-' . $field->get('id'), 'class' => 'field field-'.$field->handle().($field->get('required') == 'yes' ? ' required' : '')));
			if ($css != null) $div->setAttribute('style', $css);
			$value = array("value" => $_POST['fields'][$field->get('element_name')]);
			if (!$this->_driver->supportedField($field->get('element_name'))) {
				// print_r($_POST['fields'][$field->get('element_name')]);
				$field->displayPublishPanel(
					$div, $value,
					(isset($this->_errors[$field->get('id')]) ? $this->_errors[$field->get('id')] : NULL),
					$prefix ? '['.$prefix.']' : null,
					null, (is_numeric($entry->get('id')) ? $entry->get('id') : NULL)
				);
			}
			else {
				$fileInput = new XMLElement('input');
				$fileInput->setAttribute("multiple", "multiple");
				$fileInput->setAttribute("id", "upload_field");
				$fileInput->setAttribute("type", "file");
				$fileInput->setAttribute("name", "fields[".$field->get('element_name')."][]");
				$progress = new XMLElement('div');
				$progress->setAttribute('id', 'progress_report');
				$progress_name = new XMLElement('div');
				$progress_name->setAttribute('id', 'progress_report_name');
				$progress_status = new XMLElement('div');
				$progress_status->setAttribute('id', 'progress_report_status');
				$progress_container = new XMLElement('div');
				$progress_container->setAttribute('id', 'progress_report_bar_container');
				$progress_bar = new XMLElement('div');
				$progress_bar->setAttribute('id', 'progress_report_bar');

				$progress_container->appendChild($progress_bar);
				$progress->appendChild($progress_name);
				$progress->appendChild($progress_status);
				$progress->appendChild($progress_container);

				$hidden = Widget::Input('upload_field_name', $field->get('element_name'), 'hidden');


				$span = new XMLElement('span', NULL, array('class' => 'frame'));
				$label = Widget::Label($field->get('label'));
				$class = 'file';
				$label->setAttribute('class', $class);
				if($field->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
				$span->appendChild($fileInput);
				$span->appendChild($progress);

				$label->appendChild($span);
				$div->appendChild($label);
				$div->appendChild($hidden);
			}
			return $div;
		}


		
		public function parseErrors() {
		}

	}
?>
