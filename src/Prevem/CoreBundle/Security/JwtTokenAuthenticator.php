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

class JwtTokenAuthenticator extends AbstractGuardAuthenticator {

  private $jwtEncoder;
  private $em;

  public function __construct(JWTEncoder $jwtEncoder, EntityManager $em) {
    $this->jwtEncoder = $jwtEncoder;
    $this->em = $em;
  }

  public function getCredentials(Request $request) {
    $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
    $token = $extractor->extract($request);

    if (!$token) {
      return new CustomUserMessageAuthenticationException('Invalid Token');
    }
    return $token;
  }

  public function getUser($credentials, UserProviderInterface $userProvider) {
    $data = $this->jwtEncoder->decode($credentials);

    if ($data === FALSE) {
      throw new CustomUserMessageAuthenticationException('Invalid Token');
    }

    return $this->em->getRepository('PrevemCoreBundle:User')->find($data['username']);
  }

  public function checkCredentials($credentials, UserInterface $user) {
    return TRUE;
  }

     public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
     {
         // TODO: Implement onAuthenticationFailure() method.
     }

     public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
     {
         return TRUE;
     }

     public function supportsRememberMe()
     {
         return FALSE;
     }

     public function start(Request $request, AuthenticationException $authException = NULL) {
       return new JsonResponse($authException->getMessage(), 401);
     }

}
