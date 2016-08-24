<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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

      $conn = $this->getDoctrine()->getManager();
      $renderers = $conn->createQuery('SELECT r FROM PrevemCoreBundle:Renderer r WHERE r.lastSeen >= CURRENT_TIMESTAMP() - :render_agent_ttl')
        ->setParameter('render_agent_ttl', $ttl)
        ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

      return new JsonResponse($renderers, 200);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param $rendername
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function rendererAction(Request $request, $rendername) {
      $this->denyAccessUnlessGranted('ROLE_RENDER');

      $data = json_decode($request->getContent(), TRUE);

      $em = $this->getDoctrine()->getManager();
      $renderer = $em->getRepository('PrevemCoreBundle:Renderer')->find($rendername);

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
            'icons' => $data['icons'],
            'options' => $data['options'],
            'last_seen' => $data['lastSeen'],
          )
        );
      }
      else {
        $form = $this->createForm(new RendererType(), $renderer);
        $form->submit($data);
        $em->persist($renderer);
        $em->flush();
      }

      return new JsonResponse($data, 200);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function previewBatchAction(Request $request, $username, $batch) {
      $this->denyAccessUnlessGranted('ROLE_COMPOSE');
      $method = $request->getMethod();

      if ($method == 'GET') {
        return $this->getPreviewBatch($username, $batch);
      }
      elseif ($method == 'PUT') {

      }

    }

    public function getPreviewBatch($username, $batch) {
      $conn = $this->getDoctrine()->getManager();
      $renderers = $conn->createQuery('SELECT pb FROM PrevemCoreBundle:PreviewBatch pb WHERE pb.user = :username AND pb.batch = :batch')
        ->setParameter('username', $username)
        ->setParameter('batch', $batch)
        ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

      return new JsonResponse($renderers, 200);
    }
}
