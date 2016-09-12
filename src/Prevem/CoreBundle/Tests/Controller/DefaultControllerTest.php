<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Prevem\CoreBundle\Tests\PrevemTestCase;
use Guzzle\Http\Exception\ClientErrorResponseException;

class DefaultControllerTest extends PrevemTestCase
{

  /**
  * Test (method=POST| url=/user/login)
  */
  public function testUserLogin() {
    $url = $this->logIn();

    $this->client->setDefaultOption('headers', array('Accept' => 'application/json'));
    $jsonContent = json_encode(array('username' => $this->username));
    $responseData = $this->client->post($url . "user/login")
                      ->setBody($jsonContent, 'application/json')
                      ->send()
                      ->json();
    $this->assertNotEmpty($responseData);
    $this->assertEquals(TRUE, array_key_exists('token', $responseData));

    $this->logout();
  }

  /**
  * Test (method=POST| url=/user/login) with wrong username
  */
  public function testWrongUsernameLogin() {
    $this->client->setDefaultOption('headers', array('Accept' => 'application/json'));
    $jsonContent = json_encode(array('username' => 'asIfIcare'));
    try {
      $this->client->post("http://localhost:8000/user/login")
                      ->setBody($jsonContent, 'application/json')
                      ->send()
                      ->json();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals("Username not found", $error);
    }
  }

  /**
  * Test (method=POST| url=/user/login) without username
  */
  public function testEmptyUsernameLogin() {
    $this->client->setDefaultOption('headers', array('Accept' => 'application/json'));
    try {
      $this->client->post("http://localhost:8000/user/login")
                      ->setBody('', 'application/json')
                      ->send()
                      ->json();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals("Username not provided", $error);
    }
  }

}
