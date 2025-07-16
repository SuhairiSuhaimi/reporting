<?php

namespace Drupal\pf10\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportActivity extends FormBase {

  protected $request;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pf10_activity_info';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $connection = Database::getConnection();

    $encoded = $id;
    $reportid = base64_decode($id);

    $query = $connection->select('custom_pf10', 'pf')
      ->fields('pf')
      ->condition('report_id', $reportid);

    $record = $query->execute()->fetchAssoc();
    $result = (object) $record;

    $form['#attached']['library'][] = 'pf10/pf10';
    $form['#attached']['library'][] = 'pf10/numberjs';
    $form['#attached']['library'][] = 'pf10/flatpickr';
    $form['#attached']['library'][] = 'pf10/reportactivity';
    $form['#attached']['library'][] = 'pf10/select2';
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

    $form['sub-title2'] = [
      '#type' => 'markup',
      '#weight' => 7,
      '#markup' => '<h5 class="text-bold text-capitalize mb-3">Maklumat Aktiviti</h5>',
    ];

    $form['section_seven'] = [
      '#type' => 'container',
      '#weight' => 7,
      '#attributes' => [
        'class' => ['grid w-100'],
        'id' => 'activity-info',
      ],
    ];

    $form['section_eight'] = [
      '#type' => 'container',
      '#weight' => 8,
      '#attributes' => [
        'class' => ['form-button form-type-button d-block text-start input-inline-block mt-3']
      ],
    ];

    if ($isPF10editable) {
      $form['section_eight']['add_activiti'] = [
        '#type' => 'markup',
        '#markup' => '<div class="button3" id="addActivity"><i class="fas fa-plus me-2"></i>Tambah Aktiviti</div>',
        '#ajax' => [
          'wrapper' => 'activity-info',
          'event' => 'click',
        ],
      ];
    }

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
        '#markup' => '<div class="button2 float-end mx-1" id="savePage2">Seterusnya<i class="fas fa-arrow-right ms-2"></i></div>',
      ];
    }
    else {
      $form['section_button']['save'] = [
        '#type' => 'markup',
        '#markup' => '<div class="button2 float-end mx-1" id="goTo" link="/pf10/add-info/'. $encoded .'">Seterusnya<i class="fas fa-arrow-right ms-2"></i></div>',
      ];
    }


    $form['section_button']['back'] = [
      '#type' => 'markup',
      '#markup' => '<div class="button2 float-start mx-1" id="backPage2"><i class="fas fa-arrow-left me-2"></i>Sebelumnya</div>',
    ];

    // $form['section_button']['cancel'] = [
    //   '#type' => 'markup',
    //   '#markup' => '<div class="button3 mx-1" id="cancelPage">Kembali</div>',
    // ];

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

  private function optionPAWE() {
    $master = \Drupal::service('pf10.master_data_service');
  
    $records = $master->getPawe();

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->id ] = $rec->pawe_name;
    }

    return $lists;
  }


}