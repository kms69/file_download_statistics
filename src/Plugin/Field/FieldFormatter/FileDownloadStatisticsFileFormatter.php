<?php

namespace Drupal\file_download_statistics\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\GenericFileFormatter;

/**
 * Plugin implementation of the 'counted_downloads_file' formatter.
 *
 * @FieldFormatter(
 *  id = "file_downloader_with_counter",
 *  label = @Translation("File with statistics counter"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileDownloadStatisticsFileFormatter extends GenericFileFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    if (\Drupal::config('file_download_statistics.settings')->get('count_file_downloads')) {
      foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
        $elements[$delta]['#file']->countDownloads = TRUE;
      }
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if (\Drupal::config('file_download_statistics.settings')->get('count_file_downloads')) {
      return $field_definition->getFieldStorageDefinition()
          ->getSetting('target_type') === 'file';
    }
    return FALSE;
  }

}
