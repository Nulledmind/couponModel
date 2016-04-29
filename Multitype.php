<?php

class Application_Model_Coupon_Multitype
{
    /** @var Application_Model_Coupon The current coupon object. */
    protected $coupon;
    /** @var array[] JSON created array containing all of the discounts. */
    private $discounts;
    /** @var string[] Array based on the qualifier section per discount, concat */
    private $qualifier;
    /** @var string[] Array of all types of *currently possible qualifying types */
    private $qTypes = array(
        "models"  => "getSkuModelId",
        "mfg"     => "getModelMfgId",
        "grade"   => "getSkuGradeId",
        "classes" => "getModelClassId",
        "brands"  => "getModelBrandId",
        'strike'  => "getSkuStrikethruPrice",
        "special" => "getIsSpecial",
        "sku"     => "getSkuId",
        "tag"     => "getTag",
        "format"  => "getFormat"
    );

    public function __construct(Application_Model_Coupon $coupon, $data)
    {
        $this->coupon    = $coupon;
        $this->discounts = json_decode($coupon->getPluginData())->multi;
        $this->getList();
    }


    /**
     * Concats qualifying values
     *
     * This will return the list of qualifiers for the specified type
     * @param string $type name of the param to use
     * @return array returns all of the qualifiers under the specified type ($type)
     */
    private function getList()
    {
        foreach ($this->qTypes as $qType => $func) {
            $return = array();
            foreach ($this->discounts as $discount) {
                if (isset($discount->qualifier)) {
                    foreach ($discount->qualifier as $discSet) {
                        if (isset($discSet->$qType)) {
                            $return = array_merge($return, $discSet->$qType);
                        }
                    }
                } if (isset($discount->$qType)) {
                    $return = array_merge($return, $discount->$qType);
                }
            }
            $this->$qType = $return;
        }
    }


    /**
     * Checks if item is highest priced qualified in cart
     *
     * This will true/false depending on if the item is the highest priced qualified item in the cart
     * @param Application_Model_Cartitem $item The item element to compare with
     * @param Application_Model_Cartitem[] $cart array of items
     * @return bool Returns true if highest priced, false if not
     */
    private function isHighest(Application_Model_Cartitem $item, array $cart)
    {
        $isHigh    = false;
        $prices    = array();
        $itemPrice = $item->getOriginalPrice();
        $itemValid = $this->doPrice($this->discounts, $item, $cart)['valid'];
        foreach ($cart as $compare) {
            $comp      = $this->doPrice($this->discounts, $compare, $cart);
            $compValid = $comp['valid'];
            if ($compValid) {
                $prices[$compare->getSkuId()] = $compare->getOriginalPrice();
            }
        }
        if (empty($prices)) {
            return false;
        }
        $highPrice = max($prices);
        $validItems = array();
        foreach ($cart as $sku => $cartItem) {
            if ($cartItem->getOriginalPrice() == $highPrice && $this->doPrice($this->discounts, $cartItem, $cart)['valid']) {
                $validItems[$sku] = $cartItem;
            }
        }
        usort(
            $validItems,
            function (Application_Model_Cartitem $left, Application_Model_Cartitem $right) {
                $qty = $left->getQty() - $right->getQty();
                if ($qty) {
                    return $qty;
                }
                return $left->getSkuId() - $right->getSkuId();
            }
        );
        $highestItem = reset($validItems);

        return $item->getSkuId() == $highestItem->getSkuId();
    }

    /**
     * Checks if item is lowest priced qualified in cart
     *
     * This will true/false depending on if the item is the lowest priced qualified item in the cart
     * @param Application_Model_Cartitem $item The item element to compare with
     * @param Application_Model_Cartitem[] $cart array of items
     * @return bool Returns true if lowest priced, false if not
     */
    private function isLowest(Application_Model_Cartitem $item, array $cart)
    {
        $isHigh    = false;
        $prices    = array();
        $itemPrice = $item->getOriginalPrice();
        $itemValid = $this->doPrice($this->discounts, $item, $cart)['valid'];
        foreach ($cart as $compare) {
            $comp      = $this->doPrice($this->discounts, $compare, $cart);
            $compValid = $comp['valid'];
            if ($compValid) {
                $prices[$compare->getSkuId()] = $compare->getOriginalPrice();
            }
        }
        if (empty($prices)) {
            return false;
        }
        $lowPrice = min($prices);
        $validItems = array();
        foreach ($cart as $sku => $cartItem) {
            if ($cartItem->getOriginalPrice() == $lowPrice
                && $this->doPrice($this->discounts, $cartItem, $cart)['valid']) {
                $validItems[$sku] = $cartItem;
            }
        }
        usort(
            $validItems,
            function (Application_Model_Cartitem $left, Application_Model_Cartitem $right) {
                $qty = $left->getQty() - $right->getQty();
                if ($qty) {
                    return $qty;
                }
                return $left->getSkuId() - $right->getSkuId();
            }
        );
        $lowestItem = reset($validItems);

        return $item->getSkuId() == $lowestItem->getSkuId();
    }

    /**
     * Checks if item is to be discounted if qualified in cart
     *
     * This will true/false depending on if the item is the second highest priced qualified item in the cart
     * @param Application_Model_Cartitem $item The item element to compare with
     * @param Application_Model_Cartitem[] $cart array of items
     * @return bool Returns true if item determined, false if not
     */
    private function isDiscountItem(Application_Model_Cartitem $item, array $cart)
    {
        $isHigh    = false;
        $prices    = array();
        $itemPrice = $item->getOriginalPrice();
        $itemValid = $this->doPrice($this->discounts, $item, $cart)['valid'];
        foreach ($cart as $compare) {
            $comp      = $this->doPrice($this->discounts, $compare, $cart);
            $compValid = $comp['valid'];
            if ($compValid) {
                $prices[$compare->getSkuId()] = $compare->getOriginalPrice();
            }
        }
        if (empty($prices)) {
            return false;
        }
        $lowPrice   = min($prices);
        $highPrice  = max($prices);
        $validItems = array();
        foreach ($cart as $sku => $cartItem) {
            if ($this->doPrice($this->discounts, $cartItem, $cart)['valid']) {
                $validItems[$sku] = $cartItem;
            }
        }
        usort(
            $validItems,
            function (Application_Model_Cartitem $left, Application_Model_Cartitem $right) {
                $price = $left->getPrice() - $right->getPrice();
                if ($price) {
                    return -$price;
                }
                return $left->getSkuId() - $right->getSkuId();
            }
        );

        if (reset($validItems)->getQty() > 1) {
            return reset($validItems)->getSkuId() == $item->getSkuId();
        }

        return $item->getSkuId() == $validItems[1]->getSkuId();
    }

    /**
     * Checks if item is valid
     *
     * Checks if given item is valid against given qualifiers
     * @param Application_Model_Cartitem $item The current item for validation
     * @param Array $qualifiers an associatve array of qualifier names and values
     * @return bool Is item valid or not
     */
    private function isItemValid(Application_Model_Cartitem $item, $qualifiers = array())
    {
        if (get_object_vars($qualifiers)) {
            $qualifiers = get_object_vars($qualifiers);
        }
        $expected = count($qualifiers);
        $actual   = 0;
        $includeNew = $this->coupon->getIncludeNew();

        foreach ($qualifiers as $qName => $qValues) {
            if (!isset($this->qTypes[$qName])) {
                $expected--;
                continue;
            }
            $itemValue = $item->{$this->qTypes[$qName]}();
            if (( is_array($qValues)
                && in_array($itemValue, $qValues))
                || ($qValues == $itemValue )) {
                $actual++;
            }
        }

        if ($item->getSkuGradeId() == 1 && !$includeNew) {
            return false;
        }
        return $actual == $expected;
    }

    /**
     * Calculates the cart cost
     *
     * Calculates the total amount of the given cart, with option for qualified items only (Pre-discount)
     * @param Application_Model_Cart|array $cart The items to iterate for cost
     * @param bool $qualified Determines if cost should be counted with qualified items only
     * @param Array $qualifiers an associatve array of qualifier names and values
     * @return integer Total cost of items
     */
    private function cartCost($cart, $qualified = false, $qualifiers = array())
    {
        $items     = ( $cart instanceof Application_Model_Cart )
                   ? $cart->getItemsArray()
                   : $cart;
        $cartTotal = 0;
        $includeNew = $this->coupon->getIncludeNew();

        foreach ($items as $item) {
            if ($qualified && !$this->isItemValid($item, $qualifiers)) {
                continue;
            }
            if ($item->getSkuGradeId() == 1 && !$includeNew) {
                continue;
            }
            $cartTotal += $item->getOriginalPrice() * $item->getQty();
        }

        return $cartTotal;
    }

    /**
     * Counts cart items
     *
     * Calculates the amount of items in the cart, excluding new items
     * @param array|Application_Model_Cart $cart array of items
     * @param bool $qualified Determines if cart should be counted with qualified items only
     * @param array $qualifiers Associative rray of qualifiers
     * @return integer Number of items in cart
     */
    private function cartCount($cart, $qualified = false, $qualifiers = array())
    {
        $validCount = 0;
        $includeNew = $this->coupon->getIncludeNew();
        $items      = ( $cart instanceof Application_Model_Cart )
                    ? $cart->getItemsArray()
                    : $cart;

        foreach ($items as $item) {
            if ($qualified && !$this->isItemValid($item, $qualifiers)) {
                continue;
            }
            if ($item->getSkuGradeId() == 1 && !$includeNew) {
                continue; // Skip new items, if not including new
            }
            $validCount += $item->getQty();
        }

        return $validCount;
    }

    /**
     * getFreeShipping Overload
     *
     * This returns the free shipping items listed from either discount,
     * or discount->qualifier(If minCartTotal exists and is met)
     * @param Application_Model_Cart $cart The cart
     * @return array Returns array with freeshipping consts if exists
     */
    public function getFreeShipping(Application_Model_Cart $cart = null)
    {
        foreach ($this->discounts as $discount) {
            if (isset($discount->freeShipping)) {
                return explode(',', $discount->freeShipping);
            }
            if (isset($discount->qualifier)) {
                foreach ($discount->qualifier as $discSet) {
                    if (isset($cart)) {
                        if (isset($discSet->freeShipping)) {
                            if (( isset($discSet->minCartTotal) && $this->cartCost($cart, false, $discSet) >= $discSet->minCartTotal )) {
                                return explode(',', $discSet->freeShipping);
                            }
                            if (!isset($discSet->minCartTotal)) {
                                return explode(',', $discSet->freeShipping);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Calculates price
     *
     * This calculates the prices, and determines if the item is valid for the coupon
     * @param array $data The item element to compare with
     * @param Application_Model_Cartitem $item the item to calc price
     * @param Application_Model_Cartitem[] $cart array of items
     * @return array Returns array with keys amt(Calculated price), valid(Is item valid for cart).
     */
    private function doPrice($data, Application_Model_Cartitem $item, array $cart = array())
    {
        $discountAmts = array();

        if (isset($data)) {

            foreach ($data as $discount) {

                $qualified = false;

                if (isset($discount->qualifier)) {
                    foreach ($discount->qualifier as $discSet) {
                        if (isset($discSet->minCartTotal)
                            && $this->cartCost(
                                $cart,
                                ($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_QUALIFIED),
                                $discSet
                            ) < $discSet->minCartTotal) {
                            continue;
                        }
                        foreach ($cart as $tmpItem) {
                            if ($this->isItemValid($tmpItem, $discSet)) {
                                $qualified = true;
                            }
                        }
                    }
                }
                if ((isset($discount->qualifier) && $qualified == true ) || ( !isset($discount->qualifier))) {
                    $isPresent = array();
                    foreach ($this->qTypes as $qType => $qFunc) {
                        $isPresent[$qType] = (isset($discount->$qType)) ? true : false;
                    }

                    $dCount = array(
                        "count" => 0,
                        "vals" => array()
                    );

                    if (isset($discount->excludeCat)) {
                        $cats = Application_Model_Category::FindByModelId($item->getSkuModelId());
                        foreach ($cats as $cat) {
                            if (in_array($cat->getId(), $discount->excludeCat)) {
                                return null;
                            }
                        }
                    }
                    foreach ($discount as $key => $value) {
                        if (in_array($key, array("models", "mfg", "grade", "classes", "brands", "strike", "sku", "format", "tag"))) {
                            $dCount['count']++;
                            $dCount['vals'][] = $key;
                        }
                    }//endforeach

                    if ($dCount['count'] > 1) {
                        $matchCount = 0;
                        foreach ($dCount['vals'] as $val) {
                            if ($val == "tag") {
                                $tmpValue = $item->{$this->qTypes[$val]}();
                                if (in_array($discount->$val[0], $tmpValue)) {
                                    $matchCount++;
                                }
                            } else {
                                $tmpValue = $item->{$this->qTypes[$val]}();
                                if (in_array($tmpValue, $discount->$val)) {
                                    $matchCount++;
                                }
                            }
                        }
                        if ($matchCount == $dCount['count']) {
                            if (isset($discount->minCartTotal)
                                && $this->cartCost(
                                    $cart,
                                    ($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_QUALIFIED),
                                    $discount
                                ) < $discount->minCartTotal) {
                                //
                            } else {
                                $discountAmts[] = $discount->discount;
                            }
                        }
                    } else {
                        foreach ($this->qTypes as $qType => $qFunc) {
                            if ($isPresent[$qType]) {
                                if (isset($discount->minCartTotal)
                                    && $this->cartCost(
                                        $cart,
                                        ($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_QUALIFIED),
                                        $discount
                                    ) < $discount->minCartTotal ) {
                                    continue;
                                }
                                if ($qType == "tag") {
                                    $qVals = $discount->$qType;
                                    if (in_array($qVals[0], $item->{$qFunc}())) {
                                        $discountAmts[] = $discount->discount;
                                    }
                                } else {
                                    if (in_array($item->{$qFunc}(), $discount->$qType)) {
                                        $discountAmts[] = $discount->discount;
                                    }
                                }
                            }
                        }
                    }
                }

            }
            if (count($discountAmts) > 0) {
                $high = (float)max($discountAmts);
            } else {
                $high = 0;
            }
        }

        if (($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_HIGHEST ||
              $this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_LOWEST) &&
              $high != 0) {
            $high = ($high / $item->getQty());
        }
        return array('amt' => $item->getOriginalPrice() * (1 - ($high)), 'valid' => ($high > 0));
    }

    public function isValid(Application_Model_Cart $cart)
    {
        $mFound = false;
        $mMsg   = null;
        foreach ($this->discounts as $discount) {
            $qFilters = array();
            $pFilters = array();

            if (isset($discount->qualifier)) {
                $msg      = null;
                $found    = false;
                foreach ($discount->qualifier as $qId => $qualifier) {
                    if (isset($qualifier->minCartQty) && $this->cartCount($cart, true, $qualifier) < $qualifier->minCartQty) {
                        $msg = "Not enough qualifying items required for Coupon {$this->coupon->getCode()}.";
                        continue;
                    }
                    if (isset($qualifier->minCartTotal) && $this->cartCost($cart, true, $qualifier) < $qualifier->minCartTotal) {
                        $msg = "Total cost of qualifying items is not high enough for this coupon {$this->coupon->getCode()}.";
                        continue;
                    }
                    $found = true;
                }

                if (!$found) {
                    $mMsg = $msg;
                    continue;
                }

            }


            if (isset($discount->minCartQty) && $this->cartCount($cart) < $discount->minCartQty) {
                $mMsg = "Not enough items required for Coupon {$this->coupon->getCode()}.";
                continue;
            }

            if (isset($discount->minCartTotal)
                && $this->cartCost(
                    $cart,
                    ($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_QUALIFIED),
                    $discount
                ) < $discount->minCartTotal) {
                $mMsg = "Total cost of qualifying items is not high enough for this coupon {$this->coupon->getCode()}.";
                continue;
            }

            $mFound = true;

            foreach ($this->qTypes as $qType => $qFunc) {
                if (isset($discount->$qType) && $discount->$qType) {
                    $pFilters[$qType] = $discount->$qType;
                }
                if (isset($discount->qualifier)) {
                    foreach ($discount->qualifier as $index => $discSet) {
                        if (isset($discSet->$qType)) {
                            $qFilters[$index][$qType] = $discSet->$qType;
                        }
                    }
                }
            }
            $isQualified = false;
            $isItemValid = false;
            foreach ($cart->getItemsArray() as $item) {
                $qData = array();
                $tCount = 0;
                $tExpected = count($pFilters);

                if (isset($discount->excludeCat)) {
                    $cats = Application_Model_Category::FindByModelId($item->getSkuModelId());
                    foreach ($cats as $cat) {
                        if (in_array($cat->getId(), $discount->excludeCat)) {
                            $mMsg = "Sorry, Overstock items are not available with this coupon. <small><b> (Overstock items may exist on other pages) </b></small>";

                            continue 2;
                        }
                    }
                }

                foreach ($qFilters as $key => $value) {
                    $count = 0;
                    $expected = count($qFilters[$key]);
                    foreach ($value as $k => $v) {
                        if (in_array($item->{$this->qTypes[$k]}(), $v)) {
                            if (( $this->coupon->getIncludeNew())
                                || ($this->coupon->getIncludeAll())
                                || ($item->getSkuGradeId() >= 2 && $item->getSkuGradeId() <= 8)) {
                                $count++;
                            }
                        }
                    }
                    $qData[] = array(
                        'count' => $count,
                        'expected' => $expected
                    );
                }
                foreach ($pFilters as $key => $value) {
                    if ($key == "tag") {
                        if (in_array($value[0], $item->{$this->qTypes[$key]}())) {
                            if (( $this->coupon->getIncludeNew())
                                || ( $this->coupon->getIncludeAll())
                                || ( $item->getSkuGradeId() >= 2 && $item->getSkuGradeId() <= 8)) {
                                $tCount++;
                            }
                        }
                    } else {
                        if (in_array($item->{$this->qTypes[$key]}(), $value)) {
                            if (($this->coupon->getIncludeNew())
                                || ($this->coupon->getIncludeAll())
                                || ($item->getSkuGradeId() >= 2 && $item->getSkuGradeId() <= 8)) {
                                $tCount++;
                            }
                        }
                    }
                }

                if (count($qData) > 0) {
                    foreach ($qData as $data) {
                        if ($data['count'] == $data['expected']) {
                            $isQualified = true;
                        }
                    }
                } else {
                    $isQualified = true;
                }
                if ($tExpected) {
                    if ($tCount == $tExpected) {
                        $isItemValid = true;
                    }
                }

            }

            if ($isQualified && $isItemValid) {
                return null;
            }
        }

        if ($mMsg) {
            return $mMsg;
        }

        return "No items in your cart are valid for Coupon {$this->coupon->getCode()}";
    }

    public function getPrice(Application_Model_Cartitem $item, array $items)
    {
        $coupon_price = null;
        $cats = Application_Model_Category::FindByModelId($item->getSkuModelId());
        foreach ($cats as $cat) {
            if (in_array($cat->getId(), $discount->excludeCat)) {
                return null;
            }
        }

        if (in_array($item->getModelClassId(), $this->classes)
                || (in_array($item->getModelMfgId(), $this->mfg))
                || (in_array($item->getSkuModelId(), $this->models))
                || (in_array($item->getSkuGradeId(), $this->grade))
                || (in_array($item->getModelBrandId(), $this->brands))
                || (in_array($item->getSkuStrikethruPrice(), $this->strike))
                || (in_array($item->getFormat(), $this->format))
                || (in_array($item->getSkuId(), $this->sku))
                || (in_array($this->tag[0], $item->getTag()))
                || (in_array($item->getIsSpecial(), $this->special))
                || (isset($this->minCartQty) && $this->cartCount($items) >= $this->minCartQty)) {
            if ($this->coupon->getIncludeNew()
                || ($this->coupon->getIncludeAll())
                || ($item->getSkuGradeId() >= 2 && $item->getSkuGradeId() <= 8)) {
                if ($this->coupon->getDiscountPercent() > 0) {
                    if ($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_HIGHEST) {
                        if ($this->isHighest($item, $items)) {
                            return $this->doPrice($this->discounts, $item, $items)['amt'];
                        }
                    } elseif ($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_LOWEST) {
                        if ($this->isLowest($item, $items)) {
                            return $this->doPrice($this->discounts, $item, $items)['amt'];
                        }
                    } elseif ($this->coupon->getApplyToItem() == Application_Model_Coupon::APPLY_TO_ITEM_SECOND) {
                        if ($this->isDiscountItem($item, $items)) {
                            return $this->doPrice($this->discounts, $item, $items)['amt'];
                        }
                    } else {
                        $coupon_price = $this->doPrice($this->discounts, $item, $items)['amt'];
                    }
                }
            }
        }
        return $coupon_price;
    }
}
