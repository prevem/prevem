<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Prevem\CoreBundle\Entity\PreviewTask;
use Prevem\CoreBundle\Entity\previewBatch;
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

      $ttl = $request->query->get('render_agent_ttl');
      // If render_agent_ttl GET argument not provided then set the default
      $ttl = (!empty($ttl)) ? $ttl : $this->container->getParameter('render_agent_ttl');

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
          'e.user = :username',
          'e.batch = :batch',
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

        $previewBatch = $em->getRepository('PrevemCoreBundle:PreviewBatch')->find(array('user' => $username, 'batch' => $batch));
        if (!$previewBatch) {
          $previewBatch = new PreviewBatch();
          $previewBatch->setUser($username);
          $previewBatch->setBatch($batch);
        }
        $previewBatch->setMessage(json_encode($data['message']));
        $previewBatch->setCreateTime(new \DateTime());
        $em->persist($previewBatch);
        $em->flush();

        // Create Task(s)
        if (!empty($data['tasks'])) {
          foreach ((array) $data['tasks'] as $task) {
            $em = $this->getDoctrine()->getManager();
            $previewTask = new PreviewTask();
            $previewTask->setUser($username);
            $previewTask->setBatch($batch);
            $previewTask->setRenderer($task['renderer']);
            if (!empty($task['options'])) {
              $previewTask->setOptions($task['options']);
            }

            $previewTask->setCreateTime(new \DateTime());
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
        'e.user = :username',
        'e.batch = :batch',
      );
      $parameters = array(
        'username' => $username,
        'batch' => $batch,
      );
      $prevTasks = $this->getEntity('PreviewTask', $whereClauses, $parameters);

      $rttl = $this->container->getParameter('render_ttl');
      $attempts = $this->container->getParameter('render_attempts');
      foreach ((array) $prevTasks as &$task) {
        $status = 'pending';
        if (!empty($task['finish_time'])) {
          // if finishTime is set and errorMessage is empty, then finished
          if (empty($task['error_message'])) {
            $task['imageUrl'] = $this->getImageFilePath(
              $task['id'],
              $task['user'],
              $task['batch'],
              $task['renderer'],
              $task['options'],
              strtotime($task['create_time']->format('Y-m-d H:i:s'))
            );
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

        //If a PreviewTask is found, then update the attempts and claimTime.
        $previewTaskEntity = $conn->getRepository('PrevemCoreBundle:PreviewTask')->find($tasks['id']);
        $previewTaskEntity->setAttempts((int) $tasks['attempts'] + 1);
        $previewTaskEntity->setClaimTime(new \DateTime());
        $conn->persist($previewTaskEntity);
        $conn->flush();

        // fetch related previewBatch
        $previewBatch = $this->getEntity('PreviewBatch',
         array(
           'e.user = :user',
           'e.batch = :batch',
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
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function previewTaskSubmitAction(Request $request) {
      $data = json_decode($request->getContent(), TRUE);
      $id = $data['id'];

      $em = $this->getDoctrine()->getManager();
      $previewTask = $em->getRepository('PrevemCoreBundle:PreviewTask')->find($id);

      if (!$previewTask) {
        return new JsonResponse('Preview task not found for id : ' . $id, 404);
      }

      if (!empty($data['image'])) {
        $user = $previewTask->getUser();
        $batch = $previewTask->getBatch();
        $renderer = $previewTask->getRenderer();
        $options = $previewTask->getOptions();
        $createTime = strtotime($previewTask->getCreateTime()->format('Y-m-d H:i:s'));

        //If image is provided, then store it in a file named
        // web/files/{user}/{batch}/{md5(id . user . batch . renderer . options . createTime)}.png
        $filePath = $this->getImageFilePath($id, $user, $batch, $renderer, $options, $createTime);

         // Save the png file $fileName to desire filepath as $filePath
        file_put_contents($filePath, base64_decode($data['image']));

        $previewTask->setErrorMessage(NULL); //clear any PreviewTask.errorMessage.
        $previewTask->setFinishTime(time()); //set PreviewTask.finishTime to now.
      }
      elseif (!empty($data['errorMessage'])) {
        $rattempts = $this->container->getParameter('render_attempts');
        if ($previewTask->getAttempts() >= $rattempts) {
          $previewTask->setFinishTime(time()); //set PreviewTask.finishTime to now.
        }
        else {
          $previewTask->setClaimTime(NULL); //set PreviewTask.claimTime to empty.
        }
      }

      // Update PreviewTask Entity record
      $em->persist($previewTask);
      $em->flush();

      return new JsonResponse();
    }

    public function loginAction(Request $request) {
      $data = json_decode($request->getContent(), TRUE);
      if (empty($data['username'])) {
        return new JsonResponse('Username is not provide', 401);
      }

      $username = $data['username'];
      $user = $this->getDoctrine()
                   ->getManager()
                   ->getRepository('PrevemCoreBundle:User')
                   ->find($username);
      if (!$user) {
        return new JsonResponse('Username not found',  401);
      }

      // If ttl argument is passed
      $ttl = $request->query->get('ttl');
      if ($ttl) {
        $this->container->setParameter('lexik_jwt_authentication.token_ttl', $ttl);
      }

      $token = $this->get('lexik_jwt_authentication.encoder')
                    ->encode(['username' => $username]);

      return new JsonResponse(['token' => $token]);
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
      $query->where(implode(' AND ', $whereClauses));

      //set parameters
      foreach ($parameters as $id => $value) {
        $query->setParameter($id, $value);
      }
      $entities = $query->getQuery()->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

      return $entities;
    }

    /**
    * Return the desired image file path as
    * web/files/{user}/{batch}/{md5(id . user . batch . renderer . options . createTime)}.png
    */
    public function getImageFilePath($id, $user, $batch, $renderer, $options, $createTime) {
      $imageDir = $this->container->getParameter('image_dir');
      $filePath = implode('/', array(
        $imageDir,
        $user,
        $batch
      ));
      $fileName =  md5($id . $user . $batch . $renderer . $options . $createTime) . '.png';

      return $filePath . $fileName;
    }
}
