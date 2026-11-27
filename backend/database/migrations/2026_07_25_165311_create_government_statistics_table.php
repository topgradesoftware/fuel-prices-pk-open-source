<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('government_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_period_id')->constrained()->cascadeOnDelete();
            $table->string('metric_name')->index();
            $table->decimal('value', 15, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('government_statistics');
    }
};
