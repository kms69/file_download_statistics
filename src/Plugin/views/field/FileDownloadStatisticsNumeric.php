<?php

namespace Drupal\file_download_statistics\Plugin\views\field;

use Drupal\views\Plugin\views\field\NumericField;
use Drupal\Core\Session\AccountInterface;

/**
 * Field handler to display numeric values from the statistics module.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("file_download_statistics_numeric")
 */
class FileDownloadStatisticsNumeric extends NumericField {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('view file download statistics');
  }

}
