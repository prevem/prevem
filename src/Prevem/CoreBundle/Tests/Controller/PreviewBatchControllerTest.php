<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Prevem\CoreBundle\Tests\PrevemTestCase;
use Guzzle\Http\Exception\BadResponseException;
use Symfony\Component\Process\Process;

class PreviewBatchControllerTest extends PrevemTestCase
{

  /**
  * - Create preview batch data via (method=PUT| url=/previewBatch/{username}/{batch}) then
  * - Fetch preview batch data just created via (method=GET| url=/previewBatch/{username}/{batch})
  */
  public function testPutAndGetPreviewBatch() {
    $url = $this->logIn('compose');
    $username = parse_url($url, PHP_URL_USER);

    //sample batch message
    $batchMessage = array(
      'from' => 'Test User <test.user@prevem.com>',
      'subject' => 'Subject ' . substr(sha1(rand()), 0, 4),
      'body_text' => 'Body Text',
      'body_html' => '<html><body>Body Text</body></html>',
    );
    $renderer = 'renderer-' . substr(sha1(rand()), 0, 4);
    $batchName = 'prevem-cli-' . substr(sha1(rand()), 0, 4);

    // ************ Create preview batch ***************
    $jsonContent =  array(
      'message' => $batchMessage,
      'tasks' => array(
        array(
          'renderer' => $renderer
        )
      ),
    );
    $this->client->setDefaultOption('headers', $this->getAuthorizedHeaders($url));
    $this->client->put($url . "previewBatch/{$username}/{$batchName}")
                 ->setBody(json_encode($jsonContent), 'application/json')
                 ->send();

    // ************ Get preview task data ***************
    $task = $this->em
                 ->getRepository('PrevemCoreBundle:PreviewTask')
                 ->createQueryBuilder('pt')
                 ->where('pt.user = :username AND pt.renderer = :renderer AND pt.batch = :batch')
                 ->setParameter('username', $this->username)
                 ->setParameter('renderer', $renderer)
                 ->setParameter('batch', $batchName)
                 ->getQuery()
                 ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
    $this->assertEquals(1, count($task));
    $previewTask = $task[0];

    // ************ Get preview batch data ***************
    $this->client->setDefaultOption('headers', $this->getAuthorizedHeaders($url));
    $batches = $this->client->get($url . "previewBatch/{$username}/{$batchName}")
                      ->send()
                      ->json();
    $this->assertNotEmpty($batches);
    $batch = $batches[0]; // since GET previewBatch/{$username}/{$batchName} return array of batches
    $decodedMessage = json_decode($batch['message'], TRUE);
    $this->assertEquals($decodedMessage['from'], $batchMessage['from']);
    $this->assertEquals($decodedMessage['subject'], $batchMessage['subject']);
    $this->assertEquals($decodedMessage['body_text'], $batchMessage['body_text']);
    $this->assertEquals($decodedMessage['body_html'], $batchMessage['body_html']);
    $this->assertEquals($batch['user'], $username);
    $this->assertEquals($batch['batch'], $batchName);

    // ***************** Cleanup ******************
    // delete related preview task
    $previewTask = $this->em->getRepository('PrevemCoreBundle:PreviewTask')->find($previewTask['id']);
    $this->em->remove($previewTask);
    $this->em->flush();
    // delete preview batch
    $previewBatch = $this->em->getRepository('PrevemCoreBundle:PreviewBatch')->find(array('user' => $username, 'batch' => $batchName));
    $this->em->remove($previewBatch);
    $this->em->flush();

    $this->logout();
  }

}
