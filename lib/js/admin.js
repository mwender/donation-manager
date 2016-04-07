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

	// Logic for Export CSV buttons for individual orgs
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

	// Logic for `Load Report` button
	$('#load-report').click(function(e){
		getOrgs();
		e.preventDefault();
	});

	// Export all donations
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

	// refresh table when `Month` select changes
	$(document).on('change', '#report-month', function(){
		var month = $(this).val();
		getOrgs(month);
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

var getOrgs = function(month){
	jQuery('#donation-display tr.org').remove();
	jQuery('#donation-display tbody').html('<tr class="loading"><td colspan="5"><div class="progress"><span class="bar"></span><span class="percent"></span></div><div class="remaining"></div></td></tr>');
	jQuery('#heading-date').html('');

	var data = {
		'action': 'donation-report',
		'switch': 'get_orgs'
	};
	jQuery.post( ajax_object.ajax_url, data, function(response){
		console.log(response.orgs);
	})
	.done(function(response){
		reportRows = {};
		orgs = response.orgs;
		console.log('orgs.length = ' + orgs.length );
		if (typeof month == 'undefined')
			month = '';
		orgReportLoop( 0, orgs, month );
	})
	.fail();
}

function orgReportLoop ( x, orgs, month ){
	console.log('x = ' +  x + '; month = ' + month );
	setTimeout(function(){
		getOrgReport(orgs[x],month,x);
		x++;
		if( x < orgs.length ){
			orgReportLoop( x, orgs, month );
		}
	}, loopDelay );
}

var getOrgReport = function(id,month,x){

	var data = {
		'action': 'donation-report',
		'switch': 'get_org_report',
		'id': id,
		'month': month
	};

	jQuery.post(ajax_object.ajax_url, data, function(response){
		console.log( 'getOrgReport('+id+','+month+','+x+');');
	})
	.done(function(response){
		org = response.org;
		reportRows[x] = '<tr class="org"><td>' + (x+1) + '</td><td>' + org.ID + '</td><td>' + org.title + '</td><td style="text-align: right;">' + org.count + '</td><td>' + org.button + '</td></tr>';
		printOrgReport(response);
	})
	.fail(function(response){
		reportRows[x] = '<tr class="org"><td>' + (x+1) + '</td><td>' + id + '</td><td>ERROR: No data returned for Org ID ' + id + '.</td><td colspan="2"></td></tr>';
		printOrgReport(response);
	});
}

var printOrgReport = function(response){
	reportRowsSize = calulateSize.size(reportRows);
	console.log('reportRows.size = ' + reportRowsSize + '; orgs.length = ' + orgs.length);
	percent = Math.floor((reportRowsSize/orgs.length) * 100);
	jQuery('#donation-display tbody tr.loading .progress span.bar').css('width', percent + '%');
	jQuery('#donation-display tbody tr.loading .progress span.percent').html(percent + '%');

	remaining = ((orgs.length - reportRowsSize) * loopDelay/1000);
	note = '';
	if( 100 <= remaining ){
		note = '<br /><em>This will take a while. Feel free to go grab some coffee.</em>';
	} else if ( 70 <= remaining ){
		note = '<br /><em>We\'re getting close...</em>';
	} else if( 35 <= remaining ){
		note = '<br /><em>Not much longer. The end is in sight!</em>';
	} else if( 16 <= remaining ){
		note = '';
	} else if( 12 <= remaining ){
		note = '<br /><em>Alright, let\'s count this down...</em>';
	} else if( 11 <= remaining ){
		note = '';
	} else if(  10 <= remaining ){
		note = '<br /><em>TEN...</em>';
	} else if(  9 <= remaining ){
		note = '<br /><em>NINE...</em>';
	} else if(  8 <= remaining ){
		note = '<br /><em>EIGHT...</em>';
	} else if(  7 <= remaining ){
		note = '<br /><em>SEVEN...</em>';
	} else if(  6 <= remaining ){
		note = '<br /><em>SIX...</em>';
	} else if(  5 <= remaining ){
		note = '<br /><em>FIVE...</em>';
	} else if(  4 <= remaining ){
		note = '<br /><em>FOUR...</em>';
	} else if(  3 <= remaining ){
		note = '<br /><em>THREE...</em>';
	} else if(  2 <= remaining ){
		note = '<br /><em>TWO...</em>';
	} else if(  1 <= remaining ){
		note = '<br /><em>ONE...</em>';
	}
	jQuery('#donation-display tbody tr.loading .remaining').html( remaining.toFixed(2) + ' seconds remaining...' + note );

	jQuery('#donation-display tbody tr.loading h3').html('Loading... (' + percent + '%)');
	if( reportRowsSize === orgs.length ){
		jQuery('#heading-date').html(response.columnHeading);
		jQuery('#donation-display tr.loading').remove();
		for(y = 0; y < reportRowsSize; y++ ){
			jQuery('#donation-display tr:last').after( reportRows[y] );
		}
		jQuery('#donation-display').each(function(){jQuery(this).find('tr:odd').css('background-color','#ededed')});
	}
}