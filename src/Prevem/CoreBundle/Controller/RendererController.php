<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Prevem\CoreBundle\Form\RendererType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;


class RendererController extends Controller
{
  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return Symfony\Component\HttpFoundation\JsonResponse
   */
  public function renderersAction(Request $request) {
    $this->denyAccessUnlessGranted('ROLE_COMPOSE');

    $ttl = $request->query->get('render_agent_ttl');
    // If render_agent_ttl GET argument not provided then set the default
    $ttl = (!empty($ttl)) ? $ttl : $this->container->getParameter('render_agent_ttl');

    $renderers = $this->get('prevem_core.prevem_utils')->getEntity('Renderer',
      array('e.lastSeen >= CURRENT_TIMESTAMP() - :render_agent_ttl'),
      array('render_agent_ttl' => $ttl)
    );

    return new JsonResponse($renderers);
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $rendername
   * @return Symfony\Component\HttpFoundation\JsonResponse
   */
  public function rendererAction(Request $request, $rendername) {
    $this->denyAccessUnlessGranted('ROLE_RENDER');

    $data = json_decode($request->getContent(), TRUE);

    $em = $this->getDoctrine()->getManager();
    $renderer = $em->getRepository('PrevemCoreBundle:Renderer')->find($rendername);

    //If lastSeen is not set then use the current datetime
    $data['lastSeen'] = !empty($data['lastSeen']) ? $data['lastSeen'] : date('YmdHis');

    //If there is no renderer then create new
    if (!$renderer) {
      $this->getDoctrine()->getConnection()->executeUpdate('
        INSERT INTO Renderer (renderer, title, os, os_version, app, app_version, icons, options, last_seen)
        VALUES (:rend, :title, :os, :os_ver, :app, :app_ver, :icons, :options, :last_seen)',
        array(
          'rend' => $rendername,
          'title' => $data['title'],
          'os' => $data['os'],
          'os_ver' => $data['osVersion'],
          'app' => $data['app'],
          'app_ver' => $data['appVersion'],
          'icons' => json_encode($data['icons']),
          'options' => json_encode($data['options']),
          'last_seen' => $data['lastSeen'],
        )
      );
    }
    //Update renderer
    else {
      $form = $this->createForm(new RendererType(), $renderer);
      $form->submit($data);
      $em->persist($renderer);
      $em->flush();
    }

    return new JsonResponse($data);
  }

}
