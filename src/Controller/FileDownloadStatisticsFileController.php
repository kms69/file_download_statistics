<?php

namespace Drupal\file_download_statistics\Controller;

use Drupal\system\FileDownloadController;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\file_download_statistics\FileDownloadStatisticsStorageInterface;

/**
 * Defines a controller to serve files with download count.
 */
class FileDownloadStatisticsFileController extends FileDownloadController {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The storage for download statistics.
   *
   * @var \Drupal\file_download_statistics\FileDownloadStatisticsStorageInterface
   */
  protected $statisticsStorage;

  /**
   * Constructs a FileDownloadStatisticsFileController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   ConfigFactory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   LoggerChannelFactory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\file_download_statistics\FileDownloadStatisticsStorageInterface $statistics_storage
   *   The storage for statistics.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, AccountInterface $current_user, FileDownloadStatisticsStorageInterface $statistics_storage) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->currentUser = $current_user;
    $this->statisticsStorage = $statistics_storage;
    $this->streamWrapperManager = \Drupal::service('stream_wrapper_manager');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('current_user'),
      $container->get('file_download_statistics.storage')
    );
  }

  /**
   * Handles private file transfers.
   *
   * Call modules that implement hook_file_download() to find out if a file is
   * accessible and what headers it should be transferred with. If one or more
   * modules returned headers the download will start with the returned headers.
   * If a module returns -1 an AccessDeniedHttpException will be thrown. If the
   * file exists but no modules responded an AccessDeniedHttpException will be
   * thrown. If the file does not exist a NotFoundHttpException will be thrown.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The transferred file as response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the requested file does not exist.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see hook_file_download()
   */
  public function download(Request $request, $scheme = 'private') {

    $target = $request->query->get('file');
    if (file_exists($scheme . '://' . $target)) {
      return parent::download($request, $scheme);
    }

    $file_to_count = strpos($target, 'download-counter/') === 0;
    if (!$file_to_count) {
      return parent::download($request, $scheme);
    }
    // Remove the URL part that was used just for routing.
    $target = str_replace('download-counter/', '', $target);
    // Merge remaining path arguments into relative file path.
    $uri = $scheme . '://' . $target;
    if (\Drupal::service('stream_wrapper_manager')->isValidScheme($scheme) && file_exists($uri)) {
      // Let other modules provide headers and controls access to the file.
      $headers = $this->moduleHandler()->invokeAll('file_download', [$uri]);
      foreach ($headers as $result) {
        if ($result == -1) {
          throw new AccessDeniedHttpException();
        }
      }
      if (count($headers)) {
        $http_range = $request->server->get('HTTP_RANGE');
        // The first request comes with HTTP_RANGE empty.
        // Then a few more requests can come for the same file,
        // depending on the file size.
        if (empty($http_range)) {
          try {
            $file = $this->getFileByUri($uri);
            if (!$this->statisticsStorage->recordDownload($file->id(), $this->currentUser->id())) {
              $this->loggerFactory->get('file_download_statistics')
                ->error('Could not record Download Statistics for user #%uid, file %file', [
                  '%file' => $file->getFilename(),
                  '%uid' => $this->currentUser->id(),
                ]);
            }
          }
          catch (InvalidPluginDefinitionException $e) {
            $this->loggerFactory->get('file_download_statistics')
              ->error("Could not record Download Statistics.", Error::decodeException($e));
          }
        }
        // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
        // sets response as not cacheable if the Cache-Control header is not
        // already modified. We pass in FALSE for non-private schemes for the
        // $public parameter to make sure we don't change the headers.
        return new BinaryFileResponse($uri, 200, $headers, $scheme !== 'private');
      }

      throw new AccessDeniedHttpException();
    }

    throw new NotFoundHttpException();
  }

  /**
   * Returns File object for the valid uri.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return mixed
   *   Either \Drupal\file\FileInterface or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getFileByUri($uri) {
    // Get the file record based on the URI. If not in the database just return.
    /** @var \Drupal\file\FileInterface[] $files */
    $files = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => $uri]);
    if (count($files)) {
      foreach ($files as $item) {
        // Since some database servers sometimes use a case-insensitive
        // comparison by default, double check that the filename
        // is an exact match.
        if ($item->getFileUri() === $uri) {
          $file = $item;
          break;
        }
      }
    }
    return isset($file) ? $file : NULL;
  }

}
