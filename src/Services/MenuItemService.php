<?php


namespace Inensus\SparkMeter\Services;

use App\Models\MenuItems;

class MenuItemService
{
    private $menuItems;

    public function __construct(MenuItems $menuItems)
    {
        $this->menuItems = $menuItems;
    }
    public function createMenuItems()
    {
        $menuItem= $this->menuItems->newQuery()->where('name','Spark Meter')->first();

        if (!$menuItem){
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
                'name' =>'Sites',
                'url_slug' =>'/spark-meters/sm-site/page/1',
            ];
            array_push($subMenuItems, $subMenuItem2);


            $subMenuItem3=[
                'name' =>'Meter Models',
                'url_slug' =>'/spark-meters/sm-meter-model/page/1',
            ];
            array_push($subMenuItems, $subMenuItem3);

            $subMenuItem4=[
                'name' =>'Tariffs',
                'url_slug' =>'/spark-meters/sm-tariff/page/1',
            ];
            array_push($subMenuItems, $subMenuItem4);

            $subMenuItem5=[
                'name' =>'Customers',
                'url_slug' =>'/spark-meters/sm-customer/page/1',
            ];
            array_push($subMenuItems, $subMenuItem5);

            return ['menuItem'=>$menuItem,'subMenuItems'=>$subMenuItems];
        }else{
            return [];
        }

    }
}
