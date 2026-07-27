<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // nullOnDelete rather than cascade: if a product is later removed
            // from the catalog, past orders should still show what was
            // bought (via the snapshot fields below), not disappear.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot fields: capture name/price/image AT TIME OF PURCHASE,
            // so historical orders stay accurate even if the product is
            // later renamed, repriced, or deleted.
            $table->string('product_name');
            $table->string('product_image')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
