<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Confines supplier-portal logins (users.user_type = 'user_supplier') to
 * their own /supplier/* area. Applied to the main authenticated route
 * groups so a supplier cannot reach store/head-office endpoints — e.g.
 * /home/get-totals (business financials) or /purchases/{id} & /sells/{id}
 * (purchase/sell documents) — that are otherwise not permission-gated.
 */
class BlockSupplierPortalUser
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->user_type === 'user_supplier') {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
