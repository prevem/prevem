<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Prevem\CoreBundle\Tests\PrevemTestCase;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Symfony\Component\Process\Process;

class RendererControllerTest extends PrevemTestCase
{

  /**
  * - Create renderer data via (method=PUT| url=/Renderer/{rendername}) then
  * - Fetch renderer data just created via (method=GET| url=/Renderers)
  */
  public function testCreateAndGetRenderer() {
    // to create renderer data the user must have ROLE_RENDER role
    $url = $this->logIn('renderer');
    $renderer = 'renderer-' . substr(sha1(rand()), 0, 4);

    // fetch metadata from render-script.php
    $process = new Process(sprintf('php %s about', __DIR__ . '/../sample/render-script.php'));
    $process->run();
    $jsonContent = $process->getOutput();

    // ************ Create renderer data ***************
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    $rendererMetadata = $this->client
                             ->put($url . "renderer/{$renderer}")
                             ->setBody($jsonContent, 'application/json')
                             ->send()
                             ->json();
    $this->assertNotEmpty($rendererMetadata);
    $renderer = $this->em->getRepository('PrevemCoreBundle:Renderer')->find($renderer);
    $this->assertEquals($renderer->getTitle(), $rendererMetadata['title']);
    $this->assertEquals($renderer->getOs(), $rendererMetadata['os']);
    $this->assertEquals($renderer->getOsVersion(), $rendererMetadata['osVersion']);
    $this->assertEquals($renderer->getApp(), $rendererMetadata['app']);
    $this->assertEquals($renderer->getAppVersion(), $rendererMetadata['appVersion']);
    $this->assertEquals($renderer->getIcons(), $rendererMetadata['icons']);
    $this->assertEquals($renderer->getOptions(), $rendererMetadata['options']);

    // ************** Get renderer data *****************
    // to fetch all renderer data the user must have ROLE_COMPOSE role
    $url = $this->logIn('compose');
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    $renderers = $this->client->get($url . "renderers")
                      ->send()
                      ->json();
    $this->assertNotEmpty($renderers);

    // clean up
    $params = array(
      'Renderer' => $renderer,
      'User' => $this->username,
    );
    $this->cleanUp($params);
  }

  /**
   * Fetch renderer data via (method=GET| url=/Renderers) but user has incorrect role
   */
  public function testGetRenderersWithWrongRole() {
    // to get renderer data the user must have ROLE_COMPOSE role but user only have ROLE_RENDER which is wrong
    $url = $this->logIn('renderer');
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    try {
      $renderers = $this->client
                        ->get($url . "renderers")
                        ->send()
                        ->json();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals(403, $error['code']);
      $this->assertEquals('Access Denied.', $error['message']);
    }
    $this->logout();
  }

  /**
   * Create renderer data via (method=PUT| url=/Renderer/{rendername}) but user has incorrect role
   */
  public function testPutRendererWithWrongRole() {
    // to create/update renderer data the user must have ROLE_RENDER role but user only have ROLE_COMPOSE which is wrong
    $url = $this->logIn('compose');
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    try {
      $this->client
           ->put($url . "renderer/dont-care")
           ->setBody('', 'application/json')
           ->send()
           ->json();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals(403, $error['code']);
      $this->assertEquals('Access Denied.', $error['message']);
    }
    $this->logout();
  }

}
