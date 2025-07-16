(function($) {
  $(function($) {

    $(document).on('keydown', 'input.number', function(e) {
      const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'
      ];

      // Allow control keys
      if (allowedKeys.includes(e.key)) {
        return;
      }

      // Allow digits 0-9
      if (/^[0-9]$/.test(e.key)) {
      //if (/^\d$/.test(e.key)) {
        return;
      }

      // Otherwise, block input
      e.preventDefault();
    });

  });

}
)(jQuery);
