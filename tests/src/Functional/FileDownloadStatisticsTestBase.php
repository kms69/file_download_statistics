<?php

namespace Drupal\Tests\file_download_statistics\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Defines a base class for testing the Statistics module.
 */
abstract class FileDownloadStatisticsTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'block', 'ban', 'file_download_statistics'];

  /**
   * User with permissions to ban IP's.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $blockingUser;

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
