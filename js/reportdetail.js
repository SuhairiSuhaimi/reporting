(function($) {
  let dataChanges = false;

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

    $(document).on('change input', 'input, select, textarea', function() {
      dataChanges = true;
    });

    // Map from state code to full name
    const stateNames = {
      'JHR': 'Johor',
      'KDH': 'Kedah',
      'KTN': 'Kelantan',
      'MLK': 'Melaka',
      'NSN': 'Negeri Sembilan',
      'PHG': 'Pahang',
      'PRK': 'Perak',
      'PLS': 'Perlis',
      'PNG': 'Pulau Pinang',
      'SBH': 'Sabah',
      'SRW': 'Sarawak',
      'SGR': 'Selangor',
      'TRG': 'Terengganu',
      'KUL': 'Kuala Lumpur',
      'LBN': 'Labuan',
      'PJY': 'Putrajaya',
    };

    $(document).on('change', '.input-state-code', function() {
      var value = $(this).val();
      var text = $(this).find('option:selected').text();

      if ($('select[name=facility_code]').length) {
        reloadFacility(value);
      }

      if ($('select[name=ptj_code]').length) {
        reloadPTJ(value);
      }

      if (value) {
        geocodeState( stateNames[ value ] );
       }

    });

    $(document).on('change', '.input-facility-code', function() {
      var value = $(this).val();

      if ($('select[name=ptj_code]').length && !$('select[name=ptj_code]').val()) {
        reloadPTJbyFacility(value);
      }

    });

    $(document).on('change', '.input-ptj-code', function() {
      var value = $(this).val();

      if ($('select[name=facility_code]').length) {
        reloadFacilityByPTJ(value);
      }

    });

    // When the Submit button is clicked
    $(document).on('click', '#submitPage1', function(e) {
      e.preventDefault();

      process = validateInput();

      if (!process) {
        $('#error-message').html('Ralat dikesan.<br/>Sila semak semula.');
        $('#errorModal').show();
      }
      else {
        showLoading();

        var form = $('#pf10-basic-info')[0];
        var formData = new FormData(form); // 'this' is typically the form element

        $.ajax({
          url: '/pf10/detail/submit',
          type: 'POST',
          data: formData,
          contentType: false,
          processData: false,
          success: function(response) {
            if (response.status == '01') {
              //const redirectUrl = '/pf10/activity-info/'+ encodeURIComponent(response.id);
              const redirectUrl = '/pf10/activity-info/'+ response.reportid;
              window.location.href = redirectUrl;
            }
            else {
              hideLoading();
              $('#errorMessage').html(response.message).show();
              $('#error-message').html(response.message).show();
              $('#errorModal').show();
            }
          }
        });

      }
    });

    // When the Save button is clicked
    $(document).on('click', '#savePage1', function(e) {
      e.preventDefault();

      const reportId = $('input[name=reportid]').val();

      process = validateInput();

      if (!process) {
        $('#error-message').html('Ralat dikesan.<br/>Sila semak semula.');
        $('#errorModal').show();
      }
      else {
        showLoading();

        if (dataChanges) {
          var form = $('#pf10-basic-info')[0];
          var formData = new FormData(form); // 'this' is typically the form element

          $.ajax({
            url: '/pf10/detail/update',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
              if (response.status == '01') {
                //const redirectUrl = '/pf10/activity-info/'+ encodeURIComponent(response.id);
                //const redirectUrl = '/pf10/activity-info/'+ response.reportid;
                const redirectUrl = '/pf10/activity-info/'+ reportId;
                window.location.href = redirectUrl;
              }
              else {
                hideLoading();
                $('#errorMessage').html(response.message).show();
                $('#error-message').html(response.message).show();
                $('#errorModal').show();
              }
            }
          });

        }
        else{
          const redirectUrl = '/pf10/activity-info/'+ reportId;
          window.location.href = redirectUrl;
        }
      }
    });

  });

  function reloadFacility(state_code) {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('state_code', state_code);

    $.ajax({
      url: '/pf10/ajax-facility',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('select[name=facility_code]').empty().html(response.html);
        }
        else {}

      }
    });

  }

  function reloadFacilityByPTJ(ptj_code) {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('ptj_code', ptj_code);

    $.ajax({
      url: '/pf10/ajax-facility-by-ptj',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('select[name=facility_code]').empty().html(response.html);
        }
        else {}

        if (response.facility_code) {
          $('select[name=facility_code]').val(response.facility_code);
        }

      }
    });

  }

  function reloadPTJ(state_code) {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('state_code', state_code);

    $.ajax({
      url: '/pf10/ajax-ptj',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('select[name=ptj_code]').empty().html(response.html);
        }
        else {}

      }
    });

  }

  function reloadPTJbyFacility(facility_code) {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('facility_code', facility_code);

    $.ajax({
      url: '/pf10/ajax-ptj-by-facility',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('select[name=ptj_code]').empty().html(response.html);
        }
        else {}

        if (response.ptj_code) {
          $('select[name=ptj_code]').val(response.ptj_code);
        }

      }
    });

  }

}
)(jQuery);

// Checking validation before submit
function validateInput() {
  var email = jQuery('input[name=email]');
  var organization = jQuery('input[name=organization]');  // radio button
  var organization_val = jQuery('input[name=organization]:checked');  // radio button value
  var state_code = jQuery('select[name=state_code]');
  var facility_code = jQuery('select[name=facility_code]');
  var ptj_code = jQuery('select[name=ptj_code]');
  var facility_other = jQuery('input[name=facility_other]');
  var program_title = jQuery('input[name=program_title]');
  var program_date = jQuery('input[name=program_date]');
  var program_start_time = jQuery('input[name=program_start_time]');
  var program_end_time = jQuery('input[name=program_end_time]');
  var location = jQuery('input[name=location]');
  var postcode = jQuery('input[name=postcode]');
  //var latitude = jQuery('input[name=latitude]');
  //var longitude = jQuery('input[name=longitude]');
  var location_type = jQuery('input[name=location_type]');  // radio button
  var locationType_val = jQuery('input[name=location_type]:checked');  // radio button value
  var program_method = jQuery('input[name=program_method]');  // radio button
  var programMethod_val = jQuery('input[name=program_method]:checked');  // radio button value

  var error = 0;

  if (!email.val()) {
    email.siblings('.inputerror').html("Sila isi alamat Email.").show();

    error++;
  }
  else {
    var value = email.val().trim();
    //const emailPattern1 = /^[a-zA-Z0-9._%+-]{5,}@[a-zA-Z0-9-]{3,}\.[a-zA-Z]{3,}$/;  //name@contoh.com
    //const emailPattern2 = /^[a-zA-Z0-9._%+-]{5,}@[a-zA-Z0-9-]{3,}\.[a-zA-Z]{3,}\.[a-zA-Z]{2,}$/;  //name@contoh.com.my
    const emailPattern3 = /^[a-zA-Z0-9._%+-]{5,}@[a-zA-Z0-9.-]{3,}\.[a-zA-Z]{3,}(\.[a-zA-Z]{2,})?$/;


    if (!emailPattern3.test(value)) {
      email.siblings('.inputerror').html("Alamat email yang diisi tidak sah.").show();

      error++;
    }
    else {
      email.siblings('.inputerror').html('').hide();
    }
  }

  if (!organization_val.val()) {
    organization.parents('.radio-group').siblings('.inputerror').html("Sila pilih Jenis Organisasi.").show();

    error++;
  }
  else {
    organization.parents('.radio-group').siblings('.inputerror').html('').hide();
  }

  if (!state_code.val()) {
    state_code.siblings('.inputerror').html("Sila pilih Negeri.").show();

    error++;
  }
  else {
    state_code.siblings('.inputerror').html('').hide();
  }

  if (!facility_code.val() && organization_val.val() == 'KKM') {
    facility_code.siblings('.inputerror').html("Sila isi pilih Fasiliti.").show();

    error++;
  }
  else {
    facility_code.siblings('.inputerror').html('').hide();
  }

  if (!ptj_code.val() && organization_val.val() == 'KKM') {
    ptj_code.siblings('.inputerror').html("Sila isi pilih PTJ.").show();

    error++;
  }
  else {
    ptj_code.siblings('.inputerror').html('').hide();
  }

  if (!facility_other.val() && organization_val.val() == 'NOT') {
    facility_other.siblings('.inputerror').html("Sila isi Fasiliti yang terlibat.").show();

    error++;
  }
  else {
    facility_other.siblings('.inputerror').html('').hide();
  }

  if (!program_title.val()) {
    program_title.siblings('.inputerror').html("Sila isi Name Program.").show();

    error++;
  }
  else {
    program_title.siblings('.inputerror').html('').hide();
  }

  if (!program_date.val()) {
    program_date.siblings('.inputerror').html("Sila isi Tarikh Program.").show();

    error++;
  }
  else {
    program_date.siblings('.inputerror').html('').hide();
  }

  if (!program_start_time.val()) {
    program_start_time.parents('.form-group').siblings('.inputerror').html("Sila isi Masa Program.").show();

    error++;
  }
  else {
    program_start_time.parents('.form-group').siblings('.inputerror').html('').hide();
  }

  if (!program_end_time.val()) {
    program_end_time.parents('.form-group').siblings('.inputerror').html("Sila isi Masa Program.").show();

    error++;
  }
  else {
    program_end_time.parents('.form-group').siblings('.inputerror').html('').hide();
  }

  if (!location.val()) {
    location.siblings('.inputerror').html("Sila isi Lokasi Program.").show();

    error++;
  }
  else {
    location.siblings('.inputerror').html('').hide();
  }

  if (!postcode.val()) {
    postcode.siblings('.inputerror').html("Sila isi Poskod Lokasi Program.").show();

    error++;
  }
  else {
    postcode.siblings('.inputerror').html('').hide();
  }

  if (!locationType_val.val()) {
    location_type.parents('.radio-group').siblings('.inputerror').html("Sila pilih Jenis Kawasan.").show();

    error++;
  }
  else {
    location_type.parents('.radio-group').siblings('.inputerror').html('').hide();
  }

  if (!programMethod_val.val()) {
    program_method.parents('.radio-group').siblings('.inputerror').html("Sila pilih Kaedah Program.").show();

    error++;
  }
  else {
    program_method.parents('.radio-group').siblings('.inputerror').html('').hide();
  }

  if (error) {
    return false;
  }
  else {
    return true;
  }

}
