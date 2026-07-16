<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('compositions')) {
            Schema::create('compositions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id');
                // Auto-generated as "+"-joined list of salt names; stored for fast lookup/display.
                $table->string('name');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('business_id');
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('compositions');
    }
};
