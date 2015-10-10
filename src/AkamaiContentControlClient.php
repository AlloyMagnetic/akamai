<?php
/**
 * @file
 * Contains Drupal\akamai\AkamaiContentControlClient.
 */

namespace Drupal\akamai;

use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * Provides a service to interact with the Akamai Content Control REST API.
 */
class AkamaiContentControlClient implements AkamaiContentControl {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  // A config object.
  protected $config;

  /**
   * Constructs an AkamaiContentControlClient object.
   *
   * @todo Inject logger
   * @todo Inject HTTPClient
   * @todo Inject config
   */
  public function __construct() {
    // @todo Inject the services.
    $this->httpClient = \Drupal::httpClient();
    $this->logger = \Drupal::logger('akamai');
    $this->config = \Drupal::config('akamai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function clearUrls(array $urls) {
    foreach ($urls as $url) {
      drupal_set_message($url);
      $this->purgeUrl($url);
      // $this->logger->info('Cleared URL {url}', array('url' => $url));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearUrl($url) {
    $this->clearUrls(array($url));
  }

  /**
   * Removes an object, based on URL path, from the Akamai cache.
   *
   * @param string $url
   *   The URL of the cached object to purge.
   *
   * @todo Incorporate invalidation as well as removing objects.
   */
  protected function purgeUrl($url) {

    // Set up parameters for the request. Note, arl requests define cache
    // objects by URL.
    $parameters = array(
      'type' => 'arl',
      'action' => 'remove',
      'domain' => $this->config->get('akamai_domain'),
      'objects' => array(
        $url,
      ),
    );

    $request = new Request('POST',
      $this->config->get('akamai_restapi_endpoint'),
      $parameters
    );

    try {
      $response = $this->httpClient->send($request);
    }
    catch (RequestException $e) {
      // @todo Log/notify these more cleanly.
      drupal_set_message(t('There was an error calling the Akamai CCU service.'), 'error');
      drupal_set_message($e->getRequest(), 'error');
      drupal_set_message($e->getResponse(), 'error');
      return;
    }

  }

}
