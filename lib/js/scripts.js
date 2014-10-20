$(window).load( function() {
  $('button[type="submit"]').click(function(e){
    $(this).attr( 'disabled' );
    $(this).addClass( 'disabled' );
  });
});