<?php

namespace Bpost\BpostApiClient\Bpost\Order;

use SimpleXMLElement;

/**
 * bPost Sender class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class Sender extends Customer
{
    const TAG_NAME = 'sender';

    /**
     * @param SimpleXMLElement $xml
     *
     * @return Sender
     */
    public static function createFromXML(SimpleXMLElement $xml)
    {
        /** @var Sender $sender */
        $sender = parent::createFromXMLHelper($xml, new Sender());

        return $sender;
    }
}
