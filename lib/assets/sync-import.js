function syncManualItems( element, action, loop = 0 ) {
	element.classList.add('disabled');
	element.innerHTML = ajaxAction.label_syncing + ' <span class="spinner is-active"></span>';

	const isOdd = number => number % 2 !== 0;
	class_task = isOdd(loop) ? 'odd' : 'even';
	
	// AJAX request.
	fetch( ajaxAction.url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: 'action=' + action + '&nonce=' + ajaxAction.nonce + '&loop=' + loop,
	})
	.then( (resp) => resp.json() )
	.then( function(results) {
		if ( results.success ){
			if( ! results.data.finish ) {
				syncManualItem(element, action, results.data.loop );
			} else {
				element.classList.remove('disabled');
				element.innerHTML = ajaxAction.label_sync;
				results.data.message = ajaxAction.label_sync_complete + ' ' + results.data.loop;
			}
		} else {
			element.classList.remove('disabled');
			element.innerHTML = ajaxAction.label_sync;
		}
		// Message.
		if( results.data.message != undefined ){
			progressElement = document.createElement('p');
			progressElement.className = class_task;
			document.querySelector('#logwrapper #loglist').appendChild(progressElement);
			progressElement.innerHTML = results.data.message;
		}
		const loglist = document.querySelector('#logwrapper #loglist');
		loglist.scrollTo({ top: loglist.scrollHeight, behavior: "smooth" });
	})
	.catch(err => console.log(err));
}