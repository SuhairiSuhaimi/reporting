(function($) { console.log('loading listing js');
  $(function($) {
    // Run on page load
    updateHeaderZIndex();

    // Also check periodically as a fallback
    setInterval(updateHeaderZIndex, 200);

    let loadPage = parseInt( $('#currentPage').val() );

    // Initialize on load
    showLoading();
    loadingAjax(loadPage);

    // When the "Search" button is clicked
    $(document).on('click', '#btn_search', function() {
      if ($('#name').val()) {
        showLoading();
        loadingAjax(1);

        $('#currentPage').val(1);
      }
      else {
        return false;
      }
    });

    // When the "Reset" button is clicked
    $(document).on('click', '#btn_reset', function() {
      $('#name').val('');

      showLoading();
      loadingAjax(1);

      $('#currentPage').val(1);
    });

    // Go to detail PF10 page
    $(document).on('click', '.ahref', function() {
      var report_id = $(this).parent('tr').attr('rel');

      const url = '/pf10/basic-info/'+ report_id;

      showLoading();

      window.location.href = url;
    });

  });

  // Loading ajax data
  function loadingAjax(page = 1) {
    const nameFilter = $('#name').val();
    //const checkedCountry = getCheckedValues();
    const itemperpage = 10;

    $.ajax({
      url: '/pf10/listing-ajax',
      method: 'GET',
      data: {
        page: page,
        limit: itemperpage,
        //name: nameFilter,
        //state: checkedCountry,
      },
      success: function(response) {
        hideLoading();

        // Update the content of the page
        $('#pf10_content').html(response.html);

        // Update the pager
        $('#pagination').html(response.pager);

        totlaData = response.total;

      }
    });

  }

}
)(jQuery);


