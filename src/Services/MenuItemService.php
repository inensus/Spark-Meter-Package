<?php


namespace Inensus\SparkMeter\Services;

class MenuItemService
{
    public function createMenuItems()
    {
        $menuItem = [
            'name' =>'Spark Meter',
            'url_slug' =>'',
            'md_icon' =>'bolt'
        ];
        $subMenuItems= array();

        $subMenuItem1=[
            'name' =>'Overview',
            'url_slug' =>'/spark-meters/sm-overview',
        ];
        array_push($subMenuItems, $subMenuItem1);
        $subMenuItem2=[
            'name' =>'Meter Models',
            'url_slug' =>'/spark-meters/sm-meter-model/page/1',
        ];
        array_push($subMenuItems, $subMenuItem2);
        $subMenuItem3=[
            'name' =>'Tariffs',
            'url_slug' =>'/spark-meters/sm-tariff/page/1',
        ];
        array_push($subMenuItems, $subMenuItem3);
     
        $subMenuItem4=[
            'name' =>'Customers',
            'url_slug' =>'/spark-meters/sm-customer/page/1',
        ];
        array_push($subMenuItems, $subMenuItem4);

        return ['menuItem'=>$menuItem,'subMenuItems'=>$subMenuItems];


    }
}
