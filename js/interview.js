(function($) { console.log('loading interview js');
  let isAjaxRunning = false;

  $(function($) {

    $(document).on('change', 'select#edit-field-channel-type', function() {
      var type = $(this).val();

      if (type) {
        $('select#channel_select').val('');
        //$('div#channel-other-wrapper').hide();
        $('input#channel_other').val('');
        $('input#edit-field-channel-0-value').val('');

        reloadChannel(type);
      }

    });

    $(document).on('change', 'input[name=field_channel_type]', function() {
      var type = $('input[name=field_channel_type]:checked').val();

      if (type) {
        $('select#channel_select').val('');
        //$('div#channel-other-wrapper').hide();
        $('input#channel_other').val('');
        $('input#edit-field-channel-0-value').val('');

        reloadChannel(type);
      }

    });

    $(document).on('change', 'select#channel_select', function() {
      var value = $(this).val();

      if (value === 'other') {
        $('input#edit-field-channel-0-value').val('');
      }
      else {
        //$('div#channel-other-wrapper').hide();
        $('input#channel_other').val('');
        $('input#edit-field-channel-0-value').val(value);
      }

      //$('input#edit-field-channel-0-value').val( value );

    });

    $(document).on('keyup', 'input#channel_other', function() {
      var inputVal = $(this).val(); // Get the current input value

      $('input#edit-field-channel-0-value').val(inputVal); // Show it in
    });

  });

  function reloadChannel(type) {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('channel_type', type);

    $.ajax({
      url: '/pf10/ajax-channel2',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('select[id=channel_select]').empty().html(response.html);
        }
        else {}

      }
    });

  }

}
)(jQuery);