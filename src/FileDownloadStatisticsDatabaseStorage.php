<?php

namespace Drupal\file_download_statistics;

use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the default database storage backend for statistics.
 */
class FileDownloadStatisticsDatabaseStorage implements FileDownloadStatisticsStorageInterface {

  /**
   * The database connection used.
   *
   * @var \Drupal\Core\Database\Connection
   */

  protected $connection;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs the statistics storage.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection for the node view storage.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current RequestStack.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(Connection $connection, StateInterface $state, RequestStack $request_stack, AccountInterface $current_user) {
    $this->connection = $connection;
    $this->state = $state;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function recordDownload($fid, $uid = NULL) {
    if (empty($uid)) {
      $uid = $this->currentUser->id();
    }
    try {
      return (bool) $this->connection
        ->merge('file_download_statistics')
        ->key('fid', $fid)
        ->fields([
          'daycount' => 1,
          'totalcount' => 1,
          'timestamp' => $this->getRequestTime(),
          'uid' => $uid,
        ])
        ->expression('daycount', 'daycount + 1')
        ->expression('totalcount', 'totalcount + 1')
        ->execute();
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetchDownloads($ids) {
    $downloads = $this->connection
      ->select('file_download_statistics', 'ds')
      ->fields('ds', ['totalcount', 'daycount', 'timestamp', 'uid'])
      ->condition('fid', $ids, 'IN')
      ->execute()
      ->fetchAll();
    foreach ($downloads as $id => $download) {
      $downloads[$id] = new FileDownloadStatisticsCountResult($download->totalcount, $download->daycount, $download->timestamp, $download->uid);
    }
    return $downloads;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchDownload($id) {
    $views = $this->fetchDownloads([$id]);
    return reset($views);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAll($order = 'totalcount', $limit = 5) {
    assert(in_array($order, ['totalcount', 'daycount', 'timestamp']), "Invalid order argument.");

    $query = $this->connection
      ->select('file_download_statistics', 'ds')
      ->fields('ds', ['fid'])
      ->orderBy($order, 'DESC')
      ->range(0, $limit);
    if ($order == 'daycount') {
      $query->condition('daycount', 0, '>');
    }
    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDownloads($fid) {
    return (bool) $this->connection
      ->delete('file_download_statistics')
      ->condition('fid', $fid)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllDownloads() {
    return (bool) $this->connection
      ->delete('file_download_statistics')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function resetDayCount() {
    $statistics_timestamp = $this->state->get(' file_download_statistics.day_timestamp') ?: 0;
    if (($this->getRequestTime() - $statistics_timestamp) >= 86400) {
      $this->state->set(' file_download_statistics.day_timestamp', $this->getRequestTime());
      $this->connection->update('file_download_statistics')
        ->fields(['daycount' => 0])
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function maxTotalCount() {
    $query = $this->connection->select('file_download_statistics', 'ds');
    $query->addExpression('MAX(totalcount)');
    return (int) $query->execute()->fetchField();
  }

  /**
   * Get current request time.
   *
   * @return int
   *   Unix timestamp for current server request time.
   */
  protected function getRequestTime() {
    return $this->requestStack->getCurrentRequest()->server->get('REQUEST_TIME');
  }

  /**
   * Returns most downloaded files of all time, today, or the last one.
   *
   * @param string $dbfield
   *   The database field to use, one of:
   *   - 'totalcount': Integer that shows the top downloaded file of all time.
   *   - 'daycount': Integer that shows the top downloaded file for today.
   *   - 'timestamp': Integer that shows only the last downloaded file.
   * @param int $dbrows
   *   The number of rows to be returned.
   *
   * @return mixed
   *   A query result containing the file ID, filename, user ID and the username
   *   of the user who downloaded the file, or FALSE if the query
   *   could not be executed correctly.
   */
  function getFilenameList($dbfield, $dbrows) {
    if (in_array($dbfield, ['totalcount', 'daycount', 'timestamp'])) {
      $query = $this->connection->select('file_managed', 'f');
      $query->addTag('file_access');
      $query->join('file_download_statistics', 's', 'f.fid = s.fid');
      $query->join('users_field_data', 'u', 's.uid = u.uid');

      return $query
        ->fields('f', ['fid', 'filename'])
        ->fields('u', ['uid', 'name'])
        ->condition($dbfield, 0, '<>')
        ->condition('f.status', 1)
        ->orderBy($dbfield, 'DESC')
        ->range(0, $dbrows)
        ->execute();
    }
    return FALSE;
  }
}
