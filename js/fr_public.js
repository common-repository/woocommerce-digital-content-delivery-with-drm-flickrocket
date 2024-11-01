var count = 0;
var tid = null;

jQuery(document).ready(function(){
	if (jQuery("#fr_wait_div").length)
	{
		checkContent();
		// set interval to check all 2.5 seconds
		tid = setInterval(checkContent, 2500);
	}

	let url = window.location.href.toLowerCase();
	let my_account_page = url.indexOf("/my-account") > 0;
    if (my_account_page) {
        let queryString = window.location.search;
        let urlParams = new URLSearchParams(queryString);

		// If fr_redirect_uri was given, we are in external login
        if (urlParams.has('fr_redirect_uri')) {
            let redirect_uri = urlParams.get('fr_redirect_uri')
            if (urlParams.has('fr_email')) {
                let email = urlParams.get('fr_email')
                let ef = document.getElementById('username');
                if (ef != null) {
                    ef.value = email;
                    ef.readOnly  = true;
                }
                let lp = document.getElementById('password');
                if (lp != null) {
                    lp.focus();
                }
            }
        }

		// If "unlock=1" we are a forwarded customer for code redemption
		if (urlParams.has('fr_unlock')) {
			// Don't show customer_login section
			let ef = document.getElementById('customer_login');
			if (ef) {
				ef.style.display = 'none';
			}
		}
    }
});

//Check if content becomes available
function checkContent() {
	count++;
	jQuery.ajax({
		type: "POST",
		url: "?fr_action=checkContent",
		dataType:'json',
		cache: false,
		success: function(data_return)
		{	
			if(data_return != null && data_return.result != 'error')
			{
				jQuery("#fr_content_div").html(data_return.return);
				abortTimer();
			}
		}
	});

	if (count > 24)
	{
		//Waited too long, remove wait animation and stop polling
		jQuery("#fr_wait_div").html("");
		abortTimer();
	} 
}

//Stop checking for content
function abortTimer() {
	clearInterval(tid);
}	

//Flickrocket content frame loaded, display it now
function flickFrameLoaded() {
	jQuery("#fr_wait_div").hide();
	jQuery("#fr_content_div").show();
	abortTimer();
}

// Play preview
function playPreview() {
	setTimeout(function(){ document.getElementById('previewPlayer').play(); }, 500);
}

// Show code redemption
function fr_activate_redeem_code() {
	document.getElementById('fr_activate_redeem_code').style.display = 'none';
	document.getElementById('fr_redeem_code').style.display = 'block';
}

window.addEventListener('message', function(e) {
	var eventName = e.data[0];
	var data = e.data[1];
	switch(eventName) {
		case 'setHeight':
			var iframe = jQuery("#frIframe");
			iframe.height(data);
			break;
	}
}, false);