<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_moderation_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('trace_id');
            $table->uuid('invocation_id')->nullable();
            $table->uuid('first_invocation_id')->nullable();
            $table->uuid('second_invocation_id')->nullable();
            $table->string('policy', 128)->nullable();
            $table->string('check', 128);
            $table->string('outcome', 16);
            $table->text('reason')->nullable();
            $table->json('violations')->nullable();
            $table->string('label', 128)->nullable();
            $table->json('answers')->nullable();
            $table->json('details')->nullable();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->string('actor_type', 64)->nullable();
            $table->string('actor_id', 128)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->unsignedTinyInteger('pass');
            $table->unsignedInteger('duration_ms');
            $table->text('error')->nullable();
            $table->text('request_url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('trace_id');
            $table->index('invocation_id');
            $table->index(['check', 'created_at']);
            $table->index(['outcome', 'created_at']);
            $table->index(['policy', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_moderation_audits');
    }
};
