<?php

namespace Drupal\pf10\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;

class pf10Controller extends ControllerBase {

  protected $currentUser;
  protected $requestStack;

  public function __construct(AccountProxyInterface $current_user, RequestStack $request_stack) {
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('request_stack'),
    );
  }

  public function insertDetail(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    // Get roles as an array
    $roles = $user->getRoles();

    // To retrieve data from table User
    $account = \Drupal\user\Entity\User::load( $user_id );
    //$user_state = $account->get('field_state')->value;
    $user_state = NULL;

    $connection = Database::getConnection();
    $file_system = \Drupal::service('file_system');

    $fields = [];
    $statecode = NULL;

    if ($request->request->has('email') ) {
      $fields['email'] = $request->get('email');
    }

    if ($request->request->has('organization') ) {
      $fields['organization'] = $request->get('organization');
    }

    if ($request->request->has('state_code') ) {
      $fields['state_code'] = $request->get('state_code');
      $statecode = $request->get('state_code');
    }

    if ($request->request->has('facility_code') ) {
      if ($request->get('organization') == 'KKM') {
        $fields['facility_code'] = $request->get('facility_code');
        $fields['facility'] = $this->getFacilityName($request->get('facility_code') );
      }
      else {
        $fields['facility_code'] = NULL;
        $fields['facility'] = NULL;
      }
    }

    if ($request->request->has('ptj_code') ) {
      if ($request->get('organization') == 'KKM') {
        $fields['ptj_code'] = $request->get('ptj_code');
        $fields['ptj'] = $this->getPTJName($request->get('ptj_code') );
      }
      else {
        $fields['ptj_code'] = NULL;
        $fields['ptj'] = NULL;
      }
    }

    if ($request->request->has('facility_other') ) {
      if ($request->get('organization') == 'NOT') {
        $fields['facility_other'] = $request->get('facility_other');
      }
      else {
        $fields['facility_other'] = NULL;
      }
    }

    if ($request->request->has('program_title') ) {
      $fields['program_title'] = $request->get('program_title');
    }

    if ($request->request->has('program_date') ) {
      $fields['program_date'] = $request->get('program_date');

      // Split the string into two dates using ' - ' as the delimiter
      $dates = explode(' - ', $request->get('program_date') );

      $fields['program_start_date'] = $dates[0];
      $fields['program_end_date'] = $date[1] ?? $dates[0];
    }

    if ($request->request->has('program_start_time') ) {
      $fields['program_start_time'] = $request->get('program_start_time');
    }

    if ($request->request->has('program_end_time') ) {
      $fields['program_end_time'] = $request->get('program_end_time');
    }

    if ($request->request->has('location') ) {
      $fields['location'] = $request->get('location');
    }

    if ($request->request->has('postcode') ) {
      $fields['postcode'] = $request->get('postcode');
    }

    if ($request->request->has('latitude') && $request->request->has('longitude') ) {
      $fields['latitude'] = $request->get('latitude');
      $fields['longitude'] = $request->get('longitude');
    }

    if ($request->request->has('location_type') ) {
      $fields['location_type'] = $request->get('location_type');
    }

    if ($request->request->has('program_method') ) {
      $fields['program_method'] = $request->get('program_method');
    }

    $reportid = $statecode . date('YmdHi', time() );

    $fields['report_id'] = $reportid;
    $fields['completed'] = 0;
    $fields['created_at'] = date('Y-m-d H:i:s', time() );

    try {
      $connection->insert('custom_pf10')
        ->fields($fields)
        ->execute();

      $status = '01';
      $message = '';

      //\Drupal::logger('pf10')->notice('PF10 draft created.');
      \Drupal::messenger()->addStatus('PF10 draft has been created. '. $reportid);
    }
    catch (DatabaseExceptionWrapper $e) {
      $status = 'DATABASE';
      $message = 'System mengalami gangguan teknikal.<br/>Sila cuba lagi.';
      $reportid = NULL;

      \Drupal::logger('insert_pf10')->error('Database insert failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError('An error occurred while inserting data.');
    }

    return new JsonResponse([
      'status' => $status,
      'message' => $message,
      'reportid' => $reportid ? base64_encode($reportid) : NULL,
    ]);
  }

  public function updateDetail(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    // Get roles as an array
    $roles = $user->getRoles();

    // To retrieve data from table User
    $account = \Drupal\user\Entity\User::load( $user_id );
    //$user_state = $account->get('field_state')->value;
    $user_state = NULL;

    $connection = Database::getConnection();
    $file_system = \Drupal::service('file_system');

    $fields = [];
    $statecode = NULL;

    $encoded = $request->get('reportid');
    $reportid = base64_decode($request->get('reportid') );
    $report_id = base64_decode($request->get('report_id') );

    $query = $connection->select('custom_pf10', 'pf')
      ->fields('pf')
      ->condition('id', $report_id)
      ->condition('report_id', $reportid);

    $record = $query->execute()->fetchAssoc();
    $result = (object) $record;

    if (!$record) {
      $status = 'CREDENTIAL';
      $message = 'System mengesan berlaku perlanggaran kredentasi maklumat.<br/>Sila cuba lagi.';
      $message .= '<br/>Rekod PF10 '. $result->name .' gagal dikemaskini.';

      \Drupal::logger('update_pf10')->error('System detects information credential violation.');
      \Drupal::messenger()->addError('Rekod PF10 '. $result->name .' gagal dikemaskini.');

      return new JsonResponse([
        'status' => $status,
        'message' => $message,
        'reportid' => $encoded,
      ]);
    }

    if ($request->request->has('email') ) {
      $fields['email'] = $request->get('email');
    }

    if ($request->request->has('organization') ) {
      $fields['organization'] = $request->get('organization');
    }

    if ($request->request->has('state_code') ) {
      $fields['state_code'] = $request->get('state_code');
      $statecode = $request->get('state_code');
    }

    if ($request->request->has('facility_code') ) {
      if ($request->get('organization') == 'KKM') {
        $fields['facility_code'] = $request->get('facility_code');
        $fields['facility'] = $this->getFacilityName($request->get('facility_code') );
      }
      else {
        $fields['facility_code'] = NULL;
        $fields['facility'] = NULL;
      }
    }

    if ($request->request->has('ptj_code') ) {
      if ($request->get('organization') == 'KKM') {
        $fields['ptj_code'] = $request->get('ptj_code');
        $fields['ptj'] = $this->getPTJName($request->get('ptj_code') );
      }
      else {
        $fields['ptj_code'] = NULL;
        $fields['ptj'] = NULL;
      }
    }

    if ($request->request->has('facility_other') ) {
      if ($request->get('organization') == 'NOT') {
        $fields['facility_other'] = $request->get('facility_other');
      }
      else {
        $fields['facility_other'] = NULL;
      }
    }

    if ($request->request->has('program_title') ) {
      $fields['program_title'] = $request->get('program_title');
    }

    if ($request->request->has('program_date') ) {
      $fields['program_date'] = $request->get('program_date');

      // Split the string into two dates using ' - ' as the delimiter
      $dates = explode(' - ', $request->get('program_date') );

      $fields['program_start_date'] = $dates[0];
      $fields['program_end_date'] = isset($dates[1]) ? $dates[1] : NULL;
    }

    if ($request->request->has('program_start_time') ) {
      $fields['program_start_time'] = $request->get('program_start_time');
    }

    if ($request->request->has('program_end_time') ) {
      $fields['program_end_time'] = $request->get('program_end_time');
    }

    if ($request->request->has('location') ) {
      $fields['location'] = $request->get('location');
    }

    if ($request->request->has('postcode') ) {
      $fields['postcode'] = $request->get('postcode');
    }

    if ($request->request->has('latitude') && $request->request->has('longitude') ) {
      $fields['latitude'] = $request->get('latitude');
      $fields['longitude'] = $request->get('longitude');
    }

    if ($request->request->has('location_type') ) {
      $fields['location_type'] = $request->get('location_type');
    }

    if ($request->request->has('program_method') ) {
      $fields['program_method'] = $request->get('program_method');
    }

    $fields['updated_at'] = date('Y-m-d H:i:s', time() );

    try {
      $update = $connection->update('custom_pf10')
        ->fields($fields)
        ->condition('id', $report_id)
        ->execute();

      if ($result->completed) {
        $status = '01';
        $message = 'Laporan PF10 '. $result->report_id .' telah dikemaskini.';
        $logger_msg = 'Record PF10 has been updated.';
        $messender_msg = 'Record PF10 has been updated.';
      }
      else {
        $status = '01';
        $message = 'Draft Laporan PF10 '. $result->report_id .' telah dikemaskini.';
        $logger_msg = 'Record PF10 draft has been updated.';
        $messender_msg = 'Record PF10 draft has been updated.';
      }

      //\Drupal::logger('pf10')->notice($logger_msg);
      \Drupal::messenger()->addStatus($messender_msg);
    }
    catch (DatabaseExceptionWrapper $e) {
      $status = 'DATABASE';
      $message = 'System mengalami gangguan teknikal.<br/>Sila cuba lagi.';

      \Drupal::logger('update_pf10')->error('Database insert failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError('An error occurred while inserting data.');
    }

    return new JsonResponse([
      'status' => $status,
      'message' => $message,
      'reportid' => $encoded,
    ]);
  }

  public function ajaxNewActivity(Request $request) {
    if ($request->request->has('sequence') ) {
      $seq = $request->get('sequence');
    }
    else {
      return new JsonResponse([
        'status' => '02',
        'html' => NULL,
        'message' => 'System mengalami ralat.',
      ]);
    }

    $initial = '';

    if ($seq == 1) {
      $reportid = base64_decode($request->get('reportid') );
      $report_id = base64_decode($request->get('report_id') );

      $initial = $this->initialActivityContent($report_id, $reportid);
    }

    // title and delete button
    $html = '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
    $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'"></span>';
    $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

    $html .= $this->initialActivityType($seq, NULL);

    // fieldset content
    $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';
    $html .= '</div>'; // fieldset-activity close

    $endHtml = empty($initial) ? $html : $initial;

    return new JsonResponse([
      'status' => '01',
      'html' => $endHtml,
      'message' => NULL,
    ]);
  }

  function initialActivityType($seq, $default = NULL) {

    $optionsActivity = [
      '' => $this->t('- Sila pilih -'),
      'exhibit' => $this->t('Pameran'),
      'speech' => $this->t('Ceramah'),
      'interview' => $this->t('Wawancara TV/Radio'),
      'social-media' => $this->t('Aktiviti di Media Sosial'),
      'picc' => $this->t('Pharmacy Integrated Community Care (PICC)'),
      'training' => $this->t('Training of Trainers (TOT)'),
      'publisher' => $this->t('Penerbitan Artikel'),
      'meeting' => $this->t('Mesyuarat DUTA Kenali Ubat Anda'),
      'other' => $this->t('Lain-lain'),
    ];

    $disabled = $default ? 'disabled' : '';

    // label and selectbox
    $html = '<div class="form-item form-type-select">';
    $html .= '<div class="form-lbl text-bold form-required">Jenis aktiviti yang dijalankan:</div>';
    $html .= '<select class="field-activity-type activity-type form-select required" id="activity_type_'. $seq .'" name="activity_type[]" rel="'. $seq .'" '. $disabled .'>';

    foreach ($optionsActivity as $key => $value) {
      $selected = $key == $default ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    return $html;
  }

  function initialActivityContent($report_id, $reportid) {
    $connection = Database::getConnection();

    $query = $connection->select('custom_pf10', 'pf')
      ->fields('pf')
      ->condition('id', $report_id)
      ->condition('report_id', $reportid);

    $record = $query->execute()->fetchAssoc();
    $result = (object) $record;

    if (!$record) {
      return NULL;
    }

    $seq = 1;
    $html = '';

    $query1 = $connection->select('custom_exhibit', 'exhibit')
      ->fields('exhibit')
      ->condition('report_id', $report_id);

    $checkExhibit = $query1->execute()->fetchAssoc();
    $resultExhibit = (object) $checkExhibit;

    if ($checkExhibit) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Pameran</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'exhibit');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialExhibit($resultExhibit);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query2 = $connection->select('custom_speech_setting', 'speech_setting')
      ->fields('speech_setting')
      ->condition('report_id', $report_id);

    $checkSpeechSetting = $query2->execute()->fetchAssoc();
    $resultSpeechSetting = (object) $checkSpeechSetting;

    $query3 = $connection->select('custom_speech', 'speech')
      ->fields('speech')
      ->condition('report_id', $report_id);

    $checkSpeech = $query3->execute()->fetchAll();
    $resultSpeech = (object) $checkSpeech;

    if ($checkSpeechSetting && $checkSpeech) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Ceramah</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'speech');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialSpeech($resultSpeechSetting, $resultSpeech);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query4 = $connection->select('custom_interview', 'interview')
      ->fields('interview')
      ->condition('report_id', $report_id);

    $checkInterview = $query4->execute()->fetchAssoc();
    $resultInterview = (object) $checkInterview;

    if ($checkInterview) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Wawancara TV/Radio</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'interview');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialInterview($resultInterview);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query5 = $connection->select('custom_social_media', 'social')
      ->fields('social')
      ->condition('report_id', $report_id);

    $checkSocialMedia = $query5->execute()->fetchAll();
    $resultSocialMedia = (object) $checkSocialMedia;

    if ($checkSocialMedia) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Aktiviti di Media Sosial</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'social-media');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialSocialMedia($resultSocialMedia);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query6 = $connection->select('custom_picc_setting', 'picc')
      ->fields('picc')
      ->condition('report_id', $report_id);

    $checkPICCSetting = $query6->execute()->fetchAssoc();
    $resultPICCSetting = (object) $checkPICCSetting;

    if ($checkPICCSetting) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Pharmacy Integrated Community Care (PICC)</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'picc');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialPICCSetting($result->state_code, $resultPICCSetting);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query7 = $connection->select('custom_training', 'training')
      ->fields('training')
      ->condition('report_id', $report_id);

    $checkTraining = $query7->execute()->fetchAssoc();
    $resultTraining = (object) $checkTraining;

    if ($checkTraining) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Training of Trainers (TOT)</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'training');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialTraining($resultTraining);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query8 = $connection->select('custom_article', 'publisher')
      ->fields('publisher')
      ->condition('report_id', $report_id);

    $checkArticlePublished = $query8->execute()->fetchAssoc();
    $resultArticle = (object) $checkArticlePublished;

    if ($checkArticlePublished) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Penerbitan Artikel</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'publisher');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialArticle($resultArticle);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query9 = $connection->select('custom_meeting', 'meeting')
      ->fields('meeting')
      ->condition('report_id', $report_id);

    $checkMeeting = $query9->execute()->fetchAssoc();
    $resultMeeting = (object) $checkMeeting;

    if ($checkMeeting) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Mesyuarat DUTA Kenali Ubat Anda</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'meeting');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialMeeting($resultMeeting);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    $query10 = $connection->select('custom_other', 'other')
      ->fields('other')
      ->condition('report_id', $report_id);

    $checkOther = $query10->execute()->fetchAll();
    $resultOther = (object) $checkOther;

    if ($checkOther) {
      // title and delete button
      $html .= '<div class="fieldset-activity form-wrapper" id="fieldset_'. $seq .'">';
      $html .= '<h5 class="text-bold text-capitalize">Aktiviti <span id="activity_title_'. $seq .'">Lain-lain</span>';
      $html .= '<div class="button deleteFieldset position-absolute end-0 text-xs" rel="'. $seq .'">X</div></h5>';

      $html .= $this->initialActivityType($seq, 'other');

      // fieldset content
      $html .= '<div class="fieldset_content" id="fieldset_content_'. $seq .'"></div>';

      $html .= $this->initialOther($resultOther);

      $html .= '</div>'; // fieldset-activity close

      $seq++;
    }

    return $html;
  }

  public function ajaxActivityContent(Request $request) {
    if ($request->request->has('activity_type') ) {
      $activity = $request->get('activity_type');
    }
    elseif ($request->query->has('activity_type') ) {
      $activity = $request->get('activity_type');
    }
    else {
      return new JsonResponse([
        'status' => '02',
        'html' => NULL,
        'message' => 'System mengalami ralat.',
      ]);
    }

    $seq = $request->get('activity_seq') ? : 0;
    $state_code = $request->get('state_code') ? : NULL;

    $html = '';

    switch($activity) {
      case 'exhibit':
        $html .= $this->initialExhibit(NULL);
        break;


      case 'speech':
        $html .= $this->initialSpeech(NULL, NULL);
        break;


      case 'interview':
        $html .= $this->initialInterview(NULL);
        break;


      case 'social-media':
        $html .= $this->initialSocialMedia(NULL);
        break;


      case 'picc':
        $html .= $this->initialPICCSetting($state_code, NULL);
        break;


      case 'training':
        $html .= $this->initialTraining(NULL);
        break;


      case 'publisher':
        $html .= $this->initialArticle(NULL);
        break;


      case 'meeting':
        $html .= $this->initialMeeting(NULL);
        break;


      case 'other':
        $html .= $this->initialOther(NULL);
        break;


      default:
        $html = 'Aktiviti Tidak Tersenarai';
    };

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
      'message' => NULL,
    ]);
  }

  function initialExhibit($result = NULL) {
    $booth = $result->exhibit_booth ?? NULL;
    $participant = $result->exhibit_participant ?? NULL;
    $target = $result->exhibit_target ?? NULL;
    $other = $result->exhibit_target_other ?? NULL;

    $html = '<div class="grid grid3-3-3-4 form-wrapper setfield-exhibit">';

    $html .= '<div class="form-item form-type-textfield form-item-exhibit-booth">';
    $html .= '<div class="form-lbl text-bold form-required">Bilangan gerai(booth) pameran berkaitan Kenali Ubat Anda:</div>';
    $html .= '<input type="text" class="field-exhibit-booth form-text required number exhibit-booth" name="exhibit_booth" value="'. $booth .'" maxlength="2" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-exhibit-participant">';
    $html .= '<div class="form-lbl text-bold form-required">Jumlah peserta untuk pameran:</div>';
    $html .= '<input type="text" class="field-exhibit-participant form-text required number exhibit-participant" name="exhibit_participant" value="'. $participant .'" maxlength="5" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-wrapper exhibit-target-wrapper">';
    $html .= '<div class="form-item form-type-select form-item-exhibit-target">';
    $html .= '<div class="form-lbl text-bold form-required">Golongan sasaran:</div>';
    $html .= '<select class="field-exhibit-target exhibit-target form-select required" name="exhibit_target">';

    $optionsTarget = [
      '' => $this->t('- Sila pilih -'),
      'orang_awam' => $this->t('Orang Awam'),
      'pelajar' => $this->t('Murid dan Pelajar'),
      'other' => $this->t('Lain-lain'),
    ];

    foreach ($optionsTarget as $key => $value) {
      $selected = $key == $target ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $show_other = $other ? '' : 'style="display: none"';

    $html .= '<div class="form-item form-type-textfield form-item-exhibit-target-other" '. $show_other .'>';
    $html .= '<div class="form-lbl text-bold form-required">Lain-lain golongan sasaran:</div>';
    $html .= '<input type="text" class="field-exhibit-target-other form-text required exhibit-target-other w-90" name="exhibit_target_other" value="'. $other .'" maxlength="50" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';
    $html .= '</div>';  //exhibit-target-wrapper

    $html .= '</div>';  //setfield-exhibit

    return $html;
  }

  function initialSpeech($result = NULL, $results = NULL) {
    $target = $result->speech_target ?? NULL;
    $other = $result->speech_target_other ?? NULL;
    
    $html = '<div class="grid grid3-6-2-2 form-wrapper setfield-speech-label d-md-grid d-none">';
    $html .= '<div class="form-item form-lbl text-bold form-required mb-2">Tajuk ceramah:</div>';
    $html .= '<div class="form-item form-lbl text-bold form-required mb-2">Bilangan peserta:</div>';
    $html .= '<div class="form-lbl text-bold"></div>';
    $html .= '</div>';

    $html .= '<div class="p-0 m-0" id="section-speech">';

    if ($results) {
      foreach ($results as $res) {
        $html .= $this->speechComponent($res);
      }

    }
    else {
      $html .= $this->speechComponent(NULL);
    }

    $html .= '</div>';  //section-speech

    $html .= '<div class="grid grid2-50-50 form-wrapper speech-target-wrapper">';
    $html .= '<div class="form-item form-type-select form-item-speech-target">';
    $html .= '<div class="form-lbl text-bold form-required">Golongan sasaran:</div>';
    $html .= '<select class="field-speech-target speech-target form-select required" name="speech_target">';

    $optionsTarget = [
      '' => $this->t('- Sila pilih -'),
      'orang_awam' => $this->t('Orang Awam'),
      'orang_kkm' => $this->t('Kakitangan Kesihatan'),
      'pelajar' => $this->t('Murid dan Pelajar'),
      'other' => $this->t('Lain-lain'),
    ];

    foreach ($optionsTarget as $key => $value) {
      $selected = $key == $target ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $show_other = $other ? '' : 'style="display: none"';

    $html .= '<div class="form-item form-type-textfield form-item-speech-target-other" '. $show_other .'>';
    $html .= '<div class="form-lbl text-bold form-required">Lain-lain golongan sasaran:</div>';
    $html .= '<input type="text" class="field-speech-target-other form-text required speech-target-other w-90" name="speech_target_other" value="'. $other .'" maxlength="50" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';
    $html .= '</div>';  //speech-target-wrapper

    return $html;
  }

  function speechComponent($result = NULL) {
    $title = $result->speech_title ?? NULL;
    $participant = $result->speech_participant ?? NULL;

    $html = '<div class="grid grid3-6-2-2 form-wrapper setfield-speech">';

    $html .= '<div class="form-item form-type-textfield form-item-speech-title">';
    $html .= '<input type="text" class="field-speech-title form-text required speech-title w-90" name="speech_title[]" value="'. $title .'" maxlength="50" autocomplete="off" placeholder="Tajuk Ceramah">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-speech-participant">';
    $html .= '<input type="text" class="field-speech-participant form-text required number speech-participant" name="speech_participant[]" value="'. $participant .'" maxlength="5" autocomplete="off" placeholder="Bilangan peserta">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-button form-type-button d-block text-center">';
    $html .= '<div class="button3 plusSpeechTitle font-small mx-1"><i class="fas fa-plus"></i></div>';
    $html .= '<div class="button3 minusSpeechTitle font-small mx-1"><i class="fas fa-minus"></i></div>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-speech

    return $html;
  }

  function initialInterview($result = NULL) {
    $title = $result->interview_title ?? NULL;
    $type = $result->interview_type ?? NULL;
    $channel = $result->interview_channel ?? NULL;
    $other = $result->interview_channel_other ?? NULL;

    $html = '<div class="grid grid2-60-40 form-wrapper setfield-interview">';

    $html .= '<div class="form-item form-type-textfield form-item-interview-title">';
    $html .= '<div class="form-lbl text-bold form-required">Tajuk wawancara:</div>';
    $html .= '<input type="text" class="field-interview-title form-text required interview-title w-90" name="interview_title" value="'. $title .'" maxlength="50" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="interview-type-group radio-inline form-item form-wrapper">';
    $html .= '<div for="interview-type" class="form-lbl text-bold form-required">Saluran wawancara:</div>';
    $html .= '<div class="radio-group">';

    $radioChannel = [
      'tv' => $this->t('TV'),
      'radio' => $this->t('Radio'),
    ];

    foreach ($radioChannel as $key => $value) {
      $checked = $key == $type ? 'checked' : '';

      $html .= '<div class="form-item form-type-radio form-item-interview-type">';
      $html .= '<input class="form-radio" type="radio" id="interview-type-'. $key .'" name="interview_type" value="'. $key .'" '. $checked .'>';
      $html .= '<label for="interview-type-'. $key .'" class="form-lbl option">'. $value .'</label>';
      $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';  //interview-type-group

    $html .= '</div>';  //setfield-interview
    $html .= '<div class="grid grid2-50-50 form-wrapper interview-wrapper">';

    $html .= '<div class="form-item form-type-select form-item-interview-channel">';
    $html .= '<div class="form-lbl text-bold form-required">Nama saluran:</div>';
    $html .= '<select class="field-interview-channel interview-channel form-select form-select2 required w-90" name="interview_channel">';

    $optionsChannel = $this->optionChannel($type);
    // Add 'Select' option at the beginning
    $optionsChannel = ['' => $this->t('- Sila pilih -')] + $optionsChannel;

    // Add 'Other' option at the end
    $optionsChannel['other'] = 'Lain-lain';

    foreach ($optionsChannel as $key => $value) {
      $selected = $key == $channel ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $show_other = $other ? 'style="display: none"' : 'style="display: none"';

    $html .= '<div class="form-item form-type-textfield form-item-interview-channel-other" '. $show_other .'>';
    $html .= '<div class="form-lbl text-bold form-required">Lain-lain Saluran:</div>';
    $html .= '<input type="text" class="field-interview-channel-other form-text required interview-channel-other w-90" name="interview_channel_other" value="'. $other .'" maxlength="100" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';
    $html .= '</div>';  //interview-wrapper

    return $html;
  }

  function initialSocialMedia($results = NULL) {
    $html = '<div class="grid form-wrapper setfield-social-media">';
    $html .= '<div class="p-0 m-0" id="section-smedia">';

    if ($results) {
      $n = 1;
      foreach ($results as $res) {
        $html .= $this->socialMediaComponent($n, $res);
        $n++;
      }
    }
    else {
      $html .= $this->socialMediaComponent(1, NULL);
    }

    $html .= '</div>';  //section-smedia

    $html .= '<div class="form-button form-type-button d-block text-center">';
    $html .= '<div class="button plusSMActivity mx-1"><i class="fas fa-plus me-2"></i>Aktiviti di Media Sosial</div>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-social-media

    return $html;
  }

  function socialMediaComponent($seq, $result = NULL) {
    $type = $result->social_type ?? NULL;
    $topic = $result->social_topic ?? NULL;
    $platform = $result->social_platform ?? NULL;
    $other = $result->social_platform_other ?? NULL;
    $account = $result->social_account ?? NULL;
    $link = $result->social_link ?? NULL;
    $reach = $result->social_reach ?? NULL;

    $html = '<div class="form-wrapper setfield-smedia border-1 border-dashed mb-2">';

    $html .= '<div class="grid grid2-80-20 form-wrapper setfield-smedia-type">';
    $html .= '<div class="form-item form-type-select form-item-social-type">';
    $html .= '<div class="form-lbl text-bold form-required">Jenis aktiviti di media sosial:</div>';
    $html .= '<select class="field-social-type social-type form-select required" name="social_type[]" rel="'. $seq .'">';

    $optionSMType = [
      '' => $this->t('- Sila pilih -'),
      'live' => $this->t('Podcast/Live'),
      'content' => $this->t('Penghasilan kandungan & sebaran'),
      'share' => $this->t('Sebaran kandungan ubat-ubatan yang sahih dari pihak lain'),
    ];

    foreach ($optionSMType as $key => $value) {
      $selected1 = $key == $type ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected1 .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="button minusSocialMedia position-absolute end-10 text-xs">X</div>';

    $html .= '</div>';  //setfield-smedia-type
    $html .= '<div class="grid grid2-60-40 form-wrapper setfield-smedia-topic">';

    $html .= '<div class="form-item form-type-textfield form-item-social-topic">';
    $html .= '<div class="form-lbl text-bold form-required">Topik di media sosial:</div>';
    $html .= '<input type="text" class="field-social-topic form-text required social-topic w-90" name="social_topic[]" value="'. $topic .'" maxlength="50" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-wrapper platform-wrapper">';
    $html .= '<div class="form-item form-type-select form-item-social-platform">';
    $html .= '<div class="form-lbl text-bold form-required">Platform media sosial:</div>';
    $html .= '<select class="field-social-platform social-platform form-select required" name="social_platform[]" rel="'. $seq .'">';

    $optionsPlatform = $this->optionSocialPlatform();
    // Add 'Select' option at the beginning
    $optionsPlatform = ['' => $this->t('- Sila pilih -')] + $optionsPlatform;

    // Add 'Other' option at the end
    $optionsPlatform['other'] = 'Lain-lain';

    foreach ($optionsPlatform as $key => $value) {
      $selected2 = $key == $platform ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected2 .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $show_other = $other ? 'style="display: none"' : 'style="display: none"';

    $html .= '<div class="form-item form-type-textfield form-item-social-platform-other" id="smedia_platform_other_1" '. $show_other .'>';
    $html .= '<div class="form-lbl text-bold form-required">Lain-lain platform:</div>';
    $html .= '<input type="text" class="field-social-platform-other form-text required social-platform-other w-90" name="social_platform_other[]" value="'. $other .'" maxlength="20" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';
    $html .= '</div>';  //platform-wrapper

    $html .= '</div>';  //setfield-smedia-type

    $show_account = $type == 'live' ? '' : 'style="display: none"';
    
    $html .= '<div class="grid grid2-50-50 form-wrapper setfield-smedia-acount" id="smedia_acount_'. $seq .'" '. $show_account .'>';

    $html .= '<div class="form-item form-type-textfield form-item-social-account">';
    $html .= '<div class="form-lbl text-bold form-required">Nama akaun media sosial:</div>';
    $html .= '<input type="text" class="field-social-account form-text required social-account w-90" name="social_account[]" value="'. $account .'" maxlength="50" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-social-link">';
    $html .= '<div class="form-lbl text-bold form-required">Pautan media sosial:</div>';
    $html .= '<input type="text" class="field-social-link form-text required social-link w-90" name="social_link[]" value="'. $link .'" maxlength="100" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-smedia-acount
    $html .= '<div class="form-wrapper setfield-smedia-reach">';

    $html .= '<div class="form-item form-type-textfield form-item-social-reach">';
    $html .= '<div class="form-lbl text-bold form-required">Jumlah keseluruhan capaian (reach):</div>';
    $html .= '<input type="text" class="field-social-reach form-text required number social-reach" name="social_reach[]" value="'. $reach .'" maxlength="7" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-smedia-reach

    $html .= '</div>';  //setfield-smedia

    return $html;
  }

  function initialPICCSetting($state_code, $result = NULL) {
    $session = $result->picc_session ?? NULL;
    $participant = $result->picc_participant ?? NULL;
    $facility = $result->picc_facility ?? NULL;

    $html = '<div class="grid grid3-3-3-4 form-wrapper setfield-picc">';

    $html .= '<div class="form-item form-type-textfield form-item-picc-session">';
    $html .= '<div class="form-lbl text-bold form-required">Bilangan sesi PICC yang diadakan:</div>';
    $html .= '<input type="text" class="field-picc-session form-text required number picc-session" name="picc_session" value="'. $session .'" maxlength="2" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-picc-participant">';
    $html .= '<div class="form-lbl text-bold form-required">Bilangan ahli Duta yang terlibat:</div>';
    $html .= '<input type="text" class="field-picc-participant form-text required number picc-participant" name="picc_participant" value="'. $participant .'" maxlength="3" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-select form-item-picc-facility">';
    $html .= '<div class="form-lbl text-bold form-required">Fasiliti kesihatan yang terlibat:</div>';
    $html .= '<select class="field-picc-facility picc-facility form-select form-select2 required w-90" name="picc_facility">';

    $optionsFacility = $this->optionFacility($state_code);
    // Add 'Select' option at the beginning
    $optionsFacility = ['' => $this->t('- Sila pilih -')] + $optionsFacility;

    foreach ($optionsFacility as $key => $value) {
      $selected = $key == $facility ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-picc

    return $html;
  }

  function initialTraining($result = NULL) {
    $target = $result->training_target ?? NULL;
    $participant = $result->training_participant ?? NULL;
    
    $html = '<div class="grid grid2-70-30 form-wrapper setfield-training">';

    $html .= '<div class="form-item form-type-select form-item-training-target">';
    $html .= '<div class="form-lbl text-bold form-required">Jenis TOT yang dijalankan:</div>';
    $html .= '<select class="field-training-target training-target form-select required w-90" name="training_target">';

    $optionsTraining = [
      '' => $this->t('- Sila pilih -'),
      'duta' => $this->t('Ahli Duta'),
      'farmasi' => $this->t('Anggota Farmasi'),
    ];

    foreach ($optionsTraining as $key => $value) {
      $selected = $key == $target ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-training-participant">';
    $html .= '<div class="form-lbl text-bold form-required">Jumlah peserta TOT yang hadir:</div>';
    $html .= '<input type="text" class="field-training-participant form-text required number training-participant" name="training_participant" value="'. $participant .'" maxlength="3" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-training

    return $html;
  }

  function initialArticle($result = NULL) {
    $title = $result->article_title ?? NULL;
    $date = $result->article_date ?? NULL;
    $publisher = $result->article_publisher ?? NULL;
    $other = $result->article_publisher_other ?? NULL;

    $html = '<div class="grid grid2-70-30 form-wrapper setfield-article">';

    $html .= '<div class="form-item form-type-textfield form-item-article-title">';
    $html .= '<div class="form-lbl text-bold form-required">Tajuk artikel:</div>';
    $html .= '<input type="text" class="field-article-title form-text required article-title w-90" name="article_title" value="'. $title .'" maxlength="100" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-article-date">';
    $html .= '<div class="form-lbl text-bold form-required">Tarikh artikel diterbitkan:</div>';
    $html .= '<input type="text" class="field-article-date form-text datepicker2 fpicker required article-date inline-block w-90" name="article_date" value="'. $date .'" autocomplete="off">';
    $html .= '<i class="far fa-calendar-alt fp inline-block"></i>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '<div class="grid grid2-40-60 form-wrapper setfield-publisher">';

    $html .= '<div class="form-wrapper publisher-wrapper">';
    $html .= '<div class="form-item form-type-select form-item-article-publisher">';
    $html .= '<div class="form-lbl text-bold form-required">Saluran penerbitan:</div>';
    $html .= '<select class="field-article-publisher article-publisher form-select form-select2 required w-90" name="article_publisher">';

    $optionsPublisher = $this->optionPublisher();
    // Add 'Select' option at the beginning
    $optionsPublisher = ['' => $this->t('- Sila pilih -')] + $optionsPublisher;

    // Add 'Other' option at the end
    $optionsPublisher['other'] = 'Lain-lain';

    foreach ($optionsPublisher as $key => $value) {
      $selected = $key == $publisher ? 'selected' : '';
      $html .= '<option value="'. $key .'" '. $selected .'>'. $value .'</option>';
    }

    $html .= '</select>';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $show_other = $other ? 'style="display: none"' : 'style="display: none"';


    $html .= '<div class="form-item form-type-textfield form-item-article-publisher-other" '. $show_other .'>';
    $html .= '<div class="form-lbl text-bold form-required">Lain-lain Saluran penerbitan:</div>';
    $html .= '<input type="text" class="field-article-publisher-other form-text required article-publisher-other w-90" name="article_publisher_other" value="'. $other .'" maxlength="100" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';
    $html .= '</div>';  //publisher-wrapper

    $html .= '<div class="form-item form-type-textfield form-item-article-link">';
    $html .= '<div class="form-lbl text-bold">Pautan penerbitan:</div>';
    $html .= '<input type="text" class="field-article-link form-text required article-link w-90" name="article_link" value="'. ($result->article_link ?? NULL) .'" maxlength="100" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-publisher

    return $html;
  }

  function initialMeeting($result = NULL) {
    $module = $result->meeting_module ?? NULL;
    $participant = $result->meeting_participant ?? NULL;

    $html = '<div class="grid grid2-70-30 form-wrapper setfield-meeting">';

    $html .= '<div class="form-item form-type-textfield form-item-meeting-module">';
    $html .= '<div class="form-lbl text-bold form-required">Modul yang dikongsikan:</div>';
    $html .= '<input type="text" class="field-meeting-module form-text required meeting-module w-90" name="meeting_module" value="'. $module .'" maxlength="50" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-meeting-participant">';
    $html .= '<div class="form-lbl text-bold form-required">Bilangan ahli mesyuarat yang hadir:</div>';
    $html .= '<input type="text" class="field-meeting-participant form-text required number meeting-participant" name="meeting_participant" value="'. $participant .'" maxlength="5" autocomplete="off">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-meeting

    return $html;
  }

  function initialOther($results = NULL) {
    $html = '<div class="grid grid3-6-2-2 form-wrapper setfield-other-label d-md-grid d-none">';
    $html .= '<div class="form-item form-lbl text-bold form-required mb-2">Senarai aktiviti:</div>';
    $html .= '<div class="form-item form-lbl text-bold form-required mb-2">Bilangan peserta:</div>';
    $html .= '<div class="form-lbl text-bold"></div>';
    $html .= '</div>';

    $html .= '<div class="p-0 m-0" id="section-other">';

    if ($results) {
      foreach ($results as $res) {
        $html .= $this->otherComponent($res);
      }
    }
    else {
      $html .= $this->otherComponent(NULL);
    }

    $html .= '</div>';  //section-other">';

    return $html;
  }

  function otherComponent($result = NULL) {
    $activity = $result->other_activity ?? NULL;
    $participant = $result->other_participant ?? NULL;

    $html = '<div class="grid grid3-6-2-2 form-wrapper setfield-other">';

    $html .= '<div class="form-item form-type-textfield form-item-other-activity">';
    $html .= '<input type="text" class="field-other-activity form-text required other-activity w-90" name="other_activity[]" value="'. $activity .'" maxlength="50" autocomplete="off" placeholder="Senarai Aktiviti">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-item form-type-textfield form-item-other-participant">';
    $html .= '<input type="text" class="field-other-participant form-text required number other-participant" name="other_participant[]" value="'. $participant .'" maxlength="5" autocomplete="off" placeholder="Bilangan peserta">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-button form-type-button d-block text-center">';
    $html .= '<div class="button3 plusOtherActivity font-small mx-1"><i class="fas fa-plus"></i></div>';
    $html .= '<div class="button3 minusOtherActivity font-small mx-1"><i class="fas fa-minus"></i></div>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-other

    return $html;
  }

  public function ajaxNewSpeechTitle(Request $request) {
    if ($request->request->has('sequence') ) {
      $seq = $request->get('sequence');
    }
    else {
      return new JsonResponse([
        'status' => '02',
        'html' => NULL,
        'message' => 'System mengalami ralat.',
      ]);
    }

    $html = $this->speechComponent(NULL);

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
      'message' => NULL,
    ]);
  }

  public function ajaxNewSocialMedia(Request $request) {
    if ($request->request->has('sequence') ) {
      $seq = $request->get('sequence');
    }
    else {
      return new JsonResponse([
        'status' => '02',
        'html' => NULL,
        'message' => 'System mengalami ralat.',
      ]);
    }

    $html = $this->socialMediaComponent($seq, NULL);

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
      'message' => NULL,
    ]);
  }

  public function ajaxNewOtherActivity(Request $request) {
    if ($request->request->has('sequence') ) {
      $seq = $request->get('sequence');
    }
    else {
      return new JsonResponse([
        'status' => '02',
        'html' => NULL,
        'message' => 'System mengalami ralat.',
      ]);
    }

    $html = $this->otherComponent(NULL);

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
      'message' => NULL,
    ]);
  }

  function initialDutaParticipant($report_id) {
    $connection = Database::getConnection();

    $query = $connection->select('custom_duta_participant', 'dp')
      ->fields('dp', ['duta_id'])
      ->condition('report_id', $report_id);

    $checkDuta = $query->execute()->fetchAll();
    $resultDuta = (object) $checkDuta;

    $html = '';

    if ($checkDuta) {
      foreach ($resultDuta as $res) {
        $html .= $this->dutaparticipantComponent($res);
      }
    }
    else {
      $html .= $this->dutaparticipantComponent(NULL);
    }

    return $html;
  }

  function dutaparticipantComponent($result = NULL) {
    $duta_id = $result->duta_id ?? NULL;

    $html = '<div class="grid grid2-50-50 form-wrapper setfield-duta-party">';

    $html .= '<div class="form-item form-type-textfield form-item-duta-participant">';
    $html .= '<input type="text" class="field-duta-participant form-text required duta-participant w-100" name="duta_participant[]" value="'. $duta_id .'" maxlength="7" autocomplete="off" placeholder="ID Duta KUA">';
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-button form-type-button d-block text-start">';
    $html .= '<div class="button3 plusDutaParty font-small mx-1"><i class="fas fa-plus"></i></div>';
    $html .= '<div class="button3 minusDutaParty font-small mx-1"><i class="fas fa-minus"></i></div>';
    $html .= '</div>';

    $html .= '</div>';  //setfield-duta-party

    return $html;
  }

  public function ajaxNewDutaParticipant(Request $request) {
    if ($request->request->has('sequence') ) {
      $seq = $request->get('sequence');
    }
    else {
      return new JsonResponse([
        'status' => '02',
        'html' => NULL,
        'message' => 'System mengalami ralat.',
      ]);
    }

    $initial = '';

    if ($seq == 1) {
      $reportid = base64_decode($request->get('reportid') );
      $report_id = base64_decode($request->get('report_id') );

      $initial = $this->initialDutaParticipant($report_id);
    }
    else {
      $html = $this->dutaparticipantComponent(NULL);
    }

    $endHtml = empty($initial) ? $html : $initial;

    return new JsonResponse([
      'status' => '01',
      'html' => $endHtml,
      'message' => NULL,
    ]);
  }

  function initialActivityImage($report_id) {
    $connection = Database::getConnection();

    $query = $connection->select('custom_activity_image', 'ai')
      ->fields('ai', ['id', 'image_link'])
      ->condition('report_id', $report_id)
      ->condition('deleted_at', NULL, 'IS NULL');

    $checkAImage = $query->execute()->fetchAll();
    $resultAImage = (object) $checkAImage;

    $html = '';

    if ($checkAImage) {
      foreach ($resultAImage as $res) {
        $html .= $this->activityimageComponent($res);
      }
    }
    else {
      $html .= $this->activityimageComponent(NULL);
    }

    return $html;
  }

  function activityimageComponent($result = NULL) {
    $fid = $result->id ?? NULL;
    $imagelink = $result->image_link ?? NULL;

    $html = '<div class="grid grid2-70-30 form-wrapper setfield-activity-image mb-md-5">';

    $html .= '<div class="form-items form-type-textfield form-item-activity-image">';

    if ($imagelink) {
      $filename = basename($imagelink);
      $html .= '<a>'. $filename .'</a>';
      $html .= '<input type="hidden" class="field-activity-image activity-image" name=FALSE value="'. $filename .'">';
    }
    else {
      $html .= '<input type="file" class="field-activity-image form-file activity-image" name="activity_image[]">';
    }
    $html .= '<p class="text-danger inputerror"></p>';
    $html .= '</div>';

    $html .= '<div class="form-button form-type-button d-block text-start">';
    $html .= '<div class="button3 plusActivityImage font-small mx-1"><i class="fas fa-plus"></i></div>';
    $html .= '<div class="button3 minusActivityImage font-small mx-1" rel="'. $fid .'"><i class="fas fa-minus"></i></div>';
    $html .= '</div>';

    $html .= '<div class="image-preview">';

    if ($imagelink) {
      $html .= '<img class="img-activity-image" src="/sites/default/files/'. $imagelink .'">';
    }
    
    $html .= '</div>';  //image-preview

    $html .= '</div>';  //setfield-activity-image

    return $html;
  }

  public function ajaxNewActivityImage(Request $request) {
    if ($request->request->has('sequence') ) {
      $seq = $request->get('sequence');
    }
    else {
      return new JsonResponse([
        'status' => '02',
        'html' => NULL,
        'message' => 'System mengalami ralat.',
      ]);
    }

    $initial = '';

    if ($seq == 1) {
      $reportid = base64_decode($request->get('reportid') );
      $report_id = base64_decode($request->get('report_id') );

      $initial = $this->initialActivityImage($report_id);
    }
    else {
      $html = $this->activityimageComponent(NULL);
    }

    $endHtml = empty($initial) ? $html : $initial;

    return new JsonResponse([
      'status' => '01',
      'html' => $endHtml,
      'message' => NULL,
    ]);
  }

  public function submitActivity(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    // Get roles as an array
    $roles = $user->getRoles();

    // To retrieve data from table User
    $account = \Drupal\user\Entity\User::load( $user_id );
    //$user_state = $account->get('field_state')->value;
    $user_state = NULL;

    $connection = Database::getConnection();
    $file_system = \Drupal::service('file_system');

    $fields = [];

    $encoded = $request->get('reportid');
    $reportid = base64_decode($request->get('reportid') );
    $report_id = base64_decode($request->get('report_id') );

    $query = $connection->select('custom_pf10', 'pf')
      ->fields('pf')
      ->condition('id', $report_id)
      ->condition('report_id', $reportid);

    $record = $query->execute()->fetchAssoc();
    $result = (object) $record;

    if (!$record) {
      $status = 'CREDENTIAL';
      $message = 'System mengesan berlaku perlanggaran kredentasi maklumat.<br/>Sila cuba lagi.';
      $message .= '<br/>Rekod PF10 '. $result->name .' gagal dikemaskini.';

      \Drupal::logger('update_pf10')->error('System detects information credential violation.');
      \Drupal::messenger()->addError('Rekod PF10 '. $result->name .' gagal dikemaskini.');

      return new JsonResponse([
        'status' => $status,
        'message' => $message,
      ]);
    }

    //exhibit
    $fields['exhibit_booth'] = NULL;
    $fields['exhibit_participant'] = NULL;
    $fields['exhibit_target'] = NULL;

    //speech
    $fields['no_of_speech'] = NULL;
    $fields['speech_title'] = NULL;
    $fields['speech_participant'] = NULL;
    $fields['speech_target'] = NULL;

    //interview
    $fields['interview_title'] = NULL;
    $fields['interview_type'] = NULL;
    $fields['interview_channel'] = NULL;

    //social-media
    $fields['social_type'] = NULL;
    $fields['social_topic'] = NULL;
    $fields['social_platform'] = NULL;
    $fields['social_account'] = NULL;
    $fields['social_link'] = NULL;
    $fields['social_reach'] = NULL;

    //picc
    $fields['picc_session'] = NULL;
    $fields['picc_participant'] = NULL;
    $fields['picc_facility'] = NULL;

    //training
    $fields['training_target'] = NULL;
    $fields['training_participant'] = NULL;

    //publisher
    $fields['article_title'] = NULL;
    $fields['article_date'] = NULL;
    $fields['article_publisher'] = NULL;
    $fields['article_link'] = NULL;

    //meeting
    $fields['meeting_module'] = NULL;
    $fields['meeting_participant'] = NULL;

    //other
    $fields['no_of_other'] = NULL;
    $fields['other_activity'] = NULL;
    $fields['other_participant'] = NULL;

    $deleteExisting = $this->deleteActivityComponent($report_id);

    $fieldExhibit['report_id'] = $report_id;

    if ($request->request->has('exhibit_booth') ) {
      $fieldExhibit['exhibit_booth'] = $request->get('exhibit_booth');
      $fields['exhibit_booth'] = $request->get('exhibit_booth');
    }

    if ($request->request->has('exhibit_participant') ) {
      $fieldExhibit['exhibit_participant'] = $request->get('exhibit_participant');
      $fields['exhibit_participant'] = $request->get('exhibit_participant');
    }

    if ($request->request->has('exhibit_target') ) {
      if ($request->get('exhibit_target') == 'other') {
        $fieldExhibit['exhibit_target'] = $request->get('exhibit_target');
        $fieldExhibit['exhibit_target_other'] = ucwords($request->get('exhibit_target_other') );
        $fields['exhibit_target'] = ucwords($request->get('exhibit_target_other') );
      }
      else {
        $fieldExhibit['exhibit_target'] = $request->get('exhibit_target');
        $fieldExhibit['exhibit_target_other'] = NULL;

          // 'orang_awam' => $this->t('Orang Awam'),
          // 'pelajar' => $this->t('Murid dan Pelajar'),

        if ($request->get('exhibit_target') == 'orang_awam') {
          $fields['exhibit_target'] = 'Orang Awam';
        }
        elseif ($request->get('exhibit_target') == 'pelajar') {
          $fields['exhibit_target'] = 'Murid dan Pelajar';
        }
      }
    }

    if ($fields['exhibit_booth']) {
      $insert = $connection->insert('custom_exhibit')
        ->fields($fieldExhibit)
        ->execute();
    }

    $no_speech = 0;

    if ($request->request->has('speech_title') && $request->request->has('speech_participant') ) {
      // Retrieve all speech_title values and speech_participant values
      $stitles = $request->request->all('speech_title');
      $sparticipant = $request->request->all('speech_participant');

      $speechtitle = '';
      $stitle = [];
      $speechparticipant = 0;

      for ($i = 0; $i < count($stitles); $i++) {
        $no_speech++;

        $stitle[$i] = trim($stitles[$i]);

        $insert = $connection->insert('custom_speech')
          ->fields([
            'report_id' => $report_id,
            'speech_title' => $stitle[$i],
            'speech_participant' => (int) $sparticipant[$i],
          ])
          ->execute();

        // if ($no_speech == 1) {
        //   $speechtitle .= $stitle[$i];
        // }
        // else {
        //   $speechtitle .= ' , '. $stitle[$i];
        // }

        if (!empty($stitle) ) {
          $speechtitle = implode(' , ', $stitle);
        }

        $speechparticipant = $speechparticipant + (int) $sparticipant[$i];
      }

      $fields['no_of_speech'] = $no_speech;
      $fields['speech_title'] = $speechtitle;
      $fields['speech_participant'] = $speechparticipant;
    }

    $fieldSpeechSetting['report_id'] = $report_id;

    if ($request->request->has('speech_target') && $no_speech) {
      if ($request->get('speech_target') == 'other') {
        $fieldSpeechSetting['speech_target'] = $request->get('speech_target');
        $fieldSpeechSetting['speech_target_other'] = ucwords($request->get('speech_target_other') );
        $fields['speech_target'] = ucwords($request->get('speech_target_other') );
      }
      else {
        $fieldSpeechSetting['speech_target'] = $request->get('speech_target');
        $fieldSpeechSetting['speech_target_other'] = NULL;

          // 'orang_awam' => $this->t('Orang Awam'),
          // 'orang_kkm' => $this->t('Kakitangan Kesihatan'),
          // 'pelajar' => $this->t('Murid dan Pelajar'),

        if ($request->get('speech_target') == 'orang_awam') {
          $fields['speech_target'] = 'Orang Awam';
        }
        elseif ($request->get('speech_target') == 'orang_kkm') {
          $fields['speech_target'] = 'Kakitangan Kesihatan';
        }
        elseif ($request->get('speech_target') == 'pelajar') {
          $fields['speech_target'] = 'Murid dan Pelajar';
        }
      }

      $insertSpeech = $connection->insert('custom_speech_setting')
        ->fields($fieldSpeechSetting)
        ->execute();
    }

    $fieldInterview['report_id'] = $report_id;

    if ($request->request->has('interview_title') ) {
      $fieldInterview['interview_title'] = $request->get('interview_title');
      $fields['interview_title'] = $request->get('interview_title');
    }

    if ($request->request->has('interview_type') ) {
      $fieldInterview['interview_type'] = $request->get('interview_type');
      $fields['interview_type'] = $request->get('interview_type');
    }

    if ($request->request->has('interview_channel') ) {
      if ($request->get('interview_channel') == 'other') {
        $fieldInterview['interview_channel'] = ucwords($request->get('interview_channel_other') );
        $fieldInterview['interview_channel_other'] = ucwords($request->get('interview_channel_other') );
        $fields['interview_channel'] = ucwords($request->get('interview_channel_other') );

        $this->insertChannel(ucwords($request->get('interview_channel_other') ), $request->get('interview_type') );
      }
      else {
        $fieldInterview['interview_channel'] = $request->get('interview_channel');
        $fieldInterview['interview_channel_other'] = NULL;
        $fields['interview_channel'] = $request->get('interview_channel');
      }
    }

    if ($fieldInterview['interview_title']) {
      $insert = $connection->insert('custom_interview')
        ->fields($fieldInterview)
        ->execute();
    }

    $no_sm = 0;

    // Initialize variables to keep track of the highest reach and its index
    $highest_reach = -1;
    $highest_index = -1;

    if ($request->request->has('social_type') ) {
      // Retrieve all speech_title values and speech_participant values
      $smtypes = $request->request->all('social_type');
      $smtopics = $request->request->all('social_topic');
      $smplatform = $request->request->all('social_platform');
      $smplatform_other = $request->request->all('social_platform_other');
      $smaccount = $request->request->all('social_account');
      $smlink = $request->request->all('social_link');
      $smreach = $request->request->all('social_reach');

      for ($i = 0; $i < count($smtypes); $i++) {
        $no_sm++;

        $fieldSocial['report_id'] = $report_id;
        $fieldSocial['social_type'] = $smtypes[$i];
        $fieldSocial['social_topic'] = $smtopics[$i];
        $fieldSocial['social_reach'] = (int) $smreach[$i];

        if ($smtypes[$i] == 'live') {
          $fieldSocial['social_account'] = $smaccount[$i];
          $fieldSocial['social_link'] = $smlink[$i];
        }
        else {
          $fieldSocial['social_account'] = NULL;
          $fieldSocial['social_link'] = NULL;
        }

        if ($smplatform[$i] == 'other') {
          $fieldSocial['social_platform'] = ucwords($smplatform_other[$i]);
          $fieldSocial['social_platform_other'] = ucwords($smplatform_other[$i]);

          $this->insertSocialPlatform(ucwords($smplatform_other[$i]) );
        }
        else {
          $fieldSocial['social_platform'] = $smplatform[$i];
          $fieldSocial['social_platform_other'] = NULL;
          //$fields['social_platform'] = $smplatform[$i];
        }

        if ($smreach[$i] > $highest_reach) {
          $highest_reach = $smreach[$i];
          $highest_index = $i;
        }

        $insert = $connection->insert('custom_social_media')
          ->fields($fieldSocial)
          ->execute();
      }

          // 'live' => $this->t('Podcast/Live'),
          // 'content' => $this->t('Penghasilan kandungan & sebaran'),
          // 'share' => $this->t('Sebaran kandungan ubat-ubatan yang sahih dari pihak lain'),

      if ($smtypes[$highest_index] == 'live') {
        $fields['social_type'] = 'Podcast / Live';
      }
      elseif ($smtypes[$highest_index] == 'content') {
        $fields['social_type'] = 'Penghasilan kandungan & sebaran';
      }
      elseif ($smtypes[$highest_index] == 'share') {
        $fields['social_type'] = 'Sebaran kandungan ubat-ubatan yang sahih dari pihak lain';
      }

      //$fields['social_type'] = $smtypes[$highest];
      $fields['social_topic'] = $smtopics[$highest_index];
      $fields['social_account'] = $smaccount[$highest_index];
      $fields['social_link'] = $smlink[$highest_index];
      $fields['social_reach'] = $smreach[$highest_index];

      if ($smplatform[$highest_index] == 'other') {
        $fields['social_platform'] = ucwords($smplatform_other[$highest_index]);
      }
      else {
        $fields['social_platform'] = $smplatform[$highest_index];
      }

    }

    $fieldPicc['report_id'] = $report_id;

    if ($request->request->has('picc_session') ) {
      $fieldPicc['picc_session'] = $request->get('picc_session');
      $fields['picc_session'] = $request->get('picc_session');
    }

    if ($request->request->has('picc_participant') ) {
      $fieldPicc['picc_participant'] = $request->get('picc_participant');
      $fields['picc_participant'] = $request->get('picc_participant');
    }

    if ($request->request->has('picc_facility') ) {
      $fieldPicc['picc_facility'] = $request->get('picc_facility');
      $fields['picc_facility'] = $this->getFacilityName($request->get('picc_facility') );
    }

    if ($fieldPicc['picc_session']) {
      $insert = $connection->insert('custom_picc_setting')
        ->fields($fieldPicc)
        ->execute();
    }

    $fieldTraining['report_id'] = $report_id;

    if ($request->request->has('training_target') ) {
      $fieldTraining['training_target'] = $request->get('training_target');

          // 'duta' => $this->t('Ahli Duta'),
          // 'farmasi' => $this->t('Anggota Farmasi'),

        if ($request->get('training_target') == 'duta') {
          $fields['training_target'] = 'Ahli Duta';
        }
        elseif ($request->get('training_target') == 'farmasi') {
          $fields['training_target'] = 'Anggota Farmasi';
        }
    }

    if ($request->request->has('training_participant') ) {
      $fieldTraining['training_participant'] = $request->get('training_participant');
      $fields['training_participant'] = $request->get('training_participant');
    }

    if ($fieldTraining['training_target']) {
      $insert = $connection->insert('custom_training')
        ->fields($fieldTraining)
        ->execute();
    }

    $fieldArticle['report_id'] = $report_id;

    if ($request->request->has('article_title') ) {
      $fieldArticle['article_title'] = $request->get('article_title');
      $fields['article_title'] = $request->get('article_title');
    }

    if ($request->request->has('article_date') ) {
      $fieldArticle['article_date'] = $request->get('article_date');
      $fields['article_date'] = $request->get('article_date');
    }

    if ($request->request->has('article_publisher') ) {
      if ($request->get('article_publisher') == 'other') {
        $fieldArticle['article_publisher'] = ucwords($request->get('article_publisher_other') );
        $fieldArticle['article_publisher_other'] = ucwords($request->get('article_publisher_other') );
        $fields['article_publisher'] = ucwords($request->get('article_publisher_other') );

        $this->insertPublisher(ucwords($request->get('article_publisher_other') ) );
      }
      else {
        $fieldArticle['article_publisher'] = $request->get('article_publisher');
        $fieldArticle['article_publisher_other'] = NULL;
        $fields['article_publisher'] = $request->get('article_publisher');
      }
    }

    if ($request->request->has('article_link') ) {
      $fieldArticle['article_link'] = $request->get('article_link');
      $fields['article_link'] = $request->get('article_link');
    }

    if ($fieldArticle['article_title']) {
      $insert = $connection->insert('custom_article')
        ->fields($fieldArticle)
        ->execute();
    }

    $fieldMeeting['report_id'] = $report_id;

    if ($request->request->has('meeting_module') ) {
      $fieldMeeting['meeting_module'] = $request->get('meeting_module');
      $fields['meeting_module'] = $request->get('meeting_module');
    }

    if ($request->request->has('meeting_participant') ) {
      $fieldMeeting['meeting_participant'] = $request->get('meeting_participant');
      $fields['meeting_participant'] = $request->get('meeting_participant');
    }

    if ($fieldMeeting['meeting_module']) {
      $insert = $connection->insert('custom_meeting')
        ->fields($fieldMeeting)
        ->execute();
    }

    $no_oactivity = 0;

    if ($request->request->has('other_activity') && $request->request->has('other_participant') ) {
      // Retrieve all other_activity values and other_participant values
      $oactivities = $request->request->all('other_activity');
      $oparticipant = $request->request->all('other_participant');

      $otheractivity = '';
      $oactivity = [];
      $otherparticipant = 0;

      for ($i = 0; $i < count($oactivities); $i++) {
        $no_oactivity++;

        $oactivity[$i] = trim($oactivities[$i]);

        $insert = $connection->insert('custom_other')
          ->fields([
            'report_id' => $report_id,
            'other_activity' => $oactivity[$i],
            'other_participant' => (int) $oparticipant[$i],
          ])
          ->execute();

        // if ($no_oactivity == 1) {
        //   $otheractivity .= $oactivity[$i];
        // }
        // else {
        //   $otheractivity .= ' , '. $oactivity[$i];
        // }

        if (!empty($oactivity) ) {
          $otheractivity = implode(' , ', $oactivity);
        }

        $otherparticipant = $otherparticipant + (int) $oparticipant[$i];
      }

      $fields['no_of_other'] = $no_oactivity;
      $fields['other_activity'] = $otheractivity;
      $fields['other_participant'] = $otherparticipant;
    }

    $fields['updated_at'] = date('Y-m-d H:i:s', time() );

    try {
      $update = $connection->update('custom_pf10')
        ->fields($fields)
        ->condition('id', $report_id)
        ->execute();

      if ($result->completed) {
        $status = '01';
        $message = 'Laporan PF10 '. $result->report_id .' telah dikemaskini.';
        $logger_msg = 'Record PF10 has been updated.';
        $messender_msg = 'Record PF10 has been updated.';
      }
      else {
        $status = '01';
        $message = 'Draft Laporan PF10 '. $result->report_id .' telah dikemaskini.';
        $logger_msg = 'Record PF10 draft has been updated.';
        $messender_msg = 'Record PF10 draft has been updated.';
      }

      //\Drupal::logger('pf10')->notice($logger_msg);
      \Drupal::messenger()->addStatus($messender_msg);
    }
    catch (DatabaseExceptionWrapper $e) {
      $status = 'DATABASE';
      $message = 'System mengalami gangguan teknikal.<br/>Sila cuba lagi.';

      \Drupal::logger('update_pf10')->error('Database insert failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError('An error occurred while inserting data.');
    }

    return new JsonResponse([
      'status' => $status,
      'message' => $message,
      'reportid' => $encoded,
    ]);
  }

  function deleteActivityComponent($report_id) {
    $connection = Database::getConnection();

    $deleteExhibit = $connection->delete('custom_exhibit')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteSpeechSetting = $connection->delete('custom_speech_setting')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteSpeech = $connection->delete('custom_speech')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteInterview = $connection->delete('custom_interview')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteSocialMedia = $connection->delete('custom_social_media')
      ->condition('report_id', $report_id)
      ->execute();

    $deletePICCSetting = $connection->delete('custom_picc_setting')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteTraining = $connection->delete('custom_training')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteArticlePublished = $connection->delete('custom_article')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteMeeting = $connection->delete('custom_meeting')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteOther = $connection->delete('custom_other')
      ->condition('report_id', $report_id)
      ->execute();

    return TRUE;
  }

  public function submitAdditional(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    // Get roles as an array
    $roles = $user->getRoles();

    // To retrieve data from table User
    $account = \Drupal\user\Entity\User::load( $user_id );
    //$user_state = $account->get('field_state')->value;
    $user_state = NULL;

    $connection = Database::getConnection();
    $file_system = \Drupal::service('file_system');

    $fields = [];

    $encoded = $request->get('reportid');
    $reportid = base64_decode($request->get('reportid') );
    $report_id = base64_decode($request->get('report_id') );

    $query = $connection->select('custom_pf10', 'pf')
      ->fields('pf')
      ->condition('id', $report_id)
      ->condition('report_id', $reportid);

    $record = $query->execute()->fetchAssoc();
    $result = (object) $record;

    if (!$record) {
      $status = 'CREDENTIAL';
      $message = 'System mengesan berlaku perlanggaran kredentasi maklumat.<br/>Sila cuba lagi.';
      $message .= '<br/>Rekod PF10 '. $result->name .' gagal dikemaskini.';

      \Drupal::logger('update_pf10')->error('System detects information credential violation.');
      \Drupal::messenger()->addError('Rekod PF10 '. $result->name .' gagal dikemaskini.');

      return new JsonResponse([
        'status' => $status,
        'message' => $message,
        'reportid' => $encoded,
      ]);
    }

    $deleteExisting = $this->deleteAdditionalComponent($report_id);

    if ($request->request->has('initiative') ) {
      $fields['initiative'] = $request->get('initiative');
    }

    $no_dparticipant = 0;

    if ($request->request->has('duta_participant') ) {
      // Retrieve all duta_participant values
      $dparticipant = $request->request->all('duta_participant');

      $duta_participant = '';
      $duta_id = [];

      for ($i = 0; $i < count($dparticipant); $i++) {
        if (!empty($dparticipant[$i]) ) {
          $no_dparticipant++;

          $duta_id[$i] = strtoupper(trim($dparticipant[$i]) );

          $insert = $connection->insert('custom_duta_participant')
            ->fields([
              'report_id' => $report_id,
              'duta_id' => $duta_id[$i],
            ])
            ->execute();

          // if ($no_dparticipant == 1) {
          //   $duta_participant .= $duta_id[$i];
          // }
          // else {
          //   $duta_participant .= ' , '. $duta_id[$i];
          // }

        }
      }

      if (!empty($duta_id) ) {
        $duta_participant = implode(' , ', $duta_id);
      }

      $fields['no_of_duta'] = $no_dparticipant;
      $fields['duta_participant'] = $duta_participant;
    }

    if ($request->request->has('budget_expense') ) {
      $fields['budget_expense'] = $request->get('budget_expense');
    }

    if ($request->request->has('collaboration') ) {
      if ($request->get('collaboration') == 'other') {
        $collaborator = ucwords($request->get('collaboration_other') );

        //$fieldCollaborator['collaborator_code'] = $this->insertCollaboration($collaborator);
        $fieldCollaborator['collaboration_other'] = $request->get('collaboration_other');
        $fieldCollaborator['pawe_code'] = NULL;
        $fields['collaborator'] = $request->get('collaboration_other');
      }
      elseif ($request->get('collaboration') == 'PAWE') {
        $fieldCollaborator['collaborator_code'] = $request->get('collaboration');
        $fieldCollaborator['collaboration_other'] = NULL;
        $fieldCollaborator['pawe_code'] = $request->get('pawe_code');
        $fields['collaborator'] = $this->getPAWEName($request->get('pawe_code') );
      }
      elseif ($request->get('collaboration') ) {
        $fieldCollaborator['collaborator_code'] = $request->get('collaboration');
        $fieldCollaborator['collaboration_other'] = NULL;
        $fieldCollaborator['pawe_code'] = NULL;
        $fields['collaborator'] = $this->getCollaborationName($request->get('collaboration') );
      }

      $fieldCollaborator['report_id'] = $report_id;

      $insert = $connection->insert('custom_collaborator')
        ->fields($fieldCollaborator)
        ->execute();

    }
    else {
      $fields['collaborator'] = NULL;
    }

    $no_finvolvement = 0;

    if ($request->request->has('facility_involvement') ) {
      // Retrieve all facility_involvement values
      $finvolvement = $request->request->all('facility_involvement');

      $facility_involvement = '';
      $facility = [];

      for ($i = 0; $i < count($finvolvement); $i++) {
        if (!empty($finvolvement[$i]) ) {
          $no_finvolvement++;

          $facility[$i] = $this->getFacilityName($finvolvement[$i]);

          $insert = $connection->insert('custom_facility_involvement')
            ->fields([
              'report_id' => $report_id,
              'facility_code' => $finvolvement[$i],
            ])
            ->execute();

          // if ($no_finvolvement == 1) {
          //   $facility_involvement .= $facility[$i];
          // }
          // else {
          //   $facility_involvement .= ' , '. $facility[$i];
          // }

        }
      }

      if (!empty($facility) ) {
        $facility_involvement = implode(' , ', $facility);
      }

      $fields['no_of_facility_involve'] = $no_finvolvement;
      $fields['facility_involvement'] = $facility_involvement;
    }

    if ($request->request->has('remark') ) {
      $fields['remark'] = $request->get('remark');
    }

    if ($request->request->has('rph_no') ) {
      $fields['rph_no'] = $request->get('rph_no');
    }

    if ($request->request->has('delete_image') ) {
      // Retrieve all delete_image values
      $deleteids = $request->request->all('delete_image');

      foreach ($deleteids as $image_id) {
        $this->deleteActivityImage($image_id);
      }
    }

    $activityImages = $request->files->get('activity_image');

    if (!empty($activityImages) && is_array($activityImages)) {
      foreach ($activityImages as $image) {
        if ($image->isValid()) {
          $extension = strtolower($image->getClientOriginalExtension() );
          $allowed_extensions = ['jpg', 'jpeg', 'png'];

          if (!in_array($extension, $allowed_extensions) ) {
            //$status = 'FORMAT';
            //$message = 'Hanya format PNG, JPG, and JPEG sahaja dibenarkan.';
          }
          else {
            $year = date('Y');
            $folder = "pf10/$year";
            $directory = "public://$folder";

            // Prepare directory.
            $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

            // Save the uploaded file content.
            $filename = strtolower(preg_replace('/[^a-zA-Z0-9\._-]/', '_', $image->getClientOriginalName()) );
            $uri = $directory .'/'. $filename;

            //$file = $file_system->move($image->getRealPath(), $uri, FileSystemInterface::EXISTS_RENAME);
            $file = $file_system->move($image->getRealPath(), $uri, FileExists::Rename);

            if ($file) {
              $fieldActivityImage['report_id'] = $report_id;
              $fieldActivityImage['image_link'] = str_replace('public://', '', $uri);
              $fieldActivityImage['created_at'] = date('Y-m-d H:i:s', time() );

              $insert = $connection->insert('custom_activity_image')
                ->fields($fieldActivityImage)
                ->execute();

            }
            else {
              // $status = 'SYSTEM ERROR';
              // $message = 'Muat naik gambar Aktiviti gagal.';
            }

          }

        }
      }
    }

    $fields['updated_at'] = date('Y-m-d H:i:s', time() );

    $newreportid = NULL;

    if (!$result->completed) {
      $entry = $this->getRef( date('Y') );

      $new_entry = $entry + 1;

      // Generate report_id PF102500123
      $newreportid = 'PF10' . date('y') . str_pad($new_entry, 5, '0', STR_PAD_LEFT);

      $fields['report_id'] = $newreportid;
      $fields['completed'] = 1;
      $fields['submission_at'] = date('Y-m-d H:i:s', time() );    
      $fields['submission_by'] = $user_id;
    }

    try {
      $update = $connection->update('custom_pf10')
        ->fields($fields)
        ->condition('id', $report_id)
        ->execute();

      if ($new_entry) {
        $this->updateRef( date('Y'), $new_entry);
      }

      if ($result->completed) {
        $status = '01';
        $message = 'Laporan '. $result->report_id .' telah dikemaskini.';
        $logger_msg = 'Record PF10 has been updated.';
        $messender_msg = 'Record PF10 has been updated.';
      }
      else {
        $status = '01';
        $message = 'Laporan '. $newreportid .' telah dihantar.';
        $logger_msg = 'Record PF10 has been submit.';
        $messender_msg = 'Record PF10 has been submit.';
      }

      //\Drupal::logger('pf10')->notice($logger_msg);
      \Drupal::messenger()->addStatus($messender_msg);
    }
    catch (DatabaseExceptionWrapper $e) {
      $status = 'DATABASE';
      $message = 'System mengalami gangguan teknikal.<br/>Sila cuba lagi.';

      \Drupal::logger('submit_pf10')->error('Database insert failed: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError('An error occurred while inserting data.');
    }

    return new JsonResponse([
      'status' => $status,
      'message' => $message,
      'reportid' => $newreportid ? base64_encode($newreportid) : $encoded,
    ]);
  }

  function deleteAdditionalComponent($report_id) {
    $connection = Database::getConnection();

    $deleteDutaParticipant= $connection->delete('custom_duta_participant')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteCollaborator = $connection->delete('custom_collaborator')
      ->condition('report_id', $report_id)
      ->execute();

    $deleteFaciliyInvolve = $connection->delete('custom_facility_involvement')
      ->condition('report_id', $report_id)
      ->execute();

    // $deleteActivityImage = $connection->delete('custom_activity_image')
    //   ->condition('report_id', $report_id)
    //   ->execute();

    return TRUE;
  }

  function deleteActivityImage($image_id) {
    $connection = Database::getConnection();
    $file_system = \Drupal::service('file_system');

    $query = $connection->select('custom_activity_image', 'ai')
      ->fields('ai', ['image_link'])
      ->condition('id', $image_id);

    $record = $query->execute()->fetchField();
    //$result = (object) $record;

    if ($record) {
      $file_path = 'public://' . $record; // adjust if files are in private:// or other stream wrapper

      // Delete the image file
      // if (file_exists($file_path)) {
      //   $file_system->delete($file_path);
      // }

      $fields['deleted_at'] = date('Y-m-d H:i:s', time() );

      $update = $connection->update('custom_activity_image')
        ->fields($fields)
        ->condition('id', $image_id)
        ->execute();

      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  public function mainPage(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    $locality = [];

    $localities = $this->getLocality();

    foreach ($localities as $loc) {
      if (!$loc->state) {
        $local = $loc->locality;
        switch ($local) {
          case 'FMC':
            $desc = 'Bahagian Amalan & Perkembangan Farmasi';
            break;

          case 'HKL':
            $desc = 'Hospital Kuala Lumpur';
            break;

          case 'IKN':
            $desc = 'Institut Kanser Negara';
            break;

        }
      }
      else {
        $local = $loc->locality;
        $desc = $loc->state;
      }

      $locality[] = [
        'key' => $local,
        'value' => $desc,
      ];
    }

    $locality[] = [
      'key' => 'NOT',
      'value' => 'Ahli Farmasi Bukan KKM',
    ];

    return [
        '#theme' => 'main-page',
        '#items' => $locality,
        '#attached' => [
          'library' => [
            'pf10/pf10',
            'pf10/mainpage',
            ]
          ],
      ];
  }

  public function signIn(Request $request) {
    $status = '01';
    $message = "Testing";

    return new JsonResponse([
      'status' => $status,
      'message' => $message,
    ]);
  }

  public function managePage(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    $locality = [];

    $localities = $this->getLocality();

    foreach ($localities as $loc) {
      if (!$loc->state) {
        switch ($loc->locality) {
          case 'FMC':
            $desc = 'Bahagian Amalan & Perkembangan Farmasi';
            break;

          case 'HKL':
            $desc = 'Hospital Kuala Lumpur';
            break;

          case 'IKN':
            $desc = 'Institut Kanser Negara';
            break;

        }
      }
      else {
        $desc = $loc->state;
      }

      $locality[] = [
        'key' => $loc->loaclity,
        'value' => $desc,
      ];
    }

    $locality[] = [
      'key' => 'NOT',
      'value' => 'Ahli Farmasi Bukan KKM',
    ];

    return [
        '#theme' => 'manage-page',
        '#items' => $locality,
        '#attached' => [
          'library' => [
            'pf10/pf10',
            'pf10/managepage',
            ]
          ],
      ];
  }

  public function listing(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    $session = $this->requestStack->getCurrentRequest()->getSession();

    // Set a session variable
    $session->set('last_page', $_SERVER['REQUEST_URI']);

    $headers = [
      'id' => ['value' => 'Bil.', 'class' => ''],
      'report_id' => ['value' => 'No. Rujukan', 'class' => 'wd-15'],
      'program_date' => ['value' => 'Tarikh Program', 'class' => 'wd-30 text-center'],
      'state_code' => ['value' => 'Negeri', 'class' => 'wd-15'],
      'postcode' => ['value' => 'Poskod', 'class' => 'wd-10 text-center'],
      'budget_expense' => ['value' => 'Jumlah Bajet (RM)', 'class' => 'wd-15 text-center'],
      'rph_no' => ['value' => 'No. RPh', 'class' => 'wd-15 text-center'],
      'initiative' => ['value' => 'Inisiatif DUTA', 'class' => 'wd-10 text-center'],
      'no_of_duta' => ['value' => 'Bilangan DUTA terlibat', 'class' => 'wd-10'],
      'exhibit_booth' => ['value' => 'Pameran', 'class' => 'wd-10'],
      'no_of_speech' => ['value' => 'Ceramah', 'class' => 'wd-10'],
      'interview_title' => ['value' => 'Wawancara', 'class' => 'wd-10'],
      'social_topic' => ['value' => 'Media Sosial', 'class' => 'wd-10'],
      'picc_session' => ['value' => 'PICC', 'class' => 'wd-10'],
      'training_participant' => ['value' => 'TOT', 'class' => 'wd-10'],
      'article_title' => ['value' => 'Penerbitan Artikel', 'class' => 'wd-10'],
      'meeting_participant' => ['value' => 'Mesyuarat', 'class' => 'wd-10'],
      'no_of_other' => ['value' => 'Lain-lain Aktiviti', 'class' => 'wd-10'],
      'submission_at' => ['value' => 'Tarikh Hantar', 'class' => 'wd-10'],
      'action' => ['value' => 'Action', 'class' => 'wd-5'],
    ];

    return [
        '#theme' => 'listing',
        '#states' => $this->getStates(),
        '#tbheader' => $headers,
        '#attached' => [
          'library' => [
            'pf10/pf10',
            'pf10/listing',
            ]
          ],
      ];
  }

  public function ajaxListing(Request $request) {
    // Get current user
    $user = $this->currentUser;
    $user_id = $user->id();

    // Get roles as an array
    $roles = $user->getRoles();

    // To retrieve data from table User
    $account = \Drupal\user\Entity\User::load( $user_id );
    //$user_state = $account->get('field_state')->value;
    $user_state = NULL;

    $param['page'] = $request->get('page') ? (int) $request->get('page') - 1 : 0;
    $param['limit'] = $request->get('limit') ? : 0;
    $param['name'] = $request->get('name') ? : NULL;
    $param['state']= $user_state ? : NULL;
    $param['verify'] = FALSE; // show only unverified duta

    $headers = [
      'id' => 'Bil.',
      'report_id' => 'No. Rujukan',
      'program_date' =>'Tarikh Program',
      'state_code' =>'Negeri',
      'postcode' =>'Poskod',
      'budget_expense' =>'Jumlah Perbelanjaan',
      'rph_no' =>'No. RPh',
      'initiative' =>'Inisiatif DUTA',
      'no_of_duta' =>'Bilangan DUTA terlibat',
      'exhibit_booth' =>'Pameran',
      'no_of_speech' =>'Ceramah',
      'interview_title' =>'Wawancara',
      'social_topic' =>'Media Sosial',
      'picc_session' =>'PICC',
      'training_participant' =>'TOT',
      'article_title' =>'Penerbitan Artikel',
      'meeting_participant' =>'Mesyauarat',
      'no_of_other' =>'Lain-lain Aktiviti',
      'submission_at' => 'Tarikh Hantar',
      'action' => '',
    ];

    $fields = [
      'id',
      'report_id',
      'program_start_date',
      'program_end_date',
      'state_code',
      'postcode',
      'budget_expense',
      'rph_no',
      'initiative',
      'no_of_duta',
      'exhibit_booth',
      'no_of_speech',
      'interview_title',
      'social_topic',
      'picc_session',
      'training_participant',
      'article_title',
      'meeting_participant',
      'no_of_other',
      'submission_at',
    ];

    $getData = $this->getPF10Data($param, $fields);
    $totalData = $getData['total']; //count($getData);

    $currentPage = (int) $param['page'] + 1;
    $hasNextPage = $currentPage * $param['limit'] < $totalData ? TRUE : FALSE;
    $pager = $this->generatePager($currentPage, $totalData, $param['limit']);

    $html = '';
    if ($totalData > 0) {
      $n = 1;
      foreach ($getData['data'] as $rec) {
        $runningNo = (int) $param['page'] * (int) $param['limit'] + $n;
        $encoded = base64_encode($rec->report_id);

        $programDate = date('d M Y', strtotime($rec->program_start_date) );

        if ($rec->program_end_date) {
          $programDate .= ' ~ '. date('d M Y', strtotime($rec->program_end_date) );
        }

        $html .= '<tr rel="'. $encoded .'">';
        $html .= '<td class="ahref text-end">'. $runningNo .'.</td>';
        $html .= '<td class="ahref text-center">'. $rec->report_id .'</td>';
        $html .= '<td class="ahref text-center">'. $programDate .'</td>';
        $html .= '<td class="ahref text-start">'. $this->getCountryName($rec->state_code) .'</td>';
        $html .= '<td class="ahref text-center">'. $rec->postcode .'</td>';
        $html .= '<td class="ahref text-end">'. $rec->budget_expense .'</td>';
        $html .= '<td class="ahref text-start">'. $rec->rph_no .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->initiative == 'Y' ? 'Ya' : 'Tidak') .'</td>';
        $html .= '<td class="ahref text-end">'. $rec->no_of_duta .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->exhibit_booth ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->no_of_speech ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->interview_title ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->social_topic ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->picc_session ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->training_participant ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->article_title ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->meeting_participant ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. ($rec->no_of_other ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') .'</td>';
        $html .= '<td class="ahref text-center">'. date('d M Y', strtotime($rec->submission_at) ) .'</td>';
        $html .= '<td class="ahref text-center"><div class=""><i class="fas fa-pencil-alt"></i></div></td>';
        $html .= '</tr>';

        $n++;
      }

    }
    else {
      $column = count($fields);
      $html = '<tr><td colspan="'. $column .'">No records found.</td></tr>';
    }

    return new JsonResponse([
      'html' => $html,
      'total' => (int) $totalData,
      //'page' => (int) $currentPage,
      //'hasNextPage' => $hasNextPage,
      'pager' => $pager,
    ]);
  }

  public function getPF10Data(array &$param, array &$fields = []) {
    $connection = Database::getConnection();

    $limit = $param['limit'];
    $page = $param['page'];

    $offset = $page * $limit;

    // Main data query
    $query = $connection->select('custom_pf10', 'pf');

    if (!empty($fields) ) {
      $query->fields('pf', $fields);
    }
    else {
      $query->fields('pf');
    }

    if (!empty($param['report_id']) ) {
      $query->condition('report_id', '%' . $connection->escapeLike($param['report_id']) . '%', 'LIKE');
    }

    if (!empty($param['state']) ) {
      $allstates = explode(',', $param['state']);
      $query->condition('state_code', $allstates, 'IN');
    }

    $date_from = date('Y-m-01', strtotime("-6 months"));
    //$query->condition('submission_at', $date_from, '>=');
    $query->condition('completed', '1');

    $count_query = clone $query;
    $total = $count_query->countQuery()->execute()->fetchField();

    if ($limit) {
      $query->range($offset, $limit);
    }

    $results = $query
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll();

    $json = [
      'total' => $total,
      'data' => $results,
    ];

    return $json;
  }

  public function generatePager($currentPage, $totalData, $limit) {
    $totalPages = ceil($totalData / $limit);
    //$currentPage = max(1, min($currentPage, $totalPages) );

    // Build pager
    $pagerLinks = [];

    if ($currentPage > 1) {
      $pagerLinks[] = '<button id="prev_page">Previous</button>';
    }
    else {
      $pagerLinks[] = '<button id="prev_page" disabled>Previous</button>';
    }

    // Range: 2 before and 2 after current
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    for ($i = $start; $i <= $end; $i++) {
      $pagerLinks[] = $this->pagerLink($i, $i, $currentPage);
    }

    if ($currentPage < $totalPages) {
      $pagerLinks[] = '<button id="next_page">Next</button>';
    }
    else {
      $pagerLinks[] = '<button id="next_page" disabled>Next</button>';
    }

    return $pagerLinks;
  }

  private function pagerLink($text, $pageNo, $currentPage) {
    //$url = '/duta?page=' . $page_number;
    //return '<a href="' . $url . '">' . $text . '</a>';

    if ($pageNo < $currentPage) {
      $page = $currentPage - $pageNo;
      $button = '<button id="prev'. $page .'">'. $text .'</button>';
    }
    elseif ($pageNo == $currentPage) {
      $button = '<button class="active is-active" disabled>'. $text .'</button>';
    }
    elseif ($pageNo > $currentPage) {
      $page = $pageNo - $currentPage;
      $button = '<button id="next'. $page .'">'. $text .'</button>';
    }

    return $button;
  }

  private function getRef($year) {
    $connection = Database::getConnection();
    $query = $connection->select('custom_pf10_reference', 'ref')
      ->fields('ref', ['entry'])
      ->condition('year', $year);

    $result = $query->execute()->fetchAssoc();

    if (!$result) {
      $connection->insert('custom_pf10_reference')
        ->fields([
          'year' => $year,
          'entry' => 0,
        ])
        ->execute();

      return 0;
    }
    else {
      return $result['entry'];
    }
  }

  private function updateRef($year, $entry) {
    $connection = Database::getConnection();
    $connection->update('custom_pf10_reference')
      ->fields([
        'entry' => $entry,
      ])
      ->condition('year', $year)
      ->execute();
  }

  private function getStates() {
    $master = \Drupal::service('pf10.master_data_service');

    return $master->getStates();
  }

  private function getCountryName($state_code) {
    $master = \Drupal::service('dkua.master_data_service');

    return $master->getCountryName($state_code);
  }

  private function optionFacility($state_code = NULL) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getFacilityByState($state_code);

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->facility_code ] = ucwords($rec->facility);
    }

    return $lists;
  }

  public function ajaxDataFacility(Request $request) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getFacilityByState($request->get('state_code') );

    $html = '<option value="">- Select -</option>';

    foreach ($records as $rec) {
      $html .= '<option value="'. $rec->facility_code .'">'. $rec->facility .'</option>';
    }

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
    ]);
  }

  public function ajaxDataFacilityByPTJ(Request $request) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getFacilityByPTJ($request->get('ptj_code') );

    $html = '<option value="">- Select -</option>';

    foreach ($records as $rec) {
      $html .= '<option value="'. $rec->facility_code .'">'. $rec->facility .'</option>';
    }

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
      'facility_code' => count($records) == 1 ? $records[0]->facility_code : NULL,
    ]);
  }

  public function ajaxDataPTJ(Request $request) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getPTJByState($request->get('state_code') );

    $html = '<option value="">- Select -</option>';

    foreach ($records as $rec) {
      $html .= '<option value="'. $rec->ptj_code .'">'. $rec->ptj .'</option>';
    }

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
    ]);
  }

  public function ajaxDataPTJbyFacility(Request $request) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getPTJByFacility($request->get('facility_code') );

    $html = '<option value="">- Select -</option>';

    foreach ($records as $rec) {
      $html .= '<option value="'. $rec->ptj_code .'">'. $rec->ptj .'</option>';
    }

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
      'ptj_code' => count($records) == 1 ? $records[0]->ptj_code : NULL,
    ]);
  }

  private function insertChannel($value, $channel_type) {
    $connection = Database::getConnection();
    $driver = $connection->driver();

    $query = $connection->select('custom_channel', 'c')
      ->fields('c');

    if ($driver === 'pgsql') {
      $query->condition('channel_name', $value, 'ILIKE');
    }
    else {
      $query->condition('channel_name', $value, 'LIKE');
    }

    $result = $query->execute()->fetchAssoc();

    if (!$result) {
      $connection->insert('custom_channel')
        ->fields([
          'channel_type' => $channel_type,
          'channel_name' => $value,
          'created_at' => date('Y-m-d H:i:s'),
        ])
        ->execute();
    }
    else {}
  }

  private function optionChannel($channel_type = NULL) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getChannel($channel_type);

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->channel_name ] = ucwords($rec->channel_name);
    }

    return $lists;
  }

  public function ajaxDataChannel(Request $request) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getChannel($request->get('channel_type') );

    $html = '<option value="">- Select -</option>';

    foreach ($records as $rec) {
      $html .= '<option value="'. $rec->channel_name .'">'. $rec->channel_name .'</option>';
    }

    $html .= '<option value="other">Lain-lain</option>';

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
    ]);
  }

  public function ajaxDataChannel2(Request $request) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getChannel($request->get('channel_type') );

    $html = '<option value="">- Select -</option>';

    foreach ($records as $rec) {
      $html .= '<option value="'. $rec->id .'">'. $rec->channel_name .'</option>';
    }

    $html .= '<option value="other">Lain-lain</option>';

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
    ]);
  }

  private function insertSocialPlatform($value) {
    $connection = Database::getConnection();
    $driver = $connection->driver();

    $query = $connection->select('custom_social_platform', 'sp')
      ->fields('sp');

    if ($driver === 'pgsql') {
      $query->condition('platform_name', $value, 'ILIKE');
    }
    else {
      $query->condition('platform_name', $value, 'LIKE');
    }

    $result = $query->execute()->fetchAssoc();

    if (!$result) {
      $connection->insert('custom_social_platform')
        ->fields([
          'platform_name' => $value,
          'created_at' => date('Y-m-d H:i:s'),
        ])
        ->execute();
    }
    else {}
  }

  private function optionSocialPlatform($channel_type = NULL) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getSocialPlatform($channel_type);

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->platform_name ] = ucwords($rec->platform_name);
    }

    return $lists;
  }

  private function insertPublisher($value) {
    $connection = Database::getConnection();
    $driver = $connection->driver();

    $query = $connection->select('custom_publisher', 'p')
      ->fields('p');

    if ($driver === 'pgsql') {
      $query->condition('publisher_name', $value, 'ILIKE');
    }
    else {
      $query->condition('publisher_name', $value, 'LIKE');
    }

    $result = $query->execute()->fetchAssoc();

    if (!$result) {
      $connection->insert('custom_publisher')
        ->fields([
          'publisher_name' => $value,
          'created_at' => date('Y-m-d H:i:s'),
        ])
        ->execute();
    }
    else {}
  }

  private function optionPublisher() {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getPublisher();

    $lists = [];

    foreach ($records as $rec) {
      $lists[ $rec->publisher_name ] = ucwords($rec->publisher_name);
    }

    return $lists;
  }

  public function ajaxDataPawe(Request $request) {
    $master = \Drupal::service('pf10.master_data_service');

    $records = $master->getPawe($request->get('state_code') );

    $html = '<option value="">- Select -</option>';

    foreach ($records as $rec) {
      $html .= '<option value="'. $rec->id .'">'. $rec->pawe_name .'</option>';
    }

    return new JsonResponse([
      'status' => '01',
      'html' => $html,
    ]);
  }

  private function getFacilityName($facility_code) {
    $master = \Drupal::service('pf10.master_data_service');

    return $master->getFacilityName($facility_code);
  }

  private function getPTJName($ptj_code) {
    $master = \Drupal::service('pf10.master_data_service');

    return $master->getFacilityName($ptj_code);
  }


  private function getLocality() {
    $connection = Database::getConnection();

    $query = $connection->select('custom_facility_ptj', 'fp');

    // Join with custom_state_district table
    $query->leftJoin('custom_state_district', 'sd', 'fp.locality = sd.state_code');

    // Select distinct locality and state
    //$query->fields('fp', ['locality']);
    $query->addField('fp', 'locality');
    $query->addField('sd', 'state');

    // Make sure results are distinct
    $query->distinct();

    return $query->execute()->fetchAll();
  }

  private function insertCollaboration($value) {
    $connection = Database::getConnection();
    $query = $connection->select('custom_collaboration', 'c')
      ->fields('c');

    //$result = $query->execute()->fetchAssoc();
    $count = $query->countQuery()->execute()->fetchField();

    $result = $query->condition('collab_name', $value, 'LIKE')
      ->execute()->fetchAssoc();

    if (!$result) {
      $insert = $connection->insert('custom_collaboration')
        ->fields([
          'collab_code' => 'OTH'. ($count + 1),
          'collab_name' => $value,
          'created_at' => date('Y-m-d H:i:s'),
        ])
        ->execute();

      return 'OTH'. ($count + 1);
    }
    else {
      return $result['collab_code'];
    }
  }

  private function getCollaborationName($collab_code) {
    $master = \Drupal::service('pf10.master_data_service');
  
    return $master->getCollaborationName($collab_code);
  }

  private function getPAWEName($pawe_code) {
    $master = \Drupal::service('pf10.master_data_service');
  
    return $master->getPAWEName($pawe_code);
  }


}
