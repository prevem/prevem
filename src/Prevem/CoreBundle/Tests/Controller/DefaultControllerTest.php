<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Prevem\CoreBundle\Tests\TestController;
use Symfony\Component\BrowserKit\Cookie;
use Guzzle\Http\Exception\BadResponseException;
use Symfony\Component\Process\Process;

class DefaultControllerTest extends TestController
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

    //TODO: assert bad credential exception

    $this->logout();
  }

}
