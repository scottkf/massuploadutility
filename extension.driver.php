<?php

	class Extension_MassUploadUtility extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		protected $upload = '/uploads/mui';
		protected $supported_types = array('upload');

		public function about() {
			return array(
				'name'			=> 'Mass Upload Utility',
				'version'		=> '2.2.1',
				'release-date'	=> '2011-05-04',
				'author'		=> array(
					'name'			=> 'Scott Tesoriere',
					'website'		=> 'http://tesoriere.com',
					'email'			=> 'scott@tesoriere.com'
				),
				'description'	=> 'Allows you to jam lots of Files (that already exist or you upload) into a section. The section must have an upload Field.'
	 		);
		}
		
		public function uninstall() {
			$this->_Parent->Configuration->remove('massuploadutility');
		}
		
		public function install() {
			if (file_exists(WORKSPACE.$this->upload)) return true;
			try {
				if (!General::realiseDirectory(WORKSPACE.$this->upload, intval('0755', 8))) {
				}
			}
			catch(Exception $e) {
				if(isset(Administration::instance()->Page)){
					Administration::instance()->Page->pageAlert('Couldn\'t create '.WORKSPACE.'/uploads/mui directory, chmod this folder to 777.', Alert::ERROR);
				}
				return false;
			}
			return true;
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/system/inject/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'inject'
				),
	      array(
       	 	'page'    => '/backend/',
	        'delegate'  => 'AdminPagePreGenerate',
	        'callback'  => 'initaliseAdminPageHead'
      ));
		}

		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 200,
					'name'	=> 'Mass Upload Utility',
					'link'	=> '/inject/'
				)
			);
		}
		
    public function initaliseAdminPageHead($context) {
      $page = $context['parent']->Page;
      if ($page instanceof contentExtensionMassuploadUtilityInject and $page->_context['page'] != 'do') {      
        $page->addStylesheetToHead(URL . '/extensions/massuploadutility/assets/uploadify.css', 'screen', 100100991);
        $page->addScriptToHead(URL . '/extensions/massuploadutility/assets/jquery.uploadify.js',100100992);
      }
      
    }			
	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/
		public function getMUI() {
			return $this->upload;
		}
		
		public function getTypes() {
			return $this->supported_types;
		}
	}
?>
