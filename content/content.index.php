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

	class contentExtensionMassUploadUtilityIndex extends AdministrationPage {
		protected $_driver = null;
	
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('MassUploadUtility');
		}
				
		public function view() {
			
			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->Form->setAttribute('action','');
			$this->Form->setAttribute('id', 'MUU');
			$this->setTitle('Symphony &ndash; Add Multiple Files From a Folder');


			$this->appendSubheading('Inject some files into the <strong>'.General::sanitize($_REQUEST['MUUsource']).'</strong> section!');


			
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
			if (!$this->_driver->supportedField($field->get('type'))) {
				$field->displayPublishPanel(
					$div, $value,
					'',
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
				
				$fileList = new XMLElement('div');
				$fileList->setAttribute('id', 'file_list');

				$hidden = Widget::Input('upload_field_name', $field->get('element_name'), 'hidden');

				$script = new XMLElement('script');
				$script->setAttribute("type", 'text/javascript');
				$js = '
				jQuery(document).ready(function() {
   	                jQuery("#upload_field").html5_upload({
						fieldName: "fields['.$field->get('element_name').']",
	                    url: function(number) {
							return "'.SYMPHONY_URL.'/extension/massuploadutility/api/?" + jQuery("#MUU").serialize();
	                    },
						autostart: false,
						method: "post",
	                    sendBoundary: window.FormData || $.browser.mozilla,
	                    onStart: function(event, total) {
							if (total <= 0) {
								if (jQuery("#error").length == 0) {
									jQuery("#upload_field").parent().parent().wrap("<div id=\"error\" class=\"invalid\"></div>");
									jQuery("#upload_field").parent().parent().append("<p>No files selected.</p>");
								}
								return false;
							}
	                        return confirm("You are about to try to upload " + total + " files. Are you sure?");
	                    },
	                    setName: function(text) {
	                        jQuery("#progress_report_name").text(text);
	                    },
	                    setStatus: function(text) {
	                        jQuery("#progress_report_status").text(text);
	                    },
	                    setProgress: function(val) {
	                        jQuery("#progress_report_bar").css(\'width\', Math.ceil(val*100)+"%");
	                    },
	                    onFinishOne: function(event, response, name, number, total) {
							// check json["message"] if its set nothing happened at all.
							json = jQuery.parseJSON(response);
							class = (json.status == 1) ? "success" : "failure"; 
							$p = "<p>" + name + "<small id=\"MUU-list\" class=\""+class+"\">";
							jQuery.each(json["errors"], function(k,v) {
								// $p += jQuery("#field-" + k + " > label:first").children(":first").attr("name") + " " + v;
								$p += v;
								// jQuery("#field-" + k + " > label").wrap("<div id=\"error\" class=\"invalid\"></div>");
								// jQuery("#field-" + k + " > div > label").append("<p>" + v + "</p>");
							});
							$p += "</small></p>";
							jQuery("#file_list").show();
							if (json.status == 1) jQuery("#file_list").append($p);
							else jQuery("#file_list").prepend($p);
	                    },
						onFinish: function(total) {
							failed = jQuery("#MUU-list.failure").size();
							total = failed + jQuery("#MUU-list.success").size();
							p = "<p id=\"notice\" class=\"";
							if (failed == 0) {
								p += "success\">Successfully added a whole slew of entries, "+total+" to be exact.";								
							}
							else {
								p += "error\">Some error were encountered while attempting to save.";
								jQuery("#file_list")
								.animate({ backgroundColor: "#eeee55", opacity: 1.0 }, 200)
						      	.animate({ backgroundColor: "transparent", opacity: 1.0}, 350);
							}
							p += "</p>";
							jQuery("p#notice").remove();
							jQuery("#header").prepend(p);
						}
                   	});
					jQuery("#MUU").submit(function() {
						// jQuery("#error > label > p").remove();
						// jQuery("#error").replaceWith(jQuery("#error").contents());
						jQuery("#file_list").empty();
						jQuery("#upload_field").trigger("html5_upload.start");
						return false;
					});
					

           		});';
				$script->setValue($js);
				
				$span = new XMLElement('span', NULL, array('class' => 'frame'));
				$label = Widget::Label($field->get('label'));
				$class = 'file';
				$label->setAttribute('class', $class);
				if($field->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
				$span->appendChild($fileInput);
				$span->appendChild($progress);
				$span->appendChild($fileList);
				$span->appendChild($script);

				$label->appendChild($span);
				$div->appendChild($label);
				$div->appendChild($hidden);
			}
			return $div;
		}

	}

?>
