<?php

namespace Drupal\file_download_statistics\Plugin\Block;

use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file_download_statistics\FileDownloadStatisticsStorageInterface;

/**
 * Provides a 'Popular file downloads' block.
 *
 * @Block(
 *   id = "file_download_statistics_popular_block",
 *   admin_label = @Translation("Popular file downloads")
 * )
 */
class FileDownloadStatisticsPopularBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The storage for statistics.
   *
   * @var \Drupal\file_download_statistics\FileDownloadStatisticsStorageInterface
   */
  protected $statisticsStorage;

  /**
   * The Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an FileDownloadStatisticsPopularBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\file_download_statistics\FileDownloadStatisticsStorageInterface $statistics_storage
   *   The storage for statistics.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, FileDownloadStatisticsStorageInterface $statistics_storage, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->statisticsStorage = $statistics_storage;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('file_download_statistics.storage'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'top_day_num' => 0,
      'top_all_num' => 0,
      'last_download' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if (\Drupal::config('file_download_statistics.settings')->get('count_file_downloads')) {
      return AccessResult::allowedIfHasPermission($account, 'view file download statistics');
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // Popular content block settings.
    $numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 30, 40];
    $numbers = ['0' => $this->t('Disabled')] + array_combine($numbers, $numbers);
    $form['file_download_statistics_block_top_day_num'] = [
      '#type' => 'select',
      '#title' => $this->t("Number of day's top downloads to display"),
      '#default_value' => $this->configuration['top_day_num'],
      '#options' => $numbers,
      '#description' => $this->t('How many downloaded files to display in "day" list.'),
    ];
    $form['file_download_statistics_block_top_all_num'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of all time downloads to display'),
      '#default_value' => $this->configuration['top_all_num'],
      '#options' => $numbers,
      '#description' => $this->t('How many downloaded files to display in "all time" list.'),
    ];
    $form['file_download_statistics_block_last_download'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of most recent downloaded files to display'),
      '#default_value' => $this->configuration['last_download'],
      '#options' => $numbers,
      '#description' => $this->t('How many downloaded files to display in "recently downloaded" list.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['top_day_num'] = $form_state->getValue('file_download_statistics_block_top_day_num');
    $this->configuration['top_all_num'] = $form_state->getValue('file_download_statistics_block_top_all_num');
    $this->configuration['last_download'] = $form_state->getValue('file_download_statistics_block_last_download');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = [];

    if ($this->configuration['top_day_num'] > 0) {
      $fids = $this->statisticsStorage->fetchAll('daycount', $this->configuration['top_day_num']);
      if ($fids) {
        $content['top_day'] = $this->filenameList($fids, $this->t("Today's:"));
        $content['top_day']['#suffix'] = '<br />';
      }
    }

    if ($this->configuration['top_all_num'] > 0) {
      $fids = $this->statisticsStorage->fetchAll('totalcount', $this->configuration['top_all_num']);
      if ($fids) {
        $content['top_all'] = $this->filenameList($fids, $this->t('All time:'));
        $content['top_all']['#suffix'] = '<br />';
      }
    }

    if ($this->configuration['last_download'] > 0) {
      $fids = $this->statisticsStorage->fetchAll('timestamp', $this->configuration['last_download']);
      $content['top_last'] = $this->filenameList($fids, $this->t('Last downloaded:'));
      $content['top_last']['#suffix'] = '<br />';
    }

    return $content;
  }

  /**
   * Generates the ordered array of file links for build().
   *
   * @param int[] $fids
   *   An ordered array of file ids.
   * @param string $title
   *   The title for the list.
   *
   * @return array
   *   A render array for the list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function filenameList(array $fids, $title) {
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);

    $items = [];
    foreach ($files as $file) {
      $item = [
        '#type' => 'link',
        '#title' => $file->getFilename(),
        '#url' => Url::fromUri(file_create_url($file->getFileUri())),
      ];
      $this->renderer->addCacheableDependency($item, $file);
      $items[] = $item;
    }

    return [
      '#theme' => 'item_list__node',
      '#items' => $items,
      '#title' => $title,
      '#cache' => [
        'tags' => $this->entityTypeManager->getDefinition('file')->getListCacheTags(),
      ],
    ];
  }

}
