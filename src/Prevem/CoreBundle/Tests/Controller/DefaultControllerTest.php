<?php

namespace Prevem\CoreBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class DefaultControllerTest extends WebTestCase
{

  private $client = null;

  public function setUp() {
    $this->client = static::createClient();
  }

    public function testIndex()
    {

        $crawler = $this->client->request('GET', '/hello/Fabien');

        $this->assertTrue($crawler->filter('html:contains("Hello Fabien")')->count() > 0);
    }



    public function testRenderer() {
      $this->logIn();
      $renderParams = array(
        'title' => 'Thunderlook 9.1 (Windows XP)',
        'os' => 'windows',
        'osVersion' => '13x',
        'app' => 'gmail',
        'appVersion' => '12',
        'icons' =>  '{"16x16": "http://example.com/thunderlook-16x16.png"}',
        'options' => "['window-width','window-height']",
        'lastSeen' => date('YmdHis'),
      );

      $res = $this->client->request('PUT', '/renderer/Fabian', array(), array(), array('CONTENT_TYPE' => 'application/json'), json_encode($renderParams));
      $req = $this->client->getResponse();
      $this->assertNotEmpty($req->getContent());
      //$doc = json_decode($req->getContent(), TRUE);

      $res = $this->client->request('GET', '/renderers?render_agent_ttl=72000');
      print_r($this->client->getResponse()->getContent());exit;
    }

    private function logIn()
    {
        $session = $this->client->getContainer()->get('session');

        // the firewall context (defaults to the firewall name)
        $firewall = 'secured_area';

        $token = new UsernamePasswordToken('admin', null, $firewall, array('ROLE_RENDER'));
        $session->set('_security_'.$firewall, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }
}
