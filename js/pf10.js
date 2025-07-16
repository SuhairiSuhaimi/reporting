(function($) {
  $(function($) {

    $(document).on('click', '#successMessage', function() {
      $('#successMessage').hide();
    });

    $(document).on('click', '#errorMessage', function() {
      $('#errorMessage').hide();
    });

    $('form .form-item:not(.form-disabled)').each(function () {
      $(this).append('<p class="text-danger inputerror"></p>');
    });

    // When the Cancel button is clicked
    $(document).on('click', '#cancelPage', function(e) {
      var url = '/pf10/managepage';

      var link = $(this).attr('link');
      if (typeof link !== 'undefined' && link !== false && link !== '') {
        url = link;
      }

      showLoading();

      window.location.href = url;
    });

    // Close errorModal when Clicking on the Close Button
    $(document).on('click', '#closeError', function(e) {
      e.preventDefault();

      $('#errorModal').hide();
    });

    // Close successModal when Clicking on the Close Button
    $(document).on('click', '#closeSuccess', function(e) {
      e.preventDefault();

      $('#successModal').hide();
    });

    // Close successModal when Clicking on the Close Button
    $(document).on('click', '#goTo', function(e) {
      showLoading();

      const redirectUrl = $(this).attr('link');
      window.location.href = redirectUrl;
    });

  });

}
)(jQuery);

// Update header z-index
function updateHeaderZIndex() {
  if (jQuery('.modal:visible').length > 0) {
    jQuery('header').css('z-index', '1');
    jQuery('footer').css('z-index', '1');
  }
  else {
    jQuery('header').css('z-index', '3');
    jQuery('footer').css('z-index', '2');
  }
}

function reloadSelect2() {
  jQuery('.form-select2').select2();
}
