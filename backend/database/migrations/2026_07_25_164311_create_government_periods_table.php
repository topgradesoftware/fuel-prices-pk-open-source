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
        Schema::create('government_periods', function (Blueprint $table) {
            $table->id();
            $table->string('government_name');
            $table->string('pm_name');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('type', ['elected', 'caretaker', 'interim'])->default('elected');
            $table->string('source')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('government_periods');
    }
};
