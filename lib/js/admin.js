/* Admin JavaScript */
jQuery(document).ready(function($){
	if( true == wpvars.debug ){
		console.log( '[DM] admin.js is loaded.' );
	}

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
});