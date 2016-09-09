<?php

namespace Prevem\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('PrevemCoreBundle:Default:index.html.twig', array('name' => $name));
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

}
