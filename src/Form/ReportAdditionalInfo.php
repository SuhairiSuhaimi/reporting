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

class ReportAdditionalInfo extends FormBase {

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
    return 'pf10_additional_info';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $connection = Database::getConnection();

    $encoded = $id;
    $reportid = base64_decode($id);

    $session = $this->requestStack->getSession();
    $last_page = $session->get('last_page', NULL);

    $query = $connection->select('custom_pf10', 'pf')
      ->fields('pf')
      ->condition('report_id', $reportid);

    $record = $query->execute()->fetchAssoc();
    $result = (object) $record;

    $form['#attached']['library'][] = 'pf10/pf10';
    //$form['#attached']['library'][] = 'pf10/numberjs';
    $form['#attached']['library'][] = 'pf10/decimaljs';
    $form['#attached']['library'][] = 'pf10/reportadditional';
    //$form['#attached']['library'][] = 'pf10/select2';
    $form['#attached']['library'][] = 'pf10/select2css';

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
        $required1 = TRUE;
        $disabled1 = FALSE;
        $required1_text = $required1 ? 'form-required' : NULL;
      }
    }
    else {
      $isPF10editable = TRUE;

      $required1 = TRUE;
      $disabled1 = FALSE;
      $required1_text = $required1 ? 'form-required' : NULL;
    }

    // Get selected state_pick
    $selected_state = $result->state_code;  //$form_state->getValue('state_code');

    $form['report_id'] = [
      '#type' => 'hidden',
      '#value' => base64_encode($result->id),
    ];

    $form['reportid'] = [
      '#type' => 'hidden',
      '#value' => $encoded,
    ];

    $form['state_code'] = [
      '#type' => 'hidden',
      '#value' => $result->state_code,
    ];

    $form['editable'] = [
      '#type' => 'hidden',
      '#value' => $isPF10editable ? 'TRUE' : 'FALSE',
      '#attributes' => [
        'name' => FALSE,
        'id' => 'editable',
      ],
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

    $form['sub-title3'] = [
      '#type' => 'markup',
      '#weight' => 9,
      '#markup' => '<h5 class="text-bold text-capitalize mb-3">Maklumat Tambahan</h5>',
    ];

    $form['section_nine'] = [
      '#type' => 'container',
      '#weight' => 9,
      '#attributes' => [
        'class' => ['grid grid2-35-65'],
      ],
    ];

    $form['section_nine']['initiative_radio_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['initiative-radio-group radio-inline form-item'],
      ],
    ];

    $form['section_nine']['initiative_radio_group']['initiative_label'] = [
      '#type' => 'markup',
      '#markup' => '<div for="edit-initiative" class="form-lbl text-bold '. $required1_text .'">'. $this->t('Adakah program ini inisiatif Duta?') .'</div>',
    ];

    $form['section_nine']['initiative_radio_group']['initiative'] = [
      '#type' => 'radios',
      '#title' => NULL,
      '#required' => $required1,
      '#default_value' => $result->initiative ?? NULL,
      '#options' => [
        'Y' => 'Ya',
        'N' => 'Tidak',
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

    $form['section_nine']['duta_list_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['duta-list-group form-item grid'],
      ],
    ];

    $form['section_nine']['duta_list_group']['duta_list_label'] = [
      '#type' => 'markup',
      '#markup' => '<div for="section-duta-party" class="form-lbl text-bold">'. $this->t('Senaraikan nombor ID Duta:') .'</div>',
    ];

    $form['section_nine']['duta_list_group']['duta_list'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['grid grid2-50-50 mb-3'],
        'id' => 'section-duta-party',
      ],
    ];

    $form['section_ten'] = [
      '#type' => 'container',
      '#weight' => 10,
      '#attributes' => [
        'class' => ['grid grid2-50-50'],
      ],
    ];

    $form['section_ten']['budget_expense'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Jumlah peruntukan yang dibelanjakan bagi program ini (RM):'),
      '#default_value' => $result->budget_expense ?? NULL,
      '#maxlength' => 11,
      '#required' => $required1,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-address-1"></i></div>',
      '#attributes' => [
        'class' => ['field-budget-expense decimal text-end'],
        'autocomplete' => 'off',
        'placeholder' => '0.00',
        'disabled' => $disabled1,
      ],
    ];

    $form['section_ten']['collaboration_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['fieldset'],
      ],
    ];

    $query2 = $connection->select('custom_collaborator', 'c')
      ->fields('c')
      ->condition('report_id', $result->id);

    $collaboration = $query2->execute()->fetchAssoc();
    $collaborator = (object) $collaboration;

    $optionsColaboration = $this->optionCollaboration();

    // Add 'Other' option at the end
    $optionsColaboration['other'] = 'Lain-lain';

    $collaborator_code = $collaborator->collaboration_other ? 'other' : $collaborator->collaborator_code;

    $form['section_ten']['collaboration_group']['collaboration'] = [
      '#type' => 'select2',
      '#title' => $this->t('Nama Kementerian/NGO yang berkolaborasi menjayakan program'),
      '#default_value' => $collaborator_code ?? NULL,
      '#required' => FALSE,
      '#options' => $optionsColaboration,
      '#attributes' => [
        'class' => ['field-collaboration input-collaboration'],
        'disabled' => $disabled1,
      ],
      '#empty_option' => $this->t('- Sila pilih -'),
    ];

    $form['section_ten']['collaboration_group']['collaboration_other'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lain-lain Kementerian/NGO:'),
      '#default_value' => $collaborator->collaboration_other ?? NULL,
      '#maxlength' => 50,
      '#states' => [
        'visible' => [
          ':input[name="collaboration"]' => ['value' => 'other'],
        ],
        'required' => [
          ':input[name="collaboration"]' => ['value' => 'other'],
        ],
      ],
      '#attributes' => [
        'class' => ['field-collaboration-other'],
        'autocomplete' => 'off',
      ],
    ];

    $optionsPAWE = $this->optionPAWE($selected_state);

    // Add 'Other' option at the end
    //$optionsPAWE['other'] = 'Lain-lain';

    $form['section_ten']['collaboration_group']['pawe_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Kolaborasi PAWE:'),
      '#default_value' => $collaborator->pawe_code ?? NULL,
      //'#required' => TRUE,
      '#options' => $optionsPAWE,
      '#states' => [
        'visible' => [
          ':input[name="collaboration"]' => ['value' => 'PAWE'],
        ],
        'required' => [
          ':input[name="collaboration"]' => ['value' => 'PAWE'],
        ],
      ],
      '#attributes' => [
        'class' => ['field-pawe-code input-pawe-code'],
        'disabled' => $disabled1,
      ],
      '#empty_option' => $this->t('- Sila pilih -'),
    ];

    $form['section_eleven'] = [
      '#type' => 'container',
      '#weight' => 11,
      '#attributes' => [
        'class' => ['grid'],
      ],
    ];

    $query3 = $connection->select('custom_facility_involvement', 'fi')
      ->fields('fi', ['facility_code'])
      ->condition('report_id', $result->id);

    $facility = $query3->execute()->fetchAll();
    //$facilityInvolve = (object) $facility;

    if ($facility) {
      // Convert to array of facility_code strings
      $facilityInvolve = array_map(function($item) {
          return $item->facility_code;
      }, $facility);
    }
    else {
      $facilityInvolve = [];
    }

    $optionsInvolve = $this->optionFacilityByState($selected_state);

    $form['section_eleven']['facility_involvement'] = [
      '#type' => 'select2',
      '#title' => $this->t('Nama fasiliti kesihatan lain yang terlibat dalam program ini:'),
      '#default_value' => $facilityInvolve, //$result->facility_involvement ?? NULL,
      '#required' => FALSE,
      '#options' => $optionsInvolve,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-facility-code"></i></div>',
      '#multiple' => TRUE,
      '#size' => 5, // How many items to show at once
      '#attributes' => [
        'class' => ['field-facility-involvement input-facility-involvement'],
        'disabled' => $disabled1,
      ],
      '#empty_option' => $this->t('- Sila pilih -'),
      '#description' => $this->t('Maksima 3 pilihan.'),
    ];

    $form['section_eleven']['remark'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Catatan:'),
      //'#description' => $this->t('Catatan tambahan.'),
      '#default_value' => $result->remark ?? NULL,
      '#required' => FALSE,
      '#rows' => 2,
      '#resizable' => 'NONE',
      '#maxlength' => 200,
      //'#prefix' => '<div class="form-item form-type-textfield">',
      //'#suffix' => '</div>',
      '#attributes' => [
        'class' => ['field-remark'],
        'autocomplete' => 'off',
        'style' => 'resize: none;', // disables resizing
        'disabled' => $disabled1,
      ],
    ];

    $form['sub-title4'] = [
      '#type' => 'markup',
      '#weight' => 9,
      '#markup' => '<h5 class="text-bold text-capitalize my-3 mt-5">Maklumat Pelapor dan Gambar Aktiviti</h5>',
    ];

    $form['section_twelve'] = [
      '#type' => 'container',
      '#weight' => 12,
      '#attributes' => [
        'class' => ['grid'],
      ],
    ];

    $form['section_twelve']['rph_no'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No. RPh:'),
      '#default_value' => $result->rph_no ?? NULL,
      //'#description' => $this->t(''),
      '#maxlength' => 50,
      '#required' => $required1,
      //'#prefix' => '<div class="input-inline-block input-icon">',
      //'#suffix' => '<i class="fas fa-edit" rel="edit-email"></i></div>',
      '#attributes' => [
        'class' => ['field-rph-no'],
        'autocomplete' => 'off',
        'disabled' => $disabled1,
      ],
    ];

    $form['section_twelve']['image_upload_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['image-upload-wrapper form-item grid mt-1'],
      ],
    ];

    $form['section_twelve']['image_upload_group']['image_upload_label'] = [
      '#type' => 'markup',
      '#markup' => '<div class="form-lbl text-bold mb-0">'. $this->t('Muat naik gambar aktiviti (Maksima 3 gambar sahaja):') .'</div>
                    <div class="description"><i>'. $this->t('(sokong format *.jpg, *.jpeg, *.png, dan saiz imej tidak melebihi 5mb)') .'</i></div>',
    ];

    $form['section_twelve']['image_upload_group']['image_upload'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['image-upload-group grid grid2-50-50 mt-3'],
        'id' => 'section-images-upload',
      ],
    ];

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

    if ($result->completed && $isPF10editable) {
      $form['section_button']['save'] = [
        '#type' => 'markup',
        '#markup' => '<div class="button float-end mx-1" id="savePage3">Simpan</div>',
      ];

    }
    elseif (!$result->completed && $isPF10editable) {
      $form['section_button']['save'] = [
        '#type' => 'markup',
        '#markup' => '<div class="button float-end mx-1" id="savePage3">Hantar</div>',
      ];
    }

    $form['section_button']['back'] = [
      '#type' => 'markup',
      '#markup' => '<div class="button2 float-start mx-1" id="backPage3"><i class="fas fa-arrow-left me-2"></i>Sebelumnya</div>',
    ];

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

  private function optionCollaboration() {
    $master = \Drupal::service('pf10.master_data_service');
  
    $records = $master->getCollaboration();

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->collab_code ] = $rec->collab_name;
    }

    return $lists;
  }

  private function optionPAWE($state_code = NULL) {
    $master = \Drupal::service('pf10.master_data_service');
  
    $records = $master->getPawe($state_code);

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->id ] = $rec->pawe_name;
    }

    return $lists;
  }


}