<?php

namespace Prevem\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Prevem\CoreBundle\Entity\User;

class UserCreateCommand extends ContainerAwareCommand
{
  protected function configure()
    {
      $this->setName('user:create')
           ->setDescription('Delete batch and tasks data older as per the provided argument')
           ->addArgument('username', InputArgument::REQUIRED, 'Provide username')
           ->addOption('pass', NULL, InputOption::VALUE_OPTIONAL, 'Provide password')
           ->addOption('role', NULL, InputOption::VALUE_OPTIONAL, 'provide user role(s). E.g. \'renderer,composer\'');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $action = 'updated';
      $io = new SymfonyStyle($input, $output);
      $username = $input->getArgument('username');
      $password = $input->getOption('pass');

      $roleMap = array(
        'renderer' => 'ROLE_RENDER',
        'compose' => 'ROLE_COMPOSE',
        'admin' => 'ROLE_ADMIN',
      );
      $roles = (array) explode(',', $input->getOption('role'));
      foreach ($roles as $key => $role) {
        if (!empty($roleMap[$role])) {
          $roles[$key] = $roleMap[$role];
        }
      }

      $em = $this->getContainer()->get('doctrine')->getManager();
      $user = $em->getRepository('PrevemCoreBundle:User')->find($username);
      if (!$user) {
        $action = 'created';
        if (empty($password)) {
          $io->error("No password provided and also user '{$username}' does not exist");
          return 1;
        }

        $user = new User();
        $user->setUsername($username);
        //default set roles to ROLE_USER
        $roles = empty($roles) ? array() : $roles;
      }

      $user->setRoles($roles);

      if (!empty($password)) {
        $encoder = $this->getContainer()->get('security.encoder_factory')->getEncoder($user);
        $password = $encoder->encodePassword($password, $user->getSalt());
        $user->setPassword($password);
      }

      $em->persist($user);
      $em->flush();
      $io->success("User {$action} successfully");
    }

}
