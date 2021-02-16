var AutoSave = {};
(function($, window, document) {
	
	AutoSave = {
		
		options: {
			use_storage: 1,
			use_scroll: 0,
			use_messages: 1,
			use_direct_load: 0,
			interval: 60000
		},
		draft_autosave: '',
		textarea: '',
		button: '',
		oldmessage: '',
		tid: '',
		fid: '',
		draft_message: '',
		unique_id: '',
		lang: {},
	
		initialize: function () {
			
			// Determine localStorage support
			if (typeof Storage === 'undefined' && this.options.use_storage) {
				this.options.use_storage = 0;
			}
			
			// Determine the textarea object
			// MyBB 1.6
			if (typeof clickableEditor !== 'undefined') {
				this.textarea = $('#' + clickableEditor.textarea);
			}
			// MyBB 1.8
			else if (typeof MyBBEditor !== 'undefined' && MyBBEditor != null) {
				this.textarea = MyBBEditor;
			}
			// Otherwise, #message is assumed as the main textarea
			else {
				this.textarea = $('#message');
			}
			
			// Determine the unique identifier
			if (this.tid) {
				this.unique_id = 'post_' + this.tid;
			}
			else if (this.fid) {
				this.unique_id = 'thread_' + this.fid;
			}
		
			this.oldmessage = this.textarea.val();
			
			// Going IDLE
			$(window).on('blur', function () {
			
				if (AutoSave.draft_autosave) {
					clearInterval(AutoSave.draft_autosave);
				}
				
				AutoSave.save_drafts();
				
			});
			
			// Focusing
			this.textarea.bind('focus', function() {
			
				if (AutoSave.draft_autosave) {
					clearInterval(AutoSave.draft_autosave);
				}
				
				AutoSave.draft_autosave = setInterval(function() {
					AutoSave.save_drafts();
				}, AutoSave.options.interval);
					
			});
			
			// Using localStorage, and a draft for this thread is available
			if (this.options.use_storage && localStorage.autosaved_drafts) {
			
				// Determine the button object
				if ($('#quick_reply_form').length) {
					this.button = $('#quick_reply_submit');
				}
				else if ($('input[name="submit"]')) {
					this.button = $('input[name="submit"]');
				}
				
				if (this.button) {
				
					// Bind submit event to our
					this.button.bind('click', function(e) {
						return AutoSave.delete_drafts_on_submit();
					});
					
				}
				
				obj = this.read_storage();
				
				if (obj[this.unique_id]) {
					this.draft_message = obj[this.unique_id]['message'];
					this.draft_subject = obj[this.unique_id]['subject'];
				}
				
			}
			
			// Show message/load drafts
			if (this.draft_message) {
			
				if (!this.options.use_direct_load && this.options.use_messages != 1) {
					this.show_message(this.lang.draft_available, {timeout: 15000, clickToClose: true}, function () { AutoSave.load_drafts() });
				}
				else {
					this.load_drafts();
				}
				
			}
			
		},
			
		save_drafts: function () {
			
			// Get all the necessary data
			subject = $('input[name="subject"]').val();
			message = this.textarea.val();
			
			// Don't waste a request if the message looks the same as before or if there's no message
			if (!message || this.oldmessage == message) {
				return;
			}
			
			if (this.options.use_storage) {
			
				obj = this.read_storage();
				
				obj[this.unique_id] = {
					'message': message,
					'subject': subject
				};
			
				this.save_storage(obj);
				
				this.show_message(this.lang.success_saved, {clickToClose: true});
				
				this.oldmessage = message;
				
				return;
				
			}
			
			$.ajax({
				url: 'xmlhttp.php',
				dataType: 'json',
				data: {
					action: 'autosave_draft',
					subject: subject,
					message: message,
					fid: this.fid,
					tid: this.tid
				},
				traditional: true,
				complete: function (data) {
				
					if (data.responseText == 1) {
						local_message = AutoSave.lang.success_saved;
					}
					else if (data.responseText == 2) {
						local_message = AutoSave.lang.error_empty_data;
					}
					else {
						local_message = AutoSave.lang.error_failed;
					}
					
					AutoSave.show_message(local_message, {clickToClose: true});
					
					AutoSave.oldmessage = message;
					
				}
			});
			
		},
		
		load_drafts: function () {
		
			if (!this.draft_message) {
				return false;
			}
			
			// Message
			this.textarea.focus().val(this.draft_message);
			
			// Eventual subject
			if ($('input[name="subject"]').length > 0) {
				$('input[name="subject"]').val(this.draft_subject);
			}
			
			if (this.options.use_scroll && typeof MyBBEditor === 'undefined') {					
				window.scrollTo(0, this.textarea.offset().top - 100);
			}
				
			this.oldmessage = this.draft_message;
		
		},
		
		show_message: function (message, options, callback) {
		
			if (!message) {
				return false;
			}
			
			if (!options) {
				options = '';
			}
			
			if (!callback) {
				callback = '';
			}
			
			switch (this.options.use_messages) {
				
				case 1:
					return false;
					break;
				
				case 2:
					humane.log(message, options, callback);
					break;
					
				case 3:
					
					special_options = {};
					
					if (options['timeout']) {
						special_options['life'] = options['timeout'];
					}
					
					// jGrowl doesn't have any callback on click, we've got to create one for it
					$(document).on('click', '#jGrowl', function() {
						
						if (typeof callback == 'function') {
						
							callback();
							
							// Reset the variable, so that the check above will fail in subsequent calls
							callback = '';
							
							// This is way too cool
							$.fn.jGrowl.prototype.element = '#jGrowl';								
							$.fn.jGrowl.prototype.close();
							
						}
						
					});
					
					$.jGrowl(message, special_options);
					
					break;
			
			}
		
		},
		
		delete_drafts_on_submit: function () {
			
			if (!this.options.use_storage) {
				return false;
			}
			
			obj = this.read_storage();
			
			if (obj[this.unique_id]) {
				delete obj[this.unique_id];
			}
			
			this.save_storage(obj);
			
		},
		
		read_storage: function() {
		
			obj = localStorage.autosaved_drafts;
			
			// Wrapped in a try/catch block to prevent obj to be undefined
			try {
				obj = JSON.parse(obj);
			}
			catch (e) {
				obj = {};
			}
			
			return obj;
			
		},
		
		save_storage: function(obj) {
		
			localStorage.autosaved_drafts = JSON.stringify(obj);
			
		}	
	
	}

})(jQuery, window, document);