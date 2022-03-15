<?php

namespace Eupago\Cc\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AfterPlaceOrderObserver extends AbstractDataAssignObserver
{
    /**
     * Order Model
     *
     * @var \Magento\Sales\Model\Order $order
     */
    protected $order;

    public function __construct(
        \Magento\Sales\Model\Order $order
    )
    {
        $this->order = $order;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $orderId = $observer->getEvent()->getOrderIds();
        $order = $this->order->load($orderId);
        $currentState = $order->getState();

        $save = false;
        if ($currentState !== $order::STATE_NEW) {
            $order->setState($order::STATE_PENDING_PAYMENT);
            $order->setStatus('pending');
            $save = true;
        }
        if ($save) {
            $order->save();
        }
    }
}