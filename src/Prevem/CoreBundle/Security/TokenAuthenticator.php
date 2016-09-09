<?php

namespace Prevem\CoreBundle\Security;

use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoder;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TokenAuthenticator extends AbstractGuardAuthenticator {

  private $jwtEncoder;
  private $em;
  private $container;
  private $isBasic = FALSE;

  public function __construct(JWTEncoder $jwtEncoder, EntityManager $em, ContainerInterface $container) {
    $this->jwtEncoder = $jwtEncoder;
    $this->em = $em;
    $this->container = $container;
  }

  public function getCredentials(Request $request) {
    // bypass the login route (method=POST| url=/user/login) which is used to obtain valid JWT token
    if ($request->getPathInfo() == '/user/login') {
      return;
    }

    $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
    $token = $extractor->extract($request);

    if (!$token) {
      $extractor = new AuthorizationHeaderTokenExtractor('Basic', 'Authorization');
      $token = $extractor->extract($request);
      if (!$token) {
        return new CustomUserMessageAuthenticationException('Invalid Token');
      }
      else {
        $this->isBasic = TRUE;
      }
    }

    return $token;
  }

  public function getUser($credentials, UserProviderInterface $userProvider) {
    if ($this->isBasic) {
      list($username, $password) = explode(':', base64_decode($credentials));
      $data = array('username' => $username, 'password' => $password);
    }
    else {
      $data = $this->jwtEncoder->decode($credentials);
    }

    if ($data === FALSE) {
      throw new CustomUserMessageAuthenticationException('Invalid Token');
    }

    return $this->em->getRepository('PrevemCoreBundle:User')->find($data['username']);
  }

  public function checkCredentials($credentials, UserInterface $user) {
    if ($user instanceof UserInterface) {
      if ($this->isBasic) {
        list($username, $password) = explode(':', base64_decode($credentials));
        return $this->container->get('security.password_encoder')->isPasswordValid($user, $password, $user->getSalt());
      }
      else {
        return TRUE;
      }
    }

    return FALSE;
  }

  public function onAuthenticationFailure(Request $request, AuthenticationException $exception) {
    return new JsonResponse('Bad credential', 401);
  }

     public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
     {
         return NULL;
     }

     public function supportsRememberMe()
     {
         return FALSE;
     }

     public function start(Request $request, AuthenticationException $authException = NULL) {
       return new JsonResponse($authException->getMessage(), 401);
     }

}
