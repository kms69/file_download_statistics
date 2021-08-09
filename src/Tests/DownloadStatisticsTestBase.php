<?php

namespace Drupal\file_download_statistics\Tests;

use Drupal\Tests\BrowserTestBase;

/**
 * Defines a base class for testing the File DownloadStatistics module.
 */
abstract class DownloadStatisticsTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'block', 'ban', 'statistics'];

  /**
   * User with permissions to ban IP's.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $blockingUser;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {

    parent::setUp();
    // Create Basic page node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    }

    // Create user.
    $this->blockingUser = $this->drupalCreateUser([
      'access administration pages',
      'access site reports',
      'ban IP addresses',
      'administer blocks',
      'administer download statistics',
      'administer users',
    ]);
    $this->drupalLogin($this->blockingUser);
  }

}
