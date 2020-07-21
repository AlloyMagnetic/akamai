<?php

namespace Drupal\akamai\Tests;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests Akamai purge status logging.
 *
 * @description Tests Akamai purge status logging.
 *
 * @group Akamai
 */
class AkamaiStatusLogTest extends BrowserTestBase {

  use CronRunTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system_test', 'user', 'akamai'];

  /**
   * User with admin rights.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $privilegedUser;

  /**
   * An editable config object for access to 'akamai.settings'.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $akamaiConfig;

  /**
   * The StatusStorage service.
   *
   * @var \Drupal\akamai\StatusStorage
   */
  protected $statusStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->akamaiConfig = \Drupal::configFactory()->getEditable('akamai.settings');
    $this->statusStorage = \Drupal::service('akamai.status_storage');

    // Create and log in our privileged user.
    $this->privilegedUser = $this->drupalCreateUser([
      'administer akamai',
    ]);
    $this->drupalLogin($this->privilegedUser);
  }

  /**
   * Tests that logs are saved and purged correctly.
   */
  public function testStatusLogging() {
    $mock_status = $this->mockStatus();

    // Tests that a status is saved correctly.
    $this->statusStorage->save($mock_status);
    $saved_status = $this->statusStorage->get($mock_status['purgeId']);
    $this->assertNotNull($saved_status, 'Status was saved.');

    // Tests that a timestamp is added to the status.
    $timestamp = \Drupal::time()->getRequestTime();
    $saved_status = array_pop($saved_status);
    $key = 'request_made_at';
    $has_timestamp = array_key_exists($key, $saved_status) && $saved_status[$key] >= $timestamp;
    $this->assertTrue($has_timestamp, 'Valid timestamp added to status.');

    // Tests that subsequent statuses with the same id are collated.
    // Add 2 more status log requests, for a total of 3 saved.
    $this->statusStorage->save($mock_status);
    $this->statusStorage->save($mock_status);
    $saved_status = $this->statusStorage->get($mock_status['purgeId']);
    $this->assertTrue(count($saved_status) == 3, 'Statuses with same purge id are collated.');

    // Tests that statuses are deleted on cron.
    // Set statuses to expire immediately.
    $this->config('akamai.settings')->set('status_expire', 0)->save(TRUE);
    $this->cronRun();
    $saved_status = $this->statusStorage->get($mock_status['purgeId']);
    $this->assertFalse($saved_status, 'Statuses deleted on cron after expiring.');

    // Tests that a set number of statues are deleted.
    // Add 3 more status log requests, for a total of 3 saved.
    $this->config('akamai.settings')->set('number_of_logs_to_delete', 2)->save(TRUE);
    $this->statusStorage->save($this->mockStatus());
    $this->statusStorage->save($this->mockStatus());
    $this->statusStorage->save($this->mockStatus());
    $this->cronRun();
    $saved_status = $this->statusStorage->getResponseStatuses();;
    $this->assertTrue(count($saved_status) == 1, '1 status deleted on cron after setting number of logs to delete.');
  }

  /**
   * Returns a mock status array, as returned by Akamai's API.
   *
   * @return array
   *   The status.
   */
  protected function mockStatus() {
    return [
      'estimatedSeconds' => '420',
      'progressUri' => '/ccu/v2/purges/57799d8b-10e4-11e4-9088-62ece60caaf0',
      'purgeId' => uniqid('akamai', TRUE),
      'supportId' => '17PY1405953363409286-284546144',
      'httpStatus' => '201',
      'detail' => 'Request accepted.',
      'pingAfterSeconds' => '420',
    ];
  }

}
