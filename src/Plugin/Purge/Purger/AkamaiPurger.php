<?php

/**
 * @file
 * Contains Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger.
 */

namespace Drupal\akamai\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\akamai\AkamaiClient;


/**
 * Akamai Purger.
 *
 * @PurgePurger(
 *   id = "akamai",
 *   label = @Translation("Akamai Purger"),
 *   description = @Translation("Provides a Purge service for Akamai CCU."),
 *   types = {"url", "everything"},
 *   configform = "Drupal\akamai\Form\AkamaiConfigForm",
 * )
 */
class AkamaiPurger extends PurgerBase implements PurgerInterface {


  // Web services client.
  protected $client;


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * Constructs a \Drupal\Component\Plugin\AkamaiPurger.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $config = $config->get('akamai.config');
    $this->client = new AkamaiClient($config);
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeHint() {
    // @todo Create a configurable max timeout.
    return 4.00;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    // @todo: Implement invalidate() method.
  }

}
