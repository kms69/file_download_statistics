<?php

namespace Drupal\file_download_statistics\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileUriFormatter;

/**
 * Formatter to render the file URI with download count prefix.
 *
 * @FieldFormatter(
 *   id = "file_uri_download_count",
 *   label = @Translation("File URI with Download Count"),
 *   field_types = {
 *     "uri",
 *     "file_uri",
 *   }
 * )
 */
class FileDownloadStatisticsFileUriFormatter extends FileUriFormatter {

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    if (!\Drupal::config('file_download_statistics.settings')->get('count_file_downloads')) {
      return parent::viewValue($item);
    }
    $value = $item->value;
    $value = str_replace('private://', 'private://download-counter/', $value);
    if ($this->getSetting('file_download_path')) {
      // @todo Wrap in file_url_transform_relative(). This is currently
      // impossible. See BaseFieldFileFormatterBase::viewElements(). Fix in
      // https://www.drupal.org/node/2646744.
      $value = file_create_url($value);
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if (\Drupal::config('file_download_statistics.settings')->get('count_file_downloads')) {
      return parent::isApplicable($field_definition) && $field_definition->getName() === 'uri';
    }
    return FALSE;
  }

}
