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

  protected function configure() {
      $this->setName('renderer:poll')
           ->setDescription('Create new renderer')
           ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'Provide base Url')
           ->addOption('name', NULL, InputOption::VALUE_REQUIRED, 'Renderer name')
           ->addOption('cmd', NULL, InputOption::VALUE_REQUIRED, 'Rendering script path');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      $params = array(
        'scriptPath' => $input->getOption('cmd'),
        'renderer' => $input->getOption('name'),
        'username' => parse_url($input->getOption('url'), PHP_URL_USER),
        'password' => parse_url($input->getOption('url'), PHP_URL_PASS),
        'url' => $input->getOption('url'),
      );

      $client = new Client();
      $headers = $this->getApplication()
                      ->getKernel()
                      ->getContainer()
                      ->get('prevem_core.prevem_utils')
                      ->getBasicAuthHeader($params['username'], $params['password']);
      $client->setDefaultOption('headers', $headers);

      $this->registerRenderer($input, $output, $params, $client);
      $this->doPolling($input, $output, $params, $client);
    }

    protected function registerRenderer(InputInterface $input, OutputInterface $output, $params, $client) {
      $io = new SymfonyStyle($input, $output);

      $process = new Process(sprintf('php %s about', $params['scriptPath']));
      $process->run();
      $jsonContent = $process->getOutput();

      if (!$process->isSuccessful() || !is_array(json_decode($jsonContent, TRUE))) {
        $io->error('Unable to fetch data for renderer');
        exit(1);
      }

      $client->put($params['url'] . "renderer/" . $params['renderer'])
               ->setBody($jsonContent, 'application/json')
               ->send();
      $io->success('Updated renderer:' . $params['renderer']);
    }

    protected function doPolling(InputInterface $input, OutputInterface $output, $params, $client) {
      $io = new SymfonyStyle($input, $output);
      $jsonContent = json_encode(array('renderer' => $params['renderer']));

      $responseData = $client->post($params['url'] . "previewTask/claim")
                             ->setBody($jsonContent, 'application/json')
                             ->send()
                             ->json();
      if (count($responseData) < 1) {
        $io->error('There is no eligible preview task to claim');
        exit(1);
      }

      $process = new Process(sprintf('php %s render', $params['scriptPath']));
      $process->setInput(json_encode($responseData));
      $process->run();
      $jsonContent = $process->getOutput();

      $responseData = $client->post($params['url'] . "previewTask/submit")
                             ->setBody($jsonContent, 'application/json')
                             ->send()
                             ->json();
      $io->success('Preview task successfully submitted');
    }

}
