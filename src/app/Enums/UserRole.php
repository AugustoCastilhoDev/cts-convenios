<?php

namespace App\Enums;

enum UserRole: string
{
    /**
     * Super administrador: a equipe da Castilho Soluções Digitais. Gerencia prefeituras, usuários e
     * auditoria de todas as prefeituras e é o único que exclui registros. Não pertence a um tenant
     * (tenant_id nulo) — enxerga todos os municípios.
     */
    case AdministradorInterno = 'administrador_interno';

    /**
     * Administrador da prefeitura: a pessoa de confiança do município. Trabalha nos convênios como um
     * gestor e, além disso, cria/desativa os usuários da própria prefeitura, redefine senhas e
     * consulta/exporta a auditoria dela. Nunca enxerga outra prefeitura nem exclui registros.
     */
    case AdministradorPrefeitura = 'administrador_prefeitura';

    /**
     * Servidor da prefeitura: lança convênios, modifica marcos temporais e
     * anexa contratos. Restrito ao próprio tenant.
     */
    case GestorConvenios = 'gestor_convenios';

    /**
     * Órgão de controle interno: acesso somente leitura e exportação de
     * relatórios para auditorias do TCE. Restrito ao próprio tenant.
     */
    case FiscalControleInterno = 'fiscal_controle_interno';

    public function label(): string
    {
        return match ($this) {
            self::AdministradorInterno => 'Super Administrador',
            self::AdministradorPrefeitura => 'Administrador da Prefeitura',
            self::GestorConvenios => 'Gestor de Convênios',
            self::FiscalControleInterno => 'Fiscal de Controle Interno',
        };
    }

    /**
     * Administrador Interno não pertence a nenhuma prefeitura: enxerga
     * todos os tenants (o TenantScope não filtra quando tenant_id é nulo).
     */
    public function exigeTenant(): bool
    {
        return $this !== self::AdministradorInterno;
    }

    /** Papéis que enxergam os convênios da própria prefeitura (e recebem os alertas de prazo). */
    public function consultaConvenios(): bool
    {
        return in_array($this, [self::AdministradorPrefeitura, self::GestorConvenios, self::FiscalControleInterno], true);
    }

    /** Papéis que lançam e alteram convênios, contratos e documentos. */
    public function editaConvenios(): bool
    {
        return in_array($this, [self::AdministradorPrefeitura, self::GestorConvenios], true);
    }

    /**
     * Quem tem poder sobre o acesso de outras pessoas precisa da verificação em duas etapas: o super
     * administrador (todas as prefeituras) e o administrador da prefeitura (todas as contas dela).
     * Para os demais perfis ela é opcional.
     */
    public function exigeDoisFatores(): bool
    {
        return in_array($this, [self::AdministradorInterno, self::AdministradorPrefeitura], true);
    }

    public function administraPrefeitura(): bool
    {
        return $this === self::AdministradorPrefeitura;
    }

    /**
     * Valores dos papéis que trabalham com os convênios de uma prefeitura (para consultas ao banco).
     *
     * @return array<int, string>
     */
    public static function valoresQueConsultamConvenios(): array
    {
        return array_map(
            fn (self $papel) => $papel->value,
            array_values(array_filter(self::cases(), fn (self $papel) => $papel->consultaConvenios())),
        );
    }
}
