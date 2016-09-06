<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Symfony\Component\Process\Process;

class DefaultControllerTest extends WebTestCase
{

  private $client = null;
  private $em;
  private $username;

  public function setUp() {
    $this->username = 'test-user-' . substr(sha1(rand()), 0, 8);
    $this->client = new Client();
    static::bootKernel();
    $this->em = static::$kernel->getContainer()->get('doctrine')->getManager();

    //start server
    $process = new Process('app/console server:start');
    $process->run();
  }

  public function tearDown(){
    $this->em->close();
  }

  /**
  * Test (method=POST| url=/user/login)
  */
  public function testUserLogin() {
    $url = $this->logIn();

    $this->client->setDefaultOption('headers', array('Accept' => 'application/json'));
    $jsonContent = json_encode(array('username' => $this->username));
    $responseData = $this->client->post($url . "user/login")
                      ->setBody($jsonContent, 'application/json')
                      ->send()
                      ->json();
    $this->assertNotEmpty($responseData);
    $this->assertEquals(TRUE, array_key_exists('token', $responseData));

    //TODO: assert bad credential exception

    $this->logout();
  }

  /**
  * Create renderer data via (method=PUT| url=/Renderer/{rendername}) then
  * Fetch renderer data just created via (method=GET| url=/Renderers)
  */
  public function testCreateAndGetRenderer() {
    // to create renderer data the user must have ROLE_RENDER role
    $url = $this->logIn('renderer');
    $renderer = 'renderer-' . substr(sha1(rand()), 0, 4);

    // fetch metadata from render-script.php
    $process = new Process(sprintf('php %s about', __DIR__ . '/../sample/render-script.php'));
    $process->run();
    $jsonContent = $process->getOutput();

    // ************ Create renderer data ***************
    $this->client->setDefaultOption('headers', $this->getAuthorizedHeaders($url));
    $rendererMetadata = $this->client->put($url . "renderer/{$renderer}")
                      ->setBody($jsonContent, 'application/json')
                      ->send()
                      ->json();
    $this->assertNotEmpty($rendererMetadata);
    $renderer = $this->em->getRepository('PrevemCoreBundle:Renderer')->find($renderer);
    $this->assertEquals($renderer->getTitle(), $rendererMetadata['title']);
    $this->assertEquals($renderer->getOs(), $rendererMetadata['os']);
    $this->assertEquals($renderer->getOsVersion(), $rendererMetadata['osVersion']);
    $this->assertEquals($renderer->getApp(), $rendererMetadata['app']);
    $this->assertEquals($renderer->getAppVersion(), $rendererMetadata['appVersion']);
    $this->assertEquals($renderer->getIcons(), $rendererMetadata['icons']);
    $this->assertEquals($renderer->getOptions(), $rendererMetadata['options']);

    // ************** Get renderer data *****************
    // to fetch all renderer data the user must have ROLE_COMPOSE role
    $url = $this->logIn('compose');
    $this->client->setDefaultOption('headers', $this->getAuthorizedHeaders($url));
    $renderers = $this->client->get($url . "renderers")
                      ->send()
                      ->json();
    $this->assertNotEmpty($renderers);

    // clean up
    $renderer = $this->em->getRepository('PrevemCoreBundle:Renderer')->find($renderer);
    $this->em->remove($renderer);
    $this->em->flush();

    $this->logout();
  }

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

  /**
  * Return authorized header with JWToken
  * @param string $url
  * @param array $headers
  *
  * @return array $headers
  */
  private function getAuthorizedHeaders($url, $headers = array('Accept' => 'application/json')) {
    $this->client->setDefaultOption('headers', array('Accept' => 'application/json'));
    $jsonContent = json_encode(array('username' => $this->username));
    $responseData = $this->client->post($url . "user/login")
                         ->setBody($jsonContent, 'application/json')
                         ->send()
                         ->json();
    $this->assertNotEmpty($responseData);
    $headers['Authorization'] = 'Bearer ' . $responseData['token'];

    return $headers;
  }

  /**
   * Create user via user:create command and return the authorized url later used by REST endpoints
   *
   * @param string $role
   *
   * @return string $url
   */
  private function logIn($role = 'admin') {
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
  private function logOut() {
    $user = $this->em->getRepository('PrevemCoreBundle:User')->find($this->username);
    $this->em->remove($user);
    $this->em->flush();
  }

}
