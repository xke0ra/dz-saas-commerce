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
        Schema::create('communes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('wilaya_id');
            $table->string('name_ar');
            $table->string('name_fr');
            $table->string('postal_code', 10)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('wilaya_id')->references('id')->on('wilayas')->cascadeOnDelete();
            $table->index(['wilaya_id', 'name_fr']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
