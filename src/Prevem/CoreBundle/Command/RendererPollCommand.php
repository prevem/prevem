<?php

namespace Prevem\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Guzzle\Http\Client;

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
      list($scriptPath, $action) = explode(' ', $input->getOption('cmd'));
      $name = $input->getOption('name');
      $username = parse_url($input->getOption('url'), PHP_URL_USER);
      $url = $input->getOption('url');
      $io = new SymfonyStyle($input, $output);

      switch ($action) {
        case 'about':
        case 'render':
          $client = new Client();
          $client->setDefaultOption('headers', $this->getAuthorizedHeaders($username));

          $process = new Process("php {$scriptPath} {$action}");
          $process->run();
          $jsonContent = $process->getOutput();

          if (!$process->isSuccessful() || !is_array(json_decode($jsonContent, TRUE))) {
            $io->error('Unable to fetch data for renderer');
          }

          if ($action == 'about') {
            $client->put($url . "renderer/{$name}")
                  ->setBody($jsonContent, 'application/json')
                  ->send();
            $io->success('Updated renderer:' . $name);
          }
          else {
            $client->post($url . "previewTask/submit")
                   ->setBody($jsonContent, 'application/json')
                   ->send();
            $io->success('Preview Task submitted successfully');
          }
          break;

        default:
          $io->error('Unrecognized action: ' . $action);
          break;

      }
    }

    protected function getAuthorizedHeaders($username, $headers = array('Accept' => 'application/json')) {
        $token = $this->getApplication()
                      ->getKernel()
                      ->getContainer()
                      ->get('lexik_jwt_authentication.encoder')
                      ->encode(['username' => $username]);
        $headers['Authorization'] = 'Bearer ' . $token;

        return $headers;
    }
}
