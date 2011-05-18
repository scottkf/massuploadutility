<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(CORE . '/class.frontend.php');

	/**
	 * Called to build the content for the page. 
	 */

	class contentExtensionMassUploadUtilityIndex extends AdministrationPage {
		protected $_errors = array();
		protected $_valid = false;
		protected $_message = '';
		
		public function getSectionId() {
			$sectionManager = new SectionManager(Frontend::instance());
			
			$section_id = $sectionManager->fetchIDFromHandle(General::sanitize($_REQUEST['MUUsource']));

			if(!$section = $sectionManager->fetch($section_id))
				return NULL;
			else
				return $section_id;
		}
		/**
		 *
		 * _REQUEST in view() are used because jquery.html5_upload.js can't deal 
		 *		with extra variables in the POST
		 * Should this change, view() will need to be changed to action()
		 *	---------------------------------------------------------
		 */
		public function view() {

			if (!isset($_REQUEST['MUUsource']) or $_REQUEST['MUUsource'] == '')
			{ exit; }	

			if (($section_id = $this->getSectionId()) === NULL)
			{ exit; }	

			$_POST = $_REQUEST;
			$_POST['action']['save'] = true;
			

			require_once(CONTENT . '/content.publish.php');
			$content = new contentPublish($this->_Parent);
			// this is used by contentPublish to see the source section
			$content->_context = array();
			$content->_context['section_handle'] = General::sanitize($_REQUEST['MUUsource']);

			// this function takes care of all the entry adding
			$content->__actionNew();
			
			$response['errors'] = $content->_errors;
			$response['status'] = (count($content->_errors) ? 'error' : 'success');
			$response['message'] = ($content->Alert instanceof Alert ? $content->Alert->__get('message') : '');

			echo(json_encode($response));
				
			exit;
		}


	}
?>
