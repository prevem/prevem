<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class PreviewTaskController extends Controller
{

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
          WHERE pt.renderer = :renderer AND
            ( pt.attempts < :render_attempts OR pt.attempts IS NULL ) AND
            ( pt.claimTime IS NULL OR pt.claimTime < CURRENT_TIMESTAMP() - :render_ttl ) AND
            ( pt.createTime > CURRENT_TIMESTAMP() - :batch_ttl )
          ORDER BY pt.createTime ASC
        ')
        ->setMaxResults(1)
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
        $previewTaskEntity = $conn->getRepository('PrevemCoreBundle:PreviewTask')->find($previewTask['id']);
        $previewTaskEntity->setAttempts((int) $previewTask['attempts'] + 1);
        $previewTaskEntity->setClaimTime(new \DateTime());
        $conn->persist($previewTaskEntity);
        $conn->flush();

        // fetch related previewBatch
        $previewBatch = $this->get('prevem_core.prevem_utils')->getEntity('PreviewBatch',
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
          'PreviewBatch' => $previewBatch,
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
        $options = json_encode($previewTask->getOptions());
        $createTime = strtotime($previewTask->getCreateTime()->format('Y-m-d H:i:s'));

        //If image is provided, then store it in a file named
        // web/files/{user}/{batch}/{md5(id . user . batch . renderer . options . createTime)}.png
        $filePath = $this->get('prevem_core.prevem_utils')
                         ->getImageFilePath($id, $user, $batch, $renderer, $options, $createTime);

         // Save the png file $fileName to desire filepath as $filePath
        file_put_contents($filePath, base64_decode($data['image']));
        $previewTask->setErrorMessage(NULL); //clear any PreviewTask.errorMessage.
        $previewTask->setFinishTime(new \DateTime()); //set PreviewTask.finishTime to now.
      }
      elseif (!empty($data['errorMessage'])) {
        $rattempts = $this->container->getParameter('render_attempts');
        if ($previewTask->getAttempts() >= $rattempts) {
          $previewTask->setFinishTime(new \DateTime()); //set PreviewTask.finishTime to now.
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
}
