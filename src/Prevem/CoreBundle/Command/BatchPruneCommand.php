<?php

namespace Prevem\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;

class BatchPruneCommand extends ContainerAwareCommand
{
  protected function configure()
    {
      $this->setName('batch:prune')
           ->setDescription('Delete batch and tasks data older as per the provided argument')
           ->addArgument('duration', InputArgument::REQUIRED, 'How old data you want to delete? e.g. \'14 days ago\'' );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $io = new SymfonyStyle($input, $output);
      $duration = $input->getArgument('duration');
      $prevTime = strtotime($duration);
      $curTime = strtotime('now');
      if (!$prevTime) {
        $io->error("Incorrect duration provided : {$duration}");
        return 1;
      }

      $this->getContainer()->get('doctrine')
          ->getManager()
          ->createQueryBuilder('e')
          ->delete('PrevemCoreBundle:PreviewTask', 'e')
          ->where('UNIX_TIMESTAMP(e.createTime) BETWEEN ' . $prevTime . ' AND ' . $curTime )
          ->getQuery()
          ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
      $io->success('Preview task(s) deleted successfully');


      $this->getContainer()->get('doctrine')
          ->getManager()
          ->createQueryBuilder('e')
          ->delete('PrevemCoreBundle:PreviewBatch', 'e')
          ->where('UNIX_TIMESTAMP(e.createTime) BETWEEN ' . $prevTime . ' AND ' . $curTime )
          ->getQuery()
          ->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
      $io->success('Preview batch(s) deleted successfully');

    }

}
