<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagBackendOrder\Components\Order\Hydrator;

use SwagBackendOrder\Components\Order\Struct\OrderStruct;

class OrderHydrator
{
    /**
     * @var PositionHydrator
     */
    private $positionHydrator;

    public function __construct(PositionHydrator $positionHydrator)
    {
        $this->positionHydrator = $positionHydrator;
    }

    /**
     * @return OrderStruct
     */
    public function hydrateFromRequest(\Enlight_Controller_Request_Request $request, $shippingAddressId=false)
    {
        $data = $request->getParams();
        $data = $data['data'];

        $orderStruct = new OrderStruct();

        $orderStruct->setCustomerId((int) $data['customerId']);
		
		if (empty($data['billingAddressId']) || $data['billingAddressId']==0) {
            $userDataAddress = Shopware()->Container()->get('dbal_connection')->createQueryBuilder('s_user')
            ->select(['u.default_billing_address_id', 'u.default_shipping_address_id', 'u.language'])
            ->from('s_user', 'u')
            ->where('u.id= :userId')
            ->setParameter('userId', $data['customerId'])
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);

            $data['billingAddressId'] = $userDataAddress['default_billing_address_id'];
            $data['languageShopId'] = $userDataAddress['language'];
        }
		
		$orderStruct->setBillingAddressId((int) $data['billingAddressId']);

        $orderStruct->setShippingAddressId($data['billingAddressId']);
        if ($data['shippingAddressId']) {
            $orderStruct->setShippingAddressId($data['shippingAddressId']);
        }

        if ($shippingAddressId>0) {
            $orderStruct->setShippingAddressId($shippingAddressId);
        }

        $orderStruct->setPaymentId((int) $data['paymentId']);
        $orderStruct->setDispatchId((int) $data['dispatchId']);
        $orderStruct->setLanguageShopId((int) $data['languageShopId']);
        $orderStruct->setCurrencyId((int) $data['currencyId']);

        $orderStruct->setCurrency((string) $data['currency']);
        $orderStruct->setDeviceType((string) $data['desktopType']);

        $orderStruct->setNetOrder((bool) $data['displayNet']);
        $orderStruct->setTaxFree((bool) $data['taxFree']);
        $orderStruct->setSendMail((bool) $data['sendMail']);

        $orderStruct->setTotal((float) $data['total']);
        $orderStruct->setTotalWithoutTax((float) $data['totalWithoutTax']);
        $orderStruct->setShippingCostsNet((float) $data['shippingCostsNet']);
        $orderStruct->setShippingCosts((float) $data['shippingCosts']);
        $orderStruct->setShippingCostsTaxRate((float) $data['shippingCostsTaxRate']);

        $orderStruct->setAttributes($data['orderAttribute'][0]);

        foreach ($data['position'] as $position) {
            $positionStruct = $this->positionHydrator->hydrate($position);
            $orderStruct->addPosition($positionStruct);
        }

        return $orderStruct;
    }
}
