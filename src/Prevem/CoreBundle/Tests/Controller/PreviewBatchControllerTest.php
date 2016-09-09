<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Prevem\CoreBundle\Tests\TestController;
use Guzzle\Http\Exception\BadResponseException;
use Symfony\Component\Process\Process;

class PreviewBatchControllerTest extends TestController
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
      'body' => 'Body Text',
    );
    $renderer = 'renderer-' . substr(sha1(rand()), 0, 4);

    // ************ Create preview batch ***************
    $process = new Process(sprintf(
      'app/console batch:create --url=%s --from=\'%s\' --subject=\'%s\' --text=\'%s\' --render=%s --out=%s',
        $url,
        $batchMessage['from'],
        $batchMessage['subject'],
        $batchMessage['body'],
        $renderer,
        __DIR__
    ));
    $process->run();
    $this->assertEquals(TRUE, $process->isSuccessful());

    //After successful execution it records it data in given
    // --out=<filePath> where filename is <username>_<batch>.json
    $filePaths = glob(sprintf('%s/%s_*.json', __DIR__, $this->username));
    $jsonFilePath = $filePaths[0];
    $previewTasks = json_decode(file_get_contents($jsonFilePath), TRUE);
    $previewTask = $previewTasks['tasks'][0];
    //retrieve batch name
    $batchName = $previewTask['batch'];

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
    $this->assertEquals($decodedMessage['body_text'], $batchMessage['body']);
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
    // delete output file
    unlink($jsonFilePath);

    $this->logout();
  }

}
