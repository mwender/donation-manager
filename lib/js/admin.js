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
			failMessageHtml: 'There was a problem generating your CSV. To fix this problem, simply goto <a href="' + ajax_object.permalink_url + '">SETTINGS &gt; PERMALINKS</a> (<em>IMPORTANT: Do not change any settings or click any buttons, just visit that page</em>). Then return to this page and attempt your download again.'
		});

		e.preventDefault();
	});

	$('#export-all-donations').click(function(e){
		console.log('[DM] Exporting all donations.');

		$('#donation-download-progress').html( '0%' );
		$('#donation-download-overlay').fadeIn();
		$('#donation-download-modal').dialog({
			dialogClass: 'no-close',
			height: 220
		});

		var data = {
			'action': 'donation-report',
			'switch': 'create_file'
		};
		$.post( ajax_object.ajax_url, data, function(response){
			console.log( response.message );

			if( 'continue' == response.status )
				buildCSV( response.attach_id );
		});

		e.preventDefault();
	});

	$('#report-month').change(function(){
		var month = $(this).val();
		console.log('[DM] Current month is ' + month );

		var m_names = new Array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
		var d = new Date( month + '-01' );
		var curr_date = d.getDate();
		var curr_month = d.getMonth();
		var curr_year = d.getFullYear();
		$('.export-csv').val( 'Export ' + m_names[curr_month+1] + ' ' + curr_year + ' CSV' );
	});

	function buildCSV( attach_id ){
		if( ! attach_id )
			return;

		var data = {
			'action': 'donation-report',
			'switch': 'build_file',
			'attach_id': attach_id
		};
		$.post( ajax_object.ajax_url, data, function(response){
			if( 'continue' == response.status ){
				console.log( 'Message: ' + response.message + "\nProgress: " + response.progress_percent + '%' );
				$('#donation-download-progress').html( response.progress_percent + '%' );
				buildCSV( response.attach_id );
			} else {
				$('#donation-download-overlay').fadeOut();
				$('#donation-download-modal').dialog('close');
				console.log( 'File has been built with an attachment ID of ' + response.attach_id + '.' );
				$.fileDownload( response.fileurl,{
					preparingMessageHtml: 'Downloading ALL DONATIONS. Please wait...',
					failMessageHtml: 'There was a problem generating your CSV. Please try again.'
				});
			}
		});
	}
});