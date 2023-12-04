function syncOrderERP( order_id, element_id, type ) {
	button_sync = document.getElementById(element_id);
	button_sync.classList.add('disabled');
	button_sync.removeAttribute('onclick');
	button_sync.innerHTML = ajaxActionOrder.label_syncing + ' <span class="spinner is-active"></span>';

	// AJAX request.
	fetch( ajaxActionOrder.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: 'action=sync_erp_order&nonce=' + ajaxActionOrder.nonce + '&order_id=' + order_id + '&type=' + type,
	})
	.then((response) => response.json())
	.then( (response) => {
		button_sync.innerHTML = ajaxActionOrder.label_synced;
		button_sync.insertAdjacentHTML( 'afterend', '<p>' + response.data.message + '</p>' );
	})
	.catch(err => console.log(err) );
}