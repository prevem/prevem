<?php

namespace Prevem\CoreBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Symfony\Component\Process\Process;

class TestController extends WebTestCase
{
  public $client = null;
  public $em;
  public $username;

  public function setUp() {
    static::bootKernel();
    $this->username = 'test-user-' . substr(sha1(rand()), 0, 8);
    $this->client = new Client();
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();

    //start server
    $process = new Process('app/console server:start');
    $process->run();
  }

  public function tearDown(){
    $this->em->close();
  }

  /**
   * Create user via user:create command and return the authorized url later used by REST endpoints
   *
   * @param string $role
   *
   * @return string $url
   */
  public function logIn($role = 'admin') {
    $role = empty($role) ? "''" : $role;
    $password = substr(sha1(rand()), 0, 8);
    $testCommand = sprintf("app/console user:create %s --pass=%s --role=%s",
      $this->username,
      $password,
      $role
    );

    $process = new Process($testCommand);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

    return sprintf('http://%s:%s@localhost:8000/', $this->username, $password);
  }

  /**
   * Delete user
   */
  public function logOut() {
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    $this->em->remove($user);
    $this->em->flush();
  }

  /**
  * Return authorized header with JWToken
  * @param string $url
  * @param array $headers
  *
  * @return array $headers
  */
  public function getAuthorizedHeaders($url, $headers = array('Accept' => 'application/json')) {
    $this->client->setDefaultOption('headers', array('Accept' => 'application/json'));
    $jsonContent = json_encode(array('username' => $this->username));
    $responseData = $this->client->post($url . "user/login")
                         ->setBody($jsonContent, 'application/json')
                         ->send()
                         ->json();
    $this->assertNotEmpty($responseData);
    $headers['Authorization'] = 'Bearer ' . $responseData['token'];

    return $headers;
  }
}
