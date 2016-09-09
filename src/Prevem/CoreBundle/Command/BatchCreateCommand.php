<?php

namespace Prevem\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Guzzle\Http\Client;
use Symfony\Component\Console\Helper\ProgressBar;

class BatchCreateCommand extends ContainerAwareCommand
{

  // ./app/console batch:create
  //  --subject 'Hello world'
  //  --text "Hello world"
  //  --render thunderlook,iphone
  //  --url 'http://user:pass@localhost:9000/'
  //  --out '/tmp/rendered/'
  protected function configure()
    {
      $this->setName('batch:create')
           ->setDescription('Create new batch')
           // the form attribute isn't there in spec but I think its useful but making it optional
           ->addOption('from', NULL, InputOption::VALUE_OPTIONAL, 'Provide from email address')
           ->addOption('subject', NULL, InputOption::VALUE_REQUIRED, 'Provide base Url')
           ->addOption('text', NULL, InputOption::VALUE_REQUIRED, 'Provide Email body text')
           ->addOption('render', NULL, InputOption::VALUE_REQUIRED, 'Provide one or more renderer name.')
           ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'Provide base Url')
           ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $url = $input->getOption('url');
      $username = parse_url($url, PHP_URL_USER);
      $password = parse_url($url, PHP_URL_PASS);
      $batch = 'prevem-cli-' . substr(sha1(rand()), 0, 8);
      $renderers = explode(',', $input->getOption('render'));

      $jsonContent =  array(
        'message' =>
          array(
            'from' => $input->getOption('from'),
            'subject' => $input->getOption('subject'),
            'body_html' => '<html><body>' . $input->getOption('text') . '</body></html>',
            'body_text' => $input->getOption('text'),
          ),
        'tasks' => array(),
      );
      foreach ((array) $renderers as $renderer) {
        $jsonContent['tasks'][] = array('renderer' => $renderer);
      }

      $client = new Client();
      $headers = $this->getApplication()
                      ->getKernel()
                      ->getContainer()
                      ->get('prevem_core.prevem_utils')
                      ->getAuthorizedHeaders($username, $password, 'Basic');
      $client->setDefaultOption('headers', $headers);
      $client->put($url . "previewBatch/{$username}/{$batch}")
             ->setBody(json_encode($jsonContent), 'application/json')
             ->send();

      $bar = new ProgressBar($output, 10);
      $bar->setBarCharacter('=');
      $output->writeln("In progress");

      // Create outpath file path if not exist
      $outputPath = $input->getOption('out');
      if (!file_exists($outputPath)) {
        mkdir($outputPath, 0777, true);
      }
      //retrieve json data and write the data to $outputPath/{$username}_{$batch}.json file
      $jsonData = $client->get($url . "previewBatch/{$username}/{$batch}/tasks")->send()->getBody();
      file_put_contents($outputPath . "/{$username}_{$batch}.json", $jsonData);

      // Show progress bar and sleep for 1 sec :p
      for ($i = 0; $i < 10; $i++) {
        usleep(50000); //total of 1sec
        $bar->advance();
      }
      $bar->finish();
      $output->writeln("\n");
    }

}
