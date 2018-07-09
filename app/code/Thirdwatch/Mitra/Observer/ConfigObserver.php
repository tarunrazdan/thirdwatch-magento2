<?php

namespace Thirdwatch\Mitra\Observer;
require_once(BP . '/lib' . DIRECTORY_SEPARATOR . 'thirdwatch-php' . DIRECTORY_SEPARATOR . 'autoload.php');

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;


class ConfigObserver implements ObserverInterface
{
    private function testPostback($url){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logger = $objectManager->create('Thirdwatch\Mitra\Helper\Log');

        $client = new \GuzzleHttp\Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        try{
            $response = $client->post($url,
                ['body' => json_encode(
                    [
                        'test' => True
                    ]
                )]
            );

            if ($response->getStatusCode() == "200"){
                return True;
            }
            return False;
        } catch (\Exception $e) {
            $logger->log("tw-debug: ".$e->getMessage(), "debug");
            return False;
        }
    }

    public function execute(EventObserver $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logger = $objectManager->create('Thirdwatch\Mitra\Helper\Log');

        $storeURL = $helper->getStoreURL();
        $secret = $helper->getKey();
        $scorePostback = join('/', array(trim($storeURL, '/'), "mitra/postback/action/"));
        $actionPostback = join('/', array(trim($storeURL, '/'), "mitra/action/action/"));

        try{
            $testRespScore = $this->testPostback($scorePostback);

            if (!$testRespScore){
                throw new \Exception('Score Postback URL doesnot exists.');
            }
        } catch (\Exception $e){
            $logger->log("tw-debug: ".$e->getMessage(), "debug");
            throw new \Exception('Score Postback URL doesnot exists.');
        }

        try{
            $testRespPostback = $this->testPostback($actionPostback);

            if (!$testRespPostback){
                throw new \Exception('Action Postback URL doesnot exists.');
            }
        } catch (\Exception $e){
            throw new \Exception('Action Postback URL doesnot exists.');
            $logger->log("tw-debug: ".$e->getMessage(), "debug");
        }

        try{
            $client = new \GuzzleHttp\Client([
                'headers' => [ 'Content-Type' => 'application/json' ]
            ]);

            $response = $client->post('https://staging.thirdwatch.co/neo/v1/addpostbackurl/',
                ['body' => json_encode(
                    [
                        'score_postback' => $scorePostback,
                        'action_postback'=>$actionPostback,
                        'secret'=>$secret
                    ]
                )]
            );

            if ($response->getStatusCode() != "200"){
                throw new \Exception('Postback Url not registered successfully.');
            }
        } catch (\Exception $e){
            $logger->log("tw-debug: ".$e->getMessage(), "debug");
            throw new \Exception('Postback Url not registered successfully. Please try again.');
        }
    }
}