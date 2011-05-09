<?php

	class Extension_MassUploadUtility extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Mass Upload Utility',
				'version'		=> '0.9.1',
				'release-date'	=> '2011-05-09',
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
		}
		
		public function getSubscribedDelegates() {
			return array(
		      	array(
	       	 		'page'    => '/backend/',
		        	'delegate' => 'AdminPagePreGenerate',
		        	'callback' => 'initaliseAdminPageHead'
	      		),
			);
		}

		


		
		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;
			$assets_path = '/extensions/massuploadutility/assets/';
			
			if ($page instanceof contentPublish and $page->_context['page'] == 'new') {      
				$page->addStylesheetToHead(URL . $assets_path . 'massuploadutility.css', 'screen', 14145);
				$page->addScriptToHead(URL . $assets_path . 'jquery.massuploadutility.js',14156);
				$page->addScriptToHead(URL . $assets_path . 'jquery.html5_upload.js',14156);
			}
		}	
		
	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/


	}
?>
