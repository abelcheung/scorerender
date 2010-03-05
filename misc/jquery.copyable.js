jQuery.fn.copyable = function(getTextFunction) {
	var self = this;

	self.each(function() {
		var copyable = this;

		if (jQuery.browser.msie) {
			var clip = jQuery(copyable).data('clip');
			if (clip === undefined) {
				clip = new Object();
				jQuery(copyable).data('clip', clip);
			}
			clip.setText = function(data) {
				window.clipboardData.setData('Text', data);
			};
			jQuery(copyable).mousedown(function(e) {
				getTextFunction(e, clip);
			});
			
		} else {
			var copyableId = jQuery(copyable).attr("id");
			var containerId = jQuery(copyable).attr("id") + "_container";

			var clip = jQuery(copyable).data('clip');
			if (clip === undefined) {
				clip = new ZeroClipboard.Client();
				jQuery(copyable).data('clip', clip);
			}

			//wrapper trigger with a div container
			jQuery(copyable).wrap("<div id='" + containerId + "' style='position:relative;display:inline'></div>");

			jQuery(copyable).mousedown(getTextFunction);

			clip.glue(copyableId, containerId);
			clip.setHandCursor(false);

			//connect flash events to underlying element
			clip.addEventListener('mouseDown', function(client) {
				jQuery(copyable).trigger("mousedown", client);
			});
			clip.addEventListener('mouseOver', function(client) {
				jQuery(copyable).trigger("mouseover", client);
			});
			clip.addEventListener('mouseOut', function(client) {
				jQuery(copyable).trigger("mouseout", client);
			});
			clip.addEventListener('mouseUp', function(client) {
				jQuery(copyable).trigger("mouseup", client);
			});
		}
	});
	return self;
};