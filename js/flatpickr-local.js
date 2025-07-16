(function($) {
  $(function($) {
    // Get today's date
    const today = new Date();

    // Calculate date 6 months ago
    const sixMonthsAgo = new Date();
    sixMonthsAgo.setMonth(today.getMonth() - 6);

    // Initialize Flatpickr for the date range
    flatpickr('.date-range-picker', {
      mode: 'range',
      dateFormat: 'Y-m-d',
      defaultDate: ($(this).val() ?? null),
      minDate: sixMonthsAgo,
      maxDate: today,
      altInput: true,
      altFormat: 'j F Y',
      locale: {
        rangeSeparator: ' - ',
      }
    });

    // Initialize Flatpickr for the start date
    flatpickr('.datepicker-start', {
      dateFormat: 'Y-m-d',
      minDate: sixMonthsAgo,
      maxDate: today,
      altInput: true,
      altFormat: 'j F Y',
      plugins: [new rangePlugin({ input: ".datepicker-end"})]
    });

    // Initialize Flatpickr for the date
    flatpickr('.datepicker-end', {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'j F Y',
    });

    // Initialize Flatpickr for the date
    flatpickr('.datepicker', {
      dateFormat: 'Y-m-d',
      minDate: sixMonthsAgo,
      maxDate: today,
      altInput: true,
      altFormat: 'j F Y',
    });

    // Initialize Flatpickr for the time format 12
    flatpickr('.timepicker-12', {
      enableTime: true,
      noCalendar: true,
      dateFormat: 'H:i',
      minuteIncrement: 5,
      time_24hr: false,
      minTime: '00:00',
      maxTime: '23:59',
      altInput: true,
      altFormat: 'h:i K',
    });

    // Initialize Flatpickr for the time format 24
    flatpickr('.timepicker-24', {
      enableTime: true,
      noCalendar: true,
      dateFormat: 'H:i', // 24-hour format. Use "h:i K" for 12-hour with AM/PM
      time_24hr: true,
      minTime: '00:00',
      maxTime: '23:59',
    });

    // Icon click open calendar or time
    $('i.fp').on('click', function() {
      $(this).siblings('.fpicker').trigger('click');
      $(this).closest('.fpicker').trigger('click');
    });

  });

}
)(jQuery);

// Initialize Date picker after field reload
function initFlatpickr() {
  // Get today's date
  const today = new Date();

  // Calculate date 6 months ago
  const sixMonthsAgo = new Date();
  sixMonthsAgo.setMonth(today.getMonth() - 6);

  // Initialize flatpickr on the .datepicker2 class
  flatpickr('.datepicker2', {
    dateFormat: 'Y-m-d',
    defaultDate: today,
    minDate: sixMonthsAgo,
    maxDate: today,
    altInput: true,
    altFormat: 'j F Y',
  });

}
