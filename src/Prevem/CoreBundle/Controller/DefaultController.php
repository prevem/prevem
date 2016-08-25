<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Prevem\CoreBundle\Entity\Renderer;
use Prevem\CoreBundle\Entity\PreviewTask;
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
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function renderersAction(Request $request) {
      $this->denyAccessUnlessGranted('ROLE_COMPOSE');

      $ttl = $this->container->getParameter('render_agent_ttl');

      $renderers = $this->getEntity('Renderer',
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
      //Update renderer
      else {
        $form = $this->createForm(new RendererType(), $renderer);
        $form->submit($data);
        $em->persist($renderer);
        $em->flush();
      }

      return new JsonResponse($data);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function previewBatchAction(Request $request, $username, $batch) {
      $this->denyAccessUnlessGranted('ROLE_COMPOSE');
      $method = $request->getMethod();

      $em = $this->getDoctrine()->getManager();

      if ($method == 'GET') {
        $whereClauses = array(
          'user = :username',
          'batch = :batch',
        );
        $parameters = array(
          'username' => $username,
          'batch' => $batch,
        );
        $prevBatches = $this->getEntity('PreviewBatch', $whereClauses, $parameters);

        return new JsonResponse($prevBatches);
      }
      elseif ($method == 'PUT') {
        $data = json_decode($request->getContent(), TRUE);

        // Insert/Update PreviewBatch based on (user, batch)
        $this->getDoctrine()->getConnection()->executeUpdate('
        INSERT INTO PreviewBatch (user, batch, message, create_time)
        VALUES (:user, :batch, :message, :time)
        ON DUPLICATE KEY UPDATE
        message = :message
        ', array(
          'user' => $username,
          'batch' => $batch,
          'message' => $data['message'],
          'time' => time(),
        ));

        // Create Task(s)
        if (!empty($data['tasks'])) {
          foreach ((array) $data['tasks'] as $task) {
            $previewTask = new PreviewTask();
            $previewTask->setUser($username);
            $previewTask->setBatch($batch);
            $previewTask->setRenderer($task['renderer']);
            if (!empty($task['options'])) {
              $previewTask->setOptions($task['options']);
            }
            else {
              // TODO: what if options are not provided
              // as per http://think.hm/tmp/prevem-spec/protocol/#1-composer-requests-a-preview
              // then should we fetch the options from corresponding renderer's options ?
            }
            $previewTask->setCreateTime(time());
            $em->persist($previewTask);
            $em->flush();
          }
        }
        return new JsonResponse();
      }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function previewBatchTasksAction(Request $request, $username, $batch) {
      $this->denyAccessUnlessGranted('ROLE_COMPOSE');

      $whereClauses = array(
        'user = :username',
        'batch = :batch',
      );
      $parameters = array(
        'username' => $username,
        'batch' => $batch,
      );
      $prevTasks = $this->getEntity('PreviewTasks', $whereClauses, $parameters);

      $rttl = $this->container->getParameter('render_ttl');
      $attempts = $this->container->getParameter('render_attempts');
      foreach ((array) $prevTasks as &$task) {
        $status = 'pending';
        if (!empty($task['finish_time'])) {
          // if finishTime is set and errorMessage is empty, then finished
          if (empty($task['error_message'])) {
            //TODO: we need to set the imageUrl to absolute image url as
            // web/files/{user}/{batch}/{md5(id . user . batch . renderer . options . createTime)}.png
            // but how to fetch the root path ?
            $status = 'finished';
          }
          // if finishTime is set and errorMessage is defined and attempts exceeds render_attempts, then failed
          elseif ($task['attempts'] > $attempts) {
            $status = 'failed';
          }
        }
        // if claimTime is set and it's not passed render_ttl, then rendering
        elseif (!empty($task['claim_time']) &&
          is_a($task['claim_time'], 'DateTime') &&
          // if claim_time is less than today + render_ttl
          (strtotime($task['claim_time']->format('Y-m-d H:i:s')) < (time() + $rttl))
        ) {
          $status = 'rendering';
        }
        $task['status'] = $status;
      }

      return new JsonResponse(array('tasks' => $prevTasks));
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function previewTaskClaimAction(Request $request) {
      $this->denyAccessUnlessGranted('ROLE_RENDER');

      $data = json_decode($request->getContent(), TRUE);
      $rttl = $this->container->getParameter('render_ttl');
      $bttl = $this->container->getParameter('batch_ttl');
      $rattempts = $this->container->getParameter('render_attempts');

      $conn = $this->getDoctrine()->getManager();
      $tasks = $conn->createQuery('
        SELECT pt
        FROM PrevemCoreBundle:PreviewTask pt
        WHERE pt.renderer = :renderer AND pt.attempts < :render_attempts AND (pt.claim_time IS NULL OR pt.claim_time < NOW() - :render_ttl ) AND (pt.create_time > NOW() - :batch_ttl )
        ORDER BY create_time ASC
        LIMIT 1
      ')
      ->setParameter('renderer', $data['renderer'])
      ->setParameter('render_attempts', $rattempts)
      ->setParameter('render_ttl', $rttl)
      ->setParameter('batch_ttl', $bttl)
      ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

      //If no preview task found then
      if (empty($tasks[0])) {
        return new JsonResponse();
      }
      else {
        $previewTask = $tasks[0];
        // fetch related previewBatch
        $previewBatch = $this->getEntity('PreviewBatch',
         array(
           'user = :user',
           'batch = :batch',
         ),
         array(
           'user' => $previewTask['user'],
           'batch' => $previewTask['batch'],
         )
        );
        $response = array(
          'PreviewBatch' => $PreviewBatch,
          'PreviewTask' => $previewTask,
        );

        return new JsonResponse($response);
      }
    }

    /**
    * Retrieve record(s) of $entity based on provided where clauses passed in array format
    *
    * @param string $entity
    * @param array $whereClauses
    * @param array $parameters
    *
    * @return array $entites
    */
    public function getEntity($entity, $whereClauses = array(), $parameters = array()) {
      $query = $this->getDoctrine()->getRepository("PrevemCoreBundle:{$entity}")->createQueryBuilder('e');

      //add filter(s)
      foreach ($whereClauses as $clause) {
        $query->where($clause);
      }

      //set parameters
      foreach ($parameters as $id => $value) {
        $query->setParameter($id, $value);
      }
      $entities = $query->getQuery()->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

      return $entities;
    }

}
