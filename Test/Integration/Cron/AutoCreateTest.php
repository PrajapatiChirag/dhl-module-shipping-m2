<?php
/**
 * Dhl Shipping
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * @category  Dhl
 * @package   Dhl\Shipping\Test\Integration\Model\Cron
 * @author    Paul Siedler <paul.siedler@netresearch.de>
 * @copyright 2017 Netresearch GmbH & Co. KG
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.netresearch.de/
 */

namespace Dhl\Shipping\Test\Integration\Cron;

use Dhl\Shipping\Api\Config\ModuleConfigInterface;
use Dhl\Shipping\Cron\AutoCreate;
use Dhl\Shipping\Model\Config\ModuleConfig;
use Dhl\Shipping\Test\Fixture\OrderCollectionFixture;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoresConfig;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

class AutoCreateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var AutoCreate
     */
    private $autoCreate;

    /**
     * @var ModuleConfigInterface | \PHPUnit_Framework_MockObject_MockObject
     */
    private $moduleConfig;

    /**
     * @var StoresConfig | \PHPUnit_Framework_MockObject_MockObject
     */
    private $storesConfig;

    public static function createOrdersFixtures()
    {
        OrderCollectionFixture::createOrdersFixture();
    }

    public static function createOrdersRollback()
    {
        OrderCollectionFixture::createOrdersRollback();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();

        $this->moduleConfig = $this->getMockBuilder(ModuleConfig::class)
                                   ->disableOriginalConstructor()
                                   ->setMethods(
                                       [
                                           'getCronOrderStatuses',
                                           'canProcessRoute',
                                           'getDefaultProduct'
                                       ]
                                   )
                                   ->getMock();

        $this->storesConfig = $this->getMockBuilder(StoresConfig::class)
                                   ->disableOriginalConstructor()
                                   ->setMethods(['getStoresConfigByPath'])
                                   ->getMock();

        $this->autoCreate = $this->objectManager->create(
            AutoCreate::class,
            [
                'config' => $this->moduleConfig,
                'storesConfig' => $this->storesConfig
            ]
        );
    }

    /**
     * @test
     * @magentoDataFixture createOrdersFixtures
     */
    public function testRun()
    {
        $this->moduleConfig->expects($this->once())
                           ->method('getCronOrderStatuses')
                           ->will(
                               $this->returnValue(
                                   [
                                       Order::STATE_NEW,
                                       Order::STATE_PROCESSING
                                   ]
                               )
                           );

        $this->moduleConfig->expects($this->exactly(5))
                           ->method('canProcessRoute')
                           ->will($this->returnValue(true));

        $this->moduleConfig->expects($this->exactly(5))
                           ->method('getDefaultProduct')
                           ->will($this->returnValue('foo'));

        $this->storesConfig->expects($this->once())
                           ->method('getStoresConfigByPath')
                           ->with(ModuleConfigInterface::CONFIG_XML_PATH_CRON_ENABLED)
                           ->will(
                               $this->returnValue(
                                   [
                                       0 => 1,
                                       1 => 1
                                   ]
                               )
                           );


        $result = $this->autoCreate->run();

        $this->assertEquals(
            count(OrderCollectionFixture::getAutoCreateOrderIncrementIds()),
            $result['count']
        );
        $this->assertEquals(
            OrderCollectionFixture::getAutoCreateOrderIncrementIds(),
            $result['orderIds']
        );
        $this->assertEquals(
            count(OrderCollectionFixture::getAutoCreateOrderIncrementIds()),
            count($result['shipments'])
        );
    }


}