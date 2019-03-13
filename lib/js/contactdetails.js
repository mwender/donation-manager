jQuery(document).ready(function($){
  $( '#different-pickup-address' ).hide();
  $( '[name=donor\\[different_pickup_address\\]]' ).change( function(){
    var val = $( this ).val();
    if( 'Yes' == val ){
      $( '#different-pickup-address' ).slideDown();
    } else {
      $( '#different-pickup-address' ).slideUp();
    }
  });

  $('#donor_phone').mask('(000) 000-0000');
});
