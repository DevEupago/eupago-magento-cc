<?php
namespace Eupago\Cc\Block\Checkout\Onepage;
use Magento\Framework\Controller\ResultFactory;

class Success extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    public $_checkoutSession;
    private $redirectFactory; 
    protected $redirect;
    protected $response;
    
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Response\Http $response,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->redirect = $redirect;
        $this->response = $response;
        $this->redirectFactory = $redirectFactory;
        parent::__construct($context, $data);
    }


    public function getCcData()
    {

		$info = $this->_checkoutSession->getLastRealOrder()->getPayment()->getAdditionalInformation();
		
        $this->processErrors("Erro ao gerar getCcData: ", print_r($info, true));   

        if($info['method_title'] == "Multibanco")
            return null;
       
       
        header('Location: '. $info['url']);
        exit;
       
          
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
