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
});