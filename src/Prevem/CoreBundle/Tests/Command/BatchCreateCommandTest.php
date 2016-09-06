<?php

namespace Prevem\CoreBundle\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Process\Process;

class BatchCreateCommandTest extends WebTestCase
{

  private $client = null;
  private $username;
  private $password;
  private $em;

  public function setUp() {
    $this->client = static::createClient();
    $this->username = 'test-user-' . substr(sha1(rand()), 0, 8);
    $this->password = substr(sha1(rand()), 0, 8);
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();

    //start server
    $process = new Process('app/console server:start');
    $process->run();
  }

  public function testBatchCreate() {
    //sample batch message
    $batchMessage = array(
      'from' => 'Test User <test.user@prevem.com>',
      'subject' => 'Subject ' . substr(sha1(rand()), 0, 4),
      'body' => 'Body Text',
    );

    //set commands to be tested
    $testCommands = array(
      'user-create' => sprintf('app/console user:create %s --pass=%s --role=compose', $this->username, $this->password),
      'batch-create' => sprintf(
        'app/console batch:create --url=%s --from=\'%s\' --subject=\'%s\' --text=\'%s\' --render=%s --out=%s',
          sprintf('http://%s:%s@localhost:8000/', $this->username, $this->password),
          $batchMessage['from'],
          $batchMessage['subject'],
          $batchMessage['body'],
          'renderer-' . substr(sha1(rand()), 0, 4),
          __DIR__
      ),
    );

    // Create user with role as ROLE_COMPOSE
    $process = new Process($testCommands['user-create']);
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

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
      'previewTask' => $previewTask['id'],
      'previewBatch' => $previewBatch,
      'jsonFilePath' => $jsonFilePath,
    );
    $this->cleanUp($params);
  }

  /**
   * Cleanup created data
   * NOTE: We can also use batch:prune to clean up created tasks and batch but
   *   this will also clean the other real data with test data.
   *   So its better to delete ONLY the created test data manually
   *
   * @param array $params
   */
  protected function cleanUp($params) {
    //delete previewTask created
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($params['previewTask']);
    $this->em->remove($previewTask);
    $this->em->flush();

    //delete previewBatch created
    $this->em->remove($params['previewBatch']);
    $this->em->flush();

    //delete the desired username created
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    $this->assertEquals($this->username, $user->getUsername());
    $this->em->remove($user);
    $this->em->flush();

    //delete the preview task json file created
    unlink($params['jsonFilePath']);
  }

}
