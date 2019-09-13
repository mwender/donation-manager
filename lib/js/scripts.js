(function($){
  $(document).on('submit', '#donors-in-your-area', function (e) {
    let zipcode = $('#zipcode').val();
    let radius = $('#radius').val();
    let days = $('#days').val();
    const donorsTable = $('#donors-in-your-area-table tbody');
    console.log('Form submitted:', "\n• zipcode: ", zipcode, "\n• radius: ", radius, "\n• days: ", days);
    $('.alert-before').fadeIn();
    if ($('.alert-donors').is(':visible') )
      $('.alert-donors').fadeOut();
    donorsTable.html('');

    $.get('/wp-json/donations/v1/search/' + zipcode + '/' + radius + '/' + days, null, function(response){
      console.log('response: ', response );

      let donations = response.data.donations;

      donations.forEach(function(el){
        let row = '<tr><td>' + el.number + '</td><td>' + el.title + '</td><td>' + el.zipcode + '</td><td>' + el.date + '</td></tr>';
        donorsTable.append(row);
      });

      $('.alert-before').fadeOut();
      $('.alert-donors').fadeIn();
      $('#display_count').html(donations.length );
      $('#display_zipcode').html(zipcode);
      $('#display_radius').html(radius);
      $('#display_days').html(days);

      $('button[type="submit"]').removeAttr('disabled');
      $('button[type="submit"]').removeClass('disabled');
      $('button[type="submit"]').html('Search');
    });

    e.preventDefault();
  });


  $('#additional-details').addClass('hidden');

  $(document).on('submit', 'form', function (e) {
    // Disable submit
    $('button[type="submit"]').attr('disabled', 'disabled');
    $('button[type="submit"]').addClass('disabled');
    $('button[type="submit"]').html('one moment...');
    e.stopPropagation();
  });

  var provide_additional_details = $("form[name=\"screening-questions\"] input[name=\"provide_additional_details\"]");
  pad_value = provide_additional_details.val() == '1'; // get the boolean value

  if (true === pad_value) {
    var questions = $("form[name=\"screening-questions\"] input:checked");

    for (x = 0; x < questions.length; x++) {
      if ('yes' === questions[x].value.toLowerCase())
        $('#additional-details').removeClass('hidden');
    }

    $("form[name=\"screening-questions\"] input:radio").change(function () {
      var showAdditionalDetails = false;

      var questions = $("form[name=\"screening-questions\"] input:checked");

      for (x = 0; x < questions.length; x++) {
        if ('yes' === questions[x].value.toLowerCase())
          showAdditionalDetails = true;
      }

      if (true === showAdditionalDetails) {
        $('#additional-details').removeClass('hidden');
      } else {
        $('#additional-details').addClass('hidden');
      }
    });
  }

  /* Show Priority Pick Up Option */
  $('.show-priority').on('click', function (e) {
    e.preventDefault();
    $('.priority-note').slideUp();
    $('.priority-row').slideDown();
  });
  $('.close-priority-row').on('click', function (e) {
    e.preventDefault();
    $('.priority-note').slideDown();
    $('.priority-row').slideUp();
  });
})(jQuery);

/*
jQuery(document).ready(function($){

});
*/