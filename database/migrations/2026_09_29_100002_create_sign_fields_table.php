<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('firma');
            $table->unsignedSmallInteger('page');
            $table->decimal('x', 7, 4);
            $table->decimal('y', 7, 4);
            $table->string('value_path')->nullable();
            $table->string('value_text')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_fields');
    }
};
