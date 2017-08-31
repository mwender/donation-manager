/* JS for Donations > Donation Reports > Organizations */
console.log('[DM] reports.orgs.js loaded.');

jQuery(document).ready(function($){

	// Initial view with default DonationsByOrg report
	loadDonationsByOrg();

	// BUTTON: Export CSV for individual orgs
	$(document).on('click', '.export-csv', function(e){
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

	// BUTTON: Combined Donations > Download
	$('#export-all-donations').click(function(e){
		console.log('[DM] Exporting all donations.');

		$('#donation-download-progress').html( '0%' );
		$('#donation-download-overlay').fadeIn();
		$('#donation-download-modal').dialog({
			dialogClass: 'no-close',
			height: 220
		});

		var month = $('#all-donations-report-month').val();

		var data = {
			'action': 'donation-report',
			'context': 'organizations',
			'switch': 'create_file',
			'month': month
		};
		$.post( ajax_object.ajax_url, data, function(response){
			console.log( response.message );

			if( 'continue' == response.status )
				buildCSV( response.attach_id );
		});

		e.preventDefault();
	});

	// DROPDOWN: Donations by Organization > Month: onChange
	// - refresh table when `Month` select changes
	$(document).on('change', '#report-month', function(){
		var month = $(this).val();
		loadDonationsByOrg(month);
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
					failMessageHtml: 'There was a problem generating your CSV. To fix this problem, simply goto <a href="' + ajax_object.permalink_url + '">SETTINGS &gt; PERMALINKS</a> (<em>IMPORTANT: Do not change any settings or click any buttons, just visit that page</em>). Then return to this page and attempt your download again.'
				});
			}
		});
	}
});

var reportRows = {};
var calulateSize = {};
var loopDelay = 1000; // Amount of time between resource intensive requests (e.g. getOrgReport() ).
calulateSize.size = function(obj){
		var size = 0, key;
		for(key in obj){
			if(obj.hasOwnProperty(key)) size++;
		}
		return size;
};
var orgs = {};

/**
 * Utilizes WP REST API
 */
var loadDonationsByOrg = function(month){
	// Clear the display table
	jQuery('#donation-display tbody tr').remove();
	jQuery('#donation-display tbody').html('<tr class="loading"><td style="text-align: center; padding: 60px;" colspan="5">Loading data. One moment...</td></tr>');

	if( typeof month !== 'undefined' ){
		geturl = ajax_object.restapi_url + '=' + month;
	} else {
		geturl = ajax_object.restapi_url;
	}

	jQuery.get( geturl, { action: 'wp_rest', _wpnonce: ajax_object.nonce } )
		.done(function(data){
			jQuery('#donation-display tbody tr.loading').remove();
			jQuery('#donation-display tbody tr.org').remove();
			jQuery('#heading-date').html( data.formatted_date + ' Donations' );
			x = 0;

			orgs = data.orgs;

			orgs.forEach(function(el){
				jQuery('#donation-display tbody').append( '<tr class="org"><td>' + (x+1) + '</td><td>' + el.ID + '</td><td>' + el.title + '</td><td style="text-align: right;">' + el.count + '</td><td>' + el.button + '</td></tr>' );
				x++;
			});
			jQuery('#donation-display').each(function(){jQuery(this).find('tr:odd').css('background-color','#ededed')});

			if( typeof data.alert !== 'undefined' ){
				alert(data.alert);
			}

		})
		.fail(function(jqXHR, textStatus, errorThrown ){
			jQuery('#donation-display tbody tr').remove();
			jQuery('#donation-display tbody').html('<tr class="loading"><td style="text-align: center; padding: 60px;" colspan="5">' + jqXHR.responseJSON.message + '</td></tr>');
			console.log( jqXHR );
		});
}