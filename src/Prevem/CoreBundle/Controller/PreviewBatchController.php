<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Prevem\CoreBundle\Entity\PreviewBatch;
use Prevem\CoreBundle\Entity\PreviewTask;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class PreviewBatchController extends Controller
{

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
      $prevBatches = $this->get('prevem_core.prevem_utils')->getEntity('PreviewBatch', $whereClauses, $parameters);

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
    $prevTasks = $this->get('prevem_core.prevem_utils')->getEntity('PreviewTask', $whereClauses, $parameters);

    $rttl = $this->container->getParameter('render_ttl');
    $attempts = $this->container->getParameter('render_attempts');
    foreach ((array) $prevTasks as $key => $task) {
      $status = 'pending';
      if (!empty($task['finishTime'])) {
        // if finishTime is set and errorMessage is empty, then finished
        if (empty($task['errorMessage'])) {
          $task['imageUrl'] = $this->get('prevem_core.prevem_utils')->getImageFilePath(
            $task['id'],
            $task['user'],
            $task['batch'],
            $task['renderer'],
            $task['options'],
            strtotime($task['createTime']->format('Y-m-d H:i:s'))
          );
          $status = 'finished';
        }
        // if finishTime is set and errorMessage is defined and attempts exceeds render_attempts, then failed
        elseif ($task['attempts'] > $attempts) {
          $status = 'failed';
        }
      }
      // if claimTime is set and it's not passed render_ttl, then rendering
      elseif (!empty($task['claimTime']) &&
        is_a($task['claimTime'], 'DateTime') &&
        // if claimTime is less than today + render_ttl
        (strtotime($task['claimTime']->format('Y-m-d H:i:s')) < (time() + $rttl))
      ) {
        $status = 'rendering';
      }
      $prevTasks[$key]['status'] = $status;
    }

    return new JsonResponse(array('tasks' => $prevTasks));
  }

}
