<?php

namespace Bpost\BpostApiClient\Bpost\Order\Box\Option;

use Bpost\BpostApiClient\Bpost;
use Bpost\BpostApiClient\Common\XmlHelper;
use Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException;
use Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidValueException;
use DomDocument;
use DomElement;
use SimpleXMLElement;

/**
 * bPost Messaging class
 *
 * @author    Tijs Verkoyen <php-bpost@verkoyen.eu>
 *
 * @version   3.0.0
 *
 * @copyright Copyright (c), Tijs Verkoyen. All rights reserved.
 * @license   BSD License
 */
class Messaging extends Option
{
    const MESSAGING_LANGUAGE_EN = 'EN';
    const MESSAGING_LANGUAGE_NL = 'NL';
    const MESSAGING_LANGUAGE_FR = 'FR';
    const MESSAGING_LANGUAGE_DE = 'DE';

    const MESSAGING_TYPE_INFO_DISTRIBUTED = 'infoDistributed';
    const MESSAGING_TYPE_INFO_NEXT_DAY = 'infoNextDay';
    const MESSAGING_TYPE_INFO_REMINDER = 'infoReminder';
    const MESSAGING_TYPE_KEEP_ME_INFORMED = 'keepMeInformed';

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $emailAddress;

    /**
     * @var string
     */
    private $mobilePhone;

    /**
     * @param string $emailAddress
     *
     * @throws BpostInvalidLengthException
     */
    public function setEmailAddress($emailAddress)
    {
        $length = 50;
        if (mb_strlen($emailAddress) > $length) {
            throw new BpostInvalidLengthException('emailAddress', mb_strlen($emailAddress), $length);
        }

        $this->emailAddress = $emailAddress;
    }

    /**
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    /**
     * @param string $language
     *
     * @throws BpostInvalidValueException
     */
    public function setLanguage($language)
    {
        if (empty($language)) {
            $language = self::MESSAGING_LANGUAGE_EN;
        }
        else{
            $language = strtoupper($language);
        }

        if (!in_array($language, self::getPossibleLanguageValues())) {
            throw new BpostInvalidValueException('language', $language, self::getPossibleLanguageValues());
        }

        $this->language = $language;
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @return array
     */
    public static function getPossibleLanguageValues()
    {
        return array(
            self::MESSAGING_LANGUAGE_EN,
            self::MESSAGING_LANGUAGE_NL,
            self::MESSAGING_LANGUAGE_FR,
            self::MESSAGING_LANGUAGE_DE,
        );
    }

    /**
     * @param string $mobilePhone
     *
     * @throws BpostInvalidLengthException
     */
    public function setMobilePhone($mobilePhone)
    {
        $length = 20;
        if (mb_strlen($mobilePhone) > $length) {
            throw new BpostInvalidLengthException('mobilePhone', mb_strlen($mobilePhone), $length);
        }

        $this->mobilePhone = $mobilePhone;
    }

    /**
     * @return string
     */
    public function getMobilePhone()
    {
        return $this->mobilePhone;
    }

    /**
     * @return array
     */
    public static function getPossibleTypeValues()
    {
        return array(
            self::MESSAGING_TYPE_INFO_DISTRIBUTED,
            self::MESSAGING_TYPE_INFO_NEXT_DAY,
            self::MESSAGING_TYPE_INFO_REMINDER,
            self::MESSAGING_TYPE_KEEP_ME_INFORMED,
        );
    }

    /**
     * @param string $type
     *
     * @throws BpostInvalidValueException
     */
    public function setType($type)
    {
        if (!in_array($type, self::getPossibleTypeValues())) {
            throw new BpostInvalidValueException('type', $type, self::getPossibleTypeValues());
        }

        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string      $type
     * @param string      $language
     * @param string|null $emailAddress
     * @param string|null $mobilePhone
     *
     * @throws BpostInvalidLengthException
     * @throws BpostInvalidValueException
     */
    public function __construct($type, $language, $emailAddress = null, $mobilePhone = null)
    {
        $this->setType($type);
        $this->setLanguage($language);

        if ($emailAddress !== null) {
            $this->setEmailAddress($emailAddress);
        }
        if ($mobilePhone !== null) {
            $this->setMobilePhone($mobilePhone);
        }
    }

    /**
     * Return the object as an array for usage in the XML
     *
     * @param DomDocument $document
     * @param string      $prefix
     *
     * @return DomElement
     */
    public function toXML(DOMDocument $document, $prefix = 'common')
    {
        $messaging = $document->createElement(XmlHelper::getPrefixedTagName($this->getType(), $prefix));
        $messaging->setAttribute('language', $this->getLanguage());

        if ($this->getEmailAddress() !== null) {
            $messaging->appendChild(
                $document->createElement(
                    XmlHelper::getPrefixedTagName('emailAddress', $prefix),
                    $this->getEmailAddress()
                )
            );
        }
        if ($this->getMobilePhone() !== null) {
            $messaging->appendChild(
                $document->createElement(
                    XmlHelper::getPrefixedTagName('mobilePhone', $prefix),
                    $this->getMobilePhone()
                )
            );
        }

        return $messaging;
    }

    /**
     * @param SimpleXMLElement $xml
     *
     * @return Messaging
     *
     * @throws BpostInvalidLengthException
     * @throws BpostInvalidValueException
     */
    public static function createFromXML(SimpleXMLElement $xml)
    {
        $messaging = new Messaging($xml->getName(), (string) $xml->attributes()->language);

        $children = $xml->children(Bpost::NS_V3_COMMON);
        if ((string) $children->emailAddress !== '') {
            $messaging->setEmailAddress((string) $children->emailAddress);
        }
        if ((string) $children->mobilePhone !== '') {
            $messaging->setMobilePhone((string) $children->mobilePhone);
        }

        return $messaging;
    }
}
