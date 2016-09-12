<?php

namespace Prevem\CoreBundle\Tests\Command;

use Prevem\CoreBundle\Tests\PrevemTestCase;
use Symfony\Component\Process\Process;

class BatchCreateCommandTest extends PrevemTestCase
{

  /**
   * Unit test for user:create to create preview batch and its desired tasks and assert the created data
   */
  public function testBatchCreate() {
    // Create user with role as ROLE_COMPOSE
    $url = $this->logIn('compose');

    //sample batch message
    $batchMessage = array(
      'from' => 'Test User <test.user@prevem.com>',
      'subject' => 'Subject ' . substr(sha1(rand()), 0, 4),
      'body' => 'Body Text',
    );

    //set commands to be tested
    $testCommands = array(
      'batch-create' => sprintf(
        'app/console batch:create --url=%s --from=\'%s\' --subject=\'%s\' --text=\'%s\' --render=%s --out=%s',
          $url,
          $batchMessage['from'],
          $batchMessage['subject'],
          $batchMessage['body'],
          'renderer-' . substr(sha1(rand()), 0, 4),
          __DIR__
      ),
    );

    // Create user with role as ROLE_COMPOSE
    $process = new Process($testCommands['batch-create']);
    $process->run();
    usleep(100000);
    $this->assertEquals(TRUE, $process->isSuccessful());

    //After successful execution it records it data in given
    // --out=<filePath> where filename is <username>_<batch>.json
    $filePaths = glob(sprintf('%s/%s_*.json', __DIR__, $this->username));
    $jsonFilePath = $filePaths[0];
    $previewTasks = json_decode(file_get_contents($jsonFilePath), TRUE);
    $previewTask = $previewTasks['tasks'][0];
    //retrieve batch name
    $batchName = $previewTask['batch'];

    //Check that batch message is correctly populated with correct data
    $previewBatch = $this->em->getRepository('PrevemCoreBundle:PreviewBatch')->find(array('user' => $this->username, 'batch' => $batchName));
    $message = json_decode($previewBatch->getMessage(), TRUE);
    $this->assertEquals($batchMessage['from'], $message['from']);
    $this->assertEquals($batchMessage['subject'], $message['subject']);
    $this->assertEquals($batchMessage['body'], $message['body_text']);

    $params = array(
      'PreviewTask' => $previewTask['id'],
      'PreviewBatch' => array('batch' => $previewTask['batch'], 'user' => $this->username),
      'User' => $this->username,
      'jsonFilePath' => $jsonFilePath,
    );
    $this->cleanUp($params);
  }

}
