<?php

namespace Lifeinthecloud\KeywordBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class UpdateCommand extends ContainerAwareCommand
{
    private $resourcePath = 'Resources/dictionary';
    private $dictionnaryFiles = array(
        'common',
        'insults',
    );

    protected function configure()
    {
        $this
            ->setName('precom:keyword:update')
            ->setDescription('Update les dictionnaires des keywords')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach($this->dictionnaryFiles as $dictionnaryFile) {

            $fileResource = $this
                ->getContainer()
                ->get('file_locator')
                ->locate('@PrecomKeywordBundle/' . $this->resourcePath . '/' . $dictionnaryFile.'.txt');

            if (file_exists($fileResource)) {
                $handle = fopen($fileResource, "r");

                $stack = array();
                if ($handle) {
                    $stack = array();
                    while (($line = fgets($handle)) !== false) {
                        $stack[trim($line)] = 0;
                    }

                    $dic = gzcompress(serialize($stack));

                    $fileCompressed = $this
                        ->getContainer()
                        ->get('file_locator')
                        ->locate('@PrecomKeywordBundle/' . $this->resourcePath . '/'.$dictionnaryFile.'.gz.php');
                    $file = fopen($fileCompressed, 'w+');
                    fwrite($file, $dic);

                    //$arr = unserialize(gzuncompress(file_get_contents($dictionnaryFile.'.gz.php')));
                    //echo '<pre>' . print_r($arr, true) . '</pre>';
                }
                fclose($file);
                fclose($handle);
            } else {
                throw new KeywordException(
                    'File %s.gz.php is not found in %s directory.',
                    0, array($file, $this->resourcePath));
            }
        }
    }

}
