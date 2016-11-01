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
           ->addOption('interval', NULL, InputOption::VALUE_REQUIRED, 'Interval in ms')
           ->addOption('max-tasks', NULL, InputOption::VALUE_REQUIRED, 'Maximum number of tasks to be claim')
           ->addOption('cmd', NULL, InputOption::VALUE_REQUIRED, 'Rendering script path');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      $username = parse_url($input->getOption('url'), PHP_URL_USER);
      $password = parse_url($input->getOption('url'), PHP_URL_PASS);

      $client = new Client();
      $headers = $this->getApplication()
                      ->getKernel()
                      ->getContainer()
                      ->get('prevem_core.prevem_utils')
                      ->getBasicAuthHeader($username, $password);
      $client->setDefaultOption('headers', $headers);

      $process = new Process(sprintf('php %s about', $input->getOption('cmd')));
      $process->run();
      $rendererContent = json_decode($process->getOutput(), TRUE);
      if (!$process->isSuccessful() || !is_array($rendererContent)) {
        $io->error('Unable to fetch data for renderer');
        exit(1);
      }

      $taskCount = 0;
      while ($taskCount < $input->getOption('max-tasks')) {
        foreach ($rendererContent as $renderer => $params) {
          $this->registerRenderer($input, $output, $params, $renderer, $client);
          $this->doPolling($input, $output, $renderer, $client, $taskCount);
        }
        usleep(1000 * $input->getOption('interval'));
      }
    }

    protected function registerRenderer(InputInterface $input, OutputInterface $output, $params, $renderer, $client) {
      $io = new SymfonyStyle($input, $output);

      $client->put($input->getOption('url') . "renderer/{$renderer}")
              ->setBody(json_encode($params), 'application/json')
              ->send();
      $io->success('Updated renderer:' . $renderer);
    }

    protected function doPolling(InputInterface $input, OutputInterface $output, $renderer, $client, &$taskCount) {
      $io = new SymfonyStyle($input, $output);
      $jsonContent = json_encode(array('renderer' => $renderer));

      $responseData = $client->post($input->getOption('url') . "previewTask/claim")
                             ->setBody($jsonContent, 'application/json')
                             ->send()
                             ->json();

      // increment total taskcount with number of preview tasks found
      $taskCount += count($responseData);

      if (count($responseData) < 1) {
        $io->error('There is no eligible preview task to claim for renderer - ' . $renderer);
        return;
      }

      $process = new Process(sprintf('php %s render', $input->getOption('cmd')));
      $process->setInput(json_encode($responseData));
      $process->run();
      $jsonContent = $process->getOutput();

      $responseData = $client->post($input->getOption('url') . "previewTask/submit")
                             ->setBody($jsonContent, 'application/json')
                             ->send()
                             ->json();
      $io->success('Preview task successfully submitted');
    }

}
