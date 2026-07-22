<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable the Purchase Order feature for every existing store (business).
 *
 * Purchase Orders are gated per-business by
 * common_settings['enable_purchase_order']. Only the template business
 * had it enabled; this backfills the flag for all other stores so their
 * users get the Purchase Order menu on login. New stores are defaulted
 * on in BusinessUtil::createNewBusiness().
 */
return new class extends Migration
{
    public function up()
    {
        // common_settings is cast to array on the model, but we iterate
        // with the query builder to avoid loading the full model graph.
        DB::table('business')->select('id', 'common_settings')->orderBy('id')
            ->chunkById(100, function ($businesses) {
                foreach ($businesses as $business) {
                    $settings = json_decode($business->common_settings ?? '', true);
                    if (! is_array($settings)) {
                        $settings = [];
                    }

                    if (empty($settings['enable_purchase_order'])) {
                        $settings['enable_purchase_order'] = 1;

                        DB::table('business')
                            ->where('id', $business->id)
                            ->update(['common_settings' => json_encode($settings)]);
                    }
                }
            });
    }

    public function down()
    {
        // Intentionally left as a no-op: we cannot tell which stores had
        // the flag enabled before this migration, so reverting could
        // disable Purchase Orders for stores that always had it on.
    }
};
