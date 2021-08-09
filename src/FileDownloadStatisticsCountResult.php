<?php

namespace Drupal\file_download_statistics;

/**
 * Value object for passing file downloads statistic results.
 */
class FileDownloadStatisticsCountResult {

  /**
   * The Total Count.
   *
   * @var int
   */
  protected $totalCount;

  /**
   * The Day Count.
   *
   * @var int
   */
  protected $dayCount;

  /**
   * The timestamp.
   *
   * @var int
   */
  protected $timestamp;

  /**
   * The last user ID.
   *
   * @var int
   */
  protected $lastUserId;

  /**
   * FileDownloadStatisticsCountResult constructor.
   *
   * @param int $total_count
   *   The Total Count.
   * @param int $day_count
   *   The Day Count.
   * @param int $timestamp
   *   The timestamp.
   * @param int $uid
   *   The last user ID.
   */
  public function __construct($total_count, $day_count, $timestamp, $uid) {
    $this->totalCount = $total_count;
    $this->dayCount = $day_count;
    $this->timestamp = $timestamp;
    $this->lastUserId = $uid;
  }

  /**
   * Total number of times the file has been downloaded.
   *
   * @return int
   *   The Total Count.
   */
  public function getTotalCount() {
    return $this->totalCount;
  }

  /**
   * Total number of times the file has been downloaded "today".
   *
   * @return int
   *   The Day Count.
   */
  public function getDayCount() {
    return $this->dayCount;
  }

  /**
   * Timestamp of when the file was last downloaded.
   *
   * @return int
   *   The timestamp.
   */
  public function getTimestamp() {
    return $this->timestamp;
  }

  /**
   * User ID of the user who downloaded the last.
   *
   * @return int
   *   The last user ID.
   */
  public function getUserId() {
    return $this->lastUserId;
  }

}
