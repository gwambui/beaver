<?php
/**
 * Created by PhpStorm.
 * User: wambu
 * Date: 2018-04-14
 * Time: 12:57 PM
 */

class ProductBA extends BaseBA
{
    private $pda;
    public function __construct()
    {
        $this->pda = new ProductDA();
    }

    public function GetMainProduct($area)
    {
        return $this->pda->GetMainProduct($area);
    }
}