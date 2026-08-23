<?php
namespace App\Pipelines\Order;

class CalculateShipping
{
    public function handle(array $order, \Closure $next ){
        echo "Calculating shipping <br/> ";
        $order['shipping_cost'] = 10;
        return $next($order);
    }
}
