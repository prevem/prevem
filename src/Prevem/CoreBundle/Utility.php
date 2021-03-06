<?php

namespace Prevem\CoreBundle;

use Doctrine\ORM\EntityManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Utility {

  private $jwtEncoder;
  private $em;
  private $container;

  public function __construct(JWTEncoder $jwtEncoder, EntityManager $em, ContainerInterface $container) {
    $this->container = $container;
    $this->jwtEncoder = $jwtEncoder;
    $this->em = $em;
  }

  /**
  * Return the desired image file path as
  * web/files/{user}/{batch}/{md5(id . user . batch . renderer . options . createTime)}.png
  */
  public function getImageFilePath($id, $user, $batch, $renderer, $options, $createTime) {
    $imageDir = $this->container->getParameter('image_dir');
    $options = is_array($options) ? json_encode($options) : $options;
    $filePath = implode(DIRECTORY_SEPARATOR, array(
      $imageDir,
      $user,
      $batch
    ));
    // if file path not exists then create one
    if (!file_exists($filePath)) {
      mkdir($filePath, 0777, true);
    }
    $fileName =  md5($id . $user . $batch . $renderer . $options . $createTime) . '.png';

    return $filePath . DIRECTORY_SEPARATOR . $fileName;
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
    $query = $this->em->getRepository("PrevemCoreBundle:{$entity}")->createQueryBuilder('e');

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
  * Return valid Basic token in header for Authorization encoded in base64_encode
  *  with <username:password> information.
  *
  * @param string $username
  * @param string $password
  * @param array $headers
  *
  * @return array $headers
  */
  public function getBasicAuthHeader($username, $password, $headers = array('Accept' => 'application/json')) {
    $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");

    return $headers;
  }

  /**
  * Return valid Basic token in header for Authorization encoded in JWT
  *  with username information.
  *
  * @param string $username
  * @param array $headers
  *
  * @return array $headers
  */
  public function getBearerAuthHeader($username, $headers = array('Accept' => 'application/json')) {
    $headers['Authorization'] = 'Bearer ' . $this->jwtEncoder->encode(['username' => $username]);

    return $headers;
  }
}
