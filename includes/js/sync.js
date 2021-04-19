function syncNEOOrders() {

	var sync_num = jQuery('#wcsen_sync_num_series').val();
	
	var btn = jQuery('#wcsen_customize_button');

	btn.attr("disabled", true);
	btn.text('Synchronizing...');

	var loaderContainer = jQuery( '<span/>', {
        'class': 'loader-image-container'
    }).insertAfter( btn );

    var loader = jQuery( '<img/>', {
        src: '/wp-admin/images/loading.gif',
        'class': 'loader-image'
    }).appendTo( loaderContainer );

	var data = {
	'action': 'sync_orders',
	'sync_num': sync_num    // We pass php values differently!
	};

	// We can also pass the url value separately from ajaxurl for front end AJAX implementations
	jQuery.post(ajax_object.ajax_url, data, function(response) {
		alert(response);
		location.reload();
	});

}
