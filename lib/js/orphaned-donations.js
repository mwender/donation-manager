jQuery( function($){

	// Media upload handler
	$('#upload_csv_button').click(function() {
		formfield = $('#upload_csv').attr('name');
		tb_show('Upload a CSV', 'media-upload.php?type=file&amp;TB_iframe=true');
		return false;
	});

	window.send_to_editor = function(html) {
		loadCSVList();
		tb_remove();
	}

	// Output dismiss/close button
	$('body').on('click','.output-close a',function(e){
		e.preventDefault();
		var outputDiv = $(this).parents('div.output');
		console.log(outputDiv);
		$(outputDiv).html('');
	});

	// Orphaned Testing
	$('#form-test-pcode').submit(function(e){
		e.preventDefault();
		testPcode();
	});

	// Add a Contact
	$('#form-add-orphaned-contact').submit(function(e){
		e.preventDefault();
		addContact();
	});

	// Search/Replace
	$('#form-search-replace-email').submit(function(e){
		e.preventDefault();
		searchReplace();
	});

	// Unsubscribe emails
	$('#form-unsubscribe').submit(function(e){
		e.preventDefault();
		unsubscribeEmail();
	});

	loadCSVList();

	var dismissButton = '<div class="output-close">[<a href="#" class="">Dismiss</a>]</div>';

	function addContact(){
		var email_address = $('#contact-email').val();
		var zipcode = $('#contact-zipcode').val();
		var store_name = $('#contact-store-name').val();
		var priority = $('#contact-priority').val();

		var data = {
			'action': 'orphaned_utilities_ajax',
			'cb_action': 'add_contact',
			'email_address': email_address,
			'zipcode': zipcode,
			'store_name': store_name,
			'priority': priority
		}
		$.post( wpvars.ajax_url, data, function(response){
			$('#output-add-contact').html(dismissButton + response.output);
		});
	}

	/**
	 * Updates #search-email with #replace-email in contacts table
	 *
	 * @method     searchReplace
	 */
	function searchReplace(){
		var search_email = $('#search-email').val();
		var replace_email = $('#replace-email').val();

		var data = {
			'action': 'orphaned_utilities_ajax',
			'cb_action': 'search_replace_email',
			'search': search_email,
			'replace': replace_email
		};
		$.post( wpvars.ajax_url, data, function(response){
			$('#output-search-replace').html(dismissButton + response.output);
		});
	}

	/**
	 * Returns the contacts for a pickup code entered in form#form-test-pcode
	 *
	 * @method     testPcode
	 */
	function testPcode(){
		var pcode = $('#test-pcode').val();
		var radius = $('#test-radius').val();
		console.log('[DM] pcode = ' + pcode );

		var data = {
			'action': 'orphaned_utilities_ajax',
			'pcode': pcode,
			'radius': radius
		};
		$.post( wpvars.ajax_url, data, function(response){
			$('#output-test-pcode').html(dismissButton + response.output);
		});
	}

	/**
	 * Unsubscribes an email
	 *
	 * @method     unsubscribeEmail
	 */
	function unsubscribeEmail(){
		var email = $('#unsubscribe-email').val();

		var data = {
			'action': 'orphaned_utilities_ajax',
			'cb_action': 'unsubscribe_email',
			'email': email
		};
		$.post( wpvars.ajax_url, data, function(response){
			$('#output-unsubscribe').html(dismissButton + response.output);
		});
	}

	// BEGIN Datatables
	var table = $('#orphaned-donations').DataTable({
		fixedHeader: true,
		processing: true,
		serverSide: true,
		dom: 'l<"date_range"><"donation_priority">frtip',
		ajax: {
			url: wpvars.ajax_url,
			type: 'POST',
			data: function( d ){
				d.action = 'query_orphaned_donations';
				d.month = $('#month').val();
				d.priority = $('#priority').val();
			}
		},
		order: [[ 6, 'desc' ]],
		lengthMenu: [[25,50,100], [25,50,100]],
		columnDefs: [
			{ name: 'id', data: 'id', className: 'dt-body-right', orderable: false, targets: 0 },
			{ name: 'store_name', data: 'store_name', orderable: false, targets: 1 },
			{ name: 'zipcode', data: 'zipcode', className: 'dt-body-right', orderable: false, targets: 2 },
			{ name: 'website', data: 'website', orderable: false, targets: 3 },
			{ name: 'email_address', data: 'email_address', orderable: false, targets: 4 },
			{ name: 'receive_emails', data: 'receive_emails', className: 'dt-body-center', orderable: false, targets: 5 },
			{ name: 'total_donations', data: 'total_donations', className: 'dt-body-right', type: 'num', targets: 6 }
		]
	});

	$('div.date_range').html('Range: <select name="month" id="month">' + wpvars.month_options + '</select>');
	$('div.donation_priority').html('Priority: <select name="priority" id="priority"><option value="all">All</option><option value="nonprofit">Non-Profit</option><option value="priority">Priority</option></select>');

	// Redraw the table after a change with the #month select
	$('#month').on( 'change', function(){
		table.draw();
	});

	// Redraw the table after a change with the #priority select
	$('#priority').on( 'change', function(){
		table.draw();
	});

    // Returned AJAX object
    table.on( 'xhr.dt', function(e,settings,json){
        console.log( '[DT] Ajax event occured.' );
        //console.log(json.stores_sql);
    });
	// END Datatables
});

/**
* deleteCSV() - deletes a CSV from the media library
*/
function deleteCSV(id,title){
	var bkgrd_color = jQuery('tr#row' + id).css('background-color');
	jQuery('tr#row' + id).css('background-color','rgb(245,202,202)');
	var answer = confirm('You are about to delete ' + title + '. Do you want to continue?');
	if(answer){
		var data = {
			'action': 'orphaned_donations_ajax',
			'cb_action': 'delete_csv',
			'csvID': id
		};

		jQuery.post( wpvars.ajax_url, data, function(response){
			var data = response.data;
			if( data['deleted'] == true ){
				loadCSVList();
			}
		});
	} else {
		jQuery('tr#row' + id).css('background-color',bkgrd_color);
	}
	return false;
}

// Initialize progress variables
var counter = 0;
var progress = 0;

/**
* Imports a slice of a CSV according to the specified offset
*/
function importCSV( id, offset ){

	// We've clicked on the CSV file's title and loaded a
	// preview. So, we'll hide the preview once we start
	// the import.
	if( jQuery('#run-import:contains("start-import")') ){
		jQuery('#csvimport').slideUp();
		jQuery('#import-table').fadeOut(); // remove the CSV preview
		jQuery('#run-import').html( '' ); // clear the "Start Import" button
		jQuery('#progress-bar').fadeIn(); // show the progress bar container
		jQuery('#import-percent').fadeIn();
	} else {
		jQuery('#import-table').fadeOut();
		jQuery('#progress-bar').fadeIn();
	}
	var importProgressBar = jQuery('#progress-bar').progressbar();
	jQuery('#csv_list').fadeOut();
	jQuery('#upload-csv').fadeOut();

	console.log( '[ImportCSV] Running importCSV(' + id + ',' + offset + ')' );

	var data = {
		'action': 'orphaned_donations_ajax',
		'cb_action': 'import_csv',
		'csvID': id,
		'csvoffset': offset
	};

	var jqXHR = jQuery.post( wpvars.ajax_url, data, function(response){

		var rows = response.csv['rows'];
		var html = '';

		if(response.selected_rows > 0){
			jQuery('#import-stats').html('(' + response.current_offset + ' out of ' + response.csv['row_count'] + ')');

			counter = counter + 100;
			progress = (counter/response.csv['row_count']);
			progress = Math.round( progress * 100 );
			if( 100 <= progress ){
				progress = 100;
			}
			importProgressBar.progressbar('value',progress);
			jQuery('#import-percent').html( progress + '%' );
			importCSV( response.id, response.offset );
		} else {
			jQuery('#import-percent').html('Import complete!<br /><em>Refreshing page. One moment...</em>');
			jQuery('#import-notice').fadeOut();
			window.setTimeout(function(){
				jQuery('#progress-bar').fadeOut();
				jQuery('#import-percent').fadeOut();
				document.location.reload();
			},3000);
		}
	}).fail( function( errorObj, status, error ){
		console.log( '[ImportCSV] ERROR: importCSV() ' + status + ' (' + error + ').' );
		console.log( errorObj );
		if( 'timeout' == error ) {
			console.log( '[ImportCSV] Retrying in 5 seconds...' );
			setTimeout( importCSV( id, offset ), 5000 );
		}
	});
}

function loadCSVList(){
	var data = {
		'action': 'orphaned_donations_ajax',
		'cb_action': 'get_csv_list'
	};

	jQuery.post( wpvars.ajax_url, data, function(response){
		jQuery('#csv_list tbody').empty();

		var csvs = response.data['csv'];

		if( jQuery.isArray( csvs ) ){
			var row = '';
			for(var i = 0; i < csvs.length; i++){
				var cssclass = '';
				if(i % 2){
					cssclass = ' class="alternate"';
				}

				var row = row + '<tr' + cssclass + ' id="row' + csvs[i].id + '"><td><strong><a class="load-csv" onclick="loadCSV(' + csvs[i].id + '); return false;" title="Preview the CSV data for '+ csvs[i].filename +'" href="#">'+ csvs[i].post_title + '</a></strong><br />'+ csvs[i].filename +'<br />'+ csvs[i].timestamp +' | <a class="load-csv" onclick="deleteCSV(' + csvs[i].id + ',\'' + csvs[i].post_title + '\'); return false;" href="#">Delete</a></td><td style="text-align: right;"><a class="button" href="#" onclick="loadCSV(' + csvs[i].id + '); return false;">Import</a></td></tr>';

			}
			jQuery(row).appendTo(jQuery('#csv_list tbody'));
		} else {
			var row = '<tr><td colspan="5" style="text-align: center;">No CSVs found. Upload one via the dialog below.</td></tr>';
			jQuery(row).appendTo(jQuery('#csv_list tbody'));
		}
	});
}

/**
* Loads a CSV in preparation for running importCSV
*/
function loadCSV(id){
	var data = {
		'action': 'orphaned_donations_ajax',
		'cb_action': 'load_csv',
		'csvID': id
	};
	jQuery('#import-table').fadeIn();

	jQuery.post( wpvars.ajax_url, data, function(response){

		jQuery('#csvimport thead tr').empty();
		jQuery('#csvimport tbody').empty();
		jQuery('#import-progress').fadeIn();

		var columns = response.csv['csv']['columns'];
		var headings = '';
		for(var i = 0; i < columns.length; i++){
			headings = headings + '<th scope="col" class="manage-column">' + columns[i] + '</th>' + "\n";
		}
		jQuery(headings).appendTo(jQuery('#csvimport thead tr'));

		var rows = response.csv['csv']['rows'];
		var html = '';
		var cols = 5;
		if(rows.length < cols){
			var counter = rows.length;
		} else {
			var counter = cols;
		}
		for(var i = 0; i < counter; i++){
			var row = rows[i];
			var cssclass = '';
			if(i % 2){
				cssclass = ' class="alternate"';
			}
			html += "\n" + '<tr' + cssclass + '>';
			for(j = 0; j < columns.length; j++){
				if( typeof row[j] == 'undefined' ){
					html += "\n\t" + '<td>&nbsp;</td>';
				} else {
					html += "\n\t" + '<td>' + row[j].substring(0,100) + '</td>';
				}
			}
			html += "\n" + '</tr>';
		}
		jQuery(response.notice).appendTo('div.wrap h2:first');
		jQuery(html).appendTo(jQuery('#csvimport tbody'));
		jQuery('#csv-name').html(response.csv['title']);
		jQuery('#import-progress h2').html('Importing <em>' + response.csv['title'] + '</em>');
		jQuery('#stats').html('(Showing <span class="count">' + counter + '</span> rows/<span class="count">' + response.csv['csv']['row_count'] + '</span> total rows)');
		jQuery('#run-import').html('<div id="start-import" style="text-align: center"><a href="#" class="button" onclick="importCSV(' + response.csv['id'] + ', ' + response.csv['offset'] + '); return false;">Click here to import the file previewed below:</a></div>');
		jQuery('#csvimport').fadeIn('slow');
	});
}

// Return a number as a percent
function percent(number, whole, inverse, rounder){
	whole = parseFloat(whole);
	if( !whole ){ whole = 100; };
	number = parseFloat( number );
	if( !number ){ number = 0; };
	if( !whole || !number ){ return 0; };
	rounder = parseFloat( rounder );
	rounder = ( rounder && ( !( rounder%10 ) || rounder == 1 ) ) ? rounder:100;
	return (!inverse)? Math.round( ((number*100)/whole) *rounder)/rounder: Math.round( ((whole*number)/100) *rounder)/rounder;
}

// Overwrite Thickbox.tb_remove()
window.tb_remove = function() {
	jQuery("#TB_imageOff").unbind("click");
	jQuery("#TB_closeWindowButton").unbind("click");
	jQuery("#TB_window").fadeOut("fast",function(){jQuery('#TB_window,#TB_overlay,#TB_HideSelect').trigger("unload").unbind().remove();});
	jQuery("#TB_load").remove();
	if (typeof document.body.style.maxHeight == "undefined") {//if IE 6
		jQuery("body","html").css({height: "auto", width: "auto"});
		jQuery("html").css("overflow","");
	}
	window.loadCSVList();
	document.onkeydown = "";
	document.onkeyup = "";
	return false;
}