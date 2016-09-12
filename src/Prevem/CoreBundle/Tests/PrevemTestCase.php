<?php

namespace Prevem\CoreBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Symfony\Component\Process\Process;

class PrevemTestCase extends WebTestCase
{
  public $client = null;
  public $em;
  public $username;
  public $prevem_util;

  protected function setUp() {
    static::bootKernel();
    $this->username = 'test-user-' . substr(sha1(rand()), 0, 8);
    $this->client = new Client();
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();
    $this->prevem_util = static::$kernel->getContainer()->get('prevem_core.prevem_utils');
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

}
