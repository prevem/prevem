<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Prevem\CoreBundle\Tests\PrevemTestCase;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Symfony\Component\Process\Process;

class PreviewBatchControllerTest extends PrevemTestCase
{

public $renderer = NULL;
public $batchName = NULL;
public $batchMessage = NULL;

  protected function setUp() {
    parent::setUp();

    $this->batchName = 'prevem-cli-' . substr(sha1(rand()), 0, 4);
    $this->batchMessage = array(
      'from' => 'Test User <test.user@prevem.com>',
      'subject' => 'Subject ' . substr(sha1(rand()), 0, 4),
      'body_text' => 'Body Text',
      'body_html' => '<html><body>Body Text</body></html>',
    );
    $this->renderer = 'renderer-' . substr(sha1(rand()), 0, 4);
  }

  public function createBatchTask($url) {
    $jsonContent =  array(
      'message' => $this->batchMessage,
      'tasks' => array(
        array(
          'renderer' => $this->renderer
        )
      ),
    );
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    $this->client->put($url . "previewBatch/{$this->username}/{$this->batchName}")
                 ->setBody(json_encode($jsonContent), 'application/json')
                 ->send();
  }

  public function getBatchTask($url) {
    $batchTasks = $this->client->get($url . "previewBatch/{$this->username}/{$this->batchName}/tasks")
                      ->send()
                      ->json();
    //check if preview task information is there
    $this->assertEquals(TRUE, array_key_exists('tasks', $batchTasks));
    // check if only one preview task is created
    $this->assertEquals(1, count($batchTasks['tasks']));

    return $batchTasks['tasks'][0];
  }

  /**
  * 1. Create preview batch data via (method=PUT| url=/previewBatch/{username}/{batch}) then
  * 2. Fetch preview batch data just created via (method=GET| url=/previewBatch/{username}/{batch})
  */
  public function testPutAndGetPreviewBatch() {
    $url = $this->logIn('compose');
    $username = parse_url($url, PHP_URL_USER);

    // ************ Create preview batch ***************
    $this->createBatchTask($url);

    // ************ Get preview task data ***************
    $task = $this->em
                 ->getRepository('PrevemCoreBundle:PreviewTask')
                 ->createQueryBuilder('pt')
                 ->where('pt.user = :username AND pt.renderer = :renderer AND pt.batch = :batch')
                 ->setParameter('username', $this->username)
                 ->setParameter('renderer', $this->renderer)
                 ->setParameter('batch', $this->batchName)
                 ->getQuery()
                 ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    $this->assertEquals(1, count($task));
    $previewTask = $task[0];

    // ************ Get preview batch data ***************
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($username));
    $batches = $this->client->get($url . "previewBatch/{$username}/{$this->batchName}")
                      ->send()
                      ->json();
    $this->assertNotEmpty($batches);
    $batch = $batches[0]; // since GET previewBatch/{$username}/{$this->batchName} return array of batches
    $decodedMessage = json_decode($batch['message'], TRUE);
    $this->assertEquals($decodedMessage['from'], $this->batchMessage['from']);
    $this->assertEquals($decodedMessage['subject'], $this->batchMessage['subject']);
    $this->assertEquals($decodedMessage['body_text'], $this->batchMessage['body_text']);
    $this->assertEquals($decodedMessage['body_html'], $this->batchMessage['body_html']);
    $this->assertEquals($batch['user'], $username);
    $this->assertEquals($batch['batch'], $this->batchName);

    // ***************** Cleanup ******************
    // delete related preview task
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($previewTask['id']);
    $this->em->remove($previewTask);
    $this->em->flush();
    // delete preview batch
    $previewBatch = $this->em
                         ->getRepository('PrevemCoreBundle:PreviewBatch')
                         ->find(array('user' => $this->username, 'batch' => $this->batchName));
    $this->em->remove($previewBatch);
    $this->em->flush();

    $this->logout();
  }

  /**
   * Create/Fetch preview batch data via (method=PUT| url=/previewBatch/{username}/{batch}) and
   *  (method=GET| url=/previewBatch/{username}/{batch}) but user has incorrect role
   */
  public function testPutAndGetPreviewBatchWithWrongRole() {
    // needs compose role but given renderer role
    $url = $this->logIn('renderer');

    // create preview task via (method=PUT| url=/previewBatch/{username}/{batch}) with wrong role
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    try {
      $this->client
           ->put($url . "previewBatch/{$this->username}/{$this->batchName}")
           ->setBody('', 'application/json')
           ->send();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals(403, $error['code']);
      $this->assertEquals('Access Denied.', $error['message']);
    }

    // fetch preview task via (method=GET| url=/previewBatch/{username}/{batch}) with wrong role
    try {
      $this->client
           ->get($url . "previewBatch/{$this->username}/{$this->batchName}")
           ->send();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals(403, $error['code']);
      $this->assertEquals('Access Denied.', $error['message']);
    }

    $this->logout();
  }


  /**
  * This unit test assert following use-cases
  * 1. Create preview batch data via (method=PUT| url=/previewBatch/{username}/{batch}) in order to have sample preview Task data
  * 2. Fetch preview batch data just created via (method=GET| url=/previewBatch/{username}/{batch}/tasks)
  * 3. Assert the data of previewTask if its correct (esp. status which need to 'pending')
  * 4. With the help of renderer:poll command claim the desired task
  * 5. Change the different attributes of previewTask to see the affect on its status
  *   a. Assert the status is set to 'finished' as result of step 4
  *   b. Set the error message to see if the status reverted to pending
  *   c. Set the attempt greater then the maximum attempt limit set in the system and see if the status is changed to 'failed'
  *   d. Unset the finish time to see if the status changed to rendering
  */
  public function testGetPreviewBatchTask() {
    $url = $this->logIn('compose');

    // create preview batch
    $this->createBatchTask($url);

    // get preview batch
    $previewTask = $this->getBatchTask($url);

    $this->assertEquals($this->username, $previewTask['user']);
    $this->assertEquals($this->renderer, $previewTask['renderer']);
    $this->assertEquals($this->batchName, $previewTask['batch']);
    // assert that status is pending since no renderer has claimed the task yet
    $this->assertEquals('pending', $previewTask['status']);

    //get desired preview task ID
    $previewTaskID = $previewTask['id'];

    // we need to change the role to admin because to perform renderer:poll task the user need to have ROLE_RENDER
    // so if we set role to 'admin' then the user will have both ROLE_RENDER and ROLE_COMPOSE roles as a result
    // it can perform update/fetch preview tasks
    $url = $this->logIn('admin');

    // Now lets poll the desired task to assert its status
    $process = new Process(sprintf('app/console renderer:poll --url=%s --name=%s --cmd=\'%s\'',
        $url,
        $this->renderer,
        __DIR__ . '/../sample/render-script.php'
    ));
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());
    // get preview batch
    $previewTask = $this->getBatchTask($url);
    // assert that status is finished since renderer has just claimed the task successfully
    $this->assertEquals('finished', $previewTask['status']);

    // Now lets play with desired preview Task and see the affect on its status
    // 1. First we are going to fetch the desired PreviewTask
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($previewTaskID);

    // 2. Change the data to assert the changed status
    //  2.a Lets set the error message to see if the status reverted to pending
    $previewTask->setErrorMessage('Your renderer setting is inappropriate');
    $this->em->persist($previewTask);
    $this->em->flush();
    // assert that status is pending since no renderer has claimed the task yet
    $previewTask = $this->getBatchTask($url);
    $this->assertEquals('pending', $previewTask['status']);

    // 2.b Lets set the attempt greater then the maximum polling limit set in the system and
    //   see if the status is changed to failed
    $maxAttempts = static::$kernel->getContainer()->getParameter('render_attempts') + 1;
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($previewTaskID);
    $previewTask->setAttempts($maxAttempts);
    $this->em->persist($previewTask);
    $this->em->flush();
    // assert that status is failed since number of attempts exceeded then the limit set in the system
    $previewTask = $this->getBatchTask($url);
    $this->assertEquals('failed', $previewTask['status']);

    // 2.c Lets unset the finish time to see if the status changed to rendering
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($previewTaskID);
    $previewTask->setFinishTime(NULL);
    $this->em->persist($previewTask);
    $this->em->flush();
    // assert that status is rendering since the task is not finished yet but its claimed
    $previewTask = $this->getBatchTask($url);
    $this->assertEquals('rendering', $previewTask['status']);

    // ***************** Cleanup ******************
    // delete related preview task
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($previewTaskID);
    $this->em->remove($previewTask);
    $this->em->flush();
    // delete preview batch
    $previewBatch = $this->em
                         ->getRepository('PrevemCoreBundle:PreviewBatch')
                         ->find(array('user' => $this->username, 'batch' => $this->batchName));
    $this->em->remove($previewBatch);
    $this->em->flush();

    $this->logout();
  }

  /**
   * Fetch preview batch data via (method=PUT| url=/previewBatch/{username}/{batch}/tasks)
   * but user has incorrect role
   */
  public function testGetPreviewBatchTaskWithWrongRole() {
    // needs compose role but given renderer role
    $url = $this->logIn('renderer');

    // create preview task via (method=PUT| url=/previewBatch/{username}/{batch}) with wrong role
    $this->client->setDefaultOption('headers', $this->prevem_util->getBearerAuthHeader($this->username));
    try {
      $this->client
           ->get($url . "previewBatch/{$this->username}/{$this->batchName}/tasks")
           ->send();
    } catch (ClientErrorResponseException $e) {
      $error = json_decode($e->getResponse()->getBody(TRUE), TRUE);
      $this->assertEquals(403, $error['code']);
      $this->assertEquals('Access Denied.', $error['message']);
    }

    $this->logout();
  }

}
