<?php

namespace Tests\BpostApiExamples\FetchOrder;

use Bpost\BpostApiClient\Bpost\Order;
use Bpost\BpostApiClient\Bpost\Order\Box;
use Bpost\BpostApiClient\Bpost\ProductConfiguration\Product;
use PHPUnit_Framework_TestCase;

class AtIntlPugoTest extends PHPUnit_Framework_TestCase
{

    private function getXmlFromBpost()
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<orderInfo
    xmlns="http://schema.post.be/shm/deepintegration/v3/"
    xmlns:ns2="http://schema.post.be/shm/deepintegration/v3/common"
    xmlns:ns3="http://schema.post.be/shm/deepintegration/v3/national"
    xmlns:ns4="http://schema.post.be/shm/deepintegration/v3/international"
    >
    <accountId>123456</accountId>
    <reference>my-reference</reference>
    <box>
        <sender>
            <ns2:name>Alfred</ns2:name>
            <ns2:company>Antidot</ns2:company>
            <ns2:address>
                <ns2:streetName>Rue du Grand Duc</ns2:streetName>
                <ns2:number>13</ns2:number>
                <ns2:postalCode>1040</ns2:postalCode>
                <ns2:locality>Etterbeek</ns2:locality>
                <ns2:countryCode>BE</ns2:countryCode>
            </ns2:address>
        </sender>
        <internationalBox>
            <ns4:atIntlPugo>
                <ns4:product>bpack@bpost international</ns4:product>
                <ns4:options>
                    <ns2:keepMeInformed language="FR">
                        <ns2:emailAddress>esolutions@bpost.be</ns2:emailAddress>
                    </ns2:keepMeInformed>
                </ns4:options>
                <ns4:receiver>
                    <ns2:name>test tester</ns2:name>
                    <ns2:address>
                        <ns2:streetName>RUE SAULNIER</ns2:streetName>
                        <ns2:number>22 </ns2:number>
                        <ns2:postalCode>75009</ns2:postalCode>
                        <ns2:locality>PARIS</ns2:locality>
                        <ns2:countryCode>FR</ns2:countryCode>
                    </ns2:address>
                    <ns2:emailAddress>esolutions@bpost.be</ns2:emailAddress>
                    <ns2:phoneNumber>1111111111</ns2:phoneNumber>
                </ns4:receiver>
                <ns4:parcelWeight>11000</ns4:parcelWeight>
            </ns4:atIntlPugo>
        </internationalBox>
        <additionalCustomerReference>additional-reference</additionalCustomerReference>
        <barcode>320000000000000000000000</barcode>
        <status>ANNOUNCED</status>
    </box>
</orderInfo>
XML;
    }

    public function testCreateFromXml()
    {
        $xml = simplexml_load_string($this->getXmlFromBpost());
        $order = Order::createFromXML($xml);

        $this->assertSame('my-reference', $order->getReference());
        $this->assertNull($order->getCostCenter());
        $this->assertNull($order->getLines());
        $this->assertNotNull($order->getBoxes());
        $this->assertCount(1, $order->getBoxes());

        /** @var Box $box */
        $box = current($order->getBoxes());
        $sender = $box->getSender();

        $this->assertSame('Alfred', $sender->getName());
        $this->assertSame('Antidot', $sender->getCompany());
        $this->assertNull($sender->getEmailAddress());
        $this->assertNull($sender->getPhoneNumber());
        $this->assertSame('Rue du Grand Duc', $sender->getAddress()->getStreetName());
        $this->assertSame('13', $sender->getAddress()->getNumber());
        $this->assertSame('1040', $sender->getAddress()->getPostalCode());
        $this->assertSame('Etterbeek', $sender->getAddress()->getLocality());
        $this->assertSame('BE', $sender->getAddress()->getCountryCode());

        $this->assertSame('additional-reference', $box->getAdditionalCustomerReference());
        $this->assertSame(Box::BOX_STATUS_ANNOUNCED, $box->getStatus());
        $this->assertNull($box->getRemark());
        $this->assertNotNull($box->getInternationalBox());
        $this->assertNull($box->getNationalBox());

        /** @var Box\AtIntlPugo $internationalBox */
        $internationalBox = $box->getInternationalBox();

        $this->assertInstanceOf('Bpost\BpostApiClient\Bpost\Order\Box\International', $internationalBox);
        $this->assertSame(Product::PRODUCT_NAME_BPACK_AT_BPOST_INTERNATIONAL, $internationalBox->getProduct());
        $this->assertSame(11000, $internationalBox->getParcelWeight());
        $this->assertCount(0, $internationalBox->getParcelContents());
        $this->assertNotNull($internationalBox->getReceiver());
        $this->assertCount(1, $internationalBox->getOptions());

        $receiver = $internationalBox->getReceiver();

        $this->assertSame('test tester', $receiver->getName());
        $this->assertNull($receiver->getCompany());
        $this->assertSame('esolutions@bpost.be', $receiver->getEmailAddress());
        $this->assertSame('1111111111', $receiver->getPhoneNumber());
        $this->assertSame('RUE SAULNIER', $receiver->getAddress()->getStreetName());
        $this->assertSame('22 ', $receiver->getAddress()->getNumber());
        $this->assertSame('75009', $receiver->getAddress()->getPostalCode());
        $this->assertSame('PARIS', $receiver->getAddress()->getLocality());
        $this->assertSame('FR', $receiver->getAddress()->getCountryCode());

        $this->assertCount(1, $internationalBox->getOptions());

        $optionMessaging = current($internationalBox->getOptions());
        $this->assertInstanceOf('Bpost\BpostApiClient\Bpost\Order\Box\Option\Messaging', $optionMessaging);
        $this->assertSame(Box\Option\Messaging::MESSAGING_TYPE_KEEP_ME_INFORMED, $optionMessaging->getType());
        $this->assertSame(Box\Option\Messaging::MESSAGING_LANGUAGE_FR, $optionMessaging->getLanguage());
        $this->assertNull($optionMessaging->getMobilePhone());
        $this->assertSame('esolutions@bpost.be', $optionMessaging->getEmailAddress());
    }
}
