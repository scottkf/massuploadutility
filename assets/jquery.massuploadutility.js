(function($) {

	$(document).ready(function() {

		Symphony.Language.add({
			'Successfully added a whole slew of entries, {$total} to be exact.': false,
			'But {$total} entries were successfully added.': false,
			'Some errors were encountered while attempting to save.': false,
			'View all Entries': false,
			'Create more?': false,
			'Sorry, but your browser is incompatible with uploading files using HTML5 (at least, with current preferences.\n Please install the latest version of Firefox, Safari or Chrome': false,
			'Start' : false,
			'Progress' : false,
			'Loaded' : false,
			'Finished' : false
		});

		// this should theoretically support any upload field
		fileField = $("input[type='file']");
		form = fileField.parents('form');
		urlBase = window.location.protocol + '//' + window.location.hostname + window.location.pathname.replace(/(.*)\/symphony\/.*/i,'$1');
		urlMuu = "/symphony/extension/massuploadutility/"
		urlAssets = urlBase + '/extensions/massuploadutility/assets';
		source = window.location.pathname.replace(/.*\/symphony\/publish\/(.*)\/new\//i,'$1');
	
		//  if there's more than one upload field, we have no idea what to do
		if (fileField.size() == 1) {
			fileField.attr('multiple', 'true');
			fileField.parent().append(" \
				<input type='hidden' name='MUUsource' value='"+source+"' /> \
				<div id='progress_report'> \
					<div id='progress_report_name'></div> \
					<div id='progress_report_status'></div> \
					<div id='progress_report_bar_container'> \
						<div id='progress_report_bar'></div> \
						</div> \
					</div> \
				</div> \
				<div id='file_list'></div>"
			); 
		    fileField.html5_upload({
				fieldName: fileField.attr('name'),
		        url: function(number) {
					return urlBase + urlMuu + '?' + form.serialize();
		        },
				autostart: false,
				method: "post",
		        sendBoundary: window.FormData || $.browser.mozilla,
		        onStart: function(event, total) {
					if (total <= 0) {
						if ($("#error").length == 0) {
							fileField.parent().parent().wrap("<div id=\"error\" class=\"invalid\"></div>");
							fileField.parent().parent().append("<p>No files selected.</p>");
						}
						return false;
					}
					return true;
		        },
		        setName: function(text) {
		            $("#progress_report_name").text(text);
		        },
		        setStatus: function(text) {
		            $("#progress_report_status").text(text);
		        },
		        setProgress: function(val) {
		            $("#progress_report_bar").css('width', Math.ceil(val*100)+"%");
		        },
		        onFinishOne: function(event, response, name, number, total) {
					// check json["message"] if its set nothing happened at all.
					json = $.parseJSON(response);
					css = (json.status == 1) ? "success" : "failure"; 
					p = "<p><img src='"+urlAssets + "/images/"+json.status+".png' />&nbsp;" + name + "&nbsp;<small id='MUU-list' class="+css+">";
					$.each(json["errors"], function(k,v) {
						// $p += jQuery("#field-" + k + " > label:first").children(":first").attr("name") + " " + v;
						p += v;
						// jQuery("#field-" + k + " > label").wrap("<div id=\"error\" class=\"invalid\"></div>");
						// jQuery("#field-" + k + " > div > label").append("<p>" + v + "</p>");
					});
					p += "</small></p>";
					$("#file_list").show();
					if (json.status == 1) $("#file_list").append(p);
					else $("#file_list").prepend(p);
		        },
				onFinish: function(total) {
					failed = $("#MUU-list.failure").size();
					total = failed + $("#MUU-list.success").size();
					success = total - failed;
					p = "<p id=\"notice\" class=\"";
					if (failed == 0) {
						p += "success\">" +  Symphony.Language.get('Successfully added a whole slew of entries, {$total} to be exact.', { 'total': total });
						p += " \
							<a href='"+urlBase+"/symphony/publish/"+source+"/new'>Create more?</a> \
							<a href='"+urlBase+"/symphony/publish/"+source+"'>View all Entries</a>";								
					}
					else {
						p += "error\">" + Symphony.Language.get('Some errors were encountered while attempting to save.');
						if (success > 0)
							p += "&nbsp;" + Symphony.Language.get('But {$total} entries were successfully added.', { 'total' : success});
						$("#file_list")
						.animate({ backgroundColor: "#eeee55", opacity: 1.0 }, 200)
				      	.animate({ backgroundColor: "transparent", opacity: 1.0}, 350);
					}
					p += "</p>";
					$("p#notice").remove();
					$("#header").prepend(p);
					$('html, body').animate({ scrollTop: $("p#notice").offset().top }, 300)
				}
		   	});
			form.submit(function() {
				if (fileField.attr('files').length > 1) {
					// remove error classes for viewability
					$("#file_list").empty();
					if ($("#error.invalid").size() > 0) {
						$.each($("#error.invalid"), function(k,v) {
							$(v).children("p").remove();
							$(v).replaceWith($(v).contents());						
						});
						setTimeout(function() {
							// this exists because it caused issues when the event would fire up before the error div's would be removed
							fileField.trigger("html5_upload.start");										
						}, 100);
					}
					else {
						fileField.trigger("html5_upload.start");
					}
					return false;
				}
			});
		}	
		else if (fileField.size() > 1) {
			console.log("MUU doesn't work with multiple upload fields");
		}
		else {
			console.log("No upload fields detected");
		}
	
	});
	
})(jQuery.noConflict());

