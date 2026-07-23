<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a supplier-portal login (users.user_type = 'user_supplier') to
 * the MASTER supplier Contact it represents. This is the supplier's
 * cross-store identity: the portal resolves every store-local supplier
 * clone via contacts.common_supplier_id = users.common_supplier_id, so
 * one login sees the POs raised to that supplier across all stores.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'common_supplier_id')) {
                $table->unsignedInteger('common_supplier_id')->nullable()->after('crm_contact_id');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'common_supplier_id')) {
                $table->dropColumn('common_supplier_id');
            }
        });
    }
};
