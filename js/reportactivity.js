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

    addNewActivity(0);

    $(document).on('change input', 'input, select, textarea', function() {
      dataChanges = true;
    });

    // Activity Type trigger
    $(document).on('change', '.activity-type', function() {
      var rel = $(this).attr('rel');
      var value = $(this).val();
      var text = $(this).find('option:selected').text();
      var state = $('input[name=state_code]');

      if (!state.val()) {
        $(this).siblings('.inputerror').html('Terdapat ralat pada page sebelumnya. Sila semak semula page sebelumnya dan pastikan semua maklumat penting sudah diisi.').show();
        $(this).val('');

        return false;
      }

      if (value) {
        state.siblings('.inputerror').html('').hide();
        $(this).siblings('.inputerror').html('').hide();
        $('span#activity_title_'+ rel).text(text);

        reloadActivityContent(rel, value, state.val());
      }

      $('div#fieldset_content_'+ rel).html('');
    });

    // Add Activity
    $(document).on('click', '#addActivity', function() {
      const lastActivity = $('#activity-info .fieldset-activity').last().find('.activity-type');
      var rel = lastActivity.attr('rel');

      lastActivity.siblings('.inputerror').html('').hide();

      if (!lastActivity.val()) {
        // Show error if empty
        lastActivity.siblings('.inputerror').html('Sila pilih Aktiviti.').show();
      }
      else {
        // Disable the current select so it can't be changed
        lastActivity.prop('disabled', true);

        if (isAjaxRunning) {
          console.log('AJAX is already running. Please wait.');
          return false; // Prevent the function from running if another AJAX call is in progress
        }

        // Set flag to true when AJAX starts
        isAjaxRunning = true;

        addNewActivity(rel);
      }

    });

    // Remove Activity
    $(document).on('click', '.deleteFieldset', function() {
      var rel = $(this).attr('rel');

      $('div#fieldset_'+ rel).remove();

      showhideButtonFieldset();
      updateActivityOptions();
    });

    // Booth Target
    $(document).on('change', 'select[name=exhibit_target]', function() {
      var value = $(this).val();

      if (value == 'other') {
        $('div.form-item-exhibit-target-other').show();
      }
      else {
        $('div.form-item-exhibit-target-other').hide();
        $('input[name=exhibit_target_other]').val('');
      }

    });

    // Speech Target Other trigger
    $(document).on('change', 'select[name=speech_target]', function() {
      var value = $(this).val();

      if (value == 'other') {
        $('div.form-item-speech-target-other').show();
      }
      else {
        $('div.form-item-speech-target-other').hide();
        $('input[name=speech_target_other]').val('');
      }

    });

    // Add Speech Title
    $(document).on('click', '.plusSpeechTitle', function() {
      const lastSpeech = $('.setfield-speech').last().find('.speech-title');

      lastSpeech.siblings('.inputerror').html('').hide();

      if (!lastSpeech.val()) {
        // Show error if empty
        lastSpeech.siblings('.inputerror').html('Sila isi Tajuk Ceramah.').show();
      }
      else {
        if (isAjaxRunning) {
          console.log('AJAX is already running. Please wait.');
          return false; // Prevent the function from running if another AJAX call is in progress
        }

        // Set flag to true when AJAX starts
        isAjaxRunning = true;

        addSpeechTitle();
      }

    });

    // Remove Speech Title
    $(document).on('click', '.minusSpeechTitle', function() {
      var setfieldSpeech = $(this).closest('.setfield-speech');

      setfieldSpeech.remove();

      showhideButtonSpeech();
    });

    $(document).on('change', 'input[name=interview_type]', function() {
      var type = $('input[name=interview_type]:checked').val();

      if (type) {
        $('select[name=interview_channel]').val('');
        $('div.form-item-interview-channel-other').hide();
        $('input[name=interview_channel_other]').val('');

        reloadChannel(type);
      }

    });

    // Interview Channel Other trigger
    $(document).on('change', 'select[name=interview_channel]', function() {
      var value = $(this).val();

      if (value == 'other') {
        $('div.form-item-interview-channel-other').show();
      }
      else {
        $('div.form-item-interview-channel-other').hide();
        $('input[name=interview_channel_other]').val('');
      }

    });

    // Activity Type trigger
    $(document).on('change', '.social-type', function() {
      var rel = $(this).attr('rel');
      var value = $(this).val();
      var text = $(this).find('option:selected').text();

      if (value == 'live') {
        $('div#smedia_acount_'+ rel).show();
      }
      else  {
        $('div#smedia_acount_'+ rel).hide();
      }

      $(this).siblings('.inputerror').html('').hide();
    });

    // Activity Type trigger
    $(document).on('change', '.social-platform', function() {
      var rel = $(this).attr('rel');
      var value = $(this).val();

      if (value == 'other') {
        $('div#smedia_platform_other_'+ rel).show();
      }
      else {
        $('div#smedia_platform_other_'+ rel).hide();
        $('div#smedia_platform_other_'+ rel).find('input.social-platform-other').val('');
      }
    });

    // Add Social Media
    $(document).on('click', '.plusSMActivity', function() {
      const lastSocialMedia = $('.setfield-smedia').last().find('.social-type');
      var rel = lastSocialMedia.attr('rel');

      lastSocialMedia.siblings('.inputerror').html('').hide();

      if (!lastSocialMedia.val()) {
        // Show error if empty
        lastSocialMedia.siblings('.inputerror').html('Sila pilih Jenis Aktiviti Media Sosial.').show();
      }
      else {
        if (isAjaxRunning) {
          console.log('AJAX is already running. Please wait.');
          return false; // Prevent the function from running if another AJAX call is in progress
        }

        // Set flag to true when AJAX starts
        isAjaxRunning = true;

        addSocialMedia(rel);
      }

    });

    // Remove Speech Title
    $(document).on('click', '.minusSocialMedia', function() {
      var setfieldSocialMedia = $(this).closest('.setfield-smedia');

      setfieldSocialMedia.remove();

      showhideButtonSocialMedia();
    });

    // Article Publisher Other trigger
    $(document).on('change', 'select[name=article_publisher]', function() {
      var value = $(this).val();

      if (value == 'other') {
        $('div.form-item-article-publisher-other').show();
      }
      else {
        $('div.form-item-article-publisher-other').hide();
        $('input[name=article_publisher_other]').val('');
      }

    });

    // Add Other Activity
    $(document).on('click', '.plusOtherActivity', function() {
      const lastOther = $('.setfield-other').last().find('.other-activity');

      lastOther.siblings('.inputerror').html('').hide();

      if (!lastOther.val()) {
        // Show error if empty
        lastOther.siblings('.inputerror').html('Sila isi Lain-lain Aktiviti.').show();
      }
      else {
        if (isAjaxRunning) {
          console.log('AJAX is already running. Please wait.');
          return false; // Prevent the function from running if another AJAX call is in progress
        }

        // Set flag to true when AJAX starts
        isAjaxRunning = true;

        addOtherActivity();
      }

    });

    // Remove Other Activity
    $(document).on('click', '.minusOtherActivity', function() {
      var setfieldOther = $(this).closest('.setfield-other');

      setfieldOther.remove();

      showhideButtonOther();
    });

    // When the Save button is clicked
    $(document).on('click', '#savePage2', function(e) {
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
          var form = $('#pf10-activity-info')[0];
          var formData = new FormData(form); // 'this' is typically the form element

          $.ajax({
            url: '/pf10/activity/submit',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
              if (response.status == '01') {
                const redirectUrl = '/pf10/add-info/'+ reportId;
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
          const redirectUrl = '/pf10/add-info/'+ reportId;
          window.location.href = redirectUrl;
        }
      }
    });

    // When the Save button is clicked
    $(document).on('click', '#backPage2', function(e) {
      e.preventDefault();

      showLoading();

      const reportId = $('input[name=reportid]').val();
      const redirectUrl = '/pf10/basic-info/'+ reportId;
      window.location.href = redirectUrl;
    });

  });

  function addNewActivity(no) {
    const editable = $('#editable').val();
    const reportId = $('input[name=reportid]').val();
    const report_id = $('input[name=report_id]').val();
    var nextRunning =  parseInt(no) + 1;

    var formData = new FormData(); // 'this' is typically the form element
    formData.append('sequence', nextRunning);
    formData.append('reportid', reportId);
    formData.append('report_id', report_id);

    $.ajax({
      url: '/pf10/ajax-add-activity',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable
        hideLoading();

        if (response.status == '01') {
          $('#activity-info').append(response.html);

          if (editable === 'TRUE') {
            showhideButtonFieldset();
            updateActivityOptions();
            showhideButtonSpeech();
            showhideButtonSocialMedia();
            showhideButtonOther();
          }
          else {
            readOnlyMode();
          }

          initFlatpickr();
          reloadSelect2();
        }
        else {
          $('#errorMessage').html(response.message).show();
          $('#error-message').html(response.message).show();
          $('#errorModal').show();
        }

      }
    });

  }

  function reloadActivityContent(no, activity, state) {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('activity_type', activity);
    formData.append('activity_seq', no);
    formData.append('state_code', state);

    $.ajax({
      url: '/pf10/ajax-activity-content',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('div#fieldset_content_'+ no).html(response.html);

          if (activity == 'exhibit') {
            $('div.form-item-exhibit-target-other').hide();
          }

          if (activity == 'speech') {
            $('div.form-item-speech-target-other').hide();

            showhideButtonSpeech();
          }

          if (activity == 'social-media') {
            $('div#smedia_platform_other_1').hide();
            $('div#smedia_acount_1').hide();

            showhideButtonSocialMedia();
          }

          if (activity == 'interview') {
            $('div.form-item-interview-channel-other').hide();
          }

          if (activity == 'publisher') {
            $('div.form-item-article-publisher-other').hide();

            initFlatpickr();
          }

          if (activity == 'other') {
            showhideButtonOther();
          }

          reloadSelect2();
        }
        else {
          $('#errorMessage').html(response.message).show();
          $('#error-message').html(response.message).show();
          $('#errorModal').show();
        }

      }
    });
  }

  function addSpeechTitle() {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('sequence', 1);

    $.ajax({
      url: '/pf10/ajax-add-speech-title',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('div#section-speech').append(response.html);
          showhideButtonSpeech();
        }
        else {
          $('#errorMessage').html(response.message).show();
          $('#error-message').html(response.message).show();
          $('#errorModal').show();
        }

      }
    });

  }

  function reloadChannel(type) {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('channel_type', type);

    $.ajax({
      url: '/pf10/ajax-channel',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('select[name=interview_channel]').empty().html(response.html);
        }
        else {}

      }
    });

  }

  function addSocialMedia(no) {
    var nextNo =  parseInt(no) + 1;

    var formData = new FormData(); // 'this' is typically the form element
    formData.append('sequence', nextNo);

    $.ajax({
      url: '/pf10/ajax-add-social-media',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          $('div#section-smedia').append(response.html);
          $('div#smedia_platform_other_'+ nextNo).hide();
          $('div#smedia_acount_'+ nextNo).hide();

          showhideButtonSocialMedia();
        }
        else {
          $('#errorMessage').html(response.message).show();
          $('#error-message').html(response.message).show();
          $('#errorModal').show();
        }

      }
    });

  }

  function addOtherActivity() {
    var formData = new FormData(); // 'this' is typically the form element
    formData.append('sequence', 1);

    $.ajax({
      url: '/pf10/ajax-add-other-activity',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(response) {
        isAjaxRunning = false; // re-enable

        if (response.status == '01') {
          //$('#fieldset_content_'+ no).append(response.html);
          $('div#section-other').append(response.html);
          showhideButtonOther();
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
    jQuery('.fieldset-activity .button').hide();
    jQuery('.fieldset-activity .button3').hide();
    jQuery('.fieldset-activity input').prop('disabled', 'disabled');
    jQuery('.fieldset-activity select').prop('disabled', 'disabled');
  }

}

function showhideButtonFieldset() {
  const noActivity = jQuery('#activity-info .fieldset-activity').length;

  if (noActivity == 1) {
    jQuery('div.deleteFieldset').hide();
  }
  else {
    jQuery('div.deleteFieldset').show();
  }

  if (noActivity >= 9) {
    jQuery('div#addActivity').addClass('d-none').hide();
  }
  else {
    jQuery('div#addActivity').removeClass('d-none').show();
  }

}

function updateActivityOptions() {
  let selectedValues = [];

  // Get all selected values
  jQuery('.activity-type').each(function() {
    let val = jQuery(this).val();

    if (val) {
      selectedValues.push(val);
    }

  });

  // Disable selected options in other dropdowns
  jQuery('.activity-type:not(:disabled)').each(function() {
    let currentVal = jQuery(this).val();

    jQuery(this).find('option').each(function() {
      let optVal = jQuery(this).attr('value');

      if (selectedValues.includes(optVal) && optVal !== currentVal) {
        jQuery(this).attr('disabled', true);
      }
      else {
        jQuery(this).removeAttr('disabled');
      }

    });

  });

}

function showhideButtonSpeech() {
  const noSpeech = jQuery('.setfield-speech').length;

  if (noSpeech == 1) {
    jQuery('div.minusSpeechTitle').hide();
  }
  else {
    jQuery('div.minusSpeechTitle').show();
  }

}

function showhideButtonSocialMedia() {
  const noSocialMedia = jQuery('.setfield-smedia').length;

  if (noSocialMedia == 1) {
    jQuery('div.minusSocialMedia').hide();
  }
  else {
    jQuery('div.minusSocialMedia').show();
  }

}

function showhideButtonOther() {
  const noOther = jQuery('.setfield-other').length;

  if (noOther == 1) {
    jQuery('div.minusOtherActivity').hide();
  }
  else {
    jQuery('div.minusOtherActivity').show();
  }

}

// Checking validation before submit
function validateInput() {
  var errorExhibit = 0;
  var errorSpeech = 0;
  var errorInterview = 0;
  var errorSocialMedia = 0;
  var errorPicc = 0;
  var errorTraining = 0;
  var errorPublisher = 0;
  var errorMeeting = 0;
  var errorOther = 0;

  var error = 0;

  // Loop through each .activity-type select and check if a value is selected
  jQuery('.activity-type').each(function() {
    let selectedValue = jQuery(this).val();

    if (selectedValue === '') {
      jQuery(this).siblings('.inputerror').html('Sila pilih Aktiviti.').show();

      error++;
    }
    else {
      switch (selectedValue) {
        case 'exhibit':
          errorExhibit = validateExhibit();
          break;

        case 'speech':
          errorSpeech = validateSpeech();
          break;

        case 'interview':
          errorInterview = validateInterview();
          break;

        case 'social-media':
          errorSocialMedia = validateSocialMedia();
          break;

        case 'picc':
          errorPicc = validatePICC();
          break;

        case 'training':
          errorTraining = validateTraining();
          break;

        case 'publisher':
          errorPublisher = validatePublisher();
          break;

        case 'meeting':
          errorMeeting = validateMeeting();
          break;

        case 'other':
          errorOther = validateOther();
          break;

      }

    }

  });

  error = error + errorExhibit + errorSpeech + errorInterview + errorSocialMedia + errorPicc + errorTraining + errorPublisher + errorMeeting + errorOther;

  if (error) {
    return false;
  }
  else {
    return true;
  }

}

function validateExhibit() {
  var error = 0;

  var exhibit_booth = jQuery('input[name=exhibit_booth]');
  var exhibit_participant = jQuery('input[name=exhibit_participant]');
  var exhibit_target = jQuery('select[name=exhibit_target]');
  var exhibit_target_other = jQuery('input[name=exhibit_target_other]');

  if (!exhibit_booth.val()) {
    exhibit_booth.siblings('.inputerror').html("Sila isi bilangan gerai pameran.").show();

    error++;
  }
  else {
    exhibit_booth.siblings('.inputerror').html('').hide();
  }

  if (!exhibit_participant.val()) {
    exhibit_participant.siblings('.inputerror').html("Sila isi Jumlah peserta untuk pameran.").show();

    error++;
  }
  else {
    exhibit_participant.siblings('.inputerror').html('').hide();
  }

  if (!exhibit_target.val()) {
    exhibit_target.siblings('.inputerror').html("Sila pilih golongan sasaran.").show();

    error++;
  }
  else {
    exhibit_target.siblings('.inputerror').html('').hide();
  }

  if (!exhibit_target_other.val() && exhibit_target.val() == 'other') {
    exhibit_target_other.siblings('.inputerror').html("Sila isi Lain-lain golongan.").show();

    error++;
  }
  else {
    exhibit_target_other.siblings('.inputerror').html('').hide();
  }

  return error;
}

function validateSpeech() {
  var error = 0;

  var speech_title = jQuery('input.speech-title');
  var speech_participant = jQuery('input.speech-participant');
  var speech_target = jQuery('select[name=speech_target]');
  var speech_target_other = jQuery('input[name=speech_target_other]');

  speech_title.each(function() {
    let titleValue = jQuery(this).val();

    if (titleValue === '') {
      jQuery(this).siblings('.inputerror').html('Sila isi Tajuk Ceramah.').show();

      error++;
    }
    else {
      jQuery(this).siblings('.inputerror').html('').hide();
    }

  });

  speech_participant.each(function() {
    let participantValue = jQuery(this).val();

    if (participantValue === '') {
      jQuery(this).siblings('.inputerror').html('Sila isi Bilangan Peserta.').show();

      error++;
    }
    else {
      jQuery(this).siblings('.inputerror').html('').hide();
    }

  });

  if (!speech_target.val()) {
    speech_target.siblings('.inputerror').html("Sila pilih golongan sasaran.").show();

    error++;
  }
  else {
    speech_target.siblings('.inputerror').html('').hide();
  }

  if (!speech_target_other.val() && speech_target.val() == 'other') {
    speech_target_other.siblings('.inputerror').html("Sila isi Lain-lain golongan.").show();

    error++;
  }
  else {
    speech_target_other.siblings('.inputerror').html('').hide();
  }

  return error;
}

function validateInterview() {
  var error = 0;

  var interview_title = jQuery('input[name=interview_title]');
  var interview_type = jQuery('input[name=interview_type]');  // radio button
  var interviewType_val = jQuery('input[name=interview_type]:checked');  // radio button value
  var interview_channel = jQuery('select[name=interview_channel]');
  var interview_channel_other = jQuery('input[name=interview_channel_other]');

  if (!interview_title.val()) {
    interview_title.siblings('.inputerror').html("Sila isi Tajuk Wawancara.").show();

    error++;
  }
  else {
    interview_title.siblings('.inputerror').html('').hide();
  }

  if (!interviewType_val.val()) {
    interview_type.parents('.radio-group').siblings('.inputerror').html("Sila pilih Saluran Wawancara.").show();
    //return false;

    error++;
  }
  else {
    interview_type.parents('.radio-group').siblings('.inputerror').html('').hide();
  }

  if (!interview_channel.val()) {
    interview_channel.siblings('.inputerror').html("Sila pilih Nama Saluran.").show();

    error++;
  }
  else {
    interview_channel.siblings('.inputerror').html('').hide();
  }

  if (!interview_channel_other.val() && interview_channel.val() == 'other') {
    interview_channel_other.siblings('.inputerror').html("Sila isi Lain-lain saluran.").show();

    error++;
  }
  else {
    interview_channel_other.siblings('.inputerror').html('').hide();
  }

  return error;
}

function validateSocialMedia() {
  var error = 0;

  jQuery('.setfield-smedia').each(function (index) {
    const jQueryfieldset = jQuery(this);
    const type = jQueryfieldset.find('.social-type');
    const topic = jQueryfieldset.find('.social-topic');
    const platform = jQueryfieldset.find('.social-platform');
    const otherPlatform = jQueryfieldset.find('.social-platform-other');
    const account = jQueryfieldset.find('.social-account');
    const link = jQueryfieldset.find('.social-link');
    const reach = jQueryfieldset.find('.social-reach');

    if (!type.val()) {
      type.siblings('.inputerror').html("Sila pilih Jenis aktiviti.").show();

      error++;
    }
    else {
      type.siblings('.inputerror').html('').hide();
    }

    if (!topic.val()) {
      topic.siblings('.inputerror').html("Sila isi Topik.").show();

      error++;
    }
    else {
      topic.siblings('.inputerror').html('').hide();
    }

    if (!platform.val()) {
      platform.siblings('.inputerror').html("Sila pilih Platform yang digunakan.").show();

      error++;
    }
    else {
      platform.siblings('.inputerror').html('').hide();
    }

    // Check if social-platform is 'other', then otherPlatform is mandatory
    if (otherPlatform.val() === '' && platform.val() === 'other') {
      otherPlatform.siblings('.inputerror').html("Sila isi Lain-lain Platform Media Sosial.").show();

      error++;
    }
    else {
      otherPlatform.siblings('.inputerror').html('').hide();
    }

    // Check if social-type is 'live', then account is mandatory
    if (account.val() === '' && type.val() === 'live') {
      account.siblings('.inputerror').html("Sila isi Nama Akaun Media Social.").show();

      error++;
    }
    else {
      account.siblings('.inputerror').html('').hide();
    }

    // Check if social-type is 'live', then account is mandatory
    if (link.val() === '' && type.val() === 'live') {
      link.siblings('.inputerror').html("Sila isi Pautan Media Social.").show();

      error++;
    }
    else {
      link.siblings('.inputerror').html('').hide();
    }

    if (!reach.val()) {
      reach.siblings('.inputerror').html("Sila isi Jumlah keseluruhan capaian.").show();

      error++;
    }
    else {
      reach.siblings('.inputerror').html('').hide();
    }

  });

  return error;
}

function validatePICC() {
  var error = 0;

  var picc_session = jQuery('input[name=picc_session]');
  var picc_participant = jQuery('input[name=picc_participant]');
  var picc_facility = jQuery('select[name=picc_facility]');

  if (!picc_session.val()) {
    picc_session.siblings('.inputerror').html("Sila isi bilangan Sesi PICC.").show();

    error++;
  }
  else {
    picc_session.siblings('.inputerror').html('').hide();
  }

  if (!picc_participant.val()) {
    picc_participant.siblings('.inputerror').html("Sila isi Bilangan Ahli Duta yang terlibat.").show();

    error++;
  }
  else {
    picc_participant.siblings('.inputerror').html('').hide();
  }

  if (!picc_facility.val()) {
    picc_facility.siblings('.inputerror').html("Sila pilih faciliti kesihatan.").show();

    error++;
  }
  else {
    picc_facility.siblings('.inputerror').html('').hide();
  }

  return error;
}

function validateTraining() {
  var error = 0;

  var training_target = jQuery('select[name=training_target]');
  var training_participant = jQuery('input[name=training_participant]');

  if (!training_target.val()) {
    training_target.siblings('.inputerror').html("Sila pilih Jenis TOT.").show();

    error++;
  }
  else {
    training_target.siblings('.inputerror').html('').hide();
  }

  if (!training_participant.val()) {
    training_participant.siblings('.inputerror').html("Sila isi Jumlah peserta.").show();

    error++;
  }
  else {
    training_participant.siblings('.inputerror').html('').hide();
  }

  return error;
}

function validatePublisher() {
  var error = 0;

  var article_title = jQuery('input[name=article_title]');
  var article_date = jQuery('input[name=article_date]');
  var article_publisher = jQuery('select[name=article_publisher]');
  var article_publisher_other = jQuery('input[name=article_publisher_other]');
  var article_link = jQuery('input[name=article_link]');

  if (!article_title.val()) {
    article_title.siblings('.inputerror').html("Sila isi Tajuk artikel.").show();

    error++;
  }
  else {
    article_title.siblings('.inputerror').html('').hide();
  }

  if (!article_date.val()) {
    article_date.siblings('.inputerror').html("Sila isi Tarikh diterbitkan.").show();

    error++;
  }
  else {
    article_date.siblings('.inputerror').html('').hide();
  }

  if (!article_publisher.val()) {
    article_publisher.siblings('.inputerror').html("Sila pilih Saluran penerbitan.").show();

    error++;
  }
  else {
    article_publisher.siblings('.inputerror').html('').hide();
  }

  if (!article_publisher_other.val() && article_publisher.val() == 'other') {
    article_publisher_other.siblings('.inputerror').html("Sila isi Lain-lain Saluran penerbitan.").show();

    error++;
  }
  else {
    article_publisher_other.siblings('.inputerror').html('').hide();
  }

  if (!article_link.val()) {
    //article_link.siblings('.inputerror').html("Sila isi Pautan penerbitan.").show();
  }
  else {
    article_link.siblings('.inputerror').html('').hide();
  }

  return error;
}

function validateMeeting() {
  var error = 0;

  var meeting_module = jQuery('input[name=meeting_module]');
  var meeting_participant = jQuery('input[name=meeting_participant]');

  if (!meeting_module.val()) {
    meeting_module.siblings('.inputerror').html("Sila isi Module yang dikongsikan dalam mesyuarat.").show();

    error++;
  }
  else {
    meeting_module.siblings('.inputerror').html('').hide();
  }

  if (!meeting_participant.val()) {
    meeting_participant.siblings('.inputerror').html("Sila isi Bilangan Ahli meyuarat yang hadir.").show();

    error++;
  }
  else {
    meeting_participant.siblings('.inputerror').html('').hide();
  }

  return error;
}

function validateOther()  {
  var error = 0;

  var other_activity = jQuery('input.other-activity');
  var other_participant = jQuery('input.other-participant');

  other_activity.each(function() {
    let titleValue = jQuery(this).val();

    if (titleValue === '') {
      jQuery(this).siblings('.inputerror').html('Sila isi Aktiviti yang dijalankan.').show();

      error++;
    }
    else {
      jQuery(this).siblings('.inputerror').html('').hide();
    }

  });

  other_participant.each(function() {
    let participantValue = jQuery(this).val();

    if (participantValue === '') {
      jQuery(this).siblings('.inputerror').html('Sila isi Bilangan Peserta.').show();

      error++;
    }
    else {
      jQuery(this).siblings('.inputerror').html('').hide();
    }

  });

  return error;
}
