<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchaseOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('purchase_order_id')->index();
            $table->unsignedInteger('raw_material_id')->nullable();

            $table->string('raw_material_key');
            $table->text('notes');
            $table->decimal('cost', 15,3);
            $table->decimal('qty', 15,3)->nullable();

            $table->string('tax_name1')->nullable();
            $table->decimal('tax_rate1', 15,3)->nullable();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('raw_material_id')->references('id')->on('raw_materials')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            

            $table->unsignedInteger('public_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchase_order_items');
    }
}
