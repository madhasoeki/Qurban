<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('distribusi')) {
            return;
        }

        if (! Schema::hasColumn('distribusi', 'user_id')) {
            Schema::table('distribusi', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->unique('user_id');
            });
        }

        $legacyTotal = (int) DB::table('distribusi')->whereNull('user_id')->sum('jumlah');

        if ($legacyTotal > 0) {
            $targetUserId = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'distribusi')
                ->where('model_has_roles.model_type', User::class)
                ->value('model_has_roles.model_id');

            if (! $targetUserId) {
                $targetUserId = DB::table('users')->value('id');
            }

            if ($targetUserId) {
                $existing = DB::table('distribusi')->where('user_id', $targetUserId)->first();

                if ($existing) {
                    DB::table('distribusi')
                        ->where('id', $existing->id)
                        ->update([
                            'jumlah' => ((int) $existing->jumlah) + $legacyTotal,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('distribusi')->insert([
                        'user_id' => $targetUserId,
                        'jumlah' => $legacyTotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        DB::table('distribusi')->whereNull('user_id')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('distribusi') || ! Schema::hasColumn('distribusi', 'user_id')) {
            return;
        }

        Schema::table('distribusi', function (Blueprint $table) {
            $table->dropUnique('distribusi_user_id_unique');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
