<?php

namespace Prevem\CoreBundle\Tests\Command;

use Prevem\CoreBundle\Tests\PrevemTestCase;
use Symfony\Component\Process\Process;

class RendererPollCommandTest extends PrevemTestCase
{

  private $renderer;

  protected function setUp() {
    parent::setUp();
    $this->renderer = 'dummy-' . substr(sha1(rand()), 0, 4);
  }

  /**
   * This unit test perform the following tasks:
   *  1. Create sample preview Batch and Tasks via batch:create command
   *  2. Claim and submit the eligible preview task just created
   *  3. Assert the mail snapshot and other data
   */
  public function testRendererPoll() {
    // Create user with roles ROLE_COMPOSE and ROLE_RENDER
    $url = $this->logIn('renderer,compose');

    // set of commands to test
    $testCommands = array(
      'batch-create' => sprintf(
        'app/console batch:create --url=%s --from=\'%s\' --subject=\'%s\' --text=\'%s\' --render=%s --out=%s',
          $url,
          'Test User <test.user@prevem.com>',
          'Subject ' . substr(sha1(rand()), 0, 4),
          'Body Text',
          $this->renderer,
          __DIR__
      ),
      'renderer-poll' => sprintf(
        'app/console renderer:poll --url=%s --name=%s --cmd=\'%s\'',
          $url,
          $this->renderer,
          __DIR__ . '/../sample/render-script.php'
      ),
      'execute-render-script' => sprintf('php %s about', __DIR__ . '/../sample/render-script.php'),
    );

    // Create sample preview batch-task for render polling
    $process = new Process($testCommands['batch-create']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

    // do render polling
    $process = new Process($testCommands['renderer-poll']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

    //check if renderer is created or not
    $renderer = $this->em->getRepository('PrevemCoreBundle:Renderer')->find($this->renderer);
    $this->assertEquals(TRUE, !empty($renderer));

    // check each created renderer has correct meta-data as provided by render-script.php
    $process = new Process($testCommands['execute-render-script']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

    // Fetch renderer metadata from script that used earlier to create renderer
    $rendererMetadata = json_decode($process->getOutput(), TRUE);
    $this->assertEquals($renderer->getTitle(), $rendererMetadata['title']);
    $this->assertEquals($renderer->getOs(), $rendererMetadata['os']);
    $this->assertEquals($renderer->getOsVersion(), $rendererMetadata['osVersion']);
    $this->assertEquals($renderer->getApp(), $rendererMetadata['app']);
    $this->assertEquals($renderer->getAppVersion(), $rendererMetadata['appVersion']);
    $this->assertEquals($renderer->getIcons(), $rendererMetadata['icons']);
    $this->assertEquals($renderer->getOptions(), $rendererMetadata['options']);

    // retrieve the preview task just claimed for poll
    $tasks = $this->em
                 ->getRepository('PrevemCoreBundle:PreviewTask')
                 ->createQueryBuilder('pt')
                 ->where('pt.user = :username AND pt.renderer = :renderer AND pt.attempts = 1')
                 ->setParameter('username', $this->username)
                 ->setParameter('renderer', $this->renderer)
                 ->getQuery()
                 ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

    // check that only 1 preview tasks is claimed i.e. attempts=1
    $this->assertEquals(1, count($tasks));

    // since the result is in array format so retrieve the first data
    $previewTask = $tasks[0];

    // check that the remderer image is created in desired path which is
    // web/files/{user}/{batch}/{md5(id . user . batch . renderer . options . createTime)}.png
    $imageFilePath = static::$kernel->getContainer()
                               ->get('prevem_core.prevem_utils')
                               ->getImageFilePath(
                                    $previewTask['id'],
                                    $this->username,
                                    $previewTask['batch'],
                                    $this->renderer,
                                    json_encode($previewTask['options']),
                                    strtotime($previewTask['createTime']->format('Y-m-d H:i:s'))
                                 );
    $this->assertEquals(TRUE, file_exists($imageFilePath));

    // cleanup data
    $params = array(
      'PreviewTask' => $previewTask['id'],
      'PreviewBatch' => array('batch' => $previewTask['batch'], 'user' => $this->username),
      'Renderer' => $this->renderer,
      'User' => $this->username,
      'imageFilePath' => $imageFilePath,
      'batchJsonFilePath' => sprintf("%s/%s_%s.json", __DIR__, $this->username, $previewTask['batch']),
    );
    $this->cleanUp($params);
  }

  /**
   * Cleanup created data
   */
  protected function cleanUp($params) {
    parent::cleanUp($params);

    // delete image file directories
    $imageFileDir = static::$kernel->getContainer()->getParameter('image_dir') . DIRECTORY_SEPARATOR . $this->username;
    rmdir($imageFileDir . DIRECTORY_SEPARATOR . $params['PreviewBatch']['batch']);
    rmdir($imageFileDir);
  }

}
