<?php

namespace Drupal\file_download_statistics;

/**
 * Provides an interface defining Download Statistics Storage.
 *
 * Stores the downloads per day, total downloads and timestamp of last download
 * for files.
 */
interface FileDownloadStatisticsStorageInterface {

  /**
   * Count a file download.
   *
   * @param int $id
   *   The ID of the file entity to count.
   * @param int $uid
   *   The user ID.
   *
   * @return bool
   *   TRUE if the entity view has been counted.
   */
  public function recordDownload($id, $uid);

  /**
   * Returns the number of times files have been downloaded.
   *
   * @param array $ids
   *   An array of IDs of files to fetch the downloads for.
   *
   * @return \Drupal\file_download_statistics\FileDownloadStatisticsCountResult[]
   *   An array of value objects representing the number of times each file
   *   has been downloaded. The array is keyed by entity ID. If an ID does not
   *   exist, it will not be present in the array.
   */
  public function fetchDownloads($ids);

  /**
   * Returns the number of times a single file has been downloaded.
   *
   * @param int $id
   *   The ID of the entity to fetch the downloads for.
   *
   * @return \Drupal\file_download_statistics\FileDownloadStatisticsCountResult|false
   *   If the file exists, a value object representing the number of times if
   *   has been downloaded. If it does not exist, FALSE is returned.
   */
  public function fetchDownload($id);

  /**
   * Returns the number of times a file has been downloaded.
   *
   * @param string $order
   *   The counter name to order by:
   *   - 'totalcount' The total number of downloads.
   *   - 'daycount' The number of downloads today.
   *   - 'timestamp' The unix timestamp of the last download.
   * @param int $limit
   *   The number of file IDs to return.
   *
   * @return array
   *   An ordered array of entity IDs.
   */
  public function fetchAll($order = 'totalcount', $limit = 5);

  /**
   * Delete counts for all files.
   *
   * @return bool
   *   TRUE if all downloads have been deleted.
   */
  public function deleteAllDownloads();

  /**
   * Delete counts for a specific entity.
   *
   * @param int $fid
   *   The ID of the file which downloads data to delete.
   *
   * @return bool
   *   TRUE if the file downloads have been deleted.
   */
  public function deleteDownloads($fid);

  /**
   * Reset the day counter for all entities once every day.
   */
  public function resetDayCount();

  /**
   * Returns the highest 'totalcount' value.
   *
   * @return int
   *   The highest 'totalcount' value.
   */
  public function maxTotalCount();

}
