<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Prevem\CoreBundle\Entity\Renderer;
use Prevem\CoreBundle\Form\RendererType;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('PrevemCoreBundle:Default:index.html.twig', array('name' => $name));
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
      $response = new Response(json_encode($renderers));
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

      $em = $this->getDoctrine()->getManager();
      $renderer = $em->getRepository('PrevemCoreBundle:Renderer')->find($rendername);

      if (!$renderer) {
        $renderer = new Renderer();
        $renderer->setRenderer($rendername);
      }

      $this->createForm(new RendererType(), $renderer);
      $form->handleRequest($request);

      if ($form->isValid()) {
        $em = $this->getDoctrine()->getManager();
        $em->persist($renderer);
        $em->flush();
      }

      return $this->view($form, 201);
    }
}
