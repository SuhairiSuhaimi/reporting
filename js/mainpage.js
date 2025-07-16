(function($) {
  $(window).on('load', function() {
    hideLoading();
  });

  $(function($) {
    // Run on page load
    updateHeaderZIndex();

    // Also check periodically as a fallback
    setInterval(updateHeaderZIndex, 200);

    // Initialize on load
    showLoading();

    // Show pop up signinModal for Log In
    $(document).on('click', '.div-locality', function() {
      var locality = $(this).attr('rel');

      //showLoading();

      $('#signinModal').show();

    //   $.ajax({
    //     url: '/pf10/managepage',
    //     method: 'POST',
    //     data: {
    //         : dutaId,
    //     },
    //     success: function(response) {
    //       hideLoading();

    //       // Update the content of the page
    //       $('#fieldsetUpload').html(response.html);

    //       $('#signinModal').show();
    //     }
    //   });
    });

    $(document).on('keydown', 'input#signin_email', function() {
      var email = $(this).val();
      var pass = $('input#signin_password').val();

      if (email !== '' && pass !== '') {
        $('#submitSignIn').removeClass('disabled');
      }
      else {
        $('#submitSignIn').addClass('disabled');
      }

    });

    $(document).on('keydown', 'input#signin_password', function() {
      var pass = $(this).val();
      var email = $('input#signin_email').val();

      if (email !== '' && pass !== '') {
        $('#submitSignIn').removeClass('disabled');
      }
      else {
        $('#submitSignIn').addClass('disabled');
      }

    });

    $(document).on('input', 'input#signin_email, input#signin_password', function() {
      var email = $('input#signin_email').val();
      var pass = $('input#signin_password').val();

      if (email !== '' && pass !== '') {
        $('#submitSignIn').removeClass('disabled');
      }
      else {
        $('#submitSignIn').addClass('disabled');
      }

    });

    $(document).on('blur', 'input#signin_email, input#signin_password', function() {
      var email = $('input#signin_email').val();
      var pass = $('input#signin_password').val();

      if (email !== '' && pass !== '') {
        $('#submitSignIn').removeClass('disabled');
      }
      else {
        $('#submitSignIn').addClass('disabled');
      }

    });


    // Close uploadImageModal when Clicking on the Close Button
    $(document).on('click', '#closesignin, #closeSignIn', function(e) {
      e.preventDefault();

      $('#signinModal').hide();
      $('#signin_email').val('');
      $('#signin_password').val('');
      $('.inputerror').html('').hide();
    });

    // Trigger upload submit button click
    $(document).on('click', '#submitSignIn:not(.disabled)', function() {
      var process = true;

      $('.inputerror').html('').hide();
      $('.message').hide();

      if (process) {
        showLoading();

        var form = $('#signinForm')[0];
        var formData = new FormData(form); // 'this' is typically the form element

        $.ajax({
          url: '/pf10/signin',
          type: 'POST',
          data: formData,
          contentType: false,
          processData: false,
          success: function(response) {
            if (response.status == '01') {
              const redirectUrl = '/pf10/managepage';
              window.location.href = redirectUrl;
            }
            else {
              hideLoading();

              $('#signinForm .fieldset-wrapper').siblings('.inputerror').html(response.message).show();
            }
          }
        });



      } //if (process)

    });



    //jQuery('#signinForm .fieldset-wrapper').siblings('.inputerror').html('Testing this').show();


    //closeSignIn


  });

}
)(jQuery);