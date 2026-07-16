<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('products', 'composition_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('composition_id')->nullable()->after('brand_id');
                $table->index('composition_id');
                $table->foreign('composition_id')->references('id')->on('compositions')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('products', 'composition_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['composition_id']);
                $table->dropIndex(['composition_id']);
                $table->dropColumn('composition_id');
            });
        }
    }
};
