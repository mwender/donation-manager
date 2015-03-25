$(window).load( function() {
  $('#additional-details').addClass('hidden');

  $('button[type="submit"]').click(function(e){
    $(this).attr( 'disabled' );
    $(this).addClass( 'disabled' );
  });

  var provide_additional_details = $('form[name="screening-questions"] input[name="provide_additional_details"]');
  pad_value = provide_additional_details.val() == '1'; // get the boolean value

  if( true === pad_value ){
  	var questions = $('form[name="screening-questions"] input:checked');

  	for( x = 0; x < questions.length; x++ ){
  		if( 'yes' === questions[x].value.toLowerCase() )
  			$('#additional-details').removeClass('hidden');
  	}

	  $('form[name="screening-questions"] input:radio').change(function(){
	  	var showAdditionalDetails = false;

	  	var questions = $('form[name="screening-questions"] input:checked');

	  	for( x = 0; x < questions.length; x++ ){
	  		if( 'yes' === questions[x].value.toLowerCase() )
	  			showAdditionalDetails = true;
	  	}

	  	if( true === showAdditionalDetails ){
	  		$('#additional-details').removeClass('hidden');
	  	} else {
	  		$('#additional-details').addClass('hidden');
	  	}
	  });
  }
});