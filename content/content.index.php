<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');

	/**
	 * Called to build the content for the page. 
	 */

	class contentExtensionMassUploadUtilityIndex extends AdministrationPage {
		protected $_errors = array();
		protected $_valid = false;
		protected $_message = '';
		
				
		/**
		 *	---------------------------------------------------------
		 *	This functionality is essentially duplicated (and slightly changed)
		 * 		from Symphony's content.publish.php
		 *		in the function public function __actionNew(){
		 *	Should that core ever change, this needs to be as well.
		 *
		 * _REQUEST view() are used because jquery.html5_upload.js can't deal 
		 *		with extra variables in the POST
		 * Should this change, view() will need to be changed to action()
		 *	---------------------------------------------------------
		 */
		public function view() {
			if (!isset($_REQUEST['MUUsource']) or $_REQUEST['MUUsource'] == '')
			{ $this->_message = __("You didn't choose a source, perhaps you don't have any sections with an upload field in them?"); $this->__response(); }

			$sectionManager = new SectionManager($this->_Parent);
			
			$section_id = $sectionManager->fetchIDFromHandle(General::sanitize($_REQUEST['MUUsource']));

			if(!$section = $sectionManager->fetch($section_id))
				Administration::instance()->customError(__('Unknown Section'), __('The Section you are looking, <code>%s</code> for could not be found.', $this->_context['section_handle']));

			$entryManager = new EntryManager($this->_Parent);

			$entry =& $entryManager->create();
			$entry->set('section_id', $section_id);
			$entry->set('author_id', Administration::instance()->Author->get('id'));
			$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
			$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
			
			$fields = $_REQUEST['fields'];
			if ((!$this->__processFilePostData($fields)) === NULL)
			{ $this->_message = __("Did you forget to upload some files?"); $this->__response(); }
			
			if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($fields, $error))
			{ $this->_errors[key($error)] = $error[key($error)]; $this->__response(); }

			// setup the data, process it
			// if(__ENTRY_OK__ != $this->setDataFromPost($entry, $fields, $this->_errors, false, false, $entries))
			elseif(__ENTRY_OK__ != $entry->setDataFromPost($fields, $error)) {
				$this->_errors[key($error)] = $error[key($error)];
				$this->__response();
			}
			
			else {
				Symphony::ExtensionManager()->notifyMembers('EntryPreCreate', '/publish/new/', array('section' => $section, 'entry' => &$entry, 'fields' => &$nfields));

				$prepopulate_field_id = $prepopulate_value = NULL;
				if(isset($_POST['prepopulate'])){
					$prepopulate_field_id = array_shift(array_keys($_POST['prepopulate']));
					$prepopulate_value = stripslashes(rawurldecode(array_shift($_POST['prepopulate'])));
				}

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
