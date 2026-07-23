<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Restricts the supplier (warehouse) portal to supplier-login users
 * (users.user_type = 'user_supplier'). Everyone else is rejected, so the
 * cross-store PO data the portal exposes is only reachable by suppliers.
 */
class CheckSupplierLogin
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->user_type !== 'user_supplier' || (int) $user->allow_login !== 1) {
            abort(403, 'Unauthorized action.');
        }

        // A supplier login must be linked to a master supplier; without it
        // the portal has no identity to scope by.
        if (empty($user->common_supplier_id)) {
            abort(403, 'Supplier account is not linked to a supplier record.');
        }

        return $next($request);
    }
}
