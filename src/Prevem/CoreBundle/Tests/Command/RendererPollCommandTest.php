<?php

namespace Prevem\CoreBundle\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Process\Process;

class RendererPollCommandTest extends WebTestCase
{

  private $client = null;
  private $username;
  private $password;
  private $renderer;
  private $em;

  public function setUp() {
    $this->client = static::createClient();
    $this->username = 'test-user-' . substr(sha1(rand()), 0, 8);
    $this->password = substr(sha1(rand()), 0, 8);
    $this->renderer = 'dummy-' . substr(sha1(rand()), 0, 4);
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();

    //start server
    $process = new Process('app/console server:start');
    $process->run();
  }

  public function testRendererAbout() {
    $testCommands = array(
      'user-create' => sprintf('app/console user:create %s --pass=%s --role=renderer', $this->username, $this->password),
      'renderer-about' => sprintf(
        'app/console renderer:poll --url=%s --name=%s --cmd=\'%s\'',
          sprintf('http://%s:%s@localhost:8000/', $this->username, $this->password),
          $this->renderer,
          __DIR__ . '/sample/render-script.php about'
      ),
      'execute-render-script' => sprintf('php %s about', __DIR__ . '/sample/render-script.php'),
    );

    // Create user with role as ROLE_COMPOSE
    $process = new Process($testCommands['user-create']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

    $process = new Process($testCommands['renderer-about']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

    //check if renderer is created or not
    $renderer = $this->em->getRepository('PrevemCoreBundle:Renderer')->find($this->renderer);
    $this->assertEquals(TRUE, !empty($renderer));

    // check each created renderer has correct meta-data as provided by render-script.php
    $process = new Process($testCommands['execute-render-script']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());
    //fetch renderer metadata from script that used earlier to create renderer
    $rendererMetadata = json_decode($process->getOutput(), TRUE);
    $this->assertEquals($renderer->getTitle(), $rendererMetadata['title']);
    $this->assertEquals($renderer->getOs(), $rendererMetadata['os']);
    $this->assertEquals($renderer->getOsVersion(), $rendererMetadata['osVersion']);
    $this->assertEquals($renderer->getApp(), $rendererMetadata['app']);
    $this->assertEquals($renderer->getAppVersion(), $rendererMetadata['appVersion']);
    $this->assertEquals($renderer->getIcons(), $rendererMetadata['icons']);
    $this->assertEquals($renderer->getOptions(), $rendererMetadata['options']);

    $this->cleanup();
  }

  //TODO: add unit test for
  // "app/console renderer:poll --url=http://user:pass@localhost --render=<renderer name> --cmd='/tmp/render-script.php render' "

  /**
   * Cleanup created data
   */
  protected function cleanUp() {
    //delete renderer created
    $renderer = $this->em->getRepository('PrevemCoreBundle:Renderer')->find($this->renderer);
    $this->em->remove($renderer);
    $this->em->flush();

    //delete the desired username created
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    $this->assertEquals($this->username, $user->getUsername());
    $this->em->remove($user);
    $this->em->flush();
  }

}
