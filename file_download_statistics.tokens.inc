<?php

/**
 * @file
 * Builds placeholder replacement tokens for file download statistics.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function file_download_statistics_token_info() {
  if (!\Drupal::config('file_download_statistics.settings')->get('count_file_downloads')) {
    return [];
  }
  $file['total-count'] = [
    'name' => t("Number of downloads"),
    'description' => t("The number of visitors who have downloaded the file."),
  ];
  $file['day-count'] = [
    'name' => t("Downloads today"),
    'description' => t("The number of visitors who have downloaded the file today."),
  ];
  $file['last-view'] = [
    'name' => t("Last download"),
    'description' => t("The date on which a visitor last downloaded the file."),
    'type' => 'date',
  ];
  $file['last-user'] = [
    'name' => t("Last user"),
    'description' => t("The last visitor who downloaded the file."),
  ];

  return [
    'tokens' => ['file' => $file],
  ];
}

/**
 * Implements hook_tokens().
 */
function file_download_statistics_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $token_service = \Drupal::token();

  $replacements = [];

  if ($type == 'file' & !empty($data['file'])) {
    $file = $data['file'];

    foreach ($tokens as $name => $original) {
      if ($name == 'total-count') {
        $statistics = file_download_statistics_get($file->id());
        $replacements[$original] = $statistics['totalcount'];
      }
      elseif ($name == 'day-count') {
        $statistics = file_download_statistics_get($file->id());
        $replacements[$original] = $statistics['daycount'];
      }
      elseif ($name == 'last-view') {
        $statistics = file_download_statistics_get($file->id());
        $replacements[$original] = \Drupal::service('date.formatter')->format($statistics['timestamp']);
      }
      elseif ($name == 'last-user') {
        $statistics = file_download_statistics_get($file->id());
        $replacements[$original] = $statistics['uid'];
      }
    }

    if ($created_tokens = $token_service->findWithPrefix($tokens, 'last-view')) {
      $statistics = file_download_statistics_get($file->id());
      $replacements += $token_service->generate('date', $created_tokens, ['date' => $statistics['timestamp']], $options, $bubbleable_metadata);
    }
  }

  return $replacements;
}
