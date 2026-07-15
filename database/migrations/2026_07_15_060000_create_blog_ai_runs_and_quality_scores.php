<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_ai_runs')) {
            Schema::create('blog_ai_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blog_ai_session_id')->constrained('blog_ai_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('blog_post_id')->nullable()->index();
                $table->string('mode', 16)->default('auto');
                $table->string('status', 32)->default('pending');
                $table->string('current_step', 64)->nullable();
                $table->unsignedTinyInteger('progress_pct')->default(0);
                $table->unsignedTinyInteger('live_score')->default(0);
                $table->json('score_breakdown')->nullable();
                $table->json('step_log')->nullable();
                $table->json('revision_counts')->nullable();
                $table->json('input_json')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['status']);
            });
        }

        if (Schema::hasTable('blog_posts')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                if (! Schema::hasColumn('blog_posts', 'ai_quality_score')) {
                    $table->unsignedTinyInteger('ai_quality_score')->nullable()->after('cluster');
                }
                if (! Schema::hasColumn('blog_posts', 'ai_quality_breakdown')) {
                    $table->json('ai_quality_breakdown')->nullable()->after('ai_quality_score');
                }
                if (! Schema::hasColumn('blog_posts', 'ai_run_id')) {
                    $table->unsignedBigInteger('ai_run_id')->nullable()->index()->after('ai_quality_breakdown');
                }
            });
        }

        // Add cross FKs only when both sides exist and the FK is missing.
        if (Schema::hasTable('blog_ai_runs') && Schema::hasTable('blog_posts')) {
            $this->ensureForeignKey('blog_ai_runs', 'blog_post_id', 'blog_posts', 'id', 'null');
            if (Schema::hasColumn('blog_posts', 'ai_run_id')) {
                $this->ensureForeignKey('blog_posts', 'ai_run_id', 'blog_ai_runs', 'id', 'null');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_posts') && Schema::hasColumn('blog_posts', 'ai_run_id')) {
            $this->dropForeignKeyQuietly('blog_posts', 'ai_run_id');
        }

        if (Schema::hasTable('blog_posts')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $cols = array_values(array_filter([
                    Schema::hasColumn('blog_posts', 'ai_quality_score') ? 'ai_quality_score' : null,
                    Schema::hasColumn('blog_posts', 'ai_quality_breakdown') ? 'ai_quality_breakdown' : null,
                    Schema::hasColumn('blog_posts', 'ai_run_id') ? 'ai_run_id' : null,
                ]));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('blog_ai_runs')) {
            $this->dropForeignKeyQuietly('blog_ai_runs', 'blog_post_id');
            Schema::dropIfExists('blog_ai_runs');
        }
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $refTable,
        string $refColumn,
        string $onDelete,
    ): void {
        // Laravel has no portable "hasForeign"; try/catch is enough for idempotent deploy.
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $refTable, $refColumn, $onDelete) {
                $fk = $blueprint->foreign($column)->references($refColumn)->on($refTable);
                if ($onDelete === 'null') {
                    $fk->nullOnDelete();
                } else {
                    $fk->cascadeOnDelete();
                }
            });
        } catch (\Throwable) {
            // FK already exists (or DB rejected a duplicate) — safe to ignore on re-run.
        }
    }

    private function dropForeignKeyQuietly(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // ignore
        }
    }
};
