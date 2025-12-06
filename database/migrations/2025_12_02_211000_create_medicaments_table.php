<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medicaments', function (Blueprint $table) {
            $table->id();
            $table->string('gtin')->nullable()->index();
            $table->string('nom')->nullable();
            $table->string('forme_pharmaceutique')->nullable();
            $table->string('voie_administration')->nullable();
            $table->string('firme')->nullable();
            $table->string('commercialise')->nullable();
            $table->string('probleme_disponibilite')->nullable();
            $table->text('especes_cibles')->nullable();
            $table->text('temps_attente')->nullable();
            $table->text('substance_active')->nullable();
            $table->string('code_atc')->nullable();
            $table->string('usage')->nullable();
            $table->text('url_notice_nl')->nullable();
            $table->text('url_notice_fr')->nullable();
            $table->text('url_notice_de')->nullable();
            $table->text('url_skp')->nullable();
            $table->text('url_rcp')->nullable();
            $table->text('url_zma_zmt')->nullable();
            $table->text('url_rma_nl')->nullable();
            $table->text('url_rma_fr')->nullable();
            $table->text('url_rma_de')->nullable();
            $table->text('url_dhcp_nl')->nullable();
            $table->text('url_dhcp_fr')->nullable();
            $table->text('url_dhpc_de')->nullable();
            $table->text('url_summary_rmp_nl')->nullable();
            $table->text('url_summary_rmp_fr')->nullable();
            $table->date('date_publication_rcp')->nullable();
            $table->date('date_publication_rma')->nullable();
            $table->date('date_publication_dhpc')->nullable();
            $table->date('date_publication_summary_rmp')->nullable();
            $table->date('date_approbation_rcp')->nullable();
            $table->date('date_approbation_rma')->nullable();
            $table->date('date_approbation_dhpc')->nullable();
            $table->date('date_approbation_summary_rmp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicaments');
    }
};
