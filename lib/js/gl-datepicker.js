jQuery(document).ready(function($){
  $('.date').glDatePicker({
      cssName: 'pmd',
      allowMonthSelect: true,
      allowYearSelect: true,
      calendarOffset: {x:0,y:0},
      selectableDOW: vars.pickup_dow,
      selectableDateRange: [
        { from: new Date( vars.minPickUp0,vars.minPickUp1,vars.minPickUp2 ),
            to: new Date( vars.maxPickUp0,vars.maxPickUp1,vars.maxPickUp2 ) }
      ],
      specialDates: [
        {
          date: new Date(2014, 10, 27),
          data: { message: 'Happy Thanksgiving! Please select another date.', selectable: false },
          repeatYear: false,
          cssClass: 'special'
        },
        {
          date: new Date(2015, 10, 26),
          data: { message: 'Happy Thanksgiving! Please select another date.', selectable: false },
          repeatYear: false,
          cssClass: 'special'
        },
        {
          date: new Date(2016, 10, 24),
          data: { message: 'Happy Thanksgiving! Please select another date.', selectable: false },
          repeatYear: false,
          cssClass: 'special'
        },
        {
          date: new Date(2017, 10, 23),
          data: { message: 'Happy Thanksgiving! Please select another date.', selectable: false },
          repeatYear: false,
          cssClass: 'special'
        },
        {
          date: new Date(2018, 10, 22),
          data: { message: 'Happy Thanksgiving! Please select another date.', selectable: false },
          repeatYear: false,
          cssClass: 'special'
        },
        {
          date: new Date(2019, 10, 28),
          data: { message: 'Happy Thanksgiving! Please select another date.', selectable: false },
          repeatYear: false,
          cssClass: 'special'
        },
        {
          date: new Date(2020, 10, 26),
          data: { message: 'Happy Thanksgiving! Please select another date.', selectable: false },
          repeatYear: false,
          cssClass: 'special'
        },
        {
          date: new Date(2014, 11, 24),
          data: { message: 'Merry Christmas! Please select another date.', selectable: false },
          repeatYear: true,
          cssClass: 'special'
        },
        {
          date: new Date(2014, 11, 25),
          data: { message: 'Merry Christmas! Please select another date.', selectable: false },
          repeatYear: true,
          cssClass: 'special'
        },
        {
          date: new Date(2014, 6, 4),
          data: { message: 'Happy 4th of July! Please select another date.', selectable: false },
          repeatYear: true,
          cssClass: 'special'
        }
      ],
      onClick: function( target, cell, date, data ) {
        console.log( data );
        if( data && typeof data.selectable !== 'undefined' && false == data.selectable ){
          alert( data.message );
        } else {
          DateString = ('0' + (date.getMonth()+1)).slice(-2) + '/'
               + ('0' + date.getDate()).slice(-2) + '/'
               + date.getFullYear();
          target.val( DateString );
        }
      }
  }).prop( 'readonly', true );
});