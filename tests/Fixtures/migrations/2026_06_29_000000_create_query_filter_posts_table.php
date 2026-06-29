<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_filter_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->unsignedInteger('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_filter_posts');
    }
};
