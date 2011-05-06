<?php

	class Extension_MassUploadUtility extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Mass Upload Utility',
				'version'		=> '2.2.1.1',
				'release-date'	=> '2011-05-06',
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
					'page'          => '/system/inject/',
					'delegate'      => 'AddCustomPreferenceFieldsets',
					'callback'      => 'inject'
				),
		      	array(
	       	 		'page'    => '/backend/',
		        	'delegate' => 'AdminPagePreGenerate',
		        	'callback' => 'initaliseAdminPageHead'
	      		),
			);
		}

		


		
		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;
			if ($page instanceof contentPublish and $page->_context['page'] == 'index') {
				$sectionManager = new SectionManager($this->_Parent);
				$section = $sectionManager->fetch($sectionManager->fetchIDFromHandle($page->_context['section_handle']));
				foreach ($section->fetchFields() as $f) 
					if ($this->supportedField($f->get('type'))) {
					$page->appendSubHeading(__(''), Widget::Anchor(__('Add many'), URL . '/symphony/extension/massuploadutility/inject/do?MUUsource='.$page->_context['section_handle'], __('Add Many'), 'muu button', NULL, array('accesskey' => 'c')));
					// $page->Form->prependChild(Widget::Anchor(__('Add many'), URL . '/symphony/extension/massuploadutility/inject?source='.$page->_context['section_handle'], __('Add Many'), 'muu button', NULL, array('accesskey' => 'c')));
				}				
			}
			if ($page instanceof contentExtensionMassuploadUtilityInject and $page->_context['page'] != 'do') {      
				$page->addScriptToHead(URL . '/extensions/massuploadutility/assets/jquery.html5_upload.js',100100990);
			}

		}	
		
	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/
	// supporting everything with upload in it's name, this may or may not work
	public function supportedField($name) {
		return preg_match('/upload/',$name);
	}


	}
?>
