/* JS for Donations > Donation Reports > Donors */
jQuery(document).ready(function($){
	var zipcode = $('#query-zip').val();
	// START Donors by zip table
	var table = $('#table-donor-report').DataTable({
		fixedHeader: true,
		processing: true,
		serverSide: true,
		dom: 'l<"download-report">f<"radius">rtip',
		ajax: {
			url: ajax_object.ajax_url,
			type: 'POST',
			data: function( d ){
				d.action = 'donation-report';
				d.context = 'donors';
				d.switch = 'query_zip';
				d.radius = $('#radius').val();
			}
		},
		order: [[ 1, 'desc' ]],
		lengthMenu: [[25,50,100], [25,50,100]],
		columnDefs: [
			{ name: 'id', data: 'id', className: 'dt-body-right', orderable: false, targets: 0 },
			{ name: 'date', data: 'date', className: 'dt-body-right', orderable: true, targets: 1 },
			{ name: 'name', data: 'name', orderable: false, targets: 2 },
			{ name: 'email_address', data: 'email_address', orderable: false, targets: 3 },
			{ name: 'zipcode', data: 'zipcode', className: 'dt-body-left', orderable: false, targets: 4 },
			{ name: 'actions', data: 'actions', className: 'dt-body-right', orderable: false, targets: 5 }
		],
		language: {
			'search': 'Zip Code:'
		}
	});
  $('div.radius')
    .html('Radius: <select name="radius" id="radius"><option value="20">20 miles</option><option value="40">40 miles</option><option value="60">60 miles</option></select>')
    .on( 'change', function(){
    	var searchStr = $( '#table-donor-report_filter input[type="search"]' ).val();
    	if( '' != searchStr )
	    	table.draw();
  });

	// Only filter/search when the search value length == 5
	table.on('init.dt', function(){
		$('.dataTables_filter input')
			.unbind()
			.bind('input',function(e){
				// If the length is 5 characters, or the user pressed ENTER, search
				if( this.value.length == 5 || e.keyCode == 13 ){
					table.search(this.value).draw();
				}
				// Clear the search if the user backspaces far enough
				if( this.value == "" ){
					table.search('').draw();
				}
				return;
			});
	});

	// END Donors by zip table

  // Returned AJAX object, add a report download button
  table.on( 'xhr.dt', function(e,settings,json){
      if( true == ajax_object.debug ){
	      console.log( '[DT] Donor by zip query returned.' );
	      console.log(json);
      }
      if( typeof json.download_csv_button != 'undefined' )
	      $('div.download-report').html( json.download_csv_button );
  });

  // Make clicking on #download-zipcode-csv initate a CSV download
  $(document.body).on('click','#download-zipcode-csv',function(e){
  	e.preventDefault();
  	var zipcode = $('#download-zipcode-csv').attr('aria-zipcode');
  	var radius = $('#download-zipcode-csv').attr('aria-radius');
  	console.log('[DM] zipcode = ' + zipcode + ', radius = ' + radius );

  	var filename = ajax_object.site_url + zipcode + '/' + radius + '/';
		$.fileDownload(filename,{
			preparingMessageHtml: 'Downloading your CSV. Please wait...',
			failMessageHtml: 'There was a problem generating your CSV. To fix this problem, simply goto <a href="' + ajax_object.permalink_url + '">SETTINGS &gt; PERMALINKS</a> (<em>IMPORTANT: Do not change any settings or click any buttons, just visit that page</em>). Then return to this page and attempt your download again.'
		});
  });

	// Search form
	$('#form-donor-report').submit(function(e){
		e.preventDefault();
		console.log('[DM] #form-donor-report submitted.');
		console.log('[DM] zipcode = ' + zipcode );

		if( $.fn.dataTable.isDataTable('#table-donor-report') ){
			console.log('Destroying old results.');
			//table = $('#table-donor-report').DataTable({});
			table.destroy();
		}

		var data = {
			'action': 'donation-report',
			'context': 'donors',
			'switch': 'query_zip',
			'zipcode': zipcode
		}
		$.post( ajax_object.ajax_url, data, function(response){
			console.log(response)
			//$('#response-body').html(response.body);
		});
	});
});