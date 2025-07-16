<?php

namespace Drupal\pf10\Service;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;

class MasterDataService {

  protected Connection $connection;

  /**
   * Create a constant connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Get all state list.
   */
  public function getStates() {
    $query = $this->connection->select('custom_state_district', 'sd')
      ->fields('sd', ['state', 'state_code']);

    $query->orderBy('state', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get country data.
   */
  public function getCountryName($state_code) {
    $query = $this->connection->select('custom_state_district', 'sd')
      ->fields('sd', ['state'])
      ->condition('state_code', $state_code);

    $result = $query->execute()->fetchField();

    return $result;
  }

  /**
   * Get all facility list by state.
   */
  public function getFacilityByState($state_code = NULL) {
    $query = $this->connection->select('custom_facility_ptj', 'fp')
      ->fields('fp', ['facility_code', 'facility'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    if ($state_code) {
      $query->condition('state_code', $state_code);
    }

    $query->orderBy('facility', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get all facility list by ptj.
   */
  public function getFacilityByPTJ($ptj_code = NULL) {
    $query = $this->connection->select('custom_facility_ptj', 'fp')
      ->fields('fp', ['facility_code', 'facility'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    if ($ptj_code) {
      $query->condition('ptj_code', $ptj_code);
    }

    $query->orderBy('facility', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get all facility list by locality.
   */
  public function getFacilityByLocality($locality = NULL) {
    $query = $this->connection->select('custom_facility_ptj', 'fp')
      ->fields('fp', ['facility_code', 'facility'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    if ($locality) {
      $query->condition('locality', $locality);
    }

    $query->orderBy('facility', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get facility data.
   */
  public function getFacilityName($facility_code) {
    $query = $this->connection->select('custom_facility_ptj', 'fp')
      ->fields('fp', ['facility'])
      ->condition('facility_code', $facility_code);

    $result = $query->execute()->fetchField();

    return $result;
  }

  /**
   * Get all PTJ list by state.
   */
  public function getPTJByState($state_code = NULL) {
    $query = $this->connection->select('custom_facility_ptj', 'fp')
      ->fields('fp', ['ptj_code', 'ptj'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    if ($state_code) {
      $query->condition('state_code', $state_code);
    }

    $query->orderBy('ptj', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get all PTJ list by facility.
   */
  public function getPTJByFacility($facility_code = NULL) {
    $query = $this->connection->select('custom_facility_ptj', 'fp')
      ->fields('fp', ['ptj_code', 'ptj'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    if ($facility_code) {
      $query->condition('facility_code', $facility_code);
    }

    $query->orderBy('ptj', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get PTJ data.
   */
  public function getPTJName($ptj_code) {
    $query = $this->connection->select('custom_facility_ptj', 'fp')
      ->fields('fp', ['ptj'])
      ->condition('ptj_code', $ptj_code);

    $result = $query->execute()->fetchField();

    return $result;
  }

  /**
   * Get all Channel list by channel_type.
   */
  public function getChannel($channel_type = NULL) {
    $query = $this->connection->select('custom_channel', 'c')
      ->fields('c', ['id', 'channel_type', 'channel_name'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    if ($channel_type) {
      $query->condition('channel_type', $channel_type);
    }

    $query->orderBy('channel_type', 'ASC')
      ->orderBy('channel_name', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get Channel data.
   */
  public function getChannelName($channel_code) {
    $query = $this->connection->select('custom_channel', 'c')
      ->fields('c', ['channel_name'])
      ->condition('id', $channel_code);

    $result = $query->execute()->fetchField();

    return $result;
  }

  /**
   * Get all Social Platform list.
   */
  public function getSocialPlatform() {
    $query = $this->connection->select('custom_social_platform', 'sp')
      ->fields('sp', ['id', 'platform_name'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    $query->orderBy('platform_name', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get all Publisher list
   */
  public function getPublisher() {
    $query = $this->connection->select('custom_publisher', 'p')
      ->fields('p', ['id', 'publisher_name'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    $query->orderBy('publisher_name', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get Publisher data.
   */
  public function getPublisherName($publisher_code) {
    $query = $this->connection->select('custom_publisher', 'p')
      ->fields('p', ['publisher_name'])
      ->condition('id', $publisher_code);

    $result = $query->execute()->fetchField();

    return $result;
  }

  /**
   * Get all collaboration list.
   */
  public function getCollaboration() {
    $query = $this->connection->select('custom_collaboration', 'c')
      ->fields('c', ['id', 'collab_code', 'collab_name'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    $query->orderBy('collab_name', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get collaboration data.
   */
  public function getCollaborationName($collab_code) {
    $query = $this->connection->select('custom_collaboration', 'c')
      ->fields('c', ['collab_name'])
      ->condition('collab_code', $collab_code);

    $result = $query->execute()->fetchField();

    return $result;
  }

  /**
   * Get all pawe list
   */
  public function getPawe($state_code = NULL) {
    $query = $this->connection->select('custom_pawe', 'p')
      ->fields('p', ['id', 'pawe_code', 'pawe_name'])
      //->condition('deleted_at', NULL, 'IS NULL');
      ->isNull('deleted_at');

    if ($state_code) {
      $query->condition('state_code', $state_code);
    }

    $query->orderBy('pawe_name', 'ASC')
      ->distinct();

    //$results = $query->execute()->fetchCol();
    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Get pawe data.
   */
  public function getPAWEName($pawe_code) {
    $query = $this->connection->select('custom_pawe', 'p')
      ->fields('p', ['pawe_name'])
      ->condition('id', $pawe_code);

    $result = $query->execute()->fetchField();

    return $result;
  }

}
