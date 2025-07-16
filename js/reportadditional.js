(function($) {
  let dataChanges = false;
  let isAjaxRunning = false;

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

    addDutaParty(0);
    addActivityImage(0);

    $(document).on('change input', 'input, select, textarea', function() {
      dataChanges = true;
    });

    // Add Duta Participant
    $(document).on('click', '.plusDutaParty', function() {
      const lastDuta = $('.setfield-duta-party').last().find('.duta-participant');

      lastDuta.siblings('.inputerror').html('').hide();

      if (!lastDuta.val()) {
        // Show error if empty
        lastDuta.siblings('.inputerror').html('Sila isi ID Duta.').show();
      }
      else {
        if (isAjaxRunning) {
          console.log('AJAX is already running. Please wait.');
          return false; // Prevent the function from running if another AJAX call is in progress
        }

        // Set flag to true when AJAX starts
        isAjaxRunning = true;

        addDutaParty(1);
      }

    });

    // Remove Duta Participant
    $(document).on('click', '.minusDutaParty', function() {
      var setfieldDuta = $(this).closest('.setfield-duta-party');

      setfieldDuta.remove();

      showhideButtonDutaParty();
    });

    // Collaboration
    $(document).on('change', 'select[name=collaboration]', function() {
      var value = $(this).val();

      if (value == 'PAWE') {
        $('div.form-item-pawe-code').show();
        $('.input-pawe-code').select2();
      }
      else {
        $('div.form-item-pawe-code').hide();
        $('select[name=pawe_code]').val('');

        if ($('.input-pawe-code').hasClass('select2-hidden-accessible')) {
          // Remove Select2
          $('.input-pawe-code').select2('destroy');
        }

      }

      if (value == 'other') {
        $('div.form-item-collaboration-other').show();
      }
      else {
        $('div.form-item-collaboration-other').hide();
        $('input[name=collaboration_other]').val('');
      }

    });

    $(document).on('change', 'select.input-facility-involvement', function() {
      const selected = $(this).val(); // Gets selected values as array
      const maxSelect = 3;

      if (selected.length > maxSelect) {
        // If more than maxSelect selected, deselect the last one
        const deselectValue = selected[selected.length - 1];
        $(this).find('option[value="'+ deselectValue +'"]').prop('selected', false);

        //remove the last selected title
        $(this).siblings('.select2').find('li.select2-selection__choice').last().remove();
        $(this).siblings('.inputerror').html('Hanya '+ maxSelect +' pilihan sahaja dibenarkan').show();
      }
      else {
        $(this).siblings('.inputerror').html('').hide();
      }
    });

    $(document).on('change', 'input.activity-image', function() {
      const file = this.files[0];
      const previewDiv = $(this).closest('.setfield-activity-image').find('.image-preview');

      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
      // Validate file size (max 5MB)
      const maxSize = 5 * 1024 * 1024; // 5MB in bytes

      // Clear previous preview
      previewDiv.empty();

      if (file) {
        //if (!file.type.startsWith('image/'))
        if (!allowedTypes.includes(file.type))
        {
          $(this).siblings('.inputerror').html("Sila pilih format gambar aktiviti yang betul dan saiz imej tidak melebihi 5mb.").show();
          $(this).val('');
          return false;
        }
        else if (file.size > maxSize) {
          $(this).siblings('.inputerror').html("Saiz imej terlalu besar. Saiz imej yang dibenarkan tidak melebihi 5mb.").show();
          $(this).val('');
          return false;
        }
        else {
          const reader = new FileReader();

          reader.onload = function (e) {
            const img = $('<img>').attr('src', e.target.result);
            previewDiv.append(img);
          };

          reader.readAsDataURL(file);
          $(this).siblings('.inputerror').html('').hide();
        }
      }

    });

    // Add Activity Image
    $(document).on('click', '.plusActivityImage', function() {
      const lastImage = $('.setfield-activity-image').last().find('.activity-image');

      lastImage.siblings('.inputerror').html('').hide();

      if (!lastImage.val()) {
        // Show error if empty
        lastImage.siblings('.inputerror').html('Sila muat naik gambar.').show();
      }
      else {
        if (isAjaxRunning) {
          console.log('AJAX is already running. Please wait.');
          return false; // Prevent the function from running if another AJAX call is in progress
        }

        // Set flag to true when AJAX starts
        isAjaxRunning = true;

        addActivityImage(1);
      }

    });

    // Remove Duta Participant
    $(document).on('click', '.minusActivityImage', function() {
      var rel = $(this).attr('rel');
      var setfieldImage = $(this).closest('.setfield-activity-image');

      setfieldImage.remove();

      if (rel != '') {
        dataChanges = true;
        var html = '<input type="hidden" name="delete_image[]" value="'+ rel +'">';
        $('#section-images-upload').append(html);
      }

      showhideButtonActivityImage();
    });

    // When the Save button is clicked
    $(document).on('click', '#savePage3', function(e) {
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
          var form = $('#pf10-additional-info')[0];
          var formData = new FormData(form); // 'this' is typically the form element

          $.ajax({
            url: '/pf10/additional/submit',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
              hideLoading();

              if (response.status == '01') {
                $('input[name=reportid]').val(response.reportid);
                $('#successMessage').html(response.message).show();

                setTimeout(function() {
                  $('#successMessage').hide();
                }, 20000);

                $('#success-message').html(response.message);
                $('#successModal').show();
              }
              else {
                $('#errorMessage').html(response.message).show();
                $('#error-message').html(response.message).show();
                $('#errorModal').show();
              }
            }
          });

        }
        else{
          const url = '/';
          window.location.href = url;
        }
      }
    });

    // When the Save button is clicked
    $(document).on('click', '#backPage3', function(e) {
      e.preventDefault();

      showLoading();

      const reportId = $('input[name=reportid]').val();
      const redirectUrl = '/pf10/activity-info/'+ reportId;
      window.location.href = redirectUrl;
    });

    // Close successModal when Clicking on the Close Button
    $(document).on('click', '#closeSuccess', function(e) {
      e.preventDefault();

      showLoading();

      $('#cancelPage').trigger('click');

      //const reportId = $('input[name=reportid]').val();
      //const redirectUrl = '/pf10/add-info/'+ reportId;
      //window.location.href = redirectUrl;
    });

  });

  function addDutaParty(no) {
    const editable = $('#editable').val();
    const reportId = $('input[name=reportid]').val();
    const report_id = $('input[name=report_id]').val();
    var nextRunning =  parseInt(no) + 1;

    var formData = new FormData(); // 'this' is typically the form element
    formData.append('sequence', nextRunning);
    formData.append('reportid', reportId);
    formData.append('report_id', report_id);

    $.ajax({
      url: '/pf10/ajax-add-duta-party',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('div#section-duta-party').append(response.html);

          if (editable === 'TRUE') {
            showhideButtonDutaParty();
          }
          else {
            readOnlyMode();
          }

        }
        else {
          $('#errorMessage').html(response.message).show();
          $('#error-message').html(response.message).show();
          $('#errorModal').show();
        }

      }
    });

  }

  function addActivityImage(no) {
    const editable = $('#editable').val();
    const reportId = $('input[name=reportid]').val();
    const report_id = $('input[name=report_id]').val();
    var nextRunning =  parseInt(no) + 1;

    var formData = new FormData(); // 'this' is typically the form element
    formData.append('sequence', nextRunning);
    formData.append('reportid', reportId);
    formData.append('report_id', report_id);

    $.ajax({
      url: '/pf10/ajax-add-activity-image',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('div#section-images-upload').append(response.html);

          if (editable === 'TRUE') {
            showhideButtonActivityImage();
          }
          else {
            readOnlyMode();
          }

        }
        else {
          $('#errorMessage').html(response.message).show();
          $('#error-message').html(response.message).show();
          $('#errorModal').show();
        }

      }
    });

  }

}
)(jQuery);

function readOnlyMode() {
  var editable = jQuery('#editable').val();

  if (editable === 'FALSE') {
    jQuery('#section-duta-party .button3').hide();
    jQuery('#section-images-upload .button3').hide();
    jQuery('#section-duta-party input').prop('disabled', 'disabled');
  }

}

function showhideButtonDutaParty() {
  const noDuta = jQuery('.setfield-duta-party').length;

  if (noDuta == 1) {
    jQuery('div.minusDutaParty').hide();
  }
  else {
    jQuery('div.minusDutaParty').show();
  }

}

function showhideButtonActivityImage() {
  const noImage = jQuery('.setfield-activity-image').length;

  if (noImage >= 3) {
    jQuery('div.plusActivityImage').hide();
  }
  else {
    jQuery('div.plusActivityImage').show();
  }

  if (noImage == 1) {
    jQuery('div.minusActivityImage').hide();
  }
  else {
    jQuery('div.minusActivityImage').show();
  }

}

// Checking validation before submit
function validateInput() {
  var initiative = jQuery('input[name=initiative]');  // radio button
  var initiative_val = jQuery('input[name=initiative]:checked');  // radio button value
  var duta_participant = jQuery('input.duta-participant');
  var budget_expense = jQuery('input[name=budget_expense]');
  var collaboration = jQuery('select[name=collaboration]');
  var pawe_code = jQuery('select[name=pawe_code]');
  var collaboration_other = jQuery('input[name=collaboration_other]');
  var involve_facility = jQuery('select.input-facility-involvement');
  var remark = jQuery('textarea[name=remark]');
  var rph_no = jQuery('input[name=rph_no]');
  var image = jQuery('input.activity-image');

  var error = 0;

  if (!initiative_val.val()) {
    initiative.parents('.radio-group').siblings('.inputerror').html("Sila pilih Adakah program ini inisiatif Duta.").show();

    error++;
  }
  else {
    initiative.parents('.radio-group').siblings('.inputerror').html('').hide();
  }

  duta_participant.each(function() {
    let dutaid = jQuery(this).val();

    if (dutaid === '') {
      //jQuery(this).siblings('.inputerror').html('Sila isi ID Duta.').show();

      //error++;
    }
    else {
      jQuery(this).siblings('.inputerror').html('').hide();
    }

  });

  if (!budget_expense.val()) {
    budget_expense.siblings('.inputerror').html("Sila isi Jumlah peruntukan yang telah dibelanjakan.").show();

    error++;
  }
  else {
    budget_expense.siblings('.inputerror').html('').hide();
  }

  if (!collaboration.val()) {
    //collaboration.siblings('.inputerror').html("Sila pilih Kementrerian/NGO yang berkolaborasi.").show();

    //error++;
  }
  else {
    collaboration.siblings('.inputerror').html('').hide();
  }

  if (!pawe_code.val() && collaboration.val() == 'PAWE') {
    pawe_code.siblings('.inputerror').html("Sila isi pilih PAWE yang terlibat.").show();

    error++;
  }
  else {
    pawe_code.siblings('.inputerror').html('').hide();
  }

  if (!collaboration_other.val() && collaboration.val() == 'other') {
    collaboration_other.siblings('.inputerror').html("Sila isi Lain-lain Kolaborasi yang terlibat.").show();

    error++;
  }
  else {
    collaboration_other.siblings('.inputerror').html('').hide();
  }

  if (!involve_facility.val()) {
    //involve_facility.siblings('.inputerror').html("Sila pilih Fasiliti yang terlibat.").show();

    //error++;
  }
  else {
    involve_facility.siblings('.inputerror').html('').hide();
  }

  if (!remark.val()) {
    //remark.parent('div').siblings('.inputerror').html("Sila isi Catatan penting.").show();

    //error++;
  }
  else {
    remark.parent('div').siblings('.inputerror').html('').hide();
  }

  if (!rph_no.val()) {
    rph_no.siblings('.inputerror').html("Sila isi No. RPh.").show();

    error++;
  }
  else {
    rph_no.siblings('.inputerror').html('').hide();
  }

  //if (image.first().get(0).files.length === 0) {
  if (!image.first().val()) {
    image.first().siblings('.inputerror').html("Sila pilih format gambar aktiviti yang betul dan saiz imej tidak melebihi 5mb.").show();

    error++;
  }
  else {
    image.first().siblings('.inputerror').html('').hide();
  }

  if (error) {
    return false;
  }
  else {
    return true;
  }

}
