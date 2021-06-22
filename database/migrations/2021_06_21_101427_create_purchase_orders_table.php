<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('vendor_id')->index();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('purchase_order_status_id')->default(1);

            $table->string('purchase_order_number');
            $table->string('quote_number');
            $table->date('purchase_order_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('terms')->nullable();
            $table->text('public_notes');
            $table->boolean('is_deleted')->default(false);
            $table->unsignedInteger('frequency_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('last_sent_date')->nullable();

            $table->string('tax_name1');
            $table->decimal('tax_rate1', 15, 3);

            $table->decimal('amount', 15, 2);
            $table->decimal('balance', 15, 2);

            $table->foreign('vendor_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('purchase_order_status_id')->references('id')->on('invoice_statuses');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
            $table->unsignedInteger('purchase_order_design_id')->default(1);
            $table->foreign('purchase_order_design_id')->references('id')->on('invoice_designs');
            $table->unsignedInteger('quote_id')->nullable();
            $table->unsignedInteger('quote_purchase_order_id')->nullable();
            $table->decimal('custom_value1', 15, 2)->default(0);
            $table->decimal('custom_value2', 15, 2)->default(0);

            $table->boolean('custom_taxes1')->default(0);
            $table->boolean('custom_taxes2')->default(0);
            $table->boolean('is_amount_discount')->nullable();
            $table->text('purchase_order_footer')->nullable();
            $table->decimal('partial', 15, 2)->nullable();
            $table->string('custom_text_value1')->nullable();
            $table->string('custom_text_value2')->nullable();
            $table->date('partial_due_date')->nullable();
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
        Schema::dropIfExists('purchase_orders');
    }
}
