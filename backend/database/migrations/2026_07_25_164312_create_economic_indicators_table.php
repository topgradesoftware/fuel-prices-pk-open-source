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
        Schema::create('economic_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('indicator_type')->index();
            $table->decimal('value', 15, 4);
            $table->enum('period', ['daily', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('date')->index();
            $table->string('source')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_revised')->default(false);
            $table->unsignedBigInteger('revision_of_id')->nullable();
            $table->timestamps();
            
            $table->foreign('revision_of_id')->references('id')->on('economic_indicators')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('economic_indicators');
    }
};
