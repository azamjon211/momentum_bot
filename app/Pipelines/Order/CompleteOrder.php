<?php
namespace App\Pipelines\Order;

class CompleteOrder
{
    public function handle(array $order, \Closure $next ){
        echo "Order Completed <br/> ";
        $order['status'] = 'completed';
        return $next($order);
    }
}
