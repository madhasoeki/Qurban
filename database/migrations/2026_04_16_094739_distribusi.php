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
        Schema::table('hewan', function (Blueprint $table) {
            $table->dropColumn(['distribusi', 'kantong_distribusi']);
        });

        Schema::create('distribusi', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('jumlah')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribusi');

        Schema::table('hewan', function (Blueprint $table) {
            $table->integer('distribusi')->default(0);
            $table->integer('kantong_distribusi')->default(0);
        });
    }
};
