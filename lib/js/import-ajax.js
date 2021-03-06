jQuery(document).ready(function($){
	console.log( '[DM] import-ajax.php is loaded.' );

	// Import Orgs Button
	$('#btn-import-orgs').click(function(e){
		var orgs = [];
		$('input[name="org_id[]"]').each(function(){
			orgs.push( $(this).val() )
		});
		console.log('[DM] orgs = ');
		console.log( orgs );
		$.each( orgs, function( index, value ){
			import_organization( value );
		});
		e.preventDefault();
	});

	function import_organization(orgid){
		var data = {
			'action': 'import_org',
			'orgid': orgid
		};
		jQuery.post(ajax_object.ajax_url, data, function(response){
			pmd1id = response.pmd1id;
			pmd2id = response.pmd2id;
			bkgrdclr = $('tr#pmd1id_' + pmd1id + ' td.pmd2id').css('background-color');
			$('tr#pmd1id_' + pmd1id + ' td').animate({
				backgroundColor: '#a6d7a2'
			}, 300 );

			$('tr#pmd1id_' + pmd1id + ' td.pmd2id' ).html( pmd2id );
			$('tr#pmd1id_' + pmd1id + ' td').animate({
				backgroundColor: bkgrdclr
			}, 2000 );
			console.log( response.message );
		});
	}

	// Import Trans Depts Button
	$('#btn-import-transdepts').click(function(e){
		var transdepts = [];
		$('input[name="td_id[]"]').each(function(){
			transdepts.push( $(this).val() )
		});

		$.each( transdepts, function( index, value ){
			import_transdept( value );
		});
		e.preventDefault();
	});

	function import_transdept(transdeptID){
		var data = {
			'action': 'import_transdept',
			'transdeptID': transdeptID
		};
		//console.log( '[DM] Attempting to import trans dept ID ' + transdeptID );
		//*
		jQuery.post( ajax_object.ajax_url, data, function(response){

			pmd1ID = response.pmd1ID;
			pmd2ID = response.pmd2ID;
			bkgrdclr = $('tr#pmd1_tid_' + pmd1ID + ' td.pmd2_tid').css('background-color');
			$('tr#pmd1_tid_' + pmd1ID + ' td').animate({
				backgroundColor: '#a6d7a2'
			}, 300 );

			$('tr#pmd1_tid_' + pmd1ID + ' td.pmd2_tid' ).html( pmd2ID );
			$('tr#pmd1_tid_' + pmd1ID + ' td').animate({
				backgroundColor: bkgrdclr
			}, 2000 );
			if( typeof response.message === 'undefined' ){
				console.log( response );
			} else {
				console.log( response.message );
			}
		});
		/**/
	}

	// Import Trans Dept Pickup Codes
	$('#btn-import-transdepts-pickupcodes').click(function(e){
		var transdepts = [];
		$('input[name="td_id[]"]').each(function(){
			transdepts.push( $(this).val() )
		});

		$.each( transdepts, function( index, value ){
			import_transdept_pickupcodes( value );
		});
		e.preventDefault();
	});

	function import_transdept_pickupcodes(transdeptID){
		var data = {
			'action': 'import_transdept_pickupcodes',
			'transdeptID': transdeptID
		};
		$.post( ajax_object.ajax_url, data, function(response){
			pmd1ID = response.pmd1ID;
			bkgrdclr = $('tr#pmd1_tid_' + pmd1ID + ' td.pmd2_tid').css('background-color');
			$('tr#pmd1_tid_' + pmd1ID + ' td').animate({
				backgroundColor: '#a6d7a2'
			}, 300 );

			$('tr#pmd1_tid_' + pmd1ID + ' td').animate({
				backgroundColor: bkgrdclr
			}, 2000 );
			if( typeof response.message === 'undefined' ){
				console.log( response );
			} else {
				console.log( response.message );
				console.log( response.codes );
			}
		});
	}

	// Import Stores Button
	$('#btn-import-stores').click(function(e){
		var stores = [];
		$('input[name="store_id[]"]').each(function(){
			stores.push( $(this).val() )
		});

		$.each( stores, function( index, value ){
			import_store( value );
		});
		e.preventDefault();
	});

	function import_store(storeID){
		var data = {
			'action': 'import_store',
			'storeID': storeID
		};
		//console.log( '[DM] Attempting to import store ID ' + storeID );
		//*
		jQuery.post( ajax_object.ajax_url, data, function(response){

			pmd1ID = response.pmd1ID;
			pmd2ID = response.pmd2ID;
			bkgrdclr = $('tr#pmd1_storeid_' + pmd1ID + ' td.pmd2_storeid').css('background-color');
			$('tr#pmd1_storeid_' + pmd1ID + ' td').animate({
				backgroundColor: '#a6d7a2'
			}, 300 );

			$('tr#pmd1_storeid_' + pmd1ID + ' td.pmd2_storeid' ).html( pmd2ID );
			$('tr#pmd1_storeid_' + pmd1ID + ' td').animate({
				backgroundColor: bkgrdclr
			}, 2000 );
			if( typeof response.message === 'undefined' ){
				console.log( response );
			} else {
				console.log( response.message );
			}
		});
		/**/
	}

	// Import Pickup Codes Button
	$('#btn-import-pickupcodes').click(function(e){
		var pickup_codes = [];
		$('input[name="pickupcode_id[]"]').each(function(){
			pickup_codes.push( $(this).val() )
		});
		console.log( '[DM] Importing ' + pickup_codes.length + ' pickup codes.' );

		$.each( pickup_codes, function( index, value ){
			import_pickupcode( value );
		});
		e.preventDefault();
	});

	function import_pickupcode(pickupcodeID){
		var data = {
			'action': 'import_pickupcode',
			'pickupcodeID': pickupcodeID
		};
		//console.log( '[DM] Attempting to import pickupcode ID ' + pickupcodeID );
		//*
		jQuery.post( ajax_object.ajax_url, data, function(response){

			pmd1ID = response.pmd1ID;
			bkgrdclr = '#fff';
			$( '#pmd1_pickupcodeid_' + pmd1ID ).animate({
				backgroundColor: '#a6d7a2'
			}, 300 );

			$( '#pmd1_pickupcodeid_' + pmd1ID ).animate({
				backgroundColor: bkgrdclr
			}, 2000 );
			if( typeof response.message === 'undefined' ){
				console.log( response );
			} else {
				console.log( response.message );
			}
		});
		/**/
	}

	// Import Donations
	var start_id = $('#start_id').val();
	var total_donations = $('#total_donations').val();
	var counter = 0;
	var progress = 0;

	$('#btn-import-donations').click(function(e){
		import_donation( start_id );
		e.preventDefault();
	});

	function import_donation( id ){
		var data = {
			'action': 'import_donation',
			'id': id
		};
		$.post( ajax_object.ajax_url, data, function(response){
			counter++;
			progress = (counter/total_donations);
			progress = Math.round( progress * 100 );
			$('.progress-bar').attr( 'aria-valuenow', progress ).html( progress + '%' ).css( 'width', progress + '%' );
			$('#import-status').html( 'Importing <strong>' + counter + '</strong> of ' + total_donations + '.' );

			if( false != response.next_id ){
				console.log( response.message + '; response.next_id = ' + response.next_id + '; response.legacy_id = ' + response.legacy_id );
				if( typeof response.next_id != 'undefined' )
					import_donation( response.next_id );
			} else {
				console.log( '[DM] Finished importing donations!' );
			}
		});
	}

	// Delete Donations button
	var deleted = 0;
	$('#btn-delete-donations').click(function(e){
		var r = confirm('Are you sure you want to delete all PMD2.0 donations?');
		if( true == r ){
			delete_donations();
		}
	});

	function delete_donations(){
		var data = {
			'action': 'delete_donations'
		};
		$.post( ajax_object.ajax_url, data, function(response){
			if( 0 < response.donation_count ){
				deleted = response.numberposts + deleted;
				$('#delete-donations-status').html( '<strong>' + deleted + '</strong> donations of <strong>' + response.donation_count + '</strong> deleted.' );
				console.log( response.message );
				delete_donations();
			} else {
				$('#delete-donations-status').html( 'All donations deleted. Finished deleting.' );
			}
		});
	}
});