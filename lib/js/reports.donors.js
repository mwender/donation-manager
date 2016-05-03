/* JS for Donations > Donation Reports > Donors */
console.log('[DM] reports.donors.js loaded.');

jQuery(document).ready(function($){
	$('#form-donor-report').submit(function(e){
		e.preventDefault();
		console.log('[DM] #form-donor-report submitted.');
		var zipcode = $('#query-zip').val();
		console.log('[DM] zipcode = ' + zipcode );
		var data = {
			'action': 'donation-report',
			'context': 'donors',
			'switch': 'query_zip',
			'zipcode': zipcode
		}
		$.post( ajax_object.ajax_url, data, function(response){
			console.log(response)
			//$('#response-body').html(response.body);
			// START Donors by zip table
			var table = $('#table-donor-report').DataTable({
				fixedHeader: true,
				processing: true,
				serverSide: true,
				dom: 'l<"date_range"><"donation_priority">rtip',
				ajax: {
					url: ajax_object.ajax_url,
					type: 'POST',
					data: function( d ){
						d.action = 'donation-report';
						d.context = 'donors';
						d.switch = 'query_zip';
						d.zipcode = zipcode;
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
				]
			});
			// END Donors by zip table
		});
	});
});