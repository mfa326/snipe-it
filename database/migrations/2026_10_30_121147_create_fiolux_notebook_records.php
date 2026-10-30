<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiolux_notebooks', function (Blueprint $table): void {
            $table->id();
            $table->integer('asset_id')->unique();
            $table->integer('available_status_id');
            $table->integer('pending_status_id');
            $table->unsignedBigInteger('last_cycle_id')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('fiolux_cycles', function (Blueprint $table): void {
            $table->id();
            $table->integer('asset_id')->index();
            $table->integer('user_id')->index();
            $table->integer('open_asset_id')->nullable()->unique();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
        Schema::create('fiolux_inspections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->string('kind', 16);
            $table->uuid('operation_key')->unique();
            $table->string('payload_hash', 64);
            $table->integer('operator_id')->index();
            $table->integer('actionlog_id')->unique();
            $table->string('template_version', 80);
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['cycle_id', 'kind']);
        });
        Schema::create('fiolux_technical_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->uuid('operation_key')->unique();
            $table->string('payload_hash', 64);
            $table->integer('operator_id');
            $table->json('operator_snapshot');
            $table->string('result', 24);
            $table->text('notes');
            $table->timestamps();
        });
        Schema::create('fiolux_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inspection_id')->index();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->string('kind', 16);
            $table->string('state', 16);
            $table->string('path')->nullable()->unique();
            $table->string('sha256', 64)->nullable();
            $table->string('mime', 80)->nullable();
            $table->integer('operator_id');
            $table->integer('checked_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiolux_documents');
        Schema::dropIfExists('fiolux_technical_evaluations');
        Schema::dropIfExists('fiolux_inspections');
        Schema::dropIfExists('fiolux_cycles');
        Schema::dropIfExists('fiolux_notebooks');
    }
};
