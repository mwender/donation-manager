/* Donors by Zip Code */
(function($){
  $(document).on('submit', '#donors-in-your-area', function (e) {
    let zipcode = $('#zipcode').val();
    let radius = $('#radius').val();
    let days = $('#days').val();
    const donorsTable = $('#donors-in-your-area-table tbody');
    console.log('Form submitted:', "\n• zipcode: ", zipcode, "\n• radius: ", radius, "\n• days: ", days);
    $('.alert-before').fadeIn();
    if ($('.alert-donors').is(':visible'))
      $('.alert-donors').fadeOut();
    if($('#map').is(':visible'))
      $('#map').fadeOut();
    donorsTable.html('');

    $.get('/wp-json/donations/v1/search/' + zipcode + '/' + radius + '/' + days, null, function (response) {
      console.log('response: ', response);
      $('#map').fadeIn();

      let donations = response.data.donations;

      // Map the data
      let zipcodeCoor = { lat: response.coordinates.lat, lng: response.coordinates.lng };
      console.log('zipcodeCoor = ', zipcodeCoor);
      const map = new google.maps.Map(
        document.getElementById('map'),
        { zoom: 10, center: zipcodeCoor }
      );
      const mapMarker = new google.maps.Marker({ position: zipcodeCoor, map: map });

      // Map the boundaries of the search zip code:
      const zipCodeLayerUrl = wpvars.zipCodeMapsUrl + 'zip' + zipcode + '.kml';
      console.log('zipCodeLayerUrl = ', zipCodeLayerUrl);
      var georssLayer = new google.maps.KmlLayer({
        url: zipCodeLayerUrl,
        map: map,
        preserveViewport: true
      });

      const donationMarkers = [];
      donations.forEach(function (el) {
        let row = '<tr><td>' + el.number + '</td><td>' + el.title + '</td><td>' + el.zipcode + '</td><td>' + el.date + '</td></tr>';
        donorsTable.append(row);
        donationMarkers[el.number] = new google.maps.Marker({
          position: el.coordinates,
          map: map,
          title: el.zipcode,
          label: response.donations_by_zipcode[el.zipcode].toString()
        });
      });




      $('.alert-before').fadeOut();
      $('.alert-donors').fadeIn();
      $('#display_count').html(donations.length);
      $('#display_zipcode').html(zipcode);
      $('#display_radius').html(radius);
      $('#display_days').html(days);

      $('button[type="submit"]').removeAttr('disabled');
      $('button[type="submit"]').removeClass('disabled');
      $('button[type="submit"]').html('Search');
    });

    e.preventDefault();
  });
})(jQuery);

/*
function initMap(){
  console.log('Google Maps API...');
  var uluru = { lat: -25.344, lng: 131.036 };
  var map = new google.maps.Map(
    document.getElementById('map'),
    { zoom: 4, center: uluru }
  );
  var marker = new google.maps.Marker({position: uluru, map: map});
}
initMap();
*/