<?php

namespace Drupal\akamai\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens for config changes to Akamai credentials.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * Validates Akamai credentials upstream on config changes.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   A config change event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    // Check for changes to the Akamai config credentials, and validate them
    // with the upstream service.
    $saved_config = $event->getConfig();
    if ($saved_config->getName() == 'akamai.settings') {
      if (
          $event->isChanged('storage_method') ||
          $event->isChanged('rest_api_url') ||
          $event->isChanged('client_token') ||
          $event->isChanged('client_secret') ||
          $event->isChanged('access_token')
      ) {
        \Drupal::state()->set('akamai.valid_credentials', \Drupal::service('akamai.client.factory')->get()->isAuthorized());
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    return $events;
  }

}
