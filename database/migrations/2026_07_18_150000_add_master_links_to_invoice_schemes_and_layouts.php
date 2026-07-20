<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centralised invoice schemes/layouts for the multi-store chain.
 *
 * A "master" scheme/layout lives in the template (super admin's)
 * business. Assigning it to a store business creates a local mirror
 * row carrying master_invoice_scheme_id / master_invoice_layout_id.
 * The mirror keeps store-side dropdowns working, while the invoice
 * COUNTER always lives on the master row — that single counter is
 * what produces one gapless serial series across every store that
 * shares a GST number (state-wise GST filing).
 *
 * gst_number/state_name are set on master schemes only and document
 * which GSTIN a series belongs to; assignment validates a store's
 * business.tax_number_1 against it.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('invoice_schemes', function (Blueprint $table) {
            $table->unsignedInteger('master_invoice_scheme_id')->nullable()->index()->after('is_default');
            $table->string('gst_number', 30)->nullable()->after('master_invoice_scheme_id');
            $table->string('state_name', 100)->nullable()->after('gst_number');
        });

        Schema::table('invoice_layouts', function (Blueprint $table) {
            $table->unsignedInteger('master_invoice_layout_id')->nullable()->index()->after('is_default');
        });
    }

    public function down()
    {
        Schema::table('invoice_schemes', function (Blueprint $table) {
            $table->dropColumn(['master_invoice_scheme_id', 'gst_number', 'state_name']);
        });

        Schema::table('invoice_layouts', function (Blueprint $table) {
            $table->dropColumn(['master_invoice_layout_id']);
        });
    }
};
