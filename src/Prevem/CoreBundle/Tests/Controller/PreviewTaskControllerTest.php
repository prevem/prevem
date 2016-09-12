<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Prevem\CoreBundle\Tests\PrevemTestCase;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Symfony\Component\Process\Process;

class PreviewTaskControllerTest extends PrevemTestCase
{

  /**
   * Fetch renderer data via (method=POST| url=/previewTask/claim) but user has incorrect role
   */
  public function testClaimPreviewTaskWithWrongRole() {
    // to claim preview task the user must have ROLE_RENDER role but ROLE_COMPOSE role is given
    $url = $this->logIn('compose');
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    try {
      $this->client
           ->post($url . "previewTask/claim")
           ->setBody('', 'application/json')
           ->send();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals(403, $error['code']);
      $this->assertEquals('Access Denied.', $error['message']);
    }
    $this->logout();
  }

  /**
   * Fetch renderer data via (method=POST| url=/previewTask/submit) but user has incorrect role
   */
  public function testClaimPreviewSubmitWithWrongRole() {
    // to submit preview task the user must have ROLE_RENDER role but ROLE_COMPOSE role is given
    $url = $this->logIn('compose');
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    try {
      $this->client
           ->post($url . "previewTask/submit")
           ->setBody('', 'application/json')
           ->send();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals(403, $error['code']);
      $this->assertEquals('Access Denied.', $error['message']);
    }
    $this->logout();
  }

  /**
  * NOTE: \Prevem\CoreBundle\Tests\Controller\Command\RendererPollCommandTest unit test already cover the
  *  routes (method=POST| url=/previewTask/claim) and (method=POST| url=/previewTask/submit) so don't need to
  *  have separate unit tests to assert its functionality
  */  
}
