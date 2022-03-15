<?php

namespace Eupago\Cc\Controller\CallBack;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;

class Cc extends \Magento\Framework\App\Action\Action {

	protected $checkoutSession;
    protected $orderRepository;
    protected $messageManager;

	/**
     * Constructor
     * 
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->_messageManager = $context->getMessageManager();

        parent::__construct($context);
    }
	
	public function execute()
    {
    	$orderId = $this->getRequest()->getParam('order_id');
		//var_dump($orderId);
    	if($orderId){
            $response = $this->responseActionCc();
    	} else {
    		$response = $this->allAction();
    	
		
		$JsonFactory = $this->_objectManager->get('Magento\Framework\Controller\Result\JsonFactory');
		$result = $JsonFactory->create();
		$result = $result->setData($response);
		if(!isset($response['success']))
			 $result->setHttpResponseCode(403);
		return $result; 
		}
    }

    /**
     * @var $validated is initialized to true
     * @var $orderId is set to 'default', might make it a number
     */
    private function responseActionCc() {
    	
        if($this->getRequest()->isGet()){
            $orderId = $this->getRequest()->getParam('order_id');
            $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
			$order = $orderFactory->create()->loadByIncrementId($orderId);
			$payment_info = $order->getPayment()->getAdditionalInformation();
			if($payment_info['method_title'] == 'Cartão de crédito' && is_numeric($payment_info['referencia'])){
			$referencia = $payment_info['referencia'];
			//print_r($payment_info['method_title']);die();
            $entidade = '10047';
			}
			
            //$method = $order->getPayment()->getMethod();
            //if($method != 'eupago_cc')
                //return;
            
            $status = str_replace('/','',$this->getRequest()->getParam('eupago_status'));
            $result = $this->soapApiInformacaoReferencia($referencia, $entidade);
//print_r($status);die();
			
           
            if ($status == 'OK' || ($result->estado_referencia == 'paga' || $result->estado_referencia == 'transferida' || $result->estado_referencia == "em processamento")){
            	$orderState = Order::STATE_PROCESSING;
				$order->setState($orderState)->setStatus(Order::STATE_PROCESSING, true, 'The payment has been successful.');
    			$comment = 'Pagamento efetuado: '.$result->data_pagamento.'  '.$result->hora_pagamento;
    			$order->addStatusHistoryComment($comment)->setIsCustomerNotified(false)->setEntityName('order');
				$order->save();
                $this->messageManager->addSuccess(__('Payment has been successful. Order number: '. $orderId));
                $this->_redirect('checkout/onepage/success', array('_secure'=>true));
            } else {
                $this->cancelAction();
                $this->messageManager->addError(__('The payment has declined. Order number:'. $orderId));
                $this->_redirect('checkout/onepage/success', array('_secure'=>true));
                
            }


        } else {
            //Mage_Core_Controller_Varien_Action::_redirect('');
        }
         
    }

    public function cancelAction() {
    	$orderId = $this->getRequest()->getParam('order_id');
            $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
			$order = $orderFactory->create()->loadByIncrementId($orderId);
        if ($orderId) {

			$orderState = Order::STATE_CANCELED;
			$order->setState($orderState)->setStatus(Order::STATE_CANCELED, true, 'The payment has declined.');
			$order->save();
            
        }
    }
	
	private function allAction()
	{
			
		$callBack_params = (object)$this->getRequest()->getParams();
		
		if(!$this->validaParametrosCallback($callBack_params)) 
			return ["error" => "Faltam parametros no callback"];
		
		$orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
		$order = $orderFactory->create()->loadByIncrementId($callBack_params->identificador);
		//var_dump($params->valor);
		if($order->getId() == null)
			return ["error" => "a encomenda não existe"];

		$metodo_callback = null;
		switch(urldecode($callBack_params->mp)){
			case 'PC:PT':
				$metodo_callback = "eupago_multibanco";
				break;
			case 'MW:PT':
				$metodo_callback = "mbway";
				break;
			case 'CC:PT':
				$metodo_callback = "eupago_cc";
				break;	
			default:
				return ["error" => "método de pagamento inválido"];
		}
							
		if($order->getStatus() == "canceled")
			return ["error" => "não foi possivel concluir o pagamento porque o estado da encomenda é: ".$order_status];
		
		$method = $order->getPayment()->getMethod();
		//echo $method;
		//echo $callBack_params->mp;
		if(!isset($callBack_params->mp) || $method != $metodo_callback)
			return ["error" => "método de pagamento não corresponde ao da encomenda"];
				
		$chave_api = $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/'.$metodo_callback.'/chave');
		if($callBack_params->chave_api != $chave_api)
			//echo $metodo_callback;die();
			return ["error" => "chave API inválida", "chave" => $chave_api];
				
		if($order->getGrandTotal() != $callBack_params->valor) {
			//echo 'teste';die();
			return ["error" => "O valor da encomenda e o valor pago não correspondem!"];
		}

		
		if($order->getBaseTotalDue() == 0)
			return ["error" => "A encomenda já se encontra paga!"];
		
		if($order->getBaseTotalDue() < $callBack_params->valor)
			return ["error" => "O valor a pagamento é inferior ao valor pago!"];
		
		if($this->validaTransacao($callBack_params, $order)){
			echo '<pre>';
			var_dump($callBack_params);
			return $this->capture($order);			
		}else{
			return ["error" => "a referencia não corresponde a nenhuma transação desta encomenda."];
		}
	}
	
	private function validaTransacao($CallBack, $order){
			
		$payment = $order->getPayment();
		$payment_info = $payment->getAdditionalInformation();
		if($payment_info['referencia'] == $CallBack->referencia){
			//echo $payment_info['referencia'];die();
			return true;
		}
		return false;
	}
	
	private function validaParametrosCallback($params){
		return (isset($params->identificador, $params->valor, $params->chave_api, $params->mp, $params->referencia));
	}

	private function capture($order)
    {
        $payment = $order->getPayment();
		//var_dump($payment);die();
        try {
            $payment->capture();
			
			//var_dump($payment->capture());die();
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
        $order->save();
        return ["success" => true, "message" => "Pagamento foi capturado com sucesso."];
    }

	// faz pedido à eupago para obter o estado da referencia
    private function soapApiInformacaoReferencia($referencia, $entidade){

    	$chave_api = $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/eupago_cc/chave');

        $arraydados = ["chave" => $chave_api, "referencia" => $referencia, "entidade" => $entidade];

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

     private function getSoapUrl(){
        $version = 'eupagov20';
        $chave = $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/eupago_cc/chave');

        $demo = explode("-",$chave);

        if($demo[0] == 'demo'){
            return 'http://sandbox.eupago.pt/replica.'.$version.'.wsdl';
        }
        return 'https://clientes.eupago.pt/'.$version.'.wsdl';
    }


	
	
}