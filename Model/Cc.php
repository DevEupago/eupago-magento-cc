<?php
namespace Eupago\Cc\Model;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;


class Cc extends \Magento\Payment\Model\Method\AbstractMethod
{


 	public $_formBlockType = 'Eupago\Cc\Block\Form\Cc';
	public $_infoBlockType = 'Eupago\Cc\Block\Info\Cc';
	protected $_canCapture = true;
	protected $_canFetchTransactionInfo = true;
	protected $_code = 'eupago_cc';

    /**
     * @var \Magento\Framework\App\Response\Http
     */




	    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return $this
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
       
        $result = $this->soapApiPedidoCc($payment,$amount);
        //$this->processErrors("Erro ao gerar result: ", print_r($result, true));

        if($result == false) {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
        } else {
            if($result->estado == 0){
                 
                $payment->setTransactionId($result->referencia);
                $payment->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,['referencia'=> $result->referencia,'method'=>'euPago - Cartão de crédito','resposta'=> $result->resposta,'url' => $result->url]);
                $payment->setIsTransactionClosed(false);
                $payment->setAdditionalInformation('referencia', $result->referencia);
                $payment->setAdditionalInformation('valor', $result->valor);
                $payment->setAdditionalInformation('url', $result->url);
                
              

            } else {
                $payment->setTransactionId(-1);
                $payment->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,['error_cod'=>$result->estado,'error_description'=>$result->resposta]);
                $payment->setIsTransactionClosed(false);
                $errorMsg = $result->resposta;
            }
        }


        if(isset($errorMsg)){
            $this->processErrors("Erro ao gerar referência: ", $errorMsg);
			throw new PaymentException(__($errorMsg));
        }
		
        return $this;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return $this|void
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
		
        if($payment->getMethod() != 'eupago_cc')
            return;

		$payment_info = $payment->getAdditionalInformation();
		//if($payment_info['method_title'] == 'euPago - Cartão de crédito' && is_numeric($payment_info['referencia'])){
			//print_r($payment_info['referencia']);die();
            $referencia = $payment_info['referencia'];
            $entidade = '10047';

		//}
        
        if(!(isset($referencia) && $referencia != null)){
            throw new PaymentException(__("Nao foi encontrado pedido Cartao de credito"));
        }
        

        $result = $this->soapApiInformacaoReferencia($referencia, $entidade);
     
        if($result == false) {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
        } else {
            if(($result->estado_referencia == 'paga' || $result->estado_referencia == 'transferida' || $result->estado_referencia == "em processamento")
                && $payment->getOrder()->getBaseTotalDue() == $result->valor){
                $payment->setTransactionId($referencia."-capture");
                $payment->setParentTransactionId($referencia);
                $payment->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,['referencia'=>$referencia,'resposta'=>$result->resposta,'method'=>'Cartão de crédito', "data de pagamento"=>$result->data_pagamento,  "hora de pagamento"=>$result->hora_pagamento]);
                $payment->setIsTransactionClosed(true);

            }else{
                if($payment->getOrder()->getBaseTotalDue() != $result->valor)
                    $errorMsg ="o valor pago não corresponde ao valor da encomenda";
                else
                    $errorMsg = "a referência não se encontra paga";

               $this->processErrors("Erro ao marcar como pago: ", "\nincrementId: ".$payment->getOrder()->getIncrementId()."\nreferencia: ".$referencia."\nerro: ". $errorMsg , null, 'eupago_cc.log');
            }
        }

        if(isset($errorMsg)){
           throw new PaymentException(__($errorMsg));
        }

        return $this;
    }
	
	/**
     * Get API Key from configurations
     * @return string
    */
    public function getAPIKey()
    {
        return trim($this->getConfigData('chave'));
    }

     private function getSoapUrl(){
        $version = 'eupagov20';
        $chave = $this->getConfigData('chave');
        $demo = explode("-",$chave);

        if($demo[0] == 'demo'){
            return 'http://sandbox.eupago.pt/replica.'.$version.'.wsdl';
        }
        return 'https://clientes.eupago.pt/'.$version.'.wsdl';
    }




    // faz pedido à eupago via SOAP Para gerar pedido pagamento por cartão de crédito
    private function soapApiPedidoCc(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

        $order = $payment->getOrder();
        $name = $order->getShippingAddress()->getData("firstname") ." " . $order->getShippingAddress()->getData("lastname");
        $email = $order->getShippingAddress()->getData("email");

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        $urlRetorno = substr($storeManager->getStore()->getBaseUrl(),0,-1) . '/Eupago/Callback/cc?order_id=' . $order->getIncrementId() . '&';
        //$this->processErrors("Erro ao gerar urlRetorno: ", print_r($urlRetorno, true));
        
        $client = new \Zend\Soap\Client($this->getSoapUrl(), ['cache_wsdl' => WSDL_CACHE_NONE]);// chamada do serviço SOAP
                
        $arraydados = ["chave" => $this->getConfigData('chave'), "valor" => $amount, "id" => $order->getIncrementId(), "url_retorno" => $urlRetorno, "url_logotipo" => "", "nome" => $name, "email"=> $email, "lang"=>'pt',"comentario"=>"", "tds"=>'1'];
               $this->processErrors("Erro ao gerar arraydados: ", print_r($arraydados, true));
        $result = $client->PedidoCC($arraydados);
                
        
        return $result;
    }

    // faz pedido à eupago para obter o estado da referencia
    private function soapApiInformacaoReferencia($referencia, $entidade){

        $arraydados = ["chave" => $this->getConfigData('chave'), "referencia" => $referencia, "entidade" => $entidade];

        $client = new \Zend\Soap\Client($this->getSoapUrl(), ['cache_wsdl' => WSDL_CACHE_NONE]);// chamada do serviço SOAP

        try {
            $result = $client->informacaoReferencia($arraydados);
        }
        catch (\Exception $e) {
            throw new PaymentException(__("SOAP Fault: (faultcode: ".$e->getMessage()));
            return false;
        }

        return $result;
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
