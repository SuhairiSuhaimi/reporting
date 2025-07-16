(function($) { console.log('loading article js');
  $(function($) {

    $(document).on('change', 'select[name=publisher_select]', function() {
      var value = $(this).val();

      if (value === 'other') {
        $('input#edit-field-publisher-0-value').val('');
      }
      else {
        //$('div#publisher-other-wrapper').hide();
        $('input#publisher_other').val('');
        $('input#edit-field-publisher-0-value').val(value);
      }

      //$('input#edit-field-publisher-0-value').val( value );

    });

    $(document).on('keyup', 'input#publisher_other', function() {
      var inputVal = $(this).val(); // Get the current input value\

      $('input#edit-field-publisher-0-value').val(inputVal); // Show it in
    });

  });

}
)(jQuery);