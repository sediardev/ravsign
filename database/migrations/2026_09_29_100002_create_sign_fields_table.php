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
            $table->uuid('uuid')->unique();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('firma');
            $table->unsignedSmallInteger('page');
            $table->decimal('x', 7, 4);
            $table->decimal('y', 7, 4);
            // Field size in PDF points. Editable per field, not just per type, so a
            // signature does not have to be the same size on every document.
            $table->decimal('width', 6, 2);
            $table->decimal('height', 6, 2);
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
