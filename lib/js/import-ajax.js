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


	/*
	$( '.btn-import-org' ).click(function(e){
		var orgid = $(this).attr('pmd1id');
		console.log( '[DM] pmd1id is ' + orgid );

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
		e.preventDefault();
	});
	/**/
});