<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use mysql_xdevapi\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        dump('salom: ' . $role);
        $user = ['id'=>123, 'name'=> 'James', 'role' => 'admin'];
        if($user['role'] == $role)
        return $next($request);
        abort(404);
    }
}
