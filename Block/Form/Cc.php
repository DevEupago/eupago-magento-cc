<?php
namespace Eupago\Cc\Block\Form;

class Cc extends \Magento\Payment\Block\Form
{

    /**
     * Cc Template
     * @var string
     */
    public $_template = 'form/cc.phtml';


    protected function _construct()
    {
        $method = $this->getMethod();
        $this->showIcon = $method->getConfigData('mostra_icon');

       if(Mage::getStoreConfig('payment/cc/mostra_icon'))
            $this->setMethodLabelAfterHtml('<img style="padding:0 5px;"src="'.$this->getSkinUrl('images/cc_icon.png').'" />');
        
    }

    
}