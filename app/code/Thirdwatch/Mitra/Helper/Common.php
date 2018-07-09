<?php

namespace Thirdwatch\Mitra\Helper;
require_once(BP . '/lib' . DIRECTORY_SEPARATOR . 'thirdwatch-php' . DIRECTORY_SEPARATOR . 'autoload.php');
use Magento\Framework\App\Helper\AbstractHelper;


class Common extends AbstractHelper {

    public function __construct(
    \Magento\Directory\Model\CountryFactory $countryFactory
    ) {
        $this->_countryFactory = $countryFactory;
    }

    public function getShippingAddress($model) {
        $address = $this->getAddress($model);
        $shipping_json = new \ai\thirdwatch\Model\ShippingAddress($address);
        return $shipping_json;
    }

    public function getBillingAddress($model) {
        $address = $this->getAddress($model);
        $billing_json = new \ai\thirdwatch\Model\BillingAddress($address);
        return $billing_json;
    }

//    private function getCountryname($countryCode) {
//        $country = $this->_countryFactory->create()->loadByCode($countryCode);
//        echo $country->getName();
//        return $country;
//    }

    private function getAddress($address) {

        if (!$address) {
            return null;
        }

        $street = $address->getStreet();
        $address_1 = (!is_null($street) && array_key_exists('0', $street)) ? $street['0'] : null;
        $address_2 = (!is_null($street) && array_key_exists('1', $street)) ? $street['1'] : null;

        $addrArray = array_filter(array(
            '_name' => $address->getFirstname() . " " . $address->getLastname(),
            '_address1' => $address_1,
            '_address2' => $address_2,
            '_city' => $address->getCity(),
            '_country' => $address->getCountryId(),
            '_region' => $address->getRegion(),
            '_zipcode' => $address->getPostcode(),
            '_phone' => $address->getTelephone(),
                ), 'strlen');

        if (!$addrArray) {
            return null;
        }
        return $addrArray;
    }

}
