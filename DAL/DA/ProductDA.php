<?php
/**
 * Created by PhpStorm.
 * User: wambu
 * Date: 2018-04-14
 * Time: 12:59 PM
 */

class ProductDA extends  BaseDA
{
    private $db;

    public function __construct()
    {
        $this->db = getDBInstance();
    }
    public function GetMainProduct($area)
    {
       $bindParms = array("Area"=>$area);
       $rows = $this->db->GetArrayList("call NavMain(:Area);", $bindParms);
       return $rows;
    }
    public function GetSubProduct($area, $callname)
    {
        $bindParms = array("Area"=>$area, "Callname"=>$callname);
        $rows = $this->db->GetArrayList("call NavList(:Area, :Callname);", $bindParms);
        return $rows;
    }

}