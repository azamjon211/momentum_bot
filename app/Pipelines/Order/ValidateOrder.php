<?php
namespace App\Pipelines\Order;

class ValidateOrder
{
    public function handle(array $order, \Closure $next ){
        echo "Validate Order <br/> ";
        return $next($order);
    }
}
