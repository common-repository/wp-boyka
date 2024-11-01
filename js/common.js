
jQuery(document).ready(function () {
	
	/* Populating of each quality select */
	for(var i = 100; i >= 0; i--)
		jQuery("select[name='boyka_compression_level']").append("<option value=\""+i+"\">"+i+" %</option>");
	
	/* Update quality on change */
	jQuery("select[name='boyka_compression_level']").change(function() {
		
		var class_img = jQuery(this).attr("class");
		var compression_level = jQuery(this).val();
		
		jQuery("a[class="+class_img+"]").each(function(i) {
			
			var currHref = ""+jQuery(this).attr("href");

			if(currHref.indexOf("compression_level=") < 0)
				jQuery(this).attr("href",currHref+"&compression_level="+compression_level);
			else {
				currHref = currHref.replace(/compression_level=([0-9])+/, "compression_level="+compression_level);
				jQuery(this).attr("href", currHref);
			}
			
			
		});

	});

});


