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
        Schema::create('hewan', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
            $table->integer('berat_awal')->default(0);
            $table->integer('berat_daging')->default(0);
            $table->integer('berat_tulang')->default(0);
            $table->timestamp('mulai_jagal')->nullable();
            $table->timestamp('selesai_jagal')->nullable();
            $table->timestamp('mulai_kuliti')->nullable();
            $table->timestamp('selesai_kuliti')->nullable();
            $table->timestamp('mulai_cacah_daging')->nullable();
            $table->timestamp('selesai_cacah_daging')->nullable();
            $table->timestamp('mulai_cacah_tulang')->nullable();
            $table->timestamp('selesai_cacah_tulang')->nullable();
            $table->timestamp('mulai_jeroan')->nullable();
            $table->timestamp('selesai_jeroan')->nullable();
            $table->timestamp('mulai_packing')->nullable();
            $table->timestamp('selesai_packing')->nullable();
            $table->integer('kantong_packing')->default(0);
            $table->integer('distribusi')->default(0);
            $table->integer('kantong_distribusi')->default(0);
            $table->foreignId('sohibul_id')->constrained('sohibul')->cascadeOnDelete();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
