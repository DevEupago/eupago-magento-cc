<?php
namespace Eupago\Cc\Block\Info;

use Magento\Framework\Phrase;
use Magento\Framework\Registry;

class Cc extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    public $_template = 'Eupago_Cc::info/cc.phtml';

    public function getSpecificInformationCc()
    {
        $informations['referencia'] = $this->getInfo()->getAdditionalInformation('referencia');
        $informations['valor'] = $this->getInfo()->getAdditionalInformation('valor');
    
         //$this->processErrors("Erro ao gerar information: ", print_r($informations, true));
		
        return (object)$informations;
		
    }

    public function getCcDataAdmin()
    {
       $informationsCc['referencia'] = $this->getInfo()->getAdditionalInformation('referencia');
       $informationsCc['valor'] = $this->getInfo()->getAdditionalInformation('valor');

        return (object)$informationsCc;
        
    }

    public function getCcData(){

        return (object)$this->getInfo()->getAdditionalInformation();
    }

    public function getMethodCode()
    {
        //return $this->getInfo()->getMethodInstance()->getCode();
    }

    /**
     * @param $msg
     *
     */
    public function processErrors($type, $msg)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/eupago.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info(print_r($type.$msg,true));
    }

    
	
}