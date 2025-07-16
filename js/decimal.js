(function($) {
  $(function($) {

    // Allow only numbers and the decimal point
    $(document).on('keydown', 'input.decimal', function(e) {
      const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'
      ];

      const currentVal = $(this).val();

      // Allow control keys
      if (allowedKeys.includes(e.key)) {
        return;
      }

      // Allow digits
      if (/^[0-9]$/.test(e.key)) {
      //if (/^\d$/.test(e.key)) {
        return;
      }

      // Allow only one decimal point
      if (e.key === '.' && !currentVal.includes('.')) {
        return;
      }

      // Block anything else
      e.preventDefault();
    });

    $(document).on('input', 'input.decimal', function(e) {
      let currentValue = $(this).val();

      var maxlength = 10;

      if ($(this).attr('maxlength')) {
        maxlength = $(this).attr('maxlength');
      }

      let decidigit = maxlength - 3;

      // Remove invalid characters (letters, symbols except 1 dot)
      currentValue = currentValue.replace(/[^0-9.]/g, '');

      // Limit the number of digits before the decimal point to maxlength - 3
      let parts = currentValue.split('.');

      if (parts[0].length > decidigit) {
        parts[0] = parts[0].substring(0, decidigit); // Allow only 8 digits before decimal
      }

      // Join the parts back together
      currentValue = parts.join('.');

      // Ensure only one decimal point exists
      if (currentValue.indexOf('.') !== -1) {
        let [whole, decimal] = currentValue.split('.');

        decimal = decimal.substring(0, 2); // Limit to 2 decimals
        currentValue = whole + '.' + decimal;
      }

      // Limit the input length to maxlength characters (8 digits + 1 decimal + 2 digits after decimal)
      if (currentValue.length > maxlength) {
        currentValue = currentValue.substring(0, maxlength);
      }

      $(this).val(currentValue);

    });

    // Format the input to 2 decimal places when the input loses focus (blur event)
    $(document).on('blur', 'input.decimal', function(e) {
      let currentValue = $(this).val();

      if (currentValue !== '') {
        let parsedValue = parseFloat(currentValue);

        if (!isNaN(parsedValue)) {
          $(this).val(parsedValue.toFixed(2));
        }
      }
    });

  });

}
)(jQuery);
