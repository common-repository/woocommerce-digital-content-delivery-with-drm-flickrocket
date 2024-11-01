// const FR_OAUTH_URL = 'https://oauth.flickrocket.com/oauth2/auth';
// const FR_CLIENT_ID = 'WooCommerceFree.2033545684.apps.flickrocket.com';

jQuery(document).ready(function(){
	
	var flickRCB = 0;
	var flickRocketPMB = jQuery('#flickrocket_projectid');
	flickRocketPMB.insertAfter('#woocommerce-product-data');
	
	jQuery('#fr_message').hide();
	var tabLink = document.URL;
	var linkArray = tabLink.split('=');
	var tabName = linkArray[2];
	if(tabName == 'flickrocket') {
		jQuery('input.button-primary').hide();
	}

	//Products
	var frProdCount = 0;
	
	var frproducts = [];
	var tpproducts = [];
	
	var frPage = 1;
	var tpPage = 1;

	//Get shop url
	var href = window.location.href;
	var index = href.toLowerCase().indexOf('/wp-admin',8);
	var homeUrl = href.substring(0, index);
	
	// Start fetching digital product if on digital products page
	if (jQuery('#fr_sync_products_table_body').length > 0) {
		loadFrProductsPaged();
		loadWcProductsPaged();
		showGetProductActivity();
	}

	// Start handling analytics data if on analytics page
	if (jQuery('#fr_analytics').length > 0) {

		String.prototype.toHHMMSS = function () {
            let sec_num = parseInt(this, 10); // dont forget the second param
            let hours = Math.floor(sec_num / 3600);
            let minutes = Math.floor((sec_num - (hours * 3600)) / 60);
            let seconds = sec_num - (hours * 3600) - (minutes * 60);

            if (hours < 10) { hours = '0' + hours; }
            if (minutes < 10) { minutes = '0' + minutes; }
            if (seconds < 10) { seconds = '0' + seconds; }
            return hours + ':' + minutes + ':' + seconds;
        };

		var theme = GetChartStyle();
		var echart_IssuedLicenses = echarts.init(document.getElementById('IssuedLicenses_Chart'), theme);
		var echart_FluxPlayerDevices = echarts.init(document.getElementById('FluxPlayerDevices_Chart'), theme);
		var echart_FluxPlayerContentUsage = echarts.init(document.getElementById('FluxPlayerContentUsage_Chart'), theme);
		var echart_LicensePurchases = echarts.init(document.getElementById('LicensePurchases_Chart'), theme);
	
		window.addEventListener('resize', function ()
		{
			echart_IssuedLicenses.resize();
			echart_FluxPlayerDevices.resize();
			echart_FluxPlayerContentUsage.resize();
			echart_LicensePurchases.resize();
		});

		GetLicenseHistory(); 
		GetFluxPlayerDevices();
		GetFluxPlayerContentUsage();
		GetLicensePurchases();
	}
	
	function showGetProductActivity() {
		setTimeout(function () {
			var text = jQuery('#fr_getting_sync_products').text();
			jQuery('#fr_getting_sync_products').text(text + ". ");

			if (tpPage != 0 || frPage != 0) showGetProductActivity();
		}, 500);
	}

	function loadFrProductsPaged() {
		//Get products
		jQuery.ajax({
			url: homeUrl + '/wp-admin/admin-post.php?action=GetFrProducts&Page='+frPage,
			type: "GET",
			contentType: "application/json; charset=utf-8",
			dataType: "json",
			cache: false,
			success: function(jsonResult){
				if (jsonResult.frproducts != null)
				{
					//Received products
					frproducts = frproducts.concat(jsonResult.frproducts);

					//Prepare for getting next page
					frPage++;
					if (jsonResult.frproducts.length == 50)
					{
						loadFrProductsPaged(); //Load next page
					}
					else
					{
						//Mark as complete
						frPage = 0;
						loadProductsFinished();
					}
				}
			},
		});
	}

	function loadWcProductsPaged() {
		//Get products
		jQuery.ajax({
			url: homeUrl + '/wp-admin/admin-post.php?action=GetWcProducts&Page='+tpPage,
			type: "GET",
			contentType: "application/json; charset=utf-8",
			dataType: "json",
			cache: false,
			success: function(jsonResult){
				if (jsonResult.tpproducts != null)
				{
					//Received products
					tpproducts = tpproducts.concat(jsonResult.tpproducts);

					//Prepare for getting next page
					tpPage++;
					if (jsonResult.tpproducts.length == 50)
					{
						loadWcProductsPaged(); //Load next page
					}
				}

				//Mark as complete
				tpPage = 0;
				loadProductsFinished();
			},
		});
	}

	function loadProductsFinished() {
		if (tpPage != 0 || frPage != 0) return; //Return if not all products have been loaded

		removeUnsupportedProducts(frproducts);
		frProdCount = frproducts.length;

		jQuery.each(frproducts, function(i, item) {
			var tr = jQuery('<tr>').append(
				checkProductExistsTp(item.product_id) == true ?
					jQuery('<td>').append('<input type="checkbox" name="no_sync_cb" id="cb_sync_prod_'+i+'" /><label for="cb_sync_prod_'+i+'"><span class="hide-visually">Select</span></label>') :
					jQuery('<td>').append('<input type="checkbox" name="sync_cb" id="cb_sync_prod_'+i+'" checked /><label for="cb_sync_prod_'+i+'"><span class="hide-visually">Select</span></label>'),
				jQuery('<td>').text(item.product_id),
				jQuery('<td>').text(item.title),
				jQuery('<td>').text(GetProductTypeString(item.product_type)),
				checkProductExistsTp(item.product_id) == true ?
					jQuery('<td>').text('Yes') :
					jQuery('<td>').text('No')
			).appendTo('#fr_sync_products_table_body');
		});
		jQuery('#fr_getting_sync_products').hide();
		if (frProdCount > 0) jQuery('#fr_sync_product_display').show();
	}

	jQuery("#fr_sync_toggle").click(function() {
		var checkBoxes = jQuery("input[name=sync_cb]");
		checkBoxes.prop("checked", !checkBoxes.prop("checked"));
		return false;
	});                 

	jQuery("#fr_sync_button").click(function()
	{
		jQuery("#fr_sync_product_display").hide();
		jQuery("#fr_sync_button").hide();
		jQuery("#fr_sync_progress").show();
		syncNextProduct(0);
	});

	function removeUnsupportedProducts(frproducts) {
		for (i=0; i<frproducts.length; i++)
		{
			// Remove Physical, Service, App, Theme, Certificate, Variation
			if (frproducts[i].product_type == 6 
				|| frproducts[i].product_type == 17 
				|| frproducts[i].product_type == 18 
				|| frproducts[i].product_type == 19 
				|| frproducts[i].product_type == 20 
				|| frproducts[i].product_type == 21
				) 
				{
				frproducts.splice(i,1);
				i--;
			}
		}
	}

	function checkProductExistsTp(product_id) {
		if (tpproducts != null)
		{
			var filtered = tpproducts.filter(function (item) {
				return item == product_id;
			});
			return filtered.length > 0;
		}
		return false;
	}

	function GetProductTypeString(product_type) {
		if (product_type == 1) return "Video (SD)";
		if (product_type == 3) return "Website access";
		if (product_type == 4) return "Software | Generic";
		if (product_type == 5) return "Audio Collection";
		if (product_type == 6) return "Physical Product";
		if (product_type == 7) return "PDF";
		if (product_type == 8) return "Product Collection";
		if (product_type == 9) return "Video (HD)";
		if (product_type == 16) return "ePub";
		if (product_type == 17) return "Service";
		if (product_type == 18) return "App";
		if (product_type == 19) return "Theme";
		if (product_type == 20) return "Certificate";
		if (product_type == 21) return "Variation";
		if (product_type == 22) return "Video (VR)";
		if (product_type == 23) return "Audio Track";
		if (product_type == 24) return "Audio Album";
		if (product_type == 26) return "Access Group";
		if (product_type == 27) return "Generic File";
		if (product_type == 28) return "Template";
		if (product_type == 29) return "HTML Package";
		if (product_type == 30) return "SCORM Package";
		return 'Unknown';
	}

	function syncNextProduct(index) {

		//Get shop url
		var href = window.location.href;
		var start = href.toLowerCase().indexOf('/wp-admin',8);
		var homeUrl = href.substring(0, start);

		while ( index < frproducts.length && !jQuery('#cb_sync_prod_'+index)[0].checked )
		{
			//show progress
			progress_percent = Math.round(index * 100 / frproducts.length * 100) / 100;
			jQuery( "#fr_sync_progressbar" ).progressbar({ value: progress_percent });
			jQuery( "#fr_sync_progressbar_label" ).text(progress_percent+"%");
			
			if (++index >= frproducts.length) break;
		}
		if (index < frproducts.length)
		{
			//show progress
			progress_percent = Math.round(index * 100 / frproducts.length * 100) / 100;
			jQuery( "#fr_sync_progressbar" ).progressbar({ value: progress_percent });
			jQuery( "#fr_sync_progressbar_label" ).text(progress_percent+"%");
			
			//Sync product
			jQuery.ajax({
				url: homeUrl + '/wp-admin/admin-post.php?action=fr_product_sync',
				type: "POST",
				dataType: "json",
				data: { "postdata": JSON.stringify(frproducts[index]) },
				complete: function(result){

					//Next product
					syncNextProduct(++index);
				},
				cache: false
			});
		}
		else
		{
			jQuery( "#fr_sync_progressbar" ).progressbar({ value: 100 });
			jQuery( "#fr_sync_progressbar_label" ).text("Completed");
		}
	}

	// Handle OAuth
	jQuery("#oauth_login").click(function()
	{
		//Get shop url
		var href = window.location.href;
		var start = href.toLowerCase().indexOf('/wp-admin',8);
		var homeUrl = href.substring(0, start);

		var url = fr_options.FR_OAUTH_URL +
			"?client_id=" + encodeURI(fr_options.FR_CLIENT_ID) + 
			"&scope=orders,customers,themes,companies,products,webhooks,statistics,player" +
			"&access_type=offline" +
			"&redirect_uri=" + encodeURI(homeUrl + '/wp-admin/admin-post.php?action=fr_oauth_callback');

		window.open(url, "Login to Flickrocket", 'height=700,width=600');
	});

	// Handle signup 

	if(jQuery('#fr_sign_up').length >0 ){
		// Hide "Save changes" button
		jQuery('.woocommerce-save-button').hide();
	 }

	window.addEventListener("message", (event) => {
		if (event.data.hasOwnProperty('id') && event.data.id == 'FrExtAdminInitComplete') {
			//Get shop url
			let href = window.location.href;
			let start = href.toLowerCase().indexOf('/wp-admin',8);
			let homeUrl = href.substring(0, start);

			// Send data to prefill
			let data = {
				id: "FrExtAdminInfo",
				email: fr_options.BLOG_EMAIL,
				company: "",
				firstName: "",
				lastName: "",
				clientId: encodeURI(fr_options.FR_CLIENT_ID),
				scope: "orders,customers,themes,companies,users,products,webhooks,statistics,player",

				source: "plugin",
				platform: "woocommerce",
				mauticId: 0,

				connectionId: 0,
				redirectUrl: encodeURI(homeUrl + '/wp-admin/admin-post.php?action=fr_oauth_callback')
			};

			registerWindow.postMessage(data, '*',);
		}

		if (event.data.hasOwnProperty('message') && event.data.message == 'fr_complete') {
			window.location = window.location.href; //Refresh page
        }
	});

	var registerWindow;

	jQuery("#fr_sign_up").click(function()
	{
		//Get shop url
		var url = fr_options.FR_EXTADMIN_URL;
        registerWindow = window.open(url, 'Login', 'toolbar=0,location=0,directories=0,menubar=0,scrollbars=1,resizable=0,width=580,height=720');
        return false;
	});

	jQuery("#fr_content_type").change(function()
	{
		let s = this.value;
        if (!isNaN(parseFloat(s)) && isFinite(s) && s > 0) {
			// Valid content_type dropdown selection, enable button
			jQuery("#fr_create_product").prop('disabled', false);
		}
		else {
			// Disable button
			jQuery("#fr_create_product").prop('disabled', true);
		}
	});

	jQuery("#fr_upload").click(function()
	{
		var lpid = jQuery('#flickrocket_project_id').length > 0 ? jQuery('#flickrocket_project_id').val() : "";
		var token = fr_options.FR_ACCESS_TOKEN;
		var url = fr_options.FR_UPLOADER_URL;
		var width = 770;
		var height = 800;
		var left = (screen.width / 2) - (width / 2);
		var top = (screen.height / 2) - (height / 2);

		window.open(url + '?pid=' + lpid + '&token=' +  token, 'Uploader', 'width=' + width + ',height=' + height + ', top=' + top + ', left=' + left + ',resizable=no,scrollbars=no,toolbar=no,status=no,location=no');
		return false;
	});

	// Handle admin
	jQuery(".flick_error").hide();
	jQuery("#open_pupupbox").find("a").trigger("click");
		
	jQuery('#flickrocket_license_area').hide();	
	
	if(jQuery('#product-type').val() == 'simple'){
		if((jQuery('#_product_license_id').length > 0 && jQuery('#_product_license_id').val() != '') &&
			(jQuery('#flickrocket_project_id').length > 0 && jQuery('#flickrocket_project_id').val() != ''))
		{
			jQuery('#_flickrocket').attr('checked', true);
		}
		if( jQuery('#_flickrocket').is(':checked') == true){
			jQuery('#flickrocket_license_area').show();	
			jQuery('#flickrocket_projectid').show();
		}
	}
		
	jQuery('#_flickrocket').click(function(){ //Flickrocket checkbox 
		if(jQuery('#_flickrocket').is(':checked') == true){		
			jQuery('#flickrocket_license_area').show();	//Variable products
			jQuery('._license_id_field').show(); // Simple product
			jQuery('._quality_field').show(); // Simple product
			jQuery('#flickrocket_projectid').show(); // Generic
		}else{
			jQuery('#flickrocket_license_area').hide();
			jQuery('._license_id_field').hide();
			jQuery('._quality_field').hide();
			jQuery('#flickrocket_projectid').hide();
		}
	});
	
	jQuery('#product-type').change(function(){
		if((jQuery('#product-type').val() == 'simple') && (jQuery('#_flickrocket').is(':checked') == true)){
			// simple product
			if(jQuery('#_flickrocket').is(':checked') == true){
				jQuery('#flickrocket_license_area').show();	
				jQuery('#flickrocket_projectid').show();
			}else{
				jQuery('#flickrocket_license_area').hide();	
				jQuery('#flickrocket_projectid').hide();
			}
		}
		else
		{
			// Variable product
			jQuery('#flickrocket_license_area').hide();	
			jQuery('#flickrocket_projectid').hide();
		}
	});
	
	jQuery('.fr_settings_fields').change(function(){
			jQuery('#fr_message').show();
			jQuery('#fr_message').html('<div class="updated" style="padding:10px;"><img src="images/loading.gif" alt=""></div>');
	});
	
	var myInterval = setInterval(function(){
		if( !jQuery("#variable_product_options").length )
		{
			return;
		}
		else
		{
			jQuery('.woocommerce_variation').each(function(index, value){
				if(jQuery('#checkBoxVMB_'+index).is(':checked') == true){
					flickRCB++;
					jQuery('#flickRocketVMB_'+index).show();
				}else{
					jQuery('#flickRocketVMB_'+index).hide();
				}
			});
		}
	}, 2000 );
	
	jQuery('.woocommerce_variation').each(function(index, value){
		if(jQuery('#checkBoxVMB_'+index).is(':checked') == true){
			flickRCB++;
			jQuery('#flickRocketVMB_'+index).show();
		}else{
			jQuery('#flickRocketVMB_'+index).hide();
		}
	});
	
	if(jQuery('#product-type').val() == 'variable'){
		setTimeout(function(){
			jQuery('#flickrocket_projectid').show();		
		}, 500)
	}
	
	jQuery(document).on('click', '.checkBoxVMB', function(){
		var checkBoxIndex = this.alt;
		if(jQuery(this).is(':checked') == true){
			flickRCB++;
			jQuery('#flickRocketVMB_' + checkBoxIndex).show();
			jQuery('input[name="variable_is_virtual[' + checkBoxIndex + ']"]').attr('checked', true);
			jQuery('#flickRocketVMB_' + checkBoxIndex).parent().parent().parent().parent().parent().find('.hide_if_variation_virtual').hide();
		}else{
			flickRCB--;
			jQuery('#flickRocketVMB_' + checkBoxIndex).hide();
			jQuery('input[name="variable_is_virtual[' + checkBoxIndex + ']"]').attr('checked', false);			
			jQuery('#flickRocketVMB_' + checkBoxIndex).parent().parent().parent().parent().parent().find('.hide_if_variation_virtual').show();
		}
		
		if(flickRCB > 0){
			jQuery('#flickrocket_projectid').show();
		}else{
			jQuery('#flickrocket_projectid').hide();
		}		
	});

	// // Group Management on profile page
	// var assigned_groups;

	// if (jQuery('#fr_group_management').length > 0) {
	// 	update_groups();
	// }

	// jQuery("#fr_assign_group_button").click(function() {
	// 	// Assign new group
	// 	var selected_group = jQuery( "#fr_assign_group" ).val();
	// 	assigned_groups.forEach( group => {
	// 		if (group.id == selected_group)
	// 		{
	// 			group.assigned = true;
	// 		}
	// 	});

	// 	// Update groups
	// 	update_groups();
	// });

	// function remove_group(group_id)
	// {
	// 	assigned_groups.forEach( group => {
	// 		if (group.id == group_id)
	// 		{
	// 			group.assigned = false;
	// 		}
	// 	});
	// 	update_groups();
	// }

	// function update_groups() {
	// 	if (!assigned_groups)
	// 	{
	// 		// Assign intial groups
	// 		assigned_groups = JSON.parse(fr_original_groups);
	// 	}

	// 	// Initialize UI elements
	// 	jQuery('#fr_assign_group').empty();
	// 	jQuery('#fr_assigned_groups').empty();
	// 	jQuery('#fr_send_group_notify_on').hide();
	// 	jQuery('#fr_send_group_notify_off').show();

	// 	assigned_groups.forEach( group => {
	// 		if (group.assigned == true)
	// 		{
	// 			// List all assigned groups
	// 			jQuery('#fr_assigned_groups').append("<span>" + group.name + "&nbsp;|&nbsp;</span>");
	// 			jQuery('#fr_assigned_groups').append("<a id='Remove_" + group.id + "' href='#'>Remove</a>");
	// 			jQuery('#fr_assigned_groups').append("<br />");
				
	// 			// Handle clicks
	// 			jQuery(document).on('click', '#Remove_' + group.id , function() {
	// 				remove_group(group.id);
	// 				return false;
	// 		   });
	// 		   // Show notification checkbox
	// 		   jQuery('#fr_send_group_notify_on').show();
	// 		   jQuery('#fr_send_group_notify_off').hide();
	// 		}
	// 		else
	// 		{
	// 			// Build dropdown for all unassigned groups
	// 			var option = '<option value="'+ group.id + '">' + group.name + '</option>';
	// 			jQuery('#fr_assign_group').append(option);
	// 		}
	// 	});
	// 	var temp = JSON.stringify(assigned_groups);
	// 	jQuery('#fr_groups').val(temp);
	// }

	// // Group management on settings page
	// var role_group_assignment = Array();
	// var all_groups;
	// var all_roles;

	// if (typeof all_groups_json !== 'undefined' && all_groups_json.length > 0)
	// {
	// 	// if groups exist, populate roles dropdown
	// 	if (!all_roles)
	// 	{
	// 		//Init roles and groups
	// 		all_roles = JSON.parse(all_roles_json);
	// 		all_groups = JSON.parse(all_groups_json);
	// 		let group_to_role_setting = jQuery('#group_to_role_setting').val();
	// 		if (group_to_role_setting) role_group_assignment = JSON.parse(group_to_role_setting);
	// 	}

	// 	// Roles dropdown
	// 	jQuery('#fr_roles_and_groups').append('<label>Role:</label>&nbsp;<select id="fr_roles"></select>');
	// 	let roles_keys = Object.values(all_roles);
	// 	roles_keys.forEach( role => {
	// 		var option = '<option value="'+ role + '">' + role + '</option>';
	// 		jQuery('#fr_roles').append(option);
	// 	});

	// 	// Group dropdown
	// 	jQuery('#fr_roles_and_groups').append('<label>Group:</label>&nbsp;<select id="fr_groups"></select>');
	// 	all_groups.forEach( group => {
	// 		var option = '<option value="'+ group.id + '">' + group.name + '</option>';
	// 		jQuery('#fr_groups').append(option);
	// 	});

	// 	// Assign button
	// 	let btn_attr = '';
	// 	if (all_groups.length == 0) btn_attr = 'disabled';
	// 	jQuery('#fr_roles_and_groups').append('&nbsp;<button id="fr_btn_assign_role_group" class="button-secondary" ' + btn_attr + '>Assign</button>');
	// 	jQuery(document).on('click', '#fr_btn_assign_role_group' , function() {
	// 		assign_group_to_role();
	// 		return false;
	//    	});

	//    update_roles_groups();
	// }

	// // Apply current role/group settings to existing users
	// // Notify all users for whom a change was applied
	// // ====================================================
	
	// // "Apply to all users" button
	// if (jQuery('#fr_role_group_management') != null)
	// {
	// 	jQuery('#fr_role_group_management').append('<div id="fr_assign_all_users" style="display:block;"><br /><button id="fr_btn_assign_all_users">Apply roles to all users</button><span>' + 
	// 		'&nbsp;<b>Important:</b> Applies groups to all users and sends notification emails to newly assigned users.' +
	// 		'</span></div><br /><br /><div id="fr_apply_users_progress" style="display:block;"></div>');
	// 	jQuery(document).on('click', '#fr_btn_assign_all_users' , function() {
	// 		apply_all_users();
	// 		return false;
	// 	});
	// }

	// var fr_wp_users;
	// var fr_wp_user_index = -1;

	// function apply_all_users(){

	// 	// Get user count
	// 	nonce = jQuery(this).attr("data-nonce");
	// 	jQuery.ajax({
	// 		type: "post",
    //      	dataType: "json",
    //      	url: fr_options.ajaxurl,
	// 		data : {action: "GetUsers", nonce: nonce},
	// 		success: function(jsonResult){
	// 			fr_wp_users = jsonResult;
	// 			apply_groups_to_user();
	// 		},
	// 	});
	// }

	// function apply_groups_to_user(){
		
	// 	// Apply groups to users
	// 	if (++fr_wp_user_index < fr_wp_users.length)
	// 	{	
	// 		show_users_progress();

	// 		nonce = jQuery(this).attr("data-nonce");
	// 		jQuery.ajax({
	// 			type: "post",
	// 			dataType: "json",
	// 			url: fr_options.ajaxurl,
	// 			data: 	{ 	action: "ApplyGroupsToUser", 
	// 						nonce: nonce, 
	// 						user_id: fr_wp_users[fr_wp_user_index].data.ID,
	// 						fr_send_group_notify_checkbox: "1"
	// 					},
	// 			success: function(jsonResult){
	// 				apply_groups_to_user();
	// 			},
	// 		});
	// 	}
	// 	else
	// 	{
	// 		// Process finished
	// 		jQuery('#fr_apply_users_progress').empty();
	// 		jQuery('#fr_apply_users_progress').append('<span><b>Applying groups complete</b></span>' );
	// 		jQuery('#fr_apply_users_progress').show();
	// 	}
		
	// }

	// function show_users_progress(){
	// 	jQuery('#fr_assign_all_users').hide();
		
	// 	jQuery('#fr_apply_users_progress').empty();
	// 	jQuery('#fr_apply_users_progress').append('<span><b>Applying groups to users: ' + (fr_wp_user_index + 1) + '/' + fr_wp_users.length +'</b></span>' );
	// 	jQuery('#fr_apply_users_progress').show();
	// }

	// // Settings functions
	// // ====================================================
	// function assign_group_to_role() {
	// 	let role = jQuery('#fr_roles option:selected').val();
	// 	let group = parseInt(jQuery('#fr_groups option:selected').val());

	// 	if (!role_group_assigned(role, group))
	// 	{
	// 		// Assign group to role
	// 		let found = false;
	// 		role_group_assignment.forEach( r => {
	// 			if (r[0] == role ) {
	// 				found = true;
	// 				r[1].push(group);
	// 			}
	// 		})
	// 		if (!found) {
	// 			role_group = new Array(2);
	// 			role_group[0] = role;
	// 			role_group[1] = new Array();
	// 			role_group[1].push(group);
	// 			role_group_assignment.push(role_group);
	// 		}

	// 	}
	// 	update_roles_groups();
	// }

	// function update_roles_groups(){

	// 	jQuery('#fr_roles_groups_list').empty();

	// 	if (role_group_assignment.length > 0)
	// 	{
	// 		// Construct table
	// 		let html = "<table><th align='left'><b>Role</b></th><th align='left'><b>Groups</b></th>";
	// 		role_group_assignment.forEach( role => {
	// 			html += "<tr><td valign='top'>" + role[0] + "</td><td>";
	// 			role[1].forEach( group => {
	// 				html += "<span>" + get_group_name(group) + "<span>&nbsp;|&nbsp<a id='Remove_" + role[0] + "_" + group + "' name='fr_remove_link' href='#'>Remove</a><br />"; 
	// 			});
	// 			html += "</td></tr>";
	// 		});
	// 		html += "</table>";
			
			
	// 		jQuery('#fr_roles_groups_list').append(html);

	// 		// Add remove handlers
	// 		role_group_assignment.forEach( role => {
	// 			role[1].forEach( group => {
	// 				jQuery(document).on("click", "#Remove_" + role[0] + "_" + group , function() {
	// 					remove_role_group(role[0], group);
	// 					return false;
	// 			});
	// 			});
	// 		});
	// 	}

	// 	// Store current data in hidden field
	// 	let assignment_json = JSON.stringify(role_group_assignment);
	// 	jQuery('#group_to_role_setting').val(assignment_json);
	// }

	// function remove_role_group( role, group){
	// 	 role_entry = role_group_assignment.find( x => x[0] == role);
	// 	 if (!role_entry) return false;
	// 	 group_entry = role_entry[1].find( x =>  x == group);
	// 	 if (!group_entry) return false;
	// 	 role_entry[1].pop(group_entry);
	// 	//  if (role_entry[1].length == 0) role_group_assignment.pop(role_entry);
	// 	 update_roles_groups();
	// 	 return false;
	// }

	// function get_group_name( group_id){
	// 	group = all_groups.find( x => x.id == group_id);
	// 	return group.name;
	// }

	// function role_group_assigned(role, group) {
	// 	role_entry = role_group_assignment.find( x => x[0] == role);
	// 	if (!role_entry) return false;
	// 	group_entry = role_entry[1].find( x =>  x == group);
	// 	if (!group_entry) return false;
	// 	return true;
	// }

	// Chart functions

	function MakeTable(json, rootName)
	{
		var InitData = true;
		if (jQuery('#' + rootName + '_Table thead').children().length > 0) {
			jQuery('#' + rootName + '_Table_data').html('');
		}
		buildHtmlTable('#' + rootName + '_Table_data', json);
	}

	function buildHtmlTable(selector, data)
	{
		var columns = addAllColumnHeaders(data, selector);

		for (var i = 0; i < data.length; i++) {
			var row$ = jQuery('<tr/>');
			for (var colIndex = 0; colIndex < columns.length; colIndex++) {
				var cellValue = data[i][columns[colIndex]];
				if (cellValue == null) cellValue = "";
				if (colIndex == 0) {
					row$.append(jQuery('<td class=""/>').html(cellValue));
				}
				else {
					row$.append(jQuery('<td/>').html(cellValue));
				}
			}
			jQuery(selector).append(row$);
		}
	}

	function addAllColumnHeaders(myList, selector)
	{
		var columnSet = [];
		var headerTr$ = jQuery('<tr/>');

		for (var i = 0; i < myList.length; i++) {
			var rowHash = myList[i];
			for (var key in rowHash) {
				if (jQuery.inArray(key, columnSet) == -1) {
					columnSet.push(key);
					headerTr$.append(jQuery('<th/>').html(key));
				}
			}
		}
		jQuery(selector).append('<thead/>');
		var bla = jQuery(selector).find('thead');
		bla.append(headerTr$);

		return columnSet;
	}

	function InitDonutChart(ctrl) {
		ctrl.setOption({
			tooltip: {
				trigger: 'item',
				formatter: "{a} <br/>{b} : {c} ({d}%)"
			},
			calculable: true,
			legend: {
				x: 'center',
				y: 'bottom',
				data: []
			},
			toolbox: {
				show: true,
				feature: {
					magicType: {
						show: true,
						type: ['pie', 'funnel'],
						option: {
							funnel: {
								x: '25%',
								width: '50%',
								funnelAlign: 'center',
								max: 1548
							}
						}
					},
					restore: {
						show: true,
						title: "Restore"
					},
					saveAsImage: {
						show: true,
						title: "Save Image"
					}
				}
			},
			series: [{
				name: 'Issued Licenses',
				type: 'pie',
				center: ['50%', '50%'],
				radius: ['35%', '55%'],
				sort: 'ascending',
				itemStyle: {
					normal: {
						label: {
							show: true
						},
						labelLine: {
							show: true
						}
					},
					emphasis: {
						label: {
							show: true,
							position: 'center',
							textStyle: {
								fontSize: '14',
								fontWeight: 'normal'
							}
						}
					}
				},
				data: []
			}]
		});
	}

	function InitBarHorizontalChart(ctrl) {
		ctrl.setOption({
			title: {
				text: '',
				subtext: ''
			},
			tooltip: {
				trigger: 'axis',
				formatter: "{b}: {c}"
			},
			grid: {
				x: '80',
			},
			legend: {
				x: 100,
				data: []
			},
			toolbox: {
				show: true,
				feature: {
					saveAsImage: {
						show: true,
						title: "Save Image"
					}
				}
			},
			calculable: true,
			xAxis: [{
				type: 'value',
				boundaryGap: [0, 0.01]
			}],
			yAxis: [{
				type: 'category',
				data: ['']
			}],
			series: [{
				name: '',
				type: 'bar',
				data: []
			}]
		});
	}
	
	function GetChartStyle() {
		let tmp = {
			color: ["#26B99A", "#34495E", "#BDC3C7", "#3498DB", "#9B59B6", "#8abb6f", "#759c6a", "#bfd3b7"],
			title: {
				itemGap: 8,
				textStyle: {
					fontWeight: "normal",
					color: "#408829"
				}
			},
			dataRange: {
				min: 0,
				max: 0,
				text: ["High", "Low"],
				realtime: !0,
				calculable: !0,
				color: ["#087E65", "#26B99A", "#CBEAE3"]
			},
			toolbox: {},
			tooltip: {
				backgroundColor: "rgba(0,0,0,0.5)",
				axisPointer: {
					type: "line",
					lineStyle: {
						color: "#408829",
						type: "dashed"
					},
					crossStyle: {
						color: "#408829"
					},
					shadowStyle: {
						color: "rgba(200,200,200,0.3)"
					}
				}
			},
			dataZoom: {
				dataBackgroundColor: "#eee",
				fillerColor: "rgba(64,136,41,0.2)",
				handleColor: "#408829"
			},
			grid: {
				borderWidth: 0
			},
			categoryAxis: {
				axisLine: {
					lineStyle: {
						color: "#408829"
					}
				},
				splitLine: {
					lineStyle: {
						color: ["#eee"]
					}
				}
			},
			valueAxis: {
				axisLine: {
					lineStyle: {
						color: "#408829"
					}
				},
				splitArea: {
					show: !0,
					areaStyle: {
						color: ["rgba(250,250,250,0.1)", "rgba(200,200,200,0.1)"]
					}
				},
				splitLine: {
					lineStyle: {
						color: ["#eee"]
					}
				}
			},
			timeline: {
				lineStyle: {
					color: "#408829"
				},
				controlStyle: {
					normal: {
						color: "#408829"
					},
					emphasis: {
						color: "#408829"
					}
				}
			},
			k: {
				itemStyle: {
					normal: {
						color: "#68a54a",
						color0: "#a9cba2",
						lineStyle: {
							width: 1,
							color: "#408829",
							color0: "#86b379"
						}
					}
				}
			},
			map: {
				itemStyle: {
					normal: {
						areaStyle: {
							color: "#ddd"
						},
						label: {
							textStyle: {
								color: "#c12e34"
							}
						}
					},
					emphasis: {
						areaStyle: {
							color: "#99d2dd"
						},
						label: {
							textStyle: {
								color: "#c12e34"
							}
						}
					}
				}
			},
			force: {
				itemStyle: {
					normal: {
						linkStyle: {
							strokeColor: "#408829"
						}
					}
				}
			},
			chord: {
				padding: 4,
				itemStyle: {
					normal: {
						lineStyle: {
							width: 1,
							color: "rgba(128, 128, 128, 0.5)"
						},
						chordStyle: {
							lineStyle: {
								width: 1,
								color: "rgba(128, 128, 128, 0.5)"
							}
						}
					},
					emphasis: {
						lineStyle: {
							width: 1,
							color: "rgba(128, 128, 128, 0.5)"
						},
						chordStyle: {
							lineStyle: {
								width: 1,
								color: "rgba(128, 128, 128, 0.5)"
							}
						}
					}
				}
			},
			gauge: {
				startAngle: 225,
				endAngle: -45,
				axisLine: {
					show: !0,
					lineStyle: {
						color: [[.2, "#86b379"], [.8, "#68a54a"], [1, "#408829"]],
						width: 8
					}
				},
				axisTick: {
					splitNumber: 10,
					length: 12,
					lineStyle: {
						color: "auto"
					}
				},
				axisLabel: {
					textStyle: {
						color: "auto"
					}
				},
				splitLine: {
					length: 18,
					lineStyle: {
						color: "auto"
					}
				},
				pointer: {
					length: "90%",
					color: "auto"
				},
				title: {
					textStyle: {
						color: "#333"
					}
				},
				detail: {
					textStyle: {
						color: "auto"
					}
				}
			},
			textStyle: {
				color: "#000000",
				fontFamily: "Arial, Verdana, sans-serif"
			}
		}
		return tmp;
	}
	
	function GetLicenseHistory()
	{
		InitDonutChart(echart_IssuedLicenses);
		echart_IssuedLicenses.setOption(JSON.parse(fr_lic_stats.eChart));
		MakeTable(JSON.parse(fr_lic_stats.jsonTable), 'IssuedLicenses');
	}
	
	function GetFluxPlayerDevices()
	{
		InitBarHorizontalChart(echart_FluxPlayerDevices);
		echart_FluxPlayerDevices.setOption(JSON.parse(fr_players_stats.eChart));
		MakeTable(JSON.parse(fr_players_stats.jsonTable), 'FluxPlayerDevices');
	}

	function GetFluxPlayerContentUsage()
	{
		InitBarHorizontalChart(echart_FluxPlayerContentUsage);
		echart_FluxPlayerContentUsage.setOption({
			tooltip: {
				trigger: 'axis',
				formatter: function (params) { return params[0].name + ': ' + params[0].value.toHHMMSS() }
			},
			grid: {
				x: '10',
			},
			yAxis: [{
				type: 'category',
				show: false
			}],
			series: [{
				itemStyle: {
					normal: {
						label: {
							show: true,
							position: 'insideLeft',
							formatter: '{b}'
						}
					}
				}
			}]
		});
		echart_FluxPlayerContentUsage.setOption(JSON.parse(fr_content_stats.eChart));
		MakeTable(JSON.parse(fr_content_stats.jsonTable), 'FluxPlayerContentUsage');
	}

	function GetLicensePurchases() {
        InitDonutChart(echart_LicensePurchases);
        echart_LicensePurchases.setOption(JSON.parse(fr_license_stats.eChart));
        MakeTable(JSON.parse(fr_license_stats.jsonTable), 'LicensePurchases');
    }
			
});
