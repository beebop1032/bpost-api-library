<?php

namespace Bpost\BpostApiClient\tests;

use Bpost\BpostApiClient\Bpost;
use Bpost\BpostApiClient\Bpost\Order;
use Bpost\BpostApiClient\Bpost\Order\Address;
use Bpost\BpostApiClient\Bpost\Order\Box;
use Bpost\BpostApiClient\Bpost\Order\Box\Option\Messaging;
use Bpost\BpostApiClient\Bpost\Order\Line as OrderLine;
use Bpost\BpostApiClient\Bpost\Order\Receiver;
use Bpost\BpostApiClient\Bpost\Order\Sender;
use Bpost\BpostApiClient\Common\ValidatedValue\LabelFormat;
use PHPUnit_Framework_TestCase;

/**
 * test case.
 */
class BpostTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Bpost
     */
    private $bpost;
    /**
     * @var string
     */
    private $notificationEmail = NOTIFICATION_EMAIL;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->bpost = new Bpost(ACCOUNT_ID, PASSPHRASE);
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->bpost = null;
        parent::tearDown();
    }

    /**
     * Tests Bpost->getTimeOut()
     */
    public function testGetTimeOut()
    {
        $this->bpost->setTimeOut(5);
        $this->assertSame(5, $this->bpost->getTimeOut());
    }

    /**
     * Tests Bpost->getUserAgent()
     */
    public function testGetUserAgent()
    {
        $this->bpost->setUserAgent('testing/1.0.0');
        $this->assertSame('PHP Bpost/' . Bpost::VERSION . ' testing/1.0.0', $this->bpost->getUserAgent());
    }

    /**
     * Create an order with 2 lines and 1 box
     *
     * @return Order
     */
    protected function createAtHomeOrderObject($refSuffix)
    {
        // create order
        $order = new Order('phpunit-' . date('ymdHis-' . $refSuffix));
        $order->setCostCenter('Cost Center');

        // add lines
        $line1 = new OrderLine('Beer', 1);
        $order->addLine($line1);
        $line2 = new OrderLine('Whisky', 100);
        $order->addLine($line2);

        // add box
        $address = new Address();
        $address->setStreetName('Afrikalaan');
        $address->setNumber('289');
        $address->setPostalCode('9000');
        $address->setLocality('Gent');
        $address->setCountryCode('BE');

        $sender = new Sender();
        $sender->setAddress($address);
        $sender->setName('Tijs Verkoyen');
        $sender->setCompany('Sumo Coders');
        $sender->setPhoneNumber('+32 9 395 02 51');
        $sender->setEmailAddress($this->notificationEmail);

        $box = new Box();
        $box->setSender($sender);
        $box->setRemark('Remark');

        // add label
        $address = new Address();
        $address->setStreetName('Kerkstraat');
        $address->setNumber('108');
        $address->setPostalCode('9050');
        $address->setLocality('Gentbrugge');
        $address->setCountryCode('BE');

        $receiver = new Receiver();
        $receiver->setAddress($address);
        $receiver->setName('Tijs Verkoyen');
        $receiver->setCompany('Sumo Coders');
        $receiver->setPhoneNumber('+32 9 395 02 51');
        $receiver->setEmailAddress($this->notificationEmail);

        // @Home
        $atHome = new Box\AtHome();
        $atHome->setProduct(Bpost\ProductConfiguration\Product::PRODUCT_NAME_BPACK_24H_PRO);
        $atHome->setWeight(2000);
        $atHome->setReceiver($receiver);
        $atHome->addOption(new Messaging(Messaging::MESSAGING_TYPE_INFO_DISTRIBUTED, 'NL', $this->notificationEmail));
        $atHome->addOption(new Box\Option\CashOnDelivery(500, 'BE97068910521849', 'GKCCBEBB'));
        $atHome->addOption(new Messaging(Messaging::MESSAGING_TYPE_INFO_REMINDER, 'NL', $this->notificationEmail));
        $atHome->addOption(new Box\Option\Insured(Box\Option\Insured::INSURANCE_TYPE_BASIC_INSURANCE));
        $atHome->addOption(new Box\Option\AutomaticSecondPresentation());
        $atHome->addOption(new Box\Option\Signed());
        $box->setNationalBox($atHome);

        $order->addBox($box);

        return $order;
    }

    /**
     * Tests Bpost->createOrReplaceOrder
     */
    public function testCreateOrReplaceOrder()
    {
        $order = $this->createAtHomeOrderObject(__LINE__);
        $response = $this->bpost->createOrReplaceOrder($order);
        $this->assertTrue($response);

        $this->bpost->modifyOrderStatus($order->getReference(), Box::BOX_STATUS_CANCELLED);
    }

    /**
     * Tests Bpost->modifyOrderStatus
     */
    public function testModifyOrderStatus()
    {
        $order = $this->createAtHomeOrderObject(__LINE__);
        $this->bpost->createOrReplaceOrder($order);
        $response = $this->bpost->modifyOrderStatus($order->getReference(), Box::BOX_STATUS_OPEN);
        $this->assertTrue($response);

        $this->bpost->modifyOrderStatus($order->getReference(), Box::BOX_STATUS_CANCELLED);
    }

    /**
     * Tests Bpost->fetchOrder
     */
    public function testFetchOrder()
    {
        $order = $this->createAtHomeOrderObject(__LINE__);
        $this->bpost->createOrReplaceOrder($order);
        $response = $this->bpost->fetchOrder($order->getReference());
        $this->assertInstanceOf('\\Bpost\BpostApiClient\\Bpost\\Order', $response);
        $this->assertEquals($order->getReference(), $response->getReference());

        $this->bpost->modifyOrderStatus($order->getReference(), Box::BOX_STATUS_CANCELLED);
    }

    /**
     * Tests Bpost->createLabelForOrder
     */
    public function testCreateLabelForOrder()
    {
        $order = $this->createAtHomeOrderObject(__LINE__);
        $this->bpost->createOrReplaceOrder($order);
        $response = $this->bpost->createLabelForOrder($order->getReference());
        $this->assertInternalType('array', $response);
        foreach ($response as $label) {
            $this->assertInstanceOf('\\Bpost\\BpostApiClient\BPost\Label', $label);
        }

        $this->bpost->modifyOrderStatus($order->getReference(), Box::BOX_STATUS_CANCELLED);
    }

    /**
     * Tests Bpost->createLabelForBox
     *
     * @group qsd
     */
    public function testCreateLabelForBox()
    {
        $order = $this->createAtHomeOrderObject(__LINE__);
        $this->bpost->createOrReplaceOrder($order);
        $response = $this->bpost->createLabelForOrder($order->getReference());
        $response = $this->bpost->createLabelForBox($response[0]->getBarcode());
        $this->assertInternalType('array', $response);
        foreach ($response as $label) {
            $this->assertInstanceOf('\\Bpost\\BpostApiClient\BPost\Label', $label);
        }

        $this->bpost->modifyOrderStatus($order->getReference(), Box::BOX_STATUS_CANCELLED);
    }

    /**
     * Tests Bpost->createLabelInBulkForOrders
     *
     * @group aze
     */
    public function testCreateLabelInBulkForOrders()
    {
        $order1 = $this->createAtHomeOrderObject(__LINE__ . '_1');
        $this->bpost->createOrReplaceOrder($order1);

        $order2 = $this->createAtHomeOrderObject(__LINE__ . '_2');
        $this->bpost->createOrReplaceOrder($order2);

        $this->bpost->setTimeOut(60);
        $response = $this->bpost->createLabelInBulkForOrders(
            array(
                $order1->getReference(),
                $order2->getReference(),
            )
        );

        $this->assertInternalType('array', $response);
        foreach ($response as $label) {
            $this->assertInstanceOf('\\Bpost\\BpostApiClient\BPost\Label', $label);
        }

        $this->bpost->modifyOrderStatus($order1->getReference(), Box::BOX_STATUS_CANCELLED);
        $this->bpost->modifyOrderStatus($order2->getReference(), Box::BOX_STATUS_CANCELLED);
    }
}
