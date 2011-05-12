<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(CORE . '/class.frontend.php');
	require_once(EXTENSIONS . '/massuploadutility/events/event.mass_upload_utility_entry.php');
	require_once(EXTENSIONS . '/massuploadutility/lib/class.xmltoarray.php');

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

			$_POST['fields'] = $_REQUEST['fields'];
			$_POST['action'] = $_REQUEST['action'];
			
			$event = new Event_Mass_Upload_Utility_Entry(Frontend::instance(), array());

			// Borrowed from Nick Dunn
			$xml = $event->load();
			if(is_array($xml)) $xml = reset($xml);
			if($xml instanceOf XMLElement) $xml = $xml->generate(TRUE);			

			echo(json_encode(XMLToArray::convert($xml)));
				
			exit;
		}


	}
?>
