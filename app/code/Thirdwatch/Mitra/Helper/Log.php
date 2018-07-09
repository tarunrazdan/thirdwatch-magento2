<?php

namespace Thirdwatch\Mitra\Helper;
use Magento\Framework\App\Helper\AbstractHelper;

class Log extends AbstractHelper {

    protected $_logger;
    const DEBUG = 'debug';
    const INFO = 'info';
    const NOTICE = 'notice';
    const ERROR = 'error';
    const CRITICAL = 'critical';

    public function __construct(\Psr\Log\LoggerInterface $logger) {
        $this->_logger = $logger;
    }

    public function log($message, $level=null) {
        switch ($level){
            case self::DEBUG:
                $this->_logger->addDebug($message);
                break;
            case self::INFO:
                $this->_logger->addInfo($message);
                break;
            case self::NOTICE:
                $this->_logger->addNotice($message);
                break;
            case self::ERROR:
                $this->_logger->addError($message);
                break;
            case self::CRITICAL:
                $this->_logger->critical($message);
                break;
        }
    }
}
