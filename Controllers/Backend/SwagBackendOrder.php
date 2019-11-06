<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Models\Article\Detail;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Customer\PaymentData;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Dispatch\ShippingCost;
use Shopware\Models\Order\Order;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Shop\Currency;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;
use Shopware\Models\Tax\Tax;
use SwagBackendOrder\Components\ConfirmationMail\ConfirmationMailCreator;
use SwagBackendOrder\Components\ConfirmationMail\ConfirmationMailRepository;
use SwagBackendOrder\Components\ConfirmationMail\NumberFormatterWrapper;
use SwagBackendOrder\Components\CustomerRepository;
use SwagBackendOrder\Components\Order\Hydrator\OrderHydrator;
use SwagBackendOrder\Components\Order\OrderServiceInterface;
use SwagBackendOrder\Components\Order\Validator\InvalidOrderException;
use SwagBackendOrder\Components\Order\Validator\OrderValidator;
use SwagBackendOrder\Components\Order\Validator\Validators\ProductContext;
use SwagBackendOrder\Components\Order\Validator\Validators\ProductValidator;
use SwagBackendOrder\Components\PriceCalculation\Calculator\DiscountCalculator;
use SwagBackendOrder\Components\PriceCalculation\Calculator\ProductPriceCalculator;
use SwagBackendOrder\Components\PriceCalculation\Calculator\ShippingPriceCalculator;
use SwagBackendOrder\Components\PriceCalculation\Calculator\TotalPriceCalculator;
use SwagBackendOrder\Components\PriceCalculation\Context\PriceContextFactory;
use SwagBackendOrder\Components\PriceCalculation\DiscountType;
use SwagBackendOrder\Components\PriceCalculation\Hydrator\RequestHydrator;
use SwagBackendOrder\Components\PriceCalculation\Result\PriceResult;
use SwagBackendOrder\Components\PriceCalculation\Result\TotalPricesResult;
use SwagBackendOrder\Components\PriceCalculation\Struct\PositionStruct;
use SwagBackendOrder\Components\PriceCalculation\Struct\RequestStruct;
use SwagBackendOrder\Components\PriceCalculation\TaxCalculation;
use SwagBackendOrder\Components\Translation\PaymentTranslator;
use SwagBackendOrder\Components\Translation\ShippingTranslator;
use Shopware\Components\CSRFWhitelistAware;
class Shopware_Controllers_Backend_SwagBackendOrder extends Shopware_Controllers_Backend_ExtJs implements CSRFWhitelistAware
{
    /**
     * Return a list of customer on search or return a single customer on select.
     */
    public function postDispatch() {
        //$csrfToken = $this->container->get('BackendSession')->offsetGet('X-CSRF-Token');
        //$this->View()->assign([ 'csrfToken' => $csrfToken ]);
    }
    public function getCustomerAction()
    {
        /** @var CustomerRepository $repository */
        $repository = $this->get('swag_backend_order.customer_repository');

        if ($filter = $this->getListRequestParam()) {
            $result = $repository->getList($filter);

            $this->view->assign([
                'data' => $result,
                'total' => count($result),
                'success' => true,
            ]);

            return;
        }

        $customerId = (int) $this->Request()->get('searchParam');
        $result = $repository->get($customerId);

        $this->view->assign([
            'data' => $result,
            'total' => count($result),
            'success' => true,
        ]);
    }
    public function loadCartAction(){
        $reqs = $this->Request()->getParams();
		
		$userId = isset($reqs['userid'])?$reqs['userid']:'';
		
		try {
			$positions = Shopware()->Container()->get('dbal_connection')->createQueryBuilder()
            ->select(['basket.id', 'basket.articleID', 'basket.articleName', 'basket.quantity', 'basket.price'])
            ->from('s_order_basket', 'basket')
            ->where('userID = :userId')
            ->setParameter('userId', $userId)
            ->execute()
            ->fetchAll(\PDO::FETCH_ASSOC);
		} catch (Exception $ex) {
			echo json_encode(array('success'=>false, 'violations'=>$ex->getMessage()));
			return;
		}
		
		$totalCost = 0;
		foreach ($positions as $key=>$value) {
			$positions[$key]['create_backend_order_id'] = 0;
			$positions[$key]['mode'] = 0;
			$positions[$key]['articleId'] = $positions[$key]['articleID'];
			$positions[$key]['detailId'] = 0;
			$positions[$key]['articleNumber'] = $positions[$key]['ordernumber'];
			$positions[$key]['statusId'] = 0;
			$positions[$key]['statusDescription'] = 0;
			$positions[$key]['taxId'] = 1;
			$positions[$key]['taxRate'] = 19;
			$positions[$key]['taxDescription'] = '';
			$positions[$key]['inStock'] = 4;
			$positions[$key]['discountType'] = 0;
			$positions[$key]['isDiscount'] = false;
			$positions[$key]['total'] = $positions[$key]['quantity']*$positions[$key]['price'];
			$totalCost += $positions[$key]['quantity']*$positions[$key]['price'];
		}
		
		$result = array();
		$result['shippingCosts'] = 0;
		$result['shippingCostsNet'] = 0;
		$result['displayNet'] = 0;
		$result['oldCurrencyId'] = 0;
		$result['dispatchId'] = 0;
		$result['taxFree'] = false;
		$result['previousDisplayNet'] = false;
		$result['previousTaxFree'] = false;
		$result['previousDispatchTaxRate'] = 0;
		$result['positions'] = $positions;
		$result['userId'] = $userId;
		$result['total'] = $totalCost;
		
		echo json_encode(array('success'=>true, 'result'=>$result));
    	exit;
    }

    private function _saveShippingAddress($params)
    {
        if (
            !empty($params['data']['orderAttribute'][0]['country']) &&
            !empty($params['data']['shippingAttribute'][0]['postalcode']) &&
            !empty($params['data']['shippingAttribute'][0]['city'])
        ) {

            $userId = $params['data']['customerId'];

            try {
                $qb = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
                $res = $qb->insert('s_user_addresses')
                ->setValue('user_id', ':user_id')
                ->setValue('company', ':company')
                ->setValue('title', ':title')
                ->setValue('salutation', ':salutation')
                ->setValue('firstname', ':firstname')
                ->setValue('lastname', ':lastname')
                ->setValue('street', ':street')
                ->setValue('zipcode', ':zipcode')
                ->setValue('city', ':city')
                ->setValue('country_id', ':country_id')
                ->setValue('additional_address_line1', ':additional_address_line1')
                ->setValue('additional_address_line2', ':additional_address_line2')
                ->setParameter(':user_id', $userId)
                ->setParameter(':title', $params['data']['shippingAttribute'][0]['title'])
                ->setParameter(':company', $params['data']['shippingAttribute'][0]['company'])
                ->setParameter(':salutation', $params['data']['orderAttribute'][0]['salutation'])
                ->setParameter(':firstname', $params['data']['shippingAttribute'][0]['firstname'])
                ->setParameter(':lastname', $params['data']['shippingAttribute'][0]['lastname'])
                ->setParameter(':street', $params['data']['shippingAttribute'][0]['street'])
                ->setParameter(':zipcode', $params['data']['shippingAttribute'][0]['postalcode'])
                ->setParameter(':city', $params['data']['shippingAttribute'][0]['city'])
                ->setParameter(':country_id', $params['data']['orderAttribute'][0]['country'])
                ->setParameter(':additional_address_line1', $params['data']['shippingAttribute'][0]['address1'])
                ->setParameter(':additional_address_line2', $params['data']['shippingAttribute'][0]['address2'])
                ->execute();

                $params['data']['shippingAddressId'] = Shopware()->Db()->lastInsertId();

            } catch (Exception $ex) {
                // echo json_encode(array('success'=>false, 'violations'=>$ex->getMessage()));
                // return;
            }
        }
        return $params;
    }

    public function createOrderAction()
    {
        $adminIdentity = Shopware()->Container()->get('Auth')->getIdentity();
        $params = $this->Request()->getParams();
        $params = $this->_saveShippingAddress($params);

        /** @var ModelManager $modelManager */
        $modelManager = $this->get('models');

        /** @var OrderHydrator $orderHydrator */
        $orderHydrator = $this->get('swag_backend_order.order.order_hydrator');

        /** @var OrderValidator $orderValidator */
        $orderValidator = $this->get('swag_backend_order.order.order_validator');

        $orderStruct = $orderHydrator->hydrateFromRequest($this->Request(), $params['data']['shippingAddressId']);
        $violations = $orderValidator->validate($orderStruct);
        if ($violations->getMessages()) {
            $this->view->assign([
                'success' => false,
                'violations' => $violations->getMessages(),
            ]);

            return;
        }

        $modelManager->getConnection()->beginTransaction();
        try {
            //we need to fake a shop instance if we want to use the Articles Module
            /** @var Repository $shopRepository */
            $shopRepository = $this->get('models')->getRepository(Shop::class);
            $shop = $shopRepository->getActiveById($orderStruct->getLanguageShopId());

            if ($shop === null) {
                throw new RuntimeException('Shop not found');
            }

            $this->get('shopware.components.shop_registration_service')->registerResources($shop);

            /** @var OrderServiceInterface $orderService */
            $orderService = $this->get('swag_backend_order.order.service');
            $order = $orderService->create($orderStruct);

            $modelManager->getConnection()->commit();
            
			if ($orderStruct->getSendMail()) {
                $this->sendOrderConfirmationMail($order, $params, $adminIdentity);
            }
        } catch (InvalidOrderException $e) {
            $modelManager->getConnection()->rollBack();

            $this->view->assign([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

            return;
        } catch (\Exception $e) {
            $modelManager->getConnection()->rollBack();

            $this->view->assign([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (isset($params['data']['orderAttribute'][0]['scha1_orderfield1'])) {
            
            $orderId = $order->getId();
            $params = $params['data']['orderAttribute'][0];

            try {
                $qb = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
                $q = $qb->update('s_order_attributes')
                ->set('scha1_orderfield1', $qb->expr()->literal($params['scha1_orderfield1']))
                ->set('scha1_orderfield2', $qb->expr()->literal($params['scha1_orderfield2']))
                ->set('scha1_orderfield3', $qb->expr()->literal($params['scha1_orderfield3']))
                ->set('scha1_orderfield4', $qb->expr()->literal($params['scha1_orderfield4']))
                ->set('deliverydate_date', $qb->expr()->literal($params['deliverydate_date']))
                ->where('orderID = :id')
                ->setParameter(':id', $orderId)
                ->execute();
            } catch (\Exception $e) {
               //  echo $e->getMessage();
            }
        }

        $this->view->assign([
            'success' => true,
            'orderId' => $order->getId(),
            'ordernumber' => $order->getNumber(),
        ]);
    }

    public function getArticlesAction()
    {
        $limit = $this->request->getParam('limit');
        $offset = $this->request->getParam('start');
        $search = $this->request->getParam('query');
        $userId = $this->request->getParam('userid');
        $shopId = $this->getShopId();
        
        $productSearch = $this->container->get('swag_backend_order.product_search');
        $result = $productSearch->findProducts($search, $shopId, $limit, $offset);
        
        if (is_numeric($userId)) {
        	$user = [];
			$customerGroup = [];
			$customerGroupId = 0;
			
			try {
				$user = Shopware()->Container()->get('dbal_connection')->createQueryBuilder()
	            ->select(['u.customergroup'])
	            ->from('s_user', 'u')
	            ->where('id = :userId')
	            ->setParameter('userId', $userId)
	            ->execute()
	            ->fetchAll(\PDO::FETCH_ASSOC);
			} catch (Exception $ex) {
				echo json_encode(array('success'=>false, 'violations'=>$ex->getMessage()));
				return;
			}
			
			if (count($user)>0) {
				try {
					$customerGroup = Shopware()->Container()->get('dbal_connection')->createQueryBuilder()
		            ->select(['u.id'])
		            ->from('s_core_customergroups', 'u')
		            ->where('groupkey = :groupkey')
		            ->setParameter('groupkey', $user[0]['customergroup'])
		            ->execute()
		            ->fetchAll(\PDO::FETCH_ASSOC);
				} catch (Exception $ex) {
					echo json_encode(array('success'=>false, 'violations'=>$ex->getMessage()));
					return;
				}
			}
			
			if (count($customerGroup)>0) {
				$customerGroupId = $customerGroup[0]['id'];
			}
			
			$resultNew = [];
			foreach ($result as $key => $value) {
				
				$articleID = $value['id'];
				
				if ($customerGroupId>0) {
					
					$articleCheck = [];
					
					try {
						$articleCheck = Shopware()->Container()->get('dbal_connection')->createQueryBuilder()
			            ->select(['u.articleID'])
			            ->from('s_articles_avoid_customergroups', 'u')
			            ->where('articleID = :articleID and customergroupID = :customergroupID')
			            ->setParameter('articleID', $articleID)
			            ->setParameter('customergroupID', $customerGroupId)
			            ->execute()
			            ->fetchAll(\PDO::FETCH_ASSOC);
					} catch (Exception $ex) {
						echo json_encode(array('success'=>false, 'violations'=>$ex->getMessage()));
						return;
					}
					
					// If this article is not blocked
					if (count($articleCheck)==0) {
						$resultNew[] = $value;
					}
				}
			}
			
			$result = $resultNew;
		}

        $this->view->assign(
            [
                'success' => true,
                'data' => $result,
                'total' => $productSearch->getLastResultTotalCount(),
            ]
        );
    }

    public function getProductAction()
    {
        $params = $this->request->getParams();
        $number = $this->request->getParam('ordernumber');

        $productSearch = $this->container->get('swag_backend_order.product_search');
        $product = $productSearch->getProduct($number, $params, $this->getShopId(), $this->getCustomerGroupKey());

        $this->view->assign([
            'data' => $product,
            'success' => true,
        ]);
    }

    public function getDiscountAction()
    {
        $type = (int) $this->Request()->getParam('type');
        $value = (float) $this->Request()->getParam('value');
        $productName = $this->Request()->getParam('name');
        $totalAmount = $this->Request()->getParam('currentTotal');
        $taxRate = $this->Request()->getParam('tax');

        if ($type === DiscountType::DISCOUNT_ABSOLUTE && $totalAmount < $value) {
            $this->view->assign(['success' => false]);

            return;
        }

        $result = [
            'articleName' => $productName,
            'articleNumber' => 'DISCOUNT.' . $type,
            'articleId' => 0,
            'price' => $value * -1,
            'mode' => 4,
            'quantity' => 1,
            'inStock' => 1,
            'isDiscount' => true,
            'discountType' => $type,
            'total' => $value * -1,
            'taxRate' => $taxRate,
        ];

        $this->view->assign([
            'data' => $result,
            'success' => true,
        ]);
    }
	
	public function getCustomerGroupsAction()
    {
        $customerGroups = [];
        
        try {

            $sql = 'SET group_concat_max_len=100000000';
            Shopware()->Container()->get('dbal_connection')->exec($sql);

            $customerGroups = Shopware()->Container()->get('dbal_connection')->createQueryBuilder('s_core_customergroups')
            ->select(['CONCAT(am.description, " [", am.groupkey, "] (", count(o.id), ")") as `name`', 'group_concat(CONCAT("{\"id\":\"", o.id, "\", \"name\":\"", o.firstname, " ", o.lastname, "\"}") separator \',\') as `id`'])
            ->from('s_core_customergroups', 'am')
            ->innerJoin('am', 's_user', 'o', 'am.groupkey = o.customergroup')
            ->innerJoin('o', 's_user_attributes', 'ua', 'ua.userID = o.id')
            ->where('ua.exclude is null or ua.exclude = 0')
            ->groupBy('am.id')
            ->execute()
            ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }

        $total = count($customerGroups);

        $this->view->assign(
            [
                'data' => $customerGroups,
                'total' => $total,
                'success' => true,
            ]
        );
    }


    /**
     * gets all available payments for the backend order
     */
    public function getPaymentAction()
    {
        /** @var PaymentTranslator $paymentTranslator */
        $paymentTranslator = $this->get('swag_backend_order.payment_translator');

        /** @var \Shopware\Components\Model\QueryBuilder $builder */
        $builder = $this->get('models')->createQueryBuilder();
        $builder->select(['payment'])
            ->from(Payment::class, 'payment')
            ->orderBy('payment.active', 'DESC');

        $paymentMethods = $builder->getQuery()->getArrayResult();

        $languageId = $this->getBackendLanguage();

        foreach ($paymentMethods as &$paymentMethod) {
            $paymentMethod = $paymentTranslator->translate($paymentMethod, $languageId);
        }
        unset($paymentMethod);

        $total = count($paymentMethods);

        $this->view->assign(
            [
                'data' => $paymentMethods,
                'total' => $total,
                'success' => true,
            ]
        );
    }

    /**
     * method which selects all shipping costs
     */
    public function getShippingCostsAction()
    {
        /** @var ShippingTranslator $dispatchTranslator */
        $dispatchTranslator = $this->get('swag_backend_order.shipping_translator');

        $builder = $this->get('models')->createQueryBuilder();

        $builder->select(['dispatch', 'shipping'])
            ->from(ShippingCost::class, 'shipping')
            ->innerJoin('shipping.dispatch', 'dispatch')
            ->groupBy('dispatch.id');
        $shippingCosts = $builder->getQuery()->getArrayResult();

        $languageId = $this->getBackendLanguage();

        foreach ($shippingCosts as &$shippingCost) {
            $shippingCost['dispatch'] = $dispatchTranslator->translate($shippingCost['dispatch'], $languageId);
        }
        unset($shippingCost);

        $total = count($shippingCosts);

        $this->view->assign(
            [
                'data' => $shippingCosts,
                'total' => $total,
                'success' => true,
            ]
        );
    }

    public function getCurrenciesAction()
    {
        $repository = $this->get('models')->getRepository(Currency::class);

        $builder = $repository->createQueryBuilder('c');
        $builder->select(
            [
                'c.id as id',
                'c.name as name',
                'c.currency as currency',
                'c.symbol as symbol',
                'c.factor as factor',
                'c.default as default',
            ]
        );

        $query = $builder->getQuery();

        $total = $this->get('models')->getQueryCount($query);

        $data = $query->getArrayResult();

        $this->View()->assign(
            [
                'success' => true,
                'data' => $data,
                'total' => $total,
            ]
        );
    }

    /**
     * reads the plugin config and passes it to the ext js application
     */
    public function getPluginConfigAction()
    {
        /** @var ConfigReader $configReader */
        $configReader = $this->get('shopware.plugin.config_reader');
        $pluginConfig = $configReader->getByPluginName('SwagBackendOrder');

        $desktopTypes = $pluginConfig['desktopTypes'];
        $desktopTypes = explode(',', $desktopTypes);
        $validationMail = $pluginConfig['validationMail'];

        $config = [];
        $config['desktopTypes'] = [];
        $count = 0;

        foreach ($desktopTypes as $desktopType) {
            $config['desktopTypes'][$count]['id'] = $count;
            $config['desktopTypes'][$count]['name'] = $desktopType;
            ++$count;
        }

        $config['validationMail'] = $validationMail;
        $config['sendMail'] = (bool) $pluginConfig['sendMail'];

        $total = count($config);

        $this->view->assign(
            [
                'success' => true,
                'data' => $config,
                'total' => $total,
            ]
        );
    }

    /**
     * assigns the payment data for a user to ExtJs to show the data in the view
     */
    public function getCustomerPaymentDataAction()
    {
        /** @var ModelManager $modelManager */
        $modelManager = $this->get('models');
        $request = $this->Request()->getParams();
        $customerId = $request['customerId'];
        $paymentId = $request['paymentId'];

        $paymentDataRepository = $modelManager->getRepository(PaymentData::class);
        /** @var PaymentData[] $paymentModel */
        $paymentModel = $paymentDataRepository->findBy(['paymentMeanId' => $paymentId, 'customer' => $customerId]);
        $paymentModel = $paymentModel[0];

        $accountHolder = false;
        if ($paymentModel !== null) {
            /** @var Payment $paymentMean */
            $paymentMean = $paymentModel->getPaymentMean();
            if ($paymentModel->getUseBillingData() && $paymentMean->getName() === 'sepa') {
                $accountHolder = $this->getAccountHolder($customerId);
            }
        }

        $payment = $this->get('models')->toArray($paymentModel);
        if ($accountHolder) {
            $payment['accountHolder'] = $accountHolder;
        }

        $this->view->assign([
            'success' => true,
            'data' => $payment,
        ]);
    }

    /**
     * assigns the shop data to ExtJs to show the data in the view
     */
    public function getLanguageSubShopsAction()
    {
        $mainShopId = $this->Request()->getParam('mainShopId');

        $builder = $this->get('models')->createQueryBuilder();
        $builder->select('shops')
            ->from(Shop::class, 'shops')
            ->where('shops.mainId = :mainShopId')
            ->orWhere('shops.id = :mainShopId')
            ->andWhere('shops.active = 1')
            ->setParameter('mainShopId', $mainShopId);

        $result = $builder->getQuery()->getArrayResult();

        //Gets the correct language name for every shop
        foreach ($result as &$shop) {
            /** @var Shop $shopModel */
            $shopModel = $this->get('models')->find(Shop::class, $shop['id']);
            $shop['name'] = $shopModel->getLocale()->getLanguage();
        }
        unset($shop);
        $total = count($result);

        $this->view->assign(
            [
                'data' => $result,
                'success' => true,
                'total' => $total,
            ]
        );
    }

    /**
     * checks if the article which was added or edited is no voucher or esd article
     */
    public function validateEditAction()
    {
        $data = $this->Request()->getParams();
        $articleNumber = (string) $data['articleNumber'];
        $quantity = (int) $data['quantity'];

        $productContext = new ProductContext($articleNumber, $quantity);

        /** @var ProductValidator $validator */
        $validator = $this->get('swag_backend_order.order.product_validator');
        $violations = $validator->validate($productContext);

        if ($violations->getMessages()) {
            $this->view->assign([
                'success' => false,
                'violations' => $violations->getMessages(),
            ]);

            return;
        }

        $this->view->assign('success', true);
    }

    /**
     * calculates the tax for this order
     */
    public function calculateBasketAction()
    {
        /** @var RequestHydrator $requestHydrator */
        $requestHydrator = $this->get('swag_backend_order.price_calculation.request_hydrator');
        $requestStruct = $requestHydrator->hydrateFromRequest($this->Request()->getParams());

        $config = $this->container->get('config');
        $proportionalTaxCalculation = $config->get('proportionalTaxCalculation') && !$requestStruct->isTaxFree();

        //Basket position price calculation
        $positionPrices = [];
        foreach ($requestStruct->getPositions() as $position) {
            $positionPrice = $this->getPositionPrice($position, $requestStruct);
            $totalPositionPrice = new PriceResult();
            $totalPositionPrice->setNet($this->getTotalPrice($positionPrice->getNet(), $position->getQuantity()));
            $totalPositionPrice->setGross($this->getTotalPrice($positionPrice->getGross(), $position->getQuantity()));
            $totalPositionPrice->setTaxRate($position->getTaxRate());

            if ($requestStruct->isDisplayNet()) {
                $calculatedGross = $positionPrice->getNet() * (1 + ($position->getTaxRate() / 100));
                $totalPositionPrice->setGross($this->getTotalPrice($calculatedGross, $position->getQuantity()));
            }

            //Don't set the total amount of the product if it's a discount.
            if (!$position->getIsDiscount()) {
                $positionPrices[] = $totalPositionPrice;

                $position->setPrice($positionPrice->getGross());

                //Use net prices if it's configured like that
                if ($requestStruct->isTaxFree() || $requestStruct->isDisplayNet()) {
                    $position->setPrice($positionPrice->getNet());
                }

                $position->setTotal($this->getTotalPrice($position->getPrice(), $position->getQuantity()));
            }
        }

        $dispatchPrice = $this->getShippingPrice($requestStruct);

        /** @var TotalPriceCalculator $totalPriceCalculator */
        $totalPriceCalculator = $this->get('swag_backend_order.price_calculation.total_price_calculator');
        $totalPriceResult = $totalPriceCalculator->calculate($positionPrices, $dispatchPrice, $proportionalTaxCalculation);
        $result = $this->createBasketCalculationResult($totalPriceResult, $requestStruct, $proportionalTaxCalculation);
        $result['isTaxFree'] = $requestStruct->isTaxFree();

        /** @var DiscountCalculator $discountCalculator */
        $discountCalculator = $this->get('swag_backend_order.price_calculation.discount_calculator');
        $result = $discountCalculator->calculateDiscount($result);

        $this->view->assign([
            'data' => $result,
            'success' => true,
        ]);
    }

    /**
     * @param Order $orderModel
     */
    private function sendOrderConfirmationMail($orderModel, $params=null, $adminIdentity=[])
    {
        $confirmationMailCreator = new ConfirmationMailCreator(
            new TaxCalculation(),
            $this->get('swag_backend_order.payment_translator'),
            $this->get('swag_backend_order.shipping_translator'),
            new ConfirmationMailRepository($this->get('dbal_connection')),
            $this->get('models')->getRepository(Detail::class),
            $this->get('config'),
            new NumberFormatterWrapper(),
            $this->get('modules')->Articles()
        );

        try {
            $context = $confirmationMailCreator->prepareOrderConfirmationMailData($orderModel);
            $customerModel = $orderModel->getCustomer();
            $customerId = $customerModel->getId();
            $context['delivery_date'] = '';

            $orderFromBackend = isset($params['data']['orderFromBackend']) ? $params['data']['orderFromBackend'] : '1';
            $context['orderFromBackend'] = $orderFromBackend;
            $context['backendAdmin'] = $adminIdentity->name;

            $customerData = Shopware()->Container()->get('dbal_connection')->createQueryBuilder()
            ->select(['u.firstname', 'u.lastname'])
            ->from('s_user', 'u')
            ->where('id = :customer_id')
            ->setParameter('customer_id', $customerId)
            ->execute()
            ->fetchAll(\PDO::FETCH_ASSOC);
            
            if (isset($params['data']['orderAttribute'][0]['deliverydate_date'])) {
                $context['delivery_date'] = $params['data']['orderAttribute'][0]['deliverydate_date'];
                $context['scha1_orderfield1'] = $params['data']['orderAttribute'][0]['scha1_orderfield1'];
                $context['scha1_orderfield2'] = $params['data']['orderAttribute'][0]['scha1_orderfield2'];
                $context['scha1_orderfield3'] = $params['data']['orderAttribute'][0]['scha1_orderfield3'];
                $context['scha1_orderfield4'] = $params['data']['orderAttribute'][0]['scha1_orderfield4'];
            }

            if (count($customerData)>0) {
                $context['customer_name'] = $customerData[0]['firstname'].' '.$customerData[0]['lastname'];
            }

            $context['sOrderDetails'] = $confirmationMailCreator->prepareOrderDetailsConfirmationMailData(
                $orderModel,
                $orderModel->getLanguageSubShop()->getLocale()
            );

            $mail = Shopware()->TemplateMail()->createMail('sORDER', $context);
            $mail->addTo($context['additional']['user']['email']);
            $mail->send();

            //If configured send an email to the shop owner
            $mailNotToShopOwner = Shopware()->Config()->get('no_order_mail');
            if (!$mailNotToShopOwner) {
                $mail->clearRecipients();
                $mail->addTo(Shopware()->Config()->get('mail'));
                $mail->send();
            }

            $data = '';
        
            try {
                $data = Shopware()->Container()->get('dbal_connection')->createQueryBuilder('webnativ_einstellungen')->select(['c.email'])
                ->from('webnativ_einstellungen', 'c')
                ->execute()
                ->fetchAll(\PDO::FETCH_ASSOC);

                if (isset($data[0])) {
                    $data = $data[0]['email'];
                }
            } catch (Exception $ex) {
            }

            if ($data != '') {
                
                $mail->clearRecipients();
                $data = explode(',', $data);

                foreach ($data as $email_string) {
                    $name = 'admin';
                    $email = $email_string;
                    if (strpos($email_string, '<') !== false) {
                        $split = explode('<', $email_string);
                        $name = $split[0];
                        $email = rtrim($split[1], '>');
                    }
                    
                    if ($context['additional']['user']['email'] != $email) {
                        $mail->AddAddress($email, $name);
                    }
                }
                $mail->Send();
            }

        } catch (\Exception $e) {
            $this->view->assign('mail', $e->getMessage());
        }
    }

    /**
     * @return int
     */
    private function getBackendLanguage()
    {
        /** @var Shopware_Components_Auth $auth */
        $auth = Shopware()->Plugins()->Backend()->Auth()->checkAuth();
        $identity = $auth->getIdentity();

        return $identity->locale->getId();
    }

    /**
     * @return string
     */
    private function getListRequestParam()
    {
        $data = $this->Request()->getParams();

        return $data['filter'][0]['value'];
    }

    /**
     * @param float $price
     * @param int   $quantity
     *
     * @return float
     */
    private function getTotalPrice($price, $quantity)
    {
        return $price * $quantity;
    }

    /**
     * @param int $customerId
     *
     * @return string
     */
    private function getAccountHolder($customerId)
    {
        /** @var ModelManager $modelManager */
        $modelManager = $this->get('models');
        $customer = $modelManager->find(Customer::class, $customerId);

        return $customer->getBilling()->getFirstName() . ' ' . $customer->getBilling()->getLastName();
    }

    /**
     * @return array
     */
    private function createBasketCalculationResult(
        TotalPricesResult $totalPriceResult,
        RequestStruct $requestStruct,
        $proportionalTaxCalculation
    ) {
        $shippingCosts = $totalPriceResult->getShipping()->getRoundedGrossPrice();
        $productSum = $totalPriceResult->getSum()->getRoundedGrossPrice();
        $total = $totalPriceResult->getTotal()->getRoundedGrossPrice();
        $taxSum = $totalPriceResult->getTaxAmount();

        if ($requestStruct->isDisplayNet()) {
            $productSum = $totalPriceResult->getSum()->getRoundedNetPrice();
        }

        if ($requestStruct->isTaxFree()) {
            $productSum = $totalPriceResult->getSum()->getRoundedNetPrice();
            $total = $totalPriceResult->getTotal()->getRoundedNetPrice();
            $taxSum = 0.00;
        }

        //Total prices calculation
        $totalNetPrice = $totalPriceResult->getTotal()->getRoundedNetPrice();
        $shippingCostsNet = $totalPriceResult->getShipping()->getRoundedNetPrice();

        if ($proportionalTaxCalculation) {
            $proportionalTaxCalculator = $this->container->get('shopware.cart.proportional_tax_calculator');
            $tax = $proportionalTaxCalculator->calculate($shippingCosts, $requestStruct->getPositions(), true);
            /** @var \Shopware\Components\Cart\Struct\Price $price */
            foreach ($tax as $price) {
                $totalPriceResult->addTax($price->getTaxRate(), $price->getTax());
            }
        }

        return [
            'totalWithoutTax' => $totalNetPrice,
            'sum' => $productSum,
            'total' => $total,
            'shippingCosts' => $shippingCosts,
            'shippingCostsNet' => $shippingCostsNet,
            'shippingCostsTaxRate' => $totalPriceResult->getShipping()->getTaxRate(),
            'taxSum' => $taxSum,
            'positions' => $requestStruct->getPositionsArray(),
            'dispatchTaxRate' => $totalPriceResult->getShipping()->getTaxRate(),
            'proportionalTaxCalculation' => (int) $proportionalTaxCalculation && !$requestStruct->isTaxFree(),
            'taxes' => $this->convertTaxes($totalPriceResult->getTaxes()),
        ];
    }

    /**
     * @return array
     */
    private function convertTaxes(array $taxes)
    {
        $result = [];
        foreach ($taxes as $taxRate => $tax) {
            $result[] = [
                'taxRate' => $taxRate,
                'tax' => round($tax, 2),
            ];
        }

        return $result;
    }

    /**
     * @param PositionStruct $position
     * @param RequestStruct  $requestStruct
     *
     * @return PriceResult
     */
    private function getPositionPrice($position, $requestStruct)
    {
        /** @var PriceContextFactory $priceContextFactory */
        $priceContextFactory = $this->get('swag_backend_order.price_calculation.price_context_factory');
        /** @var ProductPriceCalculator $productCalculator */
        $productCalculator = $this->get('swag_backend_order.price_calculation.product_calculator');

        $previousPriceContext = $priceContextFactory->create(
            $position->getPrice(),
            $position->getTaxRate(),
            $requestStruct->isPreviousDisplayNet(),
            $requestStruct->isPreviousTaxFree(),
            $requestStruct->getPreviousCurrencyId()
        );
        $basePrice = $productCalculator->calculateBasePrice($previousPriceContext);

        $currentPriceContext = $priceContextFactory->create(
            $basePrice,
            $position->getTaxRate(),
            true,
            $requestStruct->isTaxFree(),
            $requestStruct->getCurrencyId()
        );

        return $productCalculator->calculate($currentPriceContext);
    }

    /**
     * @param int     $dispatchId
     * @param float[] $basketTaxRates
     *
     * @throws \RuntimeException
     *
     * @return float
     */
    private function getDispatchTaxRate($dispatchId, array $basketTaxRates = [])
    {
        if ($dispatchId === null) {
            return 0.00;
        }

        /** @var Dispatch $dispatch */
        $dispatch = $this->getModelManager()->find(Dispatch::class, $dispatchId);

        if ($dispatch === null) {
            throw new \RuntimeException('Can not find given dispatch with id ' . $dispatchId);
        }

        $taxId = $dispatch->getTaxCalculation();
        $tax = $this->getModelManager()->find(Tax::class, $taxId);

        if ($tax !== null) {
            return $tax->getTax();
        }

        if (empty($basketTaxRates)) {
            return 0.00;
        }

        return $this->getHighestDispatchTaxRate($basketTaxRates);
    }

    /**
     * @param float[] $basketTaxRates
     *
     * @return float
     */
    private function getHighestDispatchTaxRate(array $basketTaxRates)
    {
        return max($basketTaxRates);
    }

    /**
     * @param RequestStruct $requestStruct
     *
     * @return PriceResult
     */
    private function getShippingPrice($requestStruct)
    {
        $dispatchTaxRate = $this->getDispatchTaxRate($requestStruct->getDispatchId(), $requestStruct->getBasketTaxRates());
        /** @var PriceContextFactory $priceContextFactory */
        $priceContextFactory = $this->get('swag_backend_order.price_calculation.price_context_factory');
        /** @var ShippingPriceCalculator $shippingCalculator */
        $shippingCalculator = $this->get('swag_backend_order.price_calculation.shipping_calculator');

        // Get base/gross shipping costs (even if tax free)
        $previousPriceContext = $priceContextFactory->create(
            $requestStruct->getShippingCosts(),
            $dispatchTaxRate,
            $requestStruct->isPreviousDisplayNet(),
            $requestStruct->isPreviousTaxFree(),
            $requestStruct->getPreviousCurrencyId()
        );
        $baseShippingPrice = $shippingCalculator->calculateBasePrice($previousPriceContext);

        // Calculate actual gross & net shipping costs for order
        $currentPriceContext = $priceContextFactory->create(
            $baseShippingPrice,
            $dispatchTaxRate,
            $requestStruct->isDisplayNet(),
            $requestStruct->isTaxFree(),
            $requestStruct->getCurrencyId()
        );

        return $shippingCalculator->calculate($currentPriceContext);
    }

    /**
     * @return int
     */
    private function getShopId()
    {
        $shopId = (int) $this->Request()->getParam('shopId');

        if ($shopId === 0) {
            /** @var Repository $shopRepo */
            $shopRepo = $this->get('models')->getRepository(Shop::class);
            $shopId = $shopRepo->getActiveDefault()->getId();
        }

        return $shopId;
    }

    /**
     * @return string
     */
    private function getCustomerGroupKey()
    {
        $customerId = (int) $this->Request()->getParam('customerId');

        // default customer group key of shopware
        $customerGroupKey = 'EK';

        if ($customerId !== 0) {
            /** @var CustomerRepository $customerRepo */
            $customerRepo = $this->get('swag_backend_order.customer_repository');
            $customer = $customerRepo->get($customerId);
            $customerGroupKey = $customer['groupKey'];
        }

        return $customerGroupKey;
    }
    public function getWhitelistedCSRFActions() {
        return ['loadCart', 'createOrder'];
    }
}
