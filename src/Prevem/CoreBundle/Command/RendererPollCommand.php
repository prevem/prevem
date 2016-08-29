<?php

namespace Prevem\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;


class RendererPollCommand extends ContainerAwareCommand
{
  protected function configure()
    {
      $this
           ->setName('renderer:poll')
           ->setDescription('Create new renderer')
           ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'Provide base Url')
           ->addOption('name', NULL, InputOption::VALUE_REQUIRED, 'Renderer name')
           ->addOption('cmd', NULL, InputOption::VALUE_REQUIRED, 'Renderer name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $output->writeln('tadaaaaaah!');
    }

}
