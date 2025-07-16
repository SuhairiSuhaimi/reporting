<?php

namespace Drupal\pf10\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportDetail extends FormBase {

  protected $requestStack;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->requestStack = $container->get('request_stack')->getCurrentRequest();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pf10_basic_info';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $connection = Database::getConnection();
    $session = $this->requestStack->getSession();

    $ip = $_SERVER['REMOTE_ADDR']; // Get user IP

    $iplocation = $this->ipLocation($ip);

    if ($id) {
      $last_page = $session->get('last_page', NULL);

      //$encoded = base64_encode($id);
      $encoded = $id;
      $reportid = base64_decode($id);
    }
    else {
      // Set a session variable
      $session->set('last_page', NULL);

      $last_page = NULL;

      $encoded = NULL;
      $reportid = '0';
    }

    $query = $connection->select('custom_pf10', 'pf')
      ->fields('pf')
      ->condition('report_id', $reportid);

    $record = $query->execute()->fetchAssoc();
    $result = (object) $record;

    $form['#attached']['library'][] = 'pf10/pf10';
    $form['#attached']['library'][] = 'pf10/numberjs';
    $form['#attached']['library'][] = 'pf10/flatpickr';
    $form['#attached']['library'][] = 'pf10/select2css';

    // ✅ Access the environment variable
    // Get the Google Maps API key from the environment variables
    $google_maps_api_key = $_ENV['GOOGLE_MAP_API_KEY'];
    $google_maps_url = $_ENV['GOOGLE_MAP_URL'];

    if (!$google_maps_api_key) {
      \Drupal::messenger()->addError('Google Maps API key not found in .env file.');
      return $form;
    }

    // Attach the Google Maps API script with the API key
    //$form['#attached']['library'][] = 'pf10/googlemapscript';

    $lat = $result->latitude ?? $iplocation->latitude;  //Latitude from ipLocation
    $lng = $result->longitude ?? $iplocation->longitude;  //Longitude from ipLocation

    // Add inline JavaScript to initialize the map
    $form['#attached']['drupalSettings']['googleMapsSetting'] = [
        'mapCenter' => [
            'lat' => $lat,
            'lng' => $lng,
        ],
    ];

    $form['#attached']['drupalSettings']['backdoor'] = [
        'key' => $google_maps_api_key,
    ];

    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#attributes' => [
          'src' => $google_maps_url . $google_maps_api_key,
          'async' => 'async',
          //'defer' => 'defer',  // Add the defer attribute here
        ],
      ],
      'custom_async_script',
    ];

    $form['#attached']['library'][] = 'pf10/googlemapjs';

    // First day of the current month
    $firstOfThisMonth = date('Y-m-01');

    $editableMonth = $_ENV['MAX_PF10_EDITABLE'];

    // Calculate the date 6 months from that first day
    $editableCondition = date('Y-m-d', strtotime("-$editableMonth months", strtotime($firstOfThisMonth)));

    if ($id && !$record) {
      $form['record_not_exist'] = [
        '#type' => 'container',
        '#prefix' => '<div id="modal" class="modal d-block">',
        '#suffix' => '</div>',
        '#attributes' => [
          'class' => ['modal-content d-block mx-auto top-50'],
        ],
      ];

      $form['record_not_exist']['message'] = [
        '#type' => 'markup',
        '#markup' => '
          <div class="text-center text-lg text-black-50 p-2">Rekod PF10 tak wujud.</div>
        ',
      ];

      $form['record_not_exist']['button'] = [
        '#type' => 'markup',
        '#markup' => '
          <div class="form-button form-type-button d-block text-center input-inline-block mt-3">
            <div class="button3" id="cancelPage">Kembali</div>
          </div>
        ',
      ];

      return $form;
    }
    elseif ($id && $record && $result->completed == '1') {
      // Compare both dates
      $isPF10editable = strtotime($result->submission_at) > strtotime($editableCondition);

      if (!$isPF10editable) {
        $required1 = FALSE;
        $disabled1 = TRUE;
        $required1_text = NULL;
      }
      else {
        $form['#attached']['library'][] = 'pf10/reportdetail';

        $required1 = TRUE;
        $disabled1 = FALSE;
        $required1_text = $required1 ? 'form-required' : NULL;
      }
    }
    else {
      $isPF10editable = TRUE;
      $form['#attached']['library'][] = 'pf10/reportdetail';

      $required1 = TRUE;
      $disabled1 = FALSE;
      $required1_text = $required1 ? 'form-required' : NULL;
    }

    if ($id && $record) {
      $form['report_id'] = [
        '#type' => 'hidden',
        '#value' => base64_encode($result->id),
      ];

      $form['reportid'] = [
        '#type' => 'hidden',
        '#value' => $encoded,
      ];
    }

    $form['latitude'] = [
      '#type' => 'hidden',
      '#value' => $result->latitude ?? '3.2523365',
    ];

    $form['longitude'] = [
      '#type' => 'hidden',
      '#value' => $result->longitude ?? '101.6728074',
    ];
    
    $form['title'] = [
      '#type' => 'markup',
      '#weight' => -2,
      '#markup' => '<h3 class="text-bold">PF10</h3>',
    ];

    if (!$isPF10editable) {
      $form['infoMessage'] = [
        '#type' => 'markup',
        '#weight' => 0,
        '#markup' => '<div id="infoMessage" class="message message-info">Pelaporan ini hanya untuk dibaca sahaja. Data tidak boleh diubah.</div>',
      ];
    }

    $form['successMessage'] = [
      '#type' => 'markup',
      '#weight' => 0,
      '#markup' => '<div id="successMessage" class="message message-status"></div>',
    ];

    $form['errorMessage'] = [
      '#type' => 'markup',
      '#weight' => 0,
      '#markup' => '<div id="errorMessage" class="message message-error"></div>',
    ];

    $form['sub-title1'] = [
      '#type' => 'markup',
      '#weight' => 1,
      '#markup' => '<h5 class="text-bold text-capitalize">Maklumat Asas</h5>',
    ];

    $form['section_one'] = [
      '#type' => 'container',
      '#weight' => 1,
      '#attributes' => [
        'class' => ['grid grid3-3-4-3'],
      ],
    ];

    $form['section_one']['timestamp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tarikh & Masa'),
      '#default_value' => $result->submission_at ? date('d M Y H:i', strtotime($result->submission_at)) : date('d M Y H:i'),
      '#required' => FALSE,
      '#attributes' => [
        'class' => ['field-timestamp'],
        'autocomplete' => 'off',
        'placeholder' => 'System auto generate.',
        'disabled' => TRUE,
      ],
    ];
    
    $form['section_one']['email'] = [
      '#type' => 'email',  //'textfield',
      '#title' => $this->t('Email:'),
      '#default_value' => $result->email ?? NULL,
      '#maxlength' => 150,
      '#required' => $required1,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-email"></i></div>',
      '#attributes' => [
        'class' => ['field-email'],
        'autocomplete' => 'off',
        'placeholder' => 'yourname@example.com',
        'disabled' => $disabled1,
      ],
    ];

    $form['section_one']['organization_radio_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['organization-radio-group radio-inline form-item'],
      ],
    ];

    $form['section_one']['organization_radio_group']['organization_label'] = [
      '#type' => 'markup',
      '#markup' => '<div for="edit-organization" class="form-lbl text-bold '. $required1_text .'">'. $this->t('Jenis Organisasi:') .'</div>',
    ];

    $form['section_one']['organization_radio_group']['organization'] = [
      '#type' => 'radios',
      '#title' => NULL,
      '#required' => $required1,
      '#default_value' => $result->organization ?? NULL,
      '#options' => [
        'KKM' => 'KKM',
        'NOT' => 'Bukan KKM',
      ],
      '#prefix' => '<div class="radio-group">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => [],  // This removes default 'form-item'
        'disabled' => $disabled1,
      ],
      '#wrapper_attributes' => [
        'class' => [],  // This removes default 'form-item'
      ],
      '#theme_wrappers' => [],  // disables the wrapper
    ];

    $form['section_two'] = [
      '#type' => 'container',
      '#weight' => 2,
      '#attributes' => [
        'class' => ['grid grid2-30-70'],
      ],
    ];

    $optionsState = $this->optionState();

    $form['section_two']['state_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Negeri:'),
      '#default_value' => $result->state_code ?? NULL,
      '#required' => $required1,
      '#options' => $optionsState,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-state-code"></i></div>',
      '#attributes' => [
        'class' => ['field-state-code input-state-code'],
        'disabled' => $disabled1,
      ],
      '#empty_option' => $this->t('- Sila pilih -'),
    ];

    $form['section_two']['facility_other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Program ini dibawah Fasiliti mana:'),
      '#default_value' => $result->facility_other ?? NULL,
      '#maxlength' => 100,
      '#states' => [
        'visible' => [
          ':input[name="organization"]' => ['value' => 'NOT'],
        ],
        'required' => [
          ':input[name="organization"]' => ['value' => 'NOT'],
        ],
      ],
      '#attributes' => [
        'class' => ['field-affacility-other w-100'],
        'autocomplete' => 'off',
        'disabled' => $disabled1,
      ],
    ];

    $form['section_two']['facility_container'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="organization"]' => ['value' => 'KKM'],
        ],
        'required' => [
          ':input[name="organization"]' => ['value' => 'KKM'],
        ],
      ],
      '#attributes' => [
        'class' => ['fieldset grid grid2-50-50'],
      ],
    ];

    $ptj = $result->ptj_code ?? NULL;
    $optionsFacility = $this->optionFacilityByPTJ($ptj);

    // Add 'Other' option at the end
    //$optionsFacility['other'] = 'Lain-lain';

    $form['section_two']['facility_container']['facility_code'] = [
      '#type' => 'select2',
      '#title' => $this->t('Program ini dibawah Fasiliti mana:'),
      '#default_value' => $result->facility_code ?? NULL,
      '#required' => $required1,
      '#options' => $optionsFacility,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-facility-code"></i></div>',
      '#states' => [
        'visible' => [
          ':input[name="organization"]' => ['value' => 'KKM'],
        ],
        'required' => [
          ':input[name="organization"]' => ['value' => 'KKM'],
        ],
      ],
      '#attributes' => [
        'class' => ['field-facility-code input-facility-code'],
        'disabled' => $disabled1,
      ],
      '#empty_option' => $this->t('- Sila pilih -'),
    ];

    $state = $result->state_code ?? NULL;
    $optionsPTJ = $this->optionPTJ($state);

    // Add 'Other' option at the end
    //$optionsPTJ['other'] = 'Lain-lain';

    $form['section_two']['facility_container']['ptj_code'] = [
      '#type' => 'select2',
      '#title' => $this->t('Program ini dibawah PTJ mana:'),
      '#default_value' => $result->ptj_code ?? NULL,
      '#required' => $required1,
      '#options' => $optionsPTJ,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-ptj-code"></i></div>',
      '#states' => [
        'visible' => [
          ':input[name="organization"]' => ['value' => 'KKM'],
        ],
        'required' => [
          ':input[name="organization"]' => ['value' => 'KKM'],
        ],
      ],
      '#attributes' => [
        'class' => ['field-ptj-code input-ptj-code'],
        'disabled' => $disabled1,
      ],
      '#empty_option' => $this->t('- Sila pilih -'),
    ];

    $form['section_three'] = [
      '#type' => 'container',
      '#weight' => 3,
      '#attributes' => [
        'class' => ['grid'],
      ],
    ];

    $form['section_three']['program_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nama Program:'),
      '#default_value' => $result->program_title ?? NULL,
      '#required' => $required1,
      '#maxlength' => 100,
      '#attributes' => [
        'class' => ['field-program-title w-90'],
        'autocomplete' => 'off',
        'disabled' => $disabled1,
      ],
    ];

    $form['section_four'] = [
      '#type' => 'container',
      '#weight' => 4,
      '#attributes' => [
        'class' => ['grid grid2-50-50'],
      ],
    ];

    $dateRange = $disabled1 ? NULL : 'date-range-picker';

    $form['section_four']['program_date'] = [
      '#type' => 'textfield',
      '#title' => NULL, //$this->t('Tarikh Program (Mula & Tamat):'),
      '#default_value' => $result->program_date ?? NULL, //2024-12-31 - 2025-01-10
      '#required' => $required1,
      '#prefix' => '<div class="form-item form-type-textfield">
                      <div for="edit-program-date" class="form-lbl text-bold '. $required1_text .'">'. $this->t('Tarikh Program (Mula & Tamat):') .'</div>',
      '#suffix' => '<i class="far fa-calendar-alt fp inline-block"></i></div>',
      '#attributes' => [
        'class' => ['fpicker inline-block w-90', $dateRange],
        'autocomplete' => 'off',
        'placeholder' => 'DD MM YYYY - DD MM YYYY',
        'disabled' => $disabled1,
      ],
      '#theme_wrappers' => [],  // disables the wrapper
    ];

    $form['section_four']['program_time_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['fieldset'],
      ],
    ];

    $form['section_four']['program_time_group']['program_time_open'] = [
      '#type' => 'markup',
      '#markup' => '<div class="form-item">
                      <div class="form-lbl text-bold '. $required1_text .'">'. $this->t('Masa Program (Mula & Tamat):') .'</div>
                        <div class="grid grid2-50-50 form-group">',
    ];

    $form['section_four']['program_time_group']['program_start_time'] = [
      '#type' => 'textfield',
      '#title' => NULL, //$this->t('Mula:'),
      '#default_value' => $result->program_start_time ?? NULL,
      '#required' => $required1,
      // '#prefix' => '<div class="form-item form-type-textfield">',
      // '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['timepicker-12'],
        'autocomplete' => 'off',
        'placeholder' => 'Mula',
        'disabled' => $disabled1,
      ],
      '#theme_wrappers' => [],  // disables the wrapper
    ];

    $form['section_four']['program_time_group']['program_end_time'] = [
      '#type' => 'textfield',
      '#title' => NULL, //$this->t('Tamat:'),
      '#default_value' => $result->program_end_time ?? NULL,
      '#required' => $required1,
      // '#prefix' => '<div class="form-item form-type-textfield">',
      // '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['timepicker-12'],
        'autocomplete' => 'off',
        'placeholder' => 'Tamat',
        'disabled' => $disabled1,
      ],
      '#theme_wrappers' => [],  // disables the wrapper
    ];

    $form['section_four']['program_time_group']['program_date_close'] = [
      '#type' => 'markup',
      '#markup' => '</div>
                  </div>',
    ];

    $form['section_five'] = [
      '#type' => 'container',
      '#weight' => 5,
      '#attributes' => [
        'class' => ['grid grid2-70-30'],
      ],
    ];

    $form['section_five']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lokasi Program:'),
      '#default_value' => $result->location ?? NULL,
      '#required' => $required1,
      '#maxlength' => 100,
      '#attributes' => [
        'class' => ['field-location w-90'],
        'autocomplete' => 'off',
        'disabled' => $disabled1,
      ],
    ];

    $form['section_five']['postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Poskod Lokasi:'),
      '#default_value' => $result->postcode ?? NULL,
      '#required' => $required1,
      '#maxlength' => 5,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-postcode"></i></div>',
      '#attributes' => [
        'class' => ['field-postcode number'],
        'autocomplete' => 'off',
        'disabled' => $disabled1,
      ],
      '#description' => $this->t('Hanya nombor sahaja dibenarkan.'),
    ];

    $form['section_fivemap'] = [
      '#type' => 'container',
      '#weight' => 5,
      '#attributes' => [
        'class' => ['grid mb-3'],
      ],
    ];

    $form['section_fivemap']['gmap'] = [
      '#type' => 'markup',
      '#markup' => '<div class="min-vh-100 w-90 border-1 border-dashed mx-auto" id="gmap"></div>',
    ];

    $form['section_six'] = [
      '#type' => 'container',
      '#weight' => 6,
      '#attributes' => [
        'class' => ['grid grid2-40-60'],
      ],
    ];

    $form['section_six']['location_type_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['location-type-group radio-inline form-item'],
      ],
    ];

    $form['section_six']['location_type_group']['location_type_label'] = [
      '#type' => 'markup',
      '#markup' => '<div for="edit-location-type" class="form-lbl text-bold '. $required1_text .'">'. $this->t('Jenis Kawasan:') .'</div>',
    ];

    $form['section_six']['location_type_group']['location_type'] = [
      '#type' => 'radios',
      '#title' => NULL,
      '#required' => $required1,
      '#default_value' => $result->location_type ?? NULL,
      '#options' => [
        'rural' => 'Luar Bandar',
        'city' => 'Bandar',
      ],
      '#prefix' => '<div class="radio-group">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => [],  // This removes default 'form-item'
        'disabled' => $disabled1,
      ],
      '#wrapper_attributes' => [
        'class' => [],  // This removes default 'form-item'
      ],
      '#theme_wrappers' => [],  // disables the wrapper
    ];

    $form['section_six']['program_method_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['program-method-group radio-inline form-item'],
      ],
    ];

    $form['section_six']['program_method_group']['program_method_label'] = [
      '#type' => 'markup',
      '#markup' => '<div for="edit-program-method" class="form-lbl text-bold '. $required1_text .'">'. $this->t('Kaedah Program:') .'</div>',
    ];

    $form['section_six']['program_method_group']['program_method'] = [
      '#type' => 'radios',
      '#title' => NULL,
      '#required' => $required1,
      '#default_value' => $result->program_method ?? NULL,
      '#options' => [
        'f2face' => 'Bersemuka',
        'online' => 'Dalam Talian',
        'hybrid' => 'Hibrid',
      ],
      '#prefix' => '<div class="radio-group">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => [],  // This removes default 'form-item'
        'disabled' => $disabled1,
      ],
      '#wrapper_attributes' => [
        'class' => [],  // This removes default 'form-item'
      ],
      '#theme_wrappers' => [],  // disables the wrapper
    ];

    $buttonid = ($id && $record) ? 'savePage1' : 'submitPage1';

    $form['section_button'] = [
      '#type' => 'container',
      '#weight' => 20,
      '#attributes' => [
        'class' => ['form-button form-type-button d-block text-center input-inline-block mt-5']
      ],
    ];

    $form['section_button']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Hantar'),
      '#attributes' => [
        'class' => ['d-none'],
        'id' => 'submit',
      ],
    ];

    if ($isPF10editable) {
      $form['section_button']['save'] = [
        '#type' => 'markup',
        '#markup' => '<div class="button2 float-end mx-1" id="'. $buttonid .'">Seterusnya<i class="fas fa-arrow-right ms-2"></i></div>',
      ];
    }
    else {
      $form['section_button']['save'] = [
        '#type' => 'markup',
        '#markup' => '<div class="button2 float-end mx-1" id="goTo" link="/pf10/activity-info/'. $encoded .'">Seterusnya<i class="fas fa-arrow-right ms-2"></i></div>',
      ];
    }

    $link = $last_page ?? NULL;

    $form['section_button']['cancel'] = [
      '#type' => 'markup',
      '#markup' => '<div class="button3 mx-1" id="cancelPage" link="'. $link .'">Kembali</div>',
    ];

    $form['success_modal'] = [
      '#type' => 'container',
      '#prefix' => '<div id="successModal" class="modal">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['modal-content'],
        'id' => 'success-modal',
      ],
    ];

    $form['success_modal']['message'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="text-center text-lg text-black-50 p-2" id="success-message"></div>
      ',
    ];

    $form['success_modal']['button'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="form-button form-type-button d-block text-center input-inline-block mt-3">
          <div class="button3" id="closeSuccess">Tutup</div>
        </div>
      ',
    ];

    $form['error_modal'] = [
      '#type' => 'container',
      '#prefix' => '<div id="errorModal" class="modal">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['modal-content'],
        'id' => 'error-modal',
      ],
    ];

    $form['error_modal']['message'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="text-center text-lg text-black-50 p-2" id="error-message"></div>
      ',
    ];

    $form['error_modal']['button'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="form-button form-type-button d-block text-center input-inline-block mt-3">
          <div class="button3" id="closeError">Tutup</div>
        </div>
      ',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    //$connection = Database::getConnection();
    $connection = \Drupal::database();

    $email = $form_state->getValue('email');
    $state = $form_state->getValue('state');

    try {
      $connection->insert('custom_pf10')
        ->fields([
          'timestamp' => date('Y-m-d H:i:s', time()),
          'email' => $email,
          'state' => $state,
          'created_at' => date('Y-m-d H:i:s', time()),
        ])
        ->execute();

      //$this->messenger()->addStatus($this->t('Submission successfully!'));
      \Drupal::messenger()->addMessage($this->t('Submission successfully.'));
      //\Drupal::messenger()->addStatus($this->t('Submission successfully! messenger'));
    }
    catch (\Exception $e) {
      //$this->messenger()->addError($this->t('Submission failed'));
      //\Drupal::messenger()->addMessage($this->t('Submission failed.'));
      \Drupal::messenger()->addError($this->t('Database insert failed: @msg', ['@msg' => $e->getMessage()]));
    }
  }

  private function optionState() {
    $master = \Drupal::service('pf10.master_data_service');
  
    $records = $master->getStates();

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->state_code ] = $rec->state;
    }

    return $lists;
  }

  private function optionFacilityByState($state_code = NULL) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getFacilityByState($state_code);

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->facility_code ] = ucwords($rec->facility);
    }

    return $lists;
  }

  private function optionFacilityByPTJ($ptj_code = NULL) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getFacilityByPTJ($ptj_code);

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->facility_code ] = ucwords($rec->facility);
    }

    return $lists;
  }

  private function optionPTJ($state_code = NULL) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getPTJByState($state_code);

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->ptj_code ] = ucwords($rec->ptj);
    }

    return $lists;
  }

  public function ipLocation($ip = NULL) {
    if ($ip == "::1") {
      $ip = "210.19.252.34";
    }
    
    //dd("ip Location");
    //$url = "https://ipinfo.io/$ip/json";
    //$url = "http://ip-api.com/json/$ip";
    $url = "https://get.geojs.io/v1/ip/geo/$ip.json";

    $details = json_decode(file_get_contents($url));

    if ($details) {
      return (object) [
        'country' => $details->country ?? NULL,
        'country_code' => $details->country_code ?? NULL,
        // 'region_name' => $details['regionName'] ?? NULL,
        // 'city' => $details['country'] ?? NULL,
        // 'time_zone' => $details['timezone'] ?? NULL,
        'latitude' => $details->lat ?? NULL,
        'longitude' => $details->lon ?? NULL,
      ];
    }
    else {
      return NULL;
    }

  }

}