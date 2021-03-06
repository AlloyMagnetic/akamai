<?php

namespace Drupal\akamai\Form;

use Drupal\akamai\AkamaiClientFactory;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A simple form for testing the Akamai integration, or doing manual clears.
 */
class CacheControlForm extends FormBase {

  /**
   * The akamai client.
   *
   * @var \Drupal\akamai\AkamaiClientInterface
   */
  protected $akamaiClient;

  /**
   * A path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $aliasManager;

  /**
   * A messenger interface.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new CacheControlForm.
   *
   * @param \Drupal\akamai\AkamaiClientFactory $factory
   *   The akamai client factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   */
  public function __construct(AkamaiClientFactory $factory, MessengerInterface $messenger) {
    $this->akamaiClient = $factory->get();
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('akamai.client.factory'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'akamai_cache_control_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('akamai.settings');
    $version = $this->akamaiClient->getPluginId();

    $settings_link = Url::fromRoute('akamai.settings');
    $settings_link = Link::fromTextAndUrl($settings_link->getInternalPath(), $settings_link)->toString();
    $paths_description = $this->t(
      'Enter one URL or CPCode per line. URL entries should be relative to the basepath
      (e.g. node/1, content/pretty-title, sites/default/files/some/image.png).
      Your basepath for Akamai is set as :basepath. If you would like to change
      it, you can do so at @settings.',
      [
        ':basepath' => $config->get('basepath'),
        '@settings' => $settings_link,
      ]
    );

    $form['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths/URLs/CPCodes'),
      '#description' => $paths_description,
      '#required' => TRUE,
      '#default_value' => $form_state->get('paths'),
    ];

    $domain_override_default = $form_state->get('domain_override') ?: key(array_filter($config->get('domain')));
    $form['domain_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Domain'),
      '#options' => [
        'production' => $this->t('Production'),
        'staging' => $this->t('Staging'),
      ],
      '#default_value' => $domain_override_default,
      '#description' => $this->t('The Akamai domain to use for cache clearing.  Defaults to the Domain setting from the settings page.'),
    ];

    $action_default = $form_state->get('action') ?: $config->get("action_{$version}");
    $actions = $this->akamaiClient->validActions();
    $form['action'] = [
      '#type' => 'radios',
      '#title' => $this->t('Clearing Action Type'),
      '#options' => array_combine($actions, array_map(function ($action) {
        return Unicode::ucwords($action);
      }, $actions)),
      '#default_value' => key(array_filter($action_default)),
      '#description' => $this->t('<b>Remove:</b> Purge the content from Akamai edge server caches. The next time the edge server receives a request for the content, it will retrieve the current version from the origin server. If it cannot retrieve a current version, it will follow instructions in your edge server configuration.<br/><br/><b>Invalidate:</b> Mark the cached content as invalid. The next time the Akamai edge server receives a request for the content, it will send an HTTP conditional get (If-Modified-Since) request to the origin. If the content has changed, the origin server will return a full fresh copy; otherwise, the origin normally will respond that the content has not changed, and Akamai can serve the already-cached content.<br/><br/><b>Note that <em>Remove</em> can increase the load on the origin more than <em>Invalidate</em>.</b> With <em>Invalidate</em>, objects are not removed from cache and full objects are not retrieved from the origin unless they are newer than the cached versions.'),
    ];

    $form['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Purge Method'),
      '#options' => [
        'url'    => $this->t('URL'),
        'cpcode' => $this->t('Content Provider Code'),
      ],
      '#default_value' => $form_state->get('method') ?: 'url',
      '#description' => $this->t('The Akamai API method to use for cache purge requests.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Refreshing Content'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $objects = explode(PHP_EOL, $form_state->getValue('paths'));
    $method = $form_state->getValue('method');

    if ($method == 'url') {
      foreach ($objects as $path) {
        // Remove any leading slashes so we can control them later.
        if ($path[0] === '/') {
          $path = ltrim($path, '/');
        }
        $path = trim($path);
        if (UrlHelper::isExternal($path)) {
          $full_urls[] = $path;
        }
        else {
          $url = Url::fromUserInput('/' . $path);
          if ($url->isRouted() || is_file($path)) {
            $paths_to_clear[] = $path;
          }
          else {
            $invalid_paths[] = $path;
          }
        }
      }
    }
    // Handle cpcodes.
    else {
      $paths_to_clear = $objects;
    }

    if (!empty($full_urls)) {
      $form_state->setErrorByName('paths', $this->t('Please enter only relative paths, not full URLs.'));
    }

    if (!empty($invalid_paths)) {
      $paths = implode(",", $invalid_paths);
      $message = $this->formatPlural(
        count($invalid_paths),
        'The \'@paths\' path is invalid and does not exist on the site. Please provide at least one valid URL for purging.',
        '@paths paths are invalid and do not exist on the site. Please provide valid URLs for purging.',
        ['@paths' => $paths]
      );
      $form_state->setErrorByName('paths', $message);
    }

    if (empty($paths_to_clear)) {
      $form_state->setErrorByName('paths', $this->t('Please enter at least one valid object for %method purging.', ['%method' => $method]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue('action');
    $method = $form_state->getValue('method');
    $objects = explode(PHP_EOL, $form_state->getValue('paths'));
    $urls_to_clear = [];

    if ($method == 'url') {
      foreach ($objects as $path) {
        $urls_to_clear[] = trim('/' . $path);
      }
    }
    else {
      $urls_to_clear = $objects;
      $this->akamaiClient->setType('cpcode');
    }

    $this->akamaiClient->setAction($action);
    $this->akamaiClient->setDomain($form_state->getValue('domain_override'));

    if ($method == 'url') {
      $response = $this->akamaiClient->purgeUrls($urls_to_clear);
    }
    // Handle cpcodes.
    else {
      $response = $this->akamaiClient->purgeCpCodes($urls_to_clear);
    }

    if ($response) {
      $this->messenger->addMessage($this->t('Requested :action of the following objects: :objects',
        [':action' => $action, ':objects' => implode(', ', $urls_to_clear)])
      );
    }
    else {
      $this->messenger->addError($this->t('There was an error clearing the cache. Check logs for further detail.'));
    }
  }

}
