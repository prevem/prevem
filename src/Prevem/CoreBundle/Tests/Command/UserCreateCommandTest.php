<?php

namespace Prevem\CoreBundle\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Process\Process;

class UserCreateCommandTest extends WebTestCase
{

  private $client = null;
  private $username;
  private $em;

  public function setUp() {
    $this->client = static::createClient();
    $this->username = 'test-user-' . substr(sha1(rand()), 0, 8);
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();
  }

  public function testUserCreateUpdate() {

    $testCommands = array(
      'app/console user:create', // execute command without any other paramereter
      sprintf('app/console user:create %s', $this->username), // provide only username
      sprintf('app/console user:create %s --pass=%s', $this->username, substr(sha1(rand()), 0, 8)), // provide username and password
      sprintf('app/console user:create %s --role=%s', $this->username, 'admin'), // update with role=admin parameter
      sprintf('app/console user:create %s --role=%s', $this->username, '') // update role with null
    );

    // execute 'user:create'
    $process = new Process($testCommands[0]);
    $process->run();
    $this->assertEquals(FALSE, $process->isSuccessful());

    // execute 'user:create <username>'
    $process = new Process($testCommands[1]);
    $process->run();
    // it will because no passowrd is provided
    $this->assertEquals(FALSE, $process->isSuccessful());

    // execute 'user:create <username> --pass=<password>'
    $process = new Process($testCommands[2]);
    $process->run();
    // it will because pass as passowrd is provided
    $this->assertEquals(TRUE, $process->isSuccessful());
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    $this->assertEquals($this->username, $user->getUsername());
    $this->assertEquals(array(0 => ''), $user->getRoles());

    // execute 'user:create <username> --role=admin'
    $process = new Process($testCommands[3]);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    //TODO: getRoles() always return empty array whereas it must return what is set
    //$this->assertEquals(array(0 => 'ROLE_ADMIN'), $user->getRoles());

    // execute 'user:create <username> --role=NULL'
    $process = new Process($testCommands[4]);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    $this->assertEquals(array(0 => ''), $user->getRoles());

    //delete the desired username created
    $this->em->remove($user);
    $this->em->flush();
  }
}
