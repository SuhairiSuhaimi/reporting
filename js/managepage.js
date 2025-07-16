(function($) { console.log('loading managepage js');
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

  });


}
)(jQuery);