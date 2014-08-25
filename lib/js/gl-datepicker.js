$(window).load( function() {
  $('.date').glDatePicker({
      cssName: 'pmd',
      allowMonthSelect: false,
      allowYearSelect: false,
      calendarOffset: {x:0,y:0},
      selectableDOW: vars.pickup_dow,
      selectableDateRange: [
        { from: new Date( vars.minPickUp0,vars.minPickUp1,vars.minPickUp2 ),
            to: new Date( vars.maxPickUp0,vars.maxPickUp1,vars.maxPickUp2 ) }
      ],
      onClick: function( target, cell, date, data ) {
        DateString = ('0' + (date.getMonth()+1)).slice(-2) + '/'
             + ('0' + date.getDate()).slice(-2) + '/'
             + date.getFullYear();
        target.val( DateString );
      }
  }).prop( 'readonly', true );
});