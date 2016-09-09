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

  public function tearDown(){
    $this->em->close();
  }

  public function testRendererPoll() {
    $testCommands = array(
      'user-create' => sprintf('app/console user:create %s --pass=%s --role=renderer,compose', $this->username, $this->password),
      'batch-create' => sprintf(
        'app/console batch:create --url=%s --from=\'%s\' --subject=\'%s\' --text=\'%s\' --render=%s --out=%s',
          sprintf('http://%s:%s@localhost:8000/', $this->username, $this->password),
          'Test User <test.user@prevem.com>',
          'Subject ' . substr(sha1(rand()), 0, 4),
          'Body Text',
          $this->renderer,
          __DIR__
      ),
      'renderer-poll' => sprintf(
        'app/console renderer:poll --url=%s --name=%s --cmd=\'%s\'',
          sprintf('http://%s:%s@localhost:8000/', $this->username, $this->password),
          $this->renderer,
          __DIR__ . '/../sample/render-script.php'
      ),
      'execute-render-script' => sprintf('php %s about', __DIR__ . '/../sample/render-script.php'),
    );

    // Create user with roles - ROLE_COMPOSE and ROLE_RENDER
    $process = new Process($testCommands['user-create']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

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

    $params = array(
      'previewTaskID' => $previewTask['id'],
      'previewBatchName' => $previewTask['batch'],
      'imageFilePath' => $imageFilePath,
      'batchJsonFilePath' => sprintf("%s/%s_%s.json", __DIR__, $this->username, $previewTask['batch']),
    );
    $this->cleanup($params);
  }

  /**
   * Cleanup created data
   */
  protected function cleanUp($params) {
    //delete preview task
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($params['previewTaskID']);
    $this->em->remove($previewTask);
    $this->em->flush();

    //delete preview batch
    $previewBatch = $this->em
                         ->getRepository('PrevemCoreBundle:PreviewBatch')
                         ->find(array('batch' => $params['previewBatchName'], 'user' => $this->username));
    $this->em->remove($previewBatch);
    $this->em->flush();

    //delete renderer created
    $renderer = $this->em->getRepository('PrevemCoreBundle:Renderer')->find($this->renderer);
    $this->em->remove($renderer);
    $this->em->flush();

    //delete the desired username created
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    $this->assertEquals($this->username, $user->getUsername());
    $this->em->remove($user);
    $this->em->flush();

    // delete image file path
    $imageFileDir = static::$kernel->getContainer()->getParameter('image_dir') . DIRECTORY_SEPARATOR . $this->username;
    unlink($params['imageFilePath']);
    rmdir($imageFileDir . DIRECTORY_SEPARATOR . $params['previewBatchName']);
    rmdir($imageFileDir);

    // delete batch json file created as a result of batch:create
    unlink($params['batchJsonFilePath']);
  }

}
