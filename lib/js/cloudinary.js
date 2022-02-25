/* Cloudinary JS */
jQuery(document).ready(function($){

  $.cloudinary.config({{params}});
  $(function() {
    if($.fn.cloudinary_fileupload !== undefined) {
      $("input.cloudinary-fileupload[type=file]").cloudinary_fileupload();
    }
  });

  // Disable form submit during image upload
  $('.cloudinary-fileupload').bind('fileuploadsend', function(e, data){
    var parentForm = $(this).parents('form');
    console.log(parentForm);
    $(parentForm).find('button[type="submit"]').html( 'One moment...' ).addClass( 'disabled' ).prop( 'disabled', true );
  });

  // Show file upload progress
  $('.cloudinary-fileupload').bind('fileuploadprogress', function(e, data) {
    $('.cloudinary-fileupload').slideUp();
    $('.progress_bar').slideDown();
    $('.progress_bar').css({"width": Math.round((data.loaded * 100.0) / data.total) + "%"});
  });

  // Display thumbnail after image upload
  var publicIds = [];
  $('.cloudinary-fileupload').bind('cloudinarydone', function(e, data) {
    $('.preview').slideDown();
    $('.progress_bar').slideUp();
    $('.cloudinary-fileupload').slideDown();
    $('.preview').append($.cloudinary.image(
        data.result.public_id,
        {
          format: data.result.format, version: data.result.version,
          crop: 'fill',
          width: 150,
          height: 100
        }
    ));
    publicIds.push( data.result.public_id );
    $('#image_public_id').val( publicIds );

    var parentForm = $(this).parents('form');
    $(parentForm).find('button[type="submit"]').html( 'Continue to Step 3' ).removeClass( 'disabled' ).prop( 'disabled', false );
    return true;
  });

});