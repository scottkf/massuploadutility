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

	class contentExtensionMassUploadUtilityAPI extends AdministrationPage {
		protected $_errors = array();
		protected $_valid = false;
		protected $_message = '';
		
				
		public function view() {
			if (!isset($_REQUEST['MUUsource']) or $_REQUEST['MUUsource'] == '')
			{ $this->_message = 'You didnt choose a source, perhaps you dont have any sections with an upload field in them?'; $this->__response(); }

			$fields = $_REQUEST['fields'];
			if ((!$this->__processFilePostData($fields)) === NULL)
			{ $this->_message = 'Did you forget to upload some files?'; $this->__response(); }

			$sectionManager = new SectionManager($this->_Parent);
			$section_id = $sectionManager->fetchIDFromHandle(General::sanitize($_REQUEST['MUUsource']));
			// $this->_section_id = $_POST['fields']['source']; // section id
			$entryManager = new EntryManager($this->_Parent);

			if(!$section = $sectionManager->fetch($section_id))
				Administration::instance()->customError(__('Unknown Section'), __('The Section you are looking, <code>%s</code> for could not be found.', $this->_context['section_handle']));

			$entry =& $entryManager->create();
			$entry->set('section_id', $section_id);
			$entry->set('author_id', $this->_Parent->Author->get('id'));
			$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
			$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));				
			
			if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($fields, $error))
			{ $this->_errors[key($error)] = $error[key($error)]; $this->__response(); }

			// setup the data, process it
			// if(__ENTRY_OK__ != $this->setDataFromPost($entry, $fields, $this->_errors, false, false, $entries))
			elseif(__ENTRY_OK__ != $entry->setDataFromPost($fields, $error)) {
				$this->_errors[key($error)] = $error[key($error)];
				$this->__response();
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

			$this->__response();
			exit;
		}
		
		/* main page */


		public function __processFilePostData(&$fields) {
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
				return TRUE;
			}
			else
				return NULL;
			
		}


		private function __response() {
			echo json_encode(array("status"=>$this->_valid, "message"=>$this->_message, "errors" => $this->_errors));
			exit;
		}

	}
?>
