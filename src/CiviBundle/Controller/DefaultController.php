<?php

namespace CiviBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DefaultController extends Controller {
  public function indexAction($name) {
      return $this->render('CiviBundle:Default:index.html.twig', array('name' => $name));
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function renderersAction(Request $request) {
    $this->denyAccessUnlessGranted('ROLE_COMPOSE');

    //TODO: Do we need to pass any default duration in seconds when render_agent_ttl args is not present?
    $ttl = $request->query->get('render_agent_ttl');

    $conn = $this->getDoctrine()->getConnection();
    $renderers =  $conn->executeQuery('SELECT * FROM Renderer WHERE lastSeen >= NOW() - :render_agent_ttl', array('render_agent_ttl' => $ttl));
    $response = new \Symfony\Component\HttpFoundation\Response(json_encode($renderers));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $rendername
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function rendererAction(Request $request, $rendername) {
    $this->denyAccessUnlessGranted('ROLE_RENDER');


    return $response;
  }

}
