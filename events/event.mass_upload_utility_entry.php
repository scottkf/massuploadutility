<?php
	/** 
	 * An event to avoid duplicating on the Symphony Core when
	 *	adding an entry
	 */

	require_once(TOOLKIT . '/class.event.php');
	
	Class Event_Mass_Upload_Utility_Entry extends Event{

		const ROOTELEMENT = 'response';

		public $eParamFILTERS = array();

		public static function about(){
			return array(
					 'name' => 'Mass Upload Utility: Create an Entry',
					 'author' => array(
							'name' => 'Scott Tesoriere',
							'website' => 'http://tesoriere.com',
							'email' => 'scott@tesoriere.com'),
					 'version' => '1.0',
					 'release-date' => '2011-05-12T11:35:07+00:00',
					 'trigger-condition' => 'action[api]');	
		}

		public static function getSource(){
			return contentExtensionMassUploadUtilityIndex::getSectionId();
		}

		public static function documentation(){
			return '';
		}

		public function load(){
			if(isset($_POST['action']['api'])) return $this->__trigger();
		}

		protected function __trigger(){
			
			include(TOOLKIT . '/events/event.section.php');
			return $result;
		}		

	}