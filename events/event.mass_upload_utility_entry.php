<?php

	require_once(TOOLKIT . '/class.event.php');
	
	Class EventMass_Upload_Utility_Entry extends Event{

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
					 'release-date' => '2009-11-13T11:35:07+00:00',
					 'trigger-condition' => 'action[api]');	
		}

		public static function getSource(){
			return contentExtensionMassUploadUtilityIndex::getSectionId();
		}

		public static function documentation(){
			return '';
		}

		public function load(){			
			return $this->__trigger();
		}

		protected function __trigger(){
			
			include(TOOLKIT . '/events/event.section.php');
			return $result;
		}		

	}