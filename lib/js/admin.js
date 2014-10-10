/* Admin JavaScript */
jQuery(document).ready(function($){
	console.log( '[DM] admin.js is loaded.' );

	// Enhanced select for Trans Depts
	$('#enhanced-trans-dept-select').change(function(){
		var tid = $(this).val();
		console.log( '[DM] Trans Dept ID is ' + tid );
		$('#pods-form-ui-pods-meta-trans-dept').val( tid );
	});
	$('#pods-form-ui-pods-meta-trans-dept').change(function(){
		var tid = $(this).val();
		$('#enhanced-trans-dept-select').val( tid );
	});

	// Enhanced select for Organizations
	$('#enhanced-organization-select').change(function(){
		var oid = $(this).val();
		console.log( '[DM] Organization ID is ' + oid );
		$('#pods-form-ui-pods-meta-organization').val( oid );
	});
	$('#pods-form-ui-pods-meta-organization').change(function(){
		var oid = $(this).val();
		$('#enhanced-organization-select').val( oid );
	});

	// Donation Reports
	$('.export-csv').click(function(e){
		var org_id = $(this).attr('aria-org-id');
		var month = $('#report-month').val();
		console.log('[DM] Exporting ' + month + ' CSV for Org ID: ' + org_id );

		var filename = ajax_object.site_url + org_id + '/' + month + '/';
		console.log( '[DM] Attempting to download ' + filename );
		$.fileDownload(filename,{
			preparingMessageHtml: 'Downloading your CSV. Please wait...',
			failMessageHtml: 'There was a problem generating your CSV. Please try again.'
		});

		/*
		var data = {
			'action': 'export-csv',
			'org_id': org_id
		};
		$.post(ajax_object.ajax_url, data, function(response){
			// If success, initiate a jQuery file download.
			var filename = ajax_object.site_url + response.filename;
			console.log( '[DM] Attempting to download ' + filename );
			$.fileDownload(filename,{
				preparingMessageHtml: 'Downloading your CSV. Please wait...',
				failMessageHtml: 'There was a problem generating your CSV. Please try again.'
			});
		});
		/**/
		e.preventDefault();
	});
});