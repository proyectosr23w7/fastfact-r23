<?php

namespace Tests\Unit;

use App\Support\DocumentoIdentidad;
use PHPUnit\Framework\TestCase;

class DocumentoIdentidadTest extends TestCase
{
    public function test_infiere_ci_para_documentos_numericos_de_seis_a_ocho_digitos(): void
    {
        $this->assertSame('1', DocumentoIdentidad::inferirTipo('123456'));
        $this->assertSame('1', DocumentoIdentidad::inferirTipo('12345678'));
    }

    public function test_infiere_nit_para_documentos_numericos_de_nueve_a_once_digitos(): void
    {
        $this->assertSame('5', DocumentoIdentidad::inferirTipo('123456789'));
        $this->assertSame('5', DocumentoIdentidad::inferirTipo('12345678901'));
    }

    public function test_no_infiere_documentos_alfanumericos_o_fuera_de_rango(): void
    {
        $this->assertNull(DocumentoIdentidad::inferirTipo('AB123456'));
        $this->assertNull(DocumentoIdentidad::inferirTipo('12345'));
        $this->assertNull(DocumentoIdentidad::inferirTipo('123456789012'));
    }
}
